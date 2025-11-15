<?php
// supereach.php (PDO / Supabase Postgres) — UPDATED to match superhome.php notifications UI/logic
declare(strict_types=1);
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: superlogin.php');
    exit();
}

require_once __DIR__ . '/backend/connection.php'; // exposes $pdo (PDO->Postgres)

$admin_uuid = (string)$_SESSION['user_id'];

// Ensure this user is an Admin in CENRO
try {
    $st = $pdo->prepare("
        SELECT department, role
        FROM public.users
        WHERE user_id = :uid
        LIMIT 1
    ");
    $st->execute([':uid' => $admin_uuid]);
    $me = $st->fetch(PDO::FETCH_ASSOC);
    if (!$me || strtolower((string)$me['role']) !== 'admin' || strtolower((string)$me['department']) !== 'cenro') {
        header('Location: superlogin.php');
        exit();
    }
} catch (Throwable $e) {
    error_log('[SUPEREACH AUTH] ' . $e->getMessage());
    header('Location: superlogin.php');
    exit();
}

/* =================== AJAX for Notifications (same endpoints as superhome.php) =================== */
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    try {
        if ($_GET['ajax'] === 'mark_all_read') {
            // Mark ALL notifications addressed to CENRO as read
            $u = $pdo->prepare('
                update public.notifications
                set is_read = true
                where lower("to") = :to and (is_read is null or is_read = false)
            ');
            $u->execute([':to' => 'cenro']);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($_GET['ajax'] === 'mark_read') {
            $nid = $_GET['notif_id'] ?? '';
            if (!$nid) {
                echo json_encode(['success' => false, 'error' => 'Missing notif_id']);
                exit;
            }
            $u = $pdo->prepare('
                update public.notifications
                set is_read = true
                where notif_id = :nid and lower("to") = :to
            ');
            $u->execute([':nid' => $nid, ':to' => 'cenro']);
            echo json_encode(['success' => true]);
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'Unknown action']);
    } catch (Throwable $e) {
        error_log('[SUPEREACH NOTIFS AJAX] ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

/* =================== Load latest notifications for CENRO (same query as superhome.php) =================== */
$notifs = [];
$unreadCount = 0;
try {
    $ns = $pdo->prepare('
        select
            notif_id,
            "from",
            "to",
            message,
            is_read,
            created_at,
            reqpro_id
        from public.notifications
        where lower("to") = :to
        order by created_at desc
        limit 30
    ');
    $ns->execute([':to' => 'cenro']);
    $notifs = $ns->fetchAll(PDO::FETCH_ASSOC);

    foreach ($notifs as $n) {
        if (empty($n['is_read'])) $unreadCount++;
    }
} catch (Throwable $e) {
    error_log('[SUPEREACH NOTIFS LOAD] ' . $e->getMessage());
    $notifs = [];
    $unreadCount = 0;
}

/* =================== Helper functions (identical to superhome.php) =================== */
// Treat naive DB timestamps as UTC, then show elapsed time in Asia/Manila.
function _to_manila_dt(string $src): DateTimeImmutable
{
    $src = trim($src);
    $hasTz = (bool)preg_match('/[zZ]|[+\-]\d{2}:\d{2}$/', $src); // already has TZ?
    $base  = new DateTimeImmutable($src, $hasTz ? null : new DateTimeZone('UTC'));
    return $base->setTimezone(new DateTimeZone('Asia/Manila'));
}

function time_elapsed_string(string $datetime, bool $full = false): string
{
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $ago = _to_manila_dt($datetime);

    $diff = $now->diff($ago);

    // Use total days for weeks calculation
    $daysTotal = is_int($diff->days) ? $diff->days : (int)$diff->d;
    $weeks     = intdiv($daysTotal, 7);
    $daysLeft  = $daysTotal - $weeks * 7;

    $units = [
        'y' => ['v' => $diff->y,        'label' => 'year'],
        'm' => ['v' => $diff->m,        'label' => 'month'],
        'w' => ['v' => $weeks,          'label' => 'week'],
        'd' => ['v' => $daysLeft,       'label' => 'day'],
        'h' => ['v' => $diff->h,        'label' => 'hour'],
        'i' => ['v' => $diff->i,        'label' => 'minute'],
        's' => ['v' => $diff->s,        'label' => 'second'],
    ];

    $parts = [];
    foreach ($units as $u) {
        if ($u['v'] > 0) {
            $parts[] = $u['v'] . ' ' . $u['label'] . ($u['v'] > 1 ? 's' : '');
        }
    }
    if (!$full) $parts = array_slice($parts, 0, 1);

    return $parts ? implode(', ', $parts) . ' ago' : 'just now';
}

// Optional: nice absolute Manila time for tooltips
function format_manila_abs(string $datetime): string
{
    return _to_manila_dt($datetime)->format('M j, Y g:i A');
}

/* =================== Inputs to open a specific request (kept from your original) =================== */
function is_uuid(string $v): bool
{
    return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $v);
}

$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_uuid  = isset($_GET['user']) ? trim((string)$_GET['user']) : '';
$notif_id   = isset($_GET['notif_id']) ? trim((string)$_GET['notif_id']) : '';

try {
    if ($request_id > 0) {
        $st = $pdo->prepare("
            SELECT
                id,
                user_id,
                image,
                first_name,
                last_name,
                age,
                email,
                department,
                phone,
                password,
                status,
                reason_for_rejection,
                is_read,
                created_at,
                reviewed_at,
                reviewed_by
            FROM public.profile_update_requests
            WHERE id = :id
            LIMIT 1
        ");
        $st->execute([':id' => $request_id]);
        $request = $st->fetch(PDO::FETCH_ASSOC);
    } elseif ($user_uuid !== '' && is_uuid($user_uuid)) {
        $st = $pdo->prepare("
            SELECT
                id,
                user_id,
                image,
                first_name,
                last_name,
                age,
                email,
                department,
                phone,
                password,
                status,
                reason_for_rejection,
                is_read,
                created_at,
                reviewed_at,
                reviewed_by
            FROM public.profile_update_requests
            WHERE user_id = :uid
            ORDER BY
                CASE WHEN lower(status) = 'pending' THEN 0 ELSE 1 END ASC,
                created_at DESC
            LIMIT 1
        ");
        $st->execute([':uid' => $user_uuid]);
        $request = $st->fetch(PDO::FETCH_ASSOC);
        $request_id = $request ? (int)$request['id'] : 0;
    } else {
        $request = false;
    }

    if (!$request) {
        header('Location: supernotif.php');
        exit();
    }

    // 1) Mark the profile_update_request as read
    if ((int)($request['is_read'] ?? 0) === 0 && $request_id > 0) {
        $mk = $pdo->prepare("UPDATE public.profile_update_requests SET is_read = true WHERE id = :id");
        $mk->execute([':id' => $request_id]);
        $request['is_read'] = 1;
    }

    // 2) If opened via a bell click, mark THAT notification as read now
    if ($notif_id !== '') {
        $mk2 = $pdo->prepare('
            UPDATE public.notifications
               SET is_read = true
             WHERE notif_id = :nid AND lower("to") = :to
        ');
        $mk2->execute([':nid' => $notif_id, ':to' => 'cenro']);
    }
} catch (Throwable $e) {
    error_log('[SUPEREACH FETCH] ' . $e->getMessage());
    header('Location: supernotif.php');
    exit();
}

/** ── Image + safe outputs ── */
$imgVal = trim((string)($request['image'] ?? ''));
$isUrl  = (bool)preg_match('~^https?://~i', $imgVal);
$imageSrc = $isUrl && $imgVal !== '' ? htmlspecialchars($imgVal, ENT_QUOTES, 'UTF-8')
    : '/denr/superadmin/default-profile.jpg';

$first_name = htmlspecialchars((string)($request['first_name'] ?? ''), ENT_QUOTES, 'UTF-8');
$last_name  = htmlspecialchars((string)($request['last_name'] ?? ''),  ENT_QUOTES, 'UTF-8');
$age        = htmlspecialchars((string)($request['age'] ?? ''),        ENT_QUOTES, 'UTF-8');
$email      = htmlspecialchars((string)($request['email'] ?? ''),      ENT_QUOTES, 'UTF-8');
$department = htmlspecialchars((string)($request['department'] ?? ''), ENT_QUOTES, 'UTF-8');
$phone      = htmlspecialchars((string)($request['phone'] ?? ''),      ENT_QUOTES, 'UTF-8');
$status     = strtolower((string)($request['status'] ?? 'pending'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Update Request</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/denr/superadmin/css/supereach.css">

    <!-- ============ NOTIF DROPDOWN STYLES (copied from superhome.php) ============ -->
    <style>
        :root {
            --as-primary: #2b6625;
            --as-primary-dark: #1e4a1a;
            --as-white: #fff;
            --as-light-gray: #f5f5f5;
            --as-radius: 8px;
            --as-shadow: 0 4px 12px rgba(0, 0, 0, .1);
            --as-trans: all .2s ease;
        }

        .as-item {
            position: relative;
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
            transition: var(--as-trans);
        }

        .as-icon:hover {
            background: rgba(255, 255, 255, .3);
            transform: scale(1.15);
            box-shadow: 0 4px 12px rgba(0, 0, 0, .25);
        }

        .as-icon i {
            font-size: 1.3rem;
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
            z-index: 1000;
        }

        .as-item:hover>.as-dropdown-menu,
        .as-dropdown-menu:hover {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .as-notifications {
            min-width: 350px;
            max-height: 500px;
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
            z-index: 1;
        }

        .as-notif-header h3 {
            margin: 0;
            color: var(--as-primary);
            font-size: 1.1rem;
        }

        .as-mark-all {
            color: var(--as-primary);
            text-decoration: none;
            font-size: .9rem;
            transition: var(--as-trans);
        }

        .as-mark-all:hover {
            color: var(--as-primary-dark);
            transform: scale(1.05);
        }

        .notifcontainer {
            height: 380px;
            overflow-y: auto;
            padding: 5px;
            background: #fff;
        }

        .as-notif-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid #eee;
            background: #fff;
            transition: var(--as-trans);
        }

        .as-notif-item.unread {
            background: rgba(43, 102, 37, .05);
        }

        .as-notif-item:hover {
            background: #f9f9f9;
        }

        .as-notif-link {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            text-decoration: none;
            color: inherit;
            width: 100%;
        }

        .as-notif-icon {
            color: var(--as-primary);
            font-size: 1.2rem;
        }

        .as-notif-title {
            font-weight: 600;
            color: var(--as-primary);
            margin-bottom: 4px;
        }

        .as-notif-message {
            color: #2b6625;
            font-size: .92rem;
            line-height: 1.35;
        }

        .as-notif-time {
            color: #999;
            font-size: .8rem;
            margin-top: 4px;
        }

        .as-notif-footer {
            padding: 10px 20px;
            text-align: center;
            border-top: 1px solid #eee;
            background: #fff;
            position: sticky;
            bottom: 0;
            z-index: 1;
        }

        .as-view-all {
            color: var(--as-primary);
            font-weight: 600;
            text-decoration: none;
        }

        .as-view-all:hover {
            text-decoration: underline;
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
            justify-content: center;
        }
    </style>
</head>

<body>
    <header>
        <div class="logo"><a href="superhome.php"><img src="seal.png" alt="Site Logo"></a></div>
        <button class="mobile-toggle"><i class="fas fa-bars"></i></button>

        <div class="nav-container">
            <div class="nav-item dropdown">
                <div class="nav-icon"><i class="fas fa-bars"></i></div>
                <div class="dropdown-menu center">
                    <a href="superlogs.php" class="dropdown-item">
                        <i class="fas fa-user-shield" style="color:white;"></i><span>Admin Logs</span>
                    </a>
                </div>
            </div>

            <!-- ============ NOTIFICATIONS (identical to superhome.php) ============ -->
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
                                $unread   = empty($n['is_read']);
                                $created  = isset($n['created_at']) ? (string)$n['created_at'] : '';
                                $fromVal  = (string)($n['from'] ?? '');
                                $reqproId = $n['reqpro_id'] ?? '';

                                // Title rules (same as superhome.php)
                                $title = (function ($fv) {
                                    $fv = trim((string)$fv);
                                    if (preg_match('/^register request$/i', $fv)) return 'Registration';
                                    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $fv)) return 'Profile update';
                                    return 'Notification';
                                })($fromVal);

                                $cleanMsg = (function ($m) {
                                    $t = trim((string)$m);
                                    $t = preg_replace('/\s*\(?\b(rejection\s*reason|reason)\b\s*[:\-–]\s*.*$/i', '', $t);
                                    $t = preg_replace('/\s*\b(because|due\s+to)\b\s*.*/i', '', $t);
                                    return trim(preg_replace('/\s{2,}/', ' ', $t)) ?: 'There’s an update.';
                                })($n['message'] ?? '');

                                // Relative/absolute times (Asia/Manila)
                                $relTime  = $created !== '' ? time_elapsed_string($created) : 'just now';
                                $absTitle = $created !== '' ? format_manila_abs($created)
                                    : format_manila_abs((new DateTimeImmutable('now'))->format('Y-m-d H:i:s'));
                                $ts = $created !== ''
                                    ? _to_manila_dt($created)->getTimestamp()
                                    : (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->getTimestamp();
                            ?>
                                <div class="as-notif-item <?= $unread ? 'unread' : '' ?>">
                                    <a href="#" class="as-notif-link"
                                        data-notif-id="<?= htmlspecialchars((string)$n['notif_id'], ENT_QUOTES) ?>"
                                        data-from="<?= htmlspecialchars($fromVal, ENT_QUOTES) ?>"
                                        data-reqpro-id="<?= htmlspecialchars((string)$reqproId, ENT_QUOTES) ?>">
                                        <div class="as-notif-icon"><i class="fas fa-exclamation-circle"></i></div>
                                        <div class="as-notif-content">
                                            <div class="as-notif-title"><?= htmlspecialchars($title, ENT_QUOTES) ?></div>
                                            <div class="as-notif-message"><?= htmlspecialchars($cleanMsg, ENT_QUOTES) ?></div>
                                            <div class="as-notif-time"
                                                data-ts="<?= htmlspecialchars((string)$ts, ENT_QUOTES) ?>"
                                                title="<?= htmlspecialchars($absTitle, ENT_QUOTES) ?>">
                                                <?= htmlspecialchars($relTime, ENT_QUOTES) ?>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                        <?php endforeach;
                        endif; ?>
                    </div>

                    <div class="as-notif-footer">
                        <a href="supernotif.php" class="as-view-all">View All Notifications</a>
                    </div>
                </div>
            </div>
            <!-- ================================================================== -->

            <div class="nav-item dropdown">
                <div class="nav-icon"><i class="fas fa-user-circle"></i></div>
                <div class="dropdown-menu">
                    <a href="superprofile.php" class="dropdown-item"><i class="fas fa-user-edit"></i><span>Edit Profile</span></a>
                    <a href="logout.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                </div>
            </div>
        </div>
    </header>

    <div class="accident-report-container">
        <h1 class="accident-report-header">Profile Update Request</h1>
        <div class="accident-report-form">
            <div class="accident-form-group full-width" style="grid-row: span 2;">
                <label>PROFILE IMAGE</label>
                <div class="accident-form-valueimg">
                    <img src="<?= $imageSrc ?>" alt="Profile Image" style="max-height:155px;display:block;">
                </div>
            </div>

            <div class="accident-form-group"><label>FIRST NAME</label>
                <div class="accident-form-value"><?= $first_name ?></div>
            </div>
            <div class="accident-form-group"><label>LAST NAME</label>
                <div class="accident-form-value"><?= $last_name ?></div>
            </div>
            <div class="accident-form-group"><label>AGE</label>
                <div class="accident-form-value"><?= $age ?></div>
            </div>
            <div class="accident-form-group"><label>EMAIL</label>
                <div class="accident-form-value"><?= $email ?></div>
            </div>
            <div class="accident-form-group"><label>PHONE</label>
                <div class="accident-form-value"><?= $phone ?></div>
            </div>
            <div class="accident-form-group"><label>DEPARTMENT</label>
                <div class="accident-form-value"><?= $department ?></div>
            </div>

            <div class="save-button-container">
                <form id="updateRequestForm" action="/denr/superadmin/backend/admin/process_update_request.php" method="post">
                    <input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>">
                    <input type="hidden" name="action" id="formAction" value="">
                    <?php if ($status === 'pending'): ?>
                        <button type="button" id="approveBtn" class="approve-button">APPROVE</button>
                        <button type="button" id="rejectBtn" class="reject-button">REJECT</button>
                    <?php else: ?>
                        <!-- <button type="button" id="deleteBtn" class="delete-button" style="background:#dc3545;color:#fff;">DELETE</button> -->
                        <button type="button" id="backBtn" class="back-button" style="background:#6c757d;color:#fff;">BACK</button>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Modals -->
            <div id="approveModal" class="modal" style="display:none;">
                <div class="modal-content">
                    <p>Approve this profile update?</p>
                    <div><button id="confirmApprove" class="approve-button">Yes, Approve</button><button class="close-modal">Cancel</button></div>
                </div>
            </div>
            <div id="rejectModal" class="modal" style="display:none;">
                <div class="modal-content">
                    <p>Reject this profile update?</p>
                    <label for="reason">Reason for rejection:</label>
                    <input type="text" id="reason" name="reason_for_rejection" style="width:100%;">
                    <div><button id="confirmReject" class="reject-button">Yes, Reject</button><button class="close-modal">Cancel</button></div>
                </div>
            </div>
            <div id="deleteModal" class="modal" style="display:none;">
                <div class="modal-content">
                    <p>Delete this profile update request?</p>
                    <div><button id="confirmDelete" class="delete-button">Yes, Delete</button><button class="close-modal">Cancel</button></div>
                </div>
            </div>
        </div>
    </div>

    <div id="action-notification"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');
            mobileToggle && mobileToggle.addEventListener('click', () => navContainer.classList.toggle('active'));

            // Hover dropdowns
            const dropdowns = document.querySelectorAll('.dropdown');
            dropdowns.forEach(dd => {
                const menu = dd.querySelector('.dropdown-menu');
                dd.addEventListener('mouseenter', () => {
                    menu.style.opacity = '1';
                    menu.style.visibility = 'visible';
                    menu.style.transform = menu.classList.contains('center') ? 'translateX(-50%) translateY(0)' : 'translateY(0)';
                });
                dd.addEventListener('mouseleave', (e) => {
                    if (!dd.contains(e.relatedTarget)) {
                        menu.style.opacity = '0';
                        menu.style.visibility = 'hidden';
                        menu.style.transform = menu.classList.contains('center') ? 'translateX(-50%) translateY(10px)' : 'translateY(10px)';
                    }
                });
                menu.addEventListener('mouseleave', (e) => {
                    if (!dd.contains(e.relatedTarget)) {
                        menu.style.opacity = '0';
                        menu.style.visibility = 'hidden';
                        menu.style.transform = menu.classList.contains('center') ? 'translateX(-50%) translateY(10px)' : 'translateY(10px)';
                    }
                });
            });

            // === ✅ NOTIFICATIONS: CLICK → ROUTE + MARK READ (same rules as superhome.php) ===
            (function() {
                const list = document.querySelector('.notifcontainer');
                if (!list) return;

                const uuidRe = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

                list.addEventListener('click', (e) => {
                    const link = e.target.closest('.as-notif-link');
                    if (!link) return;

                    e.preventDefault();

                    const notifId = link.dataset.notifId || '';
                    const fromVal = (link.dataset.from || '').trim();
                    const reqproId = (link.dataset.reqproId || '').trim();

                    // mark as read (fire-and-forget) against THIS page's ajax handler
                    fetch('?ajax=mark_read&notif_id=' + encodeURIComponent(notifId), {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).catch(() => {});

                    // decide target (same logic as superhome.php)
                    let url = 'superhome.php';
                    if (/^register request$/i.test(fromVal)) {
                        url = 'superhome.php';
                    } else if (uuidRe.test(fromVal)) {
                        url = (reqproId && /^\d+$/.test(reqproId)) ?
                            'supereach.php?id=' + encodeURIComponent(reqproId) :
                            'supereach.php?user=' + encodeURIComponent(fromVal);
                    }
                    window.location.href = url;
                });

                // "Mark all as read"
                const markAll = document.getElementById('asMarkAllRead');
                if (markAll) {
                    markAll.addEventListener('click', (e) => {
                        e.preventDefault();
                        fetch('?ajax=mark_all_read', {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        }).finally(() => location.reload());
                    });
                }
            })();

            // ===== Page action toasts & modals (your original logic, kept) =====
            function toast(msg) {
                const n = document.getElementById('action-notification');
                n.textContent = msg;
                n.className = 'success';
                n.style.display = 'block';
                n.style.opacity = '1';
                setTimeout(() => {
                    n.style.opacity = '0';
                    setTimeout(() => n.style.display = 'none', 400);
                }, 1500);
            }

            const approveBtn = document.getElementById('approveBtn');
            const rejectBtn = document.getElementById('rejectBtn');
            const deleteBtn = document.getElementById('deleteBtn');
            const backBtn = document.getElementById('backBtn');

            const approveModal = document.getElementById('approveModal');
            const rejectModal = document.getElementById('rejectModal');
            const deleteModal = document.getElementById('deleteModal');

            const confirmApprove = document.getElementById('confirmApprove');
            const confirmReject = document.getElementById('confirmReject');
            const confirmDelete = document.getElementById('confirmDelete');
            const closeBtns = document.querySelectorAll('.close-modal');

            const form = document.getElementById('updateRequestForm');
            const formAction = document.getElementById('formAction');
            const reason = document.getElementById('reason');

            approveBtn && approveBtn.addEventListener('click', () => approveModal.style.display = 'block');
            rejectBtn && rejectBtn.addEventListener('click', () => rejectModal.style.display = 'block');
            deleteBtn && deleteBtn.addEventListener('click', () => deleteModal.style.display = 'block');
            backBtn && backBtn.addEventListener('click', () => {
                window.location.href = 'supernotif.php';
            });

            confirmApprove && confirmApprove.addEventListener('click', () => {
                formAction.value = 'approve';
                toast('Approved!');
                setTimeout(() => form.submit(), 700);
            });

            confirmReject && confirmReject.addEventListener('click', () => {
                formAction.value = 'reject';
                if (reason && reason.value) {
                    let hidden = form.querySelector('input[name="reason_for_rejection"]');
                    if (!hidden) {
                        hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'reason_for_rejection';
                        form.appendChild(hidden);
                    }
                    hidden.value = reason.value;
                }
                toast('Rejected.');
                setTimeout(() => form.submit(), 700);
            });

            confirmDelete && confirmDelete.addEventListener('click', () => {
                formAction.value = 'delete';
                form.submit();
            });

            closeBtns.forEach(btn => btn.addEventListener('click', () => {
                approveModal.style.display = 'none';
                rejectModal.style.display = 'none';
                deleteModal.style.display = 'none';
            }));

            window.addEventListener('click', (e) => {
                if (e.target === approveModal) approveModal.style.display = 'none';
                if (e.target === rejectModal) rejectModal.style.display = 'none';
                if (e.target === deleteModal) deleteModal.style.display = 'none';
            });
        });
    </script>
</body>

</html>