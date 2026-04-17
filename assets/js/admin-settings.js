if (typeof ajaxurl === 'undefined') {
    ajaxurl = scAiSettings.ajaxUrl;
}

document.addEventListener('DOMContentLoaded', function() {
    // Test API connection
    const testApiBtn = document.getElementById('sc-test-api');
    if (testApiBtn) {
        testApiBtn.addEventListener('click', function() {
            const result = document.getElementById('sc-api-test-result');
            result.style.display = 'block';
            result.style.background = '#FFF3CD';
            result.innerHTML = '<strong>Testing API connection...</strong>';

            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'sc_ai_test_api',
                    nonce: scAiSettings.nonce,
                }),
            })
            .then( response => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                }
                return response.json();
            })
            .then( data => {
                if ( data.success ) {
                    const r = data.data;
                    let html = '<strong>API Test Results:</strong><br><br>';

                    if ( r.groq ) {
                        if ( r.groq.status === 'success' ) {
                            html += '✅ <strong>Groq:</strong> Connected<br>';
                        } else if ( r.groq.status === 'not_configured' ) {
                            html += '⚠ <strong>Groq:</strong> Not configured<br>';
                        } else if ( r.groq.status === 'rate_limited' ) {
                            html += '⏳ <strong>Groq:</strong> Rate limited - ' + r.groq.error + '<br>';
                        } else {
                            html += '❌ <strong>Groq:</strong> Failed - ' + r.groq.error + '<br>';
                        }
                    }

                    if ( r.openrouter ) {
                        if ( r.openrouter.status === 'success' ) {
                            html += '✅ <strong>OpenRouter:</strong> Connected';
                        } else if ( r.openrouter.status === 'not_configured' ) {
                            html += '⚠ <strong>OpenRouter:</strong> Not configured';
                        } else if ( r.openrouter.status === 'rate_limited' ) {
                            html += '⏳ <strong>OpenRouter:</strong> Rate limited - ' + r.openrouter.error;
                        } else {
                            html += '❌ <strong>OpenRouter:</strong> Failed - ' + r.openrouter.error;
                        }
                    }

                    result.style.background = '#D4EDDA';
                    result.innerHTML = html;
                } else {
                    result.style.background = '#F8D7DA';
                    result.innerHTML = '<strong>Error:</strong> ' + data.data;
                }
            })
            .catch( err => {
                result.style.background = '#F8D7DA';
                result.innerHTML = '<strong>Error:</strong> ' + err.message + '<br><small>Check browser console for details (F12)</small>';
            });
        });
    }

    // Reset stuck questions
    const resetStuckBtn = document.getElementById('sc-reset-stuck');
    if (resetStuckBtn) {
        resetStuckBtn.addEventListener('click', function() {
            const result = document.getElementById('sc-api-test-result');
            result.style.display = 'block';
            result.style.background = '#FFF3CD';
            result.innerHTML = '<strong>Resetting stuck questions...</strong>';

            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'sc_ai_reset_stuck',
                    nonce: scAiSettings.nonce,
                }),
            })
            .then( r => r.json() )
            .then( data => {
                if ( data.success ) {
                    result.style.background = '#D4EDDA';
                    let msg = '<strong>Reset Complete!</strong><br>';
                    if ( data.data.locks_cleared ) {
                        msg += '✅ Cleared batch locks<br>';
                    }
                    if ( data.data.reset_count > 0 ) {
                        msg += '✅ Reset ' + data.data.reset_count + ' stuck questions to pending.';
                    } else {
                        msg += 'No stuck questions found.';
                    }
                    result.innerHTML = msg;
                    setTimeout(() => location.reload(), 1000);
                } else {
                    result.style.background = '#F8D7DA';
                    result.innerHTML = '<strong>Error:</strong> ' + data.data;
                }
            })
            .catch( err => {
                result.style.background = '#F8D7DA';
                result.innerHTML = '<strong>Error:</strong> ' + err.message;
            });
        });
    }

    // Manual cron trigger
    const cronBtn = document.getElementById('sc-manual-cron');
    if (cronBtn) {
        cronBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const result = document.getElementById('sc-api-test-result');
            if (!result) {
                alert('Result element not found');
                return;
            }
            result.style.display = 'block';
            result.style.background = '#FFF3CD';
            result.innerHTML = '<strong>Running final cron job...</strong>';

            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'sc_ai_manual_cron',
                    nonce: scAiSettings.nonce,
                }),
            })
            .then( response => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                }
                return response.json();
            })
            .then( data => {
                if ( data.success ) {
                    result.style.background = '#D4EDDA';
                    let msg = '<strong>Cron Job Complete!</strong><br>';
                    msg += '✅ Processed: ' + data.data.processed + '<br>';
                    msg += '✅ Success: ' + data.data.success + '<br>';
                    if ( data.data.failed > 0 ) {
                        msg += '❌ Failed: ' + data.data.failed;
                    }
                    result.innerHTML = msg;
                } else {
                    result.style.background = '#F8D7DA';
                    result.innerHTML = '<strong>Error:</strong> ' + (data.data || 'Unknown error');
                }
            })
            .catch( err => {
                result.style.background = '#F8D7DA';
                result.innerHTML = '<strong>Error:</strong> ' + err.message + '<br><small>Check browser console for details (F12)</small>';
            });
        });
    }
});
