<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'User') {
    header("Location: user_login.php");
    exit();
}
include_once __DIR__ . '/../backend/connection.php';

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
        error_log('[NOTIFS AJAX] ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

/* Load the latest notifications for the current user */
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

    foreach ($notifs as $n) {
        if (empty($n['is_read'])) $unreadCount++;
    }
} catch (Throwable $e) {
    error_log('[NOTIFS LOAD] ' . $e->getMessage());
    $notifs = [];
    $unreadCount = 0;
}


/* Prefetch released wildlife permits (WFP numbers) for renewal assist */
$clientRows = [];
try {
    $stmt = $pdo->prepare("
        SELECT client_id, user_id, first_name, middle_name, last_name,
               sitio_street, barangay, municipality, city
        FROM public.client
        ORDER BY (user_id = :uid) DESC, last_name ASC, first_name ASC
        LIMIT 500
    ");
    $stmt->execute([':uid' => $_SESSION['user_id']]);
    $clientRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[WILDLIFE-CLIENTS] ' . $e->getMessage());
    $clientRows = [];
}

$wfpRecords = [];
$wfpSelectOptions = [];
$wfpRecordsJson = '{}';
if (!empty($_SESSION['user_id'])) {
    try {
        $wfpStmt = $pdo->prepare("
            WITH latest_docs AS (
                SELECT DISTINCT ON (upper(trim(d.wfp_no)))
                    d.approved_id,
                    d.approval_id AS doc_approval_id,
                    trim(d.wfp_no) AS wfp_no,
                    d.date_issued,
                    d.expiry_date,
                    d.approved_document,
                    d.series,
                    d.meeting_date,
                    a.approval_id,
                    a.permit_type,
                    a.application_id,
                    a.requirement_id,
                    a.submitted_at,
                    af.additional_information,
                    af.present_address,
                    af.telephone_number,
                    c.first_name    AS client_first,
                    c.middle_name   AS client_middle,
                    c.last_name     AS client_last,
                    req.application_form,
                    req.wild_sec_cda_registration,
                    req.wild_scientific_expertise,
                    req.wild_financial_plan,
                    req.wild_facility_design,
                    req.wild_prior_clearance,
                    req.wild_vicinity_map,
                    req.wild_proof_of_purchase,
                    req.wild_deed_of_donation,
                    req.wild_inspection_report,
                    req.wild_previous_wfp_copy,
                    req.wild_breeding_report_quarterly,
                    req.wild_cites_import_permit,
                    req.wild_local_transport_permit,
                    req.wild_barangay_mayor_clearance
                FROM public.approved_docs d
                JOIN public.approval a ON a.approval_id = d.approval_id
                JOIN public.client   c ON c.client_id   = a.client_id
                LEFT JOIN public.application_form af ON af.application_id = a.application_id
                LEFT JOIN public.requirements     req ON req.requirement_id = a.requirement_id
                WHERE c.user_id = :uid
                  AND a.request_type ILIKE 'wildlife'
                  AND NULLIF(btrim(d.wfp_no), '') IS NOT NULL
                ORDER BY upper(trim(d.wfp_no)), COALESCE(d.date_issued, a.submitted_at) DESC, d.approved_id DESC
            )
            SELECT *
            FROM latest_docs
            ORDER BY COALESCE(date_issued, submitted_at) DESC NULLS LAST, approved_id DESC
            LIMIT 60
        ");
        $wfpStmt->execute([':uid' => $_SESSION['user_id']]);
        $rows = $wfpStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $fileMap = [
            'renewal-uploaded-files-2'  => ['wild_previous_wfp_copy', 'Copy of previous WFP'],
            'renewal-uploaded-files-3'  => ['wild_breeding_report_quarterly', 'Quarterly breeding report'],
            'renewal-uploaded-files-4a' => ['wild_cites_import_permit', 'CITES/Non-CITES import permit'],
            'renewal-uploaded-files-4b' => ['wild_proof_of_purchase', 'Proof of purchase'],
            'renewal-uploaded-files-4c' => ['wild_deed_of_donation', 'Deed of donation'],
            'renewal-uploaded-files-4d' => ['wild_local_transport_permit', 'Local transport permit'],
            'renewal-uploaded-files-5a' => ['wild_barangay_mayor_clearance', 'Barangay/Mayor clearance'],
            'renewal-uploaded-files-5b' => ['wild_facility_design', 'Facility design'],
            'renewal-uploaded-files-5c' => ['wild_vicinity_map', 'Vicinity map'],
            'renewal-uploaded-files-6'  => ['wild_inspection_report', 'Inspection report'],
        ];

        foreach ($rows as $row) {
            $key = $row['approved_id'] ?? $row['doc_approval_id'] ?? $row['approval_id'] ?? md5(($row['wfp_no'] ?? '') . ($row['date_issued'] ?? '') . uniqid('', true));
            $info = [];
            if (!empty($row['additional_information'])) {
                $decoded = json_decode($row['additional_information'], true);
                if (is_array($decoded)) {
                    $info = $decoded;
                }
            }

            $categories = [];
            if (!empty($info['categories']) && is_array($info['categories'])) {
                $categories = [
                    'zoo' => !empty($info['categories']['zoo']),
                    'botanical_garden' => !empty($info['categories']['botanical_garden']),
                    'private_collection' => !empty($info['categories']['private_collection']),
                ];
            }

            $animals = [];
            if (!empty($info['animals']) && is_array($info['animals'])) {
                $animals = $info['animals'];
            }

            $files = [];
            foreach ($fileMap as $containerId => [$column, $label]) {
                if (!empty($row[$column])) {
                    $entry = [
                        'label' => $label,
                        'url' => $row[$column],
                    ];
                    $files[$containerId][] = $entry;
                }
            }

            $issuedDisplay = '';
            if (!empty($row['date_issued'])) {
                try {
                    $issuedDisplay = (new DateTime($row['date_issued']))->format('M j, Y');
                } catch (Throwable $e) {
                    $issuedDisplay = $row['date_issued'];
                }
            }

            $optionPieces = [];
            $optionPieces[] = trim((string)$row['wfp_no']);
            if (!empty($row['permit_type'])) {
                $optionPieces[] = '(' . ucwords((string)$row['permit_type']) . ')';
            }
            if ($issuedDisplay) {
                $optionPieces[] = 'issued ' . $issuedDisplay;
            }
            $optionLabel = trim(implode(' ', array_filter($optionPieces)));

            $wfpSelectOptions[] = [
                'key' => $key,
                'label' => $optionLabel ?: ($row['wfp_no'] ?: 'Released WFP'),
            ];

            $wfpRecords[$key] = [
                'key' => $key,
                'label' => $optionLabel,
                'wfp_number' => $row['wfp_no'],
                'permit_type' => strtolower((string)($row['permit_type'] ?? '')),
                'date_issued' => $row['date_issued'],
                'expiry_date' => $row['expiry_date'],
                'issue_date' => $info['issue_date'] ?? $row['date_issued'] ?? '',
                'approved_document' => $row['approved_document'],
                'series' => $row['series'],
                'meeting_date' => $row['meeting_date'],
                'client' => [
                    'first_name' => $row['client_first'] ?? '',
                    'middle_name' => $row['client_middle'] ?? '',
                    'last_name' => $row['client_last'] ?? '',
                ],
                'info' => [
                    'residence_address' => $info['residence_address'] ?? $row['present_address'] ?? '',
                    'telephone_number' => $info['telephone_number'] ?? $row['telephone_number'] ?? '',
                    'establishment_name' => $info['establishment_name'] ?? '',
                    'establishment_address' => $info['establishment_address'] ?? '',
                    'establishment_telephone' => $info['establishment_telephone'] ?? '',
                    'postal_address' => $info['postal_address'] ?? '',
                    'wfp_number' => $info['wfp_number'] ?? $row['wfp_no'] ?? '',
                ],
                'categories' => $categories,
                'animals' => $animals,
                'files' => $files,
            ];
        }

        $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        $wfpRecordsJson = json_encode($wfpRecords, $jsonFlags) ?: '{}';
    } catch (Throwable $e) {
        error_log('[WILDLIFE WFP LIST] ' . $e->getMessage());
        $wfpRecords = [];
        $wfpSelectOptions = [];
        $wfpRecordsJson = '{}';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wildlife permit</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>

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
            --as-primary: #2b6625;
            --as-primary-dark: #1e4a1a;
            --as-white: #fff;
            --as-light-gray: #f5f5f5;
            --as-radius: 8px;
            --as-shadow: 0 4px 12px rgba(0, 0, 0, .1);
            --as-trans: all .2s ease;
        }

        /* bell icon container */
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

        /* dropdown shell */
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

        /* notifications panel */
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

        /* 🔽 the scrolling list wrapper (this holds the records) */
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

        /* Navigation Items */
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
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: var(--transition);
            padding: 0;
        }

        .dropdown-item.active-page {
            background-color: rgb(225, 255, 220);
            color: var(--primary-dark);
            font-weight: bold;
            border-left: 4px solid var(--primary-color);
        }


        .dropdown-item:hover {
            background: var(--light-gray);
            padding-left: 30px;
        }

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

        /* Dropdown Items */
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

        /* Notification Badge */
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

        /* Mobile Menu Toggle */
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

        /* Content Styles */
        .content {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: -1%;
            padding: 0 20px;
            margin-bottom: 2%;
        }

        .page-title {
            color: #005117;
            font-size: 28px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 3px solid #005117;
            padding-bottom: 10px;
            width: 80%;
            max-width: 800px;
        }

        .profile-form {
            background-color: #fff;
            padding: 30px;
            border: 2px solid #005117;
            max-width: 800px;
            width: 90%;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0, 81, 23, 0.1);
        }

        .form-group input,
        .form-group textarea,
        .form-group input[type="file"],
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #005117;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .readonly-input {
            background-color: #f4f4f4 !important;
            cursor: not-allowed;
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
            margin-bottom: 15px;
        }

        .form-group {
            flex: 1 0 200px;
            padding: 0 10px;
            margin-bottom: 25px;
        }

        .form-group.full-width {
            flex: 1 0 100%;
        }

        .form-group.two-thirds {
            flex: 2 0 400px;
        }

        .form-group.one-third {
            flex: 1 0 200px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #000;
            font-size: 14px;
            font-weight: bold;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #153415;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2b6625;
            box-shadow: 0 0 0 2px rgba(43, 102, 37, 0.2);
        }

        .form-group textarea {
            height: 180px;
            resize: vertical;
        }

        .form-group input[type="file"] {
            width: 100%;
            height: 40px;
            padding: 5px;
            border: 1px solid #153415;
            border-radius: 4px;
            font-size: 14px;
            background-color: #ffffff;
            box-sizing: border-box;
        }

        /* Make input[type="date"] same height as file input */
        .form-group input[type="date"] {
            height: 40px;
            box-sizing: border-box;
        }

        /* Make all inputs, textarea, select height 40px to match user_requestseedlings.php */
        .form-group input,
        .form-group textarea,
        .form-group select {
            height: 40px;
        }

        .button-group {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .save-btn,
        .view-records-btn {
            background-color: #005117;
            color: #fff;
            border: none;
            padding: 12px 40px;
            cursor: pointer;
            border-radius: 5px;
            font-weight: bold;
            font-size: 16px;
            transition: all 0.3s;
            text-align: center;
        }

        .view-records-btn {
            background-color: #005117;
        }

        .save-btn:hover {
            background-color: #006622;
            transform: translateY(-2px);
        }

        .view-records-btn:hover {
            background-color: #006622;
            transform: translateY(-2px);
        }

        /* Records Table Styles */
        .records-container {
            background-color: #fff;
            border: 2px solid #005117;
            border-radius: 12px;
            padding: 30px;
            margin-top: 30px;
            width: 90%;
            max-width: 800px;
            box-shadow: 0 6px 20px rgba(0, 81, 23, 0.1);
            display: none;
        }

        .records-title {
            color: #005117;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
            border-bottom: 2px solid #005117;
            padding-bottom: 10px;
        }

        .records-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .records-table th,
        .records-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        /* Center first column header and data cells text to match user_requestseedlings.php */
        .records-table th:first-child,
        .records-table td:first-child {
            text-align: center;
        }

        .records-table th {
            background-color: #005117;
            color: white;
            font-weight: 600;
        }

        .records-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .records-table tr:hover {
            background-color: #f1f1f1;
        }

        .status-pending {
            color: #4caf50;
            font-weight: 600;
        }

        .status-approved {
            color: #4caf50;
            font-weight: 600;
        }

        .status-rejected {
            color: #4caf50;
            font-weight: 600;
        }

        .no-records {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .mobile-toggle {
                display: block;
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
            /* smaller width */
            height: 40px;
            /* smaller height */
            background: rgb(233, 255, 242);
            /* slightly brighter background */
            border-radius: 12px;
            /* softer corners */
            cursor: pointer;
            transition: var(--transition);
            color: black;
            /* changed icon color to black */
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
            /* subtle shadow for depth */
        }

        .nav-icon:hover {
            background: rgba(224, 204, 204, 0.3);
            transform: scale(1.15);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }

        .nav-icon i {
            font-size: 1.3rem;
            /* smaller icon size */
            color: inherit;
            transition: color 0.3s ease;
        }

        /* Dropdown Menu */
        .dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: var(--white);
            min-width: 300px;
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
            /* Slightly darker color on hover */
            transform: scale(1.1);
            /* Slightly bigger on hover */
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


        /* Main Content */
        .main-container {
            margin-top: -0.5%;
            padding: 30px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            margin-top: -3%;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: nowrap;
            justify-content: center;
            overflow-x: auto;
            padding-bottom: 10px;
            -webkit-overflow-scrolling: touch;
        }

        .btn {
            padding: 10px 15px;
            border-radius: var(--border-radius);
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1rem;
            white-space: nowrap;
            min-width: 120px;
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-outline {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
        }

        .btn-outline:hover {
            background: var(--light-gray);
        }

        .btn-primary {
            background: var(--primary-color);
            color: var(--white);
            border: 2px solid var(--primary-color);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        /* Requirements Form */
        .requirements-form {
            margin-top: -1%;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            border: 1px solid var(--medium-gray);
        }

        .form-header {
            background-color: var(--primary-color);
            color: var(--white);
            padding: 20px 30px;
            border-bottom: 1px solid var(--primary-dark);
        }

        .form-header h2 {
            text-align: center;
            font-size: 1.5rem;
            margin: 0;
        }

        .form-body {
            padding: 30px;
        }

        .requirements-list {
            display: grid;
            gap: 20px;
        }

        .requirement-item {
            display: flex;
            flex-direction: column;
            gap: 15px;
            padding: 20px;
            background: var(--light-gray);
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary-color);
        }

        .requirement-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .requirement-title {
            font-weight: 600;
            color: var(--primary-dark);
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .requirement-number {
            background: var(--primary-color);
            color: white;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }

        .file-upload {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .file-input-container {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .file-input-label {
            padding: 8px 15px;
            background: var(--primary-color);
            color: white;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }

        .file-input-label:hover {
            background: var(--primary-dark);
        }

        .file-input {
            display: none;
        }

        .file-name {
            font-size: 0.9rem;
            color: var(--dark-gray);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }

        .uploaded-files {
            margin-top: 10px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
            padding: 8px 12px;
            border-radius: var(--border-radius);
            border: 1px solid var(--medium-gray);
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 8px;
            overflow: hidden;
        }

        .file-icon {
            color: var(--primary-color);
            flex-shrink: 0;
        }

        .file-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        .file-action-btn {
            background: none;
            border: none;
            color: var(--dark-gray);
            cursor: pointer;
            transition: var(--transition);
            padding: 5px;
        }

        .file-action-btn:hover {
            color: var(--primary-color);
        }

        .form-footer {
            padding: 20px 30px;
            background: var(--light-gray);
            border-top: 1px solid var(--medium-gray);
            display: flex;
            justify-content: flex-end;
        }

        /* Fee Information */
        .fee-info {
            margin-top: 20px;
            padding: 15px;
            background: rgba(43, 102, 37, 0.1);
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary-color);
        }

        .fee-info p {
            margin: 5px 0;
            color: var(--primary-dark);
            font-weight: 500;
        }

        /* File Preview Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            overflow: auto;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 800px;
            border-radius: var(--border-radius);
            position: relative;
        }

        .close-modal {
            color: #aaa;
            position: absolute;
            top: 10px;
            right: 20px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close-modal:hover {
            color: black;
        }

        .file-preview {
            width: 100%;
            height: 70vh;
            border: none;
            margin-top: 20px;
        }

        /* Sample Letter Button */
        .sample-letter-btn {
            display: flex;
            justify-content: flex-start;
            margin-bottom: 20px;
        }

        .download-sample {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .download-sample:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }


        .name-fields {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            MARGIN-TOP: -1%;
            margin-bottom: 10px;
            padding: 15px;
        }

        .name-field {
            flex: 1;
            min-width: 200px;
        }

        .name-field input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #153415;
            border-radius: 4px;
            font-size: 14px;
            transition: all 0.3s ease;
            height: 40px;
            box-sizing: border-box;
        }

        .name-field input:focus {
            outline: none;
            border-color: #2b6625;
            box-shadow: 0 0 0 2px rgba(43, 102, 37, 0.2);
        }

        .name-field input::placeholder {
            color: #999;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .notifications-dropdown {
                width: 320px;
            }

            .main-container {
                padding: 20px;
            }

            .form-body {
                padding: 20px;
            }

            .requirement-item {
                padding: 15px;
            }

            .requirement-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .file-input-container {
                flex-direction: column;
                align-items: flex-start;
            }

            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
        }

        @media (max-width: 576px) {
            header {
                padding: 0 15px;
            }

            .nav-container {
                gap: 15px;
            }

            .notifications-dropdown {
                width: 280px;
                right: -50px;
            }

            .notifications-dropdown:before {
                right: 65px;
            }

            .action-buttons {
                margin-top: -6%;
                gap: 8px;
                padding-bottom: 5px;
            }

            .btn {
                padding: 10px 10px;
                font-size: 0.85rem;
                min-width: 80px;
            }

            .btn i {
                font-size: 0.85rem;
                margin-right: 5px;
            }

            .form-header {
                padding: 15px 20px;
            }

            .form-header h2 {
                font-size: 1.3rem;
            }
        }

        .form-section {
            margin-bottom: 25px;
        }

        .form-section h2 {
            background-color: #2b6625;
            color: white;
            padding: 10px 15px;
            margin-bottom: 15px;
            border-radius: 4px;
            font-size: 18px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2b6625;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 15px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: #2b6625;
            outline: none;
            box-shadow: 0 0 0 2px rgba(43, 102, 37, 0.2);
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .checkbox-group {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-item input {
            width: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        table th,
        table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        table th {
            background-color: #f2f2f2;
            font-weight: 600;
        }

        /* Wider table columns */
        table th:nth-child(1),
        table td:nth-child(1) {
            width: 35%;
        }

        table th:nth-child(2),
        table td:nth-child(2) {
            width: 35%;
        }

        table th:nth-child(3),
        table td:nth-child(3) {
            width: 20%;
        }

        table th:nth-child(4),
        table td:nth-child(4) {
            width: 10%;
        }

        .table-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .add-row-btn {
            background-color: #2b6625;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .remove-row-btn {
            background-color: #ff4757;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }

        .declaration {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 4px;
            border-left: 4px solid #2b6625;
            margin-bottom: 25px;
        }

        .declaration p {
            margin-bottom: 15px;
            line-height: 1.6;
        }

        .signature-date {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .signature-box {
            width: 100%;
            margin-top: 20px;
        }

        .signature-pad-container {
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
            background: white;
        }

        /* Ensure any signature canvas inside the container fills available width
           and has an appropriate CSS height so the resize logic uses correct
           clientWidth/clientHeight. This targets the default id and any other
           canvases placed inside `.signature-pad-container` (eg. renewal). */
        .signature-pad-container canvas,
        #signature-pad {
            width: 100%;
            height: 150px;
            cursor: crosshair;
            display: block;
        }

        .signature-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .signature-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .clear-signature {
            background-color: #ff4757;
            color: white;
        }

        .save-signature {
            background-color: #2b6625;
            color: white;
        }

        .signature-preview {
            margin-top: 15px;
            text-align: center;
        }

        #signature-image {
            max-width: 300px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .download-btn {
            background-color: #2b6625;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 30px auto 0;
            transition: background-color 0.3s;
        }

        .download-btn:hover {
            background-color: #1e4a1a;
        }

        .hidden {
            display: none;
        }

        .declaration-input {
            border: none;
            border-bottom: 1px solid #999;
            border-radius: 0;
            padding: 0 5px;
            width: 300px;
            display: inline-block;
            background: transparent;
        }

        .declaration-input:focus {
            border-bottom: 2px solid #2b6625;
            outline: none;
            box-shadow: none;
        }

        .required::after {
            content: " *";
            color: #ff4757;
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .checkbox-group {
                flex-direction: column;
                gap: 10px;
            }

            .signature-date {
                flex-direction: column;
                gap: 20px;
            }

            .declaration-input {
                width: 100%;
                margin: 5px 0;
            }

            /* Adjust table for mobile */
            table {
                display: block;
                overflow-x: auto;
            }

            table th:nth-child(1),
            table td:nth-child(1),
            table th:nth-child(2),
            table td:nth-child(2),
            table th:nth-child(3),
            table td:nth-child(3),
            table th:nth-child(4),
            table td:nth-child(4) {
                width: auto;
                min-width: 120px;
            }
        }

        /* Loading indicator */
        .loading {
            display: none;
            text-align: center;
            margin-top: 20px;
            color: #2b6625;
        }

        .loading i {
            margin-right: 10px;
        }

        /* Print-specific styles */
        @media print {

            .download-btn,
            .add-row-btn,
            .remove-row-btn,
            .signature-actions,
            .signature-pad-container {
                display: none !important;
            }

            body {
                background-color: white;
                padding: 0;
            }

            .container {
                box-shadow: none;
                border: none;
                padding: 15px;
            }
        }

        .permit-type-selector {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .permit-type-selector small {
            flex-basis: 100%;
            color: #4f5d48;
        }

        .permit-type-buttons {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .renewal-wfp-picker {
            display: none;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            background: #f8fff6;
            border: 1px solid #c9e6c0;
            border-radius: 6px;
        }

        .renewal-wfp-picker label {
            font-weight: 600;
            font-size: .9rem;
            color: #2b6625;
        }

        .renewal-wfp-picker select {
            min-width: 220px;
            padding: 6px 10px;
            border: 1px solid #2b6625;
            border-radius: 4px;
            font-size: .95rem;
        }


        .permit-type-btn {
            padding: 12px 25px;
            margin: 0 10px 0 0;
            border: 2px solid #2b6625;
            background-color: white;
            color: #2b6625;
            font-weight: bold;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .permit-type-btn.active {
            background-color: #2b6625;
            color: white;
        }

        .permit-type-btn:hover {
            background-color: #2b6625;
            color: white;
        }

        .field-error {
            color: #d22;
            font-size: 12px;
            margin-top: 4px;
            display: none
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .uploaded-files a {
            display: none !important;
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
                <div class="nav-icon active">
                    <i class="fas fa-bars"></i>
                </div>

                <div class="dropdown-menu center">
                    <a href="user_reportaccident.php" class="dropdown-item">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Report Incident</span>
                    </a>
                    <a href="useraddseed.php" class="dropdown-item ">
                        <i class="fas fa-seedling"></i>
                        <span>Request Seedlings</span>
                    </a>
                    <a href="useraddwild.php" class="dropdown-item active-page">
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
            <div class="as-item">
                <div class="as-icon">
                    <i class="fas fa-bell"></i>
                    <?php if (!empty($unreadCount)) : ?>
                        <span class="as-badge" id="asNotifBadge">
                            <?= htmlspecialchars((string)$unreadCount, ENT_QUOTES) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="as-dropdown-menu as-notifications">
                    <!-- sticky header -->
                    <div class="as-notif-header">
                        <h3>Notifications</h3>
                        <a href="#" class="as-mark-all" id="asMarkAllRead">Mark all as read</a>
                    </div>

                    <!-- scrollable body -->
                    <div class="notifcontainer"><!-- this holds the records -->
                        <?php if (!$notifs): ?>
                            <div class="as-notif-item">
                                <div class="as-notif-content">
                                    <div class="as-notif-title">No record found</div>
                                    <div class="as-notif-message">There are no notifications.</div>
                                </div>
                            </div>
                            <?php else: foreach ($notifs as $n):
                                $unread = empty($n['is_read']);
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
                                        $stApprovalType = $pdo->prepare("SELECT seedl_req_id FROM public.approval WHERE approval_id = :aid LIMIT 1");
                                        $stApprovalType->execute([':aid' => $n['approval_id']]);
                                        $aprRow = $stApprovalType->fetch(PDO::FETCH_ASSOC);
                                        if (!empty($aprRow) && !empty($aprRow['seedl_req_id'])) {
                                            $title = 'Seedlings Request Update';
                                        } else {
                                            $title = 'Permit Update';
                                        }
                                    } catch (Throwable $e) {
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
                                            <script>
                                                // Manila time + relative label for notifications
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
                                                })();
                                            </script>
                                        </div>
                                    </a>
                                </div>
                        <?php endforeach;
                        endif; ?>
                    </div>

                    <!-- sticky footer -->
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

    <div class="main-container">
        <div class="requirements-form">
            <div class="form-header">
                <h2>Wildlife Registration Permit - Requirements</h2>
            </div>

            <div class="form-body">

                <!-- Permit Type Selector -->
                <div class="permit-type-selector">

                    <div class="permit-type-buttons">
                        <button type="button" class="permit-type-btn active" data-type="new">New permit</button>
                        <button type="button" class="permit-type-btn" data-type="renewal">Renewal permit</button>
                        <div class="client-mode-toggle" id="clientModeToggle" style=" display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
                            <button type="button" id="btnExisting" class="btn btn-outline">
                                <i class="fas fa-user-check"></i>&nbsp;Existing client
                            </button>
                            <button type="button" id="btnNew" class="btn btn-outline" style="display:none;">
                                <i class="fas fa-user-plus"></i>&nbsp;New client
                            </button>

                        </div>
                    </div>
                    <div class="renewal-wfp-picker" id="renewalWfpPicker" data-has-options="<?= $wfpSelectOptions ? '1' : '0' ?>">
                        <?php if ($wfpSelectOptions): ?>
                            <label for="renewalWfpSelect">WFP No.</label>
                            <select id="renewalWfpSelect">
                                <option value="">Select released WFP</option>
                                <?php foreach ($wfpSelectOptions as $opt): ?>
                                    <option value="<?= htmlspecialchars($opt['key'], ENT_QUOTES) ?>"><?= htmlspecialchars($opt['label'], ENT_QUOTES) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <span style="font-size:.85rem;color:#6c7a67;">No released WFP records yet.</span>
                        <?php endif; ?>
                    </div>
                    <small style="opacity:.8;">Choose <b>Renewal permit</b> if you already have a released wildlife permit.</small>
                    <small style="opacity:.8;">Choose <b>Existing client</b> if the client record already exists.</small>
                </div>

                <!-- ================= NEW: Upper sections ================= -->
                <div id="new-upper-block">
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="zoo">
                            <label for="zoo">Zoo</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="botanical-garden">
                            <label for="botanical-garden">Botanical Garden</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="private-collection">
                            <label for="private-collection">Private Collection</label>
                        </div>

                        <!-- group error (at least one must be checked) -->
                        <div id="new-category-error" class="field-error group" style="display:none"></div>
                    </div>

                    <div class="form-section">
                        <h2>APPLICANT INFORMATION</h2>



                        <input type="hidden" id="clientMode" value="new">

                        <div id="existingClientRow" class="form-group" style="display:none;">
                            <label for="clientPick" style="font-weight:600;margin-bottom:6px;display:block;">Select client</label>
                            <select id="clientPick" style="height:42px;width:100%;border:1px solid #ccc;border-radius:4px;padding:0 10px;">
                                <option value="">-- Select a client --</option>
                                <?php
                                $myId = (string)($_SESSION['user_id'] ?? '');
                                $renderOption = static function (array $c): string {
                                    $full = trim(trim((string)($c['first_name'] ?? '')) . ' ' . trim((string)($c['middle_name'] ?? '')) . ' ' . trim((string)($c['last_name'] ?? '')));
                                    $full = trim(preg_replace('/\s+/', ' ', $full));
                                    $addrParts = [];
                                    if (!empty($c['sitio_street'])) $addrParts[] = $c['sitio_street'];
                                    if (!empty($c['barangay'])) $addrParts[] = 'Brgy. ' . $c['barangay'];
                                    if (!empty($c['municipality']) || !empty($c['city'])) {
                                        $addrParts[] = $c['municipality'] ?: $c['city'];
                                    }
                                    $label = $full ?: 'Unnamed client';
                                    if ($addrParts) {
                                        $label .= ' - ' . implode(', ', $addrParts);
                                    }
                                    $addressValue = $addrParts ? implode(', ', $addrParts) : '';
                                    $attrs = sprintf(
                                        ' value="%s" data-first="%s" data-middle="%s" data-last="%s" data-sitio="%s" data-barangay="%s" data-municipality="%s" data-city="%s" data-address="%s"',
                                        htmlspecialchars((string)($c['client_id'] ?? ''), ENT_QUOTES),
                                        htmlspecialchars((string)($c['first_name'] ?? ''), ENT_QUOTES),
                                        htmlspecialchars((string)($c['middle_name'] ?? ''), ENT_QUOTES),
                                        htmlspecialchars((string)($c['last_name'] ?? ''), ENT_QUOTES),
                                        htmlspecialchars((string)($c['sitio_street'] ?? ''), ENT_QUOTES),
                                        htmlspecialchars((string)($c['barangay'] ?? ''), ENT_QUOTES),
                                        htmlspecialchars((string)($c['municipality'] ?? ''), ENT_QUOTES),
                                        htmlspecialchars((string)($c['city'] ?? ''), ENT_QUOTES),
                                        htmlspecialchars($addressValue, ENT_QUOTES)
                                    );
                                    return '<option' . $attrs . '>' . htmlspecialchars($label, ENT_QUOTES) . '</option>';
                                };
                                $hasMine = false;
                                foreach ($clientRows as $c) {
                                    if ((string)($c['user_id'] ?? '') !== $myId) continue;
                                    if (!$hasMine) {
                                        $hasMine = true;
                                    }
                                    echo $renderOption($c);
                                }

                                if (!$hasMine) {
                                    echo '<option disabled>No clients found</option>';
                                }
                                ?>
                            </select>
                            <div id="clientPickError" class="field-error" style="display:none;"></div>
                        </div>

                        <div class="form-row" id="newClientRow">
                            <div class="form-group">
                                <label for="first-name" class="required">First Name:</label>
                                <input type="text" id="first-name" name="first_name" />
                                <div id="first-name-error" class="field-error" style="display:none"></div>
                            </div>
                            <div class="form-group">
                                <label for="middle-name">Middle Name:</label>
                                <input type="text" id="middle-name" name="middle_name" />
                                <!-- no error div (excluded by request) -->
                            </div>
                            <div class="form-group">
                                <label for="last-name" class="required">Last Name:</label>
                                <input type="text" id="last-name" name="last_name" />
                                <div id="last-name-error" class="field-error" style="display:none"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="residence-address" class="required">Residence Address:</label>
                            <input type="text" id="residence-address">
                            <div id="residence-address-error" class="field-error" style="display:none"></div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="telephone-number">Telephone Number:</label>
                                <input type="text" id="telephone-number">
                                <div id="telephone-number-error" class="field-error" style="display:none"></div>
                            </div>

                            <div class="form-group">
                                <label for="establishment-name" class="required">Name of Establishment:</label>
                                <input type="text" id="establishment-name">
                                <div id="establishment-name-error" class="field-error" style="display:none"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="establishment-address" class="required">Address of Establishment:</label>
                            <input type="text" id="establishment-address">
                            <div id="establishment-address-error" class="field-error" style="display:none"></div>
                        </div>

                        <div class="form-group">
                            <label for="establishment-telephone">Establishment Telephone Number:</label>
                            <input type="text" id="establishment-telephone">
                            <div id="establishment-telephone-error" class="field-error" style="display:none"></div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>ANIMALS/STOCKS INFORMATION</h2>

                        <table id="animals-table">
                            <thead>
                                <tr>
                                    <th>Common Name</th>
                                    <th>Scientific Name</th>
                                    <th>Quantity</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <input type="text" class="table-input">
                                        <div class="field-error" style="display:none"></div>
                                    </td>
                                    <td>
                                        <input type="text" class="table-input">
                                        <div class="field-error" style="display:none"></div>
                                    </td>
                                    <td>
                                        <input type="number" class="table-input" min="1">
                                        <div class="field-error" style="display:none"></div>
                                    </td>
                                    <td><button type="button" class="remove-row-btn">Remove</button></td>
                                </tr>
                            </tbody>
                        </table>

                        <button type="button" class="add-row-btn" id="add-row-btn">
                            <i class="fas fa-plus"></i> Add Animal
                        </button>
                    </div>

                    <div class="form-section">
                        <h2>DECLARATION</h2>
                        <div class="declaration">
                            <p>I understand that the filling of this application conveys no right to possess any wild animals until Certificate of Registration is issued to me by the Regional Director of the DENR Region 7.</p>

                            <div class="signature-date">
                                <div class="signature-box">
                                    <label>Signature of Applicant:</label>
                                    <div class="signature-pad-container">
                                        <canvas id="signature-pad"></canvas>
                                    </div>
                                    <div class="signature-actions">
                                        <button type="button" class="signature-btn clear-signature" id="clear-signature">Clear</button>
                                        <!-- <button type="button" class="signature-btn save-signature" id="save-signature">Save Signature</button> -->
                                    </div>
                                    <div class="signature-preview">
                                        <img id="signature-image" class="hidden" alt="Signature">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="postal-address">Postal Address:</label>
                                <input type="text" id="postal-address">
                                <div id="postal-address-error" class="field-error" style="display:none"></div>
                            </div>
                        </div>
                    </div>

                    <!-- (Removed "Download Form as Word Document" button & per-section loading) -->
                </div>
                <!-- /NEW upper sections -->

                <!-- ================= RENEWAL (WILDLIFE) upper sections ================= -->
                <div id="renewal-upper-block" style="display:none;">

                    <!-- checkbox group -->
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="renewal-zoo">
                            <label for="renewal-zoo">Zoo</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="renewal-botanical-garden">
                            <label for="renewal-botanical-garden">Botanical Garden</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="renewal-private-collection">
                            <label for="renewal-private-collection">Private Collection</label>
                        </div>

                        <!-- group error (at least one must be checked) -->
                        <div id="renewal-category-error" class="field-error group" style="display:none"></div>
                    </div>

                    <div class="form-section">
                        <h2>APPLICANT INFORMATION</h2>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="renewal-first-name" class="required">First Name:</label>
                                <input type="text" id="renewal-first-name">
                                <div id="renewal-first-name-error" class="field-error" style="display:none"></div>
                            </div>

                            <div class="form-group">
                                <label for="renewal-middle-name">Middle Name:</label>
                                <input type="text" id="renewal-middle-name">
                                <!-- no error div (excluded by request) -->
                            </div>

                            <div class="form-group">
                                <label for="renewal-last-name" class="required">Last Name:</label>
                                <input type="text" id="renewal-last-name">
                                <div id="renewal-last-name-error" class="field-error" style="display:none"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="renewal-residence-address" class="required">Residence Address:</label>
                            <input type="text" id="renewal-residence-address">
                            <div id="renewal-residence-address-error" class="field-error" style="display:none"></div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="renewal-telephone-number">Telephone Number:</label>
                                <input type="text" id="renewal-telephone-number">
                                <div id="renewal-telephone-number-error" class="field-error" style="display:none"></div>
                            </div>

                            <div class="form-group">
                                <label for="renewal-establishment-name" class="required">Name of Establishment:</label>
                                <input type="text" id="renewal-establishment-name">
                                <div id="renewal-establishment-name-error" class="field-error" style="display:none"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="renewal-establishment-address" class="required">Address of Establishment:</label>
                            <input type="text" id="renewal-establishment-address">
                            <div id="renewal-establishment-address-error" class="field-error" style="display:none"></div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="renewal-establishment-telephone">Establishment Telephone Number:</label>
                                <input type="text" id="renewal-establishment-telephone">
                                <div id="renewal-establishment-telephone-error" class="field-error" style="display:none"></div>
                            </div>

                            <div class="form-group">
                                <label for="renewal-wfp-number" class="required">Original WFP No.:</label>
                                <input type="text" id="renewal-wfp-number">
                                <div id="renewal-wfp-number-error" class="field-error" style="display:none"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="renewal-issue-date" class="required">Issued on:</label>
                            <input type="date" id="renewal-issue-date">
                            <div id="renewal-issue-date-error" class="field-error" style="display:none"></div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>ANIMALS/STOCKS INFORMATION</h2>

                        <table id="renewal-animals-table">
                            <thead>
                                <tr>
                                    <th>Common Name</th>
                                    <th>Scientific Name</th>
                                    <th>Quantity</th>
                                    <th>Remarks (Alive/Deceased)</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <input type="text" class="table-input">
                                        <div class="field-error" style="display:none"></div>
                                    </td>
                                    <td>
                                        <input type="text" class="table-input">
                                        <div class="field-error" style="display:none"></div>
                                    </td>
                                    <td>
                                        <input type="number" class="table-input" min="1">
                                        <div class="field-error" style="display:none"></div>
                                    </td>
                                    <td>
                                        <select class="table-input">
                                            <option value="Alive">Alive</option>
                                            <option value="Deceased">Deceased</option>
                                        </select>
                                        <!-- no error div for remarks -->
                                    </td>
                                    <td><button type="button" class="remove-row-btn">Remove</button></td>
                                </tr>
                            </tbody>
                        </table>

                        <button type="button" class="add-row-btn" id="renewal-add-row-btn">
                            <i class="fas fa-plus"></i> Add Animal
                        </button>
                    </div>

                    <div class="form-section">
                        <h2>DECLARATION</h2>
                        <div class="declaration">
                            <p>I understand that this application for renewal does not by itself grant the right to continue possession of any wild animals until the corresponding Renewal Certificate of Registration is issued.</p>

                            <div class="signature-date">
                                <div class="signature-box">
                                    <label>Signature of Applicant:</label>
                                    <div class="signature-pad-container">
                                        <canvas id="renewal-signature-pad" style="height: 180px;"></canvas>
                                    </div>
                                    <div class="signature-actions">
                                        <button type="button" class="signature-btn clear-signature" id="renewal-clear-signature">Clear</button>
                                        <!-- <button type="button" class="signature-btn save-signature" id="renewal-save-signature">Save Signature</button> -->
                                    </div>
                                    <div class="signature-preview">
                                        <img id="renewal-signature-image" class="hidden" alt="Signature">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="renewal-postal-address">Postal Address:</label>
                                <input type="text" id="renewal-postal-address">
                                <div id="renewal-postal-address-error" class="field-error" style="display:none"></div>
                            </div>
                        </div>
                    </div>

                    <!-- (Removed Renewal "Download Form as Word Document" button & per-section loading) -->
                </div>
                <!-- /RENEWAL upper sections -->

                <!-- ============ NEW PERMIT REQUIREMENTS (default visible) ============ -->
                <!-- (unchanged – file uploads don’t get error divs) -->
                <div class="requirements-list" id="new-requirements" style="display: grid;">
                    <!-- 1 -->

                    <!-- 2 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">1</span>
                                SEC/CDA Registration (Security and Exchange Commission/Cooperative Development Authority) DTI, if for commercial purposes
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-2" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload SEC/CDA/DTI Registration
                                </label>
                                <input type="file" id="file-2" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-2"></div>
                        </div>
                    </div>
                    <!-- 3 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">2</span>
                                Proof of Scientific Expertise (Veterinary Certificate)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-3" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Veterinary Certificate
                                </label>
                                <input type="file" id="file-3" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-3"></div>
                        </div>
                    </div>
                    <!-- 4 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">3</span>
                                Financial Plan for Breeding (Financial/Bank Statement)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-4" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Financial/Bank Statement
                                </label>
                                <input type="file" id="file-4" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-4"></div>
                        </div>
                    </div>
                    <!-- 5 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">4</span>
                                Proposed Facility Design (Photo of Facility)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-5" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Photo of Facility
                                </label>
                                <input type="file" id="file-5" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-5"></div>
                        </div>
                    </div>
                    <!-- 6 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">5</span>
                                Prior Clearance of affected communities (Municipal or Barangay Clearance)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-6" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Municipal/Barangay Clearance
                                </label>
                                <input type="file" id="file-6" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-6"></div>
                        </div>
                    </div>
                    <!-- 7 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">6</span>
                                Vicinity Map of the area/site (Ex. Google map Sketch map)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-7" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Vicinity Map
                                </label>
                                <input type="file" id="file-7" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-7"></div>
                        </div>
                    </div>
                    <!-- 8 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">7</span>
                                Legal Acquisition of Wildlife:
                            </div>
                        </div>
                        <div class="file-upload">
                            <h4>Proof of Purchase (Official Receipt/Deed of Sale or Captive Bred Certificate)</h4>
                            <div class="file-input-container">
                                <label for="file-8a" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Proof of Purchase
                                </label>
                                <input type="file" id="file-8a" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-8a"></div>

                            <h4>Deed of Donation with Notary</h4>
                            <div class="file-input-container" style="margin-top:8px;">
                                <label for="file-8b" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Deed of Donation
                                </label>
                                <input type="file" id="file-8b" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-8b"></div>
                        </div>
                    </div>
                    <!-- 9 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">8</span>
                                Inspection Report conducted by concerned CENRO
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-9" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Inspection Report
                                </label>
                                <input type="file" id="file-9" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-9"></div>
                        </div>
                    </div>

                    <div class="fee-info">
                        <p><strong>Application and Processing Fee:</strong> ₱500.00</p>
                        <p><strong>Permit Fee:</strong> ₱2,500.00</p>
                        <p><strong>Total Fee:</strong> ₱3,000.00</p>
                    </div>
                </div>
                <!-- =============== /NEW REQUIREMENTS =============== -->

                <!-- ================= RENEWAL REQUIREMENTS ================= -->
                <!-- (unchanged – file uploads don’t get error divs) -->
                <div class="requirements-list" id="renewal-requirements" style="display: none;">

                    <!-- 2 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">1</span>
                                Copy of previous WFP (Original copy)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="renewal-file-2" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="renewal-file-2" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="renewal-uploaded-files-2"></div>
                        </div>
                    </div>
                    <!-- 3 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">2</span>
                                Quarterly Breeding Report & Monthly Production report
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="renewal-file-3" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="renewal-file-3" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="renewal-uploaded-files-3"></div>
                        </div>
                    </div>
                    <!-- 4 (a-d) -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">3</span>
                                For additional stocks (if any)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="sub-requirement">
                                <p style="margin-bottom: 10px; font-weight: 500;">- WFP holders/ CITES/Non-CITES Import permit</p>
                                <div class="file-input-container">
                                    <label for="renewal-file-4a" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="renewal-file-4a" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="renewal-uploaded-files-4a"></div>
                            </div>
                            <div class="sub-requirement" style="margin-top: 15px;">
                                <p style="margin-bottom: 10px; font-weight: 500;">- Proof of Purchase (Official Receipt/ Sales Invoice or Deed of Sale)</p>
                                <div class="file-input-container">
                                    <label for="renewal-file-4b" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="renewal-file-4b" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="renewal-uploaded-files-4b"></div>
                            </div>
                            <div class="sub-requirement" style="margin-top: 15px;">
                                <p style="margin-bottom: 10px; font-weight: 500;">- Notarized Deed of Donation</p>
                                <div class="file-input-container">
                                    <label for="renewal-file-4c" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="renewal-file-4c" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="renewal-uploaded-files-4c"></div>
                            </div>
                            <div class="sub-requirement" style="margin-top: 15px;">
                                <p style="margin-bottom: 10px; font-weight: 500;">- Local Transport Permit (if applicable)</p>
                                <div class="file-input-container">
                                    <label for="renewal-file-4d" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="renewal-file-4d" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="renewal-uploaded-files-4d"></div>
                            </div>
                        </div>
                    </div>
                    <!-- 5 (a-c) -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">4</span>
                                For additional facility (if any)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="sub-requirement">
                                <p style="margin-bottom: 10px; font-weight: 500;">- Barangay Clearance/ Mayor Clearance</p>
                                <div class="file-input-container">
                                    <label for="renewal-file-5a" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="renewal-file-5a" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="renewal-uploaded-files-5a"></div>
                            </div>
                            <div class="sub-requirement" style="margin-top: 15px;">
                                <p style="margin-bottom: 10px; font-weight: 500;">- Proposed facility design</p>
                                <div class="file-input-container">
                                    <label for="renewal-file-5b" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="renewal-file-5b" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="renewal-uploaded-files-5b"></div>
                            </div>
                            <div class="sub-requirement" style="margin-top: 15px;">
                                <p style="margin-bottom: 10px; font-weight: 500;">- Sketch map of the location</p>
                                <div class="file-input-container">
                                    <label for="renewal-file-5c" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="renewal-file-5c" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="renewal-uploaded-files-5c"></div>
                            </div>
                        </div>
                    </div>
                    <!-- 6 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">5</span>
                                Inspection Report conducted by concerned CENRO/Regional Office
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="renewal-file-6" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="renewal-file-6" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="renewal-uploaded-files-6"></div>
                        </div>
                    </div>
                </div>
                <!-- =============== /RENEWAL REQUIREMENTS =============== -->

            </div>


            <div class="form-footer">
                <button class="btn btn-primary" id="submitApplication">
                    <i class="fas fa-paper-plane"></i> Submit Request
                </button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div id="profile-notification" style="display:none; position:fixed; top:5px; left:50%; transform:translateX(-50%); background:#323232; color:#fff; padding:16px 32px; border-radius:8px; font-size:1.1rem; z-index:9999; box-shadow:0 2px 8px rgba(0,0,0,0.15); text-align:center; min-width:220px; max-width:90vw;"></div>

    <!-- File Preview Modal (kept) -->
    <div id="filePreviewModal" class="modal">
        <div class="modal-content">
            <span id="closeFilePreviewModal" class="close-modal">&times;</span>
            <h3 id="modal-title">File Preview</h3>
            <iframe id="filePreviewFrame" class="file-preview" src="about:blank"></iframe>
        </div>
    </div>

    <!-- Global Loading Overlay (unified) -->
    <div id="loadingIndicator" style="display:none;position:fixed;inset:0;align-items:center;justify-content:center;background:rgba(0,0,0,.25);z-index:9998">
        <div class="card" style="background:#fff;padding:18px 22px;border-radius:10px;display:flex;gap:10px;align-items:center;">
            <span class="loader" style="width:16px;height:16px;border:2px solid #ddd;border-top-color:#2b6625;border-radius:50%;display:inline-block;animation:spin 0.8s linear infinite;"></span>
            <span id="loadingMessage">Working…</span>
        </div>
    </div>




    <!-- Single “Client Decision” Modal -->
    <div id="clientDecisionModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:10000;align-items:center;justify-content:center;">
        <div style="background:#fff;max-width:560px;width:92%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden">
            <div style="padding:18px 20px;border-bottom:1px solid #eee;font-weight:600" id="clientDecisionTitle">Client</div>
            <div style="padding:16px 20px;line-height:1.6" id="clientDecisionBody">
                <!-- dynamic -->
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;padding:14px 20px;background:#fafafa;border-top:1px solid #eee" id="clientDecisionActions">
                <!-- dynamic buttons -->
            </div>
        </div>
    </div>

    <!-- Single “Validation” Modal -->
    <div id="validationModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:10000;align-items:center;justify-content:center;">
        <div style="background:#fff;max-width:560px;width:92%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden">
            <div style="padding:18px 20px;border-bottom:1px solid #eee;font-weight:600" id="validationTitle">Check Required</div>
            <div style="padding:16px 20px;line-height:1.6" id="validationBody">
                <!-- dynamic -->
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;padding:14px 20px;background:#fafafa;border-top:1px solid #eee" id="validationActions">
                <!-- dynamic buttons -->
            </div>
        </div>
    </div>


    <script>
        window.__WFP_RECORDS__ = <?= $wfpRecordsJson ?? '{}' ?>;
    </script>

    <script>
        (() => {
            // ====== CONFIG ======
            const SAVE_URL = new URL('../backend/users/wildlife/save_wildlife.php', window.location.href).toString();
            const PRECHECK_URL = new URL('../backend/users/wildlife/precheck_wildlife.php', window.location.href).toString();
            // --- Global loading helpers (use everywhere) ---
            function showLoading(msg = 'Working…') {
                const overlay = document.getElementById('loadingIndicator');
                const label = document.getElementById('loadingMessage');
                if (label) label.textContent = msg;
                if (overlay) overlay.style.display = 'flex';
            }

            function hideLoading() {
                const overlay = document.getElementById('loadingIndicator');
                if (overlay) overlay.style.display = 'none';
            }

            // ====== UTIL ======
            const byId = (id) => document.getElementById(id);
            const v = (id) => (byId(id)?.value || '').trim();
            const activePermitType = () =>
                (document.querySelector('.permit-type-btn.active')?.getAttribute('data-type') || 'new');
            const wfpRecords = window.__WFP_RECORDS__ || {};
            const renewalWfpPicker = byId('renewalWfpPicker');
            const renewalWfpSelect = byId('renewalWfpSelect');
            const fileContainerIds = [
                'renewal-uploaded-files-2',
                'renewal-uploaded-files-3',
                'renewal-uploaded-files-4a',
                'renewal-uploaded-files-4b',
                'renewal-uploaded-files-4c',
                'renewal-uploaded-files-4d',
                'renewal-uploaded-files-5a',
                'renewal-uploaded-files-5b',
                'renewal-uploaded-files-5c',
                'renewal-uploaded-files-6',
            ];
            const fileInputByContainer = {
                'renewal-uploaded-files-2': 'renewal-file-2',
                'renewal-uploaded-files-3': 'renewal-file-3',
                'renewal-uploaded-files-4a': 'renewal-file-4a',
                'renewal-uploaded-files-4b': 'renewal-file-4b',
                'renewal-uploaded-files-4c': 'renewal-file-4c',
                'renewal-uploaded-files-4d': 'renewal-file-4d',
                'renewal-uploaded-files-5a': 'renewal-file-5a',
                'renewal-uploaded-files-5b': 'renewal-file-5b',
                'renewal-uploaded-files-5c': 'renewal-file-5c',
                'renewal-uploaded-files-6': 'renewal-file-6',
            };
            const existingFileCache = Object.create(null); // fieldId -> File
            const existingFileFetches = Object.create(null); // fieldId -> Promise
            const existingFileTargets = Object.create(null); // fieldId -> url
            const existingFileUrls = Object.create(null); // fieldId -> original URL/reference

            // ====== CLIENT MODE (new permit) ======
            const clientModeEl = byId('clientMode');
            const btnExisting = byId('btnExisting');
            const btnNew = byId('btnNew');
            const existingClientRow = byId('existingClientRow');
            const newClientRow = byId('newClientRow');
            const clientPick = byId('clientPick');
            const clientPickError = byId('clientPickError');
            const clientModeToggle = byId('clientModeToggle');
            const firstNameInput = byId('first-name');
            const middleNameInput = byId('middle-name');
            const lastNameInput = byId('last-name');
            const residenceAddrInput = byId('residence-address');
            const nameInputs = [firstNameInput, middleNameInput, lastNameInput];
            let manualClientCache = {
                first: firstNameInput?.value || '',
                middle: middleNameInput?.value || '',
                last: lastNameInput?.value || '',
                residence: residenceAddrInput?.value || '',
            };

            function clearFieldErrorEl(input) {
                if (!input) return;
                const err = input.nextElementSibling;
                if (err?.classList?.contains('field-error')) err.style.display = 'none';
                input.classList.remove('invalid');
                input.removeAttribute('aria-invalid');
            }

            function setClientPickError(msg) {
                if (!clientPickError) return;
                if (msg) {
                    clientPickError.textContent = msg;
                    clientPickError.style.display = 'block';
                    clientPick?.classList?.add('invalid');
                    clientPick?.setAttribute('aria-invalid', 'true');
                } else {
                    clientPickError.textContent = '';
                    clientPickError.style.display = 'none';
                    clientPick?.classList?.remove('invalid');
                    clientPick?.removeAttribute('aria-invalid');
                }
            }

            function clearClientPickError() {
                setClientPickError('');
            }

            function populateNameInputs(first, middle, last, opts = {}) {
                const silent = !!opts.silent;
                const apply = (input, value) => {
                    if (!input) return;
                    input.value = value || '';
                    if (silent) {
                        clearFieldErrorEl(input);
                    } else {
                        input.dispatchEvent(new Event('input', {
                            bubbles: true
                        }));
                    }
                };
                apply(firstNameInput, first);
                apply(middleNameInput, middle);
                apply(lastNameInput, last);
            }

            function setResidenceAddress(value, opts = {}) {
                const silent = !!opts.silent;
                if (!residenceAddrInput) return;
                residenceAddrInput.value = value || '';
                if (silent) {
                    clearFieldErrorEl(residenceAddrInput);
                } else {
                    residenceAddrInput.dispatchEvent(new Event('input', {
                        bubbles: true
                    }));
                }
            }

            function applyClientPickValues(showError = false) {
                if (!clientPick) return;
                if (!clientPick.value) {
                    if (showError) {
                        setClientPickError('Please select an existing client.');
                    } else {
                        clearClientPickError();
                    }
                    populateNameInputs('', '', '', {
                        silent: true
                    });
                    setResidenceAddress('', {
                        silent: true
                    });
                    return;
                }
                const opt = clientPick.options[clientPick.selectedIndex];
                const first = opt?.dataset?.first || '';
                const middle = opt?.dataset?.middle || '';
                const last = opt?.dataset?.last || '';
                const address = opt?.dataset?.address || '';
                populateNameInputs(first, middle, last);
                setResidenceAddress(address);
                clearClientPickError();
            }

            function setClientMode(mode = 'new') {
                if (!clientModeEl) return;
                const isExisting = mode === 'existing';
                clientModeEl.value = isExisting ? 'existing' : 'new';

                if (existingClientRow) existingClientRow.style.display = isExisting ? '' : 'none';
                if (newClientRow) newClientRow.style.display = isExisting ? 'none' : '';
                if (btnExisting) btnExisting.style.display = isExisting ? 'none' : 'inline-flex';
                if (btnNew) btnNew.style.display = isExisting ? 'inline-flex' : 'none';

                nameInputs.forEach((input) => {
                    if (!input) return;
                    if (isExisting) {
                        input.setAttribute('readonly', 'readonly');
                    } else {
                        input.removeAttribute('readonly');
                    }
                    input.classList.toggle('readonly-input', isExisting);
                });

                if (isExisting) {
                    manualClientCache = {
                        first: firstNameInput?.value || '',
                        middle: middleNameInput?.value || '',
                        last: lastNameInput?.value || '',
                        residence: residenceAddrInput?.value || '',
                    };
                    applyClientPickValues(false);
                } else {
                    clearClientPickError();
                    if (clientPick) clientPick.value = '';
                    populateNameInputs(
                        manualClientCache.first || '',
                        manualClientCache.middle || '',
                        manualClientCache.last || '', {
                            silent: true
                        }
                    );
                    setResidenceAddress(manualClientCache.residence || '', {
                        silent: true
                    });
                }
            }

            btnExisting?.addEventListener('click', () => setClientMode('existing'));
            btnNew?.addEventListener('click', () => setClientMode('new'));
            clientPick?.addEventListener('change', () => {
                if ((clientModeEl?.value || 'new') === 'existing') {
                    applyClientPickValues(true);
                } else {
                    clearClientPickError();
                }
            });
            setClientMode(clientModeEl?.value || 'new');

            function toast(msg) {
                const n = byId('profile-notification');
                if (!n) return;
                n.textContent = msg;
                n.style.display = 'block';
                n.style.opacity = '1';
                setTimeout(() => {
                    n.style.opacity = '0';
                    setTimeout(() => {
                        n.style.display = 'none';
                        n.style.opacity = '1';
                    }, 350);
                }, 2400);
            }

            const toYMD = (value) => {
                if (!value) return '';
                if (/^\d{4}-\d{2}-\d{2}/.test(value)) return value.slice(0, 10);
                const d = new Date(value);
                if (isNaN(d.getTime())) return '';
                const mm = String(d.getMonth() + 1).padStart(2, '0');
                const dd = String(d.getDate()).padStart(2, '0');
                return `${d.getFullYear()}-${mm}-${dd}`;
            };
            const titleCase = (value = '') => {
                const lower = String(value || '').toLowerCase();
                return lower.replace(/\b\w/g, (char) => char.toUpperCase());
            };

            function toggleWfpPicker(isNew) {
                if (!renewalWfpPicker) return;
                renewalWfpPicker.style.display = isNew ? 'none' : 'flex';
            }

            function clearAllFileContainers() {
                fileContainerIds.forEach((id) => {
                    const el = byId(id);
                    if (el) el.innerHTML = '';
                    const inputId = fileInputByContainer[id];
                    if (inputId) {
                        const nameSpan = byId(inputId)?.closest('.file-input-container')?.querySelector('.file-name');
                        if (nameSpan) nameSpan.textContent = 'No file chosen';
                        delete existingFileCache[inputId];
                        delete existingFileFetches[inputId];
                        delete existingFileTargets[inputId];
                        delete existingFileUrls[inputId];
                    }
                });
            }

            function clearRenewalFilePreviews() {
                clearAllFileContainers();
            }

            function renderRenewalFiles(data = {}) {
                clearAllFileContainers();
                const fileMap = data.files || {};
                Object.entries(fileMap).forEach(([containerId, entries]) => {
                    const el = byId(containerId);
                    if (!el) return;
                    const list = Array.isArray(entries) ? entries : [entries];
                    existingFileUrls[fileInputByContainer[containerId]] = list[0]?.url || '';
                    const frag = document.createDocumentFragment();
                    list.forEach((entry) => {
                        if (!entry || !entry.url) return;
                        const link = document.createElement('a');
                        link.href = entry.url;
                        link.target = '_blank';
                        link.rel = 'noopener noreferrer';
                        link.textContent = entry.label || 'View file';
                        link.style.display = 'block';
                        frag.appendChild(link);
                    });
                    if (frag.childNodes.length) {
                        el.appendChild(frag);
                        const inputId = fileInputByContainer[containerId];
                        if (inputId) {
                            const nameSpan = byId(inputId)?.closest('.file-input-container')?.querySelector('.file-name');
                            if (nameSpan) nameSpan.textContent = fileNameFromEntry(list[0]) || 'Loading file...';
                            queueExistingFileDownload(inputId, list[0]);
                        }
                    }
                });
            }

            function fileNameFromEntry(entry) {
                if (!entry) return '';
                if (entry.url) {
                    const clean = entry.url.split('?')[0];
                    const idx = clean.lastIndexOf('/');
                    const last = idx >= 0 ? clean.substring(idx + 1) : clean;
                    try {
                        return decodeURIComponent(last) || entry.label || '';
                    } catch {
                        return last || entry.label || '';
                    }
                }
                return entry.label || '';
            }

            function queueExistingFileDownload(fieldId, entry) {
                if (!fieldId || !entry || !entry.url) {
                    delete existingFileCache[fieldId];
                    delete existingFileFetches[fieldId];
                    delete existingFileTargets[fieldId];
                    return;
                }
                const url = entry.url;
                const label = entry.label || fieldId;
                existingFileTargets[fieldId] = url;
                const promise = fetch(url, {
                    credentials: 'include'
                }).then(res => {
                    if (!res.ok) throw new Error(`Failed to fetch ${url}`);
                    return res.blob();
                }).then(blob => {
                    let base = label.replace(/[^\w.\-]+/g, '_') || fieldId;
                    const urlPath = url.split('?')[0];
                    const ext = urlPath.includes('.') ? urlPath.substring(urlPath.lastIndexOf('.') + 1) : '';
                    if (ext && !base.toLowerCase().endsWith(`.${ext.toLowerCase()}`)) {
                        base = `${base}.${ext}`;
                    }
                    const file = new File([blob], base, {
                        type: blob.type || 'application/octet-stream'
                    });
                    if (existingFileTargets[fieldId] !== url) return;
                    existingFileCache[fieldId] = file;
                    delete existingFileFetches[fieldId];
                    const inputEl = byId(fieldId);
                    const hasUserFile = !!(inputEl?.files && inputEl.files.length);
                    if (inputEl && typeof DataTransfer !== 'undefined') {
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        inputEl.files = dt.files;
                    }
                    if (!hasUserFile || !inputEl) {
                        const nameSpan = inputEl?.closest('.file-input-container')?.querySelector('.file-name');
                        if (nameSpan) nameSpan.textContent = file.name;
                    }
                }).catch(err => {
                    console.error('Existing file fetch failed:', err);
                    delete existingFileCache[fieldId];
                    delete existingFileFetches[fieldId];
                    delete existingFileTargets[fieldId];
                });
                existingFileFetches[fieldId] = promise;
            }

            function populateRenewalAnimalsFromData(rows = []) {
                const tbody = document.querySelector('#renewal-animals-table tbody');
                if (!tbody) return;
                const template = tbody.querySelector('tr') ? tbody.querySelector('tr').cloneNode(true) : null;
                tbody.innerHTML = '';
                const list = Array.isArray(rows) && rows.length ? rows : [{}];
                const valFrom = (obj, keys) => {
                    for (const key of keys) {
                        if (obj && obj[key] !== undefined && obj[key] !== null) {
                            return obj[key];
                        }
                    }
                    return '';
                };
                const buildRow = (item = {}) => {
                    let tr;
                    if (template) {
                        tr = template.cloneNode(true);
                    } else {
                        tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td><input type="text" class="table-input"><div class="field-error" style="display:none"></div></td>
                            <td><input type="text" class="table-input"><div class="field-error" style="display:none"></div></td>
                            <td><input type="number" class="table-input" min="1"><div class="field-error" style="display:none"></div></td>
                            <td>
                                <select class="table-input">
                                    <option value="Alive">Alive</option>
                                    <option value="Deceased">Deceased</option>
                                </select>
                            </td>
                            <td><button type="button" class="remove-row-btn">Remove</button></td>
                        `;
                    }
                    const inputs = tr.querySelectorAll('input, select');
                    const [commonInput, sciInput, qtyInput, remarksSelect] = inputs;
                    if (commonInput) commonInput.value = valFrom(item, ['commonName', 'common', 'common_name']);
                    if (sciInput) sciInput.value = valFrom(item, ['scientificName', 'scientific', 'scientific_name']);
                    if (qtyInput) qtyInput.value = valFrom(item, ['quantity', 'qty', 'count']);
                    if (remarksSelect) {
                        const remarksVal = String(valFrom(item, ['remarks', 'status']) || '');
                        if (remarksVal) {
                            const match = Array.from(remarksSelect.options).some(opt => opt.value === remarksVal);
                            remarksSelect.value = match ? remarksVal : (remarksSelect.options[0]?.value || '');
                        } else if (remarksSelect.options.length) {
                            remarksSelect.value = remarksSelect.options[0].value;
                        }
                    }
                    return tr;
                };
                list.forEach((item) => {
                    tbody.appendChild(buildRow(item));
                });
                if (!tbody.children.length && template) {
                    tbody.appendChild(template.cloneNode(true));
                }
                Array.from(tbody.querySelectorAll('.remove-row-btn')).forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const row = btn.closest('tr');
                        if (!row) return;
                        if (tbody.children.length <= 1) return;
                        row.remove();
                    });
                });
            }

            function applyWfpRecord(key, {
                silent = false
            } = {}) {
                const data = wfpRecords[key];
                if (!data) return;
                if (activePermitType() !== 'renewal') {
                    setPermit('renewal');
                }
                const assign = (id, value) => {
                    const el = byId(id);
                    if (el) el.value = value ?? '';
                };
                const info = data.info || {};
                const client = data.client || {};
                assign('renewal-first-name', client.first_name || '');
                assign('renewal-middle-name', client.middle_name || '');
                assign('renewal-last-name', client.last_name || '');
                assign('renewal-residence-address', info.residence_address || '');
                assign('renewal-telephone-number', info.telephone_number || '');
                assign('renewal-establishment-name', info.establishment_name || '');
                assign('renewal-establishment-address', info.establishment_address || '');
                assign('renewal-establishment-telephone', info.establishment_telephone || '');
                assign('renewal-postal-address', info.postal_address || '');
                assign('renewal-wfp-number', data.wfp_number || info.wfp_number || '');
                assign('renewal-issue-date', toYMD(data.issue_date || info.issue_date || data.date_issued || ''));
                const categories = data.categories || {};
                const setChecked = (id, value) => {
                    const el = byId(id);
                    if (el) el.checked = !!value;
                };
                setChecked('renewal-zoo', categories.zoo);
                setChecked('renewal-botanical-garden', categories.botanical_garden);
                setChecked('renewal-private-collection', categories.private_collection);
                populateRenewalAnimalsFromData(Array.isArray(data.animals) ? data.animals : []);
                renderRenewalFiles(data);
                if (!silent) {
                    const label = data.wfp_number || data.label || 'selected WFP';
                    toast(`Loaded details from ${label}.`);
                }
            }

            function dataURLToBlob(dataURL) {
                if (!dataURL) return null;
                const [meta, b64] = dataURL.split(',');
                const mime = (meta.match(/data:(.*?);base64/) || [])[1] || 'application/octet-stream';
                const bin = atob(b64 || '');
                const u8 = new Uint8Array(bin.length);
                for (let i = 0; i < bin.length; i++) u8[i] = bin.charCodeAt(i);
                return new Blob([u8], {
                    type: mime
                });
            }

            function makeMHTML(html, parts = []) {
                // For images/files embedded in the document, convert base64 to data URIs in HTML
                // This allows LibreOffice to properly render embedded images in the PDF
                let processedHtml = html;

                parts.forEach((p) => {
                    // Replace the location reference with a data URI
                    const dataUri = `data:${p.contentType};base64,${p.base64}`;
                    // Replace src="signature.png" with the actual data URI
                    processedHtml = processedHtml.replace(
                        new RegExp(`src=['"](${p.location}|${p.location.replace(/\//g, '\\/')})['"]`, 'g'),
                        `src="${dataUri}"`
                    );
                });

                return processedHtml;
            }

            function resetForm() {
                document.querySelectorAll('input[type="text"], input[type="date"], input[type="number"]').forEach(inp => inp.value = '');
                document.querySelectorAll('input[type="checkbox"]').forEach(inp => inp.checked = false);
                document.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
                document.querySelectorAll('input[type="file"]').forEach(fi => {
                    fi.value = '';
                    const nameSpan = fi.parentElement?.querySelector('.file-name');
                    if (nameSpan) nameSpan.textContent = 'No file chosen';
                });
                clearRenewalFilePreviews();
                if (renewalWfpSelect) renewalWfpSelect.selectedIndex = 0;
                // tables: leave one row
                ['animals-table', 'renewal-animals-table'].forEach(tid => {
                    const tbody = document.querySelector(`#${tid} tbody`);
                    if (tbody) {
                        Array.from(tbody.querySelectorAll('tr')).slice(1).forEach(tr => tr.remove());
                        tbody.querySelectorAll('input').forEach(i => i.value = '');
                        tbody.querySelectorAll('select').forEach(s => s.selectedIndex = 0);
                    }
                });
                // signatures
                clearSigPad(false);
                clearSigPad(true);
                setPermit('new');
                manualClientCache = {
                    first: '',
                    middle: '',
                    last: '',
                    residence: ''
                };
                setClientMode('new');
                chosenClientId = null;
                precheckCache = null;
                suggestedClient = null;
                pendingSuggestedClient = null;
                window.__FORCE_NEW_CLIENT__ = false;
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }

            // ====== MOBILE NAV ======
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');
            mobileToggle?.addEventListener('click', () => {
                const isActive = navContainer.classList.toggle('active');
                document.body.style.overflow = isActive ? 'hidden' : '';
            });

            // ====== PERMIT TYPE TOGGLE ======
            const newBtn = document.querySelector('.permit-type-btn[data-type="new"]');
            const renewalBtn = document.querySelector('.permit-type-btn[data-type="renewal"]');
            const newUpper = byId('new-upper-block');
            const newReqs = byId('new-requirements');
            const renUpper = byId('renewal-upper-block');
            const renReqs = byId('renewal-requirements');

            function setPermit(type) {
                const isNew = type === 'new';
                newUpper.style.display = isNew ? '' : 'none';
                newReqs.style.display = isNew ? 'grid' : 'none';
                renUpper.style.display = isNew ? 'none' : '';
                renReqs.style.display = isNew ? 'none' : 'grid';
                if (clientModeToggle) clientModeToggle.style.display = isNew ? 'flex' : 'none';
                newBtn.classList.toggle('active', isNew);
                renewalBtn.classList.toggle('active', !isNew);
                toggleWfpPicker(isNew);
            }
            renewalWfpSelect?.addEventListener('change', (e) => {
                const value = e.target.value;
                if (!value) {
                    clearRenewalFilePreviews();
                    return;
                }
                applyWfpRecord(value);
            });
            newBtn?.addEventListener('click', () => setPermit('new'));
            renewalBtn?.addEventListener('click', () => setPermit('renewal'));
            setPermit('new');

            // ...inside the same <script> where FILE INPUT LABEL SYNC lives...
            document.addEventListener('change', (e) => {
                const input = e.target;
                if (!(input && input.classList && input.classList.contains('file-input'))) return;

                const nameSpan = input.parentElement?.querySelector('.file-name');

                if (input.files && input.files.length > 0) {
                    // User picked a new file
                    if (nameSpan) nameSpan.textContent = input.files[0].name;
                    input.dataset.userCleared = '0';

                    // They actually replaced the preloaded file: drop caches/urls
                    if (input.id) {
                        delete existingFileCache[input.id];
                        delete existingFileFetches[input.id];
                        delete existingFileTargets[input.id];
                        delete existingFileUrls[input.id];
                    }
                } else {
                    // User opened the picker but canceled -> treat as CLEARED
                    input.dataset.userCleared = '1';
                    if (nameSpan) nameSpan.textContent = 'No file chosen';
                    // Important: do NOT delete caches/urls here; validator will block submit
                }
            });

            // ====== ANIMALS TABLES ======
            function bindAnimalsTable(addBtnId, tableId, hasRemarks = false) {
                const tbody = document.querySelector(`#${tableId} tbody`);
                const addBtn = byId(addBtnId);

                function attachRemove(tr) {
                    tr.querySelector('.remove-row-btn')?.addEventListener('click', () => {
                        if (tbody.children.length > 1) tbody.removeChild(tr);
                        else alert('You must have at least one animal entry.');
                    });
                }
                // seed existing remove
                Array.from(tbody.querySelectorAll('tr')).forEach(attachRemove);

                addBtn?.addEventListener('click', () => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
        <td><input type="text" class="table-input"></td>
        <td><input type="text" class="table-input"></td>
        <td><input type="number" class="table-input" min="1"></td>
        ${hasRemarks ? `
          <td>
            <select class="table-input">
              <option value="Alive">Alive</option>
              <option value="Deceased">Deceased</option>
            </select>
          </td>` : ''
        }
        <td><button type="button" class="remove-row-btn">Remove</button></td>
      `;
                    tbody.appendChild(tr);
                    attachRemove(tr);
                });
            }
            bindAnimalsTable('add-row-btn', 'animals-table', false);
            bindAnimalsTable('renewal-add-row-btn', 'renewal-animals-table', true);

            // ====== SIGNATURE PADS (manual canvas draw; no external lib) ======
            let sigNew = {
                has: false,
                dataURL: ''
            };
            let sigRen = {
                has: false,
                dataURL: ''
            };

            function initCanvasPad(canvasId, clearBtnId, saveBtnId, imgId, stateObj) {
                const canvas = byId(canvasId);
                const clearBtn = byId(clearBtnId);
                const saveBtn = byId(saveBtnId);
                const img = byId(imgId);
                if (!canvas) return;

                let isDrawing = false,
                    lastX = 0,
                    lastY = 0;

                function resizeCanvas() {
                    const ratio = Math.max(window.devicePixelRatio || 1, 1);
                    const cssWidth = canvas.clientWidth || 400;
                    const cssHeight = canvas.clientHeight || 150;
                    canvas.width = Math.floor(cssWidth * ratio);
                    canvas.height = Math.floor(cssHeight * ratio);
                    const ctx = canvas.getContext('2d');
                    // Use an identity transform and draw using bitmap coordinates.
                    // We'll map pointer events to bitmap pixels so coordinates line up
                    // regardless of devicePixelRatio or CSS scaling.
                    ctx.setTransform(1, 0, 0, 1, 0, 0);
                    ctx.fillStyle = '#fff';
                    // Fill the full bitmap area
                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                    // Scale line width so strokes look consistent on high-DPI
                    const scale = canvas.width / (cssWidth || 1);
                    ctx.lineWidth = 2 * scale;
                    ctx.lineCap = 'round';
                    ctx.strokeStyle = '#111';
                }

                function getPos(e) {
                    const rect = canvas.getBoundingClientRect();
                    const touch = e.touches ? e.touches[0] : null;
                    const clientX = touch ? touch.clientX : e.clientX;
                    const clientY = touch ? touch.clientY : e.clientY;
                    // Map CSS pixels to canvas bitmap pixels to avoid offsets
                    const scaleX = canvas.width / rect.width;
                    const scaleY = canvas.height / rect.height;
                    return {
                        x: (clientX - rect.left) * scaleX,
                        y: (clientY - rect.top) * scaleY
                    };
                }

                function start(e) {
                    isDrawing = true;
                    const {
                        x,
                        y
                    } = getPos(e);
                    lastX = x;
                    lastY = y;
                    e.preventDefault();
                }

                function move(e) {
                    if (!isDrawing) return;
                    const {
                        x,
                        y
                    } = getPos(e);
                    const ctx = canvas.getContext('2d');
                    ctx.beginPath();
                    ctx.moveTo(lastX, lastY);
                    ctx.lineTo(x, y);
                    ctx.stroke();
                    lastX = x;
                    lastY = y;
                    stateObj.has = true;
                    e.preventDefault();
                }

                function end() {
                    isDrawing = false;
                }

                resizeCanvas();
                window.addEventListener('resize', resizeCanvas);
                canvas.addEventListener('mousedown', start);
                canvas.addEventListener('mousemove', move);
                window.addEventListener('mouseup', end);
                canvas.addEventListener('touchstart', start, {
                    passive: false
                });
                canvas.addEventListener('touchmove', move, {
                    passive: false
                });
                window.addEventListener('touchend', end);

                clearBtn?.addEventListener('click', () => {
                    resizeCanvas();
                    stateObj.has = false;
                    stateObj.dataURL = '';
                    if (img) {
                        img.src = '';
                        img.classList.add('hidden');
                    }
                });
                saveBtn?.addEventListener('click', () => {
                    if (!stateObj.has) {
                        alert('Please draw your signature first.');
                        return;
                    }
                    stateObj.dataURL = canvas.toDataURL('image/png');
                    if (img) {
                        img.src = stateObj.dataURL;
                        img.classList.remove('hidden');
                    }
                });
            }

            function clearSigPad(isRenewal) {
                if (isRenewal) {
                    byId('renewal-clear-signature')?.click();
                } else {
                    byId('clear-signature')?.click();
                }
            }
            initCanvasPad('signature-pad', 'clear-signature', 'save-signature', 'signature-image', sigNew);
            initCanvasPad('renewal-signature-pad', 'renewal-clear-signature', 'renewal-save-signature', 'renewal-signature-image', sigRen);


            // ====== NEW unified modals & flow ======
            const loading = byId('loadingIndicator');

            // unified modals
            const clientDecisionModal = byId('clientDecisionModal');
            const cdTitle = byId('clientDecisionTitle');
            const cdBody = byId('clientDecisionBody');
            const cdActions = byId('clientDecisionActions');

            const validationModal = byId('validationModal');
            const valTitle = byId('validationTitle');
            const valBody = byId('validationBody');
            const valActions = byId('validationActions');

            let chosenClientId = null; // when user says "Yes, submit" using existing
            let precheckCache = null; // last precheck payload (contains flags)
            let suggestedClient = null; // suggested match (fuzzy)
            window.__FORCE_NEW_CLIENT__ = false;

            function openClientDecision({
                title,
                html,
                buttons
            }) {
                closeValidation();
                cdTitle.textContent = title || 'Client';
                cdBody.innerHTML = html || '';
                cdActions.innerHTML = '';
                buttons.forEach(btn => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = btn.class || 'btn btn-primary';
                    b.textContent = btn.text;
                    b.addEventListener('click', btn.onClick);
                    cdActions.appendChild(b);
                });
                clientDecisionModal.style.display = 'flex';
            }

            function closeClientDecision() {
                clientDecisionModal.style.display = 'none';
            }

            function openValidation({
                title,
                html,
                buttons
            }) {
                closeClientDecision();
                valTitle.textContent = title || 'Validation';
                valBody.innerHTML = html || '';
                valActions.innerHTML = '';
                (buttons || [{
                    text: 'Close',
                    class: 'btn btn-primary',
                    onClick: () => closeValidation()
                }]).forEach(btn => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = btn.class || 'btn btn-primary';
                    b.textContent = btn.text;
                    b.addEventListener('click', btn.onClick);
                    valActions.appendChild(b);
                });
                validationModal.style.display = 'flex';
            }

            function closeValidation() {
                validationModal.style.display = 'none';
            }

            window.addEventListener('click', (e) => {
                if (e.target === clientDecisionModal) closeClientDecision();
                if (e.target === validationModal) closeValidation();
            });

            // Helper: show blocks as validation modal
            function showBlock(code, message) {
                const readable = {
                    for_payment: 'Payment Required',
                    pending_new: 'Pending Application',
                    pending_renewal: 'Pending Application',
                    unexpired_permit: 'Unexpired Permit Found',
                    need_released_new: 'Renewal Not Allowed'
                };

                // Special-case: when renewal is blocked because there's no released NEW permit,
                // offer a quick "Request new" action next to Close so the user can switch to filing
                // a new permit without hunting through the form.
                if (code === 'need_released_new') {
                    openValidation({
                        title: readable[code] || 'Validation',
                        html: message || 'To file a renewal, the client must already have a released NEW wildlife permit record.',
                        buttons: [{
                                text: 'Request new',
                                class: 'btn btn-outline',
                                onClick: () => {
                                    closeValidation();
                                    // switch the UI to "new" permit and copy fields where appropriate
                                    try {
                                        if (typeof setPermit === 'function') setPermit('new');
                                    } catch (e) {}
                                    if (typeof autofillRenewalFromNew === 'function') {
                                        // When switching to new, copy any useful data from renewal/new flows
                                        autofillRenewalFromNew();
                                    }
                                    window.scrollTo({
                                        top: 0,
                                        behavior: 'smooth'
                                    });
                                }
                            },
                            {
                                text: 'Close',
                                class: 'btn btn-primary',
                                onClick: closeValidation
                            }
                        ]
                    });
                    return;
                }

                openValidation({
                    title: readable[code] || 'Validation',
                    html: message || 'Please resolve this before continuing.',
                    buttons: [{
                        text: 'Close',
                        class: 'btn btn-primary',
                        onClick: closeValidation
                    }]
                });
            }

            // Helper: suggestion for renewal when NEW is requested but a released NEW already exists (expired)
            function showSuggestRenewal() {
                openValidation({
                    title: 'Suggested: Renewal',
                    html: `We detected an existing (expired) NEW permit record for this client.<br>
           You may file a <b>renewal</b> instead.`,
                    buttons: [{
                            text: 'Request renewal',
                            class: 'btn btn-primary',
                            onClick: () => {
                                closeValidation();
                                setPermit('renewal');
                                if (activePermitType() === 'renewal' && typeof autofillRenewalFromNew === 'function') {
                                    autofillRenewalFromNew();
                                }
                            }
                        },
                        {
                            text: 'Close', // ← changed label
                            class: 'btn btn-outline',
                            onClick: closeValidation
                        }
                    ]

                });
            }

            // ====== PRECHECK & SUBMIT ======

            // --- NEW ➜ RENEWAL autofill ---
            function autofillRenewalFromNew() {
                // 1) Categories
                const copyCheck = (src, dst) => {
                    const s = byId(src),
                        d = byId(dst);
                    if (s && d) d.checked = !!s.checked;
                };
                copyCheck('zoo', 'renewal-zoo');
                copyCheck('botanical-garden', 'renewal-botanical-garden');
                copyCheck('private-collection', 'renewal-private-collection');

                // 2) Applicant & contact fields
                const copyVal = (src, dst) => {
                    const s = byId(src),
                        d = byId(dst);
                    if (s && d) d.value = s.value;
                };
                copyVal('first-name', 'renewal-first-name');
                copyVal('middle-name', 'renewal-middle-name');
                copyVal('last-name', 'renewal-last-name');
                copyVal('residence-address', 'renewal-residence-address');
                copyVal('telephone-number', 'renewal-telephone-number');
                copyVal('establishment-name', 'renewal-establishment-name');
                copyVal('establishment-address', 'renewal-establishment-address');
                copyVal('establishment-telephone', 'renewal-establishment-telephone');
                copyVal('postal-address', 'renewal-postal-address');

                // 3) Animals table (remarks default to "Alive")
                const srcTbody = document.querySelector('#animals-table tbody');
                const dstTbody = document.querySelector('#renewal-animals-table tbody');
                if (srcTbody && dstTbody) {
                    const rows = Array.from(srcTbody.querySelectorAll('tr')).map(tr => {
                        const inputs = tr.querySelectorAll('input');
                        return {
                            common: (inputs[0]?.value || '').trim(),
                            sci: (inputs[1]?.value || '').trim(),
                            qty: (inputs[2]?.value || '').trim()
                        };
                    }).filter(r => r.common || r.sci || r.qty);

                    // Clear existing rows
                    dstTbody.innerHTML = '';

                    const makeErrDiv = () => {
                        const d = document.createElement('div');
                        d.className = 'field-error';
                        d.style.display = 'none';
                        return d;
                    };

                    const addRow = (r = {
                        common: '',
                        sci: '',
                        qty: ''
                    }) => {
                        const tr = document.createElement('tr');

                        const td1 = document.createElement('td');
                        const i1 = document.createElement('input');
                        i1.type = 'text';
                        i1.className = 'table-input';
                        i1.value = r.common || '';
                        td1.appendChild(i1);
                        td1.appendChild(makeErrDiv());

                        const td2 = document.createElement('td');
                        const i2 = document.createElement('input');
                        i2.type = 'text';
                        i2.className = 'table-input';
                        i2.value = r.sci || '';
                        td2.appendChild(i2);
                        td2.appendChild(makeErrDiv());

                        const td3 = document.createElement('td');
                        const i3 = document.createElement('input');
                        i3.type = 'number';
                        i3.className = 'table-input';
                        i3.min = '1';
                        i3.value = r.qty || '';
                        td3.appendChild(i3);
                        td3.appendChild(makeErrDiv());

                        const td4 = document.createElement('td');
                        const sel = document.createElement('select');
                        sel.className = 'table-input';
                        sel.innerHTML = '<option value="Alive" selected>Alive</option><option value="Deceased">Deceased</option>';
                        td4.appendChild(sel);

                        const td5 = document.createElement('td');
                        const rm = document.createElement('button');
                        rm.type = 'button';
                        rm.className = 'remove-row-btn';
                        rm.textContent = 'Remove';
                        rm.addEventListener('click', () => {
                            if (dstTbody.children.length > 1) dstTbody.removeChild(tr);
                            else alert('You must have at least one animal entry.');
                        });
                        td5.appendChild(rm);

                        tr.append(td1, td2, td3, td4, td5);
                        dstTbody.appendChild(tr);
                    };

                    if (rows.length) rows.forEach(addRow);
                    else addRow(); // keep one empty row if nothing to copy
                }

                // 4) Keep renewal signature blank when copying details
                try {
                    if (typeof clearSigPad === 'function') {
                        clearSigPad(true);
                    } else {
                        const img = byId('renewal-signature-image');
                        if (img) {
                            img.src = '';
                            img.classList.add('hidden');
                        }
                        if (typeof sigRen === 'object') {
                            sigRen.has = false;
                            sigRen.dataURL = '';
                        }
                    }
                } catch {}

                // 5) Clear old validation in Renewal
                document.querySelectorAll('#renewal-upper-block .field-error').forEach(el => el.style.display = 'none');
                document.querySelectorAll('#renewal-upper-block .invalid').forEach(i => {
                    i.classList.remove('invalid');
                    i.removeAttribute('aria-invalid');
                });
                byId('renewal-first-name')?.focus();
            }

            // make available to click handlers
            window.autofillRenewalFromNew = autofillRenewalFromNew;

            const btnSubmit = byId('submitApplication');
            btnSubmit?.addEventListener('click', async (ev) => {
                ev.preventDefault();
                ev.stopPropagation();
                ev.stopImmediatePropagation();

                const type = activePermitType(); // 'new' | 'renewal'
                const first = type === 'renewal' ? v('renewal-first-name') : v('first-name');
                const middle = type === 'renewal' ? v('renewal-middle-name') : v('middle-name');
                const last = type === 'renewal' ? v('renewal-last-name') : v('last-name');
                const clientMode = (clientModeEl?.value || 'new').toLowerCase();
                const usingExistingPick = type === 'new' && clientMode === 'existing';

                // 0) VALIDATE FIRST — if invalid, stop here (no precheck, no modal)
                if (typeof window.__validateWildlifeForm === 'function') {
                    const ok = window.__validateWildlifeForm();
                    if (!ok) {
                        if (typeof window.__scrollFirstErrorIntoView === 'function') {
                            window.__scrollFirstErrorIntoView();
                        }
                        toast('Please complete the required fields first.');
                        return;
                    }
                }

                if (usingExistingPick && !(clientPick?.value)) {
                    setClientPickError('Please select an existing client.');
                    toast('Please select an existing client.');
                    return;
                }

                if (usingExistingPick) {
                    chosenClientId = clientPick.value;
                    window.__FORCE_NEW_CLIENT__ = false;
                    precheckCache = null;
                    await finalSubmit();
                    return;
                }

                try {
                    showLoading('Checking records…');

                    // 1) PRECHECK (only after validation passes)
                    const fd = new FormData();
                    fd.append('first_name', first);
                    fd.append('middle_name', middle);
                    fd.append('last_name', last);
                    fd.append('desired_permit_type', type);

                    const res = await fetch(PRECHECK_URL, {
                        method: 'POST',
                        body: fd,
                        credentials: 'include'
                    });
                    const json = await res.json();
                    if (!res.ok || !json.ok) throw new Error(json.message || 'Precheck failed');
                    precheckCache = json;

                    // 2) HANDLE PRECHECK RESULT
                    if (json.decision === 'existing' && json.client && json.client.client_id) {
                        suggestedClient = json.client;
                        const full = json.client.full_name || 'Existing client';
                        const f = json.flags || {};

                        if (json.block) {
                            showBlock(json.block, json.message || 'You already have a pending wildlife application. Please wait for the update first.');
                            return;
                        }

                        // Non-blocking suggestion: user filed NEW but an expired released NEW exists => suggest renewal
                        // if (json.suggest === 'renewal' && type === 'new') {
                        //     showSuggestRenewal();
                        // }

                        // Ask for confirmation to use the detected client
                        openClientDecision({
                            title: 'Is this the correct client?',
                            html: `We detected an existing client:<div style="margin:8px 0;font-weight:600">${full}</div>`,
                            buttons: [{
                                    text: 'No, cancel',
                                    class: 'btn btn-outline',
                                    onClick: () => {
                                        closeClientDecision();
                                    }
                                },
                                {
                                    text: 'Submit as new',
                                    class: 'btn btn-outline',
                                    onClick: async () => {
                                        // When user chooses to "Submit as new" while filing a renewal,
                                        // ensure we do not bypass the released-NEW requirement.
                                        // `type` and `f` are available in the outer scope of this handler.
                                        window.__FORCE_NEW_CLIENT__ = true;
                                        chosenClientId = null;
                                        // If this is a renewal attempt and the detected client lacks a released NEW,
                                        // show the same validation modal as the 'Yes, submit' path.
                                        try {
                                            if (type === 'renewal') {
                                                const flags = (f || {});
                                                if (!flags.has_released_new) {
                                                    closeClientDecision();
                                                    showBlock('need_released_new', 'To file a renewal, the client must already have a released NEW wildlife permit record.');
                                                    return;
                                                }
                                            }
                                        } catch (err) {
                                            // if anything unexpected happens, fall back to safe behavior
                                            console.error(err);
                                            closeClientDecision();
                                            showBlock('need_released_new', 'To file a renewal, the client must already have a released NEW wildlife permit record.');
                                            return;
                                        }

                                        closeClientDecision();
                                        await finalSubmit();
                                    }
                                },
                                {
                                    text: 'Yes, submit',
                                    class: 'btn btn-primary',
                                    onClick: async () => {
                                        // Use the existing client the user confirmed
                                        window.__FORCE_NEW_CLIENT__ = false;
                                        chosenClientId = String(json.client.client_id);

                                        // Block: FOR PAYMENT (applies to both new and renewal)
                                        if (f.has_for_payment) {
                                            closeClientDecision();
                                            showBlock('for_payment', 'You still have an unpaid wildlife permit on record. Please settle this personally at the office.');
                                            return;
                                        }

                                        if (type === 'new') {
                                            // Block: unexpired permit
                                            if (f.has_unexpired) {
                                                closeClientDecision();
                                                showBlock('unexpired_permit', 'You still have an unexpired wildlife permit. You cannot file a new application.');
                                                return;
                                            }
                                            // Suggest renewal if applicable (non-blocking), but stop auto-submitting
                                            if (json.suggest === 'renewal') {
                                                closeClientDecision();
                                                showSuggestRenewal();
                                                return;
                                            }
                                            // Block: any pending (new/renewal)
                                            if (f.has_pending_new) {
                                                closeClientDecision();
                                                showBlock('pending_new', 'You already have a pending NEW wildlife application.');
                                                return;
                                            }
                                            if (f.has_pending_renewal) {
                                                closeClientDecision();
                                                showBlock('pending_renewal', 'You have a pending RENEWAL; please wait for the update first.');
                                                return;
                                            }
                                        } else {
                                            // RENEWAL: block on ANY pending (new OR renewal)
                                            if (f.has_pending_renewal || f.has_pending_new) {
                                                closeClientDecision();
                                                showBlock('pending_renewal', 'You already have a pending wildlife application. Please wait for the update first.');
                                                return;
                                            }
                                            // Require that client has a released NEW wildlife permit to allow renewal
                                            if (!f.has_released_new) {
                                                closeClientDecision();
                                                showBlock('need_released_new', 'To file a renewal, the client must already have a released NEW wildlife permit record.');
                                                return;
                                            }
                                            // Block: unexpired permit
                                            if (f.has_unexpired) {
                                                closeClientDecision();
                                                showBlock('unexpired_permit', 'You still have an unexpired wildlife permit. Please wait until it expires to renew.');
                                                return;
                                            }
                                        }

                                        // Passed all checks — proceed
                                        closeClientDecision();
                                        await finalSubmit();
                                    }
                                }
                            ]
                        });
                        return; // wait for user action in the modal
                    }

                    // No existing client found
                    if (json.decision === 'none') {
                        if (type === 'renewal') {
                            openClientDecision({
                                title: 'No Client Detected',
                                html: 'No client matched these details. Do you want to request a new permit instead, or continue submitting this renewal as a new client?',
                                buttons: [{
                                        text: 'Cancel',
                                        class: 'btn btn-outline',
                                        onClick: () => {
                                            closeClientDecision();
                                        }
                                    },
                                    {
                                        text: 'Request new',
                                        class: 'btn btn-outline',
                                        onClick: () => {
                                            closeClientDecision();
                                            setPermit('new');
                                            if (typeof autofillRenewalFromNew === 'function') {
                                                autofillRenewalFromNew();
                                            }
                                            window.scrollTo({
                                                top: 0,
                                                behavior: 'smooth'
                                            });
                                        }
                                    },
                                    {
                                        text: 'Continue renewal',
                                        class: 'btn btn-primary',
                                        onClick: () => {
                                            // Do not allow continuing a renewal when no existing client record with a released NEW permit exists
                                            closeClientDecision();
                                            showBlock('need_released_new', 'To file a renewal, the client must already have a released NEW wildlife permit record.');
                                        }
                                    }
                                ]
                            });
                        } else {
                            openClientDecision({
                                title: 'Submit as New Client?',
                                html: 'No existing client was detected for these details. Submit as a new client?',
                                buttons: [{
                                        text: 'Cancel',
                                        class: 'btn btn-outline',
                                        onClick: () => {
                                            closeClientDecision();
                                        }
                                    },
                                    {
                                        text: 'Submit',
                                        class: 'btn btn-primary',
                                        onClick: async () => {
                                            window.__FORCE_NEW_CLIENT__ = true;
                                            chosenClientId = null;
                                            closeClientDecision();
                                            await finalSubmit();
                                        }
                                    }
                                ]
                            });
                        }
                        return;
                    }

                    // Rare fallback: server signaled a block outside "existing" path
                    if (json.block) {
                        showBlock(json.block, json.message);
                        return;
                    }

                    // Default: proceed
                    await finalSubmit();
                } catch (e) {
                    console.error(e);
                    toast(e?.message || 'Something went wrong.');
                } finally {
                    hideLoading();
                }
            }, true);



            // final submit (no confirm modal; toasts only for success/fail)
            async function finalSubmit() {
                // Validate fields AFTER precheck but BEFORE sending
                if (typeof window.__validateWildlifeForm === 'function') {
                    const ok = window.__validateWildlifeForm();
                    if (!ok) {
                        if (typeof window.__scrollFirstErrorIntoView === 'function') {
                            window.__scrollFirstErrorIntoView();
                        }
                        toast('Please fix the highlighted fields.');
                        return; // don’t submit
                    }
                }

                if (precheckCache && precheckCache.block) {
                    showBlock(precheckCache.block, precheckCache.message || 'You already have a pending wildlife application. Please wait for the update first.');
                    return;
                }

                showLoading('Submitting application...');
                try {
                    window.__USE_EXISTING_CLIENT_ID__ = chosenClientId ? String(chosenClientId) : null;
                    await doSubmit();
                    toast("Application submitted. We'll notify you once reviewed.");
                    resetForm();
                } catch (e) {
                    console.error(e);
                    toast(e?.message || 'Submission failed. Please try again.');
                } finally {
                    hideLoading();
                }
            }




            // ====== APP DOC GENERATION + SAVE ======
            async function doSubmit() {
                const type = activePermitType();
                // Collect NEW / RENEWAL specifics
                let firstName, middleName, lastName,
                    residenceAddress, telephoneNumber, establishmentName, establishmentAddress, establishmentTelephone, postalAddress;

                // Checkboxes (categories)
                let zoo = false,
                    botanical = false,
                    privateColl = false;

                // Animals
                let animals = [];

                // Renewal-only
                let wfpNumber = '',
                    issueDate = '';

                // Signatures
                let sigDataURL = '';

                if (type === 'new') {
                    firstName = v('first-name');
                    middleName = v('middle-name');
                    lastName = v('last-name');
                    residenceAddress = v('residence-address');
                    telephoneNumber = v('telephone-number');
                    establishmentName = v('establishment-name');
                    establishmentAddress = v('establishment-address');
                    establishmentTelephone = v('establishment-telephone');
                    postalAddress = v('postal-address');

                    zoo = !!byId('zoo')?.checked;
                    botanical = !!byId('botanical-garden')?.checked;
                    privateColl = !!byId('private-collection')?.checked;

                    sigDataURL = sigNew.dataURL || (sigNew.has ? byId('signature-pad')?.toDataURL?.('image/png') : '');

                    // animals rows
                    const rows = document.querySelectorAll('#animals-table tbody tr');
                    rows.forEach(row => {
                        const inputs = row.querySelectorAll('input');
                        const commonName = inputs[0]?.value || '';
                        const scientificName = inputs[1]?.value || '';
                        const quantity = inputs[2]?.value || '';
                        if (commonName || scientificName || quantity) {
                            animals.push({
                                commonName,
                                scientificName,
                                quantity
                            });
                        }
                    });
                } else {
                    firstName = v('renewal-first-name');
                    middleName = v('renewal-middle-name');
                    lastName = v('renewal-last-name');
                    residenceAddress = v('renewal-residence-address');
                    telephoneNumber = v('renewal-telephone-number');
                    establishmentName = v('renewal-establishment-name');
                    establishmentAddress = v('renewal-establishment-address');
                    establishmentTelephone = v('renewal-establishment-telephone');
                    postalAddress = v('renewal-postal-address');

                    wfpNumber = v('renewal-wfp-number');
                    issueDate = v('renewal-issue-date');

                    zoo = !!byId('renewal-zoo')?.checked;
                    botanical = !!byId('renewal-botanical-garden')?.checked;
                    privateColl = !!byId('renewal-private-collection')?.checked;

                    sigDataURL = sigRen.dataURL || (sigRen.has ? byId('renewal-signature-pad')?.toDataURL?.('image/png') : '');

                    const rows = document.querySelectorAll('#renewal-animals-table tbody tr');
                    rows.forEach(row => {
                        const inputs = row.querySelectorAll('input, select');
                        const commonName = inputs[0]?.value || '';
                        const scientificName = inputs[1]?.value || '';
                        const quantity = inputs[2]?.value || '';
                        const remarks = inputs[3]?.value || '';
                        if (commonName || scientificName || quantity || remarks) {
                            animals.push({
                                commonName,
                                scientificName,
                                quantity,
                                remarks
                            });
                        }
                    });
                }

                const docFirstName = titleCase(firstName);
                const docMiddleName = titleCase(middleName);
                const docLastName = titleCase(lastName);
                const docResidenceAddress = titleCase(residenceAddress);
                const docEstablishmentName = titleCase(establishmentName);
                const docEstablishmentAddress = titleCase(establishmentAddress);
                const docPostalAddress = titleCase(postalAddress);
                const fullName = [docFirstName, docMiddleName, docLastName].filter(Boolean).join(' ');
                // Use Unicode checkbox characters so generated .doc renders marks
                const check = (b) => b ? '☑' : '☐';

                // Build HTML content for the application (New vs Renewal)
                const isRenewal = type === 'renewal';
                const headerHtml = `
      <div style="text-align:center;margin-bottom:20px;">
        <p style="font-weight:bold;">Republic of the Philippines</p>
        <p style="font-weight:bold;">Department of Environment and Natural Resources</p>
        <p style="font-weight:bold;">REGION 7</p>
        <p>______</p>
        <p>Date</p>
      </div>
    `;

                const animalsTableRows = animals.length ?
                    animals.map(a => `
          <tr>
            <td>${a.commonName || ''}</td>
            <td>${a.scientificName || ''}</td>
            <td>${a.quantity || ''}</td>
            ${isRenewal ? `<td>${a.remarks || ''}</td>` : ''}
          </tr>`).join('') :
                    (isRenewal ?
                        `<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
             <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>` :
                        `<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
             <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>`);

                const sigLocation = 'signature.png';
                const hasSignature = !!sigDataURL;

                const docHtml = `
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:w="urn:schemas-microsoft-com:office:word"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="UTF-8">
<title>${isRenewal ? 'Wildlife Registration Renewal Application' : 'Wildlife Registration Application'}</title>
<style>
    body, div, p { line-height:1.6; font-family:Arial; font-size:11pt; margin:0; padding:0; }
    .bold{ font-weight:bold; }
    .checkbox{ font-family: 'Segoe UI Symbol', 'Arial Unicode MS', 'DejaVu Sans', Arial, sans-serif; font-size:14pt; vertical-align:middle; }
    .underline{ display:inline-block; border-bottom:1px solid #000; min-width:260px; padding:0 5px; margin:0 5px; }
    .underline-small{ display:inline-block; border-bottom:1px solid #000; min-width:150px; padding:0 5px; margin:0 5px; }
    .indent{ margin-left:40px; }
    .info-line{ margin:12pt 0; }
</style>
</head>
<body>
${headerHtml}

<p style="text-align:center;margin-bottom:20px;" class="bold">
  ${isRenewal ? 'APPLICATION FOR: RENEWAL CERTIFICATE OF WILDLIFE REGISTRATION'
              : 'APPLICATION FOR: CERTIFICATE OF WILDLIFE REGISTRATION'}
</p>

<p style="margin-bottom:15px;">
  <span class="checkbox">${check(zoo)}</span> Zoo
  <span class="checkbox">${check(botanical)}</span> Botanical Garden
  <span class="checkbox">${check(privateColl)}</span> Private Collection
</p>

<p class="info-line">The Regional Executive Director</p>
<p class="info-line">DENR Region 7</p>
<p class="info-line">National Government Center,</p>
<p class="info-line">Sudion, Lahug, Cebu City</p>

<p class="info-line">(Submit in Duplicate)</p>
<p class="info-line">Sir/Madam:</p>

${
  isRenewal
  ? `
    <p class="info-line">I, <span class="underline">${fullName}</span> with address at <span class="underline">${docResidenceAddress}</span></p>
    <p class="info-line indent">and Tel. no. <span class="underline-small">${telephoneNumber}</span>, have the honor to request for the</p>
    <p class="info-line indent">renewal of my Certificate of Wildlife Registration of <span class="underline">${docEstablishmentName}</span></p>
    <p class="info-line indent">located at <span class="underline">${docEstablishmentAddress}</span> with Tel. no. <span class="underline-small">${establishmentTelephone}</span></p>
    <p class="info-line indent">and Original WFP No. <span class="underline-small">${wfpNumber}</span> issued on <span class="underline-small">${issueDate}</span>, and</p>
    <p class="info-line">registration of animals/stocks maintained which are as follows:</p>
  `
  : `
    <p class="info-line">I <span class="underline">${fullName}</span> with address at <span class="underline">${docResidenceAddress}</span></p>
    <p class="info-line indent">and Tel. no. <span class="underline-small">${telephoneNumber}</span> have the honor to apply for the registration of <span class="underline">${docEstablishmentName}</span></p>
    <p class="info-line indent">located at <span class="underline">${docEstablishmentAddress}</span> with Tel. no. <span class="underline-small">${establishmentTelephone}</span> and registration of animals/stocks maintained</p>
    <p class="info-line">there at which are as follows:</p>
  `
}

<table style="width:100%; border-collapse:collapse; margin:15pt 0;">
    <tr>
        <th style="border:1px solid #000; padding:8px; text-align:left;">Common Name</th>
        <th style="border:1px solid #000; padding:8px; text-align:left;">Scientific Name</th>
        <th style="border:1px solid #000; padding:8px; text-align:left;">Quantity</th>
        ${isRenewal ? '<th style="border:1px solid #000; padding:8px; text-align:left;">Remarks (Alive/Deceased)</th>' : ''}
    </tr>
    ${animalsTableRows.replace(/<td>/g, '<td style="border:1px solid #000; padding:8px; text-align:left;">')}
</table>

<p class="info-line">
  ${
    isRenewal
      ? 'I understand that this application for renewal does not by itself grant the right to continue possession of any wild animals until the corresponding Renewal Certificate of Registration is issued.'
      : 'I understand that the filling of this application conveys no right to possess any wild animals until Certificate of Registration is issued to me by the Regional Director of the DENR Region 7.'
  }
</p>

<div style="margin-top:28px; text-align:left;">
    ${
        hasSignature
            ? `<img src="${sigLocation}" alt="Signature" style="display:block; margin:12px 0 0 0; max-width:100px; max-height:30px; border:none;" />`
            : `<div style="margin-top:40px;border-top:1px solid #000;width:100px;padding-top:3pt;"></div>`
    }
    <p style="margin-top:4px;">Signature of Applicant</p>
</div>

<p class="info-line">Postal Address: <span class="underline">${docPostalAddress}</span></p>

</body>
</html>
`.trim();

                const parts = hasSignature ? [{
                    location: sigLocation,
                    contentType: 'image/png',
                    base64: (sigDataURL.split(',')[1] || '')
                }] : [];

                const mhtml = makeMHTML(docHtml, parts);
                const docBlob = new Blob([mhtml], {
                    type: 'application/msword'
                });
                const docName = `${isRenewal ? 'Wildlife_Renewal' : 'Wildlife_New'}_${(fullName || 'Applicant').replace(/\s+/g, '_')}.doc`;
                const docFile = new File([docBlob], docName, {
                    type: 'application/msword'
                });

                // Build FormData for backend
                const fd = new FormData();
                fd.append('permit_type', isRenewal ? 'renewal' : 'new');
                const selectedId = window.__USE_EXISTING_CLIENT_ID__ || chosenClientId;
                if (selectedId) {
                    fd.append('use_existing_client_id', String(selectedId));
                }
                if (window.__FORCE_NEW_CLIENT__) {
                    fd.append('force_new_client', '1');
                }

                // Identity / contact
                fd.append('first_name', firstName);
                fd.append('middle_name', middleName);
                fd.append('last_name', lastName);
                fd.append('residence_address', residenceAddress);
                fd.append('telephone_number', telephoneNumber);
                fd.append('establishment_name', establishmentName);
                fd.append('establishment_address', establishmentAddress);
                fd.append('establishment_telephone', establishmentTelephone);
                fd.append('postal_address', postalAddress);

                // Categories
                fd.append('zoo', String(zoo ? 1 : 0));
                fd.append('botanical_garden', String(botanical ? 1 : 0));
                fd.append('private_collection', String(privateColl ? 1 : 0));

                // Renewal-only fields
                if (isRenewal) {
                    fd.append('wfp_number', wfpNumber);
                    fd.append('issue_date', issueDate);
                    const pendingFetches = Object.values(existingFileFetches).filter(Boolean);
                    if (pendingFetches.length) {
                        try {
                            await Promise.allSettled(pendingFetches);
                        } catch (_) {}
                    }
                    fd.append('existing_file_urls', JSON.stringify(existingFileUrls));
                }

                // Animals JSON
                fd.append(isRenewal ? 'renewal_animals_json' : 'animals_json', JSON.stringify(animals || []));

                // Generated application document + signature
                fd.append('application_doc', docFile);
                if (hasSignature) {
                    const sigBlob = dataURLToBlob(sigDataURL);
                    fd.append('signature_file', new File([sigBlob], 'signature.png', {
                        type: 'image/png'
                    }));
                }

                // ---- Attach FILES (only if present) ----
                // NEW files
                if (!isRenewal) {
                    [
                        'file-2', 'file-3', 'file-4', 'file-5', 'file-6', 'file-7', 'file-8a', 'file-8b', 'file-9'
                    ].forEach((id) => {
                        const f = byId(id)?.files?.[0];
                        if (f) fd.append(id.replace(/-/g, '_'), f); // e.g., file_1, file_8a
                    });
                } else {
                    [
                        'renewal-file-2', 'renewal-file-3',
                        'renewal-file-4a', 'renewal-file-4b', 'renewal-file-4c', 'renewal-file-4d',
                        'renewal-file-5a', 'renewal-file-5b', 'renewal-file-5c',
                        'renewal-file-6'
                    ].forEach((id) => {
                        const el = byId(id);
                        const f = el?.files?.[0];
                        const cached = existingFileCache[id];
                        const cleared = el?.dataset?.userCleared === '1';

                        if (f) {
                            fd.append(id.replace(/-/g, '_'), f);
                        } else if (cached && !cleared) {
                            fd.append(id.replace(/-/g, '_'), cached);
                        }
                    });

                }

                // ---- SEND ----
                const res = await fetch(SAVE_URL, {
                    method: 'POST',
                    body: fd,
                    credentials: 'include'
                });
                let json;
                try {
                    json = await res.json();
                } catch {
                    const text = await res.text();
                    throw new Error(`HTTP ${res.status} – ${text.slice(0, 200)}`);
                }
                if (!res.ok || !json.ok) throw new Error(json.error || `HTTP ${res.status}`);
            }

            // ====== FILE PREVIEW (optional; kept) ======
            const previewModal = byId('filePreviewModal');
            const modalFrame = byId('filePreviewFrame');
            const closePreview = byId('closeFilePreviewModal');

            function previewFile(file) {
                if (!modalFrame || !previewModal) return;
                modalFrame.removeAttribute('src');
                modalFrame.removeAttribute('srcdoc');
                const reader = new FileReader();
                reader.onload = function(e) {
                    const dataUrl = e.target?.result;
                    if (file.type.startsWith('image/')) {
                        modalFrame.srcdoc = `<img src='${dataUrl}' style='max-width:100%;max-height:80vh;'>`;
                    } else if (file.type === 'application/pdf') {
                        modalFrame.src = String(dataUrl);
                    } else {
                        const url = URL.createObjectURL(file);
                        modalFrame.srcdoc = `<div style='padding:20px;text-align:center;'>
          Cannot preview this file type.<br>
          <a href='${url}' download='${file.name}' style='color:#2b6625;font-weight:bold;'>Download ${file.name}</a>
        </div>`;
                    }
                    previewModal.style.display = 'block';
                };
                if (file.type.startsWith('image/') || file.type === 'application/pdf') reader.readAsDataURL(file);
                else reader.onload();
            }
            closePreview?.addEventListener('click', () => previewModal.style.display = 'none');
            window.addEventListener('click', (e) => {
                if (e.target === previewModal) previewModal.style.display = 'none';
            });

        })();
    </script>
    <script>
        (() => {
            const $ = (id) => document.getElementById(id);
            const activeType = () => (document.querySelector('.permit-type-btn.active')?.dataset.type || 'new');
            const clientModeEl = $('clientMode');
            const clientPickEl = $('clientPick');
            const clientPickError = $('clientPickError');

            // ---------- error helpers ----------
            function errElFor(input) {
                if (!input) return null;
                let n = input.nextElementSibling;
                if (!n || !n.classList || !n.classList.contains('field-error')) {
                    n = document.createElement('div');
                    n.className = 'field-error';
                    n.style.cssText = 'color:#d22;font-size:12px;margin-top:4px;display:none;';
                    input.insertAdjacentElement('afterend', n);
                }
                return n;
            }

            function setErr(input, msg) {
                const el = errElFor(input);
                if (el) {
                    el.textContent = msg || 'Invalid value.';
                    el.style.display = 'block';
                }
                if (input) {
                    input.classList.add('invalid');
                    input.setAttribute('aria-invalid', 'true');
                    // no border styling
                }
                return false;
            }

            function clearErr(input) {
                const el = input?.nextElementSibling;
                if (el?.classList?.contains('field-error')) el.style.display = 'none';
                if (input) {
                    input.classList.remove('invalid');
                    input.removeAttribute('aria-invalid');
                    // no border styling
                }
                return true;
            }


            function setGroupErr(container, msg) {
                if (!container) return;
                let n = container.querySelector('.field-error.group');
                if (!n) {
                    n = document.createElement('div');
                    n.className = 'field-error group';
                    n.style.cssText = 'color:#d22;font-size:12px;margin-top:6px;display:none;';
                    container.appendChild(n);
                }
                n.textContent = msg;
                n.style.display = 'block';
            }

            function clearGroupErr(container) {
                const n = container?.querySelector('.field-error.group');
                if (n) n.style.display = 'none';
            }

            function clearAllErrors(scope = document) {
                scope.querySelectorAll('.field-error').forEach(d => d.style.display = 'none');
                scope.querySelectorAll('.invalid').forEach(i => {
                    i.classList.remove('invalid');
                    i.style.borderColor = i.dataset.__origBorder || '';
                });
            }

            // ---------- patterns ----------
            const personNameRe = /^[A-Za-zÀ-ÖØ-öø-ÿ\s.'-]{1,60}$/; // for first/last names
            const alphaOnlyRe = /^[A-Za-zÀ-ÖØ-öø-ÿ\s.'()\-]{2,100}$/; // for common/scientific names (no digits)
            const phoneRe = /^[0-9+()\-\s]{6,20}$/;
            const addressBadCharsRe = /[^A-Za-z0-9À-ÖØ-öø-ÿ\s.,#\/\-()]/;

            function isFileSatisfied(inputEl) {
                if (!inputEl) return true;

                // a) user picked a new file
                if (inputEl.files && inputEl.files.length > 0) return true;

                // b) if user explicitly cleared, it must be treated as NOT satisfied
                if (inputEl.dataset && inputEl.dataset.userCleared === '1') return false;

                // c) otherwise, preloaded (anchor) counts as satisfied
                const wrap = inputEl.closest('.file-upload');
                if (wrap && wrap.querySelector('.uploaded-files a')) return true;

                return false;
            }


            function validateFileUploads(listId) {
                const scope = document.getElementById(listId);
                if (!scope) return true;

                let ok = true;
                const inputs = scope.querySelectorAll('.file-upload input[type="file"].file-input');
                inputs.forEach((inp) => {
                    if (isFileSatisfied(inp)) {
                        clearErr(inp); // uses your existing clearErr()
                    } else {
                        ok = setErr(inp, null, 'Please attach a file.') && ok; // uses your existing setErr()
                    }
                });
                return !!ok;
            }

            // live cleanup when user chooses a file
            document.addEventListener('change', (e) => {
                const t = e.target;
                if (t && t.tagName === 'INPUT' && t.type === 'file') {
                    if (isFileSatisfied(t)) clearErr(t);
                    else setErr(t, null, 'Please attach a file.');
                }
            }, true);

            function validateAddress(id, label, required = false) {
                const el = $(id);
                if (!el) return true;
                const val = (el.value || '').trim();

                if (!val) {
                    if (required) return setErr(el, `${label} is required.`);
                    return clearErr(el);
                }
                if (val.length < 5) return setErr(el, `${label} must be at least 5 characters.`);
                if (addressBadCharsRe.test(val)) {
                    return setErr(el, `Use letters, numbers, spaces, and . , - # / ( ) only.`);
                }
                return clearErr(el);
            }

            function validateClientPick() {
                if (!clientPickEl) return true;
                const type = activeType();
                const mode = (clientModeEl?.value || 'new').toLowerCase();
                if (type !== 'new' || mode !== 'existing') {
                    clientPickEl.classList.remove('invalid');
                    clientPickEl.removeAttribute('aria-invalid');
                    if (clientPickError) clientPickError.style.display = 'none';
                    return true;
                }
                if (!clientPickEl.value) {
                    clientPickEl.classList.add('invalid');
                    clientPickEl.setAttribute('aria-invalid', 'true');
                    if (clientPickError) {
                        clientPickError.textContent = 'Please select an existing client.';
                        clientPickError.style.display = 'block';
                    }
                    return false;
                }
                clientPickEl.classList.remove('invalid');
                clientPickEl.removeAttribute('aria-invalid');
                if (clientPickError) clientPickError.style.display = 'none';
                return true;
            }
            clientPickEl?.addEventListener('change', validateClientPick);


            // ---------- field validators ----------
            function requireName(id, label) {
                const el = $(id);
                if (!el) return true;
                const v = (el.value || '').trim();
                if (!v) {
                    setErr(el, `${label} is required.`);
                    return false;
                }
                if (!personNameRe.test(v)) {
                    setErr(el, `Use letters/spaces/.’- only (max 60).`);
                    return false;
                }
                clearErr(el);
                return true;
            }

            function requireText(id, label, min = 1, max = 200) {
                const el = $(id);
                if (!el) return true;
                const v = (el.value || '').trim();
                if (!v) {
                    setErr(el, `${label} is required.`);
                    return false;
                }
                if (v.length < min) {
                    setErr(el, `${label} must be at least ${min} characters.`);
                    return false;
                }
                if (v.length > max) {
                    setErr(el, `${label} is too long (max ${max}).`);
                    return false;
                }
                clearErr(el);
                return true;
            }

            function optionalText(id, label, max = 200) {
                const el = $(id);
                if (!el) return true;
                const v = (el.value || '').trim();
                if (!v) {
                    clearErr(el);
                    return true;
                }
                if (v.length > max) {
                    setErr(el, `${label} is too long (max ${max}).`);
                    return false;
                }
                clearErr(el);
                return true;
            }

            function optionalPhone(id, label) {
                const el = $(id);
                if (!el) return true;
                const v = (el.value || '').trim();
                if (!v) {
                    clearErr(el);
                    return true;
                }
                if (!phoneRe.test(v)) {
                    setErr(el, `Use digits and + ( ) - (6–20 chars).`);
                    return false;
                }
                clearErr(el);
                return true;
            }

            function requireDate(id, label) {
                const el = $(id);
                if (!el) return true;
                const v = (el.value || '').trim();
                if (!v) {
                    setErr(el, `${label} is required.`);
                    return false;
                }
                clearErr(el);
                return true;
            }

            // ---------- checkbox group (must choose at least one) ----------
            function validateCategories(isRenewal) {
                const ids = isRenewal ? ['renewal-zoo', 'renewal-botanical-garden', 'renewal-private-collection'] : ['zoo', 'botanical-garden', 'private-collection'];
                const group = document.querySelector(isRenewal ? '#renewal-upper-block .checkbox-group' : '#new-upper-block .checkbox-group');
                const anyChecked = ids.some(id => $(id)?.checked);
                if (!anyChecked) {
                    setGroupErr(group, 'Select at least one category.');
                    return false;
                }
                clearGroupErr(group);
                return true;
            }
            // Clear group error when any box toggles
            [
                ['zoo', 'botanical-garden', 'private-collection', '#new-upper-block .checkbox-group'],
                ['renewal-zoo', 'renewal-botanical-garden', 'renewal-private-collection', '#renewal-upper-block .checkbox-group']
            ]
            .forEach(([a, b, c, sel]) => {
                [$(a), $(b), $(c)].forEach(cb => cb && cb.addEventListener('change', () => clearGroupErr(document.querySelector(sel))));
            });

            // ---------- table validators ----------
            function validateAnimalsNew() {
                const tbody = document.querySelector('#animals-table tbody');
                if (!tbody) return true;
                let ok = true;
                const rows = Array.from(tbody.querySelectorAll('tr'));
                if (!rows.length) return true;

                rows.forEach((tr, idx) => {
                    const inputs = tr.querySelectorAll('input');
                    const common = inputs[0],
                        sci = inputs[1],
                        qty = inputs[2];
                    const hasAny = [common, sci, qty].some(i => (i?.value || '').trim() !== '');

                    function checkName(el, label) {
                        const s = (el?.value || '').trim();
                        if (!s) {
                            setErr(el, 'Required.');
                            return false;
                        }
                        if (!alphaOnlyRe.test(s)) {
                            setErr(el, `${label}: letters/spaces/.’()- only.`);
                            return false;
                        }
                        clearErr(el);
                        return true;
                    }

                    function checkQty(el) {
                        const n = Number((el?.value || '').trim());
                        if (!Number.isFinite(n) || n < 1) {
                            setErr(el, 'Quantity must be ≥ 1.');
                            return false;
                        }
                        clearErr(el);
                        return true;
                    }

                    if (idx === 0) {
                        ok &= checkName(common, 'Common Name');
                        ok &= checkName(sci, 'Scientific Name');
                        ok &= checkQty(qty);
                        return;
                    }

                    if (hasAny) {
                        ok &= checkName(common, 'Common Name');
                        ok &= checkName(sci, 'Scientific Name');
                        ok &= checkQty(qty);
                    } else {
                        [common, sci, qty].forEach(clearErr);
                    }
                });

                return !!ok;
            }

            function validateAnimalsRenewal() {
                const tbody = document.querySelector('#renewal-animals-table tbody');
                if (!tbody) return true;
                let ok = true;
                const rows = Array.from(tbody.querySelectorAll('tr'));
                if (!rows.length) return true;

                rows.forEach((tr, idx) => {
                    const cells = tr.querySelectorAll('input, select');
                    const common = cells[0],
                        sci = cells[1],
                        qty = cells[2];
                    const hasAny = [common, sci, qty].some(i => (i?.value || '').trim() !== '');

                    const checkName = (el, label) => {
                        const s = (el?.value || '').trim();
                        if (!s) {
                            setErr(el, 'Required.');
                            return false;
                        }
                        if (!alphaOnlyRe.test(s)) {
                            setErr(el, `${label}: letters/spaces/.’()- only.`);
                            return false;
                        }
                        clearErr(el);
                        return true;
                    };
                    const checkQty = (el) => {
                        const n = Number((el?.value || '').trim());
                        if (!Number.isFinite(n) || n < 1) {
                            setErr(el, 'Quantity must be ≥ 1.');
                            return false;
                        }
                        clearErr(el);
                        return true;
                    };

                    if (idx === 0) {
                        ok &= checkName(common, 'Common Name');
                        ok &= checkName(sci, 'Scientific Name');
                        ok &= checkQty(qty);
                        return;
                    }

                    if (hasAny) {
                        ok &= checkName(common, 'Common Name');
                        ok &= checkName(sci, 'Scientific Name');
                        ok &= checkQty(qty);
                    } else {
                        [common, sci, qty].forEach(clearErr);
                    }
                });

                return !!ok;
            }

            // ---------- overall validation ----------
            function validateAll() {
                let ok = true;
                const type = activeType();

                if (type === 'new') {
                    ok &= validateClientPick();
                    ok &= requireName('first-name', 'First Name');
                    ok &= requireName('last-name', 'Last Name');
                    ok &= requireText('residence-address', 'Residence Address', 5);
                    ok &= optionalPhone('telephone-number', 'Telephone Number');
                    ok &= requireText('establishment-name', 'Name of Establishment', 5);
                    ok &= requireText('establishment-address', 'Address of Establishment', 5);
                    ok &= optionalPhone('establishment-telephone', 'Establishment Telephone Number');
                    ok &= validateAddress('postal-address', 'Postal Address', false);
                    ok &= validateCategories(false);
                    ok &= validateAnimalsNew();
                } else {
                    ok &= requireName('renewal-first-name', 'First Name');
                    ok &= requireName('renewal-last-name', 'Last Name');
                    ok &= requireText('renewal-residence-address', 'Residence Address', 5);
                    ok &= optionalPhone('renewal-telephone-number', 'Telephone Number');
                    ok &= requireText('renewal-establishment-name', 'Name of Establishment', 5);
                    ok &= requireText('renewal-establishment-address', 'Address of Establishment', 5);
                    ok &= optionalPhone('renewal-establishment-telephone', 'Establishment Telephone Number');
                    ok &= requireText('renewal-wfp-number', 'Original WFP No.', 1, 100);
                    ok &= requireDate('renewal-issue-date', 'Issued on');
                    ok &= validateAddress('renewal-postal-address', 'Postal Address', false);
                    ok &= validateCategories(true);
                    ok &= validateAnimalsRenewal();
                }

                return !!ok;
            }

            function scrollFirstErrorIntoView() {
                const first = document.querySelector('.invalid') || document.querySelector('.field-error[style*="display: block"]');
                if (first) first.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }

            // ---------- live bindings (min-length aware) ----------
            function bindLive(id, fn) {
                const el = $(id);
                el && el.addEventListener('input', fn);
            }
            // NEW
            bindLive('first-name', () => requireName('first-name', 'First Name'));
            bindLive('last-name', () => requireName('last-name', 'Last Name'));
            bindLive('residence-address', () => requireText('residence-address', 'Residence Address', 5));
            bindLive('telephone-number', () => optionalPhone('telephone-number', 'Telephone Number'));
            bindLive('establishment-name', () => requireText('establishment-name', 'Name of Establishment', 5));
            bindLive('establishment-address', () => requireText('establishment-address', 'Address of Establishment', 5));
            bindLive('establishment-telephone', () => optionalPhone('establishment-telephone', 'Establishment Telephone Number'));
            bindLive('postal-address', () => validateAddress('postal-address', 'Postal Address', false));
            // RENEWAL
            bindLive('renewal-first-name', () => requireName('renewal-first-name', 'First Name'));
            bindLive('renewal-last-name', () => requireName('renewal-last-name', 'Last Name'));
            bindLive('renewal-residence-address', () => requireText('renewal-residence-address', 'Residence Address', 5));
            bindLive('renewal-telephone-number', () => optionalPhone('renewal-telephone-number', 'Telephone Number'));
            bindLive('renewal-establishment-name', () => requireText('renewal-establishment-name', 'Name of Establishment', 5));
            bindLive('renewal-establishment-address', () => requireText('renewal-establishment-address', 'Address of Establishment', 5));
            bindLive('renewal-establishment-telephone', () => optionalPhone('renewal-establishment-telephone', 'Establishment Telephone Number'));
            bindLive('renewal-wfp-number', () => requireText('renewal-wfp-number', 'Original WFP No.', 1, 100));
            bindLive('renewal-issue-date', () => requireDate('renewal-issue-date', 'Issued on'));
            bindLive('renewal-postal-address', () => validateAddress('renewal-postal-address', 'Postal Address', false));

            // Table inputs: prevent digits in Common/Scientific Name in real-time
            document.addEventListener('input', (e) => {
                const t = e.target;
                const inAnimalsTable = t && t.tagName === 'INPUT' && t.type === 'text' &&
                    (t.closest('#animals-table') || t.closest('#renewal-animals-table'));
                if (inAnimalsTable) {
                    const cleaned = t.value.replace(/[0-9]/g, '');
                    if (cleaned !== t.value) t.value = cleaned;
                    clearErr(t); // remove stale error while typing
                }
            });

            // Permit switch clears errors (so group error doesn’t stick)
            document.querySelectorAll('.permit-type-btn').forEach(btn =>
                btn.addEventListener('click', () => clearAllErrors(document))
            );

            // ---------- gate submit (runs BEFORE your handlers) ----------
            const btnSubmit = $('submitApplication');
            if (btnSubmit) {
                btnSubmit.addEventListener('click', (ev) => {
                    clearAllErrors();
                    if (!validateAll()) {
                        ev.preventDefault();
                        ev.stopPropagation();
                        scrollFirstErrorIntoView();
                    }
                }, true);
            }
            window.__validateWildlifeForm = validateAll;
            window.__scrollFirstErrorIntoView = scrollFirstErrorIntoView;
        })();
    </script>
    <script>
        (() => {
            const $ = (id) => document.getElementById(id);
            const activeType = () => (document.querySelector('.permit-type-btn.active')?.dataset.type || 'new');

            // ===== gating =====
            let submitted = false;
            const touched = new WeakSet();

            // ===== error helpers =====
            function errElFor(input) {
                if (!input) return null;
                // Prefer an existing sibling error div if present
                let n = input.nextElementSibling;
                if (!n || !n.classList || !n.classList.contains('field-error')) {
                    n = document.createElement('div');
                    n.className = 'field-error';
                    n.style.cssText = 'color:#d22;font-size:12px;margin-top:4px;display:none;';
                    input.insertAdjacentElement('afterend', n);
                }
                return n;
            }

            function shouldPaint(inputEl) {
                return submitted || touched.has(inputEl);
            }

            function setErr(inputEl, errEl, msg) {
                if (!inputEl) return false;
                errEl = errEl || errElFor(inputEl);
                if (errEl) {
                    errEl.textContent = msg || 'Invalid value.';
                    if (shouldPaint(inputEl)) errEl.style.display = 'block';
                }
                if (shouldPaint(inputEl)) {
                    inputEl.classList.add('invalid');
                    inputEl.setAttribute('aria-invalid', 'true');
                }
                // no border/outline styling
                return false;
            }

            function clearErr(inputEl, errEl) {
                if (!inputEl) return true;
                errEl = errEl || (inputEl.nextElementSibling?.classList?.contains('field-error') ? inputEl.nextElementSibling : null);
                if (errEl) errEl.style.display = 'none';
                inputEl.classList.remove('invalid');
                inputEl.removeAttribute('aria-invalid');
                // no border/outline styling
                return true;
            }


            function setGroupErr(container, msg) {
                if (!container) return;
                let n = container.querySelector('.field-error.group');
                if (!n) {
                    n = document.createElement('div');
                    n.className = 'field-error group';
                    n.style.cssText = 'color:#d22;font-size:12px;margin-top:6px;display:none;';
                    container.appendChild(n);
                }
                n.textContent = msg || 'This field is required.';
                // paint immediately (group messages don’t need gating)
                n.style.display = submitted ? 'block' : 'block';
            }

            function clearGroupErr(container) {
                const n = container?.querySelector('.field-error.group');
                if (n) n.style.display = 'none';
            }

            function clearAllErrors(scope = document) {
                scope.querySelectorAll('.field-error').forEach(d => d.style.display = 'none');
                scope.querySelectorAll('.invalid').forEach(i => {
                    i.classList.remove('invalid');
                    i.removeAttribute('aria-invalid');
                    i.style.removeProperty('border-color');
                    i.style.removeProperty('border-width');
                    i.style.removeProperty('border-style');
                    i.style.removeProperty('outline');
                    i.style.removeProperty('box-shadow');
                });
            }

            // ===== patterns =====
            const personNameRe = /^[A-Za-zÀ-ÖØ-öø-ÿ\s.'-]{1,60}$/; // first/last
            const optionalNameRe = /^[A-Za-zÀ-ÖØ-öø-ÿ\s.'-]{1,60}$/; // middle (optional)
            const alphaOnlyRe = /^[A-Za-zÀ-ÖØ-öø-ÿ\s.'()\-]{2,100}$/; // common/scientific names
            const phoneRe = /^[0-9+()\-\s]{6,20}$/;

            // ===== field validators =====
            function requireName(id, label) {
                const el = $(id);
                if (!el) return true;
                const v = (el.value || '').trim();
                if (!v) return setErr(el, null, `${label} is required.`);
                if (!personNameRe.test(v)) return setErr(el, null, `Use letters/spaces/.’- only (max 60).`);
                return clearErr(el);
            }

            function optionalName(id, label) {
                const el = $(id);
                if (!el) return true;
                const v = (el.value || '').trim();
                if (!v) return clearErr(el); // empty OK
                if (!optionalNameRe.test(v)) return setErr(el, null, `Use letters/spaces/.’- only (max 60).`);
                return clearErr(el);
            }

            function requireText(id, label, min = 1, max = 200) {
                const el = $(id);
                if (!el) return true;
                const v = (el.value || '').trim();
                if (!v) return setErr(el, null, `${label} is required.`);
                if (v.length < min) return setErr(el, null, `${label} must be at least ${min} characters.`);
                if (v.length > max) return setErr(el, null, `${label} is too long (max ${max}).`);
                return clearErr(el);
            }

            function optionalText(id, label, max = 200) {
                const el = $(id);
                if (!el) return true;
                const v = (el.value || '').trim();
                if (!v) return clearErr(el);
                if (v.length > max) return setErr(el, null, `${label} is too long (max ${max}).`);
                return clearErr(el);
            }

            function optionalPhone(id, label) {
                const el = $(id);
                if (!el) return true;
                const v = (el.value || '').trim();
                if (!v) return clearErr(el);
                if (!phoneRe.test(v)) return setErr(el, null, `Use digits and + ( ) - (6–20 chars).`);
                return clearErr(el);
            }

            function requireDate(id, label) {
                const el = $(id);
                if (!el) return true;
                const v = (el.value || '').trim();
                if (!v) return setErr(el, null, `${label} is required.`);
                return clearErr(el);
            }

            function isFileSatisfied(inputEl) {
                if (!inputEl) return true;

                // a) user picked a new file
                if (inputEl.files && inputEl.files.length > 0) return true;

                // b) if user explicitly cleared, it must be treated as NOT satisfied
                if (inputEl.dataset && inputEl.dataset.userCleared === '1') return false;

                // c) otherwise, preloaded (anchor) counts as satisfied
                const wrap = inputEl.closest('.file-upload');
                if (wrap && wrap.querySelector('.uploaded-files a')) return true;

                return false;
            }


            function validateFileUploads(listId) {
                const scope = document.getElementById(listId);
                if (!scope) return true;

                let ok = true;
                const inputs = scope.querySelectorAll('.file-upload input[type="file"].file-input');

                inputs.forEach((inp) => {
                    // Force “paint errors” even if the user hasn’t interacted yet
                    try {
                        touched.add(inp);
                    } catch {}

                    if (isFileSatisfied(inp)) {
                        clearErr(inp);
                    } else {
                        ok = setErr(inp, null, 'Please attach a file.') && ok;
                    }
                });

                return !!ok;
            }


            // live revalidation when user picks a file
            document.addEventListener('change', (e) => {
                const t = e.target;
                if (!(t instanceof HTMLElement)) return;
                if (t.tagName === 'INPUT' && t.getAttribute('type') === 'file') {
                    // mark touched (same gating behavior as other fields)
                    try {
                        touched.add(t);
                    } catch {}
                    if (isFileSatisfied(t)) clearErr(t);
                    else setErr(t, null, 'Please attach a file.');
                }
            }, true);

            // ===== checkbox groups =====
            function validateCategories(isRenewal) {
                const ids = isRenewal ? ['renewal-zoo', 'renewal-botanical-garden', 'renewal-private-collection'] : ['zoo', 'botanical-garden', 'private-collection'];
                const group = document.querySelector(isRenewal ? '#renewal-upper-block .checkbox-group' :
                    '#new-upper-block .checkbox-group');
                const anyChecked = ids.some(id => $(id)?.checked);
                if (!anyChecked) {
                    setGroupErr(group, 'Select at least one category.');
                    return false;
                }
                clearGroupErr(group);
                return true;
            }
            // clear group error on toggle
            [
                ['zoo', 'botanical-garden', 'private-collection', '#new-upper-block .checkbox-group'],
                ['renewal-zoo', 'renewal-botanical-garden', 'renewal-private-collection', '#renewal-upper-block .checkbox-group']
            ].forEach(([a, b, c, sel]) => {
                [$(a), $(b), $(c)].forEach(cb => cb && cb.addEventListener('change', () => clearGroupErr(document.querySelector(sel))));
            });

            // ===== animals tables =====
            function validateAnimalsNew() {
                const tbody = document.querySelector('#animals-table tbody');
                if (!tbody) return true;
                let ok = true;
                const rows = Array.from(tbody.querySelectorAll('tr'));
                if (!rows.length) return true;

                rows.forEach((tr, idx) => {
                    const inputs = tr.querySelectorAll('input');
                    const common = inputs[0],
                        sci = inputs[1],
                        qty = inputs[2];
                    const hasAny = [common, sci, qty].some(i => (i?.value || '').trim() !== '');

                    function checkName(el, label) {
                        const s = (el?.value || '').trim();
                        if (!s) return setErr(el, null, 'Required.');
                        if (!alphaOnlyRe.test(s)) return setErr(el, null, `${label}: letters/spaces/.’()- only.`);
                        return clearErr(el);
                    }

                    function checkQty(el) {
                        const n = Number((el?.value || '').trim());
                        if (!Number.isFinite(n) || n < 1) return setErr(el, null, 'Quantity must be ≥ 1.');
                        return clearErr(el);
                    }

                    if (idx === 0) {
                        ok = checkName(common, 'Common Name') && ok;
                        ok = checkName(sci, 'Scientific Name') && ok;
                        ok = checkQty(qty) && ok;
                        return;
                    }
                    if (hasAny) {
                        ok = checkName(common, 'Common Name') && ok;
                        ok = checkName(sci, 'Scientific Name') && ok;
                        ok = checkQty(qty) && ok;
                    } else {
                        [common, sci, qty].forEach(el => clearErr(el));
                    }
                });
                return !!ok;
            }

            function validateAnimalsRenewal() {
                const tbody = document.querySelector('#renewal-animals-table tbody');
                if (!tbody) return true;
                let ok = true;
                const rows = Array.from(tbody.querySelectorAll('tr'));
                if (!rows.length) return true;

                rows.forEach((tr, idx) => {
                    const cells = tr.querySelectorAll('input, select');
                    const common = cells[0],
                        sci = cells[1],
                        qty = cells[2];
                    const hasAny = [common, sci, qty].some(i => (i?.value || '').trim() !== '');

                    const checkName = (el, label) => {
                        const s = (el?.value || '').trim();
                        if (!s) return setErr(el, null, 'Required.');
                        if (!alphaOnlyRe.test(s)) return setErr(el, null, `${label}: letters/spaces/.’()- only.`);
                        return clearErr(el);
                    };
                    const checkQty = (el) => {
                        const n = Number((el?.value || '').trim());
                        if (!Number.isFinite(n) || n < 1) return setErr(el, null, 'Quantity must be ≥ 1.');
                        return clearErr(el);
                    };

                    if (idx === 0) {
                        ok = checkName(common, 'Common Name') && ok;
                        ok = checkName(sci, 'Scientific Name') && ok;
                        ok = checkQty(qty) && ok;
                        return;
                    }
                    if (hasAny) {
                        ok = checkName(common, 'Common Name') && ok;
                        ok = checkName(sci, 'Scientific Name') && ok;
                        ok = checkQty(qty) && ok;
                    } else {
                        [common, sci, qty].forEach(el => clearErr(el));
                    }
                });
                return !!ok;
            }

            // prevent digits in common/scientific names live + mark touched
            document.addEventListener('input', (e) => {
                const t = e.target;
                const inAnimals = t && t.tagName === 'INPUT' && t.type === 'text' &&
                    (t.closest('#animals-table') || t.closest('#renewal-animals-table'));
                if (inAnimals) {
                    const cleaned = t.value.replace(/[0-9]/g, '');
                    if (cleaned !== t.value) t.value = cleaned;
                    touched.add(t);
                }
            }, true);

            // ===== overall validate =====
            function validateAll() {
                let ok = true;
                const type = activeType();

                if (type === 'new') {
                    ok = requireName('first-name', 'First Name') && ok;
                    ok = optionalName('middle-name', 'Middle Name') && ok; // optional
                    ok = requireName('last-name', 'Last Name') && ok;
                    ok = requireText('residence-address', 'Residence Address', 5) && ok;
                    ok = optionalPhone('telephone-number', 'Telephone Number') && ok;
                    ok = requireText('establishment-name', 'Name of Establishment', 5) && ok;
                    ok = requireText('establishment-address', 'Address of Establishment', 5) && ok;
                    ok = optionalPhone('establishment-telephone', 'Establishment Telephone Number') && ok;
                    ok = optionalText('postal-address', 'Postal Address') && ok;
                    ok = validateCategories(false) && ok;
                    ok = validateAnimalsNew() && ok;

                    // NEW: require all visible file inputs in New requirements
                    ok = validateFileUploads('new-requirements') && ok;
                } else {
                    ok = requireName('renewal-first-name', 'First Name') && ok;
                    ok = optionalName('renewal-middle-name', 'Middle Name') && ok; // optional
                    ok = requireName('renewal-last-name', 'Last Name') && ok;
                    ok = requireText('renewal-residence-address', 'Residence Address', 5) && ok;
                    ok = optionalPhone('renewal-telephone-number', 'Telephone Number') && ok;
                    ok = requireText('renewal-establishment-name', 'Name of Establishment', 5) && ok;
                    ok = requireText('renewal-establishment-address', 'Address of Establishment', 5) && ok;
                    ok = optionalPhone('renewal-establishment-telephone', 'Establishment Telephone Number') && ok;
                    ok = requireText('renewal-wfp-number', 'Original WFP No.', 1, 100) && ok;
                    ok = requireDate('renewal-issue-date', 'Issued on') && ok;
                    ok = optionalText('renewal-postal-address', 'Postal Address') && ok;
                    ok = validateCategories(true) && ok;
                    ok = validateAnimalsRenewal() && ok;

                    // NEW: require all visible file inputs in Renewal requirements
                    ok = validateFileUploads('renewal-requirements') && ok;
                }
                return !!ok;
            }


            function scrollFirstErrorIntoView() {
                const first = document.querySelector('.invalid') ||
                    document.querySelector('.field-error[style*="display: block"]');
                if (first) first.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }

            // ===== live gating: mark fields touched and validate their own rule =====
            function revalidateField(el) {
                switch (el.id) {
                    // new
                    case 'first-name':
                        return requireName('first-name', 'First Name');
                    case 'middle-name':
                        return optionalName('middle-name', 'Middle Name');
                    case 'last-name':
                        return requireName('last-name', 'Last Name');
                    case 'residence-address':
                        return requireText('residence-address', 'Residence Address', 5);
                    case 'telephone-number':
                        return optionalPhone('telephone-number', 'Telephone Number');
                    case 'establishment-name':
                        return requireText('establishment-name', 'Name of Establishment', 5);
                    case 'establishment-address':
                        return requireText('establishment-address', 'Address of Establishment', 5);
                    case 'establishment-telephone':
                        return optionalPhone('establishment-telephone', 'Establishment Telephone Number');
                    case 'postal-address':
                        return optionalText('postal-address', 'Postal Address');
                        // renewal
                    case 'renewal-first-name':
                        return requireName('renewal-first-name', 'First Name');
                    case 'renewal-middle-name':
                        return optionalName('renewal-middle-name', 'Middle Name');
                    case 'renewal-last-name':
                        return requireName('renewal-last-name', 'Last Name');
                    case 'renewal-residence-address':
                        return requireText('renewal-residence-address', 'Residence Address', 5);
                    case 'renewal-telephone-number':
                        return optionalPhone('renewal-telephone-number', 'Telephone Number');
                    case 'renewal-establishment-name':
                        return requireText('renewal-establishment-name', 'Name of Establishment', 5);
                    case 'renewal-establishment-address':
                        return requireText('renewal-establishment-address', 'Address of Establishment', 5);
                    case 'renewal-establishment-telephone':
                        return optionalPhone('renewal-establishment-telephone', 'Establishment Telephone Number');
                    case 'renewal-wfp-number':
                        return requireText('renewal-wfp-number', 'Original WFP No.', 1, 100);
                    case 'renewal-issue-date':
                        return requireDate('renewal-issue-date', 'Issued on');
                    case 'renewal-postal-address':
                        return optionalText('renewal-postal-address', 'Postal Address');
                    default:
                        return true;
                }
            }
            document.addEventListener('input', (e) => {
                const el = e.target;
                if (!(el instanceof HTMLElement)) return;
                if (!/^(INPUT|SELECT|TEXTAREA)$/i.test(el.tagName)) return;
                touched.add(el);
                revalidateField(el);
            }, true);
            document.addEventListener('change', (e) => {
                const el = e.target;
                if (!(el instanceof HTMLElement)) return;
                if (!/^(INPUT|SELECT|TEXTAREA)$/i.test(el.tagName)) return;
                touched.add(el);
                revalidateField(el);
            }, true);

            // ===== simple file-type category validation =====
            // If an input's `accept` contains only image types (jpg/jpeg/png) -> enforce image only
            // If `accept` contains only doc/pdf types -> enforce docs only
            // If `accept` contains both or is not present, skip category enforcement (mixed allowed)
            function isImageMime(mime) {
                return typeof mime === 'string' && mime.startsWith('image/');
            }

            function isDocMime(mime) {
                return ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'].includes(mime);
            }

            document.addEventListener('change', (ev) => {
                const t = ev.target;
                if (!(t instanceof HTMLInputElement) || t.type !== 'file') return;
                const file = t.files?.[0];
                // no file selected -> nothing to validate here
                if (!file) return;

                // mark touched so setErr will paint immediately
                try {
                    touched.add(t);
                } catch (e) {}

                const accept = (t.getAttribute('accept') || '').toLowerCase();

                // detect explicit accept-only cases first
                const acceptHasImage = /\.jpe?g|\.png|image\//.test(accept);
                const acceptHasDoc = /\.pdf|\.docx?|application\//.test(accept);
                const acceptOnlyImage = acceptHasImage && !acceptHasDoc;
                const acceptOnlyDoc = acceptHasDoc && !acceptHasImage;

                // heuristic: if accept is mixed (both image and doc allowed) try to infer expected type
                function detectExpectedType(inputEl) {
                    if (!inputEl) return null;
                    if (acceptOnlyImage) return 'image';
                    if (acceptOnlyDoc) return 'doc';

                    // look for surrounding requirement text/title
                    const item = inputEl.closest('.requirement-item') || inputEl.closest('.sub-requirement') || inputEl.closest('.file-upload');
                    let ctx = '';
                    if (item) {
                        const titleEl = item.querySelector('.requirement-title') || item.querySelector('h4') || item;
                        ctx = (titleEl && (titleEl.textContent || '') || '').toLowerCase();
                    }

                    const imageKeywords = ['photo', 'photo of', 'facility design', 'sketch', 'map', 'picture', 'image', 'photo of facility'];
                    const docKeywords = ['registration', 'certificate', 'receipt', 'report', 'clearance', 'deed', 'proof', 'wfp', 'cda', 'sec', 'dti', 'dtI', 'permit', 'application', 'bank', 'financial', 'inspection', 'quarterly', 'monthly', 'original', 'official'];

                    for (const k of imageKeywords)
                        if (ctx.includes(k)) return 'image';
                    for (const k of docKeywords)
                        if (ctx.includes(k)) return 'doc';
                    return null;
                }

                const expected = detectExpectedType(t);

                if ((acceptOnlyImage || expected === 'image') && !isImageMime(file.type)) {
                    setErr(t, null, 'JPG/PNG only');
                    try {
                        t.value = '';
                    } catch (e) {}
                    return;
                }

                if ((acceptOnlyDoc || expected === 'doc') && !isDocMime(file.type)) {
                    setErr(t, null, 'PDF/DOC/DOCX only.');
                    try {
                        t.value = '';
                    } catch (e) {}
                    return;
                }

                // otherwise clear any previous file-type errors
                clearErr(t);
            }, true);

            // ===== permit switch clears errors =====
            document.querySelectorAll('.permit-type-btn').forEach(btn =>
                btn.addEventListener('click', () => {
                    submitted = false;
                    clearAllErrors(document);
                })
            );

            // ===== gate submit BEFORE your submit handlers run =====
            const btnSubmit = $('submitApplication');
            if (btnSubmit) {
                btnSubmit.addEventListener('click', (ev) => {
                    submitted = true; // allow painting everywhere
                    clearAllErrors(document); // remove stale visuals
                    if (!validateAll()) {
                        ev.preventDefault();
                        ev.stopPropagation();
                        scrollFirstErrorIntoView();
                    }
                }, true); // capture: before other listeners
            }

            // One pass on load to ensure nothing is red initially
            window.addEventListener('load', () => {
                clearAllErrors(document);
            });
            window.__validateWildlifeForm = validateAll;
            window.__scrollFirstErrorIntoView = scrollFirstErrorIntoView;
        })();
    </script>




</body>






</html>