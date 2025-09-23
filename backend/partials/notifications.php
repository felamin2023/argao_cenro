<?php
declare(strict_types=1);

/**
 * Drop-in notifications icon + dropdown.
 * Usage (in header):  include dirname(__DIR__) . '/backend/partials/notifications.php';
 * Assumes Font Awesome CSS is already loaded by the page.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Site-absolute base URL for your project (adjust if your URL differs)
if (!defined('APP_BASE_URL')) {
    // Example when your app is at http://localhost/denr/superadmin
    define('APP_BASE_URL', '/denr/superadmin');
}

// If not logged in, render nothing (or a disabled bell)
if (empty($_SESSION['user_id'])) {
    ?>
    <div class="nav-item dropdown" id="notif-root">
        <div class="nav-icon" title="Notifications">
            <i class="fas fa-bell"></i>
        </div>
    </div>
    <?php
    return;
}

// Because this file is in backend/partials/, go up ONE level to reach backend/*.php
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../notifications_repo.php';

$userId = (string)$_SESSION['user_id'];

// Fetch recent notifications + unread count
$limit = 10;
$items = fetch_user_notifications($pdo, $userId, $limit, 0, false);
$unread = count_unread_notifications($pdo, $userId);
?>
<div class="nav-item dropdown" id="notif-root">
    <div class="nav-icon" title="Notifications">
        <i class="fas fa-bell"></i>
        <?php if ($unread > 0): ?>
            <span class="badge" id="notif-badge"><?= htmlspecialchars((string)min($unread, 9)) ?></span>
        <?php endif; ?>
    </div>

    <div class="dropdown-menu notifications-dropdown" id="notif-menu">
        <div class="notification-header">
            <h3>Notifications</h3>
            <a href="#" class="mark-all-read" id="notif-mark-all">Mark all as read</a>
        </div>

        <?php if (empty($items)): ?>
            <div class="notification-item" style="padding:16px">No notifications yet.</div>
        <?php else: ?>
            <?php foreach ($items as $row): ?>
                <?php
                    $isUnread = !$row['is_read'];
                    $timeAgo = time_ago_string($row['created_at']);
                    // absolute path so it works from any page
                    $href = APP_BASE_URL . '/user/user_notification.php';
                ?>
                <div class="notification-item <?= $isUnread ? 'unread' : '' ?>" data-notif-id="<?= htmlspecialchars($row['notif_id']) ?>">
                    <a href="<?= htmlspecialchars($href) ?>" class="notification-link">
                        <div class="notification-icon">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">
                                <?= htmlspecialchars(ucfirst((string)($row['request_type'] ?? 'Update'))) ?>
                                <?php if (!empty($row['approval_status'])): ?>
                                    — <?= htmlspecialchars(ucfirst((string)$row['approval_status'])) ?>
                                <?php endif; ?>
                            </div>
                            <div class="notification-message"><?= htmlspecialchars((string)$row['message']) ?></div>
                            <div class="notification-time"><?= htmlspecialchars($timeAgo) ?></div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="notification-footer">
            <a href="<?= APP_BASE_URL ?>/user/user_notification.php" class="view-all">View All Notifications</a>
        </div>
    </div>
</div>

<!-- Minimal JS hook; if your page already has dropdown logic, this only handles read-state -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  const BASE = <?= json_encode(APP_BASE_URL) ?>;

  const markAll = document.getElementById('notif-mark-all');
  const badge = document.getElementById('notif-badge');

  if (markAll) {
    markAll.addEventListener('click', async (e) => {
      e.preventDefault();
      try {
        const res = await fetch(`${BASE}/backend/notifications_mark_all_read.php`, {
          method: 'POST',
          headers: { 'X-Requested-With': 'fetch' }
        });
        if (!res.ok) throw new Error('Network error');
        const data = await res.json();
        if (data.ok) {
          document.querySelectorAll('#notif-menu .notification-item.unread').forEach(el => el.classList.remove('unread'));
          if (badge) badge.style.display = 'none';
        }
      } catch (err) {
        console.error(err);
        alert('Sorry, failed to mark notifications as read.');
      }
    });
  }

  // Mark a single notification as read when clicked
  document.querySelectorAll('#notif-menu .notification-item').forEach(item => {
    item.addEventListener('click', async () => {
      const id = item.getAttribute('data-notif-id');
      if (!id || !item.classList.contains('unread')) return;
      try {
        await fetch(`${BASE}/backend/notifications_mark_read.php`, {
          method: 'POST',
          headers: {
            'Content-Type':'application/x-www-form-urlencoded',
            'X-Requested-With':'fetch'
          },
          body: new URLSearchParams({ notif_id: id }).toString()
        });
        item.classList.remove('unread');
        const badgeEl = document.getElementById('notif-badge');
        if (badgeEl) {
          const n = parseInt(badgeEl.textContent || '0', 10) - 1;
          if (n > 0) badgeEl.textContent = String(n);
          else badgeEl.style.display = 'none';
        }
      } catch {}
    });
  });
});
</script>
