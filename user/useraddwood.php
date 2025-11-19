<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'User') {
    header("Location: user_login.php");
    exit();
}
include_once __DIR__ . '/../backend/connection.php';

$notifs = [];
$unreadCount = 0;
$wfpRecords = [];
$wfpSelectOptions = [];
$wfpRecordsJson = '{}';
$myClientIds = [];

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

// prepared statement used to check whether an approval is for seedlings
$stApprovalType = $pdo->prepare("SELECT seedl_req_id FROM public.approval WHERE approval_id = :aid LIMIT 1");

/* Prefetch linked client IDs for current user */
if (!empty($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare('SELECT client_id FROM public.client WHERE user_id = :uid');
        $stmt->execute([':uid' => $_SESSION['user_id']]);
        $myClientIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'client_id');
    } catch (Throwable $e) {
        error_log('[WOOD-MY-CLIENTS] ' . $e->getMessage());
        $myClientIds = [];
    }
}

/* Prefetch WFP numbers + metadata for renewal picker */
try {
    $stmt = $pdo->prepare("
        WITH my_clients AS (
            SELECT client_id FROM public.client WHERE user_id = :uid
        ),
        doc_rows AS (
            SELECT
                d.approved_id,
                d.approval_id AS doc_approval_id,
                NULLIF(trim(d.wfp_no), '') AS wfp_no,
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
                a.approval_status,
                a.client_id,
                CASE WHEN a.client_id IN (SELECT client_id FROM my_clients) THEN 0 ELSE 1 END AS priority,
                c.first_name    AS client_first,
                c.middle_name   AS client_middle,
                c.last_name     AS client_last,
                c.user_id       AS client_user_id,
                af.contact_number,
                af.email_address,
                af.present_address,
                af.legitimate_business_address,
                af.plant_location,
                af.plant_location_barangay_municipality_province,
                af.form_of_ownership,
                af.kind_of_wood_processing_plant,
                af.daily_rated_capacity_per8_hour_shift,
                af.machineries_and_equipment_to_be_used_with_specifications,
                af.suppliers_json,
                af.declaration_name,
                af.permit_number,
                af.expiry_date AS permit_expiry_date,
                af.additional_information,
                req.wood_monthly_reports,
                req.wood_importer_registration,
                req.wood_importer_supply_contracts,
                req.wood_proof_of_importation
            FROM public.approved_docs d
            JOIN public.approval a ON a.approval_id = d.approval_id
            LEFT JOIN public.client c ON c.client_id = a.client_id
            LEFT JOIN public.application_form af ON af.application_id = a.application_id
            LEFT JOIN public.requirements req ON req.requirement_id = a.requirement_id
            WHERE NULLIF(trim(d.wfp_no), '') IS NOT NULL
              AND lower(coalesce(a.request_type, '')) = 'wood'
        ),
        ranked AS (
            SELECT *,
                   ROW_NUMBER() OVER (
                       PARTITION BY upper(wfp_no)
                       ORDER BY priority ASC, COALESCE(date_issued, submitted_at) DESC, approved_id DESC
                   ) AS rn
            FROM doc_rows
        )
        SELECT *
        FROM ranked
        WHERE rn = 1
        ORDER BY priority ASC, COALESCE(date_issued, submitted_at) DESC, approved_id DESC
        LIMIT 80
    ");
    $stmt->execute([':uid' => $_SESSION['user_id'] ?? null]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $fileMap = [
        'uploaded-files-r1' => ['approved_document', 'Previously Approved WPP Permit'],
        'uploaded-files-r4' => ['wood_monthly_reports', 'Monthly Production and Disposition Report'],
        'uploaded-files-r5' => ['wood_importer_registration', 'Certificate of Registration as Log/Veneer/Lumber Importer'],
        'uploaded-files-r6' => ['wood_importer_supply_contracts', 'Importer Supply Contracts'],
        'uploaded-files-r7' => ['wood_proof_of_importation', 'Proof of Importation'],
    ];

    foreach ($rows as $row) {
        $wfpNumber = $row['wfp_no'] ?? '';
        $key = sha1(($row['approval_id'] ?? uniqid('', true)) . '|' . $wfpNumber . '|' . ($row['date_issued'] ?? ''));
        $issueLabel = '';
        if (!empty($row['date_issued'])) {
            $dt = date_create($row['date_issued']);
            if ($dt) {
                $issueLabel = 'issued ' . $dt->format('M d, Y');
            }
        }
        $clientLabel = trim(($row['client_last'] ?? '') . ', ' . ($row['client_first'] ?? ''));
        $labelParts = array_filter([
            $wfpNumber ?: null,
            $clientLabel ?: null,
            $issueLabel ?: null,
        ]);
        $optionLabel = $labelParts ? implode(' • ', $labelParts) : ($wfpNumber ?: 'Released WFP');
        $wfpSelectOptions[] = [
            'key' => $key,
            'label' => $optionLabel,
        ];

        $files = [];
        foreach ($fileMap as $containerId => [$column, $display]) {
            $url = trim((string)($row[$column] ?? ''));
            if ($url === '') continue;
            $files[$containerId] = [[
                'url' => $url,
                'label' => $display,
            ]];
        }

        $machRows = [];
        if (!empty($row['machineries_and_equipment_to_be_used_with_specifications'])) {
            $decoded = json_decode($row['machineries_and_equipment_to_be_used_with_specifications'], true);
            if (is_array($decoded)) {
                $machRows = $decoded;
            }
        }
        $supplierRows = [];
        if (!empty($row['suppliers_json'])) {
            $decoded = json_decode($row['suppliers_json'], true);
            if (is_array($decoded)) {
                $supplierRows = $decoded;
            }
        }
        $additionalInfo = [];
        if (!empty($row['additional_information'])) {
            $decoded = json_decode($row['additional_information'], true);
            if (is_array($decoded)) {
                $additionalInfo = $decoded;
            }
        }

        $info = [
            'address' => $row['present_address'] ?? $row['legitimate_business_address'] ?? '',
            'contact_number' => $row['contact_number'] ?? '',
            'email_address' => $row['email_address'] ?? '',
            'plant_location' => $row['plant_location'] ?? $row['plant_location_barangay_municipality_province'] ?? '',
            'ownership_type' => $row['form_of_ownership'] ?? '',
            'permit_number' => $row['permit_number'] ?? '',
            'expiry_date' => $row['permit_expiry_date'] ?? $row['expiry_date'] ?? '',
            'declaration_name' => $row['declaration_name'] ?? '',
            'declaration_address' => $additionalInfo['declaration_address'] ?? '',
            'power_source' => $additionalInfo['power_source'] ?? '',
            'plant_type' => $row['kind_of_wood_processing_plant'] ?? '',
            'daily_capacity' => $row['daily_rated_capacity_per8_hour_shift'] ?? '',
            'machinery_rows' => $machRows,
            'supplier_rows' => $supplierRows,
        ];

        $wfpRecords[$key] = [
            'key' => $key,
            'wfp_number' => $wfpNumber,
            'issue_date' => $row['date_issued'] ?? null,
            'expiry_date' => $row['expiry_date'] ?? null,
            'label' => $optionLabel,
            'priority' => (int)($row['priority'] ?? 1),
            'client' => [
                'first_name' => $row['client_first'] ?? '',
                'middle_name' => $row['client_middle'] ?? '',
                'last_name' => $row['client_last'] ?? '',
            ],
            'info' => $info,
            'files' => $files,
        ];
    }
    $jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    $wfpRecordsJson = $wfpRecords ? (json_encode($wfpRecords, $jsonFlags) ?: '{}') : '{}';
} catch (Throwable $e) {
    error_log('[WOOD-WFP] ' . $e->getMessage());
    $wfpRecords = [];
    $wfpSelectOptions = [];
    $wfpRecordsJson = '{}';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id, $hashed_password, $role);
        $stmt->fetch();

        if (password_verify($password, $hashed_password)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['role'] = $role;

            header("Location: user_home.php");
            exit();
        } else {
            $error = "Incorrect password.";
        }
    } else {
        $error = "User not found.";
    }

    $stmt->close();
}

$clientRows = [];
try {
    $stmt = $pdo->prepare("
        SELECT client_id, user_id, first_name, middle_name, last_name,
               sitio_street, barangay, municipality, city, contact_number
        FROM public.client
        ORDER BY (user_id = :uid) DESC, last_name ASC, first_name ASC
        LIMIT 500
    ");
    $stmt->execute([':uid' => $_SESSION['user_id']]);
    $clientRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[WOOD-CLIENTS] ' . $e->getMessage());
    $clientRows = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wood Processing Plant Permit Application</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>

</head>
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
        --loader-size: 20px;
        --as-white: #fff;
        --as-light-gray: #f5f5f5;
        --as-radius: 8px;
        --as-shadow: 0 4px 12px rgba(0, 0, 0, .1);
        --as-trans: all .2s ease;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    .as-item {
        position: relative;
    }

    /* Bell button */
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

    /* Base dropdown */
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

    /* Notifications-specific sizing */
    .as-notifications {
        min-width: 350px;
        max-height: 500px;
    }

    /* Sticky header */
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

    /* Scroll body */
    .notifcontainer {
        height: 380px;
        overflow-y: auto;
        padding: 5px;
        background: #fff;
    }

    /* Rows */
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

    /* Sticky footer */
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

    /* Red badge on bell */
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
        min-width: 320px;
        /* Increased from 300px */
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transform: translateY(10px);
        transition: var(--transition);
        padding: 0;
    }

    .dropdown-item {
        padding: 15px 25px;
        display: flex;
        align-items: center;
        color: black;
        text-decoration: none;
        transition: var(--transition);
        font-size: 1.1rem;
        white-space: nowrap;
        /* Prevent text wrapping */
    }

    .dropdown-item:hover {
        background: var(--light-gray);
        padding-left: 30px;
    }

    .dropdown-item.active-page {
        background-color: rgb(225, 255, 220);
        color: var(--primary-dark);
        font-weight: bold;
        border-left: 4px solid var(--primary-color);
        padding-left: 21px;
        /* Adjusted for border */
    }

    .dropdown-item i {
        width: 30px;
        font-size: 1.5rem;
        color: var(--primary-color) !important;
        margin-right: 15px;
        flex-shrink: 0;
    }

    .dropdown-item span {
        overflow: hidden;
        text-overflow: ellipsis;
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
        border: 1px solid #ddd;
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
        grid-template-columns: 1fr 1fr;
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
        margin-bottom: 20px;
    }

    .requirement-item:last-child {
        margin-bottom: 0;
    }

    .requirement-item.supporting-docs {
        margin-bottom: 0;
    }

    .requirement-item.renewal-only {
        display: none;
    }

    .requirement-item.renewal-only.active {
        display: block;
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
        margin-right: 10px;
        flex-shrink: 0;
        line-height: 25px;
        text-align: center;
    }

    .new-number {
        display: inline;
    }

    .renewal-number {
        display: none;
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
        color: #555;
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
        border: 1px solid #ddd;
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
        color: #555;
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
        border-top: 1px solid #ddd;
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
        grid-column: 1 / -1;
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

    /* Download button styles */
    .download-btn {
        display: inline-flex;
        align-items: center;
        background-color: #2b6625;
        color: white;
        padding: 8px 15px;
        border-radius: 5px;
        text-decoration: none;
        margin-top: 10px;
        transition: all 0.3s;
    }

    .download-btn:hover {
        background-color: #1e4a1a;
    }

    .download-btn i {
        margin-right: 8px;
    }

    /* Permit Type Selector */
    .permit-type-selector {
        display: flex;
        justify-content: flex-start;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
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

    .wfp-details-card {
        width: 100%;
        border: 1px solid #dfe6dd;
        border-radius: 10px;
        padding: 15px 18px;
        background: #f9fff6;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.05);
        margin-bottom: 12px;
    }

    .wfp-details-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 10px;
    }

    .wfp-details-meta {
        display: flex;
        gap: 14px;
        font-size: .9rem;
        color: #4d5a4f;
        flex-wrap: wrap;
    }

    .wfp-details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px 18px;
    }

    .wfp-detail-label {
        font-size: .78rem;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #5b6a5e;
        margin-bottom: 2px;
    }

    .wfp-detail-value {
        font-size: .95rem;
        font-weight: 600;
        color: #1f2d21;
    }

    /* Add new styles for name fields */
    .name-fields {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 20px;
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
        transition: border-color 0.3s;
        height: 40px;
        box-sizing: border-box;
    }

    .readonly-input {
        background-color: #f4f4f4 !important;
        cursor: not-allowed;
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
        .requirements-list {
            grid-template-columns: 1fr;
        }

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

        .permit-type-selector {
            flex-wrap: nowrap;
            overflow-x: auto;
            padding-bottom: 10px;
            -webkit-overflow-scrolling: touch;
        }

        .permit-type-btn {
            flex: 0 0 auto;
            margin: 0 5px 0 0;
            padding: 10px 15px;
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

        .permit-type-btn {
            font-size: 0.9rem;
            padding: 8px 12px;
        }
    }

    .sub-requirement {
        margin-top: 15px;
    }

    .sub-requirement:first-child {
        margin-top: 0;
    }

    .sub-requirement:last-child {
        margin-bottom: 0;
    }

    .form-section {
        margin-bottom: 25px;
    }

    .general-section h3 {
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
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 15px;
    }

    .checkbox-item {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 15px;
    }

    table,
    th,
    td {
        border: 1px solid #ddd;
    }

    th,
    td {
        padding: 10px;
        text-align: left;
    }

    th {
        background-color: #f2f2f2;
    }

    .add-row-btn {
        background-color: #2b6625;
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 4px;
        cursor: pointer;
        margin-bottom: 15px;
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

    #signature-pad {
        width: 100%;
        height: 150px;
        cursor: crosshair;
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

    .address-same-notice {
        background-color: #e8f5e9;
        padding: 10px;
        border-radius: 4px;
        margin-top: 10px;
        font-size: 14px;
        color: #2b6625;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    @media (max-width: 768px) {
        .form-row {
            flex-direction: column;
            gap: 0;
        }

        .signature-date {
            flex-direction: column;
            gap: 20px;
        }

        .declaration-input {
            width: 100%;
            margin: 5px 0;
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

    .form-section h2,
    .plant-section h3,
    .supply-section h3,
    .declaration-section h3 {
        background-color: #2b6625;
        color: white;
        padding: 10px 15px;
        margin-bottom: 15px;
        border-radius: 4px;
        font-size: 18px;
    }

    /* Make address field same height as name field */
    .same-height-input {
        height: 45px;
        /* Same height as text input */
        min-height: 45px;
        resize: vertical;
        /* Allow vertical resizing only */
    }
</style>

<body>
    <header>
        <div class="logo"> <a href="user_home.php"> <img src="seal.png" alt="Site Logo"> </a> </div>
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
                    <a href="useraddwood.php" class="dropdown-item active-page">
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
                                $ts     = $n['created_at'] ? (new DateTime((string)$n['created_at']))->getTimestamp() : time();
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
                                        $title = 'Permit Update';
                                    }
                                } elseif (!empty($n['incident_id'])) {
                                    $title = 'Incident Update';
                                }
                                $cleanMsg = (function ($m) {
                                    $t = trim((string)$m);
                                    $t = preg_replace('/\\s*\\(?\\b(rejection\\s*reason|reason)\\b\\s*[:\\-–]\\s*.*$/i', '', $t);
                                    $t = preg_replace('/\\s*\\b(because|due\\s+to)\\b\\s*.*/i', '', $t);
                                    return trim(preg_replace('/\\s{2,}/', ' ', $t)) ?: 'There’s an update.';
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
        <!-- <div class="action-buttons">
            <button class="btn btn-primary" id="addFilesBtn">
                <i class="fas fa-plus-circle"></i> Add
            </button>
            <a href="usereditwood.php" class="btn btn-outline">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="userviewwood.php" class="btn btn-outline">
                <i class="fas fa-eye"></i> View
            </a>
        </div> -->

        <div class="requirements-form">
            <div class="form-header">
                <h2>Wood Processing Plant Permit - Requirements</h2>
            </div>

            <div class="form-body">
                <!-- Permit Type Selector -->
                <div class="permit-type-selector">
                    <div style="display:flex;align-items:center;gap:8px; ">
                        <button class="permit-type-btn active" data-type="new">New Permit</button>
                        <button class="permit-type-btn" data-type="renewal">Renewal</button>
                        <div id="wfpPicker" style="display:flex; text-align: center; gap:6px;min-width:220px; border: 1px dashed #c8d3c5; border-radius: 6px; background: #f8fbf7; padding:  5px;">
                            <label for="wfpSelect" style="font-size:.85rem;margin-bottom:4px;">Select Permit No.</label>
                            <?php if ($wfpSelectOptions): ?>
                                <select id="wfpSelect" style="min-width:220px;height:38px;border:1px solid #ccc;border-radius:4px;padding:0 10px;">
                                    <option value="">-- Select Permit No. --</option>
                                    <?php foreach ($wfpSelectOptions as $opt): ?>
                                        <option value="<?= htmlspecialchars($opt['key'], ENT_QUOTES) ?>"><?= htmlspecialchars($opt['label'], ENT_QUOTES) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <select id="wfpSelect" disabled style="min-width:220px;height:38px;border:1px solid #ccc;border-radius:4px;padding:0 10px;">
                                    <option value="">-- No WFP records yet --</option>
                                </select>
                                <small style="margin-top:4px;font-size: 10px;color:#666;">No released WFP numbers on record.</small>
                            <?php endif; ?>

                        </div>
                        <div class="client-mode-toggle" id="clientModeToggle" style="display:<?= $clientRows ? 'flex' : 'none' ?>;flex-wrap:wrap;align-items:center;gap:8px;">
                            <button type="button" id="btnExisting" class="btn btn-outline">
                                <i class="fas fa-user-check"></i>&nbsp;Existing client
                            </button>
                            <button type="button" id="btnNew" class="btn btn-outline" style="display:none;">
                                <i class="fas fa-user-plus"></i>&nbsp;New client
                            </button>
                        </div>
                    </div>

                </div>

                <small id="clientModeHint" style="display:<?= $clientRows ? 'block' : 'none' ?>;margin-bottom:15px;opacity:.8;">Choose <b>Existing client</b> if the record already exists; otherwise stay on <b>New client</b>.</small>

                <!-- ======= I. GENERAL INFORMATION ======= -->
                <!-- New Permit -->
                <div id="general-new" class="general-section" style="display:block;">
                    <h3 style="margin:18px 0 10px;">I. GENERAL INFORMATION (New)</h3>

                    <input type="hidden" id="clientMode" value="new">

                    <div id="existingClientRow" class="form-group" style="display:none;margin-bottom:16px;">
                        <label for="clientPick" style="font-weight:600;margin-bottom:6px;display:block;">Select client</label>
                        <?php if ($clientRows): ?>
                            <select id="clientPick" style="height:42px;width:100%;border:1px solid #ccc;border-radius:4px;padding:0 10px;">
                                <option value="">-- Select a client --</option>
                                <?php
                                $myId = (string)($_SESSION['user_id'] ?? '');
                                $renderOption = static function (array $c) {
                                    $full = trim(trim((string)($c['first_name'] ?? '')) . ' ' . trim((string)($c['middle_name'] ?? '')) . ' ' . trim((string)($c['last_name'] ?? '')));
                                    $full = trim(preg_replace('/\s+/', ' ', $full)) ?: 'Unnamed client';
                                    $addrParts = [];
                                    if (!empty($c['sitio_street'])) $addrParts[] = $c['sitio_street'];
                                    if (!empty($c['barangay'])) $addrParts[] = 'Brgy. ' . $c['barangay'];
                                    if (!empty($c['municipality']) || !empty($c['city'])) {
                                        $addrParts[] = $c['municipality'] ?: $c['city'];
                                    }
                                    $addressValue = $addrParts ? implode(', ', $addrParts) : '';
                                    $label = $full . ($addressValue ? ' - ' . $addressValue : '');
                                    $attrs = sprintf(
                                        ' value="%s" data-first="%s" data-middle="%s" data-last="%s" data-address="%s" data-contact="%s"',
                                        htmlspecialchars((string)($c['client_id'] ?? ''), ENT_QUOTES),
                                        htmlspecialchars((string)($c['first_name'] ?? ''), ENT_QUOTES),
                                        htmlspecialchars((string)($c['middle_name'] ?? ''), ENT_QUOTES),
                                        htmlspecialchars((string)($c['last_name'] ?? ''), ENT_QUOTES),
                                        htmlspecialchars($addressValue, ENT_QUOTES),
                                        htmlspecialchars((string)($c['contact_number'] ?? ''), ENT_QUOTES)
                                    );
                                    return '<option' . $attrs . '>' . htmlspecialchars($label, ENT_QUOTES) . '</option>';
                                };
                                $hasMine = false;
                                foreach ($clientRows as $c) {
                                    $isMine = ((string)($c['user_id'] ?? '') === $myId);
                                    if (!$isMine) continue;
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
                        <?php else: ?>
                            <div style="padding:10px 12px;border:1px dashed #aaa;border-radius:4px;color:#555;background:#fafafa;">
                                No existing clients have been recorded yet.
                            </div>
                        <?php endif; ?>
                        <div id="clientPickError" class="field-error" style="display:none;"></div>
                    </div>

                    <div id="newClientRow">
                        <div class="name-fields">
                            <div class="name-field">
                                <input type="text" id="new-first-name" placeholder="First Name" required>
                            </div>
                            <div class="name-field">
                                <input type="text" id="new-middle-name" placeholder="Middle Name">
                            </div>
                            <div class="name-field">
                                <input type="text" id="new-last-name" placeholder="Last Name" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="new-business-address" class="required">Complete Business Address:</label>
                        <textarea id="new-business-address" rows="1" class="same-height-input"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="new-plant-location" class="required">Plant Location (Barangay/Municipality/Province):</label>
                        <input type="text" id="new-plant-location">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="new-contact-number" class="required">Contact Number(s):</label>
                            <input type="text" id="new-contact-number">
                        </div>

                        <div class="form-group">
                            <label for="new-email-address">Email Address:</label>
                            <input type="email" id="new-email-address">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="required">Type of Ownership:</label>
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="radio" id="new-single-proprietorship" name="new-ownership-type" value="Single Proprietorship">
                                <label for="new-single-proprietorship">Single Proprietorship</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="new-partnership" name="new-ownership-type" value="Partnership">
                                <label for="new-partnership">Partnership</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="new-corporation" name="new-ownership-type" value="Corporation">
                                <label for="new-corporation">Corporation</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="new-cooperative" name="new-ownership-type" value="Cooperative">
                                <label for="new-cooperative">Cooperative</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Renewal -->
                <div id="general-renewal" class="general-section" style="display:none;">
                    <h3 style="margin:18px 0 10px;">I. GENERAL INFORMATION (Renewal)</h3>

                    <div class="name-fields">
                        <div class="name-field">
                            <input type="text" id="r-first-name" placeholder="First Name" required>
                        </div>
                        <div class="name-field">
                            <input type="text" id="r-middle-name" placeholder="Middle Name">
                        </div>
                        <div class="name-field">
                            <input type="text" id="r-last-name" placeholder="Last Name" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="r-address" class="required">Address:</label>
                        <textarea id="r-address" rows="1" class="same-height-input"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="r-plant-location" class="required">Plant Location:</label>
                        <input type="text" id="r-plant-location">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="r-contact-number" class="required">Contact Number:</label>
                            <input type="text" id="r-contact-number">
                        </div>

                        <div class="form-group">
                            <label for="r-email-address">Email:</label>
                            <input type="email" id="r-email-address">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="required">Type of Ownership:</label>
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="radio" id="r-single-proprietorship" name="r-ownership-type" value="Single Proprietorship">
                                <label for="r-single-proprietorship">Single Proprietorship</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="r-partnership" name="r-ownership-type" value="Partnership">
                                <label for="r-partnership">Partnership</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="r-corporation" name="r-ownership-type" value="Corporation">
                                <label for="r-corporation">Corporation</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="r-cooperative" name="r-ownership-type" value="Cooperative">
                                <label for="r-cooperative">Cooperative</label>
                            </div>
                        </div>
                    </div>

                    <div class="permit-info" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group">
                            <label for="r-previous-permit">Previous Permit No.:</label>
                            <input type="text" id="r-previous-permit" placeholder="e.g., WPP-XXXX-2024">
                        </div>

                        <div class="form-group">
                            <label for="r-expiry-date">Expiry Date:</label>
                            <input type="date" id="r-expiry-date">
                        </div>
                    </div>
                </div>
                <!-- ======= /I. GENERAL INFORMATION ======= -->

                <!-- ======= II. PLANT DESCRIPTION AND OPERATION (shared UI) ======= -->
                <div class="plant-section">
                    <h3 style="margin:18px 0 10px;">II. PLANT DESCRIPTION AND OPERATION</h3>

                    <div class="form-group">
                        <label class="required">Kind of Wood Processing Plant:</label>
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="radio" id="resawmill" name="plant-type" value="Resawmill">
                                <label for="resawmill">Resawmill</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="saw-mill" name="plant-type" value="Saw Mill">
                                <label for="saw-mill">Saw Mill</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="veneer-plant" name="plant-type" value="Veneer Plant">
                                <label for="veneer-plant">Veneer Plant</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="plywood-plant" name="plant-type" value="Plywood Plant">
                                <label for="plywood-plant">Plywood Plant</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="other-plant" name="plant-type" value="Other">
                                <label for="other-plant">Others (Specify):</label>
                                <input type="text" id="other-plant-specify" style="width: 200px;" placeholder="Specify">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="daily-capacity" class="required">Daily Rated Capacity (per 8-hour shift):</label>
                        <input type="text" id="daily-capacity" placeholder="e.g., 20 m³">
                    </div>

                    <div class="form-group">
                        <label class="required">Machineries and Equipment to be Used (with specifications):</label>
                        <table id="machinery-table">
                            <thead>
                                <tr>
                                    <th>Type of Equipment/Machinery</th>
                                    <th>Brand/Model</th>
                                    <th>Horsepower/Capacity</th>
                                    <th>Quantity</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><input type="text" class="table-input"></td>
                                    <td><input type="text" class="table-input"></td>
                                    <td><input type="text" class="table-input"></td>
                                    <td><input type="number" class="table-input" min="1"></td>
                                    <td><button type="button" class="remove-row-btn">Remove</button></td>
                                </tr>
                            </tbody>
                        </table>
                        <button type="button" class="add-row-btn" id="add-machinery-row">Add Row</button>
                    </div>

                    <div class="form-group">
                        <label class="required">Source of Power Supply:</label>
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="radio" id="electricity" name="power-source" value="Electricity">
                                <label for="electricity">Electricity</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="generator" name="power-source" value="Generator">
                                <label for="generator">Generator</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="other-power" name="power-source" value="Other">
                                <label for="other-power">Others (Specify):</label>
                                <input type="text" id="other-power-specify" style="width: 200px;" placeholder="Specify">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ======= III. SUPPLY CONTRACTS AND RAW MATERIAL REQUIREMENTS (shared UI) ======= -->
                <div class="supply-section">
                    <h3 style="margin:18px 0 10px;">III. SUPPLY CONTRACTS AND RAW MATERIAL REQUIREMENTS</h3>

                    <div class="form-group">
                        <p>The applicant has Log/Lumber Supply Contracts for a minimum period of five (5) years.</p>
                        <table id="supply-table">
                            <thead>
                                <tr>
                                    <th>Supplier Name</th>
                                    <th>Species</th>
                                    <th>Contracted Vol.</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><input type="text" class="table-input"></td>
                                    <td><input type="text" class="table-input"></td>
                                    <td><input type="text" class="table-input"></td>
                                    <td><button type="button" class="remove-row-btn">Remove</button></td>
                                </tr>
                            </tbody>
                        </table>
                        <button type="button" class="add-row-btn" id="add-supply-row">Add Row</button>
                    </div>
                </div>

                <!-- ======= IV. DECLARATION (DIFFERS NEW vs RENEWAL) + SIGNATURE (shared) ======= -->
                <div class="declaration-section">
                    <h3 style="margin:18px 0 10px;">IV. DECLARATION</h3>

                    <!-- New declaration -->
                    <div id="declaration-new" data-permit-for="new" style="display:block;">
                        <p>
                            I,
                            <input type="text" id="declaration-name-new" class="declaration-input" placeholder="Enter your full name">,
                            of legal age, a citizen of the Philippines, with residence at
                            <input type="text" id="declaration-address" class="declaration-input" placeholder="Enter your address">,
                            do hereby certify that the foregoing information and documents are true and correct to the best of my knowledge.
                        </p>
                        <p>
                            I further understand that any false statement or misrepresentation shall be ground for denial, cancellation, or revocation of the permit, without prejudice to legal actions that may be filed against me.
                        </p>
                    </div>

                    <!-- Renewal declaration -->
                    <div id="declaration-renewal" data-permit-for="renewal" style="display:none;">
                        <p>
                            I,
                            <input type="text" id="declaration-name-renewal" class="declaration-input" placeholder="Enter your full name">,
                            hereby certify that the above information is true and correct, and all requirements for renewal are submitted.
                        </p>
                    </div>

                    <!-- Shared signature pad -->
                    <div class="signature-date">
                        <div class="signature-box">
                            <label>Signature of Applicant:</label>
                            <div class="signature-pad-container">
                                <canvas id="signature-pad"></canvas>
                            </div>
                            <div class="signature-actions">
                                <button type="button" class="signature-btn clear-signature" id="clear-signature">Clear</button>
                                <button type="button" class="signature-btn undo-signature" id="undo-signature">Undo</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Download buttons REMOVED (generation moved to submit flow) -->

                <!-- Global Loading Overlay (unified) -->
                <div id="loadingIndicator" style="display:none;position:fixed;inset:0;align-items:center;justify-content:center;background:rgba(0,0,0,.25);z-index:9998">
                    <div class="card" style="background:#fff;padding:18px 22px;border-radius:10px;display:flex;gap:10px;align-items:center;">
                        <span class="loader" style="width:var(--loader-size);height:var(--loader-size);border:2px solid #ddd;border-top-color:#2b6625;border-radius:50%;display:inline-block;animation:spin 0.8s linear infinite;"></span>
                        <span id="loadingMessage">Working...</span>
                    </div>
                </div>

                <!-- ================= NEW PERMIT REQUIREMENTS ================= -->
                <div id="new-requirements" class="requirements-list" style="display:block;">
                    <!-- c -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">a</span>
                                Copy of Certificate of Registration, Articles of Incorporation, Partnership or Cooperation
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-c" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-c" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-c"></div>
                        </div>
                    </div>

                    <!-- d -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">b</span>
                                Authorization issued by the Corporation, Partnership or Association in favor of the person signing the application
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-d" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-d" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-d"></div>
                        </div>
                    </div>

                    <!-- e -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">c</span>
                                Feasibility Study/Business Plan
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-e" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-e" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-e"></div>
                        </div>
                    </div>

                    <!-- f -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">d</span>
                                Business Permit
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-f" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-f" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-f"></div>
                        </div>
                    </div>

                    <!-- g -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">e</span>
                                Environmental Compliance Certificate (ECC)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-g" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-g" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-g"></div>
                        </div>
                    </div>

                    <!-- h -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">f</span>
                                For individual persons, proof of Filipino citizenship (Birth Certificate/Certificate of Naturalization)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-h" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-h" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-h"></div>
                        </div>
                    </div>

                    <!-- i -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">g</span>
                                Evidence of ownership of machines
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-i" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-i" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-i"></div>
                        </div>
                    </div>

                    <!-- j -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">h</span>
                                GIS generated map with geo-tagged photos showing the location of WPP
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-j" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-j" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-j"></div>
                        </div>
                    </div>

                    <!-- k -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">i</span>
                                Certification from the Regional Office that the WPP is not within the illegal logging hotspot area
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-k" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-k" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-k"></div>
                        </div>
                    </div>

                    <!-- l -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">j</span>
                                Proof of sustainable sources of legally cut logs for at least 5 years
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-l" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-l" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-l"></div>
                        </div>
                    </div>

                    <!-- m (supporting docs for NEW) -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">k</span>
                                Supporting Documents
                            </div>
                        </div>

                        <div class="sub-requirement">
                            <p style="margin-bottom:10px;font-weight:500;">1. Original copy of Log/Veneer/Lumber Supply Contracts</p>
                            <div class="file-input-container">
                                <label for="file-o2" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-o2" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-o2"></div>
                        </div>

                        <div class="sub-requirement">
                            <p style="margin-bottom:10px;font-weight:500;">2. 5% Tree Inventory</p>
                            <div class="file-input-container">
                                <label for="file-o3" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-o3" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-o3"></div>
                        </div>

                        <div class="sub-requirement">
                            <p style="margin-bottom:10px;font-weight:500;">3. Electronic Copy of Inventory Data</p>
                            <div class="file-input-container">
                                <label for="file-o4" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-o4" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-o4"></div>
                        </div>

                        <div class="sub-requirement">
                            <p style="margin-bottom:10px;font-weight:500;">4. Validation Report</p>
                            <div class="file-input-container">
                                <label for="file-o5" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-o5" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-o5"></div>
                        </div>

                        <div class="sub-requirement">
                            <p style="margin-bottom:10px;font-weight:500;">5. Copy of CTPO/PTPR and map (if source is private tree plantations)</p>
                            <div class="file-input-container">
                                <label for="file-o7" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-o7" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-o7"></div>
                        </div>
                    </div>
                    <!-- =============== /NEW REQUIREMENTS =============== -->

                </div>

                <!-- ================= RENEWAL REQUIREMENTS ================= -->
                <div id="renewal-requirements" class="requirements-list" style="display:none;">
                    <!-- 1 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">1</span>
                                Previously Approved WPP Permit
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-r1" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-r1" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-r1"></div>
                        </div>
                    </div>

                    <!-- 2 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">2</span>
                                Certificate of Good Standing
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-r2" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-r2" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-r2"></div>
                        </div>
                    </div>

                    <!-- 3 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">3</span>
                                Certificate that the WPP Holder has already installed CCTV Camera
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-r3" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-r3" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-r3"></div>
                        </div>
                    </div>

                    <!-- 4 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">4</span>
                                Monthly Production and Disposition Report
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-r4" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-r4" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-r4"></div>
                        </div>
                    </div>

                    <!-- 5 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">5</span>
                                Certificate of Registration as Log/Veneer/Lumber Importer
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-r5" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-r5" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-r5"></div>
                        </div>
                    </div>

                    <!-- 6 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">6</span>
                                Original Copy of Log/Veneer/Lumber Supply Contracts
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-r6" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-r6" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-r6"></div>
                        </div>
                    </div>

                    <!-- 7 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">7</span>
                                Proof of importation
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-r7" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-r7" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-r7"></div>
                        </div>
                    </div>
                </div>
                <!-- =============== /RENEWAL REQUIREMENTS =============== -->
            </div>

            <div class="form-footer">
                <button class="btn btn-primary" id="submitApplication">
                    <i class="fas fa-paper-plane"></i> Submit Application
                </button>
            </div>
        </div>
    </div>
    <!-- Toast -->
    <div id="profile-notification" style="display:none; position:fixed; top:5px; left:50%; transform:translateX(-50%); background:#323232; color:#fff; padding:16px 32px; border-radius:8px; font-size:1.1rem; z-index:9999; box-shadow:0 2px 8px rgba(0,0,0,0.15); text-align:center; min-width:220px; max-width:90vw;"></div>

    <!-- Single  Modal -->
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

    <!-- Single Modal -->
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
        (function() {
            /* ===================== CONFIG ===================== */
            const SIG_WIDTH = 220; // for doc image tag (px)
            const SIG_HEIGHT = 80; // for doc image tag (px)

            const SAVE_URL = new URL('../backend/users/wood/save_wood.php', window.location.href).toString();
            const PRECHECK_URL = new URL('../backend/users/wood/precheck_wood.php', window.location.href).toString();

            /* ===================== Helpers ===================== */
            const $ = (sel) => document.querySelector(sel);
            const $$ = (sel) => Array.from(document.querySelectorAll(sel));
            const v = (id) => (document.getElementById(id)?.value || '').trim();
            const show = (el, on = true) => el && (el.style.display = on ? 'block' : 'none');
            const wfpRecords = window.__WFP_RECORDS__ || {};
            const wfpSelect = document.getElementById('wfpSelect');
            const wfpDetailsCard = document.getElementById('wfpDetailsCard');
            const wfpDetailFields = {
                number: document.getElementById('wfpDetailNumber'),
                issued: document.getElementById('wfpDetailIssued'),
                expiry: document.getElementById('wfpDetailExpiry'),
                client: document.getElementById('wfpDetailClient'),
                address: document.getElementById('wfpDetailAddress'),
                plant: document.getElementById('wfpDetailPlant'),
                contact: document.getElementById('wfpDetailContact'),
                email: document.getElementById('wfpDetailEmail'),
                ownership: document.getElementById('wfpDetailOwnership'),
                permit: document.getElementById('wfpDetailPermit')
            };
            const existingFileCache = Object.create(null);
            const existingFileFetches = Object.create(null);
            const existingFileTargets = Object.create(null);
            const renewalFileContainers = [
                'uploaded-files-r1',
                'uploaded-files-r2',
                'uploaded-files-r3',
                'uploaded-files-r4',
                'uploaded-files-r5',
                'uploaded-files-r6',
                'uploaded-files-r7'
            ];
            const fileInputByContainer = {
                'uploaded-files-r1': 'file-r1',
                'uploaded-files-r2': 'file-r2',
                'uploaded-files-r3': 'file-r3',
                'uploaded-files-r4': 'file-r4',
                'uploaded-files-r5': 'file-r5',
                'uploaded-files-r6': 'file-r6',
                'uploaded-files-r7': 'file-r7'
            };
            const rFirstInput = document.getElementById('r-first-name');
            const rMiddleInput = document.getElementById('r-middle-name');
            const rLastInput = document.getElementById('r-last-name');
            const rAddressInput = document.getElementById('r-address');
            const rPlantLocationInput = document.getElementById('r-plant-location');
            const rContactInput = document.getElementById('r-contact-number');
            const rEmailInput = document.getElementById('r-email-address');
            const rOwnershipInputs = $$('input[name="r-ownership-type"]');
            const rPrevPermitInput = document.getElementById('r-previous-permit');
            const rExpiryInput = document.getElementById('r-expiry-date');
            const declarationNameRenewalInput = document.getElementById('declaration-name-renewal');
            const plantTypeInputs = $$('input[name="plant-type"]');
            const powerInputs = $$('input[name="power-source"]');
            const otherPlantInput = document.getElementById('other-plant-specify');
            const otherPowerInput = document.getElementById('other-power-specify');
            const dailyCapacityInput = document.getElementById('daily-capacity');
            const machTbody = document.querySelector('#machinery-table tbody');
            const supplyTbody = document.querySelector('#supply-table tbody');

            function toast(msg) {
                const n = document.getElementById('profile-notification');
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

            const friendlyDate = (value) => {
                if (!value) return '--';
                const parsed = new Date(value);
                if (!isNaN(parsed)) {
                    return parsed.toLocaleDateString(void 0, {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric'
                    });
                }
                return value;
            };

            const activePermitType = () =>
                (document.querySelector('.permit-type-btn.active')?.getAttribute('data-type') || 'new');

            /* ===================== Permit type toggle ===================== */
            const btnNew = document.querySelector('.permit-type-btn[data-type="new"]');
            const btnRenewal = document.querySelector('.permit-type-btn[data-type="renewal"]');
            const clientModeEl = document.getElementById('clientMode');
            const btnExisting = document.getElementById('btnExisting');
            const btnNewClient = document.getElementById('btnNew');
            const existingClientRow = document.getElementById('existingClientRow');
            const newClientRow = document.getElementById('newClientRow');
            const clientPick = document.getElementById('clientPick');
            const clientPickError = document.getElementById('clientPickError');
            const clientModeToggle = document.getElementById('clientModeToggle');
            const clientModeHint = document.getElementById('clientModeHint');
            const wfpPicker = document.getElementById('wfpPicker');
            const firstNameInput = document.getElementById('new-first-name');
            const middleNameInput = document.getElementById('new-middle-name');
            const lastNameInput = document.getElementById('new-last-name');
            const declarationNameInput = document.getElementById('declaration-name-new');
            const declarationAddressInput = document.getElementById('declaration-address');
            const contactInput = document.getElementById('new-contact-number');
            const nameInputs = [firstNameInput, middleNameInput, lastNameInput];
            const hasClientOptions = !!clientPick;
            let manualClientCache = {
                first: firstNameInput?.value || '',
                middle: middleNameInput?.value || '',
                last: lastNameInput?.value || '',
                declarationName: declarationNameInput?.value || '',
                declarationAddress: declarationAddressInput?.value || '',
                contact: contactInput?.value || ''
            };

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

            function setInputValue(input, value, opts = {}) {
                if (!input) return;
                input.value = value || '';
                if (!opts.silent) {
                    input.dispatchEvent(new Event('input', {
                        bubbles: true
                    }));
                    input.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                }
            }

            function setRenewalNames(first, middle, last, opts = {}) {
                setInputValue(rFirstInput, first, opts);
                setInputValue(rMiddleInput, middle, opts);
                setInputValue(rLastInput, last, opts);
            }

            function setOwnershipValue(value) {
                const target = (value || '').toLowerCase();
                let matched = false;
                rOwnershipInputs.forEach((radio) => {
                    const isMatch = target && radio.value.toLowerCase() === target;
                    radio.checked = isMatch;
                    if (isMatch) matched = true;
                });
                if (!matched) {
                    rOwnershipInputs.forEach((radio) => (radio.checked = false));
                }
            }

            function setPlantTypeValue(raw) {
                const val = (raw || '').trim();
                let matched = false;
                plantTypeInputs.forEach((radio) => {
                    const isMatch = val && radio.value.toLowerCase() === val.toLowerCase();
                    radio.checked = isMatch;
                    if (isMatch) matched = true;
                });
                if (matched) {
                    if (otherPlantInput) otherPlantInput.value = '';
                    return;
                }
                if (!val) {
                    plantTypeInputs.forEach((radio) => (radio.checked = false));
                    if (otherPlantInput) otherPlantInput.value = '';
                    return;
                }
                const otherRadio = document.getElementById('other-plant');
                if (otherRadio) otherRadio.checked = true;
                if (otherPlantInput) {
                    otherPlantInput.value = val.replace(/^other\s*[-:]\s*/i, '').trim() || val;
                }
            }

            function setPowerSourceValue(raw) {
                const val = (raw || '').trim();
                let matched = false;
                powerInputs.forEach((radio) => {
                    const isMatch = val && radio.value.toLowerCase() === val.toLowerCase();
                    radio.checked = isMatch;
                    if (isMatch) matched = true;
                });
                if (matched) {
                    if (otherPowerInput) otherPowerInput.value = '';
                    return;
                }
                if (!val) {
                    powerInputs.forEach((radio) => (radio.checked = false));
                    if (otherPowerInput) otherPowerInput.value = '';
                    return;
                }
                const otherRadio = document.getElementById('other-power');
                if (otherRadio) otherRadio.checked = true;
                if (otherPowerInput) {
                    otherPowerInput.value = val.replace(/^other\s*[-:]\s*/i, '').trim() || val;
                }
            }

            function createMachineryRow(values = []) {
                const tr = document.createElement('tr');
                const types = ['text', 'text', 'text', 'number'];
                for (let i = 0; i < 4; i++) {
                    const td = document.createElement('td');
                    const input = document.createElement('input');
                    input.type = types[i];
                    input.className = 'table-input';
                    if (types[i] === 'number') input.min = '1';
                    input.value = values[i] ?? '';
                    td.appendChild(input);
                    tr.appendChild(td);
                }
                const actionTd = document.createElement('td');
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'remove-row-btn';
                btn.textContent = 'Remove';
                btn.addEventListener('click', () => tr.remove());
                actionTd.appendChild(btn);
                tr.appendChild(actionTd);
                return tr;
            }

            function createSupplyRow(values = []) {
                const tr = document.createElement('tr');
                for (let i = 0; i < 3; i++) {
                    const td = document.createElement('td');
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.className = 'table-input';
                    input.value = values[i] ?? '';
                    td.appendChild(input);
                    tr.appendChild(td);
                }
                const actionTd = document.createElement('td');
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'remove-row-btn';
                btn.textContent = 'Remove';
                btn.addEventListener('click', () => tr.remove());
                actionTd.appendChild(btn);
                tr.appendChild(actionTd);
                return tr;
            }

            function setMachineryRows(rows = []) {
                if (!machTbody) return;
                const data = Array.isArray(rows) && rows.length ? rows : [
                    []
                ];
                machTbody.innerHTML = '';
                data.forEach((row) => {
                    machTbody.appendChild(createMachineryRow(Array.isArray(row) ? row : []));
                });
            }

            function setSupplyRows(rows = []) {
                if (!supplyTbody) return;
                const data = Array.isArray(rows) && rows.length ? rows : [
                    []
                ];
                supplyTbody.innerHTML = '';
                data.forEach((row) => {
                    supplyTbody.appendChild(createSupplyRow(Array.isArray(row) ? row : []));
                });
            }

            function populateNameInputs(first, middle, last, opts = {}) {
                const values = [first, middle, last];
                nameInputs.forEach((input, idx) => setInputValue(input, values[idx], opts));
            }

            function fileNameFromEntry(entry) {
                if (!entry) return '';
                if (entry.filename) return entry.filename;
                if (entry.url) {
                    try {
                        const clean = entry.url.split('?')[0];
                        const last = clean.substring(clean.lastIndexOf('/') + 1);
                        return decodeURIComponent(last) || entry.label || '';
                    } catch {
                        return entry.label || '';
                    }
                }
                return entry.label || '';
            }

            function clearRenewalFilePreviews() {
                renewalFileContainers.forEach((containerId) => {
                    const holder = document.getElementById(containerId);
                    if (holder) holder.innerHTML = '';
                    const inputId = fileInputByContainer[containerId];
                    if (inputId) {
                        const input = document.getElementById(inputId);
                        if (input) {
                            input.value = '';
                            const nameSpan = input.parentElement?.querySelector('.file-name');
                            if (nameSpan) nameSpan.textContent = 'No file chosen';
                        }
                        delete existingFileCache[inputId];
                        delete existingFileFetches[inputId];
                        delete existingFileTargets[inputId];
                    }
                });
            }

            function applyExistingFileToInput(fieldId) {
                const file = existingFileCache[fieldId];
                if (!file) return;
                const input = document.getElementById(fieldId);
                if (!input) return;
                if (typeof DataTransfer === 'undefined') return;
                try {
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    input.files = dt.files;
                } catch (err) {
                    console.warn('Unable to attach existing file', err);
                }
            }

            function queueExistingFileDownload(fieldId, entry) {
                if (!fieldId || !entry || !entry.url) {
                    delete existingFileCache[fieldId];
                    delete existingFileFetches[fieldId];
                    delete existingFileTargets[fieldId];
                    return;
                }
                const url = entry.url;
                existingFileTargets[fieldId] = url;
                const fetchPromise = fetch(url, {
                    credentials: 'include'
                }).then((res) => {
                    if (!res.ok) throw new Error(`Failed to fetch ${url}`);
                    return res.blob();
                }).then((blob) => {
                    if (existingFileTargets[fieldId] !== url) return;
                    const filename = fileNameFromEntry(entry) || `${fieldId}.dat`;
                    existingFileCache[fieldId] = new File([blob], filename, {
                        type: blob.type || 'application/octet-stream'
                    });
                    applyExistingFileToInput(fieldId);
                }).catch((err) => {
                    console.error(err);
                    delete existingFileCache[fieldId];
                }).finally(() => {
                    delete existingFileFetches[fieldId];
                });
                existingFileFetches[fieldId] = fetchPromise;
            }

            function renderRenewalFiles(data = {}) {
                clearRenewalFilePreviews();
                const fileMap = data.files || {};
                Object.entries(fileMap).forEach(([containerId, entries]) => {
                    const holder = document.getElementById(containerId);
                    if (!holder) return;
                    const list = Array.isArray(entries) ? entries : [entries];
                    const frag = document.createDocumentFragment();
                    list.forEach((entry) => {
                        if (!entry || !entry.url) return;
                        const link = document.createElement('a');
                        link.href = entry.url;
                        link.target = '_blank';
                        link.rel = 'noopener noreferrer';
                        link.textContent = entry.label || entry.url;
                        link.style.display = 'block';
                        frag.appendChild(link);
                    });
                    if (frag.childNodes.length) {
                        holder.appendChild(frag);
                        const inputId = fileInputByContainer[containerId];
                        if (inputId && list[0]) {
                            const input = document.getElementById(inputId);
                            const nameSpan = input?.parentElement?.querySelector('.file-name');
                            if (nameSpan) nameSpan.textContent = fileNameFromEntry(list[0]) || 'Existing file detected';
                            queueExistingFileDownload(inputId, list[0]);
                        }
                    }
                });
            }

            async function waitForExistingFiles() {
                const pending = Object.values(existingFileFetches).filter(Boolean);
                if (!pending.length) return;
                try {
                    await Promise.allSettled(pending);
                } catch (err) {
                    console.warn('Some existing files failed to load', err);
                }
            }

            function updateWfpDetailsCard(record) {
                if (!wfpDetailsCard) return;
                if (!record) {
                    wfpDetailsCard.style.display = 'none';
                    Object.values(wfpDetailFields).forEach((el) => {
                        if (el) el.textContent = '--';
                    });
                    return;
                }
                const info = record.info || {};
                const client = record.client || {};
                const fullName = [client.first_name || client.first, client.middle_name || client.middle, client.last_name || client.last].filter(Boolean).join(' ').trim() || '--';
                wfpDetailsCard.style.display = 'block';
                if (wfpDetailFields.number) wfpDetailFields.number.textContent = record.wfp_number || info.permit_number || '--';
                if (wfpDetailFields.issued) wfpDetailFields.issued.textContent = `Issued ${friendlyDate(record.issue_date)}`;
                if (wfpDetailFields.expiry) wfpDetailFields.expiry.textContent = `Expires ${friendlyDate(info.expiry_date || record.expiry_date)}`;
                if (wfpDetailFields.client) wfpDetailFields.client.textContent = fullName;
                if (wfpDetailFields.address) wfpDetailFields.address.textContent = info.address || '--';
                if (wfpDetailFields.plant) wfpDetailFields.plant.textContent = info.plant_location || '--';
                if (wfpDetailFields.contact) wfpDetailFields.contact.textContent = info.contact_number || '--';
                if (wfpDetailFields.email) wfpDetailFields.email.textContent = info.email_address || '--';
                if (wfpDetailFields.ownership) wfpDetailFields.ownership.textContent = info.ownership_type || '--';
                if (wfpDetailFields.permit) wfpDetailFields.permit.textContent = info.permit_number || record.wfp_number || '--';
            }

            function applyWfpRecord(record) {
                if (!record) return;
                const info = record.info || {};
                const client = record.client || {};
                const first = client.first_name || client.first || '';
                const middle = client.middle_name || client.middle || '';
                const last = client.last_name || client.last || '';
                setRenewalNames(first, middle, last, {
                    silent: true
                });
                setInputValue(rAddressInput, info.address || '', {
                    silent: true
                });
                setInputValue(rPlantLocationInput, info.plant_location || '', {
                    silent: true
                });
                setInputValue(rContactInput, info.contact_number || '', {
                    silent: true
                });
                setInputValue(rEmailInput, info.email_address || '', {
                    silent: true
                });
                setOwnershipValue(info.ownership_type || '');
                setInputValue(rPrevPermitInput, info.permit_number || record.wfp_number || '', {
                    silent: true
                });
                setInputValue(rExpiryInput, toYMD(info.expiry_date || record.expiry_date || ''), {
                    silent: true
                });
                setInputValue(declarationNameRenewalInput, info.declaration_name || [first, last].filter(Boolean).join(' ').trim(), {
                    silent: true
                });
                setInputValue(declarationAddressInput, info.declaration_address || '', {
                    silent: true
                });
                setInputValue(dailyCapacityInput, info.daily_capacity || '', {
                    silent: true
                });
                setPlantTypeValue(info.plant_type || '');
                setPowerSourceValue(info.power_source || '');
                setMachineryRows(info.machinery_rows || []);
                setSupplyRows(info.supplier_rows || []);
                updateWfpDetailsCard(record);
                renderRenewalFiles(record);
            }

            wfpSelect?.addEventListener('change', () => {
                const key = wfpSelect.value || '';
                const record = key && wfpRecords[key] ? wfpRecords[key] : null;
                if (record) {
                    applyWfpRecord(record);
                } else {
                    updateWfpDetailsCard(null);
                    clearRenewalFilePreviews();
                }
            });

            function setDeclarationFields(name, address, opts = {}) {
                setInputValue(declarationNameInput, name, opts);
                setInputValue(declarationAddressInput, address, opts);
            }

            function setContactValue(value, opts = {}) {
                setInputValue(contactInput, value, opts);
            }

            function applyClientPickValues(showError = false) {
                if (!clientPick) return;
                if (!clientPick.value) {
                    if (showError) setClientPickError('Please select an existing client.');
                    else setClientPickError('');
                    populateNameInputs('', '', '', {
                        silent: true
                    });
                    setDeclarationFields('', '', {
                        silent: true
                    });
                    setContactValue('', {
                        silent: true
                    });
                    chosenClientName = null;
                    chosenClientId = null;
                    return;
                }
                const opt = clientPick.options[clientPick.selectedIndex];
                const first = opt?.dataset?.first || '';
                const middle = opt?.dataset?.middle || '';
                const last = opt?.dataset?.last || '';
                const address = opt?.dataset?.address || '';
                const contact = opt?.dataset?.contact || '';
                populateNameInputs(first, middle, last);
                const fullName = [first, middle, last].filter(Boolean).join(' ');
                setDeclarationFields(fullName, address);
                setContactValue(contact);
                setClientPickError('');
                chosenClientName = {
                    first,
                    middle,
                    last
                };
            }

            function setClientMode(mode = 'new') {
                if (!clientModeEl) return;
                const isExisting = mode === 'existing';
                clientModeEl.value = isExisting ? 'existing' : 'new';

                if (existingClientRow) existingClientRow.style.display = isExisting ? '' : 'none';
                if (newClientRow) newClientRow.style.display = isExisting ? 'none' : '';
                if (btnExisting) btnExisting.style.display = isExisting ? 'none' : 'inline-flex';
                if (btnNewClient) btnNewClient.style.display = isExisting ? 'inline-flex' : 'none';

                nameInputs.forEach((input) => {
                    if (!input) return;
                    input.classList.toggle('readonly-input', isExisting);
                    if (isExisting) input.setAttribute('readonly', 'readonly');
                    else input.removeAttribute('readonly');
                });

                if (isExisting) {
                    manualClientCache = {
                        first: firstNameInput?.value || '',
                        middle: middleNameInput?.value || '',
                        last: lastNameInput?.value || '',
                        declarationName: declarationNameInput?.value || '',
                        declarationAddress: declarationAddressInput?.value || '',
                        contact: contactInput?.value || ''
                    };
                    applyClientPickValues(false);
                } else {
                    setClientPickError('');
                    if (clientPick) clientPick.value = '';
                    populateNameInputs(manualClientCache.first || '', manualClientCache.middle || '', manualClientCache.last || '', {
                        silent: true
                    });
                    setDeclarationFields(manualClientCache.declarationName || '', manualClientCache.declarationAddress || '', {
                        silent: true
                    });
                    setContactValue(manualClientCache.contact || '', {
                        silent: true
                    });
                    chosenClientName = null;
                    chosenClientId = null;
                }
            }

            btnExisting?.addEventListener('click', () => setClientMode('existing'));
            btnNewClient?.addEventListener('click', () => setClientMode('new'));
            clientPick?.addEventListener('change', () => {
                if ((clientModeEl?.value || 'new') === 'existing') {
                    applyClientPickValues(true);
                } else {
                    setClientPickError('');
                }
            });
            setClientMode(clientModeEl?.value || 'new');

            function setType(type, opts = {}) {
                const skipWfpChange = !!opts.skipWfpChange;
                const isNew = type === 'new';
                btnNew?.classList.toggle('active', isNew);
                btnRenewal?.classList.toggle('active', !isNew);

                show(document.getElementById('general-new'), isNew);
                show(document.getElementById('general-renewal'), !isNew);
                const showClientToggle = isNew && hasClientOptions;
                if (clientModeToggle) clientModeToggle.style.display = showClientToggle ? 'flex' : 'none';
                if (clientModeHint) clientModeHint.style.display = showClientToggle ? 'block' : 'none';
                if (!showClientToggle) {
                    setClientMode('new');
                }
                if (wfpPicker) wfpPicker.style.display = isNew ? 'none' : 'block';
                show(document.getElementById('declaration-new'), isNew);
                show(document.getElementById('declaration-renewal'), !isNew);
                show(document.getElementById('new-requirements'), isNew);
                show(document.getElementById('renewal-requirements'), !isNew);
                if (!isNew) {
                    if (!skipWfpChange) {
                        wfpSelect?.dispatchEvent(new Event('change'));
                    }
                } else {
                    if (wfpSelect) wfpSelect.value = '';
                    clearRenewalFilePreviews();
                    updateWfpDetailsCard(null);
                }
            }
            btnNew?.addEventListener('click', () => setType('new'));
            btnRenewal?.addEventListener('click', () => setType('renewal'));
            setType('new');

            /* ===================== Mobile menu toggle ===================== */
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');
            mobileToggle?.addEventListener('click', () => {
                const isActive = navContainer.classList.toggle('active');
                document.body.style.overflow = isActive ? 'hidden' : '';
            });

            /* ===================== Dynamic tables ===================== */
            document.getElementById('add-machinery-row')?.addEventListener('click', () => {
                if (!machTbody) return;
                machTbody.appendChild(createMachineryRow());
            });
            machTbody?.querySelectorAll('.remove-row-btn').forEach((b) =>
                b.addEventListener('click', () => b.closest('tr')?.remove())
            );

            document.getElementById('add-supply-row')?.addEventListener('click', () => {
                if (!supplyTbody) return;
                supplyTbody.appendChild(createSupplyRow());
            });
            supplyTbody?.querySelectorAll('.remove-row-btn').forEach((b) =>
                b.addEventListener('click', () => b.closest('tr')?.remove())
            );

            /* ===================== File input name preview ===================== */
            document.addEventListener('change', (e) => {
                const input = e.target;
                if (input?.classList?.contains('file-input')) {
                    const nameSpan = input.parentElement?.querySelector('.file-name');
                    if (nameSpan) nameSpan.textContent = input.files?.[0]?.name || 'No file chosen';
                    if (input.id) {
                        delete existingFileCache[input.id];
                        delete existingFileFetches[input.id];
                        delete existingFileTargets[input.id];
                    }
                }
            });

            /* ===================== Signature pad (with undo) ===================== */
            const canvas = document.getElementById('signature-pad');
            const clearBtn = document.getElementById('clear-signature');
            const undoBtn = document.getElementById('undo-signature');
            let isDrawing = false,
                lastX = 0,
                lastY = 0;
            let strokes = [],
                currentStroke = [];

            function ctxStyle(ctx) {
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#111';
            }

            function repaint(bg = true) {
                if (!canvas) return;
                const ctx = canvas.getContext('2d');
                const cssW = canvas.clientWidth || 300;
                const cssH = canvas.clientHeight || 200;
                if (bg) {
                    ctx.fillStyle = '#fff';
                    ctx.fillRect(0, 0, cssW, cssH);
                }
                ctxStyle(ctx);
                for (const s of strokes) {
                    if (s.length < 2) continue;
                    ctx.beginPath();
                    ctx.moveTo(s[0].x, s[0].y);
                    for (let i = 1; i < s.length; i++) ctx.lineTo(s[i].x, s[i].y);
                    ctx.stroke();
                }
            }

            function resizeCanvas() {
                if (!canvas) return;
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                const cssW = canvas.clientWidth || 300;
                const cssH = 200;
                canvas.width = Math.floor(cssW * ratio);
                canvas.height = Math.floor(cssH * ratio);
                const ctx = canvas.getContext('2d');
                ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
                repaint(true);
            }
            resizeCanvas();
            window.addEventListener('resize', resizeCanvas);

            function getPos(e) {
                const rect = canvas.getBoundingClientRect();
                const t = e.touches ? e.touches[0] : null;
                const cx = t ? t.clientX : e.clientX;
                const cy = t ? t.clientY : e.clientY;
                return {
                    x: cx - rect.left,
                    y: cy - rect.top
                };
            }

            function startDraw(e) {
                if (!canvas) return;
                isDrawing = true;
                currentStroke = [];
                const {
                    x,
                    y
                } = getPos(e);
                lastX = x;
                lastY = y;
                currentStroke.push({
                    x,
                    y
                });
                e.preventDefault?.();
            }

            function draw(e) {
                if (!isDrawing || !canvas) return;
                const ctx = canvas.getContext('2d');
                const {
                    x,
                    y
                } = getPos(e);
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                ctx.lineTo(x, y);
                ctx.stroke();
                lastX = x;
                lastY = y;
                currentStroke.push({
                    x,
                    y
                });
                e.preventDefault?.();
            }

            function endDraw() {
                if (!isDrawing) return;
                isDrawing = false;
                if (currentStroke.length > 1) strokes.push(currentStroke);
                currentStroke = [];
            }

            if (canvas) {
                // mouse
                canvas.addEventListener('mousedown', startDraw);
                canvas.addEventListener('mousemove', draw);
                window.addEventListener('mouseup', endDraw);
                // touch
                canvas.addEventListener('touchstart', startDraw, {
                    passive: false
                });
                canvas.addEventListener('touchmove', draw, {
                    passive: false
                });
                window.addEventListener('touchend', endDraw);
                // buttons
                clearBtn?.addEventListener('click', () => {
                    strokes = [];
                    repaint(true);
                });
                undoBtn?.addEventListener('click', () => {
                    if (strokes.length) {
                        strokes.pop();
                        repaint(true);
                    }
                });
            }

            function hasSignature() {
                return strokes && strokes.length > 0 && strokes.some(s => s.length > 1);
            }

            function getSignatureDataURL() {
                if (!canvas || !hasSignature()) return '';
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                const cssW = canvas.width / ratio;
                const cssH = canvas.height / ratio;
                const off = document.createElement('canvas');
                off.width = Math.floor(cssW * ratio);
                off.height = Math.floor(cssH * ratio);
                const octx = off.getContext('2d');
                octx.setTransform(ratio, 0, 0, ratio, 0, 0);
                octx.fillStyle = '#fff';
                octx.fillRect(0, 0, cssW, cssH);
                ctxStyle(octx);
                for (const s of strokes) {
                    if (s.length < 2) continue;
                    octx.beginPath();
                    octx.moveTo(s[0].x, s[0].y);
                    for (let i = 1; i < s.length; i++) octx.lineTo(s[i].x, s[i].y);
                    octx.stroke();
                }
                return off.toDataURL('image/png');
            }

            /* ===================== Value helpers ===================== */
            function buildRows(tbody, colsExpected) {
                const trs = tbody ? Array.from(tbody.querySelectorAll('tr')) : [];
                if (!trs.length) return '';
                return trs.map(row => {
                    const ins = Array.from(row.querySelectorAll('input'));
                    const tds = [];
                    for (let i = 0; i < colsExpected; i++) tds.push(`<td>${(ins[i]?.value || '').toString()}</td>`);
                    return `<tr>${tds.join('')}</tr>`;
                }).join('');
            }

            function tableToRowsJSON(tbody, colsExpected) {
                const trs = tbody ? Array.from(tbody.querySelectorAll('tr')) : [];
                const rows = trs.map(row => {
                    const ins = Array.from(row.querySelectorAll('input')).slice(0, colsExpected);
                    return ins.map(i => (i.value || '').trim());
                });
                // keep rows with at least one non-empty cell
                const filtered = rows.filter(r => r.some(cell => cell));
                return JSON.stringify(filtered);
            }

            function plantTypeValue() {
                let val = '';
                document.querySelectorAll('input[name="plant-type"]').forEach((r) => {
                    if (r.checked) {
                        val = r.value;
                        if (val === 'Other') val += ' - ' + (v('other-plant-specify') || '');
                    }
                });
                return val;
            }

            function powerSourceValue() {
                let val = '';
                document.querySelectorAll('input[name="power-source"]').forEach((r) => {
                    if (r.checked) {
                        val = r.value;
                        if (val === 'Other') val += ' - ' + (v('other-power-specify') || '');
                    }
                });
                return val;
            }

            /* ===================== MHTML (Word-friendly) ===================== */
            function makeMHTML(html, parts = []) {
                const boundary = '----=_NextPart_' + Date.now().toString(16);
                const header = [
                    'MIME-Version: 1.0',
                    `Content-Type: multipart/related; type="text/html"; boundary="${boundary}"`,
                    'X-MimeOLE: Produced By Microsoft MimeOLE',
                    '',
                    `--${boundary}`,
                    'Content-Type: text/html; charset="utf-8"',
                    'Content-Transfer-Encoding: 8bit',
                    '',
                    html
                ].join('\r\n');

                const bodyParts = parts.map(p => {
                    const wrapped = p.base64.replace(/.{1,76}/g, '$&\r\n');
                    return [
                        '',
                        `--${boundary}`,
                        `Content-Location: ${p.location}`,
                        'Content-Transfer-Encoding: base64',
                        `Content-Type: ${p.contentType}`,
                        '',
                        wrapped
                    ].join('\r\n');
                }).join('');

                return header + bodyParts + `\r\n--${boundary}--`;
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

            /* ===================== Document builders ===================== */
            function buildNewDocHTML(sigLocation, includeSignature) {
                const first = v('new-first-name');
                const middle = v('new-middle-name');
                const last = v('new-last-name');
                const applicantName = [first, middle, last].filter(Boolean).join(' ');

                const businessAddress = v('new-business-address');
                const plantLocation = v('new-plant-location');
                const contactNumber = v('new-contact-number');
                const emailAddress = v('new-email-address');
                const ownershipType = document.querySelector('input[name="new-ownership-type"]:checked')?.value || '';

                const plantType = plantTypeValue();
                const dailyCapacity = v('daily-capacity');
                const machineryRowsHTML = buildRows(machTbody, 4) || `<tr><td colspan="4"></td></tr>`;
                const powerSource = powerSourceValue();
                const supplyRowsHTML = buildRows(supplyTbody, 3) || `<tr><td colspan="3"></td></tr>`;

                const declarationName = v('declaration-name-new') || applicantName;
                const declarationAddress = v('declaration-address');

                const sigBlock = includeSignature ?
                    `<img src="${sigLocation}" width="${SIG_WIDTH}" height="${SIG_HEIGHT}" style="display:block;margin:8px 0 6px 0;border:1px solid #000;" alt="Signature">` :
                    '';

                return `
<html xmlns:o="urn:schemas-microsoft-com:office:office" 
      xmlns:w="urn:schemas-microsoft-com:office:word" 
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="UTF-8">
<title>Wood Processing Plant Permit Application</title>
<style>
  body, div, p { line-height: 1.8; font-family: Arial; font-size: 11pt; margin: 0; padding: 0; }
  .section-title { font-weight: normal; margin: 15pt 0 6pt 0; }
  .info-line { margin: 12pt 0; }
  .underline { display: inline-block; min-width: 300px; border-bottom: 1px solid #000; padding: 0 5px; margin: 0 5px; }
  .bold { font-weight: bold; }
  table { width: 100%; border-collapse: collapse; margin: 12pt 0; }
  table, th, td { border: 1px solid #000; }
  th, td { padding: 8px; text-align: left; }
  th { background-color: #f2f2f2; }
  .signature-line { margin-top: 12pt; border-top: 1px solid #000; width: 50%; padding-top: 3pt; }
</style>
</head>
<body>
  <div style="text-align:center;">
    <p class="bold">Republic of the Philippines</p>
    <p class="bold">Department of Environment and Natural Resources</p>
    <p>Community Environment and Natural Resources Office (CENRO)</p>
    <p>Argao, Cebu</p>
  </div>

  <h3 style="text-align:center; margin-bottom: 20px;">Application for Wood Processing Plant Permit</h3>

  <p class="section-title">I. GENERAL INFORMATION</p>
  <p class="info-line">Name of Applicant / Company: <span class="underline">${applicantName}</span></p>
  <p class="info-line">Complete Business Address: <span class="underline">${businessAddress}</span></p>
  <p class="info-line">Plant Location (Barangay/Municipality/Province): <span class="underline">${plantLocation}</span></p>
  <p class="info-line">Contact Number(s): <span class="underline">${contactNumber}</span> Email Address: <span class="underline">${emailAddress}</span></p>
  <p class="info-line">Type of Ownership: <span class="underline">${ownershipType}</span></p>

  <p class="section-title">II. PLANT DESCRIPTION AND OPERATION</p>
  <p class="info-line">Kind of Wood Processing Plant: <span class="underline">${plantType}</span></p>
  <p class="info-line">Daily Rated Capacity (per 8-hour shift): <span class="underline">${dailyCapacity}</span></p>

  <p class="info-line">Machineries and Equipment to be Used (with specifications):</p>
  <table>
    <thead>
      <tr>
        <th>Type of Equipment/Machinery</th>
        <th>Brand/Model</th>
        <th>Horsepower/Capacity</th>
        <th>Quantity</th>
      </tr>
    </thead>
    <tbody>
      ${machineryRowsHTML}
    </tbody>
  </table>

  <p class="info-line">Source of Power Supply: <span class="underline">${powerSource}</span></p>

  <p class="section-title">III. SUPPLY CONTRACTS AND RAW MATERIAL REQUIREMENTS</p>
  <p class="info-line">The applicant has Log/Lumber Supply Contracts for a minimum period of five (5) years.</p>
  <table>
    <thead>
      <tr>
        <th>Supplier Name</th>
        <th>Species</th>
        <th>Contracted Vol.</th>
      </tr>
    </thead>
    <tbody>
      ${supplyRowsHTML}
    </tbody>
  </table>

  <p class="section-title">IV. DECLARATION AND SIGNATURE</p>
  <div class="declaration">
    <p>I, <span class="underline">${declarationName}</span>, of legal age, a citizen of the Philippines, with residence at <span class="underline">${declarationAddress}</span>, do hereby certify that the foregoing information and documents are true and correct to the best of my knowledge.</p>
    <p>I further understand that any false statement or misrepresentation shall be ground for denial, cancellation, or revocation of the permit, without prejudice to legal actions that may be filed against me.</p>
    <div style="margin-top: 16px;">
      ${sigBlock}
      <div class="signature-line"></div>
      <p>Signature of Applicant</p>
    </div>
  </div>
</body>
</html>`.trim();
            }

            function buildRenewalDocHTML(sigLocation, includeSignature) {
                const first = v('r-first-name');
                const middle = v('r-middle-name');
                const last = v('r-last-name');
                const applicantName = [first, middle, last].filter(Boolean).join(' ');

                const address = v('r-address');
                const plantLocation = v('r-plant-location');
                const contactNumber = v('r-contact-number');
                const emailAddress = v('r-email-address');
                const ownershipType = document.querySelector('input[name="r-ownership-type"]:checked')?.value || '';

                const previousPermit = v('r-previous-permit');
                const theExpiryDate = v('r-expiry-date');

                const plantType = plantTypeValue();
                const dailyCapacity = v('daily-capacity');
                const machineryRowsHTML = buildRows(machTbody, 4) || `<tr><td colspan="4"></td></tr>`;
                const powerSource = powerSourceValue();
                const supplyRowsHTML = buildRows(supplyTbody, 3) || `<tr><td colspan="3"></td></tr>`;

                const declarationName = v('declaration-name-renewal') || applicantName;

                const sigBlock = includeSignature ?
                    `<img src="${sigLocation}" width="${SIG_WIDTH}" height="${SIG_HEIGHT}" style="display:block;margin:8px 0 6px 0;border:1px solid #000;" alt="Signature">` :
                    '';

                return `
<html xmlns:o="urn:schemas-microsoft-com:office:office" 
      xmlns:w="urn:schemas-microsoft-com:office:word" 
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="UTF-8">
<title>Renewal of Wood Processing Plant Permit Application</title>
<style>
  body, div, p { line-height: 1.8; font-family: Arial; font-size: 11pt; margin: 0; padding: 0; }
  .section-title { font-weight: normal; margin: 15pt 0 6pt 0; }
  .info-line { margin: 12pt 0; }
  .underline { display: inline-block; min-width: 300px; border-bottom: 1px solid #000; padding: 0 5px; margin: 0 5px; }
  .bold { font-weight: bold; }
  table { width: 100%; border-collapse: collapse; margin: 12pt 0; }
  table, th, td { border: 1px solid #000; }
  th, td { padding: 8px; text-align: left; }
  th { background-color: #f2f2f2; }
  .signature-line { margin-top: 12pt; border-top: 1px solid #000; width: 50%; padding-top: 3pt; }
</style>
</head>
<body>
  <div style="text-align:center;">
    <p class="bold">Republic of the Philippines</p>
    <p class="bold">Department of Environment and Natural Resources</p>
    <p>Community Environment and Natural Resources Office (CENRO)</p>
    <p>Argao, Cebu</p>
  </div>

  <h3 style="text-align: center; margin-bottom: 20px;">Application for Renewal of Wood Processing Plant Permit</h3>

  <p class="section-title">I. GENERAL INFORMATION</p>
  <p class="info-line">Name of Applicant / Company: <span class="underline">${applicantName}</span></p>
  <p class="info-line">Address: <span class="underline">${address}</span></p>
  <p class="info-line">Plant Location: <span class="underline">${plantLocation}</span></p>
  <p class="info-line">Contact Number: <span class="underline">${contactNumber}</span> Email: <span class="underline">${emailAddress}</span></p>
  <p class="info-line">Type of Ownership: <span class="underline">${ownershipType}</span></p>
  <p class="info-line">Previous Permit No.: <span class="underline">${previousPermit}</span> Expiry Date: <span class="underline">${theExpiryDate}</span></p>

  <p class="section-title">II. PLANT DESCRIPTION AND OPERATION</p>
  <p class="info-line">Kind of Wood Processing Plant: <span class="underline">${plantType}</span></p>
  <p class="info-line">Daily Rated Capacity (per 8-hour shift): <span class="underline">${dailyCapacity}</span></p>

  <p class="info-line">Machineries and Equipment to be Used (with specifications):</p>
  <table>
    <thead>
      <tr>
        <th>Type of Equipment/Machinery</th>
        <th>Brand/Model</th>
        <th>Horsepower/Capacity</th>
        <th>Quantity</th>
      </tr>
    </thead>
    <tbody>
      ${machineryRowsHTML}
    </tbody>
  </table>

  <p class="info-line">Source of Power Supply: <span class="underline">${powerSource}</span></p>

  <p class="section-title">III. SUPPLY CONTRACTS AND RAW MATERIAL REQUIREMENTS</p>
  <p class="info-line">The applicant has Log/Lumber Supply Contracts for a minimum period of five (5) years.</p>
  <table>
    <thead>
      <tr>
        <th>Supplier Name</th>
        <th>Species</th>
        <th>Contracted Vol.</th>
      </tr>
    </thead>
    <tbody>
      ${supplyRowsHTML}
    </tbody>
  </table>

  <p class="section-title">IV. DECLARATION</p>
  <div class="declaration">
    <p>I, <span class="underline">${declarationName}</span>, hereby certify that the above information is true and correct, and all requirements for renewal are submitted.</p>
    <div style="margin-top: 16px;">
      ${sigBlock}
      <div class="signature-line"></div>
      <p>Signature of Applicant</p>
    </div>
  </div>
</body>
</html>`.trim();
            }

            /* =========================
               Loading + notification helpers
            ========================== */
            const loadingOverlay = document.getElementById('loadingIndicator');
            const loadingMessageEl = document.getElementById('loadingMessage');

            function showLoading(message = 'Working...') {
                if (loadingMessageEl) loadingMessageEl.textContent = message;
                if (loadingOverlay) loadingOverlay.style.display = 'flex';
            }

            function hideLoading() {
                if (loadingOverlay) loadingOverlay.style.display = 'none';
            }

            /* =========================
               Unified modal helpers
            ========================== */
            const clientDecisionModal = document.getElementById('clientDecisionModal');
            const cdTitle = document.getElementById('clientDecisionTitle');
            const cdBody = document.getElementById('clientDecisionBody');
            const cdActions = document.getElementById('clientDecisionActions');

            const validationModal = document.getElementById('validationModal');
            const valTitle = document.getElementById('validationTitle');
            const valBody = document.getElementById('validationBody');
            const valActions = document.getElementById('validationActions');

            function openClientDecision({
                title,
                html,
                buttons
            }) {
                closeValidation();
                cdTitle.textContent = title || 'Client';
                cdBody.innerHTML = html || '';
                cdActions.innerHTML = '';
                (buttons || []).forEach(btn => {
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

            function showBlock(code, message) {
                const titles = {
                    for_payment: 'Payment Required',
                    pending_new: 'Pending Application',
                    pending_renewal: 'Pending Application',
                    unexpired_permit: 'Unexpired Permit Found',
                    need_approved_new: 'Action Required',
                    need_released_new: 'Action Required'
                };

                // default single Close button
                let buttons = [{
                    text: 'Close',
                    class: 'btn btn-primary',
                    onClick: () => closeValidation()
                }];

                // For the special need_released_new block, offer Request new + Close
                if (code === 'need_released_new') {
                    buttons = [{
                            text: 'Request new',
                            class: 'btn btn-outline',
                            onClick: () => {
                                closeValidation();
                                try {
                                    requestNewFromRenewal();
                                } catch (e) {
                                    console.error(e);
                                }
                            }
                        },
                        {
                            text: 'Close',
                            class: 'btn btn-primary',
                            onClick: () => closeValidation()
                        }
                    ];
                }

                openValidation({
                    title: titles[code] || 'Notice',
                    html: message || 'Please resolve this before continuing.',
                    buttons: buttons
                });
            }

            function showSuggestRenewal() {
                openValidation({
                    title: 'Consider Renewal',
                    html: 'We detected a released NEW Wood Processing Plant permit for this client. You may file a renewal instead.',
                    buttons: [{
                        text: 'Request renewal',
                        class: 'btn btn-primary',
                        onClick: () => {
                            closeValidation();
                            requestRenewalFromNew();
                        }
                    }, {
                        text: 'Close',
                        class: 'btn btn-outline',
                        onClick: () => closeValidation()
                    }]
                });
            }

            function requestRenewalFromNew() {
                setType('renewal');
                autofillRenewalFromNew();
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }

            function requestNewFromRenewal() {
                setType('new');
                autofillNewFromRenewal();
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }

            window.__FORCE_NEW_CLIENT__ = false;
            window.__USE_EXISTING_CLIENT_ID__ = null;

            function applyClientNames(names) {
                if (!names) return;
                const first = names.first || names.first_name || '';
                const middle = names.middle || names.middle_name || '';
                const last = names.last || names.last_name || '';
                const decl = [first, last].filter(Boolean).join(' ');

                const setVal = (id, value) => {
                    const el = document.getElementById(id);
                    if (el != null) el.value = value;
                };

                setVal('new-first-name', first);
                setVal('new-middle-name', middle);
                setVal('new-last-name', last);
                setVal('declaration-name-new', decl);

                setVal('r-first-name', first);
                setVal('r-middle-name', middle);
                setVal('r-last-name', last);
                setVal('declaration-name-renewal', decl);

                existingClientNames = {
                    first,
                    middle,
                    last
                };
                chosenClientName = {
                    first,
                    middle,
                    last
                };
            }

            function clearPrecheckState() {
                chosenClientId = null;
                chosenClientName = null;
                confirmNewClient = false;
                precheckCache = null;
                suggestedClient = null;
                existingClientNames = null;
                window.__FORCE_NEW_CLIENT__ = false;
                window.__USE_EXISTING_CLIENT_ID__ = null;
            }

            clearPrecheckState();

            function getEffectiveNames() {
                if (chosenClientName) return {
                    ...chosenClientName
                };
                const isRenewal = activePermitType() === 'renewal';
                return {
                    first: v(isRenewal ? 'r-first-name' : 'new-first-name'),
                    middle: v(isRenewal ? 'r-middle-name' : 'new-middle-name'),
                    last: v(isRenewal ? 'r-last-name' : 'new-last-name')
                };
            }

            const submitApplicationBtn = document.getElementById('submitApplication');
            const btnSubmit = submitApplicationBtn;

            btnSubmit?.addEventListener('click', async (ev) => {
                ev.preventDefault();
                ev.stopPropagation();
                ev.stopImmediatePropagation();

                clearPrecheckState();

                const type = activePermitType();
                const first = type === 'renewal' ? v('r-first-name') : v('new-first-name');
                const middle = type === 'renewal' ? v('r-middle-name') : v('new-middle-name');
                const last = type === 'renewal' ? v('r-last-name') : v('new-last-name');
                const clientMode = (clientModeEl?.value || 'new').toLowerCase();
                const usingExistingPick = type === 'new' && clientMode === 'existing';

                if (!first || !last) {
                    toast('First and last name are required.');
                    return;
                }

                if (usingExistingPick && !(clientPick?.value)) {
                    setClientPickError('Please select an existing client.');
                    toast('Please select an existing client.');
                    return;
                }

                if (usingExistingPick && clientPick?.value) {
                    setClientPickError('');
                    chosenClientId = clientPick.value;
                    chosenClientName = {
                        first,
                        middle,
                        last
                    };
                    existingClientNames = {
                        first,
                        middle,
                        last
                    };
                    window.__FORCE_NEW_CLIENT__ = false;
                    confirmNewClient = false;
                    precheckCache = null;
                    window.__USE_EXISTING_CLIENT_ID__ = String(chosenClientId);
                    await finalSubmit();
                    return;
                }

                showLoading('Checking records...');
                try {
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

                    if (json.decision === 'existing' && json.client && json.client.client_id) {
                        hideLoading();
                        const client = json.client;
                        const flags = json.flags || {};
                        const full = client.full_name || [client.first_name, client.middle_name, client.last_name].filter(Boolean).join(' ') || 'Existing client';

                        openClientDecision({
                            title: 'Is this the correct client?',
                            html: 'We detected an existing client:<div style="margin:8px 0;font-weight:600">' + full + '</div>',
                            buttons: [{
                                text: 'No, cancel',
                                class: 'btn btn-outline',
                                onClick: () => {
                                    clearPrecheckState();
                                    closeClientDecision();
                                }
                            }, {
                                text: 'Submit as new',
                                class: 'btn btn-outline',
                                onClick: async () => {
                                    // prevent bypass: when filing a renewal, ensure the detected client
                                    // has a released NEW wood permit; otherwise show validation modal
                                    chosenClientId = null;
                                    chosenClientName = null;
                                    existingClientNames = null;
                                    confirmNewClient = true;
                                    window.__FORCE_NEW_CLIENT__ = true;
                                    window.__USE_EXISTING_CLIENT_ID__ = null;
                                    try {
                                        if (type === 'renewal') {
                                            const flagsLocal = (flags || {});
                                            if (!flagsLocal.has_released_new) {
                                                closeClientDecision();
                                                showBlock('need_released_new', 'To file a renewal, the client must already have a released NEW wood permit record.');
                                                return;
                                            }
                                        }
                                    } catch (e) {
                                        console.error(e);
                                        closeClientDecision();
                                        showBlock('need_released_new', 'To file a renewal, the client must already have a released NEW wood permit record.');
                                        return;
                                    }

                                    closeClientDecision();
                                    await finalSubmit();
                                }
                            }, {
                                text: 'Yes, continue',
                                class: 'btn btn-primary',
                                onClick: async () => {
                                    window.__FORCE_NEW_CLIENT__ = false;
                                    confirmNewClient = false;
                                    chosenClientId = String(client.client_id);
                                    window.__USE_EXISTING_CLIENT_ID__ = chosenClientId;
                                    applyClientNames(client);
                                    closeClientDecision();

                                    if (flags.has_for_payment) {
                                        showBlock('for_payment', 'You still have an unpaid Wood Processing Plant permit. Please settle it before submitting another request.');
                                        return;
                                    }

                                    if (type === 'new') {
                                        if (flags.has_unexpired) {
                                            showBlock('unexpired_permit', 'We found a released Wood Processing Plant permit that is still valid. You cannot file a new application.');
                                            return;
                                        }
                                        if (json.suggest === 'renewal') {
                                            showSuggestRenewal();
                                            return;
                                        }
                                        if (flags.has_pending_new) {
                                            showBlock('pending_new', 'You already have a pending NEW Wood Processing Plant application.');
                                            return;
                                        }
                                        if (flags.has_pending_renewal) {
                                            showBlock('pending_renewal', 'You already have a pending Wood Processing Plant renewal application.');
                                            return;
                                        }
                                    } else {
                                        // RENEWAL: require a released NEW wood permit before allowing renewal
                                        if (!flags.has_released_new) {
                                            showBlock('need_released_new', 'To file a renewal, the client must already have a released NEW wood permit record.');
                                            return;
                                        }

                                        if (flags.has_pending_new || flags.has_pending_renewal) {
                                            showBlock('pending_renewal', 'You already have a pending Wood Processing Plant application. Please wait for the update first.');
                                            return;
                                        }
                                        if (flags.has_unexpired) {
                                            showBlock('unexpired_permit', 'You still have an unexpired Wood Processing Plant permit. Please wait until it expires to renew.');
                                            return;
                                        }
                                    }

                                    await finalSubmit();
                                }
                            }]
                        });
                        return;
                    }

                    if (json.decision === 'none') {
                        hideLoading();
                        if (type === 'renewal') {
                            openClientDecision({
                                title: 'No Client Detected',
                                html: 'No client matched these details. Do you want to request a new permit instead, or continue submitting this renewal as a new client?',
                                buttons: [{
                                    text: 'Cancel',
                                    class: 'btn btn-outline',
                                    onClick: () => {
                                        clearPrecheckState();
                                        closeClientDecision();
                                    }
                                }, {
                                    text: 'Request new',
                                    class: 'btn btn-outline',
                                    onClick: () => {
                                        closeClientDecision();
                                        requestNewFromRenewal();
                                    }
                                }, {
                                    text: 'Continue renewal',
                                    class: 'btn btn-primary',
                                    onClick: async () => {
                                        // Do not allow continuing a renewal when no released NEW wood permit exists
                                        closeClientDecision();
                                        showBlock('need_released_new', 'To file a renewal, the client must already have a released NEW wood permit record.');
                                    }
                                }]
                            });
                        } else {
                            openClientDecision({
                                title: 'Submit as New Client?',
                                html: 'No existing client was detected for these details. Submit as a new client?',
                                buttons: [{
                                    text: 'Cancel',
                                    class: 'btn btn-outline',
                                    onClick: () => closeClientDecision()
                                }, {
                                    text: 'Submit',
                                    class: 'btn btn-primary',
                                    onClick: async () => {
                                        closeClientDecision();
                                        confirmNewClient = true;
                                        window.__FORCE_NEW_CLIENT__ = true;
                                        window.__USE_EXISTING_CLIENT_ID__ = null;
                                        await finalSubmit();
                                    }
                                }]
                            });
                        }
                        return;
                    }

                    if (json.block) {
                        hideLoading();
                        showBlock(json.block, json.message);
                        return;
                    }

                    if (json.suggest === 'renewal' && type === 'new') {
                        hideLoading();
                        showSuggestRenewal();
                        return;
                    }

                    hideLoading();
                    await finalSubmit();
                } catch (err) {
                    console.error(err);
                    hideLoading();
                    toast(err?.message || 'Precheck failed. Please try again.');
                }
            });

            async function finalSubmit() {
                if (typeof window.validateWPPForm === 'function') {
                    const ok = window.validateWPPForm();
                    if (!ok) {
                        if (typeof window.__scrollFirstErrorIntoView === 'function') {
                            window.__scrollFirstErrorIntoView();
                        }
                        toast('Please fix the highlighted fields.');
                        return;
                    }
                }

                if (precheckCache && precheckCache.block) {
                    showBlock(precheckCache.block, precheckCache.message || 'Please resolve outstanding applications before continuing.');
                    return;
                }

                await doFinalSubmit();
            }

            /* ===================== Final Submit (same logic, callable) ===================== */
            /* ===================== Final Submit (same logic, callable) ===================== */
            async function doFinalSubmit() {
                // block UI
                showLoading('Submitting application...');
                submitApplicationBtn.disabled = true;

                try {
                    // 1) Build Word (MHTML) with embedded signature
                    const sigData = getSignatureDataURL();
                    const hasSig = !!sigData;
                    const sigLocation = 'signature.png';
                    const html =
                        activePermitType() === 'renewal' ?
                        buildRenewalDocHTML(sigLocation, hasSig) :
                        buildNewDocHTML(sigLocation, hasSig);
                    const parts = hasSig ? [{
                        location: sigLocation,
                        contentType: 'image/png',
                        base64: (sigData.split(',')[1] || '')
                    }] : [];
                    const mhtml = makeMHTML(html, parts);
                    const docBlob = new Blob([mhtml], {
                        type: 'application/msword'
                    });

                    const eff = getEffectiveNames();
                    const fullApplicantName = [eff.first, eff.middle, eff.last].filter(Boolean).join(' ');

                    const docFilename =
                        (activePermitType() === 'renewal' ? 'WPP_Renewal_' : 'WPP_New_') +
                        (fullApplicantName || 'Applicant').replace(/\s+/g, '_') + '.doc';

                    // 2) Collect fields & files for backend save
                    await waitForExistingFiles();
                    const fd = new FormData();
                    const type = activePermitType();
                    fd.append('permit_type', type);

                    const useExistingId = window.__USE_EXISTING_CLIENT_ID__ || (chosenClientId ? String(chosenClientId) : '');
                    if (useExistingId) {
                        fd.append('use_existing_client_id', useExistingId);
                    }
                    if (window.__FORCE_NEW_CLIENT__ || confirmNewClient) {
                        fd.append('force_new_client', '1');
                        fd.append('confirm_new_client', '1');
                    }



                    // Names
                    fd.append('first_name', eff.first);
                    fd.append('middle_name', eff.middle); // '' if empty in table, as required
                    fd.append('last_name', eff.last);

                    // NEW vs RENEWAL
                    if (type === 'renewal') {
                        fd.append('r_address', v('r-address'));
                        fd.append('r_plant_location', v('r-plant-location'));
                        fd.append('r_contact_number', v('r-contact-number'));
                        fd.append('r_email_address', v('r-email-address'));
                        fd.append('r_ownership_type', document.querySelector('input[name="r-ownership-type"]:checked')?.value || '');
                        fd.append('r_previous_permit', v('r-previous-permit'));
                        fd.append('r_expiry_date', v('r-expiry-date'));
                    } else {
                        fd.append('new_business_address', v('new-business-address'));
                        fd.append('new_plant_location', v('new-plant-location'));
                        fd.append('new_contact_number', v('new-contact-number'));
                        fd.append('new_email_address', v('new-email-address'));
                        fd.append('new_ownership_type', document.querySelector('input[name="new-ownership-type"]:checked')?.value || '');
                    }

                    // Shared plant details
                    fd.append('plant_type', plantTypeValue());
                    fd.append('daily_capacity', v('daily-capacity'));
                    fd.append('power_source', powerSourceValue());

                    // Dynamic tables as JSON
                    fd.append('machinery_rows_json', tableToRowsJSON(machTbody, 4));
                    fd.append('supply_rows_json', tableToRowsJSON(supplyTbody, 3));
                    // NEW: also send pretty HTML so the backend can store it directly in application_form


                    // Declaration
                    fd.append('declaration_name', type === 'renewal' ? v('declaration-name-renewal') : v('declaration-name-new'));
                    fd.append('declaration_address', v('declaration-address'));

                    // Generated .doc
                    fd.append('application_doc', new File([docBlob], docFilename, {
                        type: 'application/msword'
                    }));

                    // Signature file (optional)
                    if (hasSig) {
                        const sigBlob = dataURLToBlob(sigData);
                        fd.append('signature_file', new File([sigBlob], 'signature.png', {
                            type: 'image/png'
                        }));
                    }

                    // Attach ALL chosen files present in UI
                    $$('input[type="file"]').forEach((fi) => {
                        if (fi.files?.[0]) fd.append(fi.id, fi.files[0]);
                    });
                    Object.entries(existingFileCache).forEach(([fieldId, file]) => {
                        if (!file) return;
                        const input = document.getElementById(fieldId);
                        if (input?.files?.length) return;
                        fd.append(fieldId, file);
                    });

                    // 3) Submit to backend
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
                    if (!res.ok || !json.ok) {
                        // If backend returned a structured block (e.g., need_released_new), show the modal
                        if (json && json.block) {
                            hideLoading();
                            showBlock(json.block, json.message || 'Action required');
                            throw new Error('BLOCKED:' + (json.block || ''));
                        }
                        throw new Error(json.error || `HTTP ${res.status}`);
                    }

                    toast("Application submitted. We'll notify you once reviewed.");
                    clearPrecheckState();
                    resetAllFields();
                } catch (e) {
                    console.error(e);
                    const msg = String(e?.message || '');
                    if (/^BLOCKED:/.test(msg)) {
                        // already displayed validation modal via showBlock; no toast needed
                    } else {
                        toast(e?.message || 'Submission failed. Please try again.');
                    }
                } finally {
                    hideLoading();
                    submitApplicationBtn.disabled = false;
                }
            }




            /* ===================== FINAL SUBMIT (generate doc + upload + save) ===================== */
            // confirmSubmitBtn?.addEventListener('click', async () => {
            //     confirmModal.style.display = 'none';
            //     // block UI
            //     loading.style.display = 'flex';
            //     submitApplicationBtn.disabled = true;

            //     try {
            //         // 1) Build Word (MHTML) with embedded signature
            //         const sigData = getSignatureDataURL();
            //         const hasSig = !!sigData;
            //         const sigLocation = 'signature.png';
            //         const html =
            //             activePermitType() === 'renewal' ?
            //             buildRenewalDocHTML(sigLocation, hasSig) :
            //             buildNewDocHTML(sigLocation, hasSig);
            //         const parts = hasSig ? [{
            //             location: sigLocation,
            //             contentType: 'image/png',
            //             base64: (sigData.split(',')[1] || '')
            //         }] : [];
            //         const mhtml = makeMHTML(html, parts);
            //         const docBlob = new Blob([mhtml], {
            //             type: 'application/msword'
            //         });

            //         const fullApplicantName =
            //             activePermitType() === 'renewal' ? [v('r-first-name'), v('r-middle-name'), v('r-last-name')].filter(Boolean).join(' ') : [v('new-first-name'), v('new-middle-name'), v('new-last-name')].filter(Boolean).join(' ');

            //         const docFilename =
            //             (activePermitType() === 'renewal' ? 'WPP_Renewal_' : 'WPP_New_') +
            //             (fullApplicantName || 'Applicant').replace(/\s+/g, '_') + '.doc';

            //         // 2) Collect fields & files for backend save
            //         const fd = new FormData();
            //         const type = activePermitType();
            //         fd.append('permit_type', type);

            //         // Names (shared)
            //         fd.append('first_name', type === 'renewal' ? v('r-first-name') : v('new-first-name'));
            //         fd.append('middle_name', type === 'renewal' ? v('r-middle-name') : v('new-middle-name'));
            //         fd.append('last_name', type === 'renewal' ? v('r-last-name') : v('new-last-name'));

            //         // NEW vs RENEWAL: send the keys the backend expects
            //         if (type === 'renewal') {
            //             fd.append('r_address', v('r-address'));
            //             fd.append('r_plant_location', v('r-plant-location'));
            //             fd.append('r_contact_number', v('r-contact-number'));
            //             fd.append('r_email_address', v('r-email-address'));
            //             fd.append('r_ownership_type', document.querySelector('input[name="r-ownership-type"]:checked')?.value || '');
            //             fd.append('r_previous_permit', v('r-previous-permit'));
            //             fd.append('r_expiry_date', v('r-expiry-date'));
            //         } else {
            //             fd.append('new_business_address', v('new-business-address'));
            //             fd.append('new_plant_location', v('new-plant-location'));
            //             fd.append('new_contact_number', v('new-contact-number'));
            //             fd.append('new_email_address', v('new-email-address'));
            //             fd.append('new_ownership_type', document.querySelector('input[name="new-ownership-type"]:checked')?.value || '');
            //         }

            //         // Shared plant details
            //         fd.append('plant_type', plantTypeValue());
            //         fd.append('daily_capacity', v('daily-capacity'));
            //         fd.append('power_source', powerSourceValue());

            //         // Dynamic tables as JSON (what backend expects)
            //         fd.append('machinery_rows_json', tableToRowsJSON(machTbody, 4));
            //         fd.append('supply_rows_json', tableToRowsJSON(supplyTbody, 3));

            //         // Declaration fields (saved into additional_information)
            //         fd.append('declaration_name',
            //             type === 'renewal' ? v('declaration-name-renewal') : v('declaration-name-new')
            //         );
            //         fd.append('declaration_address', v('declaration-address'));

            //         // Generated application document
            //         fd.append('application_doc', new File([docBlob], docFilename, {
            //             type: 'application/msword'
            //         }));

            //         // Signature file (optional)
            //         if (hasSig) {
            //             const sigBlob = dataURLToBlob(sigData);
            //             fd.append('signature_file', new File([sigBlob], 'signature.png', {
            //                 type: 'image/png'
            //             }));
            //         }

            //         // Attach ALL chosen files present in UI (IDs unchanged)
            //         $$('input[type="file"]').forEach((fi) => {
            //             if (fi.files?.[0]) fd.append(fi.id, fi.files[0]);
            //         });

            //         // 3) Submit to backend
            //         const res = await fetch(SAVE_URL, {
            //             method: 'POST',
            //             body: fd,
            //             credentials: 'include'
            //         });
            //         let json;
            //         try {
            //             json = await res.json();
            //         } catch {
            //             const text = await res.text();
            //             throw new Error(`HTTP ${res.status} – ${text.slice(0, 200)}`);
            //         }
            //         if (!res.ok || !json.ok) throw new Error(json.error || `HTTP ${res.status}`);

            //         toast("Application submitted. We'll notify you once reviewed.");
            //         resetAllFields();
            //     } catch (e) {
            //         console.error(e);
            //         toast(e?.message || 'Submission failed. Please try again.');
            //     } finally {
            //         loading.style.display = 'none';
            //         submitApplicationBtn.disabled = false;
            //     }
            // });

            /* ===================== Reset ===================== */
            function resetAllFields() {
                // New
                ['new-first-name', 'new-middle-name', 'new-last-name', 'new-business-address', 'new-plant-location', 'new-contact-number', 'new-email-address']
                .forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
                // Renewal
                ['r-first-name', 'r-middle-name', 'r-last-name', 'r-address', 'r-plant-location', 'r-contact-number', 'r-email-address', 'r-previous-permit', 'r-expiry-date']
                .forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
                // Shared
                ['daily-capacity', 'other-plant-specify', 'other-power-specify', 'declaration-name-new', 'declaration-address', 'declaration-name-renewal']
                .forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
                // Radios
                $$('input[type="radio"]').forEach(r => (r.checked = false));
                // Files
                $$('input[type="file"]').forEach(i => {
                    i.value = '';
                    const n = i.parentElement?.querySelector('.file-name');
                    if (n) n.textContent = 'No file chosen';
                });
                // Tables: keep first row, clear inputs, remove extras
                [machTbody, supplyTbody].forEach(tbody => {
                    if (!tbody) return;
                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    rows.forEach((tr, idx) => {
                        if (idx === 0) tr.querySelectorAll('input').forEach(inp => (inp.value = ''));
                        else tr.remove();
                    });
                });
                // Signature
                strokes = [];
                repaint(true);
                // Back to NEW view
                setType('new');
                chosenClientId = null;
                confirmNewClient = false;
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });

            }
        })();
    </script>
    <script>
        /* =======================================================================
   Wood Processing Plant (WPP) — Client-side Validation (standalone script)
   - Shows red error TEXT under each input (no red borders)
   - Validates **New** & **Renewal** sections + both tables
   - Blocks the Submit button if anything is invalid (capture phase)
   - Safe to paste after your existing scripts
   - Does NOT validate the file uploads nor the signature pad
======================================================================= */
        (function() {
            'use strict';

            /* ------------------ tiny helpers ------------------ */
            const $ = (s, r = document) => r.querySelector(s);
            const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

            const isBlank = (v) => !v || !String(v).trim();
            const minChars = (v, n) => String(v || '').trim().length >= n;
            const hasDigits = (v) => /\d/.test(String(v || ''));
            const onlyNum = (v) => (String(v || '').match(/[\d.]+/g) || []).join('');
            const toFloat = (v) => parseFloat(onlyNum(v));
            const toInt = (v) => parseInt(onlyNum(v), 10);

            // create/get an error line right after the element (or container)
            function ensureErrorEl(el) {
                if (!el) return null;
                let next = el.nextElementSibling;
                if (!(next && next.classList && next.classList.contains('field-error'))) {
                    next = document.createElement('div');
                    next.className = 'field-error';
                    next.style.cssText = 'color:#d32f2f;margin:6px 0 0;display:none;font-size:.92rem;';
                    el.insertAdjacentElement('afterend', next);
                }
                return next;
            }
            // let chosenClientId = null;


            function setError(afterEl, msg) {
                if (!afterEl) return;
                const holder = ensureErrorEl(afterEl);
                if (!holder) return;
                if (msg) {
                    holder.textContent = msg;
                    holder.style.display = 'block';
                } else {
                    holder.textContent = '';
                    holder.style.display = 'none';
                }
                // keep borders clean
                if (afterEl.classList) {
                    afterEl.classList.remove('error', 'is-invalid');
                }
                afterEl.style.borderColor = '';
                afterEl.style.boxShadow = '';
                afterEl.style.outline = '';
            }
            /* ------------------ FILE VALIDATION (NEW) ------------------ */
            // Treats every VISIBLE file input inside the active requirements as required.
            function isDisplayed(el) {
                if (!el) return false;
                const cs = getComputedStyle(el);
                return cs.display !== 'none' && cs.visibility !== 'hidden';
            }

            function ensureFileErrorContainer(fileInput) {
                const container = fileInput.closest('.file-input-container') || fileInput.parentElement;
                if (!container) return null;
                let holder = container.querySelector('.field-error');
                if (!holder) {
                    holder = document.createElement('div');
                    holder.className = 'field-error';
                    holder.style.cssText = 'color:#d32f2f;margin-top:6px;font-size:.92rem;display:none;';
                    container.appendChild(holder);
                }
                return holder;
            }

            function setFileError(fileInput, msg) {
                const holder = ensureFileErrorContainer(fileInput);
                if (!holder) return;
                holder.textContent = msg || '';
                holder.style.display = msg ? 'block' : 'none';
            }

            // Detects CR auto-attached previews that were rendered as links in the holder.
            function hasAutoAttachedPreview(fileInput) {
                const id = fileInput.id || '';
                // our holders follow "uploaded-files-<suffix>", where <suffix> = file id after "file-"
                const suffix = id.startsWith('file-') ? id.slice(5) : id;
                const holder = document.getElementById('uploaded-files-' + suffix);
                return !!(holder && holder.children && holder.children.length > 0);
            }

            function fileInputsForType(type) {
                const rootId = (type === 'renewal') ? 'renewal-requirements' : 'new-requirements';
                const root = document.getElementById(rootId);
                if (!root || !isDisplayed(root)) return [];
                return Array.from(root.querySelectorAll('input[type="file"]'));
            }

            // Main file validator - File uploads are OPTIONAL unless explicitly required
            // Determine allowed file type for a requirement based on its title text
            function allowedForInput(input) {
                // find the nearest requirement title text
                let titleEl = input.closest('.requirement-item')?.querySelector('.requirement-title');
                if (!titleEl) {
                    // sub-requirements may have a <p> tag with description
                    titleEl = input.closest('.sub-requirement')?.querySelector('p');
                }
                const txt = (titleEl?.textContent || '').toLowerCase();

                // heuristics: image-only keywords
                const imageKeywords = /photo|photos|image|images|jpg|jpeg|png|gis|geo[- ]?tag|geotag|map|geo tag/;
                if (imageKeywords.test(txt)) {
                    return {
                        kind: 'image',
                        message: 'JPG/PNG only.'
                    };
                }

                // default to document-only for most requirement titles
                return {
                    kind: 'doc',
                    message: 'PDF/DOC/DOCX only.'
                };
            }

            // Validate a single file input; returns true if ok
            function validateSingleFile(input) {
                // Skip if its requirement row is currently hidden
                const reqItem = input.closest('.requirement-item') || input.closest('.file-upload') || input.parentElement;
                if (reqItem && !isDisplayed(reqItem)) {
                    setFileError(input, ''); // clear any stale error
                    return true;
                }

                const chosen = (input.files && input.files.length > 0);
                const auto = hasAutoAttachedPreview(input);
                const loaded = !!(input.dataset && input.dataset.loadedUrl);

                // if nothing selected and nothing auto-loaded, it's fine (uploads optional)
                if (!(chosen || auto || loaded)) {
                    setFileError(input, '');
                    return true;
                }

                // if there's a file, check extension against expected kind
                if (chosen && input.files[0]) {
                    const name = (input.files[0].name || '').toLowerCase();
                    const ext = (name.split('.').pop() || '').replace(/[^a-z0-9]/g, '');
                    const rules = allowedForInput(input);
                    if (rules.kind === 'doc') {
                        if (!['pdf', 'doc', 'docx'].includes(ext)) {
                            setFileError(input, rules.message);
                            return false;
                        }
                    } else if (rules.kind === 'image') {
                        if (!['jpg', 'jpeg', 'png'].includes(ext)) {
                            setFileError(input, rules.message);
                            return false;
                        }
                    }
                }

                // all good
                setFileError(input, '');
                return true;
            }

            // Main file validator: validate all visible file inputs for the active type
            function validateFiles(activeType) {
                let ok = true;
                const files = fileInputsForType(activeType);
                files.forEach(input => {
                    const res = validateSingleFile(input);
                    if (!res) ok = false;
                });
                return ok;
            }

            /* live validation for files */
            function attachFileLiveValidation() {
                const all = Array.from(document.querySelectorAll('#new-requirements input[type="file"], #renewal-requirements input[type="file"]'));
                all.forEach(el => {
                    // Validate only the specific input on change
                    el.addEventListener('change', () => validateSingleFile(el));
                });
                // Re-check when switching tabs (buttons are present in DOM)
                document.querySelectorAll('.permit-type-btn').forEach(btn => {
                    btn.addEventListener('click', () => setTimeout(() => validateFiles(activeType()), 0));
                });
            }


            /* ------------------ find active permit type ------------------ */
            const activeType = () =>
                (document.querySelector('.permit-type-btn.active')?.getAttribute('data-type') || 'new');

            /* ------------------ radio group helpers ------------------ */
            function groupContainerByName(name) {
                // radio group wrapper is the ".checkbox-group" near the radios
                const first = document.querySelector(`input[name="${name}"]`);
                return first ? first.closest('.checkbox-group') || first.parentElement : null;
            }

            function groupValue(name) {
                const r = document.querySelector(`input[name="${name}"]:checked`);
                return r ? r.value : '';
            }

            /* ------------------ per-field rules ------------------ */
            // Names: allow letters incl. accents + space/hyphen/apostrophe
            const NAME_RX = /^[A-Za-zÀ-ž' -]{2,50}$/;
            const MID_RX = /^[A-Za-zÀ-ž' -]{1,50}$/;
            const EMAIL_RX = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
            const SIMPLE_ID_RX = /^[A-Za-z0-9\-\/. ]{3,}$/; // generic doc/permit format

            // contact numbers: allow comma-separated list; each item must have ≥7 digits
            // PH mobile only: allow comma/newline separated values; ignore separators inside each number
            // PH mobile only; support comma/newline separated values
            function contactRule(v) {
                if (isBlank(v)) return 'Required.';
                const parts = String(v).split(/[,\n]/).map(s => s.trim()).filter(Boolean);
                if (!parts.length) return 'Required.';

                const normalize = s => s.replace(/[ \t\-()./]/g, '');
                const ok = parts.every(p => {
                    const n = normalize(p);
                    // 09171234567  or  +639171234567
                    return /^09\d{9}$/.test(n) || /^\+639\d{9}$/.test(n);
                });

                if (!ok) return 'Use 09XXXXXXXXX or +639XXXXXXXX (comma-separate if many).';
            }



            // address should contain letters & numbers and be ≥10 chars
            function addressRule(v) {
                if (isBlank(v)) return 'Address is required.';
                if (!minChars(v, 10)) return 'Enter at least 10 characters.';
                if (!/[A-Za-z]/.test(v) || !/\d/.test(v)) return 'Include both street/house number and area.';
            }

            // location: require letters and min 5 chars
            function locationRule(v) {
                if (isBlank(v)) return 'Location is required.';
                if (!minChars(v, 5)) return 'Enter at least 5 characters.';
                if (!/[A-Za-z]/.test(v)) return 'Use letters to describe the location.';
            }

            // daily capacity: require positive number; units optional
            function capacityRule(v) {
                if (isBlank(v)) return 'Daily rated capacity is required.';
                const num = toFloat(v);
                if (!isFinite(num) || num <= 0) return 'Enter a valid positive number (units optional).';
            }

            // email: optional, but must be valid if present
            function emailRule(v) {
                if (!v) return;
                if (!EMAIL_RX.test(v.trim())) return 'Enter a valid email address.';
            }

            // declaration name must be present & sensible
            function declNameRule(v, firstId, lastId) {
                if (isBlank(v)) return 'Enter your full name here.';
                if (!/^[A-Za-zÀ-ž' .-]{4,100}$/.test(v)) return 'Use letters and spaces only.';
                const f = $(firstId)?.value?.trim().toLowerCase();
                const l = $(lastId)?.value?.trim().toLowerCase();
                if (f && l) {
                    const lower = v.trim().toLowerCase();
                    if (!(lower.includes(f) && lower.includes(l))) return 'Include your first and last name.';
                }
            }

            /* ------------------ TABLES ------------------ */

            // MACHINERY table validation
            function validateMachinery() {
                const tbody = $('#machinery-table tbody');
                if (!tbody) return true;
                let ok = true;
                let hasAnyContent = false;
                let hasCompleteRow = false;

                Array.from(tbody.querySelectorAll('tr')).forEach((tr, idx) => {
                    const [typeEl, brandEl, hpEl, qtyEl] = Array.from(tr.querySelectorAll('input')).slice(0, 4);

                    // prepare error holders once
                    [typeEl, brandEl, hpEl, qtyEl].forEach(ensureErrorEl);

                    const type = (typeEl?.value || '').trim();
                    const brand = (brandEl?.value || '').trim();
                    const hp = (hpEl?.value || '').trim();
                    const qty = (qtyEl?.value || '').trim();

                    const rowBlank = !type && !brand && !hp && !qty;
                    if (rowBlank) { // ignore empty rows
                        setError(typeEl, '');
                        setError(brandEl, '');
                        setError(hpEl, '');
                        setError(qtyEl, '');
                        return;
                    }

                    hasAnyContent = true;

                    // validations (per-cell)
                    if (!minChars(type, 2)) {
                        setError(typeEl, 'Type is required.');
                        ok = false;
                    } else setError(typeEl, '');
                    if (!minChars(brand, 2)) {
                        setError(brandEl, 'Brand/Model is required.');
                        ok = false;
                    } else setError(brandEl, '');
                    const hpNum = toFloat(hp);
                    if (!isFinite(hpNum) || hpNum <= 0) {
                        setError(hpEl, 'Enter numeric horsepower/capacity.');
                        ok = false;
                    } else setError(hpEl, '');
                    const qNum = toInt(qty);
                    if (!Number.isInteger(qNum) || qNum < 1) {
                        setError(qtyEl, 'Quantity must be a whole number ≥ 1.');
                        ok = false;
                    } else setError(qtyEl, '');

                    if (minChars(type, 2) && minChars(brand, 2) && isFinite(hpNum) && hpNum > 0 && Number.isInteger(qNum) && qNum >= 1) {
                        hasCompleteRow = true;
                    }
                });

                // table-level requirement
                if (!hasAnyContent) {
                    const firstTypeInput = $('#machinery-table tbody tr input');
                    setError(firstTypeInput, 'Add at least one machinery/equipment.');
                    ok = false;
                }

                return ok && hasCompleteRow;
            }

            // SUPPLY table validation
            function validateSupply() {
                const tbody = $('#supply-table tbody');
                if (!tbody) return true;
                let ok = true;
                let hasAnyContent = false;
                let hasCompleteRow = false;

                Array.from(tbody.querySelectorAll('tr')).forEach((tr) => {
                    const [suppEl, specieEl, volEl] = Array.from(tr.querySelectorAll('input')).slice(0, 3);
                    [suppEl, specieEl, volEl].forEach(ensureErrorEl);

                    const s = (suppEl?.value || '').trim();
                    const sp = (specieEl?.value || '').trim();
                    const v = (volEl?.value || '').trim();

                    const rowBlank = !s && !sp && !v;
                    if (rowBlank) {
                        setError(suppEl, '');
                        setError(specieEl, '');
                        setError(volEl, '');
                        return;
                    }

                    hasAnyContent = true;

                    if (!/^[A-Za-zÀ-ž0-9' .,-]{3,}$/.test(s)) {
                        setError(suppEl, 'Supplier name (3+ chars).');
                        ok = false;
                    } else setError(suppEl, '');
                    if (!/^[A-Za-zÀ-ž' .,-]{2,}$/.test(sp)) {
                        setError(specieEl, 'Species must be letters.');
                        ok = false;
                    } else setError(specieEl, '');
                    const volNum = toFloat(v);
                    if (!isFinite(volNum) || volNum <= 0) {
                        setError(volEl, 'Enter a numeric volume (units optional).');
                        ok = false;
                    } else setError(volEl, '');

                    if (/^[A-Za-zÀ-ž0-9' .,-]{3,}$/.test(s) && /^[A-Za-zÀ-ž' .,-]{2,}$/.test(sp) && isFinite(volNum) && volNum > 0) {
                        hasCompleteRow = true;
                    }
                });

                if (!hasAnyContent) {
                    const firstSupp = $('#supply-table tbody tr input');
                    setError(firstSupp, 'Add at least one supplier contract.');
                    ok = false;
                }

                return ok && hasCompleteRow;
            }

            /* ------------------ field-by-field validation ------------------ */

            const rules = {
                // NEW
                '#new-first-name': v => {
                    if (isBlank(v)) return 'First name is required.';
                    if (!NAME_RX.test(v)) return '2–50 letters only.';
                },
                '#new-middle-name': v => {
                    if (v && !MID_RX.test(v)) return 'Letters only.';
                },
                '#new-last-name': v => {
                    if (isBlank(v)) return 'Last name is required.';
                    if (!NAME_RX.test(v)) return '2–50 letters only.';
                },
                '#new-business-address': addressRule,
                '#new-plant-location': locationRule,
                '#new-contact-number': contactRule,
                '#new-email-address': emailRule,

                // RENEWAL
                '#r-first-name': v => {
                    if (isBlank(v)) return 'First name is required.';
                    if (!NAME_RX.test(v)) return '2–50 letters only.';
                },
                '#r-middle-name': v => {
                    if (v && !MID_RX.test(v)) return 'Letters only.';
                },
                '#r-last-name': v => {
                    if (isBlank(v)) return 'Last name is required.';
                    if (!NAME_RX.test(v)) return '2–50 letters only.';
                },
                '#r-address': addressRule,
                '#r-plant-location': locationRule,
                '#r-contact-number': contactRule,
                '#r-email-address': emailRule,
                '#r-previous-permit': v => {
                    if (v && !SIMPLE_ID_RX.test(v)) return 'Use letters/numbers, slashes or dashes.';
                },
                '#r-expiry-date': v => {
                    /* validated together below when paired */
                },

                // SHARED
                '#daily-capacity': capacityRule,
                '#declaration-name-new': v => declNameRule(v, '#new-first-name', '#new-last-name'),
                '#declaration-address': v => {
                    if (isBlank(v)) return 'Enter your address.';
                    if (!minChars(v, 10)) return 'Address must be at least 10 characters.';
                },
                '#declaration-name-renewal': v => declNameRule(v, '#r-first-name', '#r-last-name'),
                '#other-plant-specify': v => {
                    /* required only when plant-type=Other */
                },
                '#other-power-specify': v => {
                    /* required only when power-source=Other */
                },
            };

            // validate one field by selector
            function validateField(sel) {
                const el = $(sel);
                if (!el) return true;
                ensureErrorEl(el);
                const rule = rules[sel];
                let msg = rule ? (rule(el.value) || '') : '';

                // pair rule for renewal previous-permit + expiry-date
                if (sel === '#r-previous-permit' || sel === '#r-expiry-date') {
                    const prev = $('#r-previous-permit')?.value?.trim();
                    const exp = $('#r-expiry-date')?.value?.trim();
                    // if ((prev && !exp) || (!prev && exp)) msg = 'Provide BOTH previous permit no. and expiry date, or leave both empty.';
                    if (sel === '#r-previous-permit' && prev && !SIMPLE_ID_RX.test(prev)) msg = 'Use letters/numbers, slashes or dashes.';
                    else msg = '';
                }

                setError(el, msg);
                return !msg;
            }

            // ownership group (new/renewal)
            function validateOwnership(type) {
                const name = type === 'new' ? 'new-ownership-type' : 'r-ownership-type';
                const container = groupContainerByName(name);
                if (!container) return true;
                ensureErrorEl(container);
                let msg = '';
                if (!groupValue(name)) msg = 'Select a type of ownership.';
                setError(container, msg);
                return !msg;
            }

            // plant type group + "other" specify
            function validatePlantType() {
                const container = groupContainerByName('plant-type');
                if (!container) return true;
                ensureErrorEl(container);
                let msg = '';
                const val = groupValue('plant-type');
                if (!val) msg = 'Select a plant type.';
                setError(container, msg);

                // other specify
                const otherInp = $('#other-plant-specify');
                if (val === 'Other') {
                    const v = otherInp?.value || '';
                    const msg2 = !minChars(v, 3) ? 'Please specify (min 3 characters).' : '';
                    setError(otherInp, msg2);
                    return !msg && !msg2;
                } else {
                    setError(otherInp, '');
                    return !msg;
                }
            }

            // power source group + "other" specify
            function validatePowerSource() {
                const container = groupContainerByName('power-source');
                if (!container) return true;
                ensureErrorEl(container);
                let msg = '';
                const val = groupValue('power-source');
                if (!val) msg = 'Select a source of power.';
                setError(container, msg);

                const otherInp = $('#other-power-specify');
                if (val === 'Other') {
                    const v = otherInp?.value || '';
                    const msg2 = !minChars(v, 3) ? 'Please specify (min 3 characters).' : '';
                    setError(otherInp, msg2);
                    return !msg && !msg2;
                } else {
                    setError(otherInp, '');
                    return !msg;
                }
            }

            /* ------------------ validate a whole section ------------------ */
            function validateSection(type) {
                const isNew = type === 'new';
                let ok = true;

                const toCheck = isNew ? ['#new-first-name', '#new-middle-name', '#new-last-name',
                    '#new-business-address', '#new-plant-location',
                    '#new-contact-number', '#new-email-address',
                    '#daily-capacity',
                    '#declaration-name-new', '#declaration-address'
                ] : ['#r-first-name', '#r-middle-name', '#r-last-name',
                    '#r-address', '#r-plant-location',
                    '#r-contact-number', '#r-email-address',
                    '#r-previous-permit', '#r-expiry-date',
                    '#daily-capacity',
                    '#declaration-name-renewal'
                ];

                toCheck.forEach(sel => {
                    if (!validateField(sel)) ok = false;
                });

                if (!validateOwnership(type)) ok = false;
                if (!validatePlantType()) ok = false;
                if (!validatePowerSource()) ok = false;

                if (!validateMachinery()) ok = false;
                if (!validateSupply()) ok = false;

                // hard guard: numbers in first/last names
                (isNew ? ['#new-first-name', '#new-last-name'] : ['#r-first-name', '#r-last-name'])
                .forEach(sel => {
                    const el = $(sel);
                    if (!el) return;
                    if (/\d/.test(el.value)) {
                        setError(el, 'Names cannot contain numbers.');
                        ok = false;
                    }
                });
                if (!validateFiles(type)) ok = false;

                return ok;
            }

            /* ------------------ live validation listeners ------------------ */
            function addLiveValidation() {
                Object.keys(rules).forEach(sel => {
                    const el = $(sel);
                    if (!el) return;
                    ensureErrorEl(el);
                    const handler = () => validateField(sel);
                    el.addEventListener('input', handler);
                    el.addEventListener('blur', handler);
                    el.classList?.remove('error', 'is-invalid');
                });

                // radios
                ['new-ownership-type', 'r-ownership-type', 'plant-type', 'power-source'].forEach(name => {
                    $$(`input[name="${name}"]`).forEach(r => {
                        r.addEventListener('change', () => {
                            if (name === 'plant-type') validatePlantType();
                            else if (name === 'power-source') validatePowerSource();
                            else if (name === 'new-ownership-type') validateOwnership('new');
                            else if (name === 'r-ownership-type') validateOwnership('renewal');
                        });
                    });
                });

                // tables: validate when typing, plus when adding rows
                $('#machinery-table')?.addEventListener('input', (e) => {
                    if (e.target && e.target.tagName === 'INPUT') validateMachinery();
                });
                $('#supply-table')?.addEventListener('input', (e) => {
                    if (e.target && e.target.tagName === 'INPUT') validateSupply();
                });

                // hook after row add buttons (rows are added by your other script)
                $('#add-machinery-row')?.addEventListener('click', () =>
                    setTimeout(() => {
                        // ensure new inputs get error placeholders
                        Array.from($('#machinery-table tbody')?.querySelectorAll('tr input') || []).forEach(ensureErrorEl);
                        validateMachinery();
                    }, 0)
                );
                $('#add-supply-row')?.addEventListener('click', () =>
                    setTimeout(() => {
                        Array.from($('#supply-table tbody')?.querySelectorAll('tr input') || []).forEach(ensureErrorEl);
                        validateSupply();
                    }, 0)
                );
            }

            /* ------------------ submit interception ------------------ */
            /* ------------------ submit interception ------------------ */
            function interceptSubmit() {
                const submitBtn = $('#submitApplication');
                if (!submitBtn) return;

                const guard = (e) => {
                    const ok = validateSection(activeType());
                    if (!ok) {
                        // focus/scroll to first visible error
                        const firstErr = document.querySelector('.field-error:not([style*="display: none"])');
                        const target = firstErr?.previousElementSibling;
                        if (target && typeof target.focus === 'function') target.focus();
                        if (firstErr?.scrollIntoView) {
                            firstErr.scrollIntoView({
                                behavior: 'smooth',
                                block: 'center'
                            });
                        }
                    }
                };

                // IMPORTANT: capture=true so this runs BEFORE your main click handler
                submitBtn.addEventListener('click', guard, {
                    capture: true
                });
            }



            // expose manual trigger in case you want to call it elsewhere
            window.validateWPPForm = () => validateSection(activeType());

            // init
            addLiveValidation();
            attachFileLiveValidation();
            interceptSubmit();
        })();
    </script>


</body>

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
                try {
                    const manilaFmt = new Intl.DateTimeFormat('en-PH', {
                        timeZone: 'Asia/Manila',
                        year: 'numeric', month: 'short', day: 'numeric',
                        hour: 'numeric', minute: '2-digit', second: '2-digit'
                    });
                    el.title = manilaFmt.format(new Date(tsMs));
                } catch (err) {
                    el.title = new Date(tsMs).toLocaleString();
                }
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
                const nid = link.dataset.notifId;
                try {
                    await fetch(location.pathname + '?ajax=mark_read&notif_id=' + encodeURIComponent(nid), {
                        method: 'POST',
                        credentials: 'same-origin'
                    });
                } catch {}
                link.closest('.as-notif-item')?.classList.remove('unread');
                if (badge) {
                    const v = Number(badge.textContent || 0);
                    if (v > 1) badge.textContent = String(v - 1);
                    else badge.style.display = 'none';
                }
            });
        })();
    </script>







</html>