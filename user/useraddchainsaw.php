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
    error_log('[CHAINSAW-CLIENTS] ' . $e->getMessage());
    $clientRows = [];
}

$permitOptions = [];
$permitRecords = [];
$renewalQuickOptions = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            ad.no                  AS permit_no,
            ad.date_issued         AS doc_date_issued,
            ad.expiry_date         AS doc_expiry_date,
            a.approval_id,
            a.client_id,
            c.first_name           AS client_first,
            c.middle_name          AS client_middle,
            c.last_name            AS client_last,
            c.sitio_street,
            c.barangay,
            c.municipality         AS client_municipality,
            c.city                 AS client_city,
            c.contact_number       AS client_contact,
            af.contact_number      AS af_contact_number,
            af.present_address,
            af.province            AS af_province,
            af.location,
            af.purpose_of_use,
            af.brand,
            af.model,
            af.date_of_acquisition,
            af.serial_number_chainsaw,
            af.horsepower,
            af.maximum_length_of_guide_bar,
            af.permit_number       AS stored_permit_number,
            af.expiry_date         AS af_expiry_date,
            req.chainsaw_cert_terms,
            req.chainsaw_cert_sticker,
            req.chainsaw_staff_work,
            req.chainsaw_permit_to_sell,
            req.chainsaw_business_permit,
            req.chainsaw_old_registration
        FROM public.approved_docs ad
        JOIN public.approval a   ON a.approval_id = ad.approval_id
        LEFT JOIN public.client   c   ON c.client_id   = a.client_id
        LEFT JOIN public.application_form af ON af.application_id = a.application_id
        LEFT JOIN public.requirements req    ON req.requirement_id = a.requirement_id
        WHERE NULLIF(btrim(ad.no), '') IS NOT NULL
        ORDER BY COALESCE(ad.date_issued, a.submitted_at) DESC NULLS LAST
        LIMIT 200
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as $row) {
        $permitNo = trim((string)($row['permit_no'] ?? ''));
        if ($permitNo === '') continue;

        $approvalId = $row['approval_id'] ?? null;
        $issuedLabel = '';
        if (!empty($row['doc_date_issued'])) {
            try {
                $issuedLabel = (new DateTime((string)$row['doc_date_issued']))->format('M j, Y');
            } catch (Throwable $e) {
                $issuedLabel = '';
            }
        }
        $permitOptions[] = [
            'no'          => $permitNo,
            'label'       => $issuedLabel ? ($permitNo . ' - ' . $issuedLabel) : $permitNo,
            'approval_id' => $approvalId ? (string)$approvalId : null
        ];

        if (!isset($renewalQuickOptions[$permitNo])) {
            $renewalQuickOptions[$permitNo] = [
                'no' => $permitNo,
                'approval_id' => $approvalId ? (string)$approvalId : ''
            ];
        }

        $files = array_filter([
            'chainsaw_cert_terms'       => $row['chainsaw_cert_terms'] ?? null,
            'chainsaw_cert_sticker'     => $row['chainsaw_cert_sticker'] ?? null,
            'chainsaw_staff_work'       => $row['chainsaw_staff_work'] ?? null,
            'chainsaw_permit_to_sell'   => $row['chainsaw_permit_to_sell'] ?? null,
            'chainsaw_business_permit'  => $row['chainsaw_business_permit'] ?? null,
            'chainsaw_old_registration' => $row['chainsaw_old_registration'] ?? null,
        ], static fn($v) => !empty($v));

        $permitRecords[] = [
            'approval_id' => $approvalId,
            'permit_no'    => $permitNo,
            'client_id'    => $row['client_id'],
            'issued_date'  => $row['doc_date_issued'] ?? null,
            'expiry_date'  => $row['doc_expiry_date'] ?? ($row['af_expiry_date'] ?? null),
            'client'       => [
                'first'  => $row['client_first'] ?? '',
                'middle' => $row['client_middle'] ?? '',
                'last'   => $row['client_last'] ?? '',
            ],
            'address'      => [
                'street'      => $row['sitio_street'] ?? '',
                'barangay'    => $row['barangay'] ?? '',
                'municipality' => $row['client_municipality'] ?? $row['client_city'] ?? '',
                'province'    => $row['client_province'] ?? $row['af_province'] ?? '',
                'full'        => $row['present_address'] ?? ''
            ],
            'contact_number' => $row['af_contact_number'] ?? $row['client_contact'] ?? '',
            'purpose'        => $row['purpose_of_use'] ?? '',
            'brand'          => $row['brand'] ?? '',
            'model'          => $row['model'] ?? '',
            'date_of_acquisition' => $row['date_of_acquisition'] ?? '',
            'serial_number'  => $row['serial_number_chainsaw'] ?? '',
            'horsepower'     => $row['horsepower'] ?? '',
            'guide_bar'      => $row['maximum_length_of_guide_bar'] ?? '',
            'files'          => $files,
        ];
    }
    $renewalQuickOptions = array_values($renewalQuickOptions);
} catch (Throwable $e) {
    error_log('[CHAINSAW-PERMITS] ' . $e->getMessage());
    $permitOptions = [];
    $permitRecords = [];
    $renewalQuickOptions = [];
}

if (!$renewalQuickOptions) {
    try {
        $stmt = $pdo->query("
            SELECT approval_id, no, date_issued
            FROM public.approved_docs
            WHERE NULLIF(btrim(no), '') IS NOT NULL
            ORDER BY date_issued DESC NULLS LAST, approval_id DESC
            LIMIT 200
        ");
        $fallbackRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($fallbackRows as $row) {
            $permitNo = trim((string)($row['no'] ?? ''));
            if ($permitNo === '') continue;
            $issuedLabel = '';
            if (!empty($row['date_issued'])) {
                try {
                    $issuedLabel = (new DateTime((string)$row['date_issued']))->format('M j, Y');
                } catch (Throwable $e) {
                    $issuedLabel = '';
                }
            }
            $renewalQuickOptions[] = [
                'no' => $permitNo,
                'approval_id' => (string)($row['approval_id'] ?? ''),
                'label' => $issuedLabel ? ($permitNo . ' - ' . $issuedLabel) : $permitNo,
            ];
        }
    } catch (Throwable $e) {
        error_log('[CHAINSAW-PERMITS-FALLBACK] ' . $e->getMessage());
    }
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wildlife Registration Application</title>
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
            --dark-gray: #555;
            --medium-gray: #ddd;
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
            background: rgba(224, 204, 204, 0.3);
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
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }

        .permit-type-buttons {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
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

        .renewal-quick-select {
            display: none;
            align-items: center;
            gap: 12px;
            margin-left: auto;
        }

        .renewal-quick-select .renewal-quick-select__label {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            text-align: left;
            color: #2b6625;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .renewal-quick-select__label small {
            font-weight: 400;
            font-size: 0.75rem;
            color: #6c757d;
        }

        .renewal-quick-select select {
            height: 40px;
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 0 12px;
            min-width: 220px;
        }

        .client-mode-toggle {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .client-mode-toggle .btn {
            min-width: 140px;
        }

        .renewal-permit-picker {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-top: 8px;
        }

        .renewal-permit-picker select {
            height: 40px;
            width: 240px;
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 0 10px;
        }

        .renewal-cr-files {
            margin-top: 10px;
            padding: 10px 12px;
            border: 1px dashed #c8d3c5;
            border-radius: 6px;
            background: #f8fbf7;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .renewal-cr-files__title {
            font-size: .85rem;
            font-weight: 600;
            text-transform: uppercase;
            color: #2b6625;
        }

        .renewal-cr-files__list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .renewal-cr-files__pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #d5e2d1;
            background: #fff;
            border-radius: 999px;
            padding: 4px 12px;
            font-size: .85rem;
            color: #2b6625;
            text-decoration: none;
            transition: background .2s ease, border-color .2s ease;
        }

        .renewal-cr-files__pill:hover {
            background: #e8f3e6;
            border-color: #9ec394;
        }

        .renewal-cr-files__pill i {
            font-size: .75rem;
        }

        .renewal-cr-files__empty {
            font-size: .85rem;
            color: #6c757d;
        }

        .field-error {
            color: #c53030;
            font-size: 0.9rem;
            margin-top: 4px;
        }

        .readonly-input {
            background-color: #f7f7f7;
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

        /* fixed visible height */

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
        #loadingIndicator {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.25);
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }

        #loadingIndicator .card {
            background: #fff;
            padding: 16px 20px;
            border-radius: 10px;
            box-shadow: var(--box-shadow);
            color: #333;
        }

        /* Print-specific styles */
        @media print {

            .download-btn,
            .add-row,
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
    </style>
</head>

<body>
    <!-- Header #1 (kept) -->
    <header>
        <div class="logo">
            <a href="user_home.php"><img src="seal.png" alt="Site Logo" /></a>
        </div>
        <button class="mobile-toggle"><i class="fas fa-bars"></i></button>
        <div class="nav-container">
            <div class="nav-item dropdown">
                <div class="nav-icon active"><i class="fas fa-bars"></i></div>
                <div class="dropdown-menu center">
                    <a href="user_reportaccident.php" class="dropdown-item"><i class="fas fa-file-invoice"></i><span>Report Incident</span></a>
                    <a href="user_requestseedlings.php" class="dropdown-item"><i class="fas fa-seedling"></i><span>Request Seedlings</span></a>
                    <a href="user_chainsaw_renewal.php" class="dropdown-item active-page"><i class="fas fa-tools"></i><span>Chainsaw Renewal</span></a>
                </div>
            </div>
            <div class="nav-item dropdown">
                <div class="nav-icon"><i class="fas fa-bell"></i><span class="badge">1</span></div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3><a href="#" class="mark-all-read">Mark all as read</a>
                    </div>
                    <div class="notification-item unread">
                        <a href="user_each.php?id=1" class="notification-link">
                            <div class="notification-icon"><i class="fas fa-exclamation-circle"></i></div>
                            <div class="notification-content">
                                <div class="notification-title">Chainsaw Renewal Status</div>
                                <div class="notification-message">Chainsaw Renewal has been approved.</div>
                                <div class="notification-time">10 minutes ago</div>
                            </div>
                        </a>
                    </div>
                    <div class="notification-footer"><a href="user_notification.php" class="view-all">View All Notifications</a></div>
                </div>
            </div>
            <div class="nav-item dropdown">
                <div class="nav-icon"><i class="fas fa-user-circle"></i></div>
                <div class="dropdown-menu">
                    <a href="user_profile.php" class="dropdown-item"><i class="fas a-user-edit"></i><span>Edit Profile</span></a>
                    <a href="user_login.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                </div>
            </div>
        </div>
    </header>

    <!-- Header #2 (kept) -->
    <header>
        <div class="logo">
            <a href="user_home.php"><img src="seal.png" alt="Site Logo" /></a>
        </div>
        <button class="mobile-toggle"><i class="fas fa-bars"></i></button>
        <div class="nav-container">
            <div class="nav-item dropdown">
                <div class="nav-icon active"><i class="fas fa-bars"></i></div>
                <div class="dropdown-menu center">
                    <a href="user_reportaccident.php" class="dropdown-item"><i class="fas fa-file-invoice"></i><span>Report Incident</span></a>
                    <a href="useraddseed.php" class="dropdown-item"><i class="fas fa-seedling"></i><span>Request Seedlings</span></a>
                    <a href="useraddwild.php" class="dropdown-item"><i class="fas fa-paw"></i><span>Wildlife Permit</span></a>
                    <a href="useraddtreecut.php" class="dropdown-item"><i class="fas fa-tree"></i><span>Tree Cutting Permit</span></a>
                    <a href="useraddlumber.php" class="dropdown-item"><i class="fas fa-boxes"></i><span>Lumber Dealers Permit</span></a>
                    <a href="useraddwood.php" class="dropdown-item"><i class="fas fa-industry"></i><span>Wood Processing Permit</span></a>
                    <a href="useraddchainsaw.php" class="dropdown-item active-page"><i class="fas fa-tools"></i><span>Chainsaw Permit</span></a>
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
                                $title  = $n['approval_id'] ? 'Permit Update' : ($n['incident_id'] ? 'Incident Update' : 'Notification');
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

            <div class="nav-item dropdown">
                <div class="nav-icon"><i class="fas fa-user-circle"></i></div>
                <div class="dropdown-menu">
                    <a href="user_profile.php" class="dropdown-item"><i class="fas fa-user-edit"></i><span>Edit Profile</span></a>
                    <a href="user_login.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                </div>
            </div>
        </div>
    </header>

    <div class="main-container">
        <!-- <div class="action-buttons">
            <button class="btn btn-primary" id="addFilesBtn"><i class="fas fa-plus-circle"></i> Add</button>
            <a href="usereditchainsaw.php" class="btn btn-outline"><i class="fas fa-edit"></i> Edit</a>
            <a href="userviewchainsaw.php" class="btn btn-outline"><i class="fas fa-eye"></i> View</a>
        </div> -->

        <div class="requirements-form">
            <div class="form-header">
                <h2>Chainsaw Registration (New/ Renewal) Permit - Requirements</h2>
            </div>

            <div class="form-body">
                <div class="permit-type-selector">
                    <div class="permit-type-buttons">
                        <button class="permit-type-btn active" data-type="new" type="button">New Chainsaw Permit</button>
                        <button class="permit-type-btn" data-type="renewal" type="button">Chainsaw Renewal</button>
                        <?php $hasRenewalQuickOptions = !empty($renewalQuickOptions); ?>
                        <div class="renewal-quick-select" id="renewalQuickSelectWrap" style="display:flex; text-align: center; gap:6px;min-width:220px; border: 1px dashed #c8d3c5; border-radius: 6px; background: #f8fbf7; padding:  5px;">
                            <div class="renewal-quick-select__label">
                                <span>Permit No.</span>
                                <!-- <small>Enter your full name to autofill the form</small> -->
                            </div>
                            <select id="renewalQuickSelect" aria-label="Select released chainsaw permit" <?= $hasRenewalQuickOptions ? '' : 'disabled' ?>>
                                <?php if ($hasRenewalQuickOptions): ?>
                                    <option value="">-- Select Permit No. --</option>
                                    <?php foreach ($renewalQuickOptions as $opt): ?>
                                        <?php $permitNo = (string)($opt['no'] ?? ''); ?>
                                        <?php if ($permitNo === '') continue; ?>
                                        <option
                                            value="<?= htmlspecialchars($permitNo, ENT_QUOTES) ?>"
                                            data-approval-id="<?= htmlspecialchars((string)($opt['approval_id'] ?? ''), ENT_QUOTES) ?>">
                                            <?= htmlspecialchars((string)($opt['label'] ?? $permitNo), ENT_QUOTES) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="">No released permits yet</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="client-mode-toggle" id="clientModeToggle">
                        <button type="button" class="btn btn-outline" id="btnExisting">
                            <i class="fas fa-user-check"></i>&nbsp;Existing client
                        </button>
                        <button type="button" class="btn btn-outline" id="btnNew" style="display:none;">
                            <i class="fas fa-user-plus"></i>&nbsp;New client
                        </button>
                    </div>
                </div>

                <!-- NEW: APPLICANT INFO -->
                <div class="form-section-group" data-permit-for="new">
                    <div class="form-section">
                        <h2>I. APPLICANT INFORMATION</h2>

                        <input type="hidden" id="clientMode" value="new">

                        <div id="existingClientRow" class="form-group" style="display:none;">
                            <label for="clientPick" style="font-weight:600;margin-bottom:6px;display:block;">Select client</label>
                            <?php if ($clientRows): ?>
                                <select id="clientPick" style="height:42px;width:100%;border:1px solid #ccc;border-radius:4px;padding:0 10px;">
                                    <option value="">-- Select a client --</option>
                                    <?php
                                    $myId = (string)($_SESSION['user_id'] ?? '');
                                    $renderOption = static function (array $c): string {
                                        $full = trim(trim((string)($c['first_name'] ?? '')) . ' ' . trim((string)($c['middle_name'] ?? '')) . ' ' . trim((string)($c['last_name'] ?? '')));
                                        $full = trim(preg_replace('/\s+/', ' ', $full));
                                        $full = $full ?: 'Unnamed client';
                                        $addrParts = [];
                                        if (!empty($c['sitio_street'])) $addrParts[] = $c['sitio_street'];
                                        if (!empty($c['barangay'])) $addrParts[] = 'Brgy. ' . $c['barangay'];
                                        $cityOrMuni = $c['municipality'] ?: ($c['city'] ?? '');
                                        if ($cityOrMuni) $addrParts[] = $cityOrMuni;
                                        $addressValue = $addrParts ? implode(', ', $addrParts) : '';
                                        $label = $full . ($addressValue ? ' - ' . $addressValue : '');
                                        $attrs = sprintf(
                                            ' value="%s" data-first="%s" data-middle="%s" data-last="%s" data-street="%s" data-barangay="%s" data-municipality="%s" data-city="%s" data-province="%s" data-contact="%s"',
                                            htmlspecialchars((string)($c['client_id'] ?? ''), ENT_QUOTES),
                                            htmlspecialchars((string)($c['first_name'] ?? ''), ENT_QUOTES),
                                            htmlspecialchars((string)($c['middle_name'] ?? ''), ENT_QUOTES),
                                            htmlspecialchars((string)($c['last_name'] ?? ''), ENT_QUOTES),
                                            htmlspecialchars((string)($c['sitio_street'] ?? ''), ENT_QUOTES),
                                            htmlspecialchars((string)($c['barangay'] ?? ''), ENT_QUOTES),
                                            htmlspecialchars((string)($c['municipality'] ?? ''), ENT_QUOTES),
                                            htmlspecialchars((string)($c['city'] ?? ''), ENT_QUOTES),
                                            htmlspecialchars((string)($c['province'] ?? 'Cebu'), ENT_QUOTES),
                                            htmlspecialchars((string)($c['contact_number'] ?? ''), ENT_QUOTES)
                                        );
                                        return '<option' . $attrs . '>' . htmlspecialchars($label, ENT_QUOTES) . '</option>';
                                    };
                                    $hasMine = false;
                                    foreach ($clientRows as $row) {
                                        if ((string)($row['user_id'] ?? '') === $myId) {
                                            $hasMine = true;
                                            break;
                                        }
                                    }
                                    if ($hasMine) {
                                        echo '<optgroup label="Your clients">';
                                        foreach ($clientRows as $row) {
                                            if ((string)($row['user_id'] ?? '') !== $myId) continue;
                                            echo $renderOption($row);
                                        }
                                        echo '</optgroup>';
                                    }
                                    $hasOthers = false;
                                    foreach ($clientRows as $row) {
                                        if ((string)($row['user_id'] ?? '') === $myId) continue;
                                        $hasOthers = true;
                                        break;
                                    }
                                    if ($hasOthers) {
                                        echo '<optgroup label="All clients (others)">';
                                        foreach ($clientRows as $row) {
                                            if ((string)($row['user_id'] ?? '') === $myId) continue;
                                            echo $renderOption($row);
                                        }
                                        echo '</optgroup>';
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
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="first-name" class="required">First Name:</label>
                                    <input type="text" id="first-name" name="first_name" />
                                </div>
                                <div class="form-group">
                                    <label for="middle-name">Middle Name:</label>
                                    <input type="text" id="middle-name" name="middle_name" />
                                </div>
                                <div class="form-group">
                                    <label for="last-name" class="required">Last Name:</label>
                                    <input type="text" id="last-name" name="last_name" />
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="street" class="required">Street Name/Sitio:</label>
                                    <input type="text" id="street" name="sitio_street" />
                                </div>
                                <div class="form-group">
                                    <label for="barangay" class="required">Barangay:</label>
                                    <input list="barangayList" id="barangay" name="barangay" />
                                    <datalist id="barangayList">
                                        <option value="Guadalupe" />
                                        <option value="Lahug" />
                                        <option value="Mabolo" />
                                        <option value="Labangon" />
                                        <option value="Talamban" />
                                    </datalist>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="municipality" class="required">Municipality:</label>
                                    <select id="municipality" name="municipality">
                                        <option value="">Select Municipality (Cebu)</option>
                                        <option>Alcantara</option>
                                        <option>Alcoy</option>
                                        <option>Alegria</option>
                                        <option>Aloguinsan</option>
                                        <option>Argao</option>
                                        <option>Asturias</option>
                                        <option>Badian</option>
                                        <option>Balamban</option>
                                        <option>Bantayan</option>
                                        <option>Barili</option>
                                        <option>Boljoon</option>
                                        <option>Borbon</option>
                                        <option>Carmen</option>
                                        <option>Catmon</option>
                                        <option>Compostela</option>
                                        <option>Consolacion</option>
                                        <option>Cordova</option>
                                        <option>Daanbantayan</option>
                                        <option>Dalaguete</option>
                                        <option>Dumanjug</option>
                                        <option>Ginatilan</option>
                                        <option>Liloan</option>
                                        <option>Madridejos</option>
                                        <option>Malabuyoc</option>
                                        <option>Medellin</option>
                                        <option>Minglanilla</option>
                                        <option>Moalboal</option>
                                        <option>Oslob</option>
                                        <option>Pilar</option>
                                        <option>Pinamungajan</option>
                                        <option>Poro</option>
                                        <option>Ronda</option>
                                        <option>Samboan</option>
                                        <option>San Fernando</option>
                                        <option>San Francisco</option>
                                        <option>San Remigio</option>
                                        <option>Santa Fe</option>
                                        <option>Santander</option>
                                        <option>Sibonga</option>
                                        <option>Sogod</option>
                                        <option>Tabogon</option>
                                        <option>Tabuelan</option>
                                        <option>Tuburan</option>
                                        <option>Tudela</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="province" class="required">Province:</label>
                                    <input type="text" id="province" name="province" />
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="contact-number" class="required">Contact Number:</label>
                                <input type="text" id="contact-number" name="contact_number" />
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>II. CHAINSAW INFORMATION AND DESCRIPTION</h2>

                        <div class="form-group">
                            <label for="purpose" class="required">Purpose of Use:</label>
                            <input type="text" id="purpose" name="purpose" />
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="brand" class="required">Brand:</label>
                                <input type="text" id="brand" name="brand" />
                            </div>
                            <div class="form-group">
                                <label for="model" class="required">Model:</label>
                                <input type="text" id="model" name="model" />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="acquisition-date" class="required">Date of Acquisition:</label>
                                <input type="date" id="acquisition-date" name="date_of_acquisition" />
                            </div>
                            <div class="form-group">
                                <label for="serial-number" class="required">Serial Number:</label>
                                <input type="text" id="serial-number" name="serial_number" />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="horsepower">Horsepower:</label>
                                <input type="text" id="horsepower" name="horsepower" />
                            </div>
                            <div class="form-group">
                                <label for="guide-bar-length" class="required">Maximum Length of Guide Bar:</label>
                                <input type="text" id="guide-bar-length" name="maximum_length_of_guide_bar" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RENEWAL -->
                <div class="form-section-group" data-permit-for="renewal" style="display:none">
                    <div class="form-section">
                        <h2>I. APPLICANT INFORMATION</h2>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="first-name-r" class="required">First Name:</label>
                                <input type="text" id="first-name-r" />
                            </div>
                            <div class="form-group">
                                <label for="middle-name-r">Middle Name:</label>
                                <input type="text" id="middle-name-r" />
                            </div>
                            <div class="form-group">
                                <label for="last-name-r" class="required">Last Name:</label>
                                <input type="text" id="last-name-r" />
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="address-r" class="required">Address:</label>
                            <input type="text" id="address-r" />
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="street-r" class="required">Street Name/Sitio:</label>
                                <input type="text" id="street-r" />
                            </div>
                            <div class="form-group">
                                <label for="barangay-r" class="required">Barangay:</label>
                                <input type="text" id="barangay-r" />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="municipality-r" class="required">Municipality:</label>
                                <input type="text" id="municipality-r" />
                            </div>
                            <div class="form-group">
                                <label for="province-r" class="required">Province:</label>
                                <input type="text" id="province-r" />
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="contact-number-r" class="required">Contact Number:</label>
                            <input type="text" id="contact-number-r" />
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>II. EXISTING CHAINSAW PERMIT INFORMATION</h2>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="permit-number-r" class="required">Permit Number:</label>
                                <input type="text" id="permit-number-r" />
                                <?php if ($permitOptions): ?>
                                    <div class="renewal-permit-picker" id="renewalPermitPicker">
                                        <label for="renewalPermitSelect" style="font-weight:600;margin:0;">Select existing permit</label>
                                        <select id="renewalPermitSelect">
                                            <option value="">-- Select Permit No. --</option>
                                            <?php foreach ($permitOptions as $opt): ?>
                                                <option
                                                    value="<?= htmlspecialchars((string)($opt['no'] ?? ''), ENT_QUOTES) ?>"
                                                    data-approval-id="<?= htmlspecialchars((string)($opt['approval_id'] ?? ''), ENT_QUOTES) ?>">
                                                    <?= htmlspecialchars((string)($opt['label'] ?? ($opt['no'] ?? '')), ENT_QUOTES) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php else: ?>
                                    <small style="display:block;margin-top:6px;color:#6c757d;">No released permits found yet.</small>
                                <?php endif; ?>
                                <!-- <div id="renewalPermitFiles" class="renewal-cr-files" style="display:none;">
                                    <div class="renewal-cr-files__title">Available Files</div>
                                    <div class="renewal-cr-files__list" id="renewalPermitFilesList">
                                        <div class="renewal-cr-files__empty">Select a permit number to view uploads.</div>
                                    </div>
                                </div> -->
                            </div>

                            <div class="form-group">
                                <label for="issuance-date-r" class="required">Date of Original Issuance:</label>
                                <input type="date" id="issuance-date-r" />
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="expiry-date-r" class="required">Expiry Date:</label>
                            <input type="date" id="expiry-date-r" />
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>III. CHAINSAW INFORMATION AND DESCRIPTION</h2>

                        <div class="form-group">
                            <label for="purpose-r" class="required">Purpose of Use:</label>
                            <input type="text" id="purpose-r" />
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="brand-r" class="required">Brand:</label>
                                <input type="text" id="brand-r" />
                            </div>

                            <div class="form-group">
                                <label for="model-r" class="required">Model:</label>
                                <input type="text" id="model-r" />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="acquisition-date-r" class="required">Date of Acquisition:</label>
                                <input type="date" id="acquisition-date-r" />
                            </div>

                            <div class="form-group">
                                <label for="serial-number-r" class="required">Serial Number:</label>
                                <input type="text" id="serial-number-r" />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="horsepower-r">Horsepower:</label>
                                <input type="text" id="horsepower-r" />
                            </div>

                            <div class="form-group">
                                <label for="guide-bar-length-r" class="required">Maximum Length of Guide Bar:</label>
                                <input type="text" id="guide-bar-length-r" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DECLARATION -->
                <div class="form-section">
                    <h2 class="declaration-title">DECLARATION AND SUBMISSION</h2>
                    <div class="declaration">
                        <p>
                            I,
                            <input type="text" id="declaration-name" class="declaration-input" placeholder="Enter your full name" />,
                            hereby declare that the information provided above is true and correct to the best of my knowledge. I understand that any false statement or misrepresentation may be a ground for the denial or revocation of this application and will subject me to appropriate legal action.
                        </p>

                        <div class="signature-date">
                            <div class="signature-box">
                                <label>Signature of Applicant:</label>
                                <div class="signature-pad-container">
                                    <canvas id="signature-pad"></canvas>
                                </div>
                                <div class="signature-actions">
                                    <button type="button" class="signature-btn clear-signature" id="clear-signature"><i class="fa-solid fa-eraser"></i> Clear</button>
                                </div>
                                <div class="signature-preview">
                                    <img id="signature-image" class="hidden" alt="Signature Preview" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Requirements (common + filtered per type) -->
                <div class="requirements-list" id="requirementsList">
                    <!-- 1 -->
                    <div class="requirement-item" data-show-for="new,renewal">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">1</span>
                                Certificate of Chainsaw Registration (3 copies for CENRO signature)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="sub-requirement" data-show-for="new,renewal">
                                <p style="margin-bottom:10px;font-weight:500;">- Terms and Conditions</p>
                                <div class="file-input-container">
                                    <label for="file-cert-terms" class="file-input-label"><i class="fas fa-upload"></i> Upload File</label>
                                    <input type="file" id="file-cert-terms" name="chainsaw_cert_terms" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="uploaded-cert-terms"></div>
                            </div>
                            <div class="sub-requirement" style="margin-top:15px;" data-show-for="new,renewal">
                                <p style="margin-bottom:10px;font-weight:500;">- Chainsaw Registration Sticker</p>
                                <div class="file-input-container">
                                    <label for="file-cert-sticker" class="file-input-label"><i class="fas fa-upload"></i> Upload File</label>
                                    <input type="file" id="file-cert-sticker" name="chainsaw_cert_sticker" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="uploaded-cert-sticker"></div>
                            </div>
                        </div>
                    </div>

                    <!-- 2 -->
                    <div class="requirement-item" data-show-for="new,renewal">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">2</span>
                                Complete Staff Work (Memorandum Report) – 2 pages with Station Supervisor’s signature (1 file)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-memo" class="file-input-label"><i class="fas fa-upload"></i> Upload File</label>
                                <input type="file" id="file-memo" name="chainsaw_staff_work" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-memo"></div>
                        </div>
                    </div>

                    <!-- 3 -->
                    <div class="requirement-item" data-show-for="new,renewal">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">3</span>
                                Geo-tagged Photos of the Chainsaw (2 copies from the client)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-geo" class="file-input-label"><i class="fas fa-upload"></i> Upload File</label>
                                <input type="file" id="file-geo" name="geo_photos" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-geo"></div>
                            <!-- NOTE: Application letter REMOVED from UI -->
                        </div>
                    </div>

                    <!-- New-only items -->
                    <div class="requirement-item" data-show-for="new">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">4</span>
                                Permit to Sell (2 copies)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-sell-permit" class="file-input-label"><i class="fas fa-upload"></i> Upload File</label>
                                <input type="file" id="file-sell-permit" name="chainsaw_permit_to_sell" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-sell-permit"></div>
                        </div>
                    </div>

                    <div class="requirement-item" data-show-for="new">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">5</span>
                                Photocopy of Business Permit – new recent issued (2 copies)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-business-permit" class="file-input-label"><i class="fas fa-upload"></i> Upload File</label>
                                <input type="file" id="file-business-permit" name="chainsaw_business_permit" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-business-permit"></div>
                        </div>
                    </div>

                    <div class="requirement-item" data-show-for="renewal">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">6</span>
                                Photocopy of old chainsaw Registration – 2 copies
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-old-reg" class="file-input-label"><i class="fas fa-upload"></i> Upload File</label>
                                <input type="file" id="file-old-reg" name="chainsaw_old_registration" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-old-reg"></div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="form-footer">
                <button class="btn btn-primary" id="submitApplication" type="button">
                    <i class="fas fa-paper-plane"></i> Submit Application
                </button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div id="profile-notification" style="display:none; position:fixed; top:5px; left:50%; transform:translateX(-50%); background:#323232; color:#fff; padding:16px 32px; border-radius:8px; font-size:1.1rem; z-index:9999; box-shadow:0 2px 8px rgba(0,0,0,0.15); text-align:center; min-width:220px; max-width:90vw;"></div>

    <!-- Loading -->
    <div id="loadingIndicator" style="display:none;position:fixed;inset:0;align-items:center;justify-content:center;background:rgba(0,0,0,.25);z-index:9998">
        <div class="card" style="background:#fff;padding:18px 22px;border-radius:10px">Working…</div>
    </div>
    <!-- Need Approved NEW modal -->
    <!-- <div id="needApprovedNewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:10000;align-items:center;justify-content:center;">
        <div style="background:#fff;max-width:560px;width:92%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden">
            <div style="padding:18px 20px;border-bottom:1px solid #eee;font-weight:600">Action Required</div>
            <div style="padding:16px 20px;line-height:1.6">
                To request a renewal, you must have an approved <b>NEW</b> chainsaw permit on record.<br><br>
                You can switch to a NEW permit request. We’ll copy over what you’ve already entered.
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;padding:14px 20px;background:#fafafa;border-top:1px solid #eee">
                <button id="needApprovedNewOk" class="btn btn-outline" type="button">Okay</button>
                <button id="needApprovedNewSwitch" class="btn btn-primary" type="button">Request new</button>
            </div>
        </div>
    </div> -->


    <!-- Confirm Modal -->
    <!-- <div id="confirmModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;max-width:520px;width:92%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden">
            <div style="padding:18px 20px;border-bottom:1px solid #eee;font-weight:600">Submit Application</div>
            <div style="padding:16px 20px;line-height:1.6">
                Please confirm you want to submit this Chainsaw application. Files will be uploaded and your request will enter review.
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;padding:14px 20px;background:#fafafa;border-top:1px solid #eee">
                <button id="btnCancelConfirm" class="btn btn-outline" type="button">Cancel</button>
                <button id="btnOkConfirm" class="btn btn-primary" type="button">Yes, submit</button>
            </div>
        </div>
    </div> -->

    <!-- Pending NEW request modal -->
    <!-- <div id="pendingNewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:10000;align-items:center;justify-content:center;">
        <div style="background:#fff;max-width:520px;width:92%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden">
            <div style="padding:18px 20px;border-bottom:1px solid #eee;font-weight:600">Pending Request</div>
            <div style="padding:16px 20px;line-height:1.6">
                You already have a pending chainsaw <b>new</b> permit request. Please wait for updates before submitting another one.
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;padding:14px 20px;background:#fafafa;border-top:1px solid #eee">
                <button id="pendingNewOk" class="btn btn-primary" type="button">Okay</button>
            </div>
        </div>
    </div> -->

    <!-- Offer renewal modal -->
    <!-- <div id="offerRenewalModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:10000;align-items:center;justify-content:center;">
        <div style="background:#fff;max-width:560px;width:92%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden">
            <div style="padding:18px 20px;border-bottom:1px solid #eee;font-weight:600">Renewal Available</div>
            <div style="padding:16px 20px;line-height:1.6">
                You can’t request a <b>new</b> chainsaw permit because you already have an approved one. You’re allowed to request a <b>renewal</b> instead.
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;padding:14px 20px;background:#fafafa;border-top:1px solid #eee">
                <button id="offerRenewalOk" class="btn btn-outline" type="button">Okay</button>
                <button id="offerRenewalSwitch" class="btn btn-primary" type="button">Request renewal</button>
            </div>
        </div>
    </div> -->

    <!-- Universal App Modal (shared) -->
    <div id="appModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:10000;align-items:center;justify-content:center;">
        <div style="background:#fff;max-width:560px;width:92%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden">
            <div style="padding:18px 20px;border-bottom:1px solid #eee;display:flex;align-items:center;justify-content:space-between;gap:12px">
                <div id="amTitle" style="font-weight:600">Title</div>
                <button id="amClose" class="close-modal" type="button" aria-label="Close" style="border:none;background:transparent;font-size:22px;line-height:1;cursor:pointer">&times;</button>
            </div>
            <div id="amBody" style="padding:16px 20px;line-height:1.6">Body</div>
            <div id="amFooter" style="display:flex;gap:10px;justify-content:flex-end;padding:14px 20px;background:#fafafa;border-top:1px solid #eee"></div>
        </div>
    </div>


    <script>
        window.__CHAINSAW_PERMITS__ = <?= json_encode($permitRecords ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <script>
        (function() {
            const SIG_WIDTH = 300,
                SIG_HEIGHT = 110;
            const SAVE_URL = new URL('../backend/users/chainsaw/save_chainsaw.php', window.location.href).toString();
            const PRECHECK_URL = new URL('../backend/users/chainsaw/precheck_chainsaw.php', window.location.href).toString();

            /* ===== Universal Modal controller (uses #appModal you already have) ===== */
            const AppModal = (() => {
                const root = document.getElementById('appModal');
                const titleEl = document.getElementById('amTitle');
                const bodyEl = document.getElementById('amBody');
                const footEl = document.getElementById('amFooter');
                const closeBtn = document.getElementById('amClose');
                let resolver = null;

                function close(value = null) {
                    root.style.display = 'none';
                    footEl.innerHTML = '';
                    if (resolver) {
                        const r = resolver;
                        resolver = null;
                        r(value);
                    }
                    document.body.style.overflow = '';
                }

                function btn({
                    text,
                    value,
                    variant
                }) {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.textContent = text;
                    b.className = variant === 'primary' ? 'btn btn-primary' : 'btn btn-outline';
                    b.addEventListener('click', () => close(value ?? text));
                    return b;
                }

                function open({
                    title = 'Notice',
                    html = '',
                    buttons = [{
                        text: 'OK',
                        variant: 'primary',
                        value: 'ok'
                    }]
                }) {
                    return new Promise(resolve => {
                        resolver = resolve;
                        titleEl.textContent = title;
                        bodyEl.innerHTML = html;
                        footEl.innerHTML = '';
                        buttons.forEach(def => footEl.appendChild(btn(def)));
                        root.style.display = 'flex';
                        document.body.style.overflow = 'hidden';
                    });
                }
                root.addEventListener('click', (e) => {
                    if (e.target === root) close('cancel');
                });
                closeBtn?.addEventListener('click', () => close('cancel'));
                window.addEventListener('keydown', (e) => {
                    if (root.style.display !== 'none' && e.key === 'Escape') close('cancel');
                });
                return {
                    open,
                    close
                };
            })();
            const openModal = (opts) => AppModal.open(opts);

            const RENEWAL_TYPED = "__typed__";

            /* ===== Fuzzy precheck helpers ===== */
            function renderCandidateList(cands) {
                if (!Array.isArray(cands) || !cands.length) return '';
                const rows = cands.map((c, i) => {
                    const nm = [c.first_name, c.middle_name, c.last_name].filter(Boolean).join(' ');
                    const pct = (c.score != null) ? ` <small style="opacity:.65">~${Math.round((c.score||0)*100)}% match</small>` : '';
                    return `<label style="display:flex;gap:8px;padding:6px 0;border-top:1px solid #eee;">
              <input type="radio" name="cand_pick" value="${String(c.client_id)}" ${i===0?'checked':''}>
              <span>${nm}${pct}</span>
            </label>`;
                }).join('');
                return `<div style="max-height:220px;overflow:auto;padding-top:6px;">${rows}</div>`;
            }

            function escHtml(s) {
                return String(s ?? '').replace(/[&<>"']/g, m => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                } [m]));
            }

            async function confirmDetectedClientForRenewal(baseJson) {
                // exact match already found?
                const nameFromExact =
                    (baseJson && (baseJson.existing_client_first || baseJson.existing_client_last)) ? [baseJson.existing_client_first, baseJson.existing_client_middle, baseJson.existing_client_last]
                    .filter(Boolean).join(' ') :
                    (baseJson?.existing_client_name || '');

                if (baseJson?.existing_client_id && nameFromExact) {
                    const act = await openModal({
                        title: 'Confirm detected client',
                        html: `Is this the correct client for renewal?<br><br><b>${escHtml(nameFromExact)}</b>`,
                        buttons: [{
                                text: 'Cancel',
                                variant: 'outline',
                                value: 'cancel'
                            },
                            {
                                text: 'Use typed details',
                                variant: 'outline',
                                value: 'typed'
                            },
                            {
                                text: 'Use detected client',
                                variant: 'primary',
                                value: 'use'
                            },
                        ]
                    });
                    if (act === 'use') return String(baseJson.existing_client_id);
                    if (act === 'typed') return RENEWAL_TYPED;
                    return null;
                }

                // fuzzy candidates �' require a pick
                const cands = Array.isArray(baseJson?.candidates) ? baseJson.candidates : [];
                if (cands.length) {
                    const act = await openModal({
                        title: 'Choose client for renewal',
                        html: `<div>We found similar client records. Pick the correct one for renewal:</div>${renderCandidateList(cands)}`,
                        buttons: [{
                                text: 'Cancel',
                                variant: 'outline',
                                value: 'cancel'
                            },
                            {
                                text: 'Use typed details',
                                variant: 'outline',
                                value: 'typed'
                            },
                            {
                                text: 'Use selected',
                                variant: 'primary',
                                value: 'use'
                            },
                        ]
                    });
                    if (act === 'use') {
                        const picked = readSelectedCandidateId();
                        return picked ? String(picked) : null;
                    }
                    if (act === 'typed') return RENEWAL_TYPED;
                    return null;
                }

                // no match at all
                const act = await openModal({
                    title: 'No matching client',
                    html: `We couldn’t detect an existing client for renewal. You can edit the name fields, proceed with the details you entered, or switch to a NEW request.`,
                    buttons: [{
                            text: 'Cancel',
                            variant: 'outline',
                            value: 'cancel'
                        },
                        {
                            text: 'submit as new',
                            variant: 'primary',
                            value: 'typed'
                        },
                        {
                            text: 'Switch to NEW',
                            variant: 'outline',
                            value: 'to_new'
                        },
                    ]
                });
                if (act === 'to_new') {
                    applyFilter('new');
                    autofillNewFromRenewal();
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                    return null;
                }
                if (act === 'typed') return RENEWAL_TYPED;
                return null;
            }

            function readSelectedCandidateId() {
                const r = document.querySelector('input[name="cand_pick"]:checked');
                return r ? r.value : null;
            }

            const MSG = {
                pay: 'You still have an unpaid chainsaw permit on record (<b>for payment</b>). <br>Please settle this <b>personally at the office</b> before filing another request.',
                offerR: 'You can\'t request a <b>new</b> Chainsaw permit because you already have a released one. You\'re allowed to request a <b>renewal</b> instead.',
                // NEW: shown only when trying to RENEW but an unexpired permit exists.
                // (If you want the word "lumber" exactly, change \'chainsaw\' below.)
                unexpired: '<div style="padding:16px 20px;line-height:1.6"> You still have an <b>unexpired</b> chainsaw permit.<br><br> Please wait until your current permit <b>expires</b> before requesting a renewal. </div>',
            };


            /* small wrapper that reuses your PRECHECK_URL and existing v()/activePermitType() */
            async function precheckWith(type, pickedClientId = null) {
                const first = type === "renewal" ? v("first-name-r") : v("first-name");
                const middle = type === "renewal" ? v("middle-name-r") : v("middle-name");
                const last = type === "renewal" ? v("last-name-r") : v("last-name");

                const fd = new FormData();
                fd.append('first_name', first);
                fd.append('middle_name', middle);
                fd.append('last_name', last);
                fd.append('desired_permit_type', type);
                if (pickedClientId) fd.append('use_existing_client_id', pickedClientId);


                const res = await fetch(PRECHECK_URL, {
                    method: 'POST',
                    body: fd,
                    credentials: 'include'
                });
                const json = await res.json();
                if (!res.ok) throw new Error(json.message || 'Precheck failed');
                return json;
            }

            /* state set by the “Use existing / Create new” step */
            let chosenClientId = null; // if user selected an existing client
            let chosenClientName = null; // {first, middle, last} from existing client
            let confirmNewClient = false;

            const clientModeEl = document.getElementById("clientMode");
            const btnExistingToggle = document.getElementById("btnExisting");
            const btnNewToggle = document.getElementById("btnNew");
            const clientModeToggle = document.getElementById("clientModeToggle");
            const existingClientRow = document.getElementById("existingClientRow");
            const clientPick = document.getElementById("clientPick");
            const clientPickError = document.getElementById("clientPickError");
            const firstNameInput = document.getElementById("first-name");
            const middleNameInput = document.getElementById("middle-name");
            const lastNameInput = document.getElementById("last-name");
            const streetInput = document.getElementById("street");
            const barangayInput = document.getElementById("barangay");
            const municipalitySelect = document.getElementById("municipality");
            const provinceInput = document.getElementById("province");
            const contactNumberInput = document.getElementById("contact-number");

            if (clientModeToggle && !clientPick) {
                clientModeToggle.style.display = "none";
                if (existingClientRow) existingClientRow.style.display = "none";
            }

            const captureApplicantValues = () => ({
                first: firstNameInput?.value || "",
                middle: middleNameInput?.value || "",
                last: lastNameInput?.value || "",
                street: streetInput?.value || "",
                barangay: barangayInput?.value || "",
                municipality: municipalitySelect?.value || "",
                province: provinceInput?.value || "",
                contact: contactNumberInput?.value || ""
            });

            const setValueAndDispatch = (el, value) => {
                if (!el) return;
                el.value = value ?? "";
                el.dispatchEvent(new Event("input", {
                    bubbles: true
                }));
                el.dispatchEvent(new Event("change", {
                    bubbles: true
                }));
            };

            const restoreApplicantValues = (vals) => {
                if (!vals) return;
                setValueAndDispatch(firstNameInput, vals.first || "");
                setValueAndDispatch(middleNameInput, vals.middle || "");
                setValueAndDispatch(lastNameInput, vals.last || "");
                setValueAndDispatch(streetInput, vals.street || "");
                setValueAndDispatch(barangayInput, vals.barangay || "");
                if (municipalitySelect) {
                    municipalitySelect.value = vals.municipality || "";
                    municipalitySelect.dispatchEvent(new Event("change", {
                        bubbles: true
                    }));
                }
                setValueAndDispatch(provinceInput, vals.province || "");
                setValueAndDispatch(contactNumberInput, vals.contact || "");
            };

            let manualApplicantValues = captureApplicantValues();

            const setClientPickError = (msg) => {
                if (!clientPickError) return;
                if (msg) {
                    clientPickError.textContent = msg;
                    clientPickError.style.display = "block";
                } else {
                    clientPickError.textContent = "";
                    clientPickError.style.display = "none";
                }
            };

            function applyClientPickValues(showError = false) {
                if (!clientPick || !clientPick.value) {
                    if (showError) setClientPickError("Please select an existing client.");
                    chosenClientId = null;
                    chosenClientName = null;
                    return;
                }
                const opt = clientPick.options[clientPick.selectedIndex];
                if (!opt) return;
                const ds = opt.dataset || {};
                setClientPickError("");
                setValueAndDispatch(firstNameInput, ds.first || "");
                setValueAndDispatch(middleNameInput, ds.middle || "");
                setValueAndDispatch(lastNameInput, ds.last || "");
                setValueAndDispatch(streetInput, ds.street || "");
                setValueAndDispatch(barangayInput, ds.barangay || "");
                const muni = ds.municipality || ds.city || "";
                if (municipalitySelect && muni) {
                    municipalitySelect.value = muni;
                    municipalitySelect.dispatchEvent(new Event("change", {
                        bubbles: true
                    }));
                }
                setValueAndDispatch(provinceInput, ds.province || "");
                setValueAndDispatch(contactNumberInput, ds.contact || "");
                const declarationInput = document.getElementById("declaration-name");
                if (declarationInput) {
                    const fullName = [ds.first, ds.middle, ds.last].filter(Boolean).join(" ").trim();
                    if (fullName) declarationInput.value = fullName;
                }
                chosenClientId = clientPick.value;
                chosenClientName = {
                    first: ds.first || "",
                    middle: ds.middle || "",
                    last: ds.last || ""
                };
                confirmNewClient = false;
            }

            function setClientMode(mode) {
                if (!clientModeEl) return;
                const isExisting = mode === "existing";
                clientModeEl.value = isExisting ? "existing" : "new";
                if (existingClientRow) existingClientRow.style.display = isExisting ? "" : "none";
                if (btnExistingToggle) btnExistingToggle.style.display = isExisting ? "none" : "inline-flex";
                if (btnNewToggle) btnNewToggle.style.display = isExisting ? "inline-flex" : "none";

                if (isExisting) {
                    manualApplicantValues = captureApplicantValues();
                    if (clientPick && clientPick.value) {
                        applyClientPickValues(false);
                    } else {
                        setClientPickError("");
                    }
                } else {
                    if (clientPick) clientPick.value = "";
                    setClientPickError("");
                    restoreApplicantValues(manualApplicantValues);
                    chosenClientId = null;
                    chosenClientName = null;
                }
            }

            btnExistingToggle?.addEventListener("click", () => setClientMode("existing"));
            btnNewToggle?.addEventListener("click", () => setClientMode("new"));
            clientPick?.addEventListener("change", () => applyClientPickValues(true));
            setClientMode(clientModeEl ? clientModeEl.value : "new");

            const permitRecordsRaw = Array.isArray(window.__CHAINSAW_PERMITS__) ? window.__CHAINSAW_PERMITS__ : [];
            const permitRecordByNo = Object.create(null);
            permitRecordsRaw.forEach((rec) => {
                if (rec && rec.permit_no) permitRecordByNo[rec.permit_no] = rec;
            });

            const renewalPermitSelect = document.getElementById("renewalPermitSelect");
            const renewalPermitPicker = document.getElementById("renewalPermitPicker");
            const renewalQuickSelect = document.getElementById("renewalQuickSelect");
            const renewalQuickSelectWrap = document.getElementById("renewalQuickSelectWrap");
            const renewalPermitFilesWrap = document.getElementById("renewalPermitFiles");
            const renewalPermitFilesList = document.getElementById("renewalPermitFilesList");

            const remoteFileCache = Object.create(null);
            const remoteFileFetches = Object.create(null);
            const remoteFileFallback = Object.create(null);
            const chainsawFileFieldMap = {
                chainsaw_cert_terms: 'file-cert-terms',
                chainsaw_cert_sticker: 'file-cert-sticker',
                chainsaw_staff_work: 'file-memo',
                chainsaw_permit_to_sell: 'file-sell-permit',
                chainsaw_business_permit: 'file-business-permit',
                chainsaw_old_registration: 'file-old-reg',
            };

            const isSameOriginUrl = (url) => {
                try {
                    return new URL(url, window.location.href).origin === window.location.origin;
                } catch {
                    return false;
                }
            };

            function clearFileInput(id) {
                const input = document.getElementById(id);
                if (!input) return;
                input.value = "";
                if (input.dataset && input.dataset.loadedUrl) delete input.dataset.loadedUrl;
                if (remoteFileFallback[id]) delete remoteFileFallback[id];
                const nameEl = input.parentElement?.querySelector(".file-name");
                if (nameEl) nameEl.textContent = "No file chosen";
            }

            function filenameFromUrl(url) {
                try {
                    const clean = url.split("?")[0];
                    const parts = clean.split("/");
                    const last = parts[parts.length - 1] || "file";
                    return decodeURIComponent(last);
                } catch {
                    return "file";
                }
            }

            function setInputFileFromRemote(input, file, url) {
                const nameEl = input.parentElement?.querySelector(".file-name");
                if (typeof DataTransfer !== "undefined") {
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    input.files = dt.files;
                } else if (input.id) {
                    remoteFileFallback[input.id] = file;
                }
                if (input.dataset) input.dataset.loadedUrl = url || "";
                if (nameEl) nameEl.textContent = file.name;
            }

            function attachRemoteFile(inputId, url) {
                const input = document.getElementById(inputId);
                if (!input) return;
                if (!url) {
                    clearFileInput(inputId);
                    return;
                }
                const key = `${inputId}|${url}`;
                const applyFile = (file) => setInputFileFromRemote(input, file, url);
                if (remoteFileCache[key]) {
                    applyFile(remoteFileCache[key]);
                    return;
                }
                if (!remoteFileFetches[key]) {
                    const fetchOpts = isSameOriginUrl(url) ? {
                        credentials: 'include'
                    } : {
                        mode: 'cors',
                        credentials: 'omit'
                    };
                    remoteFileFetches[key] = fetch(url, fetchOpts)
                        .then(res => {
                            if (!res.ok) throw new Error(`Failed to fetch ${url}`);
                            return res.blob();
                        })
                        .then(blob => {
                            const file = new File([blob], filenameFromUrl(url), {
                                type: blob.type || 'application/octet-stream'
                            });
                            remoteFileCache[key] = file;
                            return file;
                        })
                        .catch(err => {
                            console.error('Remote file load failed:', err, url);
                            toast('Auto-attach failed for a requirement file. Open the pill link and re-upload manually.');
                            throw err;
                        })
                        .finally(() => {
                            delete remoteFileFetches[key];
                        });
                }
                remoteFileFetches[key]
                    .then(applyFile)
                    .catch(() => {});
            }

            function resetPermitFilesView(message = 'Select a permit number to view uploads.', show = false) {
                if (!renewalPermitFilesList) return;
                renewalPermitFilesList.innerHTML = `<div class="renewal-cr-files__empty">${message}</div>`;
                if (renewalPermitFilesWrap) renewalPermitFilesWrap.style.display = show ? "flex" : "none";
            }

            function renderPermitFiles(record) {
                if (!renewalPermitFilesWrap || !renewalPermitFilesList) return;
                const entries = record && record.files ? Object.entries(record.files).filter(([, url]) => !!url) : [];
                if (!entries.length) {
                    renewalPermitFilesList.innerHTML = `<div class="renewal-cr-files__empty">No previously uploaded files for this record.</div>`;
                    renewalPermitFilesWrap.style.display = "flex";
                    return;
                }
                renewalPermitFilesList.innerHTML = '';
                entries.forEach(([key, url]) => {
                    const pill = document.createElement('a');
                    pill.href = url;
                    pill.target = '_blank';
                    pill.rel = 'noopener noreferrer';
                    pill.className = 'renewal-cr-files__pill';
                    const label = ({
                        chainsaw_cert_terms: 'Terms & Conditions',
                        chainsaw_cert_sticker: 'Registration sticker',
                        chainsaw_staff_work: 'Staff work',
                        chainsaw_permit_to_sell: 'Permit to sell',
                        chainsaw_business_permit: 'Business permit',
                        chainsaw_old_registration: 'Old registration'
                    } [key]) || key.replace(/_/g, ' ');
                    pill.innerHTML = `<span>${label}</span><i class="fas fa-external-link-alt"></i>`;
                    renewalPermitFilesList.appendChild(pill);
                });
                renewalPermitFilesWrap.style.display = "flex";
            }

            function syncPermitFiles(record) {
                const files = record?.files || {};
                Object.entries(chainsawFileFieldMap).forEach(([key, inputId]) => {
                    const input = document.getElementById(inputId);
                    if (!input) return;
                    clearFileInput(inputId);
                    const url = files[key];
                    if (url) attachRemoteFile(inputId, url);
                });
            }

            function applyPermitRecord(record) {
                const client = record?.client || {};
                setValueAndDispatch(document.getElementById("first-name-r"), client.first || "");
                setValueAndDispatch(document.getElementById("middle-name-r"), client.middle || "");
                setValueAndDispatch(document.getElementById("last-name-r"), client.last || "");
                const addr = record?.address || {};
                const addrLine = addr.full || [addr.street, addr.barangay, addr.municipality, addr.province].filter(Boolean).join(", ");
                setValueAndDispatch(document.getElementById("address-r"), addrLine || "");
                setValueAndDispatch(document.getElementById("street-r"), addr.street || "");
                setValueAndDispatch(document.getElementById("barangay-r"), addr.barangay || "");
                setValueAndDispatch(document.getElementById("municipality-r"), addr.municipality || "");
                setValueAndDispatch(document.getElementById("province-r"), addr.province || "");
                setValueAndDispatch(document.getElementById("contact-number-r"), record?.contact_number || "");
                setValueAndDispatch(document.getElementById("permit-number-r"), record?.permit_no || record?.stored_permit_number || "");
                setValueAndDispatch(document.getElementById("issuance-date-r"), (record?.issued_date || '').slice(0, 10));
                setValueAndDispatch(document.getElementById("expiry-date-r"), (record?.expiry_date || '').slice(0, 10));
                setValueAndDispatch(document.getElementById("purpose-r"), record?.purpose || "");
                setValueAndDispatch(document.getElementById("brand-r"), record?.brand || "");
                setValueAndDispatch(document.getElementById("model-r"), record?.model || "");
                setValueAndDispatch(document.getElementById("acquisition-date-r"), (record?.date_of_acquisition || '').slice(0, 10));
                setValueAndDispatch(document.getElementById("serial-number-r"), record?.serial_number || "");
                setValueAndDispatch(document.getElementById("horsepower-r"), record?.horsepower || "");
                setValueAndDispatch(document.getElementById("guide-bar-length-r"), record?.guide_bar || "");
                const declInput = document.getElementById("declaration-name");
                const fullNameValue = [client.first, client.middle, client.last].filter(Boolean).join(" ").trim();
                if (declInput) {
                    setValueAndDispatch(declInput, fullNameValue || "");
                }
                renderPermitFiles(record);
                syncPermitFiles(record);
                if (record?.client_id) {
                    chosenClientId = record.client_id;
                    chosenClientName = {
                        first: client.first || "",
                        middle: client.middle || "",
                        last: client.last || ""
                    };
                }
            }

            function handlePermitSelect(value) {
                if (!value) {
                    resetPermitFilesView();
                    syncPermitFiles(null);
                    return;
                }
                const record = permitRecordByNo[value];
                if (!record) {
                    toast('No saved details found for that permit number.');
                    resetPermitFilesView('No uploads found for that permit number.', true);
                    syncPermitFiles(null);
                    return;
                }
                applyPermitRecord(record);
            }

            resetPermitFilesView();
            renewalPermitSelect?.addEventListener("change", (e) => {
                const value = e.target.value || "";
                if (renewalQuickSelect && renewalQuickSelect.value !== value) {
                    renewalQuickSelect.value = value;
                }
                handlePermitSelect(value);
            });
            renewalQuickSelect?.addEventListener("change", (e) => {
                const value = e.target.value || "";
                if (renewalPermitSelect) {
                    if (renewalPermitSelect.value !== value) {
                        renewalPermitSelect.value = value;
                    }
                    renewalPermitSelect.dispatchEvent(new Event("change", {
                        bubbles: true
                    }));
                } else {
                    handlePermitSelect(value);
                }
            });
            const btns = document.querySelectorAll(".permit-type-btn");
            const list = document.getElementById("requirementsList");

            function renumberVisible() {
                if (!list) return;
                const items = Array.from(list.querySelectorAll(".requirement-item")).filter(el => el.style.display !== "none");
                items.forEach((el, idx) => {
                    const num = el.querySelector(".requirement-number");
                    if (num) num.textContent = (idx + 1).toString();
                });
            }

            function showPermitGroup(type) {
                document.querySelectorAll(".form-section-group").forEach((g) => {
                    g.style.display = g.getAttribute("data-permit-for") === type ? "" : "none";
                });
            }

            function applyFilter(type) {
                btns.forEach((b) => b.classList.toggle("active", b.dataset.type === type));
                showPermitGroup(type);
                if (clientModeToggle) clientModeToggle.style.display = type === "new" ? "" : "none";
                if (renewalPermitPicker) renewalPermitPicker.style.display = type === "renewal" ? "" : "none";
                if (renewalQuickSelectWrap) {
                    renewalQuickSelectWrap.style.display = type === "renewal" ? "flex" : "none";
                }
                if (type !== "renewal") {
                    if (renewalPermitSelect) renewalPermitSelect.value = "";
                    if (renewalQuickSelect) renewalQuickSelect.value = "";
                    handlePermitSelect("");
                }

                // filter items and sub-requirements
                if (list) {
                    list.style.display = "";
                    list.querySelectorAll(".requirement-item").forEach((el) => {
                        const show = (el.getAttribute("data-show-for") || "").split(",").map((s) => s.trim());
                        el.style.display = show.includes(type) ? "" : "none";
                    });
                    list.querySelectorAll(".sub-requirement[data-show-for]").forEach((sub) => {
                        const show = (sub.getAttribute("data-show-for") || "").split(",").map((s) => s.trim());
                        sub.style.display = show.includes(type) ? "" : "none";
                    });
                }
                renumberVisible();
            }
            btns.forEach((b) => b.addEventListener("click", () => applyFilter(b.dataset.type)));
            applyFilter("new");

            // file input name preview
            document.addEventListener("change", (e) => {
                const input = e.target;
                if (input.classList && input.classList.contains("file-input")) {
                    if (input.dataset && input.dataset.loadedUrl) delete input.dataset.loadedUrl;
                    if (remoteFileFallback[input.id]) delete remoteFileFallback[input.id];
                    const nameSpan = input.parentElement.querySelector(".file-name");
                    nameSpan.textContent = input.files && input.files[0] ? input.files[0].name : "No file chosen";
                }
            });

            // signature pad
            const canvas = document.getElementById("signature-pad");
            const clearBtn = document.getElementById("clear-signature");
            const sigImg = document.getElementById("signature-image");
            let isDrawing = false,
                lastX = 0,
                lastY = 0,
                hasDrawn = false;

            function resizeCanvas() {
                if (!canvas) return;
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                const cssWidth = canvas.clientWidth || 300;
                const cssHeight = canvas.clientHeight || 150;
                canvas.width = Math.floor(cssWidth * ratio);
                canvas.height = Math.floor(cssHeight * ratio);
                const ctx = canvas.getContext("2d");
                ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
                ctx.fillStyle = "#fff";
                ctx.fillRect(0, 0, cssWidth, cssHeight);
                ctx.lineWidth = 2;
                ctx.lineCap = "round";
                ctx.strokeStyle = "#111";
            }

            function getPos(e) {
                const rect = canvas.getBoundingClientRect();
                const touch = e.touches ? e.touches[0] : null;
                const clientX = touch ? touch.clientX : e.clientX;
                const clientY = touch ? touch.clientY : e.clientY;
                return {
                    x: clientX - rect.left,
                    y: clientY - rect.top
                };
            }

            function startDraw(e) {
                isDrawing = true;
                const {
                    x,
                    y
                } = getPos(e);
                lastX = x;
                lastY = y;
                e.preventDefault();
            }

            function draw(e) {
                if (!isDrawing) return;
                const ctx = canvas.getContext("2d");
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
                hasDrawn = true;
                e.preventDefault();
            }

            function endDraw() {
                isDrawing = false;
            }

            if (canvas) {
                resizeCanvas();
                window.addEventListener("resize", resizeCanvas);
                canvas.addEventListener("mousedown", startDraw);
                canvas.addEventListener("mousemove", draw);
                window.addEventListener("mouseup", endDraw);
                canvas.addEventListener("touchstart", startDraw, {
                    passive: false
                });
                canvas.addEventListener("touchmove", draw, {
                    passive: false
                });
                window.addEventListener("touchend", endDraw);
                clearBtn?.addEventListener("click", () => {
                    resizeCanvas();
                    hasDrawn = false;
                    if (sigImg) {
                        sigImg.src = "";
                        sigImg.classList.add("hidden");
                    }
                });
            }

            // helpers
            function dataURLToBlob(dataURL) {
                if (!dataURL) return null;
                const [meta, b64] = dataURL.split(",");
                const mime = (meta.match(/data:(.*?);base64/) || [])[1] || "application/octet-stream";
                const bin = atob(b64 || "");
                const u8 = new Uint8Array(bin.length);
                for (let i = 0; i < bin.length; i++) u8[i] = bin.charCodeAt(i);
                return new Blob([u8], {
                    type: mime
                });
            }

            function makeMHTML(html, parts = []) {
                const boundary = "----=_NextPart_" + Date.now().toString(16);
                const header = [
                    "MIME-Version: 1.0",
                    `Content-Type: multipart/related; type="text/html"; boundary="${boundary}"`,
                    "X-MimeOLE: Produced By Microsoft MimeOLE",
                    "",
                    `--${boundary}`,
                    'Content-Type: text/html; charset="utf-8"',
                    "Content-Transfer-Encoding: 8bit",
                    "",
                    html
                ].join("\r\n");
                const bodyParts = parts.map(p => {
                    const wrapped = p.base64.replace(/.{1,76}/g, "$&\r\n");
                    return [
                        "", `--${boundary}`,
                        `Content-Location: ${p.location}`,
                        "Content-Transfer-Encoding: base64",
                        `Content-Type: ${p.contentType}`, "", wrapped
                    ].join("\r\n");
                }).join("");
                return header + bodyParts + `\r\n--${boundary}--`;
            }

            function toast(msg) {
                const n = document.getElementById("profile-notification");
                n.textContent = msg;
                n.style.display = "block";
                n.style.opacity = "1";
                setTimeout(() => {
                    n.style.opacity = "0";
                    setTimeout(() => {
                        n.style.display = "none";
                        n.style.opacity = "1";
                    }, 350);
                }, 2400);
            }

            function resetForm() {
                document.querySelectorAll("input[type='text'], input[type='date']").forEach(inp => inp.value = "");
                document.querySelectorAll("select").forEach(sel => sel.selectedIndex = 0);
                document.querySelectorAll("input[type='file']").forEach(fi => {
                    fi.value = "";
                    const nameSpan = fi.parentElement?.querySelector(".file-name");
                    if (nameSpan) nameSpan.textContent = "No file chosen";
                    if (fi.dataset && fi.dataset.loadedUrl) delete fi.dataset.loadedUrl;
                    if (remoteFileFallback[fi.id]) delete remoteFileFallback[fi.id];
                });
                hasDrawn = false;
                const sigImg = document.getElementById("signature-image");
                if (sigImg) {
                    sigImg.src = "";
                    sigImg.classList.add("hidden");
                }
                if (canvas) resizeCanvas();
                applyFilter("new");
                if (clientPick) clientPick.value = "";
                setClientMode("new");
                manualApplicantValues = captureApplicantValues();
                if (renewalPermitSelect) renewalPermitSelect.value = "";
                handlePermitSelect("");
            }

            // elements
            const confirmModal = document.getElementById("confirmModal");
            const btnSubmit = document.getElementById("submitApplication");
            const btnOk = document.getElementById("btnOkConfirm");
            const btnCancel = document.getElementById("btnCancelConfirm");
            const loading = document.getElementById("loadingIndicator");

            const pendingNewModal = document.getElementById("pendingNewModal");
            const pendingNewOk = document.getElementById("pendingNewOk");
            const offerRenewalModal = document.getElementById("offerRenewalModal");
            const offerRenewalOk = document.getElementById("offerRenewalOk");
            const offerRenewalSwitch = document.getElementById("offerRenewalSwitch");

            // NEW: Need Approved NEW modal refs
            const needApprovedNewModal = document.getElementById("needApprovedNewModal");
            const needApprovedNewOk = document.getElementById("needApprovedNewOk");
            const needApprovedNewSwitch = document.getElementById("needApprovedNewSwitch");

            pendingNewOk?.addEventListener("click", () => {
                pendingNewModal.style.display = "none";
            });
            offerRenewalOk?.addEventListener("click", () => {
                offerRenewalModal.style.display = "none";
            });
            needApprovedNewOk?.addEventListener("click", () => {
                needApprovedNewModal.style.display = "none";
            });

            // value helper
            function v(id) {
                return (document.getElementById(id)?.value || "").trim();
            }

            // Autofill RENEWAL from NEW (used when offering renewal)
            function autofillRenewalFromNew() {
                const map = [
                    ["first-name", "first-name-r"],
                    ["middle-name", "middle-name-r"],
                    ["last-name", "last-name-r"],
                    ["street", "street-r"],
                    ["barangay", "barangay-r"],
                    ["municipality", "municipality-r"],
                    ["province", "province-r"],
                    ["contact-number", "contact-number-r"],
                    ["purpose", "purpose-r"],
                    ["brand", "brand-r"],
                    ["model", "model-r"],
                    ["acquisition-date", "acquisition-date-r"],
                    ["serial-number", "serial-number-r"],
                    ["horsepower", "horsepower-r"],
                    ["guide-bar-length", "guide-bar-length-r"],
                ];
                map.forEach(([srcId, dstId]) => {
                    const src = document.getElementById(srcId);
                    const dst = document.getElementById(dstId);
                    if (src && dst && typeof src.value === "string") dst.value = src.value;
                });
                const addr = [v("street"), v("barangay"), v("municipality"), v("province")].filter(Boolean).join(", ");
                const addrR = document.getElementById("address-r");
                if (addrR) addrR.value = addr;
                const decl = document.getElementById("declaration-name");
                const fullName = [v("first-name"), v("middle-name"), v("last-name")].filter(Boolean).join(" ");
                if (decl && !decl.value) decl.value = fullName;
            }

            // NEW: Autofill NEW from RENEWAL (used when renewal is blocked; switch to NEW)
            function autofillNewFromRenewal() {
                const map = [
                    ["first-name-r", "first-name"],
                    ["middle-name-r", "middle-name"],
                    ["last-name-r", "last-name"],
                    ["street-r", "street"],
                    ["barangay-r", "barangay"],
                    ["municipality-r", "municipality"],
                    ["province-r", "province"],
                    ["contact-number-r", "contact-number"],
                    ["purpose-r", "purpose"],
                    ["brand-r", "brand"],
                    ["model-r", "model"],
                    ["acquisition-date-r", "acquisition-date"],
                    ["serial-number-r", "serial-number"],
                    ["horsepower-r", "horsepower"],
                    ["guide-bar-length-r", "guide-bar-length"],
                ];
                map.forEach(([srcId, dstId]) => {
                    const src = document.getElementById(srcId);
                    const dst = document.getElementById(dstId);
                    if (src && dst && typeof src.value === "string") dst.value = src.value;
                });
                // declaration name if blank
                const decl = document.getElementById("declaration-name");
                if (decl && !decl.value) {
                    const fullName = [v("first-name-r"), v("middle-name-r"), v("last-name-r")].filter(Boolean).join(" ");
                    decl.value = fullName;
                }
            }

            // Switch to renewal via offer
            offerRenewalSwitch?.addEventListener("click", () => {
                offerRenewalModal.style.display = "none";
                applyFilter("renewal");
                autofillRenewalFromNew();
                window.scrollTo({
                    top: 0,
                    behavior: "smooth"
                });
            });

            // NEW: Switch to NEW from the "Need Approved NEW" modal
            needApprovedNewSwitch?.addEventListener("click", () => {
                needApprovedNewModal.style.display = "none";
                applyFilter("new");
                autofillNewFromRenewal();
                window.scrollTo({
                    top: 0,
                    behavior: "smooth"
                });
            });

            btnCancel?.addEventListener("click", () => {
                confirmModal.style.display = "none";
            });

            const activePermitType = () =>
                (document.querySelector(".permit-type-btn.active")?.getAttribute("data-type") || "new");

            // PRECHECK before confirm (sync with backend decisions)
            btnSubmit?.addEventListener("click", async () => {
                try {

                    const type = activePermitType();

                    // 1) First pass precheck
                    const base = await precheckWith(type, null);

                    // 2) NEW FLOW — unchanged logic
                    if (type === "new") {
                        if (base.block === "for_payment") {
                            await openModal({
                                title: 'Payment Due',
                                html: MSG.pay,
                                buttons: [{
                                    text: 'Okay',
                                    variant: 'primary',
                                    value: 'ok'
                                }]
                            });
                            return;
                        }
                        if (base.block === "pending_new") {
                            if (pendingNewModal) pendingNewModal.style.display = "flex";
                            else await openModal({
                                title: 'Pending Request',
                                html: 'You already have a pending <b>new</b> chainsaw permit request. Please wait for updates before submitting another one.',
                                buttons: [{
                                    text: 'Okay',
                                    variant: 'primary',
                                    value: 'ok'
                                }]
                            });
                            return;
                        }
                        if (base.block === "pending_renewal") {
                            toast("You already have a pending chainsaw renewal. Please wait for the update first.");
                            return;
                        }
                        if (base.offer === "renewal") {
                            const act = offerRenewalModal ? (offerRenewalModal.style.display = "flex", 'ok') : await openModal({
                                title: 'Renewal Available',
                                html: MSG.offerR,
                                buttons: [{
                                    text: 'Okay',
                                    variant: 'outline',
                                    value: 'ok'
                                }, {
                                    text: 'Request renewal',
                                    variant: 'primary',
                                    value: 'switch'
                                }]
                            });
                            if (act === 'switch') {
                                applyFilter('renewal');
                                autofillRenewalFromNew();
                                window.scrollTo({
                                    top: 0,
                                    behavior: 'smooth'
                                });
                            }
                            return;
                        }

                        const cands = Array.isArray(base.candidates) ? base.candidates :
                            (base.existing_client_id ? [{
                                client_id: base.existing_client_id,
                                first_name: base.existing_client_first || '',
                                middle_name: base.existing_client_middle || '',
                                last_name: base.existing_client_last || '',
                                score: base.suggestion_score || 0.7
                            }] : []);
                        if (cands.length) {
                            const choice = await openModal({
                                title: 'Use existing client?',
                                html: `We found existing client records that look like a match. Do you want to use one of them?${renderCandidateList(cands)}`,
                                buttons: [{
                                        text: 'Cancel',
                                        variant: 'outline',
                                        value: 'cancel'
                                    },
                                    {
                                        text: 'Create as new',
                                        variant: 'outline',
                                        value: 'new'
                                    },
                                    {
                                        text: 'Use existing',
                                        variant: 'primary',
                                        value: 'use'
                                    }
                                ]
                            });
                            if (choice === 'cancel') return;
                            if (choice === 'new') {
                                confirmNewClient = true;
                            } else if (choice === 'use') {
                                const picked = readSelectedCandidateId();
                                if (picked) {
                                    chosenClientId = picked;
                                    const j2 = await precheckWith(type, picked);
                                    chosenClientName = {
                                        first: j2.existing_client_first || '',
                                        middle: j2.existing_client_middle || '',
                                        last: j2.existing_client_last || ''
                                    };
                                    if (j2.block === "for_payment") {
                                        await openModal({
                                            title: 'Payment Due',
                                            html: MSG.pay,
                                            buttons: [{
                                                text: 'Okay',
                                                variant: 'primary',
                                                value: 'ok'
                                            }]
                                        });
                                        return;
                                    }
                                    if (j2.block === "pending_new") {
                                        if (pendingNewModal) pendingNewModal.style.display = "flex";
                                        else await openModal({
                                            title: 'Pending Request',
                                            html: 'You already have a pending <b>new</b> chainsaw permit request. Please wait for updates before submitting another one.',
                                            buttons: [{
                                                text: 'Okay',
                                                variant: 'primary',
                                                value: 'ok'
                                            }]
                                        });
                                        return;
                                    }
                                    if (j2.block === "pending_renewal") {
                                        toast("You already have a pending chainsaw renewal. Please wait for the update first.");
                                        return;
                                    }
                                    if (j2.offer === "renewal") {
                                        const sw = await openModal({
                                            title: 'Renewal Available',
                                            html: MSG.offerR,
                                            buttons: [{
                                                text: 'Okay',
                                                variant: 'outline',
                                                value: 'ok'
                                            }, {
                                                text: 'Request renewal',
                                                variant: 'primary',
                                                value: 'switch'
                                            }]
                                        });
                                        if (sw === 'switch') {
                                            applyFilter('renewal');
                                            autofillRenewalFromNew();
                                            window.scrollTo({
                                                top: 0,
                                                behavior: 'smooth'
                                            });
                                            return;
                                        }
                                    }
                                }
                            }
                        }

                        // confirm submit
                        if (typeof confirmModal !== "undefined" && confirmModal) {
                            confirmModal.style.display = "flex";
                        } else {
                            const ans = await openModal({
                                title: 'Submit Application',
                                html: 'Please confirm you want to submit this Chainsaw application. Files will be uploaded and your request will enter review.',
                                buttons: [{
                                    text: 'Cancel',
                                    variant: 'outline',
                                    value: 'cancel'
                                }, {
                                    text: 'Yes, submit',
                                    variant: 'primary',
                                    value: 'ok'
                                }]
                            });
                            if (ans === 'ok') {
                                loading.style.display = "flex";
                                try {
                                    await doSubmit();
                                    toast("Application submitted. We'll notify you once reviewed.");
                                    resetForm();
                                } catch (e) {
                                    console.error(e);
                                    if (!e || !e.isBlocked) {
                                        toast(e?.message || "Submission failed. Please try again.");
                                    }
                                } finally {
                                    loading.style.display = "none";
                                }
                            }
                        }
                        return;
                    }

                    const handleRenewalBlock = async (json) => {
                        if (!json) return false;

                        // server-declared block codes
                        if (json.block === "for_payment") {
                            await openModal({
                                title: 'Payment Due',
                                html: MSG.pay,
                                buttons: [{
                                    text: 'Okay',
                                    variant: 'primary',
                                    value: 'ok'
                                }]
                            });
                            return true;
                        }
                        if (json.block === "pending_renewal") {
                            toast("You already have a pending chainsaw renewal. Please wait for the update first.");
                            return true;
                        }
                        if (json.block === "unexpired_permit") {
                            await openModal({
                                title: 'Unexpired Permit',
                                html: MSG.unexpired,
                                buttons: [{
                                    text: 'Okay',
                                    variant: 'primary',
                                    value: 'ok'
                                }]
                            });
                            return true;
                        }

                        // Special-case: missing released NEW permit (either signalled by precheck block or by flags)
                        const flags = json.flags || {};
                        const missingReleased = (json.block === 'need_released_new');
                        if (missingReleased) {
                            const act = await openModal({
                                title: 'Action Required',
                                html: escHtml(json.message || 'To file a renewal, the client must already have a released NEW chainsaw permit record.'),
                                buttons: [{
                                    text: 'Request new',
                                    variant: 'outline',
                                    value: 'request_new'
                                }, {
                                    text: 'Close',
                                    variant: 'primary',
                                    value: 'close'
                                }]
                            });
                            if (act === 'request_new') {
                                applyFilter('new');
                                autofillNewFromRenewal();
                                window.scrollTo({
                                    top: 0,
                                    behavior: 'smooth'
                                });
                            }
                            return true;
                        }

                        return false;
                    };

                    // 1) Ask the user first
                    const picked = await confirmDetectedClientForRenewal(base);
                    if (picked === null) return; // user cancelled or switched to NEW

                    // 2) Record the choice
                    let json = base;
                    chosenClientId = null;
                    chosenClientName = null;
                    confirmNewClient = false;

                    if (picked === RENEWAL_TYPED) {
                        // user wants to proceed with the typed details (i.e., don't force the matched client)
                        confirmNewClient = true;
                    } else {
                        // user explicitly chose an existing client -> re-precheck for THAT client, then gate
                        chosenClientId = picked;
                        json = await precheckWith("renewal", picked);
                        chosenClientName = {
                            first: json.existing_client_first || '',
                            middle: json.existing_client_middle || '',
                            last: json.existing_client_last || ''
                        };

                        // Now it’s appropriate to apply hard blocks for the chosen client
                        if (await handleRenewalBlock(json)) return;
                    }


                    // Confirm submit
                    if (typeof confirmModal !== "undefined" && confirmModal) {
                        confirmModal.style.display = "flex";
                    } else {
                        const ans = await openModal({
                            title: 'Submit Application',
                            html: 'Please confirm you want to submit this Chainsaw application. Files will be uploaded and your request will enter review.',
                            buttons: [{
                                text: 'Cancel',
                                variant: 'outline',
                                value: 'cancel'
                            }, {
                                text: 'Yes, submit',
                                variant: 'primary',
                                value: 'ok'
                            }]
                        });
                        if (ans === 'ok') {
                            loading.style.display = "flex";
                            try {
                                await doSubmit();
                                toast("Application submitted. We'll notify you once reviewed.");
                                resetForm();
                            } catch (e) {
                                console.error(e);
                                if (!e || !e.isBlocked) {
                                    toast(e?.message || "Submission failed. Please try again.");
                                }
                            } finally {
                                loading.style.display = "none";
                            }
                        }
                    }
                } catch (e) {
                    console.error(e);
                    if (typeof confirmModal !== "undefined" && confirmModal) {
                        confirmModal.style.display = "flex";
                    } else {
                        const ans = await openModal({
                            title: 'Submit Application',
                            html: 'Proceed with submission?',
                            buttons: [{
                                text: 'Cancel',
                                variant: 'outline',
                                value: 'cancel'
                            }, {
                                text: 'Yes, submit',
                                variant: 'primary',
                                value: 'ok'
                            }]
                        });
                        if (ans === 'ok') {
                            loading.style.display = "flex";
                            try {
                                await doSubmit();
                                toast("Application submitted. We'll notify you once reviewed.");
                                resetForm();
                            } catch (err) {
                                console.error(err);
                                toast(err?.message || "Submission failed. Please try again.");
                            } finally {
                                loading.style.display = "none";
                            }
                        }
                    }
                }
            });



            // FINAL submit
            document.getElementById("btnOkConfirm")?.addEventListener("click", async () => {
                confirmModal.style.display = "none";
                loading.style.display = "flex";
                try {
                    await doSubmit();
                    toast("Application submitted. We'll notify you once reviewed.");
                    resetForm();
                } catch (e) {
                    console.error(e);
                    if (!e || !e.isBlocked) {
                        toast(e?.message || "Submission failed. Please try again.");
                    }
                } finally {
                    loading.style.display = "none";
                }
            });

            async function doSubmit() {
                // Signature capture
                let signatureDataURL = "";
                if (canvas && hasDrawn) signatureDataURL = canvas.toDataURL("image/png");

                const type = activePermitType();
                let firstName, middleName, lastName, sitio_street, barangay, municipality, province, contact_number;
                let permit_number = "",
                    issuance_date = "",
                    expiry_date = "";
                let purpose, brand, model, date_of_acquisition, serial_number, horsepower, maximum_length_of_guide_bar;
                let address_line = "";

                if (type === "renewal") {
                    firstName = v("first-name-r");
                    middleName = v("middle-name-r");
                    lastName = v("last-name-r");
                    address_line = v("address-r");
                    sitio_street = v("street-r");
                    barangay = v("barangay-r");
                    municipality = v("municipality-r");
                    province = v("province-r");
                    contact_number = v("contact-number-r");
                    permit_number = v("permit-number-r");
                    issuance_date = v("issuance-date-r");
                    expiry_date = v("expiry-date-r");
                    purpose = v("purpose-r");
                    brand = v("brand-r");
                    model = v("model-r");
                    date_of_acquisition = v("acquisition-date-r");
                    serial_number = v("serial-number-r");
                    horsepower = v("horsepower-r");
                    maximum_length_of_guide_bar = v("guide-bar-length-r");
                } else {
                    firstName = v("first-name");
                    middleName = v("middle-name");
                    lastName = v("last-name");
                    sitio_street = v("street");
                    barangay = v("barangay");
                    municipality = v("municipality");
                    province = v("province");
                    contact_number = v("contact-number");
                    purpose = v("purpose");
                    brand = v("brand");
                    model = v("model");
                    date_of_acquisition = v("acquisition-date");
                    serial_number = v("serial-number");
                    horsepower = v("horsepower");
                    maximum_length_of_guide_bar = v("guide-bar-length");
                }

                const fullNameForDoc = (
                    chosenClientId && chosenClientName ? [chosenClientName.first, chosenClientName.middle, chosenClientName.last] : [firstName, middleName, lastName]
                ).filter(Boolean).join(' ').replace(/\s+/g, ' ').trim();

                // Build MHTML doc (Word-opening)
                const sigLocation = "signature.png";
                const hasSignature = !!signatureDataURL;
                const titleLine = type === "renewal" ? "Application for Renewal of Chainsaw Permit" : "Application for New Chainsaw Permit";
                const signatureBlock = hasSignature ?
                    `<div style="margin-top:28px;">
             <img src="${sigLocation}" width="${SIG_WIDTH}" height="${SIG_HEIGHT}"
                  style="display:block;border:1px solid #ddd;padding:4px;border-radius:4px;width:${SIG_WIDTH}px;height:${SIG_HEIGHT}px;" alt="Signature"/>
             <p style="margin-top:6px;">Signature of Applicant</p>
           </div>` :
                    `<div style="margin-top:40px;">
             <div style="border-top:1px solid #000;width:50%;padding-top:3pt;"></div>
             <p>Signature of Applicant</p>
           </div>`;

                const addrJoined = [address_line, sitio_street, barangay, municipality, province].filter(Boolean).join(", ");
                const bodyHtml = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office"
              xmlns:w="urn:schemas-microsoft-com:office:word"
              xmlns="http://www.w3.org/TR/REC-html40">
          <head><meta charset="UTF-8"><title>Chainsaw Registration Form</title></head>
          <body>
            <div style="text-align:center">
              <p><b>Republic of the Philippines</b></p>
              <p><b>Department of Environment and Natural Resources</b></p>
              <p>Community Environment and Natural Resources Office (CENRO)</p>
              <p>Argao, Cebu</p>
            </div>
            <h3 style="text-align:center;">${titleLine}</h3>

            <p><b>I. APPLICANT INFORMATION</b></p>
            <p>Name: <u>${fullNameForDoc}</u></p>
            <p>Address: <u>${addrJoined}</u></p>
            <p>Contact Number: <u>${contact_number}</u></p>

            ${type === "renewal" ? `
            <p><b>II. EXISTING CHAINSAW PERMIT INFORMATION</b></p>
            <p>Permit Number: <u>${permit_number}</u></p>
            <p>Date of Original Issuance: <u>${issuance_date}</u></p>
            <p>Expiry Date: <u>${expiry_date}</u></p>
            ` : ""}

            <p><b>${type === "renewal" ? "III" : "II"}. CHAINSAW INFORMATION AND DESCRIPTION</b></p>
            <p>Purpose of Use: <u>${purpose}</u></p>
            <p>Brand: <u>${brand}</u></p>
            <p>Model: <u>${model}</u></p>
            <p>Date of Acquisition: <u>${date_of_acquisition}</u></p>
            <p>Serial Number: <u>${serial_number}</u></p>
            <p>Horsepower: <u>${horsepower}</u></p>
            <p>Maximum Length of Guide Bar: <u>${maximum_length_of_guide_bar}</u></p>

            <p><b>${type === "renewal" ? "IV" : "III"}. DECLARATION AND SUBMISSION</b></p>
            ${signatureBlock}
          </body>
        </html>`.trim();

                const parts = hasSignature ? [{
                    location: sigLocation,
                    contentType: "image/png",
                    base64: (signatureDataURL.split(",")[1] || "")
                }] : [];
                const mhtml = makeMHTML(bodyHtml, parts);
                const docBlob = new Blob([mhtml], {
                    type: "application/msword"
                });
                const docFileName =
                    `${type === "renewal" ? "Chainsaw_Renewal" : "Chainsaw_New"}_${(fullNameForDoc || "Applicant").replace(/\s+/g, "_")}.doc`;
                const docFile = new File([docBlob], docFileName, {
                    type: "application/msword"
                });

                const fd = new FormData();
                fd.append("permit_type", type);
                fd.append("first_name", firstName);
                fd.append("middle_name", middleName);
                fd.append("last_name", lastName);
                fd.append("sitio_street", sitio_street);
                fd.append("barangay", barangay);
                fd.append("municipality", municipality);
                fd.append("province", province);
                fd.append("contact_number", contact_number);
                if (type === "renewal") {
                    fd.append("permit_number", permit_number);
                    fd.append("issuance_date", issuance_date);
                    fd.append("expiry_date", expiry_date);
                }
                fd.append("purpose", purpose);
                fd.append("brand", brand);
                fd.append("model", model);
                fd.append("date_of_acquisition", date_of_acquisition);
                fd.append("serial_number", serial_number);
                fd.append("horsepower", horsepower);
                fd.append("maximum_length_of_guide_bar", maximum_length_of_guide_bar);

                // generated application document
                fd.append("application_doc", docFile);

                // signature file (optional)
                if (hasSignature) {
                    const sigBlob = dataURLToBlob(signatureDataURL);
                    fd.append("signature_file", new File([sigBlob], "signature.png", {
                        type: "image/png"
                    }));
                }

                // Attach files (Application letter intentionally NOT present)
                const pick = (id) => {
                    const input = document.getElementById(id);
                    if (!input) return remoteFileFallback[id] || null;
                    return input.files?.[0] || remoteFileFallback[id] || null;
                };

                if (type === "new") {
                    const files = {
                        chainsaw_cert_terms: pick("file-cert-terms"),
                        chainsaw_cert_sticker: pick("file-cert-sticker"),
                        chainsaw_staff_work: pick("file-memo"),
                        geo_photos: pick("file-geo"),
                        chainsaw_permit_to_sell: pick("file-sell-permit"),
                        chainsaw_business_permit: pick("file-business-permit"),
                        // REMOVED: chainsaw_old_registration (now renewal-only)
                    };
                    Object.entries(files).forEach(([name, file]) => {
                        if (file) fd.append(name, file);
                    });
                } else {
                    const filesRenewal = {
                        chainsaw_cert_terms: pick("file-cert-terms"), // 1.1
                        chainsaw_cert_sticker: pick("file-cert-sticker"), // 1.2
                        chainsaw_staff_work: pick("file-memo"), // 2
                        geo_photos: pick("file-geo"), // 3
                        chainsaw_old_registration: pick("file-old-reg"), // ADDED for renewal
                    };
                    Object.entries(filesRenewal).forEach(([name, file]) => {
                        if (file) fd.append(name, file);
                    });
                }


                // Carry user’s choice from the fuzzy step
                if (chosenClientId) fd.append("use_existing_client_id", String(chosenClientId));
                if (confirmNewClient) fd.append("confirm_new_client", "1");

                // Now submit
                const res = await fetch(SAVE_URL, {
                    method: "POST",
                    body: fd,
                    credentials: "include"
                });

                let json;
                try {
                    json = await res.json();
                } catch {
                    const text = await res.text();
                    throw new Error(`HTTP ${res.status} – ${text.slice(0, 200)}`);
                }

                // If the server responds with a structured block (e.g. need_released_new), show the modal and surface a handled error
                if (json && json.block === 'need_released_new') {
                    const act = await openModal({
                        title: 'Action Required',
                        html: escHtml(json.message || 'To file a renewal, the client must already have a released NEW chainsaw permit record.'),
                        buttons: [{
                            text: 'Request new',
                            variant: 'outline',
                            value: 'request_new'
                        }, {
                            text: 'Close',
                            variant: 'primary',
                            value: 'close'
                        }]
                    });
                    if (act === 'request_new') {
                        applyFilter('new');
                        autofillNewFromRenewal();
                        window.scrollTo({
                            top: 0,
                            behavior: 'smooth'
                        });
                    }
                    const err = new Error(json.message || 'Blocked');
                    err.isBlocked = true;
                    throw err;
                }

                if (!res.ok || !json.ok) throw new Error(json.error || `HTTP ${res.status}`);
            }

            // mobile menu toggle
            const mobileToggle = document.querySelector(".mobile-toggle");
            const navContainer = document.querySelector(".nav-container");
            mobileToggle?.addEventListener("click", () => {
                const isActive = navContainer.classList.toggle("active");
                document.body.style.overflow = isActive ? "hidden" : "";
            });
        })();
    </script>
    <script>
        /*! chainsaw-validate.js (concise msgs) */
        (() => {
            "use strict";
            const $ = (id) => document.getElementById(id);
            const q = (sel, root = document) => root.querySelector(sel);
            const qa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
            const TYPE = {
                NEW: "new",
                REN: "renewal"
            };
            const curType = () => (q(".permit-type-btn.active")?.dataset.type || TYPE.NEW);

            // Inject minimal error CSS (red text only)
            (() => {
                const s = document.createElement("style");
                s.textContent = `.fv-error{color:#d93025;font-size:.88rem;line-height:1.3;margin-top:6px}`;
                document.head.appendChild(s);
            })();

            // Error helpers (render under control; no borders)
            function setErr(el, msg) {
                if (!el) return false;
                clrErr(el);
                const d = document.createElement("div");
                d.className = "fv-error";
                d.setAttribute("role", "alert");
                d.textContent = msg;
                el.insertAdjacentElement("afterend", d);
                return false;
            }

            function clrErr(el) {
                if (!el) return;
                const sib = el.nextElementSibling;
                if (sib && sib.classList.contains("fv-error")) sib.remove();
            }

            function firstErrFocus() {
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
            }

            // Utils
            const blank = (v) => !v || !String(v).trim();
            const letters = (v) => /^[A-Za-z][A-Za-z\s'’-]*[A-Za-z]$/.test(v.trim());
            const rep4 = (v) => /(.)\1{3,}/.test(v);
            const num = (v) => {
                const n = parseFloat(String(v).replace(/,/g, "."));
                return isNaN(n) ? NaN : n;
            };
            const isPHMobile = (s) => /^(\+639|639|09)\d{9}$/.test(String(s).replace(/[^\d+]/g, ""));
            const isFuture = (iso) => {
                if (!iso) return false;
                const d = new Date(iso + "T00:00:00");
                const t = new Date();
                t.setHours(0, 0, 0, 0);
                return d > t;
            };
            const before = (a, b) => new Date(a + "T00:00:00") < new Date(b + "T00:00:00");

            function barInches(raw) {
                if (!raw) return NaN;
                const s = raw.trim().toLowerCase();
                const mCm = s.match(/^(\d+(\.\d+)?)\s*cm$/);
                const mIn = s.match(/^(\d+(\.\d+)?)\s*(in|inch|inches|")?$/);
                if (mCm) return parseFloat(mCm[1]) / 2.54;
                if (mIn) return parseFloat(mIn[1]);
                const n0 = num(s);
                return isNaN(n0) ? NaN : n0;
            }

            // Quick JPEG GPS check (EXIF)
            async function jpegHasGPS(file) {
                if (!file || file.type !== "image/jpeg") return false;
                const buf = await file.slice(0, 128 * 1024).arrayBuffer();
                const dv = new DataView(buf);
                if (dv.getUint8(0) !== 0xff || dv.getUint8(1) !== 0xd8) return false;
                let off = 2;
                while (off + 3 < dv.byteLength) {
                    const marker = dv.getUint16(off);
                    off += 2;
                    if ((marker & 0xff00) !== 0xff00) break;
                    if (marker === 0xffda) break;
                    const len = dv.getUint16(off);
                    if (marker === 0xffe1 && len >= 8) {
                        const id = new TextDecoder().decode(new Uint8Array(buf, off + 2, 6));
                        if (/^Exif\0\0/.test(id)) {
                            const tiff = off + 8;
                            const be = new TextDecoder().decode(new Uint8Array(buf, tiff, 2)) === "MM";
                            const u16 = (p) => dv.getUint16(p, !be);
                            const u32 = (p) => dv.getUint32(p, !be);
                            const ifd0 = tiff + u32(tiff + 4);
                            const n = u16(ifd0);
                            for (let i = 0; i < n; i++) {
                                const e = ifd0 + 2 + i * 12;
                                if (u16(e) === 0x8825) {
                                    const gpsIfd = tiff + u32(e + 8);
                                    const gn = u16(gpsIfd);
                                    let lat = false,
                                        lon = false;
                                    for (let j = 0; j < gn; j++) {
                                        const te = gpsIfd + 2 + j * 12;
                                        const tag = u16(te);
                                        if (tag === 0x0002) lat = true;
                                        if (tag === 0x0004) lon = true;
                                    }
                                    return lat && lon;
                                }
                            }
                        }
                    }
                    off += len;
                }
                return false;
            }

            // Allowed municipalities (read from select)
            const MUNIC = (() => {
                const sel = $("#municipality");
                if (!sel) return [];
                return qa("option", sel).map((o) => o.textContent.trim()).filter((t) => t && !/^Select/i.test(t));
            })();

            // Validators (concise messages)
            const nameReq = (el, label) => {
                const v = el?.value?.trim() || "";
                clrErr(el);
                if (blank(v)) return setErr(el, "Required.");
                if (v.length < 2) return setErr(el, "Too short.");
                if (!letters(v)) return setErr(el, "Letters only.");
                if (rep4(v)) return setErr(el, "Looks invalid.");
                return true;
            };
            const nameOpt = (el) => {
                const v = el?.value?.trim() || "";
                clrErr(el);
                if (!v) return true;
                if (v.length === 1) return true;
                if (!letters(v)) return setErr(el, "Letters only.");
                if (rep4(v)) return setErr(el, "Looks invalid.");
                return true;
            };
            const vStreet = (el) => {
                const v = el?.value?.trim() || "";
                clrErr(el);
                if (blank(v)) return setErr(el, "Required.");
                if (v.length < 3) return setErr(el, "Be specific.");
                if (/^\d+$/.test(v)) return setErr(el, "Not numbers only.");
                return true;
            };
            const vBarangay = (el) => {
                const v = el?.value?.trim() || "";
                clrErr(el);
                if (blank(v)) return setErr(el, "Required.");
                return true;
            };
            const vMunicipNew = (sel) => {
                clrErr(sel);
                if (!sel || !sel.value) return setErr(sel, "Select one.");
                return true;
            };
            const vMunicipRen = (el) => {
                const v = el?.value?.trim() || "";
                clrErr(el);
                if (blank(v)) return setErr(el, "Required.");
                if (MUNIC.length && !MUNIC.some((m) => m.toLowerCase() === v.toLowerCase()))
                    return setErr(el, "Invalid.");
                return true;
            };
            const vProv = (el) => {
                const v = el?.value?.trim() || "";
                clrErr(el);
                if (blank(v)) return setErr(el, "Required.");
                if (/^\d+$/.test(v)) return setErr(el, "Letters only.");
                return true;
            };
            const vPhone = (el) => {
                const v = el?.value?.trim() || "";
                clrErr(el);
                if (blank(v)) return setErr(el, "Required.");
                if (!isPHMobile(v)) return setErr(el, "Use 09/639 format.");
                return true;
            };
            const vPurpose = (el) => {
                const v = el?.value?.trim() || "";
                clrErr(el);
                if (blank(v)) return setErr(el, "Required.");
                if (v.length < 10) return setErr(el, "Min 10 chars.");
                if (/^(test|na|n\/a|sample|asdf)$/i.test(v)) return setErr(el, "Be specific.");
                return true;
            };
            const vBrand = (el) => {
                const v = el?.value?.trim() || "";
                clrErr(el);
                if (blank(v)) return setErr(el, "Required.");
                if (/^\d+$/.test(v)) return setErr(el, "Not numbers only.");
                return true;
            };
            const vModel = (el) => {
                const v = el?.value?.trim() || "";
                clrErr(el);
                if (blank(v)) return setErr(el, "Required.");
                if (v.length < 2) return setErr(el, "Too short.");
                return true;
            };
            const vAcq = (el) => {
                const v = el?.value || "";
                clrErr(el);
                if (!v) return setErr(el, "Required.");
                if (isFuture(v)) return setErr(el, "No future date.");
                if (new Date(v) < new Date("1990-01-01")) return setErr(el, "Unrealistic.");
                return true;
            };
            const vSerial = (el) => {
                const v = el?.value?.trim() || "";
                clrErr(el);
                if (blank(v)) return setErr(el, "Required.");
                if (v.length < 5) return setErr(el, "Too short.");
                if (/^[^A-Za-z0-9]+$/.test(v)) return setErr(el, "Alphanumeric.");
                if (rep4(v)) return setErr(el, "Looks invalid.");
                return true;
            };
            const vHPopt = (el) => {
                const r = el?.value?.trim() || "";
                clrErr(el);
                if (!r) return true;
                const n = num(r);
                if (isNaN(n)) return setErr(el, "Number only.");
                if (n < 0.5 || n > 15) return setErr(el, "0.5–15.");
                return true;
            };
            const vBar = (el) => {
                const r = el?.value?.trim() || "";
                clrErr(el);
                if (!r) return setErr(el, "Required.");
                const inches = barInches(r);
                if (isNaN(inches)) return setErr(el, `Use in/cm.`);
                if (inches < 8 || inches > 36) return setErr(el, "8–36 in.");
                return true;
            };
            const vPermitNo = (el) => {
                const v = el?.value?.trim() || "";
                clrErr(el);
                if (blank(v)) return setErr(el, "Required.");
                return true;
            };
            const vIssExp = (iss, exp) => {
                clrErr(iss);
                clrErr(exp);
                const a = iss?.value || "",
                    b = exp?.value || "";
                if (!a) return setErr(iss, "Required.");
                if (!b) return setErr(exp, "Required.");
                if (!before(a, b)) return setErr(exp, "After issuance.");
                if (isFuture(a)) return setErr(iss, "No future date.");
                return true;
            };

            // File helpers
            function okType(file, accept) {
                if (!file) return false;
                const ext = (file.name.split(".").pop() || "").toLowerCase();
                const map = {
                    pdf: ["pdf"],
                    doc: ["doc", "docx"],
                    img: ["jpg", "jpeg", "png"],
                };
                const allow = {
                    "pdf,doc,docx": [...map.pdf, ...map.doc],
                    "jpg,jpeg,png": map.img,
                    "pdf,doc,docx,jpg,jpeg,png": [...map.pdf, ...map.doc, ...map.img],
                } [accept] || [];
                return allow.includes(ext);
            }

            async function vFiles(type) {
                let ok = true;
                const need = (inp, msg, types, maxMB) => {
                    clrErr(inp);
                    const f = inp?.files?.[0];
                    if (!f) {
                        ok = setErr(inp, msg);
                        return;
                    }
                    if (!okType(f, types)) ok = setErr(inp, `Allowed: ${types.replace(/,/g, "/")}.`);
                    else if (f.size > maxMB * 1024 * 1024) ok = setErr(inp, `Max ${maxMB}MB.`);
                };

                // common
                need($("#file-cert-terms"), "Upload file.", "pdf,doc,docx", 10);
                need($("#file-cert-sticker"), "Upload image.", "jpg,jpeg,png", 5);
                need($("#file-memo"), "Upload file.", "pdf,doc,docx", 15);

                // geo (gps if jpeg)
                const geo = $("#file-geo");
                if (geo?.files?.[0]) {
                    const f = geo.files[0];
                    clrErr(geo);
                    if (!okType(f, "jpg,jpeg,png")) ok = setErr(geo, "JPG/PNG only.");
                    else if (f.type === "image/jpeg") {
                        try {
                            const hasGps = await jpegHasGPS(f);
                            if (!hasGps) ok = setErr(geo, "Need GPS EXIF.");
                        } catch {
                            ok = setErr(geo, "EXIF error.");
                        }
                    }
                } else {
                    ok = setErr(geo, "Upload image.");
                }

                if (type === TYPE.NEW) {
                    need($("#file-sell-permit"), "Upload file.", "pdf,doc,docx", 10);
                    need($("#file-business-permit"), "Upload file.", "pdf,doc,docx", 10);
                } else if (type === TYPE.REN) {
                    need($("#file-old-reg"), "Upload file.", "pdf,doc,docx,jpg,jpeg,png", 10);
                }
                return ok;

            }

            // Signature (canvas not blank)
            function vSig() {
                const c = $("#signature-pad");
                if (!c) return true;
                const ctx = c.getContext("2d");
                try {
                    const {
                        data
                    } = ctx.getImageData(0, 0, c.width, c.height);
                    let ink = false;
                    for (let i = 0; i < data.length; i += 4) {
                        if (!(data[i] === 255 && data[i + 1] === 255 && data[i + 2] === 255) && data[i + 3] !== 0) {
                            ink = true;
                            break;
                        }
                    }
                    const anchor = c.closest(".signature-pad-container") || c;
                    clrErr(anchor);
                    if (!ink) return setErr(anchor, "Please sign."), false;
                    return true;
                } catch {
                    return true;
                }
            }

            // Validate all visible fields
            async function validateAll() {
                qa(".fv-error").forEach((n) => n.remove());
                const t = curType();
                const fld = (n, r) => $(t === TYPE.REN ? r : n);

                let ok = true;
                ok &= nameReq(fld("first-name", "first-name-r"), "First name");
                ok &= nameOpt(fld("middle-name", "middle-name-r"));
                ok &= nameReq(fld("last-name", "last-name-r"), "Last name");
                ok &= vStreet(fld("street", "street-r"));
                ok &= vBarangay(fld("barangay", "barangay-r"));
                ok &= (t === TYPE.NEW ? vMunicipNew($("#municipality")) : vMunicipRen($("#municipality-r")));
                ok &= vProv(fld("province", "province-r"));
                ok &= vPhone(fld("contact-number", "contact-number-r"));

                ok &= vPurpose(fld("purpose", "purpose-r"));
                ok &= vBrand(fld("brand", "brand-r"));
                ok &= vModel(fld("model", "model-r"));
                ok &= vAcq(fld("acquisition-date", "acquisition-date-r"));
                ok &= vSerial(fld("serial-number", "serial-number-r"));
                ok &= vHPopt(fld("horsepower", "horsepower-r"));
                ok &= vBar(fld("guide-bar-length", "guide-bar-length-r"));

                if (t === TYPE.REN) {
                    ok &= vPermitNo($("#permit-number-r"));
                    ok &= vIssExp($("#issuance-date-r"), $("#expiry-date-r"));
                }

                ok &= vSig();
                ok &= await vFiles(t);

                return !!ok;
            }

            // Live validation bindings
            function bindLive() {
                const rules = [
                    ["first-name", (e) => nameReq(e, "First name")],
                    ["middle-name", nameOpt],
                    ["last-name", (e) => nameReq(e, "Last name")],
                    ["street", vStreet],
                    ["barangay", vBarangay],
                    ["municipality", vMunicipNew],
                    ["province", vProv],
                    ["contact-number", vPhone],
                    ["purpose", vPurpose],
                    ["brand", vBrand],
                    ["model", vModel],
                    ["acquisition-date", vAcq],
                    ["serial-number", vSerial],
                    ["horsepower", vHPopt],
                    ["guide-bar-length", vBar],
                    // Renewal
                    ["first-name-r", (e) => nameReq(e, "First name")],
                    ["middle-name-r", nameOpt],
                    ["last-name-r", (e) => nameReq(e, "Last name")],
                    ["street-r", vStreet],
                    ["barangay-r", (e) => (clrErr(e), !blank(e.value) || setErr(e, "Required."))],
                    ["municipality-r", vMunicipRen],
                    ["province-r", vProv],
                    ["contact-number-r", vPhone],
                    ["permit-number-r", vPermitNo],
                    ["issuance-date-r", () => true],
                    ["expiry-date-r", () => true],
                    ["purpose-r", vPurpose],
                    ["brand-r", vBrand],
                    ["model-r", vModel],
                    ["acquisition-date-r", vAcq],
                    ["serial-number-r", vSerial],
                    ["horsepower-r", vHPopt],
                    ["guide-bar-length-r", vBar],
                ];
                for (const [id, fn] of rules) {
                    const el = $(id);
                    if (!el) continue;
                    const evt = el.tagName === "SELECT" || el.type === "date" ? "change" : "input";
                    el.addEventListener(evt, () => fn(el));
                    el.addEventListener("blur", () => fn(el));
                }

                // issuance/expiry pair
                const iss = $("#issuance-date-r"),
                    exp = $("#expiry-date-r");
                if (iss && exp) {
                    const sync = () => vIssExp(iss, exp);
                    iss.addEventListener("change", sync);
                    exp.addEventListener("change", sync);
                }

                // file quick checks
                qa("input[type='file']").forEach((inp) => {
                    inp.addEventListener("change", async () => {
                        const id = inp.id;
                        clrErr(inp);
                        const f = inp.files?.[0];
                        if (!f) {
                            const msg = id === "file-cert-sticker" || id === "file-geo" ? "Upload image." : "Upload file.";
                            setErr(inp, msg);
                            return;
                        }
                        const over = (sz, mb) => sz > mb * 1024 * 1024 ? setErr(inp, `Max ${mb}MB.`) : true;
                        if (id === "file-cert-terms" || id === "file-memo") {
                            if (!okType(f, "pdf,doc,docx")) setErr(inp, "PDF/DOC/DOCX only.");
                            else over(f.size, id === "file-memo" ? 15 : 10);
                        } else if (id === "file-cert-sticker") {
                            if (!okType(f, "jpg,jpeg,png")) setErr(inp, "JPG/PNG only.");
                            else over(f.size, 5);
                        } else if (id === "file-geo") {
                            if (!okType(f, "jpg,jpeg,png")) setErr(inp, "JPG/PNG only.");
                            else if (f.type === "image/jpeg") {
                                try {
                                    if (!(await jpegHasGPS(f))) setErr(inp, "Need GPS EXIF.");
                                } catch {
                                    setErr(inp, "EXIF error.");
                                }
                            }
                        } else if (id === "file-sell-permit" || id === "file-business-permit") {
                            if (!okType(f, "pdf,doc,docx")) setErr(inp, "PDF/DOC/DOCX only.");
                            else over(f.size, 10);
                        } else if (id === "file-old-reg") {
                            if (!okType(f, "pdf,doc,docx,jpg,jpeg,png")) setErr(inp, "PDF/DOC/DOCX/JPG/PNG.");
                            else over(f.size, 10);
                        }
                    });
                });

                // clear when switching type
                qa(".permit-type-btn").forEach((b) => b.addEventListener("click", () => setTimeout(() => qa(".fv-error").forEach((n) => n.remove()), 0)));
            }

            // Intercept submits (precheck + confirm buttons)
            function guardSubmits() {
                const guard = async (e) => {
                    const ok = await validateAll();
                    if (!ok) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        firstErrFocus();
                    }
                };
                $("#submitApplication")?.addEventListener("click", guard, true);
                $("#btnOkConfirm")?.addEventListener("click", guard, true);
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
