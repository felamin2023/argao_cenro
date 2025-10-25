<?php

declare(strict_types=1);

session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: user_login.php');
    exit();
}

require_once __DIR__ . '/../backend/connection.php';

$notifs = [];
$unreadCount = 0;

/* AJAX endpoints used by the header JS:
   - POST ?ajax=mark_all_read
   - POST ?ajax=mark_read&notif_id=...
*/
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    try {
        if ($_GET['ajax'] === 'mark_all_read') {
            $u = $pdo->prepare('
                update public.notifications
                set is_read = true
                where "to" = :uid and (is_read is null or is_read = false)
            ');
            $u->execute([':uid' => $_SESSION['user_id']]);
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
                where notif_id = :nid and "to" = :uid
            ');
            $u->execute([':nid' => $nid, ':uid' => $_SESSION['user_id']]);
            echo json_encode(['success' => true]);
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'Unknown action']);
    } catch (Throwable $e) {
        error_log('[USER-NOTIF AJAX] ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

try {
    $ns = $pdo->prepare('
        select notif_id, approval_id, incident_id, message, is_read, created_at
        from public.notifications
        where "to" = :uid
        order by created_at desc
        limit 30
    ');
    $ns->execute([':uid' => $_SESSION['user_id']]);
    $notifs = $ns->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($notifs as $n) {
        if (empty($n['is_read'])) {
            $unreadCount++;
        }
    }
} catch (Throwable $e) {
    error_log('[USER-NOTIFICATION PAGE] ' . $e->getMessage());
}

$cleanNotificationMessage = static function (?string $m): string {
    $t = trim((string)$m);
    if ($t === '') return "There's an update.";
    $t = preg_replace('/\s*\(?\b(rejection\s*reason|reason)\b\s*[:\--]\s*.*$/i', '', $t);
    $t = preg_replace('/\s*\b(because|due\s+to)\b\s*.*/i', '', $t);
    $t = preg_replace('/\s{2,}/', ' ', $t);
    $t = trim($t);
    return $t !== '' ? $t : "There's an update.";
};

$renderNotificationItem = static function (array $n) use ($cleanNotificationMessage): string {
    $notifId = (string)($n['notif_id'] ?? '');
    if ($notifId === '') return '';

    $isUnread = empty($n['is_read']);
    $title = !empty($n['approval_id'])
        ? 'Permit Update'
        : (!empty($n['incident_id']) ? 'Incident Update' : 'Notification');

    $rawMessage = (string)($n['message'] ?? '');
    $summary = $cleanNotificationMessage($rawMessage);
    $fullMessage = trim($rawMessage) !== '' ? trim($rawMessage) : $summary;

    $createdAtRaw = (string)($n['created_at'] ?? '');
    $ts = $createdAtRaw !== '' ? strtotime($createdAtRaw) : false;
    if ($ts === false) $ts = time();

    $iconClass = !empty($n['incident_id']) ? 'alert' : 'approve';

    $actionUrl = '';
    $actionLabel = '';
    if (!empty($n['approval_id'])) {
        $actionUrl = 'applicationstatus.php';
        $actionLabel = 'Open Application Status';
    } elseif (!empty($n['incident_id'])) {
        $actionUrl = 'user_reportaccident.php?view=' . rawurlencode((string)$n['incident_id']);
        $actionLabel = 'View Incident Report';
    }

    $attrs = [
        'data-notif-id'    => $notifId,
        'data-approval-id' => (string)($n['approval_id'] ?? ''),
        'data-incident-id' => (string)($n['incident_id'] ?? ''),
        'data-title'       => $title,
        'data-summary'     => $summary,
        'data-full-message' => $fullMessage,
        'data-ts'          => (string)$ts,
        'data-created'     => $createdAtRaw,
        'data-is-read'     => $isUnread ? '0' : '1',
        'data-action-url'  => $actionUrl,
        'data-action-label' => $actionLabel,
    ];

    $attrStr = '';
    foreach ($attrs as $k => $v) {
        $attrStr .= ' ' . $k . '="' . htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }

    $classes = 'notification-item' . ($isUnread ? ' unread' : '');
    $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $summaryEsc = htmlspecialchars($summary, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $tsEsc = htmlspecialchars((string)$ts, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $viewButtonClass = $isUnread ? 'action-button view-details-btn' : 'action-button action-button--half view-details-btn';
    $viewButton = '<button type="button" class="' . $viewButtonClass . '">View Details</button>';
    $markButton = $isUnread
        ? '<button type="button" class="action-button mark-read-btn">Mark as Read</button>'
        : '';

    return <<<HTML
        <div class="{$classes}"{$attrStr}>
            <div class="notification-title">
                <div class="notification-icon {$iconClass}"><i class="fas fa-exclamation-circle"></i></div>
                {$titleEsc}
            </div>
            <div class="notification-content">
                <div class="notification-message">{$summaryEsc}</div>
                <div class="notification-time" data-ts="{$tsEsc}">just now</div>
            </div>
            <div class="notification-actions">
                {$viewButton}
                {$markButton}
            </div>
        </div>
    HTML;
};

// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <style>
        :root {
            --primary-color: #2b6625;
            --primary-dark: #1e4a1a;
            --white: #ffffff;
            --light-gray: #f5f5f5;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --transition: all 0.2s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f9f9f9;
            padding-top: 80px;
        }

        /* Notifications dropdown styles copied from user_home.php */
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
            z-index: 999;
        }

        .as-item:hover>.as-dropdown-menu,
        .as-dropdown-menu:hover {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .as-center {
            left: 50%;
            right: auto;
            transform: translateX(-50%) translateY(10px);
        }

        .as-center.as-dropdown-menu:hover,
        .as-item:hover>.as-center {
            transform: translateX(-50%) translateY(0);
        }

        .as-dropdown-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 25px;
            text-decoration: none;
            color: #111;
            transition: var(--as-trans);
            font-size: 1.05rem;
        }

        .as-dropdown-item i {
            width: 30px;
            font-size: 1.5rem;
            color: var(--as-primary) !important;
        }

        .as-dropdown-item:hover {
            background: var(--as-light-gray);
            padding-left: 30px;
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

        /* Header Styles */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--primary-color);
            color: var(--white);
            padding: 0 30px;
            height: 58px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        /* Logo */
        .logo {
            height: 45px;
            display: flex;
            margin-top: -1px;
            align-items: center;
            position: relative;
        }

        .logo a {
            display: flex;
            align-items: center;
            height: 90%;
        }

        .logo img {
            height: 98%;
            width: auto;
            transition: var(--transition);
        }

        .logo:hover img {
            transform: scale(1.05);
        }

        /* Navigation Container */
        .nav-container {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        /* Navigation Items - Larger Icons */
        .nav-item {
            position: relative;
        }

        .nav-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: rgb(233, 255, 242);
            border-radius: 12px;
            cursor: pointer;
            transition: var(--transition);
            color: black;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        }

        .nav-icon:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.15);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }

        .nav-icon i {
            font-size: 1.3rem;
            color: inherit;
            transition: color 0.3s ease;
        }

        .nav-icon.active {
            position: relative;
        }

        .nav-icon.active::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 2px;
            background-color: var(--white);
            border-radius: 2px;
        }

        /* Dropdown Menu */
        .dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: var(--white);
            min-width: 300px;
            max-width: 90vw;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: var(--transition);
            padding: 0;
        }

        /* Notification-specific dropdown styles */
        .notifications-dropdown {
            min-width: 350px;
            max-height: 500px;
            overflow-y: auto;
        }

        .notification-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h3 {
            margin: 0;
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .mark-all-read {
            color: var(--primary-color);
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            transition: var(--transition), transform 0.2s ease;
        }

        .mark-all-read:hover {
            color: var(--primary-dark);
            transform: scale(1.1);
        }

        .notification-item {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            transition: var(--transition);
            display: flex;
            align-items: flex-start;
        }

        .notification-item.unread {
            background-color: rgba(43, 102, 37, 0.05);
        }

        .notification-item:hover {
            background-color: #f9f9f9;
        }

        .notification-icon {
            margin-right: 15px;
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--primary-color);
        }

        .notification-message {
            color: var(--primary-color);
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .notification-time {
            color: #999;
            font-size: 0.8rem;
            margin-top: 5px;
        }

        .notification-footer {
            padding: 10px 20px;
            text-align: center;
            border-top: 1px solid #eee;
        }

        .view-all {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: var(--transition);
            display: inline-block;
            padding: 5px 0;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        .dropdown-menu.center {
            left: 50%;
            transform: translateX(-50%) translateY(10px);
        }

        .dropdown:hover .dropdown-menu,
        .dropdown-menu:hover {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-menu.center:hover,
        .dropdown:hover .dropdown-menu.center {
            transform: translateX(-50%) translateY(0);
        }

        .dropdown-menu:before {
            content: '';
            position: absolute;
            bottom: 100%;
            right: 20px;
            border-width: 10px;
            border-style: solid;
            border-color: transparent transparent var(--white) transparent;
        }

        .dropdown-menu.center:before {
            left: 50%;
            right: auto;
            transform: translateX(-50%);
        }

        /* Larger Dropdown Items */
        .dropdown-item {
            padding: 15px 25px;
            display: flex;
            align-items: center;
            color: black;
            text-decoration: none;
            transition: var(--transition);
            font-size: 1.1rem;
        }

        .dropdown-item i {
            width: 30px;
            font-size: 1.5rem;
            color: var(--primary-color) !important;
            margin-right: 15px;
        }

        .dropdown-item:hover {
            background: var(--light-gray);
            padding-left: 30px;
        }

        /* Notification Badge - Larger */
        .badge {
            position: absolute;
            top: 2px;
            right: 8px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 14px;
            height: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: bold;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }

            100% {
                transform: scale(1);
            }
        }

        /* Mobile Menu Toggle - Larger */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            padding: 15px;
        }

        .notification-link {
            display: flex;
            align-items: flex-start;
            text-decoration: none;
            color: inherit;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            transition: var(--transition);
        }

        .notification-link:hover {
            background-color: #f9f9f9;
        }

        /* Notifications Container */
        .notifications-container {
            width: 100%;
            max-width: 1000px;
            margin: 20px auto;
            border: 2px solid var(--primary-color);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }

        /* Notifications Header */
        .notifications-header {
            background-color: white;
            color: black;
            padding: 20px;
            text-align: center;
            font-size: 28px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            border-bottom: 1px solid #000;
        }

        /* Notification Tabs - Modified to stay side by side */
        .notification-tabs {
            display: flex;
            background-color: #f5f5f5;
            border-bottom: 1px solid #ddd;
            flex-wrap: nowrap;
            /* Prevent wrapping */
        }

        .tab {
            flex: 1;
            /* Equal width */
            min-width: 0;
            /* Allow flex items to shrink */
            padding: 15px 0;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            color: #333;
            transition: all 0.3s ease;
            position: relative;
            white-space: nowrap;
            /* Prevent text wrapping */
            overflow: hidden;
            /* Hide overflow */
            text-overflow: ellipsis;
            /* Add ellipsis if text is too long */
        }

        .tab:hover {
            background-color: #e9e9e9;
        }

        .tab.active {
            color: var(--primary-color);
            background-color: white;
            border-bottom: 3px solid var(--primary-color);
        }

        /* Notification count as icon */
        .tab-badge {
            margin-top: -5%;
            display: inline-block;
            background-color: #ff6b6b;
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            line-height: 22px;
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            margin-left: 1px;
            vertical-align: middle;
        }

        /* Notification List */
        .notification-list {
            background-color: white;
        }

        .notification-item {
            padding: 20px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.3s ease;
        }

        .notification-item:hover {
            background-color: #f9f9f9;
        }

        .notification-item.unread {
            background-color: #f0fff0;
            border-left: 4px solid var(--primary-color);
        }

        .notification-title {
            font-size: 17px;
            font-weight: bold;
            margin-bottom: 8px;
            color: var(--primary-color);
            display: flex;
            align-items: center;
        }

        .notification-icon {
            margin-right: 10px;
            color: var(--primary-color);
            font-size: 20px;
        }

        .notification-icon.alert {
            color: #e74c3c;
        }

        .notification-icon.approve {
            color: var(--primary-color);
        }

        .notification-content {
            color: #555;
            margin-bottom: 10px;
            font-size: 16px;
            line-height: 1.5;
            padding-left: 30px;
        }

        .notification-time {
            color: #888;
            font-size: 14px;
            margin-bottom: 15px;
            padding-left: 30px;
        }

        .notification-actions {
            display: flex;
            justify-content: end;
            gap: 15px;
            width: 27%;
        }

        .action-button {
            padding: 8px 15px;
            border: 1px solid var(--primary-color);
            background-color: white;
            color: var(--primary-color);
            cursor: pointer;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            width: 100%;
        }

        .action-button--half {
            width: 50%;
        }

        .action-button:hover {
            background-color: var(--primary-color);
            color: white;
        }

        /* Mark all as read button */
        .mark-all-button {
            text-align: right;
            padding: 15px 20px;
            background-color: #f5f5f5;
            border-top: 1px solid #ddd;
        }

        .mark-all-button button {
            padding: 8px 15px;
            background-color: white;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            cursor: pointer;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .mark-all-button button:hover {
            background-color: var(--primary-color);
            color: white;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 10px;
            box-sizing: border-box;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            color: var(--primary-color);
            margin: 0;
            font-size: 24px;
        }

        .modal-body {
            margin-bottom: 20px;
        }

        .modal-body p {
            margin: 10px 0;
            line-height: 1.6;
        }

        .modal-body strong {
            color: var(--primary-color);
        }

        .modal-footer {
            text-align: right;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #888;
        }

        .close-modal:hover {
            color: #333;
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .notifications-container {
                width: 95%;
                margin: 20px auto;
            }

            .notifications-header {
                font-size: 20px;
                padding: 10px;
            }

            .notification-tabs {
                flex-direction: row;
                /* Keep tabs in a row */
                overflow-x: auto;
                /* Add horizontal scrolling if needed */
                -webkit-overflow-scrolling: touch;
                /* Smooth scrolling on iOS */
            }

            .tab {
                flex: none;
                /* Don't grow or shrink */
                width: 50%;
                /* Each tab takes half width */
                font-size: 16px;
                padding: 10px;
                white-space: nowrap;
            }

            .tab-badge {
                width: 20px;
                height: 20px;
                line-height: 20px;
                font-size: 11px;
            }

            .notification-item {
                padding: 15px;
            }

            .notification-title {
                font-size: 16px;
            }

            .notification-content {
                font-size: 14px;
                padding-left: 20px;
            }

            .notification-time {
                font-size: 12px;
                padding-left: 20px;
            }

            .notification-actions {
                flex-direction: row;
                gap: 10px;
                padding-left: 20px;
            }

            .action-button {
                width: auto;
            }

            .action-button--half {
                width: auto;
            }

            .mark-all-button {
                text-align: right;
            }

            .mark-all-button button {
                width: auto;
            }

            /* Modal */
            .modal-content {
                width: 90%;
                padding: 20px;
            }

            .modal-header h2 {
                font-size: 20px;
            }

            .modal-body p {
                font-size: 14px;
            }

            .close-modal {
                font-size: 20px;
            }
        }

        @media (max-width: 480px) {
            .tab {
                font-size: 14px;
                padding: 8px 0;
            }

            .notification-title {
                font-size: 14px;
            }

            .notification-content {
                font-size: 12px;
                padding-left: 15px;
            }

            .notification-time {
                font-size: 10px;
                padding-left: 15px;
            }

            .action-button {
                font-size: 12px;
                padding: 6px 10px;
            }

            .action-button--half {
                width: auto;
            }

            .notification-actions {
                gap: 8px;
                padding-left: 15px;
            }
        }

        /* For very small screens where tabs might still not fit */
        @media (max-width: 360px) {
            .tab {
                font-size: 12px;
                padding: 8px 0;
            }

            .tab-badge {
                width: 18px;
                height: 18px;
                line-height: 18px;
                font-size: 10px;
            }
        }
    </style>
</head>

<body>

    <header>
        <div class="logo">
            <a href="user_home.php">
                <img src="seal.png" alt="Site Logo">
            </a>
        </div>

        <!-- Mobile menu toggle -->
        <button class="mobile-toggle">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Navigation on the right -->
        <div class="nav-container">
            <!-- Dashboard Dropdown -->
            <div class="nav-item dropdown">
                <div class="nav-icon">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="dropdown-menu center">

                    <a href="user_reportaccident.php" class="dropdown-item">
                        <i class="fas fa-file-invoice"></i>
                        <span>Report Incident</span>
                    </a>


                    <a href="useraddseed.php" class="dropdown-item">
                        <i class="fas fa-seedling"></i>
                        <span>Request Seedlings</span>
                    </a>
                    <a href="useraddwild.php" class="dropdown-item">
                        <i class="fas fa-paw"></i>
                        <span>Wildlife Permit</span>
                    </a>
                    <a href="useraddtreecut.php" class="dropdown-item">
                        <i class="fas fa-tree"></i>
                        <span>Tree Cutting Permit</span>
                    </a>
                    <a href="useraddlumber.php" class="dropdown-item">
                        <i class="fas fa-boxes"></i>
                        <span>Lumber Dealers Permit</span>
                    </a>
                    <a href="useraddwood.php" class="dropdown-item">
                        <i class="fas fa-industry"></i>
                        <span>Wood Processing Permit</span>
                    </a>
                    <a href="useraddchainsaw.php" class="dropdown-item">
                        <i class="fas fa-tools"></i>
                        <span>Chainsaw Permit</span>
                    </a>
                    <a href="applicationstatus.php" class="dropdown-item"><i class="fas fa-clipboard-check"></i><span>Application Status</span></a>

                </div>
            </div>

            <!-- Notifications -->
            <div class="nav-item dropdown as-item">
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
                        <?php else: ?>
                            <?php foreach ($notifs as $n): ?>
                                <?php
                                $unread = empty($n['is_read']);
                                $ts = $n['created_at'] ? (new DateTime((string)$n['created_at']))->getTimestamp() : time();
                                $title = $n['approval_id'] ? 'Permit Update' : ($n['incident_id'] ? 'Incident Update' : 'Notification');
                                $cleanMsg = (function ($m) {
                                    $t = trim((string)$m);
                                    $t = preg_replace('/\s*\(?\b(rejection\s*reason|reason)\b\s*[:\--]\s*.*$/i', '', $t);
                                    $t = preg_replace('/\s*\b(because|due\s+to)\b\s*.*/i', '', $t);
                                    return trim(preg_replace('/\s{2,}/', ' ', $t)) ?: 'There\'s an update.';
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

            <!-- Profile Dropdown -->
            <div class="nav-item dropdown">
                <div class="nav-icon">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="user_profile.php" class="dropdown-item">
                        <i class="fas fa-user-edit"></i>
                        <span>Edit Profile</span>
                    </a>
                    <a href="user_login.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Notifications Content -->
    <div class="notifications-container">
        <div class="notifications-header">NOTIFICATIONS</div>

        <div class="notification-tabs">
            <div id="all-tab" class="tab active">All Notifications</div>
            <div id="unread-tab" class="tab">Unread <span class="tab-badge" style="display:none;"></span></div>
        </div>

        <div id="all-notifications" class="notification-list">
            <?php if (!$notifs): ?>
                <div class="notification-item empty">
                    <div class="notification-content">
                        <div class="notification-title">No notifications yet</div>
                        <div class="notification-message">We'll let you know once there's an update.</div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($notifs as $notif): ?>
                    <?= $renderNotificationItem($notif) ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div id="unread-notifications" class="notification-list" style="display: none;">
            <?php $hasUnread = false; ?>
            <?php if ($notifs): ?>
                <?php foreach ($notifs as $notif): ?>
                    <?php if (!empty($notif['is_read'])) continue; ?>
                    <?php $hasUnread = true; ?>
                    <?= $renderNotificationItem($notif) ?>
                <?php endforeach; ?>
            <?php endif; ?>
            <div class="notification-item empty" id="unread-empty" <?= $hasUnread ? 'style="display:none;"' : '' ?>>
                <div class="notification-content">
                    <div class="notification-title">No unread notifications</div>
                    <div class="notification-message">You're all caught up.</div>
                </div>
            </div>
        </div>

        <div class="mark-all-button">
            <button type="button" id="mark-all-read">Mark all as read</button>
        </div>
    </div>

    <!-- Modal for Notification Details -->
    <div class="modal" id="notification-modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <div class="modal-header">
                <h2 id="modalTitle">Notification Details</h2>
            </div>
            <div class="modal-body">
                <p><strong>Category:</strong> <span id="modalCategory">N/A</span></p>
                <p><strong>Received:</strong> <span id="modalReceived">N/A</span></p>
                <p id="modalMessage" style="margin-top: 15px;">No additional details.</p>
            </div>
            <div class="modal-footer" id="modalFooter" style="display:none;">
                <a href="#" id="modalActionBtn" class="action-button">Open Details</a>
            </div>
        </div>
    </div>

    <!-- Notifications dropdown behavior -->
    <script>
        (function() {
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
                    if (next <= 0) {
                        badge.style.display = 'none';
                    } else {
                        badge.textContent = String(next);
                    }
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');

            if (mobileToggle && navContainer) {
                mobileToggle.addEventListener('click', () => {
                    const isActive = navContainer.classList.toggle('active');
                    document.body.style.overflow = isActive ? 'hidden' : '';
                });

                document.addEventListener('click', (e) => {
                    if (!e.target.closest('.nav-container') && !e.target.closest('.mobile-toggle')) {
                        navContainer.classList.remove('active');
                        document.body.style.overflow = '';
                    }
                });
            }

            const allTab = document.getElementById('all-tab');
            const unreadTab = document.getElementById('unread-tab');
            const allContent = document.getElementById('all-notifications');
            const unreadContent = document.getElementById('unread-notifications');

            allTab?.addEventListener('click', function() {
                allTab.classList.add('active');
                unreadTab?.classList.remove('active');
                if (allContent) allContent.style.display = 'block';
                if (unreadContent) unreadContent.style.display = 'none';
            });

            unreadTab?.addEventListener('click', function() {
                unreadTab.classList.add('active');
                allTab?.classList.remove('active');
                if (unreadContent) unreadContent.style.display = 'block';
                if (allContent) allContent.style.display = 'none';
            });

            const modal = document.getElementById('notification-modal');
            const closeModalBtn = document.querySelector('.close-modal');
            const modalTitle = document.getElementById('modalTitle');
            const modalCategory = document.getElementById('modalCategory');
            const modalReceived = document.getElementById('modalReceived');
            const modalMessage = document.getElementById('modalMessage');
            const modalFooter = document.getElementById('modalFooter');
            const modalActionBtn = document.getElementById('modalActionBtn');

            const timeAgo = (seconds) => {
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
            };

            function updateRelativeTimes() {
                document.querySelectorAll('.notification-time[data-ts]').forEach(el => {
                    const tsMs = Number(el.dataset.ts || 0) * 1000;
                    if (!tsMs) return;
                    const diffSec = Math.floor((Date.now() - tsMs) / 1000);
                    el.textContent = timeAgo(diffSec);
                    el.title = new Date(tsMs).toLocaleString();
                });
            }

            const tabBadge = document.querySelector('.tab-badge');
            const headerBadge = document.getElementById('asNotifBadge');

            function updateCounters() {
                const unreadItems = allContent ? allContent.querySelectorAll('.notification-item.unread') : [];
                const unreadCount = unreadItems ? unreadItems.length : 0;

                if (tabBadge) {
                    if (unreadCount > 0) {
                        tabBadge.style.display = 'inline-block';
                        tabBadge.textContent = unreadCount;
                    } else {
                        tabBadge.style.display = 'none';
                        tabBadge.textContent = '';
                    }
                }

                if (headerBadge) {
                    if (unreadCount > 0) {
                        headerBadge.style.display = 'flex';
                        headerBadge.textContent = unreadCount;
                    } else {
                        headerBadge.style.display = 'none';
                    }
                }
            }

            function refreshUnreadList() {
                if (!unreadContent) return;
                const emptyState = document.getElementById('unread-empty');
                let anyVisible = false;
                unreadContent.querySelectorAll('.notification-item').forEach(item => {
                    if (item.id === 'unread-empty') return;
                    const isUnread = item.dataset.isRead !== '1' && item.classList.contains('unread');
                    if (isUnread) {
                        item.style.display = '';
                        anyVisible = true;
                    } else {
                        item.style.display = 'none';
                    }
                });
                if (emptyState) emptyState.style.display = anyVisible ? 'none' : '';
            }

            function applyReadState(notifId) {
                if (!notifId) return;
                document.querySelectorAll(`.notification-item[data-notif-id="${notifId}"]`).forEach(item => {
                    item.classList.remove('unread');
                    item.dataset.isRead = '1';
                    const btn = item.querySelector('.mark-read-btn');
                    if (btn) {
                        btn.disabled = true;
                        btn.textContent = 'Read';
                    }
                });

                document.querySelectorAll(`.as-notif-item.unread a[data-notif-id="${notifId}"]`).forEach(link => {
                    const row = link.closest('.as-notif-item');
                    row?.classList.remove('unread');
                });

                refreshUnreadList();
                updateCounters();
            }

            const pendingMarks = new Set();

            async function markNotificationRead(notifId) {
                if (!notifId || pendingMarks.has(notifId)) return;
                pendingMarks.add(notifId);
                try {
                    await fetch(location.pathname + `?ajax=mark_read&notif_id=${encodeURIComponent(notifId)}`, {
                        method: 'POST',
                        credentials: 'same-origin'
                    });
                } catch (err) {
                    console.error('Mark notification read failed', err);
                } finally {
                    pendingMarks.delete(notifId);
                    applyReadState(notifId);
                }
            }

            function openModalFor(item) {
                if (!modal) return;
                modalTitle.textContent = item.dataset.title || 'Notification Details';
                modalCategory.textContent = item.dataset.title || 'Notification';

                const ts = Number(item.dataset.ts || 0) * 1000;
                modalReceived.textContent = ts ? new Date(ts).toLocaleString() : 'N/A';

                const fullMessage = item.dataset.fullMessage || item.dataset.summary || "There's an update.";
                modalMessage.textContent = fullMessage;

                const actionUrl = item.dataset.actionUrl || '';
                const actionLabel = item.dataset.actionLabel || '';
                if (actionUrl && actionLabel) {
                    modalFooter.style.display = '';
                    modalActionBtn.href = actionUrl;
                    modalActionBtn.textContent = actionLabel;
                } else {
                    modalFooter.style.display = 'none';
                    modalActionBtn.removeAttribute('href');
                }

                modal.style.display = 'flex';
            }

            const hideModal = () => {
                if (modal) modal.style.display = 'none';
            };

            closeModalBtn?.addEventListener('click', hideModal);
            window.addEventListener('click', (event) => {
                if (event.target === modal) hideModal();
            });

            document.querySelectorAll('.view-details-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const item = btn.closest('.notification-item');
                    if (!item) return;
                    openModalFor(item);
                    if (item.classList.contains('unread')) {
                        const notifId = item.dataset.notifId;
                        if (notifId) markNotificationRead(notifId);
                    }
                });
            });

            document.querySelectorAll('.mark-read-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const item = btn.closest('.notification-item');
                    if (!item) return;
                    const notifId = item.dataset.notifId;
                    if (notifId) markNotificationRead(notifId);
                });
            });

            const markAllButton = document.getElementById('mark-all-read');

            async function markAllNotificationsRead() {
                if (!markAllButton) return;
                pendingMarks.clear();
                markAllButton.disabled = true;
                try {
                    await fetch(location.pathname + '?ajax=mark_all_read', {
                        method: 'POST',
                        credentials: 'same-origin'
                    });
                } catch (err) {
                    console.error('Mark all notifications failed', err);
                } finally {
                    markAllButton.disabled = false;
                }

                document.querySelectorAll('.notification-item[data-notif-id]').forEach(item => {
                    item.classList.remove('unread');
                    item.dataset.isRead = '1';
                    const btn = item.querySelector('.mark-read-btn');
                    if (btn) {
                        btn.disabled = true;
                        btn.textContent = 'Read';
                    }
                });

                document.querySelectorAll('.as-notif-item.unread').forEach(row => row.classList.remove('unread'));

                if (headerBadge) headerBadge.style.display = 'none';

                refreshUnreadList();
                updateCounters();
            }

            markAllButton?.addEventListener('click', markAllNotificationsRead);

            updateRelativeTimes();
            refreshUnreadList();
            updateCounters();
        });
    </script>
</body>

</html>