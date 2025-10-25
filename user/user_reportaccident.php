<?php

declare(strict_types=1);

/**
 * Auth guard — User + Verified
 */
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'user') {
    header('Location: user_login.php');
    exit();
}

require_once __DIR__ . '/../backend/connection.php'; // exposes $pdo

try {
    $st = $pdo->prepare("
        select role, status
        from public.users
        where user_id = :id
        limit 1
    ");
    $st->execute([':id' => $_SESSION['user_id']]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    $roleOk   = $row && strtolower((string)$row['role']) === 'user';
    $statusOk = $row && strtolower((string)$row['status']) === 'verified';

    if (!$roleOk || !$statusOk) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        header('Location: user_login.php');
        exit();
    }
} catch (Throwable $e) {
    error_log('[USER REPORT ACCIDENT GUARD] ' . $e->getMessage());
    header('Location: user_login.php');
    exit();
}

/* ---------- Notifications (to = current user_id) for header ---------- */
$notifs = [];
$unreadCount = 0;
try {
    $ns = $pdo->prepare('
        select notif_id, approval_id, incident_id, message, is_read, created_at
        from public.notifications
        where "to" = :uid
        order by created_at desc
        limit 30
    ');
    $ns->execute([':uid' => $_SESSION['user_id']]);
    $notifs = $ns->fetchAll(PDO::FETCH_ASSOC);
    foreach ($notifs as $n) if (empty($n['is_read'])) $unreadCount++;
} catch (Throwable $e) {
    error_log('[USER REPORT ACCIDENT NOTIFS] ' . $e->getMessage());
}

/* ---------- Incident reports for the currently logged-in user ---------- */
$reports = [];
try {
    $rs = $pdo->prepare('
        select
            incident_id,
            "where" as location,
            category,
            status
        from public.incident_report
        where user_id = :uid
        order by created_at desc
    ');
    $rs->execute([':uid' => $_SESSION['user_id']]);
    $reports = $rs->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[USER INCIDENT LIST] ' . $e->getMessage());
    $reports = [];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>User</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../css/user_reportaccident.css">

    <!-- Scoped header styles (as-*) -->
    <style id="as-nav-styles">
        :root {
            --as-primary: #2b6625;
            --as-primary-dark: #1e4a1a;
            --as-white: #fff;
            --as-light-gray: #f5f5f5;
            --as-radius: 8px;
            --as-shadow: 0 4px 12px rgba(0, 0, 0, .1);
            --as-trans: all .2s ease
        }

        .as-header {
            position: fixed;
            inset: 0 0 auto 0;
            height: 58px;
            background: var(--as-primary);
            color: var(--as-white);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .1)
        }

        .as-logo {
            height: 45px;
            display: flex;
            align-items: center;
            position: relative
        }

        .as-logo a {
            display: flex;
            align-items: center;
            height: 90%
        }

        .as-logo img {
            height: 98%;
            width: auto;
            transition: var(--as-trans)
        }

        .as-logo:hover img {
            transform: scale(1.05)
        }


        .as-nav {
            display: flex;
            align-items: center;
            gap: 20px
        }

        .as-item {
            position: relative
        }

        .as-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            cursor: pointer;
            background: rgb(233, 255, 242);
            color: #000;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .15);
            transition: var(--as-trans)
        }

        .as-icon:hover {
            background: rgba(255, 255, 255, .3);
            transform: scale(1.15);
            box-shadow: 0 4px 12px rgba(0, 0, 0, .25)
        }

        .as-icon i {
            font-size: 1.3rem
        }

        .as-dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            min-width: 300px;
            background: #fff;
            border-radius: var(--as-radius);
            box-shadow: var(--as-shadow);
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: var(--as-trans);
            padding: 0;
            z-index: 1000
        }

        .as-center {
            left: 50%;
            right: auto;
            transform: translateX(-50%) translateY(10px)
        }

        .as-item:hover>.as-dropdown-menu,
        .as-dropdown-menu:hover {
            opacity: 1;
            visibility: visible;
            transform: translateY(0)
        }

        .as-center.as-dropdown-menu:hover,
        .as-item:hover>.as-center {
            transform: translateX(-50%) translateY(0)
        }

        .as-dropdown-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 25px;
            text-decoration: none;
            color: #111;
            transition: var(--as-trans);
            font-size: 1.05rem
        }

        .as-dropdown-item i {
            width: 30px;
            font-size: 1.5rem;
            color: var(--as-primary) !important
        }

        .as-dropdown-item:hover {
            background: var(--as-light-gray);
            padding-left: 30px
        }

        /* Active state for current page */
        .as-dropdown-item.active {
            background-color: rgb(225, 255, 220);
            color: var(--as-primary-dark);
            font-weight: 700;
            border-left: 4px solid var(--as-primary)
        }

        /* Notifications dropdown: sticky header/footer + scroll body */
        .as-notifications {
            min-width: 350px;
            max-height: 500px
        }

        .as-notif-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            background: #fff;
            position: sticky;
            top: 0;
            z-index: 1
        }

        .as-notif-header h3 {
            margin: 0;
            color: var(--as-primary);
            font-size: 1.1rem
        }

        .as-mark-all {
            color: var(--as-primary);
            text-decoration: none;
            font-size: .9rem;
            transition: var(--as-trans)
        }

        .as-mark-all:hover {
            color: var(--as-primary-dark);
            transform: scale(1.05)
        }

        .notifcontainer {
            height: 380px;
            overflow-y: auto;
            padding: 5px;
            background: #fff
        }

        .as-notif-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid #eee;
            background: #fff;
            transition: var(--as-trans)
        }

        .as-notif-item.unread {
            background: rgba(43, 102, 37, .05)
        }

        .as-notif-item:hover {
            background: #f9f9f9
        }

        .as-notif-link {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            text-decoration: none;
            color: inherit;
            width: 100%
        }

        .as-notif-icon {
            color: var(--as-primary);
            font-size: 1.2rem
        }

        .as-notif-title {
            font-weight: 600;
            color: var(--as-primary);
            margin-bottom: 4px
        }

        .as-notif-message {
            color: #2b6625;
            font-size: .92rem;
            line-height: 1.35
        }

        .as-notif-time {
            color: #999;
            font-size: .8rem;
            margin-top: 4px
        }

        .as-notif-footer {
            padding: 10px 20px;
            text-align: center;
            border-top: 1px solid #eee;
            background: #fff;
            position: sticky;
            bottom: 0;
            z-index: 1
        }

        .as-view-all {
            color: var(--as-primary);
            font-weight: 600;
            text-decoration: none
        }

        .as-view-all:hover {
            text-decoration: underline
        }

        .as-badge {
            position: absolute;
            top: 2px;
            right: 8px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #ff4757;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center
        }
    </style>
    <style>
        /* Ensure content clears fixed header */
        body {
            padding-top: 100px
        }
    </style>
</head>

<body>
    <!-- Loading overlay (replaced with your loader) -->
    <div id="loadingIndicator" style="display:none;position:fixed;inset:0;align-items:center;justify-content:center;background:rgba(0,0,0,.25);z-index:9998">
        <div class="card" style="background:#fff;padding:18px 22px;border-radius:10px;display:flex;gap:10px;align-items:center;">
            <span class="loader" style="width:var(--loader-size);height:var(--loader-size);border:2px solid #ddd;border-top-color:#2b6625;border-radius:50%;display:inline-block;animation:spin 0.8s linear infinite;"></span>
            <span id="loadingMessage">Working...</span>
        </div>
    </div>
    <style>
        :root {
            --loader-size: 18px;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }
    </style>


    <!-- ✅ Standard Green Header (active = Report Incident) -->
    <header class="as-header">
        <div class="as-logo">
            <a href="user_home.php"><img src="seal.png" alt="Site Logo"></a>
        </div>

        <div class="as-nav">
            <!-- App menu -->
            <div class="as-item">
                <div class="as-icon"><i class="fas fa-bars"></i></div>
                <div class="as-dropdown-menu as-center">
                    <a href="user_reportaccident.php" class="as-dropdown-item active" aria-current="page">
                        <i class="fas fa-file-invoice"></i><span>Report Incident</span>
                    </a>
                    <a href="useraddseed.php" class="as-dropdown-item"><i class="fas fa-seedling"></i><span>Request Seedlings</span></a>
                    <a href="useraddwild.php" class="as-dropdown-item"><i class="fas fa-paw"></i><span>Wildlife Permit</span></a>
                    <a href="useraddtreecut.php" class="as-dropdown-item"><i class="fas fa-tree"></i><span>Tree Cutting Permit</span></a>
                    <a href="useraddlumber.php" class="as-dropdown-item"><i class="fas fa-boxes"></i><span>Lumber Dealers Permit</span></a>
                    <a href="useraddwood.php" class="as-dropdown-item"><i class="fas fa-industry"></i><span>Wood Processing Permit</span></a>
                    <a href="useraddchainsaw.php" class="as-dropdown-item"><i class="fas fa-tools"></i><span>Chainsaw Permit</span></a>
                    <a href="applicationstatus.php" class="as-dropdown-item"><i class="fas fa-clipboard-check"></i><span>Application Status</span></a>
                </div>
            </div>

            <!-- Notifications -->
            <div class="as-item">
                <div class="as-icon">
                    <i class="fas fa-bell"></i>
                    <?php if (!empty($unreadCount)) : ?>
                        <span class="as-badge" id="asNotifBadge"><?= htmlspecialchars((string)$unreadCount, ENT_QUOTES) ?></span>
                    <?php endif; ?>
                </div>
                <div class="as-dropdown-menu as-notifications">
                    <div class="as-notif-header">
                        <h3>Notifications</h3>
                        <a href="#" class="as-mark-all" id="asMarkAllRead">Mark all as read</a>
                    </div>

                    <div class="notifcontainer">
                        <?php if (!$notifs): ?>
                            <div class="as-notif-item">
                                <div class="as-notif-content">
                                    <div class="as-notif-title">No record found</div>
                                    <div class="as-notif-message">There are no notifications.</div>
                                </div>
                            </div>
                            <?php else: foreach ($notifs as $n):
                                $unread = empty($n['is_read']);
                                $ts     = $n['created_at'] ? (new DateTime((string)$n['created_at']))->getTimestamp() : time();
                                $title  = $n['approval_id'] ? 'Permit Update' : ($n['incident_id'] ? 'Incident Update' : 'Notification');
                                $cleanMsg = (function ($m) {
                                    $t = trim((string)$m);
                                    $t = preg_replace('/\s*\(?\b(rejection\s*reason|reason)\b\s*[:\-–]\s*.*$/i', '', $t);
                                    $t = preg_replace('/\s*\b(because|due\s+to)\b\s*.*/i', '', $t);
                                    return trim(preg_replace('/\s{2,}/', ' ', $t)) ?: 'There’s an update.';
                                })($n['message'] ?? '');
                            ?>
                                <div class="as-notif-item <?= $unread ? 'unread' : '' ?>">
                                    <a href="#" class="as-notif-link"
                                        data-notif-id="<?= htmlspecialchars((string)$n['notif_id'], ENT_QUOTES) ?>"
                                        <?= !empty($n['approval_id']) ? 'data-approval-id="' . htmlspecialchars((string)$n['approval_id'], ENT_QUOTES) . '"' : '' ?>
                                        <?= !empty($n['incident_id']) ? 'data-incident-id="' . htmlspecialchars((string)$n['incident_id'], ENT_QUOTES) . '"' : '' ?>>
                                        <div class="as-notif-icon"><i class="fas fa-exclamation-circle"></i></div>
                                        <div class="as-notif-content">
                                            <div class="as-notif-title"><?= htmlspecialchars($title, ENT_QUOTES) ?></div>
                                            <div class="as-notif-message"><?= htmlspecialchars($cleanMsg, ENT_QUOTES) ?></div>
                                            <div class="as-notif-time" data-ts="<?= htmlspecialchars((string)$ts, ENT_QUOTES) ?>">just now</div>
                                        </div>
                                    </a>
                                </div>
                        <?php endforeach;
                        endif; ?>
                    </div>

                    <div class="as-notif-footer">
                        <a href="user_notification.php" class="as-view-all">View All Notifications</a>
                    </div>
                </div>
            </div>

            <!-- Profile -->
            <div class="as-item">
                <div class="as-icon"><i class="fas fa-user-circle"></i></div>
                <div class="as-dropdown-menu">
                    <a href="user_profile.php" class="as-dropdown-item"><i class="fas fa-user-edit"></i><span>Edit Profile</span></a>
                    <a href="logout.php" class="as-dropdown-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                </div>
            </div>
        </div>
    </header>

    <!-- Toast -->
    <div id="profile-notification" style="display:none; position:fixed; top:5px; left:50%; transform:translateX(-50%);
    background:#323232; color:#fff; padding:16px 32px; border-radius:8px; font-size:1.1rem; z-index:9999;
    box-shadow:0 2px 8px rgba(0,0,0,0.15); text-align:center; min-width:220px; max-width:90vw;">
    </div>

    <!-- Page Content -->
    <div class="content">
        <h2 class="page-title">REPORT AN INCIDENT</h2>

        <div class="profile-form">
            <form id="incidentForm" action="../backend/users/report_incident.php" method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label for="who">WHO</label>
                        <input type="text" id="who" name="who" required>
                    </div>
                    <div class="form-group">
                        <label for="where">WHERE</label>
                        <input type="text" id="where" name="where" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group one-third">
                        <label for="contact">CONTACT NO:</label>
                        <input type="text" id="contact" name="contact" required>
                    </div>
                    <div class="form-group one-third">
                        <label for="when">WHEN</label>
                        <input type="datetime-local" id="when" name="when" required>
                    </div>
                    <div class="form-group one-third">
                        <label for="why">WHY</label>
                        <input type="text" id="why" name="why" required>
                    </div>
                </div>

                <!-- Photos -->
                <div class="form-row">
                    <div class="form-group full-width">
                        <label>UPLOAD PHOTOS (Max 5):</label>
                        <button type="button" id="addPhotoBtn" style="margin-bottom:10px;">Add Photo(s)</button>
                        <input type="file" id="photos" name="photos[]" multiple accept="image/*" style="display:none;">
                        <div id="photoPreview" class="photo-preview-container"></div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group two-thirds">
                        <label for="categories" style="margin-top: 0;">CATEGORIES:</label>
                        <select id="categories" name="categories" required>
                            <option value="">-- Select Category --</option>
                            <option value="Tree Cutting">Tree Cutting</option>
                            <option value="Marine Resource Monitoring">Marine Resource Monitoring</option>
                            <option value="Seedlings Monitoring">Seedlings Monitoring</option>
                            <option value="WildLife Monitoring">Wildlife Monitoring</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="what">WHAT</label>
                        <textarea id="what" name="what" style="height: 100px; text-align: start;" required></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label for="description">MORE DESCRIPTION OF INCIDENT:</label>
                        <textarea id="description" name="description" style="height: 130px;" required></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full-width">
                        <div class="button-group">
                            <button type="submit" name="submit" class="save-btn">SUBMIT</button>
                            <button type="button" class="view-records-btn" id="viewRecordsBtn">VIEW RECORDS</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Records (optional list; keep if you load $reports) -->
        <div class="records-container" id="recordsContainer">
            <h3 class="records-title">INCIDENT REPORTS</h3>
            <table class="records-table">
                <thead>
                    <tr>
                        <th>Report ID</th>
                        <th>Location</th>
                        <th>Category</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reports)): ?>
                        <tr>
                            <td colspan="4" class="no-records">No incident reports found</td>
                        </tr>
                        <?php else: foreach ($reports as $report): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$report['incident_id'], ENT_QUOTES) ?></td>
                                <td><?= htmlspecialchars((string)$report['location'], ENT_QUOTES) ?></td>
                                <td><?= htmlspecialchars((string)$report['category'], ENT_QUOTES) ?></td>
                                <td class="status-<?= htmlspecialchars(strtolower((string)$report['status']), ENT_QUOTES) ?>">
                                    <?= htmlspecialchars(ucfirst((string)$report['status']), ENT_QUOTES) ?>
                                </td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>

            </table>
        </div>
    </div>

    <!-- Header JS: time-ago, mark read, click routing -->
    <script>
        (function() {
            function timeAgo(seconds) {
                if (seconds < 60) return 'just now';
                const m = Math.floor(seconds / 60);
                if (m < 60) return `${m} minute${m>1?'s':''} ago`;
                const h = Math.floor(m / 60);
                if (h < 24) return `${h} hour${h>1?'s':''} ago`;
                const d = Math.floor(h / 24);
                if (d < 7) return `${d} day${d>1?'s':''} ago`;
                const w = Math.floor(d / 7);
                if (w < 5) return `${w} week${w>1?'s':''} ago`;
                const mo = Math.floor(d / 30);
                if (mo < 12) return `${mo} month${mo>1?'s':''} ago`;
                const y = Math.floor(d / 365);
                return `${y} year${y>1?'s':''} ago`;
            }
            document.querySelectorAll('.as-notif-time[data-ts]').forEach(el => {
                const tsMs = Number(el.dataset.ts || 0) * 1000;
                if (!tsMs) return;
                const diffSec = Math.floor((Date.now() - tsMs) / 1000);
                el.textContent = timeAgo(diffSec);
                el.title = new Date(tsMs).toLocaleString();
            });

            const badge = document.getElementById('asNotifBadge');
            const markAllBtn = document.getElementById('asMarkAllRead');
            markAllBtn?.addEventListener('click', async (e) => {
                e.preventDefault();
                try {
                    await fetch(location.pathname + '?ajax=mark_all_read', {
                        method: 'POST',
                        credentials: 'same-origin'
                    });
                } catch {}
                document.querySelectorAll('.as-notif-item.unread').forEach(n => n.classList.remove('unread'));
                if (badge) badge.style.display = 'none';
            });

            const list = document.querySelector('.as-notifications');
            list?.addEventListener('click', async (e) => {
                const link = e.target.closest('.as-notif-link');
                if (!link) return;
                e.preventDefault();
                const row = link.closest('.as-notif-item');
                const wasUnread = row?.classList.contains('unread');
                row?.classList.remove('unread');
                if (badge && wasUnread) {
                    const current = parseInt(badge.textContent || '0', 10) || 0;
                    const next = Math.max(0, current - 1);
                    if (next <= 0) badge.style.display = 'none';
                    else badge.textContent = String(next);
                }
                const nid = link.dataset.notifId || '';
                if (nid) {
                    try {
                        await fetch(location.pathname + `?ajax=mark_read&notif_id=${encodeURIComponent(nid)}`, {
                            method: 'POST',
                            credentials: 'same-origin'
                        });
                    } catch {}
                }
                if (link.dataset.approvalId) {
                    window.location.href = 'applicationstatus.php';
                    return;
                }
                if (link.dataset.incidentId) {
                    window.location.href = `user_reportaccident.php?view=${encodeURIComponent(link.dataset.incidentId)}`;
                    return;
                }
                window.location.href = 'applicationstatus.php';
            });
        })();
    </script>

    <!-- Page JS (your original logic kept) -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById("incidentForm");
            const confirmationModal = document.getElementById("confirmationModal");
            const confirmBtn = document.getElementById("confirmSubmit");
            const cancelBtn = document.getElementById("cancelSubmit");
            const notification = document.getElementById("profile-notification");

            const photoInput = document.getElementById('photos');
            const addPhotoBtn = document.getElementById('addPhotoBtn');
            const photoPreview = document.getElementById('photoPreview');
            const maxPhotos = 5;
            let selectedFiles = [];

            const loadingEl = document.getElementById('loadingIndicator');
            const loadingMsg = document.getElementById('loadingMessage');
            const showLoading = (msg = 'Working...') => {
                if (!loadingEl) return;
                if (loadingMsg) loadingMsg.textContent = msg;
                loadingEl.style.display = 'flex';
            };
            const hideLoading = () => {
                if (loadingEl) loadingEl.style.display = 'none';
            };


            addPhotoBtn.addEventListener('click', () => photoInput.click());
            photoInput.addEventListener('change', function() {
                const newFiles = Array.from(photoInput.files);
                for (let f of newFiles) {
                    if (selectedFiles.length >= maxPhotos) break;
                    if (!selectedFiles.some(x => x.name === f.name && x.size === f.size)) selectedFiles.push(f);
                }
                renderPreviews();
                photoInput.value = '';
            });

            function renderPreviews() {
                photoPreview.innerHTML = '';
                selectedFiles.forEach((file, idx) => {
                    const reader = new FileReader();
                    reader.onload = e => {
                        const wrap = document.createElement('div');
                        wrap.className = 'photo-preview-wrapper';
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'photo-preview';
                        const x = document.createElement('span');
                        x.className = 'remove-photo';
                        x.textContent = '×';
                        x.onclick = () => {
                            selectedFiles.splice(idx, 1);
                            renderPreviews();
                        };
                        wrap.appendChild(img);
                        wrap.appendChild(x);
                        photoPreview.appendChild(wrap);
                    };
                    reader.readAsDataURL(file);
                });
            }

            form.addEventListener("submit", function(e) {
                e.preventDefault();
                if (selectedFiles.length === 0) {
                    alert('Please upload at least one photo');
                    return;
                }
                confirmationModal.classList.remove("hidden-modal");
            });
            cancelBtn?.addEventListener("click", () => confirmationModal.classList.add("hidden-modal"));

            async function postJSON(url, body) {
                const res = await fetch(url, {
                    method: "POST",
                    body
                });
                const text = await res.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error(`Non-JSON (${res.status}): ${text.slice(0,500)}`);
                }
            }

            document.getElementById("confirmSubmit")?.addEventListener("click", async function() {
                confirmationModal.classList.add("hidden-modal");
                const fd = new FormData(form);
                fd.delete('photos[]');
                selectedFiles.forEach(f => fd.append('photos[]', f));

                console.log('[CLIENT] inputs:', {
                    who: form.who.value?.trim(),
                    what: form.what.value?.trim(),
                    where: form.where.value?.trim(),
                    when: form.when.value?.trim(),
                    why: form.why.value?.trim(),
                    contact: form.contact.value?.trim(),
                    categories: form.categories.value?.trim(),
                    description: form.description.value?.trim(),
                    files: selectedFiles.map(f => ({
                        name: f.name,
                        size: f.size,
                        type: f.type
                    }))
                });

                showLoading('Submitting report...');
                try {
                    const data = await postJSON(form.action, fd);
                    if (data && data.echo) console.log('[SERVER] echo:', data.echo);
                    notification.textContent = data.message || 'Done.';
                    notification.style.backgroundColor = data.success ? "#28a745" : "#dc3545";
                    notification.style.display = "block";
                    setTimeout(() => {
                        notification.style.display = "none";
                        if (data.success) window.location.reload();
                    }, 2500);
                } catch (err) {
                    console.error('[NETWORK] error:', err);
                    notification.textContent = "Server said: " + err.message;
                    notification.style.backgroundColor = "#dc3545";
                    notification.style.display = "block";
                    setTimeout(() => {
                        notification.style.display = "none";
                    }, 3500);
                } finally {
                    hideLoading();
                }
            });

            const viewRecordsBtn = document.getElementById('viewRecordsBtn');
            const recordsContainer = document.getElementById('recordsContainer');
            viewRecordsBtn?.addEventListener('click', function() {
                const show = recordsContainer.style.display === 'none' || recordsContainer.style.display === '';
                recordsContainer.style.display = show ? 'block' : 'none';
                this.textContent = show ? 'HIDE RECORDS' : 'VIEW RECORDS';
            });
        });
    </script>

    <!-- Confirmation modal (unchanged) -->
    <div id="confirmationModal" class="hidden-modal">
        <div class="modal-content">
            <h3>Confirm Incident Report</h3>
            <p>Are you sure you want to submit this incident report?</p>
            <div style="margin-top:20px;">
                <button id="confirmSubmit" style="background:#28a745; color:#fff; padding:8px 16px; border:none; border-radius:4px; margin-right:10px; cursor:pointer;">Yes, Submit</button>
                <button id="cancelSubmit" style="background:#dc3545; color:#fff; padding:8px 16px; border:none; border-radius:4px; cursor:pointer;">Cancel</button>
            </div>
        </div>
    </div>
    <script>
        /*! incident-validate.js — minimal red messages, no borders */
        (() => {
            "use strict";

            const $ = (id) => document.getElementById(id);
            const q = (sel, root = document) => root.querySelector(sel);
            const qa = (sel, root = document) => Array.from(root.querySelectorAll(sel));

            // Inject tiny CSS: red text only under fields
            (() => {
                const s = document.createElement("style");
                s.textContent = `.fv-error{color:#d93025;font-size:.9rem;line-height:1.3;margin-top:6px}`;
                document.head.appendChild(s);
            })();

            // Error helpers
            const clrErr = (el) => {
                if (!el) return;
                const sib = el.nextElementSibling;
                if (sib && sib.classList.contains("fv-error")) sib.remove();
            };
            const setErr = (el, msg) => {
                if (!el) return false;
                clrErr(el);
                const d = document.createElement("div");
                d.className = "fv-error";
                d.setAttribute("role", "alert");
                d.textContent = msg;
                el.insertAdjacentElement("afterend", d);
                return false;
            };
            const firstErrFocus = () => {
                const e = q(".fv-error");
                if (!e) return;
                const a = e.previousElementSibling || e;
                try {
                    a.scrollIntoView({
                        behavior: "smooth",
                        block: "center"
                    });
                } catch {}
                if (a && a.focus) a.focus({
                    preventScroll: true
                });
            };

            // Utils
            const blank = (v) => !v || !String(v).trim();
            const rep4 = (v) => /(.)\1{3,}/.test(String(v));
            const letters = (v) => /^[A-Za-z][A-Za-z\s'’.()-]*[A-Za-z]$/.test(String(v).trim());
            const isPHMobile = (s) => /^(\+639|639|09)\d{9}$/.test(String(s).replace(/[^\d+0-9]/g, ""));

            const parseDT = (s) => s ? new Date(s) : null;
            const now = () => new Date();

            // Field validators (short msgs)
            const vWho = (el) => {
                const v = el.value.trim();
                clrErr(el);
                if (blank(v)) return setErr(el, "Required.");
                if (v.length < 2) return setErr(el, "Too short.");
                if (!letters(v)) return setErr(el, "Letters only.");
                if (rep4(v)) return setErr(el, "Looks invalid.");
                return true;
            };
            const vWhere = (el) => {
                const v = el.value.trim();
                clrErr(el);
                if (blank(v)) return setErr(el, "Required.");
                if (v.length < 3) return setErr(el, "Be specific.");
                if (/^\d+$/.test(v)) return setErr(el, "Not numbers only.");
                return true;
            };
            const vContact = (el) => {
                const v = el.value.trim();
                clrErr(el);
                if (blank(v)) return setErr(el, "Required.");
                if (!isPHMobile(v)) return setErr(el, "Use 09/639 format.");
                return true;
            };
            const vWhen = (el) => {
                clrErr(el);
                if (!el.value) return setErr(el, "Required.");
                const d = parseDT(el.value);
                if (!d || isNaN(+d)) return setErr(el, "Invalid.");
                if (d > now()) return setErr(el, "No future time.");
                const years = (now() - d) / (365 * 24 * 60 * 60 * 1000);
                if (years > 10) return setErr(el, "Too old.");
                return true;
            };
            const vWhy = (el) => {
                const v = el.value.trim();
                clrErr(el);
                if (blank(v)) return setErr(el, "Required.");
                if (v.length < 5) return setErr(el, "Min 5 chars.");
                return true;
            };
            const vCat = (el) => {
                clrErr(el);
                if (!el.value) return setErr(el, "Select one.");
                return true;
            };
            const vWhat = (el) => {
                const v = el.value.trim();
                clrErr(el);
                if (blank(v)) return setErr(el, "Required.");
                if (v.length < 20) return setErr(el, "Min 20 chars.");
                return true;
            };
            const vDesc = (el) => {
                const v = el.value.trim();
                clrErr(el);
                if (blank(v)) return setErr(el, "Required.");
                if (v.length < 30) return setErr(el, "Min 30 chars.");
                return true;
            };

            // Photos
            const photoInput = $("photos");
            const photoPreview = $("photoPreview"); // container with preview tiles
            const addBtn = $("addPhotoBtn");
            const maxPhotos = 5;
            const maxMB = 8;

            const placePhotoErrAfter = (anchor) => anchor || photoPreview || photoInput;
            const clrPhotoErr = () => {
                const anchor = placePhotoErrAfter(addBtn);
                clrErr(anchor);
            };
            const setPhotoErr = (msg) => {
                const anchor = placePhotoErrAfter(addBtn);
                return setErr(anchor, msg);
            };

            function currentPhotoCount() {
                // count preview tiles (added by your page script)
                return qa(".photo-preview-wrapper", photoPreview).length;
            }

            async function vPhotosOnChange(files) {
                clrPhotoErr();
                if (!files || !files.length) return true; // handled on submit if none overall
                const exist = currentPhotoCount();
                if (exist + files.length > maxPhotos) return setPhotoErr(`Max ${maxPhotos} photos.`);
                for (const f of files) {
                    // Basic type/size checks (accept common mobile formats)
                    const okType = /^image\/(jpeg|png|webp|heic|heif)$/i.test(f.type || "") ||
                        /\.(jpe?g|png|webp|heic|heif)$/i.test(f.name || "");
                    if (!okType) return setPhotoErr("Images only.");
                    if (f.size > maxMB * 1024 * 1024) return setPhotoErr(`Max ${maxMB}MB each.`);
                }
                return true;
            }

            function vPhotosOnSubmit() {
                clrPhotoErr();
                const count = currentPhotoCount();
                if (count < 1) return setPhotoErr("Need 1+ photo.");
                if (count > maxPhotos) return setPhotoErr(`Max ${maxPhotos} photos.`);
                return true;
            }

            // Validate-all
            async function validateAll() {
                // clear all existing errors
                qa(".fv-error").forEach(n => n.remove());

                let ok = true;
                ok &= vWho($("who"));
                ok &= vWhere($("where"));
                ok &= vContact($("contact"));
                ok &= vWhen($("when"));
                ok &= vWhy($("why"));
                ok &= vCat($("categories"));
                ok &= vWhat($("what"));
                ok &= vDesc($("description"));
                ok &= vPhotosOnSubmit();

                return !!ok;
            }

            // Live bindings
            function bindLive() {
                const map = [
                    ["who", vWho, "input"],
                    ["where", vWhere, "input"],
                    ["contact", vContact, "input"],
                    ["when", vWhen, "change"],
                    ["why", vWhy, "input"],
                    ["categories", vCat, "change"],
                    ["what", vWhat, "input"],
                    ["description", vDesc, "input"],
                ];
                for (const [id, fn, evt] of map) {
                    const el = $(id);
                    if (!el) continue;
                    el.addEventListener(evt, () => fn(el));
                    el.addEventListener("blur", () => fn(el));
                }

                // File change validation (runs BEFORE your page script clears the input)
                photoInput?.addEventListener("change", async () => {
                    try {
                        await vPhotosOnChange(photoInput.files || []);
                    } catch {}
                });

                // If previews are mutated, clear photo error when at least one exists
                if (photoPreview && "MutationObserver" in window) {
                    const mo = new MutationObserver(() => {
                        if (currentPhotoCount() > 0) clrPhotoErr();
                    });
                    mo.observe(photoPreview, {
                        childList: true,
                        subtree: false
                    });
                }
            }

            // Guard submits (intercept both the initial "SUBMIT" and the Confirm button)
            function guardSubmits() {
                // form submit (opens modal)
                $("incidentForm")?.addEventListener("submit", async (e) => {
                    const ok = await validateAll();
                    if (!ok) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        firstErrFocus();
                    }
                }, true);

                // "Yes, Submit" confirm button (posts to server)
                $("confirmSubmit")?.addEventListener("click", async (e) => {
                    const ok = await validateAll();
                    if (!ok) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        firstErrFocus();
                    }
                }, true);
            }

            // Boot
            const ready = (fn) => (document.readyState === "loading" ? document.addEventListener("DOMContentLoaded", fn) : fn());
            ready(() => {
                bindLive();
                guardSubmits();
            });
        })();
    </script>
</body>

</html>