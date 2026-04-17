(function() {
    'use strict';

    // Common variables
    const scAiAjaxUrl = typeof scAiData !== 'undefined' ? scAiData.ajaxUrl : (ajaxurl || '/wp-admin/admin-ajax.php');
    const scAiNonce = typeof scAiData !== 'undefined' ? scAiData.nonce : '';
    let scAiCurrentPage = 1;
    let scAiCurrentFilter = 'all';
    const scAiPerPage = 20;

    /**
     * Load status table
     */
    function scAiLoadTable(page, filter) {
        page = page || 1;
        filter = filter || 'all';
        scAiCurrentPage = page;
        scAiCurrentFilter = filter;

        const tbody = document.getElementById('sc-ai-table-body');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align: center;">Loading...</td></tr>';

        fetch(scAiAjaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'sc_ai_get_status_table',
                nonce: scAiNonce,
                page: page,
                per_page: scAiPerPage,
                filter: filter,
            }),
        })
        .then(r => r.json())
        .then(response => {
            if (response.success) {
                scAiRenderTable(response.data);
            } else {
                tbody.innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align: center; color: #d63638;">Error: ' + response.data + '</td></tr>';
            }
        })
        .catch(err => {
            tbody.innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align: center; color: #d63638;">Error: ' + err.message + '</td></tr>';
        });
    }

    /**
     * Render table data
     */
    function scAiRenderTable(data) {
        if (typeof data === 'undefined') data = {};
        const tbody = document.getElementById('sc-ai-table-body');
        if (!tbody) return;

        if (!data.questions || data.questions.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align: center;">No questions found.</td></tr>';
            const pageControls = document.getElementById('sc-ai-page-controls');
            if (pageControls) pageControls.innerHTML = '';
            return;
        }

        let html = '';
        data.questions.forEach(q => {
            let statusBadge = '';
            if (q.status === 'done' || q.status === 'complete') {
                statusBadge = '<span style="background: #00a32a; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px;">COMPLETE</span>';
            } else {
                statusBadge = '<span style="background: #646970; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px;">PENDING</span>';
            }

            html += '<tr>';
            html += '<td style="padding: 8px; border-bottom: 1px solid #e5e5e5;"><input type="checkbox" class="sc-ai-item-checkbox" data-id="' + q.id + '"></td>';
            html += '<td style="padding: 8px; border-bottom: 1px solid #e5e5e5; min-width: 500px;"><a href="' + q.edit_link + '" style="color: #2271b1; text-decoration: none;">' + q.title + '</a></td>';
            html += '<td style="padding: 8px; border-bottom: 1px solid #e5e5e5; width: 80px;">' + statusBadge + '</td>';
            html += '<td style="padding: 8px; border-bottom: 1px solid #e5e5e5; font-size: 12px; color: #646970; width: 110px;">' + (q.generated_time || '—') + '</td>';
            html += '<td style="padding: 8px; border-bottom: 1px solid #e5e5e5; width: 100px;">';
            if (q.status === 'none' || q.status === 'pending') {
                html += '<button class="button button-small sc-ai-gen" data-id="' + q.id + '" style="font-size: 11px; padding: 3px 8px;">✨ Generate</button>';
            } else {
                html += '<span style="color: #00a32a; font-size: 11px;">✓ Done</span>';
            }
            html += '</td>';
            html += '</tr>';
        });

        tbody.innerHTML = html;

        // Update pagination
        const totalPages = Math.ceil(data.total / scAiPerPage);
        scAiRenderPagination(scAiCurrentPage, totalPages, data.total);

        // Add action listeners
        document.querySelectorAll('.sc-ai-gen').forEach(btn => {
            btn.addEventListener('click', function() {
                scAiGenerate(this.dataset.id);
            });
        });

        document.querySelectorAll('.sc-ai-delete').forEach(btn => {
            btn.addEventListener('click', function() {
                scAiDeleteQuestion(this.dataset.id);
            });
        });

        document.querySelectorAll('.sc-ai-item-checkbox').forEach(cb => {
            cb.addEventListener('change', scAiUpdateBatchButton);
        });
    }

    /**
     * Delete question
     */
    function scAiDeleteQuestion(postId) {
        scAiShowModal({
            title: 'Delete Question',
            message: 'Are you sure you want to delete this question? This action cannot be undone.',
            confirmText: 'Delete',
            cancelText: 'Cancel',
            onConfirm: () => {
                fetch(scAiAjaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'sc_ai_delete_question',
                        nonce: scAiNonce,
                        post_id: postId,
                    }),
                })
                .then(r => r.json())
                .then(response => {
                    if (response.success) {
                        scAiShowToast('Question deleted successfully', 'success');
                        scAiLoadTable(scAiCurrentPage, scAiCurrentFilter);
                    } else {
                        scAiShowToast('Error: ' + (response.data || 'Unknown error'), 'error');
                    }
                })
                .catch(err => scAiShowToast('Error: ' + err.message, 'error'));
            }
        });
    }

    /**
     * Render pagination
     */
    function scAiRenderPagination(currentPage, totalPages, total) {
        const pageControls = document.getElementById('sc-ai-page-controls');
        if (!pageControls) return;

        let html = '<span class="displaying-num">' + total + ' items</span>';
        html += '<span class="pagination-links">';
        
        if (currentPage > 1) {
            html += '<button class="button sc-ai-prev-page" aria-label="Previous page">&lsaquo;</button>';
        }
        
        html += '<span class="paging-input">Page <input type="number" class="sc-ai-page-input" value="' + currentPage + '" min="1" max="' + totalPages + '" style="width: 50px; text-align: center;"> of ' + totalPages + '</span>';
        
        if (currentPage < totalPages) {
            html += '<button class="button sc-ai-next-page" aria-label="Next page">&rsaquo;</button>';
        }
        
        html += '</span>';
        
        pageControls.innerHTML = html;
        
        pageControls.querySelector('.sc-ai-prev-page')?.addEventListener('click', () => scAiLoadTable(scAiCurrentPage - 1, scAiCurrentFilter));
        pageControls.querySelector('.sc-ai-next-page')?.addEventListener('click', () => scAiLoadTable(scAiCurrentPage + 1, scAiCurrentFilter));
        
        pageControls.querySelector('.sc-ai-page-input')?.addEventListener('change', function() {
            let newPage = parseInt(this.value);
            if (newPage < 1) newPage = 1;
            if (newPage > totalPages) newPage = totalPages;
            if (newPage !== currentPage) {
                scAiLoadTable(newPage, scAiCurrentFilter);
            }
        });
        
        pageControls.querySelector('.sc-ai-page-input')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.dispatchEvent(new Event('change'));
            }
        });
    }

    /**
     * Update batch button
     */
    function scAiUpdateBatchButton() {
        const selected = document.querySelectorAll('.sc-ai-item-checkbox:checked').length;
        const btn = document.getElementById('sc-ai-batch-generate');
        if (selected > 0) {
            btn.style.display = 'inline-block';
            btn.textContent = 'Generate Selected (' + selected + ')';
        } else {
            btn.style.display = 'none';
        }
    }

    /**
     * Batch generate
     */
    function scAiBatchGenerate(questionIds) {
        scAiShowModal({
            title: 'Batch Generate Content',
            message: 'Generate AI content for ' + questionIds.length + ' selected questions?',
            confirmText: 'Generate All',
            onConfirm: () => {
                fetch(scAiAjaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'sc_ai_final_batch_manual',
                        nonce: scAiNonce,
                        batch: questionIds.length,
                    }),
                })
                .then(r => r.json())
                .then(response => {
                    if (response.success) {
                        scAiShowToast('Batch generation started for ' + questionIds.length + ' questions', 'success');
                        setTimeout(() => scAiLoadTable(scAiCurrentPage, scAiCurrentFilter), 3000);
                    } else {
                        scAiShowToast('Error: ' + (response.data || 'Unknown error'), 'error');
                    }
                })
                .catch(err => scAiShowToast('Error: ' + err.message, 'error'));
            }
        });
    }

    /**
     * Show custom modal dialog
     */
    function scAiShowModal(options) {
        const { title, message, onConfirm, onCancel, confirmText = 'Confirm', cancelText = 'Cancel' } = options;
        
        // Create modal overlay
        const overlay = document.createElement('div');
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999998;
            display: flex;
            align-items: center;
            justify-content: center;
        `;
        
        // Create modal
        const modal = document.createElement('div');
        modal.style.cssText = `
            background: white;
            border-radius: 8px;
            padding: 24px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            animation: slideIn 0.2s ease-out;
        `;
        
        modal.innerHTML = `
            <h3 style="margin: 0 0 16px 0; font-size: 18px; color: #1d2327;">${title}</h3>
            <p style="margin: 0 0 24px 0; color: #3c434a; line-height: 1.5;">${message}</p>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button class="button" style="padding: 8px 16px;">${cancelText}</button>
                <button class="button button-primary" style="padding: 8px 16px;">${confirmText}</button>
            </div>
        `;
        
        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        
        const cancelBtn = modal.querySelector('.button:not(.button-primary)');
        const confirmBtn = modal.querySelector('.button-primary');
        
        cancelBtn.addEventListener('click', () => {
            overlay.remove();
            if (onCancel) onCancel();
        });
        
        confirmBtn.addEventListener('click', () => {
            overlay.remove();
            if (onConfirm) onConfirm();
        });
        
        // Close on escape key
        const escapeHandler = (e) => {
            if (e.key === 'Escape') {
                overlay.remove();
                if (onCancel) onCancel();
                document.removeEventListener('keydown', escapeHandler);
            }
        };
        document.addEventListener('keydown', escapeHandler);
        
        // Close on overlay click
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                overlay.remove();
                if (onCancel) onCancel();
            }
        });
    }

    /**
     * Show toast notification
     */
    function scAiShowToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            background: ${type === 'success' ? '#00a32a' : '#dc3232'};
            color: white;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            z-index: 999999;
            font-size: 14px;
            animation: slideIn 0.3s ease-out;
        `;
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s ease-out';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    /**
     * Generate content for single question
     */
    function scAiGenerate(postId) {
        scAiShowModal({
            title: 'Generate Content',
            message: 'Generate AI content for this question?',
            confirmText: 'Generate',
            onConfirm: () => {
                fetch(scAiAjaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'sc_ai_generate',
                        nonce: scAiNonce,
                        post_id: postId,
                    }),
                })
                .then(r => r.json())
                .then(response => {
                    if (response.success && response.data.success) {
                        scAiShowToast('Content generated successfully!', 'success');
                        scAiLoadTable(scAiCurrentPage, scAiCurrentFilter);
                    } else {
                        scAiShowToast('Error: ' + (response.data.error || response.data || 'Unknown error'), 'error');
                    }
                })
                .catch(err => scAiShowToast('Error: ' + err.message, 'error'));
            }
        });
    }

    // Make functions globally accessible for inline onclick handlers
    window.scAiGenerate = scAiGenerate;

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        // Filter buttons
        document.querySelectorAll('.sc-ai-filter').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.sc-ai-filter').forEach(b => b.classList.remove('button-primary'));
                this.classList.add('button-primary');
                scAiLoadTable(1, this.dataset.filter);
            });
        });

        // Select all checkbox
        document.getElementById('sc-ai-select-all')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.sc-ai-item-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
            scAiUpdateBatchButton();
        });

        // Batch generate button
        document.getElementById('sc-ai-batch-generate')?.addEventListener('click', function() {
            const selected = Array.from(document.querySelectorAll('.sc-ai-item-checkbox:checked')).map(cb => parseInt(cb.dataset.id));
            if (selected.length === 0) return;
            if (selected.length > 20) {
                scAiShowToast('Maximum 20 items allowed for batch generation', 'error');
                return;
            }
            scAiBatchGenerate(selected);
        });

        // Load table on page load
        scAiLoadTable();
    }

})();
