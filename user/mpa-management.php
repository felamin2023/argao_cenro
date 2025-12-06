<?php
declare(strict_types=1);

/**
 * User-only gate for mpa-management.php
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
    error_log('[MPA-MANAGEMENT GUARD] ' . $e->getMessage());
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
    error_log('[MPA-MANAGEMENT NOTIFS] ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MPA Management & Networking</title>
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
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Content Header */
        .content-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 20px;
            position: relative;
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

        /* Cards */
        .program-highlight,
        .component-section {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            position: relative;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .program-highlight:hover,
        .component-section:hover {
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
            transform: translateY(-3px);
        }

        h2, h3 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-weight: 600;
        }

        h2 {
            font-size: 22px;
        }

        h3 {
            font-size: 18px;
        }

        p {
            color: #555;
            margin-bottom: 20px;
        }

        /* Performance Metrics */
        .performance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }

        .metric-card {
            background: var(--white);
            padding: 20px;
            text-align: center;
            box-shadow: var(--box-shadow);
            border-radius: var(--border-radius);
            transition: var(--transition);
            border-top: 4px solid var(--accent-color);
            border: none;
        }

        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .metric-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        /* Enhanced Chart Container */
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
            margin: 20px 0;
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
        }
        
        .chart-legend-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 20px;
        }
        
        .chart-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: center;
            margin-bottom: 15px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            font-size: 14px;
        }
        
        .legend-color {
            width: 15px;
            height: 15px;
            border-radius: 3px;
            margin-right: 8px;
        }

        /* Dashboard Cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 25px 0;
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

        /* Performance Items */
        .performance-item {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            border-left: 4px solid var(--primary-color);
        }

        .performance-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
        }

        .performance-item h4 {
            color: var(--primary-color);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        .performance-item h4 i {
            margin-right: 10px;
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

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .two-column {
                grid-template-columns: 1fr;
            }
            
            .stat-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .main-nav {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 90px 20px 20px 20px;
                margin-top: -60px;
            }
            
            .stat-grid {
                grid-template-columns: 1fr;
            }
            
            .as-logo img {
                height: 32px;
            }
            
            .chart-container {
                height: 300px;
            }
            
            .dashboard-cards {
                grid-template-columns: 1fr;
            }
            
            .section-header h2 {
                font-size: 18px;
            }

            .filter-container {
                margin-top: -5%;
                flex-direction: row;
                align-items: center;
                justify-content: flex-start;
                gap: 10px;
            }

            .filter-group {
                width: auto;
                flex-direction: row;
                justify-content: flex-start;
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
                        <a href="mpa-management.php" class="filter-item active">MPA Management</a>
                        <a href="habitat.php" class="filter-item">Habitat Assessment</a>
                        <a href="species.php" class="filter-item">Species Monitoring</a>
                        <a href="reports.php" class="filter-item">Reports & Analytics</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="component-section">
            <div class="content-header">
                <h3 style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-weight: 700; font-size: 24px; color: var(--primary-dark); text-align: center; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 20px;">MPA Management & Networking</h3>
            </div>
            
            <div class="figure-container">
                <div class="chart-container">
                    <canvas id="performanceChart"></canvas>
                    <div class="chart-legend-container">
                        <div class="chart-legend" id="chartLegend"></div>
                    </div>
                </div>
            </div>
           
            <div class="dashboard-cards">
                <div class="stat-card">
                    <h3>Established</h3>
                    <div class="stat-value">2017</div>
                    <p class="stat-description">Year CMEMP was first implemented</p>
                </div>
                <div class="stat-card">
                    <h3>Coverage</h3>
                    <div class="stat-value">Nationwide</div>
                    <p class="stat-description">All coastal regions of the Philippines</p>
                </div>
                <div class="stat-card">
                    <h3>Approach</h3>
                    <div class="stat-value">Science-Based</div>
                    <p class="stat-description">Community-involved ecosystem management</p>
                </div>
            </div>
        </div>

        <!-- 2020 Regional Performance Section -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-chart-line"></i> 2020 Regional Performance</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <p>Despite challenges from the COVID-19 pandemic, CMEMP achieved 100% or higher completion of targets in its fourth year of implementation.</p>
                    
                    <div class="dashboard-cards">
                        <div class="stat-card">
                            <h3>Protected Areas</h3>
                            <div class="stat-value">100%</div>
                            <p class="stat-description">of targeted PAs assessed and monitored</p>
                        </div>
                        <div class="stat-card">
                            <h3>Habitat Monitoring</h3>
                            <div class="stat-value">103%</div>
                            <p class="stat-description">of targeted MPA habitats regularly monitored</p>
                        </div>
                        <div class="stat-card">
                            <h3>Stakeholder Training</h3>
                            <div class="stat-value">115%</div>
                            <p class="stat-description">of targeted stakeholders capacitated</p>
                        </div>
                        <div class="stat-card">
                            <h3>Database Updates</h3>
                            <div class="stat-value">135%</div>
                            <p class="stat-description">of targeted database updates completed</p>
                        </div>
                    </div>
                    
                    <p>Activities included baseline assessments of corals, mangroves, and seagrass in protected areas, providing updated data on habitat extent and conditions.</p>
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
                    <p>An MPA Network (MPAN) is a collection of individual MPAs operating cooperatively at various spatial scales to achieve objectives that a single reserve cannot achieve.</p>
                    
                    <div class="performance-grid">
                        <div class="performance-item">
                            <h4><i class="fas fa-graduation-cap"></i> MPAN Training</h4>
                            <p>70 DENR personnel trained through Open Distance Learning on MPA Networking from May-August 2020</p>
                        </div>
                        <div class="performance-item">
                            <h4><i class="fas fa-clipboard-check"></i> MPAN Assessment</h4>
                            <p>23 MPANs reassessed in 2020 with new threshold criteria</p>
                        </div>
                        <div class="performance-item">
                            <h4><i class="fas fa-layer-group"></i> MPAN Levels</h4>
                            <p>44 at establishment level, 2 for strengthening, 1 for sustaining</p>
                        </div>
                        <div class="performance-item">
                            <h4><i class="fas fa-file-signature"></i> Policy Development</h4>
                            <p>Joint DA-DENR-DILG Memorandum Circular finalized to guide MPAN establishment</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Maintenance & Protection Activities Section -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-shield-alt"></i> Maintenance & Protection Activities</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <p>38 NIPAS MPAs conducted maintenance and protection activities including patrolling, habitat surveillance, and facility repairs.</p>
                    
                    <div class="performance-grid">
                        <div class="performance-item">
                            <h4><i class="fas fa-map-marked-alt"></i> Region 1</h4>
                            <p>Coral reef monitoring using photo transect method in BBBIDA MPAN</p>
                        </div>
                        <div class="performance-item">
                            <h4><i class="fas fa-star-of-life"></i> Region 7</h4>
                            <p>COTS (Crown-of-Thorns Starfish) removal in Olango Island Wildlife Sanctuary</p>
                        </div>
                        <div class="performance-item">
                            <h4><i class="fas fa-ship"></i> Region 8</h4>
                            <p>Regular seaborne patrolling in Biri-Larosa Protected Landscape</p>
                        </div>
                        <div class="performance-item">
                            <h4><i class="fas fa-turtle"></i> Marine Wildlife</h4>
                            <p>Marine turtles voluntarily turned over by communities were released to protected areas</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Future Directions Section -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-road"></i> Future Directions</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <p>The CMEMP program has identified several key priorities for future implementation:</p>
                    
                    <div class="performance-grid">
                        <div class="performance-item">
                            <h4><i class="fas fa-handshake"></i> Convergence</h4>
                            <p>Strengthening initiatives with other agencies</p>
                        </div>
                        <div class="performance-item">
                            <h4><i class="fas fa-book"></i> Policy Development</h4>
                            <p>Developing supplemental guidance for CMEMP implementation</p>
                        </div>
                        <div class="performance-item">
                            <h4><i class="fas fa-flask"></i> Water Quality</h4>
                            <p>Mainstreaming water quality monitoring in all NIPAS MPAs</p>
                        </div>
                        <div class="performance-item">
                            <h4><i class="fas fa-business-time"></i> BDFE</h4>
                            <p>Expanding Biodiversity-Friendly Enterprise Development</p>
                        </div>
                        <div class="performance-item">
                            <h4><i class="fas fa-graduation-cap"></i> Capacity Building</h4>
                            <p>Developing plans for DENR personnel training</p>
                        </div>
                        <div class="performance-item">
                            <h4><i class="fas fa-chart-pie"></i> Economic Valuation</h4>
                            <p>Assessing the economic value of coastal and marine ecosystem services</p>
                        </div>
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
                    // Remove active class from all items
                    filterItems.forEach(i => i.classList.remove('active'));
                    // Add active class to clicked item
                    this.classList.add('active');
                    
                    // Update the filter button text
                    const filterBtn = document.querySelector('.filter-btn');
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

            // Create the performance chart
            const ctx = document.getElementById('performanceChart').getContext('2d');
            const performanceChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['PAs assessed', 'PAs monitored', 'PAs water quality', 'MPA Network', 'Habitats monitored'],
                    datasets: [
                        {
                            label: 'Target',
                            data: [100, 100, 100, 100, 100],
                            backgroundColor: 'rgba(169, 169, 169, 0.7)',
                            borderColor: 'rgba(169, 169, 169, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Actual',
                            data: [12, 100, 100, 100, 103],
                            backgroundColor: 'rgba(43, 102, 37, 0.7)',
                            borderColor: 'rgba(43, 102, 37, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 120,
                            title: {
                                display: true,
                                text: 'Percentage (%)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.raw + '%';
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 1500,
                        easing: 'easeInOutQuart'
                    }
                }
            });
            
            // Custom legend
            const legendItems = performanceChart.data.datasets.map((dataset, i) => {
                return {
                    label: dataset.label,
                    backgroundColor: dataset.backgroundColor,
                    borderColor: dataset.borderColor
                };
            });
            
            const legendContainer = document.getElementById('chartLegend');
            legendItems.forEach(item => {
                const legendItem = document.createElement('div');
                legendItem.className = 'legend-item';
                
                const colorBox = document.createElement('div');
                colorBox.className = 'legend-color';
                colorBox.style.backgroundColor = item.backgroundColor;
                colorBox.style.border = `1px solid ${item.borderColor}`;
                
                const text = document.createElement('span');
                text.textContent = item.label;
                
                legendItem.appendChild(colorBox);
                legendItem.appendChild(text);
                legendContainer.appendChild(legendItem);
            });

            // Filter button functionality
            const applyFilterBtn = document.querySelector('.apply-filter-btn');
            if (applyFilterBtn) {
                applyFilterBtn.addEventListener('click', function() {
                    // Here you would implement your filter functionality
                    alert('Filters applied. Implement your filter functionality here.');
                });
            }
        });
    </script>
</body>
</html>