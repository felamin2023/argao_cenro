document.addEventListener('DOMContentLoaded', function () {
  const root = document.getElementById('notif-root');
  if (!root) return;

  const badge = document.getElementById('notif-badge');

  const markAll = document.getElementById('notif-mark-all');
  if (markAll) {
    markAll.addEventListener('click', async (e) => {
      e.preventDefault();
      const res = await fetch('/backend/notifications_mark_all_read.php', { method: 'POST', headers: {'X-Requested-With': 'fetch'} });
      if (!res.ok) return;
      const data = await res.json();
      if (data.ok) {
        root.querySelectorAll('.notification-item.unread').forEach(el => el.classList.remove('unread'));
        if (badge) badge.style.display = 'none';
      }
    });
  }

  root.querySelectorAll('.notification-item').forEach(item => {
    item.addEventListener('click', async () => {
      if (!item.classList.contains('unread')) return;
      const id = item.getAttribute('data-notif-id');
      if (!id) return;
      await fetch('/backend/notifications_mark_read.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'fetch'},
        body: new URLSearchParams({ notif_id: id }).toString()
      });
      item.classList.remove('unread');
      if (badge) {
        const n = parseInt(badge.textContent || '0', 10) - 1;
        if (n > 0) badge.textContent = String(n); else badge.style.display = 'none';
      }
    });
  });
});
