<?php
declare(strict_types=1);

/**
 * User-only gate for reports.php
 * - Requires a logged-in session
 * - Role must be 'User'
 * - Status must be 'Verified'
 * - Verifies against DB on each hit (defense-in-depth)
 */

session_start();

// Optional: extra safety headers (helps on back/forward caching)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Quick session check first
if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'user') {
    header('Location: user_login.php');
    exit();
}

// DB check to ensure the session still matches a User, Verified account
require_once __DIR__ . '/../backend/connection.php'; // must expose $pdo (PDO -> Supabase PG)

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
        // Invalidate session if it no longer matches a real verified User
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
    error_log('[REPORTS GUARD] ' . $e->getMessage());
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
    // prepare statement to detect seedling approvals
    $stApprovalType = $pdo->prepare("SELECT seedl_req_id FROM public.approval WHERE approval_id = :aid LIMIT 1");
    foreach ($notifs as $n) {
        if (empty($n['is_read'])) $unreadCount++;
    }
} catch (Throwable $e) {
    error_log('[REPORTS NOTIFS] ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMEMP Reports & Analytics</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #2b6625;
            --primary-dark: #1e4a1a;
            --white: #ffffff;
            --light-gray: #f5f5f5;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --transition: all 0.2s ease;
            --accent-color: #3a86ff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f9f9f9;
            padding-top: 100px;
            color: #333;
            line-height: 1.6;
        }

        /* Header Styles (Application Status navbar) */
        .as-header {
            position: fixed;
            inset: 0 0 auto 0;
            height: 58px;
            background: var(--primary-color);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
            transition: var(--transition)
        }

        .as-logo:hover img {
            transform: scale(1.05)
        }

        .as-logo::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -2px;
            width: 100%;
            height: 2px;
            background: var(--white);
            border-radius: 1px
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
            transition: var(--transition)
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
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: var(--transition);
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
            transition: var(--transition);
            font-size: 1.05rem
        }

        .as-dropdown-item i {
            width: 30px;
            font-size: 1.5rem;
            color: var(--primary-color) !important
        }

        .as-dropdown-item:hover {
            background: var(--light-gray);
            padding-left: 30px
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
        }

        .as-notif-header h3 {
            margin: 0;
            color: var(--primary-color);
            font-size: 1.1rem
        }

        .as-mark-all {
            color: var(--primary-color);
            text-decoration: none;
            font-size: .9rem;
            transition: var(--transition)
        }

        .as-mark-all:hover {
            color: var(--primary-dark);
            transform: scale(1.05)
        }

        .as-notif-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid #eee;
            background: #fff;
            transition: var(--transition);
        }

        .as-notif-item.unread {
            background: rgba(43, 102, 37, .05)
        }

        .as-notif-item:hover {
            background: #f9f9f9
        }

        .notifcontainer {
            height: 380px;
            overflow-y: auto;
            padding: 5px;
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
            color: var(--primary-color);
            font-size: 1.2rem
        }

        .as-notif-title {
            font-weight: 600;
            color: var(--primary-color);
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
            border-top: 1px solid #eee
        }

        .as-view-all {
            color: var(--primary-color);
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

        /* Main Content Styles */
        .main-content {
            padding: 90px 40px 40px 40px;
            max-width: 1200px;
            margin: 0 auto;
            margin-top: -8%;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Filter Container Styles */
        .filter-container {
            display: flex;
            justify-content: flex-start;
            margin: 20px 0 30px;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-dropdown {
            position: relative;
            display: inline-block;
        }

        .filter-btn {
            background-color: var(--white);
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            padding: 10px 15px;
            border-radius: var(--border-radius);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: var(--transition);
            font-size: 14px;
        }

        .filter-btn:hover {
            background-color: rgba(43, 102, 37, 0.1);
        }

        .filter-btn i {
            font-size: 12px;
        }

        .filter-content {
            display: none;
            position: absolute;
            background-color: var(--white);
            min-width: 200px;
            box-shadow: var(--box-shadow);
            z-index: 1;
            border-radius: var(--border-radius);
            overflow: hidden;
            top: 100%;
            left: 0;
        }

        .filter-dropdown:hover .filter-content {
            display: block;
        }

        .filter-item {
            color: var(--primary-dark);
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            transition: var(--transition);
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        .filter-item:hover {
            background-color: rgba(43, 102, 37, 0.1);
            padding-left: 20px;
        }

        .filter-item.active {
            background-color: rgba(43, 102, 37, 0.2);
            font-weight: 600;
        }

        .apply-filter-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: var(--border-radius);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: var(--transition);
            font-size: 14px;
            white-space: nowrap;
        }

        .apply-filter-btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        /* Page Header */
        .page-header {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
            border-left: 4px solid var(--primary-color);
        }

        .page-title {
            color: var(--primary-color);
            font-size: 28px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-title i {
            font-size: 32px;
        }

        .page-description {
            color: #555;
            font-size: 16px;
            line-height: 1.6;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            text-align: center;
            transition: var(--transition);
            border-top: 4px solid var(--primary-color);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .stat-card h3 {
            color: var(--primary-color);
            font-size: 16px;
            margin-bottom: 10px;
        }

        .stat-card .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 10px;
        }

        .stat-card .stat-description {
            font-size: 14px;
            color: #666;
        }

        /* Collapsible Sections */
        .collapsible-section {
            margin-bottom: 25px;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            transition: all 0.3s ease;
            background: white;
            border: 1px solid #e0e0e0;
        }

        .section-header {
            padding: 18px 25px;
            background-color: white;
            color: var(--primary-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: background-color 0.3s ease;
            border-bottom: 1px solid #e0e0e0;
        }

        .section-header:hover {
            background-color: #f9f9f9;
        }

        .section-header h2 {
            color: var(--primary-color);
            margin: 0;
            font-size: 20px;
            display: flex;
            align-items: center;
        }

        .section-header h2 i {
            margin-right: 12px;
            font-size: 1.1em;
            color: var(--primary-color);
        }

        .toggle-icon {
            transition: transform 0.3s ease;
            font-size: 1.2em;
            color: var(--primary-color);
        }

        .section-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: white;
        }

        .section-content-inner {
            padding: 25px;
        }

        .collapsible-section.active .section-content {
            max-height: 2000px;
        }

        .collapsible-section.active .toggle-icon {
            transform: rotate(180deg);
        }

        /* Content Styles */
        p {
            margin-bottom: 20px;
            color: #555;
            line-height: 1.6;
        }

        /* Chart Styles */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
            margin: 20px 0;
        }

        /* Table Styles */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 0.9em;
        }

        .data-table th {
            background-color: var(--primary-color);
            color: white;
            padding: 12px;
            text-align: left;
        }

        .data-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        .data-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .data-table tr:hover {
            background-color: #f1f1f1;
        }

        /* Summary Stats */
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-item {
            background-color: #f8f8f8;
            border-radius: var(--border-radius);
            padding: 15px;
            text-align: center;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #666;
        }

        /* Progress Bars */
        .progress-container {
            margin-top: 15px;
        }

        .progress-item {
            margin-bottom: 15px;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .progress-bar {
            height: 10px;
            background-color: #eee;
            border-radius: 5px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background-color: var(--primary-color);
            border-radius: 5px;
            transition: width 0.5s ease;
        }

        /* Button Styles */
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .btn i {
            margin-right: 8px;
        }

        .btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .main-nav {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 90px 20px 20px 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .summary-stats {
                grid-template-columns: 1fr;
            }
            
            .as-logo img {
                height: 32px;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .section-header h2 {
                font-size: 18px;
            }

            .filter-container {
                flex-direction: row;
                align-items: center;
                justify-content: flex-start;
                gap: 10px;
                margin-top: -11%;
            }

            .filter-group {
                width: auto;
                flex-direction: row;
                gap: 10px;
            }

            .filter-dropdown {
                width: auto;
            }

            .filter-content {
                width: auto;
            }

            .apply-filter-btn {
                width: auto;
                justify-content: center;
            }

            .as-header {
                padding: 0 15px;
            }

            .as-dropdown-menu {
                min-width: 280px;
            }

            .as-notifications {
                min-width: 320px;
            }
        }

        @media (max-width: 480px) {
            .as-dropdown-menu {
                min-width: 250px;
                right: -50px;
            }

            .as-notifications {
                min-width: 280px;
                right: -80px;
            }

            .as-dropdown-item {
                padding: 12px 20px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Application Status navbar -->
    <header class="as-header">
        <div class="as-logo">
            <a href="user_home.php"><img src="seal.png" alt="Site Logo"></a>
        </div>

        <div class="as-nav">
            <!-- App menu -->
            <div class="as-item">
                <div class="as-icon"><i class="fas fa-bars"></i></div>
                <div class="as-dropdown-menu as-center">
                    <a href="user_reportaccident.php" class="as-dropdown-item"><i class="fas fa-file-invoice"></i><span>Report Incident</span></a>
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
                                    <div class="as-notif-title">No notifications</div>
                                    <div class="as-notif-message">You don't have any notifications at the moment.</div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifs as $n): ?>
                                <?php
                                $unread = empty($n['is_read']);
                                // Convert UTC timestamp to Manila timezone before calculating elapsed time
                                if ($n['created_at']) {
                                    $dt = new DateTime((string)$n['created_at'], new DateTimeZone('UTC'));
                                    $dt->setTimezone(new DateTimeZone('Asia/Manila'));
                                    $ts = $dt->getTimestamp();
                                } else {
                                    $ts = time();
                                }
                                // Determine title: if approval -> check if it's a seedlings approval
                                $title = 'Notification';
                                if (!empty($n['approval_id'])) {
                                    try {
                                        $stApprovalType->execute([':aid' => $n['approval_id']]);
                                        $aprRow = $stApprovalType->fetch(PDO::FETCH_ASSOC);
                                        if (!empty($aprRow) && !empty($aprRow['seedl_req_id'])) {
                                            $title = 'Seedlings Request Update';
                                        } else {
                                            $title = 'Permit Update';
                                        }
                                    } catch (Throwable $e) {
                                        // fallback
                                        $title = 'Permit Update';
                                    }
                                } elseif (!empty($n['incident_id'])) {
                                    $title = 'Incident Update';
                                }
                        
                                $cleanMsg = (function ($m) {
                                    $t = trim((string)$m);
                                    $t = preg_replace('/\s*[,\s]*You\s+can\s+download.*?(?:now|below|here)[,\s\.]*/i', '', $t);
                                    $t = preg_replace('/\s*\(?\breason\b.*$/i', '', $t);
                                    return trim(preg_replace('/\s+/', ' ', $t)) ?: "Update available.";
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
                            <?php endforeach; ?>
                        <?php endif; ?>
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
    
    <div class="main-content">
        <!-- Filter dropdown container above the title -->
        <div class="filter-container">
            <div class="filter-group">
                <div class="filter-dropdown">
                    <button class="filter-btn">
                        <i class="fas fa-chart-pie"></i> Filter by Category
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="filter-content">
                        <a href="user_home.php" class="filter-item">All Categories</a>
                        <a href="mpa-management.php" class="filter-item">MPA Management</a>
                        <a href="habitat.php" class="filter-item">Habitat Assessment</a>
                        <a href="species.php" class="filter-item">Species Monitoring</a>
                        <a href="reports.php" class="filter-item active">Reports & Analytics</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-chart-bar"></i> CMEMP Reports & Analytics
            </h1>
            <p class="page-description">
                Comprehensive reports and analytics dashboard for the Coastal and Marine Ecosystems Management Program (CMEMP).
                Track program performance, habitat assessments, MPA networking, and biodiversity-friendly enterprises.
            </p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Program Performance</h3>
                <div class="stat-value">100%+</div>
                <p class="stat-description">Targets Achieved</p>
            </div>
            <div class="stat-card">
                <h3>Protected Areas</h3>
                <div class="stat-value">18/18</div>
                <p class="stat-description">PAs Monitored</p>
            </div>
            <div class="stat-card">
                <h3>Water Quality</h3>
                <div class="stat-value">15</div>
                <p class="stat-description">PAs Monitored</p>
            </div>
            <div class="stat-card">
                <h3>MPA Networks</h3>
                <div class="stat-value">47</div>
                <p class="stat-description">Identified</p>
            </div>
        </div>

        <!-- Habitat Assessment Section -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-tree"></i> Habitat Assessment</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <div class="chart-container">
                        <canvas id="habitatExtentChart"></canvas>
                    </div>
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Region</th>
                                <th>Protected Area</th>
                                <th>Habitat</th>
                                <th>Extent (ha)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>R4A</td>
                                <td>Maragondon and Ternate</td>
                                <td>Seagrass</td>
                                <td>2.20</td>
                            </tr>
                            <tr>
                                <td>R4A</td>
                                <td>Ragay Gulf</td>
                                <td>Coral Reefs</td>
                                <td>510.17</td>
                            </tr>
                            <tr>
                                <td>R7</td>
                                <td>Olango Is Wildlife Sanctuary</td>
                                <td>Coral Reefs</td>
                                <td>574.25</td>
                            </tr>
                            <tr>
                                <td>R7</td>
                                <td>Olango Is Wildlife Sanctuary</td>
                                <td>Seagrass</td>
                                <td>3,790.85</td>
                            </tr>
                            <tr>
                                <td>R10</td>
                                <td>Bacolod-Kauswagan PLS</td>
                                <td>Coral Reefs</td>
                                <td>262.26</td>
                            </tr>
                            <tr>
                                <td>R10</td>
                                <td>Initao-Libertad PLS</td>
                                <td>Seagrass</td>
                                <td>524.87</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div class="progress-container">
                        <div class="progress-item">
                            <div class="progress-label">
                                <span>Mangrove Expansion</span>
                                <span>57.4 ha</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 100%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MPA Networking Section -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-network-wired"></i> MPA Networking</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <div class="chart-container">
                        <canvas id="mpaNetworkChart"></canvas>
                    </div>
                    
                    <div class="progress-container">
                        <div class="progress-item">
                            <div class="progress-label">
                                <span>Establishment Level</span>
                                <span>44 MPANs</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 94%"></div>
                            </div>
                        </div>
                        <div class="progress-item">
                            <div class="progress-label">
                                <span>Strengthening</span>
                                <span>2 MPANs</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 4%"></div>
                            </div>
                        </div>
                        <div class="progress-item">
                            <div class="progress-label">
                                <span>Sustaining</span>
                                <span>1 MPAN</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 2%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Biodiversity-Friendly Enterprises Section -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-leaf"></i> Biodiversity-Friendly Enterprises</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <div class="summary-stats">
                        <div class="stat-item">
                            <div class="stat-value">114/111</div>
                            <div class="stat-label">POs Assisted</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">₱37.8M</div>
                            <div class="stat-label">Financial Assistance</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">6</div>
                            <div class="stat-label">Training Sessions</div>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <canvas id="bdfeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Social Marketing & Public Awareness Section -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-bullhorn"></i> Social Marketing & Public Awareness</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <div class="chart-container">
                        <canvas id="awarenessChart"></canvas>
                    </div>
                    
                    <div class="chart-container">
                        <canvas id="communicationChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Capacity Building Section -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-graduation-cap"></i> Capacity Building</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <div class="summary-stats">
                        <div class="stat-item">
                            <div class="stat-value">70</div>
                            <div class="stat-label">Personnel Trained</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">63</div>
                            <div class="stat-label">Extension Officers</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">4</div>
                            <div class="stat-label">Webinar Episodes</div>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <canvas id="webinarChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Relative time labels for notifications
            function timeAgo(seconds) {
                if (seconds < 60) return 'just now';
                const m = Math.floor(seconds / 60);
                if (m < 60) return `${m} minute${m > 1 ? 's' : ''} ago`;
                const h = Math.floor(m / 60);
                if (h < 24) return `${h} hour${h > 1 ? 's' : ''} ago`;
                const d = Math.floor(h / 24);
                if (d < 7) return `${d} day${d > 1 ? 's' : ''} ago`;
                const w = Math.floor(d / 7);
                if (w < 5) return `${w} week${w > 1 ? 's' : ''} ago`;
                const mo = Math.floor(d / 30);
                if (mo < 12) return `${mo} month${mo > 1 ? 's' : ''} ago`;
                const y = Math.floor(d / 365);
                return `${y} year${y > 1 ? 's' : ''} ago`;
            }

            document.querySelectorAll('.as-notif-time[data-ts]').forEach(el => {
                const tsMs = Number(el.dataset.ts || 0) * 1000;
                if (!tsMs) return;
                const diffSec = Math.floor((Date.now() - tsMs) / 1000);
                el.textContent = timeAgo(diffSec);
                try {
                    const manilaFmt = new Intl.DateTimeFormat('en-PH', {
                        timeZone: 'Asia/Manila',
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: 'numeric',
                        minute: '2-digit',
                        second: '2-digit'
                    });
                    el.title = manilaFmt.format(new Date(tsMs));
                } catch (err) {
                    el.title = new Date(tsMs).toLocaleString();
                }
            });

            // Mark all as read functionality
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

            // Click a single notification
            const list = document.querySelector('.as-notifications');
            list?.addEventListener('click', async (e) => {
                const link = e.target.closest('.as-notif-link');
                if (!link) return;
                e.preventDefault();

                // Optimistic mark read in UI
                const row = link.closest('.as-notif-item');
                const wasUnread = row?.classList.contains('unread');
                row?.classList.remove('unread');

                // Update badge count if needed
                if (badge && wasUnread) {
                    const current = parseInt(badge.textContent || '0', 10) || 0;
                    const next = Math.max(0, current - 1);
                    if (next <= 0) {
                        badge.style.display = 'none';
                    } else {
                        badge.textContent = String(next);
                    }
                }

                // Best-effort server mark
                const nid = link.dataset.notifId || '';
                if (nid) {
                    try {
                        await fetch(location.pathname + `?ajax=mark_read&notif_id=${encodeURIComponent(nid)}`, {
                            method: 'POST',
                            credentials: 'same-origin'
                        });
                    } catch {}
                }

                // Routing
                if (link.dataset.approvalId) {
                    // Permit-related → Application Status page
                    window.location.href = 'applicationstatus.php';
                    return;
                }
                if (link.dataset.incidentId) {
                    // Incident-related deep link
                    window.location.href = `user_reportaccident.php?view=${encodeURIComponent(link.dataset.incidentId)}`;
                    return;
                }
                // Fallback: Application Status
                window.location.href = 'applicationstatus.php';
            });

            // Collapsible sections functionality
            const sectionHeaders = document.querySelectorAll('.section-header');
            sectionHeaders.forEach(header => {
                header.addEventListener('click', () => {
                    const section = header.parentElement;
                    section.classList.toggle('active');
                });
            });

            // Filter functionality
            const filterItems = document.querySelectorAll('.filter-item');
            filterItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    const filterMenu = this.parentElement;
                    
                    // Remove active class from all items in this menu
                    filterMenu.querySelectorAll('.filter-item').forEach(i => i.classList.remove('active'));
                    // Add active class to clicked item
                    this.classList.add('active');
                    
                    // Update the filter button text
                    const filterBtn = filterMenu.parentElement.querySelector('.filter-btn');
                    if (filterBtn) {
                        const icon = filterBtn.querySelector('i:first-child');
                        filterBtn.innerHTML = '';
                        filterBtn.appendChild(icon);
                        filterBtn.appendChild(document.createTextNode(this.textContent));
                        const chevron = document.createElement('i');
                        chevron.className = 'fas fa-chevron-down';
                        filterBtn.appendChild(chevron);
                    }
                    // Redirect to the href of the clicked filter item
                    const href = this.getAttribute('href');
                    if (href && href !== '#') {
                        window.location.href = href;
                    }
                });
            });

            // Charts initialization
            // Habitat Extent Chart
            const habitatExtentCtx = document.getElementById('habitatExtentChart').getContext('2d');
            const habitatExtentChart = new Chart(habitatExtentCtx, {
                type: 'pie',
                data: {
                    labels: ['Coral Reefs', 'Seagrass', 'Mangroves'],
                    datasets: [{
                        data: [1346.68, 4821.92, 28],
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(153, 102, 255, 0.7)'
                        ],
                        borderColor: [
                            'rgba(54, 162, 235, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(153, 102, 255, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += context.raw.toLocaleString() + ' ha';
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
            
            // MPA Network Chart
            const mpaNetworkCtx = document.getElementById('mpaNetworkChart').getContext('2d');
            const mpaNetworkChart = new Chart(mpaNetworkCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Establishment', 'Strengthening', 'Sustaining'],
                    datasets: [{
                        data: [44, 2, 1],
                        backgroundColor: [
                            'rgba(43, 102, 37, 0.7)',
                            'rgba(255, 206, 86, 0.7)',
                            'rgba(54, 162, 235, 0.7)'
                        ],
                        borderColor: [
                            'rgba(43, 102, 37, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(54, 162, 235, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        }
                    }
                }
            });
            
            // BDFE Chart
            const bdfeCtx = document.getElementById('bdfeChart').getContext('2d');
            const bdfeChart = new Chart(bdfeCtx, {
                type: 'bar',
                data: {
                    labels: ['Sea Cucumber Ranching', 'Ecotourism', 'Sustainable Fisheries', 'Mangrove Products'],
                    datasets: [{
                        label: 'Number of Enterprises',
                        data: [15, 8, 12, 5],
                        backgroundColor: 'rgba(43, 102, 37, 0.7)',
                        borderColor: 'rgba(43, 102, 37, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Enterprises'
                            }
                        }
                    }
                }
            });
            
            // Awareness Chart
            const awarenessCtx = document.getElementById('awarenessChart').getContext('2d');
            const awarenessChart = new Chart(awarenessCtx, {
                type: 'bar',
                data: {
                    labels: ['Aware of PA Status', 'Feel Need to Protect'],
                    datasets: [{
                        label: 'Percentage of Respondents',
                        data: [96.7, 39.2],
                        backgroundColor: [
                            'rgba(43, 102, 37, 0.7)',
                            'rgba(75, 192, 192, 0.7)'
                        ],
                        borderColor: [
                            'rgba(43, 102, 37, 1)',
                            'rgba(75, 192, 192, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Percentage (%)'
                            }
                        }
                    }
                }
            });
            
            // Communication Chart
            const communicationCtx = document.getElementById('communicationChart').getContext('2d');
            const communicationChart = new Chart(communicationCtx, {
                type: 'polarArea',
                data: {
                    labels: ['Television', 'Radio', 'Social Media', 'DENR/LGU Officials'],
                    datasets: [{
                        data: [40, 30, 20, 10],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(255, 206, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(75, 192, 192, 1)'
                        ],
                        borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });
        
        // Webinar Chart
        const webinarCtx = document.getElementById('webinarChart').getContext('2d');
        const webinarChart = new Chart(webinarCtx, {
            type: 'line',
            data: {
                labels: ['Episode 1', 'Episode 2', 'Episode 3', 'Episode 4'],
                datasets: [{
                    label: 'Average Attendance',
                    data: [383, 383, 383, 383],
                    borderColor: 'rgba(43, 102, 37, 1)',
                    backgroundColor: 'rgba(43, 102, 37, 0.1)',
                    tension: 0.1,
                    fill: true
                }, {
                    label: 'Engagement (Comments/Reacts)',
                    data: [1300, 1300, 1300, 1300],
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.1,
                    fill: true
                }, {
                    label: 'Reach',
                    data: [17225, 17225, 17225, 17225],
                    borderColor: 'rgba(255, 159, 64, 1)',
                    backgroundColor: 'rgba(255, 159, 64, 0.1)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Count'
                        }
                    }
                }
            }
        });
    });
    </script>
</body>
</html>