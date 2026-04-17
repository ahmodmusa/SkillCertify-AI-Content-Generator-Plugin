document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.sc-ai-col-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var postId = this.dataset.postId;
      var cell   = this.closest('td');
      var btn    = this;

      btn.disabled    = true;
      btn.textContent = 'Generating...';

      fetch(scAiCol.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action:  'sc_ai_generate',
          post_id: postId,
          nonce:   scAiCol.nonce
        })
      })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.success) {
          cell.innerHTML =
            '<span class="sc-ai-badge done">✅ Done</span>' +
            '<small class="sc-ai-time">Just now</small>';
        } else {
          btn.disabled    = false;
          btn.textContent = 'Retry';
          cell.insertAdjacentHTML('afterbegin',
            '<span class="sc-ai-badge failed">❌ Failed</span><br>');
        }
      })
      .catch(function () {
        btn.disabled    = false;
        btn.textContent = 'Retry';
      });
    });
  });
});
