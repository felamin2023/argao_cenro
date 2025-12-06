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
            document.querySelectorAll('.as-notif-time[data-ts], .notification-time[data-ts]').forEach(el => {
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

    // prepared statement used to check whether an approval is for seedlings
    $stApprovalType = $pdo->prepare("SELECT seedl_req_id FROM public.approval WHERE approval_id = :aid LIMIT 1");

   
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
        error_log('[LUMBER-CLIENTS] ' . $e->getMessage());
        $clientRows = [];
    }

    $crOptions = [];
    $crRecords = [];
    try {
        $stmt = $pdo->prepare("
        WITH user_clients AS (
            SELECT client_id
            FROM public.client
            WHERE user_id = :uid
        )
        SELECT
            ad.no                AS cr_no,
            ad.date_issued       AS doc_date_issued,
            ad.expiry_date       AS doc_expiry_date,
            a.approval_id,
            a.client_id,
            c.first_name         AS client_first,
            c.middle_name        AS client_middle,
            c.last_name          AS client_last,
            af.company_name,
            af.present_address,
            af.location,
            af.proposed_place_of_operation,
            af.expected_annual_volume,
            af.estimated_annual_worth,
            af.total_number_of_employees,
            af.total_number_of_dependents,
            af.intended_market,
            af.my_experience_as_alumber_dealer,
            af.declaration_name,
            af.suppliers_json,
            af.permit_number,
            af.expiry_date       AS cr_expiry_date,
            af.cr_license_no,
            af.buying_from_other_sources,
            af.additional_information,
            af.applicant_age,
            af.is_government_employee,
            req.lumber_csw_document,
            req.geo_photos,
            req.lumber_supply_contract,
            req.lumber_mayors_permit,
            req.lumber_registration_certificate,
            req.lumber_or_copy,
            req.lumber_op_copy,
            req.lumber_business_plan,
            req.lumber_tax_return,
            req.lumber_monthly_reports
        FROM public.approved_docs ad
        JOIN public.approval a   ON a.approval_id = ad.approval_id
        JOIN public.client   c   ON c.client_id   = a.client_id
        LEFT JOIN public.application_form af ON af.application_id = a.application_id
        LEFT JOIN public.requirements req    ON req.requirement_id = a.requirement_id
        WHERE a.request_type ILIKE 'lumber'
          AND a.client_id IN (SELECT client_id FROM user_clients)
          AND NULLIF(btrim(ad.no), '') IS NOT NULL
        ORDER BY COALESCE(ad.date_issued, a.submitted_at) DESC NULLS LAST
        LIMIT 200
    ");
        $stmt->execute([':uid' => $_SESSION['user_id']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as $row) {
            $crNo = trim((string)($row['cr_no'] ?? ''));
            if ($crNo === '') continue;

            $issuedLabel = '';
            if (!empty($row['doc_date_issued'])) {
                try {
                    $issuedLabel = (new DateTime((string)$row['doc_date_issued']))->format('M j, Y');
                } catch (Throwable $e) {
                    $issuedLabel = '';
                }
            }
            $crOptions[] = [
                'no'    => $crNo,
                'label' => $issuedLabel ? ($crNo . ' — ' . $issuedLabel) : $crNo
            ];

            $suppliers = [];
            if (!empty($row['suppliers_json'])) {
                $decoded = json_decode($row['suppliers_json'], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $entry) {
                        if (!is_array($entry)) continue;
                        $suppliers[] = [
                            'name'   => trim((string)($entry['name'] ?? '')),
                            'volume' => trim((string)($entry['volume'] ?? '')),
                        ];
                    }
                }
            }

            $sawmillPermit = null;
            if (!empty($row['additional_information'])) {
                $chunks = preg_split('/\s*;\s*/', (string)$row['additional_information']);
                foreach ($chunks as $chunk) {
                    if (!$chunk) continue;
                    [$k, $v] = array_map('trim', explode('=', $chunk, 2) + ['', '']);
                    if ($k && strtolower($k) === 'sawmill_permit_no') {
                        $sawmillPermit = $v;
                    }
                }
            }

            $files = array_filter([
                'lumber_csw_document'        => $row['lumber_csw_document'] ?? null,
                'geo_photos'                 => $row['geo_photos'] ?? null,
                'lumber_supply_contract'     => $row['lumber_supply_contract'] ?? null,
                'lumber_mayors_permit'       => $row['lumber_mayors_permit'] ?? null,
                'lumber_registration_certificate' => $row['lumber_registration_certificate'] ?? null,
                'lumber_or_copy'             => $row['lumber_or_copy'] ?? null,
                'lumber_op_copy'             => $row['lumber_op_copy'] ?? null,
                'lumber_business_plan'       => $row['lumber_business_plan'] ?? null,
                'lumber_tax_return'          => $row['lumber_tax_return'] ?? null,
                'lumber_monthly_reports'     => $row['lumber_monthly_reports'] ?? null,
            ], static fn($v) => !empty($v));

            $crRecords[] = [
                'cr_no'                   => $crNo,
                'approval_id'             => $row['approval_id'],
                'client_id'               => $row['client_id'],
                'date_issued'             => $row['doc_date_issued'],
                'doc_expiry_date'         => $row['doc_expiry_date'],
                'cr_expiry_date'          => $row['cr_expiry_date'],
                'company_name'            => $row['company_name'],
                'present_address'         => $row['present_address'],
                'location'                => $row['location'],
                'proposed_place_of_operation' => $row['proposed_place_of_operation'],
                'expected_annual_volume'  => $row['expected_annual_volume'],
                'estimated_annual_worth'  => $row['estimated_annual_worth'],
                'total_number_of_employees'   => $row['total_number_of_employees'],
                'total_number_of_dependents'  => $row['total_number_of_dependents'],
                'intended_market'         => $row['intended_market'],
                'experience'              => $row['my_experience_as_alumber_dealer'],
                'declaration_name'        => $row['declaration_name'],
                'applicant_age'           => $row['applicant_age'],
                'is_government_employee'  => $row['is_government_employee'],
                'permit_number'           => $row['permit_number'],
                'cr_license_no'           => $row['cr_license_no'],
                'buying_from_other_sources' => $row['buying_from_other_sources'],
                'sawmill_permit_no'       => $sawmillPermit,
                'suppliers'               => $suppliers,
                'client' => [
                    'first'  => $row['client_first'],
                    'middle' => $row['client_middle'],
                    'last'   => $row['client_last'],
                ],
                'files' => $files,
            ];
        }
    } catch (Throwable $e) {
        error_log('[LUMBER-CR-NOS] ' . $e->getMessage());
        $crOptions = [];
        $crRecords = [];
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Lumber Dealer Permit Application</title>
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
                --as-primary: #2b6625;
                --as-primary-dark: #1e4a1a;
                --as-white: #fff;
                --as-light-gray: #f5f5f5;
                --as-radius: 8px;
                --as-shadow: 0 4px 12px rgba(0, 0, 0, .1);
                --as-trans: all .2s ease;
                --loader-size: 16px;
            }

            @keyframes spin {
                from {
                    transform: rotate(0deg);
                }

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
                margin-right: 10px;
                flex-shrink: 0;
                /* Add this to prevent shrinking */
                line-height: 25px;
                /* Add this to ensure vertical centering */
                text-align: center;
                /* Add this for horizontal centering */
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

            .file-type-error {
                font-size: 0.85rem;
                color: #dc3545;
                margin-top: 6px;
                display: none;
            }

            .file-type-error.show {
                display: block;
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
                padding: 3px 10px;
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

            .renewal-cr-files {
                margin-top: 6px;
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

            .readonly-input {
                background-color: #f4f4f4 !important;
                cursor: not-allowed;
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

            .form-section h2,
            .form-section h3 {
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
                padding: 12px 15px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 15px;
                transition: border-color 0.3s;
                min-height: 48px;
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

            .suppliers-table {
                width: 100%;
                border-collapse: collapse;
                margin: 15px 0;
            }

            .suppliers-table th,
            .suppliers-table td {
                border: 1px solid #ddd;
                padding: 12px;
                text-align: left;
            }

            .suppliers-table th {
                background-color: #f2f2f2;
            }

            .suppliers-table input {
                width: 100%;
                padding: 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 15px;
                min-height: 48px;
            }

            .supplier-name {
                width: 70%;
            }

            .supplier-volume {
                width: 25%;
            }

            .add-row {
                background-color: #2b6625;
                color: white;
                border: none;
                padding: 10px 15px;
                border-radius: 4px;
                cursor: pointer;
                margin-bottom: 15px;
                font-size: 14px;
            }

            .remove-btn {
                background-color: #ff4757;
                color: white;
                border: none;
                padding: 8px 12px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 13px;
            }

            .govt-employee-container {
                display: flex;
                gap: 20px;
                align-items: center;
            }

            .govt-option {
                display: flex;
                align-items: center;
                gap: 5px;
            }

            .govt-option input[type="radio"] {
                width: auto;
                min-height: auto;
            }

            .govt-option label {
                margin-bottom: 0;
                font-weight: normal;
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

                .suppliers-table {
                    display: block;
                    overflow-x: auto;
                }

                .govt-employee-container {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 10px;
                }
            }

            /* Loading indicator */
            .loading {
                display: none;
                text-align: center;
                margin-top: 20px;
                color: #2b6625;
                font-weight: bold;
            }

            .loading i {
                margin-right: 10px;
            }

            /* Print-specific styles */
            @media print {

                .download-btn,
                .add-row,
                .signature-actions,
                .signature-pad-container,
                .remove-btn {
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
        <header>
            <div class="logo">
                <a href="user_home.php">
                    <img src="seal.png" alt="Site Logo">
                </a>
            </div>

            <!-- Mobile menu toggle -->
            <button type="button" class="mobile-toggle" aria-label="Toggle menu">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Navigation on the right -->
            <div class="nav-container">
                <!-- Dashboard Dropdown -->
                <div class="nav-item dropdown">
                    <div class="nav-icon active" data-dropdown-toggle>
                        <i class="fas fa-bars"></i>
                    </div>
                    <div class="dropdown-menu center">
                        <a href="user_reportaccident.php" class="dropdown-item">
                            <i class="fas fa-exclamation-triangle"></i>
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
                        <a href="useraddlumber.php" class="dropdown-item active-page">
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
                            document.querySelectorAll('.as-notif-time[data-ts], .notification-time[data-ts]').forEach(el => {
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


                <!-- Profile Dropdown -->
                <div class="nav-item dropdown">
                    <div class="nav-icon" data-dropdown-toggle>
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
                    <h2>Lumber Dealer Permit - Requirements</h2>
                </div>

                <div class="form-body">
                    <!-- Permit Type Selector -->
                    <div class="permit-type-selector" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
                        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
                            <button type="button" class="permit-type-btn active" data-type="new">New Permit</button>
                            <button type="button" class="permit-type-btn" data-type="renewal">Renewal</button>
                        </div>
                        <div class="client-mode-toggle" id="clientModeToggle" style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
                            <button type="button" id="btnExisting" class="btn btn-outline">
                                <i class="fas fa-user-check"></i>&nbsp;Existing client
                            </button>
                            <button type="button" id="btnNew" class="btn btn-outline" style="display:none;">
                                <i class="fas fa-user-plus"></i>&nbsp;New client
                            </button>
                        </div>
                        <div id="renewalCrPicker" class="renewal-cr-picker" style="display:flex; text-align: center; gap:6px;min-width:220px; border: 1px dashed #c8d3c5; border-radius: 6px; background: #f8fbf7; padding:  5px;">
                            <label for="renewalCrSelect" style="font-weight:600;margin:0;">C.R. No.</label>
                            <?php if ($crOptions): ?>
                                <select id="renewalCrSelect" style="height:40px;width:220px;border:1px solid #ccc;border-radius:4px;padding:0 10px;">
                                    <option value="">-- Select C.R. No. --</option>
                                    <?php foreach ($crOptions as $opt): ?>
                                        <option value="<?= htmlspecialchars((string)($opt['no'] ?? ''), ENT_QUOTES) ?>">
                                            <?= htmlspecialchars((string)($opt['label'] ?? ($opt['no'] ?? '')), ENT_QUOTES) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <span style="font-size:.9rem;color:#6c757d;">No released C.R. numbers yet.</span>
                            <?php endif; ?>

                        </div>
                    </div><small id="clientModeHint" style="display:block;margin-bottom:15px;opacity:.8;">Choose <b>Existing client</b> if the record already exists; otherwise stay on <b>New client</b>.</small>

                    <!-- ===================== NEW LUMBER DEALER PERMIT APPLICATION (UI only) ===================== -->
                    <div class="form-section" id="lumber-application-section" style="margin-top:16px;">
                        <!-- Applicant Information -->
                        <div class="form-subsection">
                            <h2>Applicant Information</h2>
                            <input type="hidden" id="clientMode" value="new">

                            <div id="existingClientRow" class="form-group" style="display:none;margin-bottom:16px;">
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
                                            if (!empty($c['sitio_street'])) {
                                                $addrParts[] = $c['sitio_street'];
                                            }
                                            if (!empty($c['barangay'])) {
                                                $addrParts[] = 'Brgy. ' . $c['barangay'];
                                            }
                                            if (!empty($c['municipality']) || !empty($c['city'])) {
                                                $addrParts[] = $c['municipality'] ?: $c['city'];
                                            }
                                            $addressValue = $addrParts ? implode(', ', $addrParts) : '';
                                            $label = $full . ($addressValue ? ' - ' . $addressValue : '');
                                            $attrs = sprintf(
                                                ' value="%s" data-first="%s" data-middle="%s" data-last="%s" data-address="%s"',
                                                htmlspecialchars((string)($c['client_id'] ?? ''), ENT_QUOTES),
                                                htmlspecialchars((string)($c['first_name'] ?? ''), ENT_QUOTES),
                                                htmlspecialchars((string)($c['middle_name'] ?? ''), ENT_QUOTES),
                                                htmlspecialchars((string)($c['last_name'] ?? ''), ENT_QUOTES),
                                                htmlspecialchars($addressValue, ENT_QUOTES)
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
                                        <input type="text" id="first-name" placeholder="First Name" required>
                                    </div>
                                    <div class="name-field">
                                        <input type="text" id="middle-name" placeholder="Middle Name">
                                    </div>
                                    <div class="name-field">
                                        <input type="text" id="last-name" placeholder="Last Name" required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="applicant-age" class="required">Age:</label>
                                    <input type="number" id="applicant-age" min="18" placeholder="18+">
                                </div>

                                <div class="form-group">
                                    <label class="required">Government Employee:</label>
                                    <div class="govt-employee-container" style="display:flex; gap:16px;">
                                        <div class="govt-option">
                                            <input type="radio" id="govt-employee-no" name="govt-employee" value="no" checked>
                                            <label for="govt-employee-no">No</label>
                                        </div>
                                        <div class="govt-option">
                                            <input type="radio" id="govt-employee-yes" name="govt-employee" value="yes">
                                            <label for="govt-employee-yes">Yes</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="business-name" class="required">Business Name:</label>
                                <input type="text" id="business-name" placeholder="Registered business name">
                            </div>


                            <div class="form-group">
                                <label for="business-address" class="required">Business Address:</label>
                                <input type="text" id="business-address" placeholder="Full business address">
                            </div>
                        </div>

                        <!-- Business Information -->
                        <div class="form-subsection" style="margin-top:18px;">
                            <h3>Business Information</h3>

                            <div class="form-group">
                                <label for="operation-place" class="required">Proposed Place of Operation:</label>
                                <input type="text" id="operation-place" placeholder="Full address of operation place">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="annual-volume" class="required">Expected Gross Annual Volume of Business:</label>
                                    <input type="text" id="annual-volume" placeholder="e.g., 1,000 bd ft">
                                </div>
                                <div class="form-group">
                                    <label for="annual-worth" class="required">Worth:</label>
                                    <input type="text" id="annual-worth" placeholder="e.g., 500,000">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="employees-count" class="required">Total Number of Employees:</label>
                                    <input type="number" id="employees-count" min="0" placeholder="0">
                                </div>
                                <div class="form-group">
                                    <label for="dependents-count" class="required">Total Number of Dependents:</label>
                                    <input type="number" id="dependents-count" min="0" placeholder="0">
                                </div>
                            </div>
                        </div>

                        <!-- Suppliers Information -->
                        <div class="form-subsection" style="margin-top:18px;">
                            <h3>Suppliers Information</h3>

                            <table class="suppliers-table" id="suppliers-table">
                                <thead>
                                    <tr>
                                        <th width="70%">Suppliers Name/Company</th>
                                        <th width="25%">Volume</th>
                                        <th width="5%">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input type="text" class="supplier-name" placeholder="Supplier name"></td>
                                        <td><input type="text" class="supplier-volume" placeholder="Volume"></td>
                                        <td><button type="button" class="remove-btn">Remove</button></td>
                                    </tr>
                                </tbody>
                            </table>

                            <button type="button" class="add-row" id="add-supplier-row">Add Supplier</button>
                        </div>

                        <!-- Market & Experience -->
                        <div class="form-subsection" style="margin-top:18px;">
                            <h4>Market Information</h4>

                            <div class="form-group">
                                <label for="intended-market" class="required">Intended Market (Barangays and Municipalities to be served):</label>
                                <textarea id="intended-market" rows="3" placeholder="List barangays and municipalities"></textarea>
                            </div>

                            <div class="form-group">
                                <label for="experience" class="required">Experience as a Lumber Dealer:</label>
                                <textarea id="experience" rows="3" placeholder="Describe your experience in the lumber business"></textarea>
                            </div>
                        </div>

                        <!-- Declaration & Signature -->
                        <div class="form-subsection" style="margin-top:18px;">
                            <h4>Declaration</h4>

                            <div class="declaration">
                                <p>I will fully comply with Republic Act No. 123G and the rules and regulations of the Forest Management Bureau.</p>

                                <p>I understand that false statements or omissions may result in:</p>
                                <ul style="margin-left: 20px; margin-bottom: 15px;">
                                    <li>Disapproval of this application</li>
                                    <li>Cancellation of registration</li>
                                    <li>Forfeiture of bond</li>
                                    <li>Criminal liability</li>
                                </ul>

                                <p>
                                    I, <input type="text" id="declaration-name" class="declaration-input" placeholder="Enter your full name">,
                                    after being sworn to upon my oath, depose and say that I have read the foregoing application and that every statement therein is true and correct to the best of my knowledge and belief.
                                </p>

                                <div class="signature-date">
                                    <div class="signature-box">
                                        <label>Signature of Applicant:</label>
                                        <div class="signature-pad-container">
                                            <!-- Width/height set in style so CSS size = drawing size (prevents ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œdead zoneÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â) -->
                                            <canvas id="signature-pad" style="width:100%;height:220px;display:block;"></canvas>
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
                            </div>
                        </div>
                    </div>
                    <!-- ===================== /NEW LUMBER DEALER PERMIT APPLICATION ===================== -->

                    <!-- ===================== RENEWAL LUMBER DEALER PERMIT APPLICATION ===================== -->
                    <div class="form-section" id="renewal-application-section" style="margin-top:16px; display:none;">
                        <!-- Applicant Information -->
                        <div class="form-subsection">
                            <h2>Applicant Information (Renewal)</h2>

                            <!-- split into First/Middle/Last -->
                            <div class="name-fields">
                                <div class="name-field">
                                    <input type="text" id="first-name-ren" placeholder="First Name" required>
                                </div>
                                <div class="name-field">
                                    <input type="text" id="middle-name-ren" placeholder="Middle Name">
                                </div>
                                <div class="name-field">
                                    <input type="text" id="last-name-ren" placeholder="Last Name" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="applicant-age-ren" class="required">Age:</label>
                                <input type="number" id="applicant-age-ren" min="18">
                            </div>
                            <div class="form-group">
                                <label for="business-name-ren" class="required">Business Name:</label>
                                <input type="text" id="business-name-ren" placeholder="Registered business name">
                            </div>


                            <div class="form-group">
                                <label for="business-address-ren" class="required">Business Address:</label>
                                <input type="text" id="business-address-ren" placeholder="Full business address">
                            </div>

                            <div class="form-group">
                                <label class="required">Government Employee:</label>
                                <div class="govt-employee-container" style="display:flex; gap:16px;">
                                    <div class="govt-option">
                                        <input type="radio" id="govt-employee-ren-no" name="govt-employee-ren" value="no" checked>
                                        <label for="govt-employee-ren-no">No</label>
                                    </div>
                                    <div class="govt-option">
                                        <input type="radio" id="govt-employee-ren-yes" name="govt-employee-ren" value="yes">
                                        <label for="govt-employee-ren-yes">Yes</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Business Information -->
                        <div class="form-subsection" style="margin-top:18px;">
                            <h3>Business Information (Renewal)</h3>

                            <div class="form-group">
                                <label for="operation-place-ren" class="required">Place of Operation:</label>
                                <input type="text" id="operation-place-ren" placeholder="Full address of operation place">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="annual-volume-ren" class="required">Expected Gross Annual Volume of Business:</label>
                                    <input type="text" id="annual-volume-ren" placeholder="e.g., 1000 board feet">
                                </div>

                                <div class="form-group">
                                    <label for="annual-worth-ren" class="required">Value:</label>
                                    <input type="text" id="annual-worth-ren" placeholder="e.g., 500,000">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="employees-count-ren" class="required">Total Number of Employees:</label>
                                    <input type="number" id="employees-count-ren" min="0">
                                </div>

                                <div class="form-group">
                                    <label for="dependents-count-ren" class="required">Total Number of Dependents:</label>
                                    <input type="number" id="dependents-count-ren" min="0">
                                </div>
                            </div>
                        </div>

                        <!-- Suppliers Information -->
                        <div class="form-subsection" style="margin-top:18px;">
                            <h3>Suppliers Information (Renewal)</h3>

                            <table class="suppliers-table" id="suppliers-table-ren">
                                <thead>
                                    <tr>
                                        <th width="70%">SUPPLIERS NAME/COMPANY</th>
                                        <th width="25%">VOLUME</th>
                                        <th width="5%">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input type="text" class="supplier-name-ren" placeholder="Supplier name"></td>
                                        <td><input type="text" class="supplier-volume-ren" placeholder="Volume"></td>
                                        <td><button type="button" class="remove-btn-ren">Remove</button></td>
                                    </tr>
                                </tbody>
                            </table>

                            <button type="button" class="add-row" id="add-supplier-row-ren">Add Supplier</button>
                        </div>

                        <!-- Business Details -->
                        <div class="form-subsection" style="margin-top:18px;">
                            <h3>Business Details (Renewal)</h3>

                            <div class="form-group">
                                <label for="intended-market-ren" class="required">Selling Products To:</label>
                                <textarea id="intended-market-ren" rows="3" placeholder="List adjacent barangays and municipalities"></textarea>
                            </div>

                            <div class="form-group">
                                <label for="experience-ren" class="required">Experience as a Lumber Dealer:</label>
                                <textarea id="experience-ren" rows="3" placeholder="Describe your experience in the lumber business"></textarea>
                            </div>

                            <div class="form-group">
                                <label for="prev-certificate-ren">Previous Certificate of Registration No.:</label>
                                <input type="text" id="prev-certificate-ren" placeholder="Certificate number">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="issued-date-ren">Issued On:</label>
                                    <input type="date" id="issued-date-ren">
                                </div>

                                <div class="form-group">
                                    <label for="expiry-date-ren">Expires On:</label>
                                    <input type="date" id="expiry-date-ren">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="cr-license-ren">C.R. License No.:</label>
                                    <input type="text" id="cr-license-ren" placeholder="License number">
                                </div>

                                <div class="form-group">
                                    <label for="sawmill-permit-ren">Sawmill Permit No.:</label>
                                    <input type="text" id="sawmill-permit-ren" placeholder="Permit number">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="required">Buying logs or lumber from other sources:</label>
                                <div class="govt-employee-container" style="display:flex; gap:16px;">
                                    <div class="govt-option">
                                        <input type="radio" id="other-sources-ren-no" name="other-sources-ren" value="no" checked>
                                        <label for="other-sources-ren-no">No</label>
                                    </div>
                                    <div class="govt-option">
                                        <input type="radio" id="other-sources-ren-yes" name="other-sources-ren" value="yes">
                                        <label for="other-sources-ren-yes">Yes</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Declaration & Signature (RENEWAL) -->
                        <div class="form-subsection" style="margin-top:18px;">
                            <h4>Declaration (Renewal)</h4>

                            <div class="declaration">
                                <p>I will faithfully comply with all provisions of Rep. Act No. 1239 as well as rules and regulations of the Forest Management Bureau.</p>
                                <p>I fully understand that false statements or material omissions may cause the cancellation of the registration and the forfeiture of the bond, without prejudice to criminal action that the government may take against me.</p>
                                <p>I FINALLY UNDERSTAND THAT THE MERE FILING OF THE APPLICATION AND/OR PAYMENT OF THE NECESSARY FEES DOES NOT ENTITLE ME TO START OPERATION WHICH MUST COMMENCE ONLY AFTER THE ISSUANCE OF THE CERTIFICATE OF REGISTRATION.</p>

                                <p style="text-align: center; font-weight: bold;">REPUBLIC OF THE PHILIPPINES</p>

                                <p>
                                    I, <input type="text" id="declaration-name-ren" class="declaration-input" placeholder="Enter your full name">,
                                    the applicant after having been sworn to upon my oath, depose and say: That I have thoroughly read the foregoing application and that every statement therein is true to the best of my knowledge and belief.
                                </p>

                                <div class="signature-date">
                                    <div class="signature-box">
                                        <label>Signature of Applicant:</label>
                                        <div class="signature-pad-container">
                                            <!-- Same fix: match CSS size & drawing size -->
                                            <canvas id="signature-pad-ren" style="width:100%;height:220px;display:block;"></canvas>
                                        </div>
                                        <div class="signature-actions">
                                            <button type="button" class="signature-btn clear-signature" id="clear-signature-ren">Clear</button>
                                            <!-- <button type="button" class="signature-btn save-signature" id="save-signature-ren">Save Signature</button> -->
                                        </div>
                                        <div class="signature-preview">
                                            <img id="signature-image-ren" class="hidden" alt="Signature">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- ===================== /RENEWAL LUMBER DEALER PERMIT APPLICATION ===================== -->

                    <!-- Requirements List -->
                    <div class="requirements-list">
                        <!-- 1 -->
                        <div class="requirement-item">
                            <div class="requirement-header">
                                <div class="requirement-title">
                                    <span class="requirement-number new-number">1</span>
                                    <span class="requirement-number renewal-number" style="display:none">1</span>
                                    Complete Staff Work (CSW) by the inspecting officer - from inspecting officer for signature of RPS chief
                                </div>
                            </div>
                            <div class="file-upload">
                                <div class="file-input-container">
                                    <label for="file-1" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="file-1" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                    <span class="file-type-error"></span>
                                </div>
                            </div>
                        </div>

                        <!-- 2 -->
                        <div class="requirement-item">
                            <div class="requirement-header">
                                <div class="requirement-title">
                                    <span class="requirement-number new-number">2</span>
                                    <span class="requirement-number renewal-number" style="display:none">2</span>
                                    Geo-tagged pictures of the business establishment ( from inspecting officer for signature of RPS chief)
                                </div>
                            </div>
                            <div class="file-upload">
                                <div class="file-input-container">
                                    <label for="file-2" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="file-2" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                    <span class="file-type-error"></span>
                                </div>
                            </div>
                        </div>

                        <!-- 3 -->
                        <div class="requirement-item">
                            <div class="requirement-header">
                                <div class="requirement-title">
                                    <span class="requirement-number new-number">3</span>
                                    <span class="requirement-number renewal-number" style="display:none">3</span>
                                    Log/Lumber Supply Contract (approved by RED)
                                </div>
                            </div>
                            <div class="file-upload">
                                <div class="file-input-container">
                                    <label for="file-4" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="file-4" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                    <span class="file-type-error"></span>
                                </div>
                            </div>
                        </div>

                        <!-- 4 (NEW ONLY) -->
                        <div class="requirement-item" id="requirement-5">
                            <div class="requirement-header">
                                <div class="requirement-title">
                                    <span class="requirement-number new-number">4</span>
                                    Business Management Plan
                                </div>
                            </div>
                            <div class="file-upload">
                                <div class="file-input-container">
                                    <label for="file-5" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="file-5" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                            </div>
                        </div>

                        <!-- 4 (NEW ONLY: MayorÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¾ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢s Permit) -->
                        <div class="requirement-item" id="mayors-permit" style="display:none">
                            <div class="requirement-header">
                                <div class="requirement-title">
                                    <span class="requirement-number new-number">5</span>
                                    Mayor's Permit
                                </div>
                            </div>
                            <div class="file-upload">
                                <div class="file-input-container">
                                    <label for="file-6" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="file-6" name="lumber_mayors_permit"
                                        class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                    <span class="file-type-error"></span>
                                </div>

                            </div>
                        </div>


                        <!-- 5 -->
                        <div class="requirement-item">
                            <div class="requirement-header">
                                <div class="requirement-title">
                                    <span class="requirement-number new-number">6</span>
                                    <span class="requirement-number renewal-number" style="display:none">5</span>
                                    Certificate of Registration by DTI/SEC
                                </div>
                            </div>
                            <div class="file-upload">
                                <div class="file-input-container">
                                    <label for="file-7" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="file-7" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                    <span class="file-type-error"></span>
                                </div>
                            </div>
                        </div>

                        <!-- 6 (NEW ONLY) -->
                        <div class="requirement-item" id="requirement-8">
                            <div class="requirement-header">
                                <div class="requirement-title">
                                    <span class="requirement-number new-number">7</span>
                                    Latest Annual Income Tax Return
                                </div>
                            </div>
                            <div class="file-upload">
                                <div class="file-input-container">
                                    <label for="file-8" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="file-8" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                    <span class="file-type-error"></span>
                                </div>
                            </div>
                        </div>

                        <!-- 6 (RENEWAL ONLY) -->
                        <div class="requirement-item" id="requirement-9" style="display:none">
                            <div class="requirement-header">
                                <div class="requirement-title">
                                    <span class="requirement-number renewal-number">6</span>
                                    Monthly and Quarterly Reports from the date issued to date (for renewal only)
                                </div>
                            </div>
                            <div class="file-upload">
                                <div class="file-input-container">
                                    <label for="file-9" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="file-9" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                    <span class="file-type-error"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /Requirements List -->

                </div>

                <div class="form-footer">
                    <button type="button" class="btn btn-primary" id="submitApplication">
                        <i class="fas fa-paper-plane"></i> Submit Application
                    </button>
                </div>
            </div>
        </div>

        <!-- Global Loading Overlay (unified) -->
        <div id="loadingIndicator" style="display:none;position:fixed;inset:0;align-items:center;justify-content:center;background:rgba(0,0,0,.25);z-index:9998">
            <div class="card" style="background:#fff;padding:18px 22px;border-radius:10px;display:flex;gap:10px;align-items:center;">
                <span class="loader" style="width:var(--loader-size);height:var(--loader-size);border:2px solid #ddd;border-top-color:#2b6625;border-radius:50%;display:inline-block;animation:spin 0.8s linear infinite;"></span>
                <span id="loadingMessage">Working...</span>
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

        <!-- Hidden file inputs for type-specific uploads -->
        <input type="file" id="uploadImg" accept=".jpg,.jpeg,.png" style="display:none;">
        <input type="file" id="uploadDoc" accept=".doc,.docx,.pdf" style="display:none;">


        <script>
            window.__CR_RECORDS__ = <?= json_encode($crRecords ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        </script>
        <script>
            (function() {
                'use strict';

                /* =========================
                   Constants / Endpoints
                ========================== */
                const SAVE_URL = new URL('../backend/users/lumber/save_lumber.php', window.location.href).toString();
                const PRECHECK_URL = new URL('../backend/users/lumber/precheck_lumber.php', window.location.href).toString();

                /* =========================
                   Tiny helpers
                ========================== */
                const $ = (sel, root) => (root || document).querySelector(sel);
                const $all = (sel, root) => Array.from((root || document).querySelectorAll(sel));
                const show = (el, on = true) => {
                    if (el) el.style.display = on ? '' : 'none';
                };
                const v = (id) => (document.getElementById(id)?.value || '').trim();
                const display = (el, val) => {
                    if (el && el.style) el.style.display = val;
                }; // safe show/hide

                const crRecords = Array.isArray(window.__CR_RECORDS__) ? window.__CR_RECORDS__ : [];
                const crRecordByNo = Object.create(null);
                crRecords.forEach((rec) => {
                    if (rec && rec.cr_no) crRecordByNo[rec.cr_no] = rec;
                });
                const remoteFileCache = Object.create(null);
                const remoteFileFetches = Object.create(null);
                const remoteFileFallback = Object.create(null); // used when DataTransfer API is missing
                const fileFieldMap = {
                    lumber_csw_document: 'file-1',
                    geo_photos: 'file-2',
                    lumber_supply_contract: 'file-4',
                    lumber_mayors_permit: 'file-6',
                    lumber_registration_certificate: 'file-7',
                    lumber_or_copy: 'file-10a',
                    lumber_op_copy: 'file-10b',
                    lumber_business_plan: 'file-5',
                    lumber_tax_return: 'file-8',
                    lumber_monthly_reports: 'file-9',
                };
                const crFileLabels = {
                    lumber_csw_document: 'CSW document',
                    geo_photos: 'Geo-tagged photos',
                    lumber_supply_contract: 'Supply contract',
                    lumber_mayors_permit: "Mayor's permit",
                    lumber_registration_certificate: 'DTI/SEC registration',
                    lumber_or_copy: 'O.R. copy',
                    lumber_op_copy: 'O.P. copy',
                    lumber_business_plan: 'Business plan',
                    lumber_tax_return: 'Income tax return',
                    lumber_monthly_reports: 'Monthly/quarterly reports',
                };
                const renewalCrFilesWrap = document.getElementById('renewalCrFiles');
                const renewalCrFilesList = document.getElementById('renewalCrFilesList');

                const setInputValue = (id, value) => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    el.value = value ?? '';
                    el.dispatchEvent(new Event('input', {
                        bubbles: true
                    }));
                };

                const setRadioGroup = (yesId, noId, value) => {
                    const normalized = (value || '').toLowerCase();
                    const yes = document.getElementById(yesId);
                    const no = document.getElementById(noId);
                    if (normalized === 'yes') {
                        yes && (yes.checked = true);
                    } else {
                        no && (no.checked = true);
                    }
                };

                function clearFileInput(id) {
                    const input = document.getElementById(id);
                    if (!input) return;
                    input.value = '';
                    if (input.dataset && input.dataset.loadedUrl) delete input.dataset.loadedUrl;
                    if (remoteFileFallback[id]) delete remoteFileFallback[id];
                    const nameEl = input.parentElement?.querySelector('.file-name');
                    if (nameEl) nameEl.textContent = 'No file chosen';
                }

                function filenameFromUrl(url) {
                    try {
                        const clean = url.split('?')[0];
                        const parts = clean.split('/');
                        const last = parts[parts.length - 1] || 'file';
                        return decodeURIComponent(last);
                    } catch {
                        return 'file';
                    }
                }

                function setInputFileFromRemote(input, file, url) {
                    const nameEl = input.parentElement?.querySelector('.file-name');
                    if (typeof DataTransfer !== 'undefined') {
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        input.files = dt.files;
                    } else {
                        remoteFileFallback[input.id] = file;
                    }
                    if (input.dataset) input.dataset.loadedUrl = url || '';
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
                        remoteFileFetches[key] = fetch(url, {
                                credentials: 'include'
                            })
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
                                console.error('Remote file load failed', err);
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

                function resetCrFilesView(message = 'Select a C.R. number to view uploads.', show = false) {
                    if (!renewalCrFilesList) return;
                    renewalCrFilesList.innerHTML = `<div class="renewal-cr-files__empty">${message}</div>`;
                    if (renewalCrFilesWrap) {
                        renewalCrFilesWrap.style.display = show ? 'flex' : 'none';
                    }
                }

                function renderCrFiles(record) {
                    if (!renewalCrFilesWrap || !renewalCrFilesList) return;
                    const entries = record && record.files && typeof record.files === 'object' ?
                        Object.entries(record.files).filter(([, url]) => !!url) : [];
                    if (!entries.length) {
                        resetCrFilesView('No previously uploaded files for this record.', true);
                        return;
                    }
                    renewalCrFilesList.innerHTML = '';
                    entries.forEach(([key, url]) => {
                        const pill = document.createElement('a');
                        pill.href = url;
                        pill.target = '_blank';
                        pill.rel = 'noopener noreferrer';
                        pill.className = 'renewal-cr-files__pill';
                        const label = crFileLabels[key] || key.replace(/_/g, ' ');
                        pill.innerHTML = `<span>${label}</span><i class="fas fa-external-link-alt"></i>`;
                        renewalCrFilesList.appendChild(pill);
                    });
                    renewalCrFilesWrap.style.display = 'flex';
                }

                resetCrFilesView();

                function syncPermitFiles(record) {
                    const files = record?.files || {};
                    Object.entries(fileFieldMap).forEach(([key, inputId]) => {
                        const input = document.getElementById(inputId);
                        if (!input) return;
                        clearFileInput(inputId);
                        const url = files[key];
                        if (url) attachRemoteFile(inputId, url);
                    });
                }

                function populateSuppliersRen(list) {
                    const body = document.querySelector('#suppliers-table-ren tbody');
                    if (!body) return;
                    body.innerHTML = '';
                    if (Array.isArray(list) && list.length) {
                        list.forEach(item => addSupplierRowRen(item?.name || '', item?.volume || ''));
                    } else {
                        addSupplierRowRen('', '');
                    }
                }

                function handleCrSelectChange(value) {
                    if (!value) {
                        window.__USE_EXISTING_CLIENT_ID__ = null;
                        syncPermitFiles(null);
                        resetCrFilesView();
                        return;
                    }
                    const record = crRecordByNo[value];
                    if (!record) {
                        toast('No saved details found for that C.R. number.');
                        syncPermitFiles(null);
                        resetCrFilesView('No uploads found for that C.R. number.', true);
                        return;
                    }
                    applyCrRecord(record);
                }

                function pick(obj, keys) {
                    for (const k of keys) {
                        const v = (obj && obj[k] !== undefined && obj[k] !== null) ? String(obj[k]).trim() : '';
                        if (v) return v;
                    }
                    return '';
                }

                function applyCrRecord(record) {
                    const client = record?.client || {};

                    // Names / basic
                    setInputValue('first-name-ren', pick(client, ['first', 'first_name']));
                    setInputValue('middle-name-ren', pick(client, ['middle', 'middle_name']));
                    setInputValue('last-name-ren', pick(client, ['last', 'last_name']));

                    setInputValue('business-name-ren', pick(record, ['company_name', 'business_name', 'name_of_business']));
                    setInputValue('business-address-ren', pick(record, ['present_address', 'business_address', 'address']));

                    // 🔽 Fixed mappings with robust fallbacks (your empty fields)
                    setInputValue('operation-place-ren', pick(record, [
                        'proposed_place_of_operation', 'place_of_operation', 'operation_place', 'location', 'place'
                    ]));

                    setInputValue('annual-volume-ren', pick(record, [
                        'expected_annual_volume', 'expected_gross_annual_volume', 'expected_gross_annual_volume_of_business', 'annual_volume'
                    ]));

                    // Label says “Value” in your UI; your data might use “worth” or “value”
                    setInputValue('annual-worth-ren', pick(record, [
                        'estimated_annual_worth', 'annual_worth', 'worth', 'value', 'estimated_value'
                    ]));

                    setInputValue('employees-count-ren', pick(record, [
                        'total_number_of_employees', 'total_employees', 'employees_count', 'number_of_employees'
                    ]));

                    setInputValue('dependents-count-ren', pick(record, [
                        'total_number_of_dependents', 'total_dependents', 'dependents_count', 'number_of_dependents'
                    ]));

                    setInputValue('intended-market-ren', pick(record, [
                        'intended_market', 'selling_products_to', 'markets', 'market'
                    ]));

                    setInputValue('experience-ren', pick(record, [
                        'experience_as_lumber_dealer', 'experience', 'lumber_dealer_experience'
                    ]));

                    // Other fields as before (with a few extra fallbacks)
                    const declName = pick(record, ['declaration_name']) || [pick(client, ['first', 'first_name']), pick(client, ['last', 'last_name'])].filter(Boolean).join(' ').trim();
                    setInputValue('declaration-name-ren', declName);

                    setInputValue('applicant-age-ren', pick(record, ['applicant_age', 'age']));

                    setInputValue('prev-certificate-ren', pick(record, [
                        'permit_number', 'previous_certificate_no', 'prev_certificate_no', 'cr_no'
                    ]));

                    setInputValue('issued-date-ren', pick(record, ['date_issued', 'issued_on']).slice(0, 10));
                    const expiry = pick(record, ['doc_expiry_date', 'cr_expiry_date', 'expiry_date', 'expires_on']);
                    setInputValue('expiry-date-ren', expiry ? expiry.slice(0, 10) : '');

                    setInputValue('cr-license-ren', pick(record, ['cr_license_no', 'cr_license', 'license_no']));
                    setInputValue('sawmill-permit-ren', pick(record, ['sawmill_permit_no', 'sawmill_permit', 'permit_no']));

                    if (record.is_government_employee !== undefined && record.is_government_employee !== null) {
                        setRadioGroup('govt-employee-ren-yes', 'govt-employee-ren-no', record.is_government_employee);
                    }
                    if (record.buying_from_other_sources !== undefined && record.buying_from_other_sources !== null) {
                        setRadioGroup('other-sources-ren-yes', 'other-sources-ren-no', record.buying_from_other_sources);
                    }

                    if (Array.isArray(record.suppliers) && record.suppliers.length) {
                        populateSuppliersRen(record.suppliers);
                    }
                    renderCrFiles(record);
                    syncPermitFiles(record);

                    chosenClientId = record.client_id || null;
                    if (record.client_id) {
                        window.__USE_EXISTING_CLIENT_ID__ = record.client_id;
                        existingClientNames = {
                            first: pick(client, ['first', 'first_name']) || '',
                            middle: pick(client, ['middle', 'middle_name']) || '',
                            last: pick(client, ['last', 'last_name']) || ''
                        };
                    }

                    window.__FORCE_NEW_CLIENT__ = false;
                    precheckCache = null;

                    // Optional: uncomment to inspect what was filled
                    // console.debug('[CR mapped]', {
                    //   opPlace: v('operation-place-ren'),
                    //   annVol:  v('annual-volume-ren'),
                    //   worth:   v('annual-worth-ren'),
                    //   emp:     v('employees-count-ren'),
                    //   deps:    v('dependents-count-ren'),
                    //   exp:     v('experience-ren')
                    // });
                }

                function showLoading(msg = 'Working...') {
                    const overlay = document.getElementById('loadingIndicator');
                    const label = document.getElementById('loadingMessage');
                    if (label) label.textContent = msg;
                    if (overlay) overlay.style.display = 'flex';
                }

                function hideLoading() {
                    const overlay = document.getElementById('loadingIndicator');
                    if (overlay) overlay.style.display = 'none';
                }

                function toast(msg) {
                    const n = document.getElementById('profile-notification');
                    if (!n) return;
                    n.textContent = msg;
                    display(n, 'block');
                    n.style.opacity = '1';
                    setTimeout(() => {
                        n.style.opacity = '0';
                        setTimeout(() => {
                            display(n, 'none');
                            n.style.opacity = '1';
                        }, 350);
                    }, 2400);
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
                        const wrapped = (p.base64 || '').replace(/.{1,76}/g, '$&\r\n');
                        return [
                            '', `--${boundary}`,
                            `Content-Location: ${p.location}`,
                            'Content-Transfer-Encoding: base64',
                            `Content-Type: ${p.contentType}`, '',
                            wrapped
                        ].join('\r\n');
                    }).join('');
                    return header + bodyParts + `\r\n--${boundary}--`;
                }

                function svgToPngDataUrl(svgString) {
                    return new Promise((resolve) => {
                        try {
                            const svgBlob = new Blob([svgString], {
                                type: 'image/svg+xml;charset=utf-8'
                            });
                            const url = URL.createObjectURL(svgBlob);
                            const img = new Image();
                            img.onload = function() {
                                const c = document.createElement('canvas');
                                c.width = img.width;
                                c.height = img.height;
                                c.getContext('2d').drawImage(img, 0, 0);
                                URL.revokeObjectURL(url);
                                try {
                                    resolve(c.toDataURL('image/png'));
                                } catch {
                                    resolve('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
                                }
                            };
                            img.onerror = () => resolve('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
                            img.src = url;
                        } catch {
                            resolve('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
                        }
                    });
                }

                function wordHeaderStyles() {
                    return `
      body, div, p { line-height:1.8; font-family: Arial; font-size:11pt; margin:0; padding:0; }
      .section-title { font-weight:700; margin:15pt 0 6pt 0; text-decoration:underline; }
      .info-line { margin:12pt 0; }
      .underline { display:inline-block; min-width:300px; border-bottom:1px solid #000; padding:0 5px; margin:0 5px; }
      .declaration { margin-top:15pt; }
      .signature-line { margin-top:36pt; border-top:1px solid #000; width:50%; padding-top:3pt; }
      .header-container { position:relative; margin-bottom:20px; width:100%; }
      .header-logo { width:80px; height:80px; }
      .header-content { text-align:center; margin:0 auto; width:100%; }
      .header-content p { margin:0; padding:0; }
      .bold { font-weight:700; }
      .suppliers-table { width:100%; border-collapse:collapse; margin:15px 0; }
      .suppliers-table th, .suppliers-table td { border:1px solid #000; padding:8px; text-align:left; }
      .suppliers-table th { background:#f2f2f2; }
    `;
                }

                function esc(s) {
                    return String(s || '').replace(/[&<>"']/g, c => ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#39;'
                    } [c]));
                }

                function suppliersTableHTML(suppliers) {
                    const body = suppliers.length ?
                        suppliers.map(s => `<tr><td>${esc(s.name)}</td><td>${esc(s.volume)}</td></tr>`).join('') :
                        `<tr><td>&nbsp;</td><td>&nbsp;</td></tr><tr><td>&nbsp;</td><td>&nbsp;</td></tr>`;
                    return `
    <table class="suppliers-table">
      <tr><th>SUPPLIERS NAME/COMPANY</th><th>VOLUME</th></tr>
      ${body}
    </table>`;
                }


                function buildNewDocHTML(logoHref, sigHref, F, suppliers) {
                    const notStr = F.govEmp === 'yes' ? '' : 'not';
                    return `
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:w="urn:schemas-microsoft-com:office:word"
      xmlns:v="urn:schemas-microsoft-com:vml"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
  <meta charset="UTF-8">
  <title>Lumber Dealer Permit Application</title>
  <style>${wordHeaderStyles()}</style>
  <!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]-->
</head>
<body>
  <div class="header-container">
    <div style="text-align:center;">
      <!--[if gte mso 9]><v:shape id="Logo" style="width:80px;height:80px;visibility:visible;mso-wrap-style:square" stroked="f" type="#_x0000_t75"><v:imagedata src="${logoHref}" o:title="Logo"/></v:shape><![endif]-->
      <!--[if !mso]><!-- --><img class="header-logo" src="${logoHref}" alt="Logo"/><!--<![endif]-->
    </div>
    <div class="header-content">
      <p class="bold">Republic of the Philippines</p>
      <p class="bold">Department of Environment and Natural Resources</p>
      <p>Community Environment and Natural Resources Office (CENRO)</p>
      <p>Argao, Cebu</p>
    </div>
  </div>

  <h2 style="text-align:center;margin-bottom:20px;">NEW LUMBER DEALER PERMIT APPLICATION</h2>

  <p class="info-line">The CENR Officer<br>Argao, Cebu</p>
  <p class="info-line">Sir:</p>

  <p class="info-line">I/We, <span class="underline">${esc(F.fullName)}</span>, <span class="underline">${esc(F.applicantAge)}</span> years old, with business address at <span class="underline">${esc(F.businessAddress)}</span>, hereby apply for registration as a Lumber Dealer.</p>


  <p class="info-line">1. I am ${notStr} a government employee and have ${notStr} received any compensation from the government.</p>

  <p class="info-line">2. Proposed place of operation: <span class="underline">${F.operationPlace || ''}</span></p>

  <p class="info-line">3. Expected gross annual volume of business: <span class="underline">${F.annualVolume || ''}</span> worth <span class="underline">${F.annualWorth || ''}</span></p>

  <p class="info-line">4. Total number of employees: <span class="underline">${F.employeesCount || ''}</span></p>
  <p class="info-line" style="margin-left:20px;">Total number of dependents: <span class="underline">${F.dependentsCount || ''}</span></p>

  <p class="info-line">5. List of Suppliers and Corresponding Volume</p>
  ${suppliersTableHTML(suppliers)}

  <p class="info-line">6. Intended market (barangays and municipalities to be served): <span class="underline">${F.intendedMarket || ''}</span></p>

  <p class="info-line">7. My experience as a lumber dealer: <span class="underline">${F.experience || ''}</span></p>

  <p class="info-line">8. I will fully comply with Republic Act No. 123G and the rules and regulations of the Forest Management Bureau.</p>

  <p class="info-line">9. I understand that false statements or omissions may result in:</p>
  <ul style="margin-left:40px;">
    <li>Disapproval of this application</li>
    <li>Cancellation of registration</li>
    <li>Forfeiture of bond</li>
    <li>Criminal liability</li>
  </ul>

  <div style="margin-top:40px;">
    <p>AFFIDAVIT OF TRUTH</p>
    <p>I, <span class="underline">${F.declarationName || ''}</span>, after being sworn to upon my oath, depose and say that I have read the foregoing application and that every statement therein is true and correct to the best of my knowledge and belief.</p>

    <div style="margin-top:60px;">
      ${sigHref
        ? `
          <!--[if gte mso 9]><v:shape style="width:300px;height:110px;visibility:visible;mso-wrap-style:square" stroked="f" type="#_x0000_t75"><v:imagedata src="${sigHref}" o:title="Signature"/></v:shape><![endif]-->
          <!--[if !mso]><!-- --><img src="${sigHref}" width="300" height="110" alt="Signature"/><!--<![endif]-->
          <p>Signature of Applicant</p>
        `
        : `
          <div class="signature-line"></div>
          <p>Signature of Applicant</p>
        `}
    </div>
  </div>
</body>
</html>`;
                }

                function buildRenewalDocHTML(logoHref, sigHref, F, suppliers) {
                    const notStr = F.govEmp === 'yes' ? '' : 'not';
                    return `
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:w="urn:schemas-microsoft-com:office:word"
      xmlns:v="urn:schemas-microsoft-com:vml"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
  <meta charset="UTF-8">
  <title>Renewal ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ Lumber Dealer Permit</title>
  <style>${wordHeaderStyles()}</style>
  <!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]-->
</head>
<body>
  <div class="header-container">
    <div style="text-align:center;">
      <!--[if gte mso 9]><v:shape id="Logo" style="width:80px;height:80px;visibility:visible;mso-wrap-style:square" stroked="f" type="#_x0000_t75"><v:imagedata src="${logoHref}" o:title="Logo"/></v:shape><![endif]-->
      <!--[if !mso]><!-- --><img class="header-logo" src="${logoHref}" alt="Logo"/><!--<![endif]-->
    </div>
    <div class="header-content">
      <p class="bold">Republic of the Philippines</p>
      <p class="bold">Department of Environment and Natural Resources</p>
      <p>Community Environment and Natural Resources Office (CENRO)</p>
      <p>Argao, Cebu</p>
    </div>
  </div>

  <h2 style="text-align:center;margin-bottom:20px;">RENEWAL OF LUMBER DEALER PERMIT</h2>

  <p class="info-line">The CENR Officer<br>Argao, Cebu</p>
  <p class="info-line">Sir:</p>

  <p class="info-line">I/We, <span class="underline">${F.fullName || ''}</span>, <span class="underline">${F.applicantAge || ''}</span> years old, with business address at <span class="underline">${F.businessAddress || ''}</span>, hereby apply for <b>renewal</b> of registration as a Lumber Dealer.</p>

  <p class="info-line">1. I am ${notStr} a government employee and have ${notStr} received any compensation from the government.</p>

  <p class="info-line">2. Place of operation: <span class="underline">${F.operationPlace || ''}</span></p>

  <p class="info-line">3. Expected gross annual volume of business: <span class="underline">${F.annualVolume || ''}</span> worth <span class="underline">${F.annualWorth || ''}</span></p>

  <p class="info-line">4. Total number of employees: <span class="underline">${F.employeesCount || ''}</span></p>
  <p class="info-line" style="margin-left:20px;">Total number of dependents: <span class="underline">${F.dependentsCount || ''}</span></p>

  <p class="info-line">5. List of Suppliers and Corresponding Volume</p>
  ${suppliersTableHTML(suppliers)}

  <p class="info-line">6. Selling products to: <span class="underline">${F.intendedMarket || ''}</span></p>
  <p class="info-line">7. My experience as a lumber dealer: <span class="underline">${F.experience || ''}</span></p>

  <p class="info-line">8. Previous Certificate of Registration No.: <span class="underline">${F.prevCert || ''}</span></p>
  <p class="info-line" style="margin-left:20px;">Issued On: <span class="underline">${F.issuedDate || ''}</span> &nbsp; Expires On: <span class="underline">${F.expiryDate || ''}</span></p>

  <p class="info-line">9. C.R. License No.: <span class="underline">${F.crLicense || ''}</span> &nbsp;&nbsp; Sawmill Permit No.: <span class="underline">${F.sawmillPermit || ''}</span></p>
  <p class="info-line">10. Buying logs/lumber from other sources: <span class="underline">${(F.buyingOther || '').toUpperCase()}</span></p>

  <div style="margin-top:40px;">
    <p>AFFIDAVIT OF TRUTH</p>
    <p>I, <span class="underline">${F.declarationName || ''}</span>, after being sworn to upon my oath, depose and say that I have read the foregoing application and that every statement therein is true and correct to the best of my knowledge and belief.</p>

    <div style="margin-top:60px;">
      ${sigHref
        ? `
          <!--[if gte mso 9]><v:shape style="width:300px;height:110px;visibility:visible;mso-wrap-style:square" stroked="f" type="#_x0000_t75"><v:imagedata src="${sigHref}" o:title="Signature"/></v:shape><![endif]-->
          <!--[if !mso]><!-- --><img src="${sigHref}" width="300" height="110" alt="Signature"/><!--<![endif]-->
          <p>Signature of Applicant</p>
        `
        : `
          <div class="signature-line"></div>
          <p>Signature of Applicant</p>
        `}
    </div>
  </div>
</body>
</html>`;
                }

                /* =========================
                   Mobile menu + header dropdowns
                ========================== */
                const mobileToggle = $('.mobile-toggle');
                mobileToggle?.addEventListener('click', () => {
                    const nav = $('.nav-container');
                    nav?.classList.toggle('active');
                    document.body.style.overflow = nav?.classList.contains('active') ? 'hidden' : '';
                });

                const dropdownIcons = $all('.nav-item.dropdown [data-dropdown-toggle], .nav-item.dropdown .nav-icon');
                dropdownIcons.forEach(icon => {
                    icon.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const item = icon.closest('.nav-item');
                        const menu = item?.querySelector('.dropdown-menu');
                        $all('.dropdown-menu._open').forEach(m => {
                            if (m !== menu) {
                                m.classList.remove('_open');
                                Object.assign(m.style, {
                                    opacity: 0,
                                    visibility: 'hidden',
                                    pointerEvents: 'none',
                                    transform: 'translateY(10px)'
                                });
                            }
                        });
                        if (!menu) return;
                        const open = !menu.classList.contains('_open');
                        menu.classList.toggle('_open', open);
                        Object.assign(menu.style, open ? {
                            opacity: 1,
                            visibility: 'visible',
                            pointerEvents: 'auto',
                            transform: 'translateY(0)'
                        } : {
                            opacity: 0,
                            visibility: 'hidden',
                            pointerEvents: 'none',
                            transform: 'translateY(10px)'
                        });
                    });
                });
                document.addEventListener('click', () => {
                    $all('.dropdown-menu._open').forEach(m => {
                        m.classList.remove('_open');
                        Object.assign(m.style, {
                            opacity: 0,
                            visibility: 'hidden',
                            pointerEvents: 'none',
                            transform: 'translateY(10px)'
                        });
                    });
                });

                /* =========================
   Permit Type toggle (NEW/RENEWAL)
========================== */
                const requirement5 = document.getElementById('requirement-5'); // NEW only: Business Plan
                const requirement8 = document.getElementById('requirement-8'); // NEW only: ITR
                const requirement9 = document.getElementById('requirement-9'); // RENEWAL only: Monthly/Quarterly
                const mayorPermit = document.getElementById('mayors-permit'); // NEW only


                const newSection = document.getElementById('lumber-application-section');
                const renSection = document.getElementById('renewal-application-section');
                const permitTypeBtns = $all('.permit-type-btn');
                const clientModeEl = document.getElementById('clientMode');
                const btnExisting = document.getElementById('btnExisting');
                const btnNew = document.getElementById('btnNew');
                const existingClientRow = document.getElementById('existingClientRow');
                const newClientRow = document.getElementById('newClientRow');
                const clientPick = document.getElementById('clientPick');
                const clientPickError = document.getElementById('clientPickError');
                const clientModeToggle = document.getElementById('clientModeToggle');
                const clientModeHint = document.getElementById('clientModeHint');
                const firstNameInput = document.getElementById('first-name');
                const middleNameInput = document.getElementById('middle-name');
                const lastNameInput = document.getElementById('last-name');
                const declarationNameInput = document.getElementById('declaration-name');
                const nameInputs = [firstNameInput, middleNameInput, lastNameInput];
                const renewalCrPicker = document.getElementById("renewalCrPicker");
                const renewalCrSelect = document.getElementById("renewalCrSelect");
                let manualClientCache = {
                    first: firstNameInput?.value || '',
                    middle: middleNameInput?.value || '',
                    last: lastNameInput?.value || '',
                    declaration: declarationNameInput?.value || ''
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

                function populateNameInputs(first, middle, last, opts = {}) {
                    const silent = !!opts.silent;
                    const values = [first, middle, last];
                    nameInputs.forEach((input, idx) => {
                        if (!input) return;
                        input.value = values[idx] || '';
                        if (!silent) {
                            input.dispatchEvent(new Event('input', {
                                bubbles: true
                            }));
                        }
                    });
                }

                function applyClientPickValues(showError = false) {
                    if (!clientPick) return;
                    if (!clientPick.value) {
                        if (showError) {
                            setClientPickError('Please select an existing client.');
                        } else {
                            setClientPickError('');
                        }
                        populateNameInputs('', '', '', {
                            silent: true
                        });
                        if (declarationNameInput) {
                            declarationNameInput.value = '';
                            declarationNameInput.dispatchEvent(new Event('input', {
                                bubbles: true
                            }));
                        }
                        return;
                    }
                    const opt = clientPick.options[clientPick.selectedIndex];
                    const first = opt?.dataset?.first || '';
                    const middle = opt?.dataset?.middle || '';
                    const last = opt?.dataset?.last || '';
                    const fullName = [first, middle, last].filter(Boolean).join(' ').trim();
                    populateNameInputs(first, middle, last);
                    if (declarationNameInput) {
                        declarationNameInput.value = fullName;
                        declarationNameInput.dispatchEvent(new Event('input', {
                            bubbles: true
                        }));
                    }
                    setClientPickError('');
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
                            declaration: declarationNameInput?.value || ''
                        };
                        applyClientPickValues(false);
                    } else {
                        setClientPickError('');
                        if (clientPick) clientPick.value = '';
                        populateNameInputs(
                            manualClientCache.first || '',
                            manualClientCache.middle || '',
                            manualClientCache.last || '', {
                                silent: true
                            }
                        );
                        if (declarationNameInput) {
                            declarationNameInput.value = manualClientCache.declaration || '';
                            declarationNameInput.dispatchEvent(new Event('input', {
                                bubbles: true
                            }));
                        }
                    }
                }

                btnExisting?.addEventListener('click', () => setClientMode('existing'));
                btnNew?.addEventListener('click', () => setClientMode('new'));
                clientPick?.addEventListener('change', () => {
                    if ((clientModeEl?.value || 'new') === 'existing') {
                        applyClientPickValues(true);
                    } else {
                        setClientPickError('');
                    }
                });
                setClientMode(clientModeEl?.value || 'new');
                renewalCrSelect?.addEventListener('change', (e) => {
                    handleCrSelectChange(e.target.value || '');
                });

                function activePermitType() {
                    return (document.querySelector('.permit-type-btn.active')?.getAttribute('data-type') || 'new');
                }

                window.activePermitType = activePermitType;

                function setPermitType(type) {
                    const isNew = type === 'new';
                    show(newSection, isNew);
                    show(renSection, !isNew);
                    if (clientModeToggle) clientModeToggle.style.display = isNew ? 'flex' : 'none';
                    if (clientModeHint) clientModeHint.style.display = isNew ? 'block' : 'none';
                    if (renewalCrPicker) {
                        renewalCrPicker.style.display = isNew ? 'none' : 'flex';
                        if (isNew && renewalCrSelect) {
                            renewalCrSelect.value = '';
                            handleCrSelectChange('');
                        }
                    }
                    if (isNew) {
                        syncPermitFiles(null);
                        resetCrFilesView();
                    }
                    if (!isNew) {
                        setClientMode('new');
                    }

                    // NEW-only
                    show(requirement5, isNew);
                    show(requirement8, isNew);

                    // RENEWAL-only
                    show(requirement9, !isNew);
                    // NEW-only
                    show(mayorPermit, isNew);


                    // number spans
                    $all('.new-number').forEach(el => show(el, isNew));
                    $all('.renewal-number').forEach(el => show(el, !isNew));

                    setTimeout(() => {
                        isNew ? resizeSigCanvas() : resizeSigCanvasRen();
                    }, 0);
                }

                permitTypeBtns.forEach(btn => {
                    btn.addEventListener('click', () => {
                        permitTypeBtns.forEach(b => b.classList.remove('active'));
                        btn.classList.add('active');
                        setPermitType(btn.getAttribute('data-type'));
                    });
                });
                document.querySelector('.permit-type-btn[data-type="new"]')?.classList.add('active');
                setPermitType('new');

                function requestRenewalFromNew() {
                    permitTypeBtns.forEach(btn => {
                        const isRenewalBtn = btn.getAttribute('data-type') === 'renewal';
                        btn.classList.toggle('active', isRenewalBtn);
                    });
                    setPermitType('renewal');
                    if (typeof autofillRenewalFromNew === 'function') {
                        autofillRenewalFromNew();
                    }
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                    document.getElementById('first-name-ren')?.focus();
                }

                function requestNewFromRenewal() {
                    permitTypeBtns.forEach(btn => {
                        const isNewBtn = btn.getAttribute('data-type') === 'new';
                        btn.classList.toggle('active', isNewBtn);
                    });
                    setPermitType('new');
                    if (typeof autofillNewFromRenewal === 'function') {
                        autofillNewFromRenewal();
                    }
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                    document.getElementById('first-name')?.focus();
                }


                /* =========================
                   Suppliers tables
                ========================== */
                const suppliersTbody = $('#suppliers-table tbody');
                $('#add-supplier-row')?.addEventListener('click', () => addSupplierRow());

                function addSupplierRow(name = '', vol = '') {
                    if (!suppliersTbody) return;
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
      <td><input type="text" class="supplier-name" placeholder="Supplier name" value="${name}"></td>
      <td><input type="text" class="supplier-volume" placeholder="Volume" value="${vol}"></td>
      <td><button type="button" class="remove-btn">Remove</button></td>`;
                    suppliersTbody.appendChild(tr);
                    tr.querySelector('.remove-btn').addEventListener('click', () => {
                        if (suppliersTbody.rows.length > 1) tr.remove();
                    });
                }

                const suppliersTbodyRen = $('#suppliers-table-ren tbody');
                $('#add-supplier-row-ren')?.addEventListener('click', () => addSupplierRowRen());

                function addSupplierRowRen(name = '', vol = '') {
                    if (!suppliersTbodyRen) return;
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
      <td><input type="text" class="supplier-name-ren" placeholder="Supplier name" value="${name}"></td>
      <td><input type="text" class="supplier-volume-ren" placeholder="Volume" value="${vol}"></td>
      <td><button type="button" class="remove-btn-ren">Remove</button></td>`;
                    suppliersTbodyRen.appendChild(tr);
                    tr.querySelector('.remove-btn-ren').addEventListener('click', () => {
                        if (suppliersTbodyRen.rows.length > 1) tr.remove();
                    });
                }

                function gatherSuppliers(isRenewal) {
                    const rows = isRenewal ? $all('#suppliers-table-ren tbody tr') : $all('#suppliers-table tbody tr');
                    return rows.map(r => {
                        const name = (r.querySelector(isRenewal ? '.supplier-name-ren' : '.supplier-name')?.value || '').trim();
                        const volume = (r.querySelector(isRenewal ? '.supplier-volume-ren' : '.supplier-volume')?.value || '').trim();
                        if (!name && !volume) return null;
                        return {
                            name,
                            volume
                        };
                    }).filter(Boolean);
                }

                /* =========================
                   File names on select
                ========================== */
                const fileIds = ['file-1', 'file-2', 'file-4', 'file-5', 'file-6', 'file-7', 'file-8', 'file-9'];

                // File type requirements: 'doc' = PDF/DOC/DOCX, 'img' = JPG/PNG
                const fileTypeMap = {
                    'file-1': 'doc', // Complete Staff Work (CSW)
                    'file-2': 'img', // Geo-tagged pictures
                    'file-4': 'doc', // Log/Lumber Supply Contract
                    'file-5': 'doc', // Business Management Plan
                    'file-6': 'doc', // Mayor's Permit
                    'file-7': 'doc', // Certificate of Registration
                    'file-8': 'doc', // Latest Annual Income Tax Return
                    'file-9': 'doc' // Monthly and Quarterly Reports
                };

                function getFileExtension(filename) {
                    if (!filename) return '';
                    return filename.split('.').pop().toLowerCase();
                }

                function validateFileType(fileId, file) {
                    const expectedType = fileTypeMap[fileId];
                    if (!expectedType || !file) return {
                        valid: true,
                        error: ''
                    };

                    const ext = getFileExtension(file.name);
                    const docExts = ['pdf', 'doc', 'docx'];
                    const imgExts = ['jpg', 'jpeg', 'png'];

                    if (expectedType === 'doc') {
                        if (!docExts.includes(ext)) {
                            return {
                                valid: false,
                                error: 'PDF/DOC/DOCX only.'
                            };
                        }
                    } else if (expectedType === 'img') {
                        if (!imgExts.includes(ext)) {
                            return {
                                valid: false,
                                error: 'JPG/PNG only.'
                            };
                        }
                    }

                    return {
                        valid: true,
                        error: ''
                    };
                }

                // Get the hidden file inputs
                const uploadImg = document.getElementById('uploadImg');
                const uploadDoc = document.getElementById('uploadDoc');

                // MIME type validation
                const allowedMimeTypes = {
                    'img': ['image/jpeg', 'image/png'],
                    'doc': ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
                };

                function validateMimeType(file, expectedType) {
                    if (!file) return {
                        valid: true,
                        error: ''
                    };
                    const allowed = allowedMimeTypes[expectedType] || [];
                    if (!allowed.includes(file.type)) {
                        const errorMsg = expectedType === 'img' ? 'JPG/PNG only.' : 'PDF/DOC/DOCX only.';
                        return {
                            valid: false,
                            error: errorMsg
                        };
                    }
                    return {
                        valid: true,
                        error: ''
                    };
                }

                // Add MIME validation to hidden image input
                uploadImg?.addEventListener('change', function() {
                    const file = this.files?.[0];
                    if (file) {
                        const validation = validateMimeType(file, 'img');
                        if (!validation.valid) {
                            alert(validation.error);
                            this.value = '';
                        }
                    }
                });

                // Add MIME validation to hidden document input
                uploadDoc?.addEventListener('change', function() {
                    const file = this.files?.[0];
                    if (file) {
                        const validation = validateMimeType(file, 'doc');
                        if (!validation.valid) {
                            alert(validation.error);
                            this.value = '';
                        }
                    }
                });

                fileIds.forEach(id => {
                    const input = document.getElementById(id);
                    if (!input) return;

                    // Handle label click - trigger appropriate hidden input based on file type
                    document.querySelector(`label[for="${id}"]`)?.addEventListener('click', (e) => {
                        e.preventDefault();
                        const expectedType = fileTypeMap[id];

                        if (expectedType === 'img' && uploadImg) {
                            // Reset and trigger image input
                            uploadImg.value = '';
                            uploadImg.onchange = (evt) => {
                                if (uploadImg.files?.[0]) {
                                    const file = uploadImg.files[0];
                                    // Store file info in dataset
                                    input.dataset.uploadedFile = file.name;
                                    // Validate and display
                                    const nameEl = input.parentElement?.querySelector('.file-name');
                                    if (nameEl) nameEl.textContent = file.name;
                                    const validation = validateFileType(id, file);
                                    const errorEl = input.parentElement?.querySelector('.file-type-error');
                                    if (!validation.valid && errorEl) {
                                        errorEl.textContent = validation.error;
                                        errorEl.classList.add('show');
                                    } else if (errorEl) {
                                        errorEl.classList.remove('show');
                                        errorEl.textContent = '';
                                    }
                                }
                            };
                            uploadImg.click();
                        } else if (expectedType === 'doc' && uploadDoc) {
                            // Reset and trigger document input
                            uploadDoc.value = '';
                            uploadDoc.onchange = (evt) => {
                                if (uploadDoc.files?.[0]) {
                                    const file = uploadDoc.files[0];
                                    // Store file info in dataset
                                    input.dataset.uploadedFile = file.name;
                                    // Validate and display
                                    const nameEl = input.parentElement?.querySelector('.file-name');
                                    if (nameEl) nameEl.textContent = file.name;
                                    const validation = validateFileType(id, file);
                                    const errorEl = input.parentElement?.querySelector('.file-type-error');
                                    if (!validation.valid && errorEl) {
                                        errorEl.textContent = validation.error;
                                        errorEl.classList.add('show');
                                    } else if (errorEl) {
                                        errorEl.classList.remove('show');
                                        errorEl.textContent = '';
                                    }
                                }
                            };
                            uploadDoc.click();
                        } else {
                            // Fallback: use regular file input
                            input.click();
                        }
                    });

                    // Direct file input change handler (for manual changes or fallback)
                    input.addEventListener('change', () => {
                        triggerInputChange(input, id);
                    });
                });

                function triggerInputChange(input, id) {
                    if (remoteFileFallback[id]) delete remoteFileFallback[id];
                    if (input.dataset && input.dataset.loadedUrl) delete input.dataset.loadedUrl;
                    const nameEl = input.parentElement?.querySelector('.file-name');
                    const errorEl = input.parentElement?.querySelector('.file-type-error');

                    // Determine the file name to display
                    let fileName = 'No file chosen';
                    let fileObj = null;

                    // First check if files were set directly (fallback case)
                    if (input.files?.length > 0) {
                        fileName = input.files[0].name;
                        fileObj = input.files[0];
                    }
                    // Otherwise check dataset (hidden input case)
                    else if (input.dataset?.uploadedFile) {
                        fileName = input.dataset.uploadedFile;
                    }

                    if (nameEl) nameEl.textContent = fileName;

                    // Store the uploaded file info in dataset so validation can detect it
                    if (fileObj || input.dataset.uploadedFile) {
                        if (!input.dataset.uploadedFile && fileObj) {
                            input.dataset.uploadedFile = fileObj.name;
                        }

                        // Only validate if we have an actual file object
                        if (fileObj) {
                            const validation = validateFileType(id, fileObj);
                            if (!validation.valid && errorEl) {
                                errorEl.textContent = validation.error;
                                errorEl.classList.add('show');
                            } else if (errorEl) {
                                errorEl.classList.remove('show');
                                errorEl.textContent = '';
                            }
                        }
                    } else {
                        if (input.dataset) delete input.dataset.uploadedFile;
                        if (errorEl) {
                            errorEl.classList.remove('show');
                            errorEl.textContent = '';
                        }
                    }
                }

                /* =========================
                   Signature NEW
                ========================== */
                const sigCanvas = document.getElementById('signature-pad');
                const clearSigBtn = document.getElementById('clear-signature');
                const saveSigBtn = document.getElementById('save-signature');
                const sigPreview = document.getElementById('signature-image');

                let strokes = [],
                    currentStroke = [],
                    drawing = false,
                    lastPt = {
                        x: 0,
                        y: 0
                    };

                function sizeCanvasToCSS(canvas) {
                    if (!canvas) return;
                    const rect = canvas.getBoundingClientRect();
                    const ratio = Math.max(window.devicePixelRatio || 1, 1);
                    canvas.style.width = rect.width + 'px';
                    canvas.style.height = rect.height + 'px';
                    canvas.width = Math.round(rect.width * ratio);
                    canvas.height = Math.round(rect.height * ratio);
                    const ctx = canvas.getContext('2d');
                    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
                    ctx.fillStyle = '#fff';
                    ctx.fillRect(0, 0, rect.width, rect.height);
                }

                function getCanvasPos(e, canvas) {
                    const r = canvas.getBoundingClientRect();
                    const t = e.touches ? e.touches[0] : null;
                    const cx = t ? t.clientX : e.clientX;
                    const cy = t ? t.clientY : e.clientY;
                    return {
                        x: cx - r.left,
                        y: cy - r.top
                    };
                }

                function repaintSignature(bg = true) {
                    if (!sigCanvas) return;
                    const ctx = sigCanvas.getContext('2d');
                    const rect = sigCanvas.getBoundingClientRect();
                    if (bg) {
                        ctx.fillStyle = '#fff';
                        ctx.fillRect(0, 0, rect.width, rect.height);
                    }
                    ctx.lineWidth = 2;
                    ctx.lineCap = 'round';
                    ctx.strokeStyle = '#111';
                    for (const s of strokes) {
                        if (!s || s.length < 2) continue;
                        ctx.beginPath();
                        ctx.moveTo(s[0].x, s[0].y);
                        for (let i = 1; i < s.length; i++) ctx.lineTo(s[i].x, s[i].y);
                        ctx.stroke();
                    }
                }

                function resizeSigCanvas() {
                    sizeCanvasToCSS(sigCanvas);
                    repaintSignature(true);
                }
                window.addEventListener('resize', () => {
                    if (newSection?.style.display !== 'none') resizeSigCanvas();
                });
                resizeSigCanvas();

                function startSig(e) {
                    if (!sigCanvas) return;
                    drawing = true;
                    currentStroke = [];
                    lastPt = getCanvasPos(e, sigCanvas);
                    currentStroke.push(lastPt);
                    e.preventDefault?.();
                }

                function drawSig(e) {
                    if (!drawing || !sigCanvas) return;
                    const ctx = sigCanvas.getContext('2d');
                    const p = getCanvasPos(e, sigCanvas);
                    ctx.beginPath();
                    ctx.moveTo(lastPt.x, lastPt.y);
                    ctx.lineTo(p.x, p.y);
                    ctx.lineWidth = 2;
                    ctx.lineCap = 'round';
                    ctx.strokeStyle = '#111';
                    ctx.stroke();
                    lastPt = p;
                    currentStroke.push(p);
                    e.preventDefault?.();
                }

                function endSig() {
                    if (!drawing) return;
                    drawing = false;
                    if (currentStroke.length > 1) strokes.push(currentStroke);
                    currentStroke = [];
                }

                if (sigCanvas) {
                    sigCanvas.addEventListener('mousedown', startSig);
                    sigCanvas.addEventListener('mousemove', drawSig);
                    window.addEventListener('mouseup', endSig);
                    sigCanvas.addEventListener('touchstart', startSig, {
                        passive: false
                    });
                    sigCanvas.addEventListener('touchmove', drawSig, {
                        passive: false
                    });
                    window.addEventListener('touchend', endSig);

                    clearSigBtn?.addEventListener('click', () => {
                        strokes = [];
                        repaintSignature(true);
                        if (sigPreview) {
                            sigPreview.src = '';
                            sigPreview.classList.add('hidden');
                        }
                    });
                    saveSigBtn?.addEventListener('click', () => {
                        if (!strokes.some(s => s && s.length > 1)) {
                            alert('Please provide a signature first.');
                            return;
                        }
                        const img = getSignatureDataURLScaled(300, 110);
                        sigPreview.src = img.dataURL;
                        sigPreview.classList.remove('hidden');
                    });
                }

                function getSignatureDataURLScaled(targetW = 300, targetH = 110) {
                    if (!sigCanvas || !strokes.some(s => s && s.length > 1)) return {
                        dataURL: '',
                        w: 0,
                        h: 0
                    };
                    const rect = sigCanvas.getBoundingClientRect();
                    const off = document.createElement('canvas');
                    off.width = targetW;
                    off.height = targetH;
                    const ctx = off.getContext('2d');
                    ctx.fillStyle = '#fff';
                    ctx.fillRect(0, 0, targetW, targetH);
                    const sx = targetW / rect.width,
                        sy = targetH / rect.height;
                    ctx.lineCap = 'round';
                    ctx.strokeStyle = '#111';
                    ctx.lineWidth = 2 * Math.min(sx, sy);
                    for (const s of strokes) {
                        if (!s || s.length < 2) continue;
                        ctx.beginPath();
                        ctx.moveTo(s[0].x * sx, s[0].y * sy);
                        for (let j = 1; j < s.length; j++) ctx.lineTo(s[j].x * sx, s[j].y * sy);
                        ctx.stroke();
                    }
                    return {
                        dataURL: off.toDataURL('image/png'),
                        w: targetW,
                        h: targetH
                    };
                }

                /* =========================
                   Signature RENEWAL
                ========================== */
                const sigCanvasRen = document.getElementById('signature-pad-ren');
                const clearSigBtnRen = document.getElementById('clear-signature-ren');
                const saveSigBtnRen = document.getElementById('save-signature-ren');
                const sigPreviewRen = document.getElementById('signature-image-ren');

                let strokesRen = [],
                    currentStrokeRen = [],
                    drawingRen = false,
                    lastPtRen = {
                        x: 0,
                        y: 0
                    };

                function repaintSignatureRen(bg = true) {
                    if (!sigCanvasRen) return;
                    const ctx = sigCanvasRen.getContext('2d');
                    const rect = sigCanvasRen.getBoundingClientRect();
                    if (bg) {
                        ctx.fillStyle = '#fff';
                        ctx.fillRect(0, 0, rect.width, rect.height);
                    }
                    ctx.lineWidth = 2;
                    ctx.lineCap = 'round';
                    ctx.strokeStyle = '#111';
                    for (const s of strokesRen) {
                        if (!s || s.length < 2) continue;
                        ctx.beginPath();
                        ctx.moveTo(s[0].x, s[0].y);
                        for (let i = 1; i < s.length; i++) ctx.lineTo(s[i].x, s[i].y);
                        ctx.stroke();
                    }
                }

                function resizeSigCanvasRen() {
                    sizeCanvasToCSS(sigCanvasRen);
                    repaintSignatureRen(true);
                }
                window.addEventListener('resize', () => {
                    if (renSection?.style.display !== 'none') resizeSigCanvasRen();
                });

                function startSigRen(e) {
                    if (!sigCanvasRen) return;
                    drawingRen = true;
                    currentStrokeRen = [];
                    lastPtRen = getCanvasPos(e, sigCanvasRen);
                    currentStrokeRen.push(lastPtRen);
                    e.preventDefault?.();
                }

                function drawSigRen(e) {
                    if (!drawingRen || !sigCanvasRen) return;
                    const ctx = sigCanvasRen.getContext('2d');
                    const p = getCanvasPos(e, sigCanvasRen);
                    ctx.beginPath();
                    ctx.moveTo(lastPtRen.x, lastPtRen.y);
                    ctx.lineTo(p.x, p.y);
                    ctx.lineWidth = 2;
                    ctx.lineCap = 'round';
                    ctx.strokeStyle = '#111';
                    ctx.stroke();
                    lastPtRen = p;
                    currentStrokeRen.push(p);
                    e.preventDefault?.();
                }

                function endSigRen() {
                    if (!drawingRen) return;
                    drawingRen = false;
                    if (currentStrokeRen.length > 1) strokesRen.push(currentStrokeRen);
                    currentStrokeRen = [];
                }

                if (sigCanvasRen) {
                    sigCanvasRen.addEventListener('mousedown', startSigRen);
                    sigCanvasRen.addEventListener('mousemove', drawSigRen);
                    window.addEventListener('mouseup', endSigRen);
                    sigCanvasRen.addEventListener('touchstart', startSigRen, {
                        passive: false
                    });
                    sigCanvasRen.addEventListener('touchmove', drawSigRen, {
                        passive: false
                    });
                    window.addEventListener('touchend', endSigRen);

                    clearSigBtnRen?.addEventListener('click', () => {
                        strokesRen = [];
                        repaintSignatureRen(true);
                        if (sigPreviewRen) {
                            sigPreviewRen.src = '';
                            sigPreviewRen.classList.add('hidden');
                        }
                    });
                    saveSigBtnRen?.addEventListener('click', () => {
                        if (!strokesRen.some(s => s && s.length > 1)) {
                            alert('Please provide a signature first.');
                            return;
                        }
                        const img = getSignatureDataURLScaledRen(300, 110);
                        sigPreviewRen.src = img.dataURL;
                        sigPreviewRen.classList.remove('hidden');
                    });
                }

                function getSignatureDataURLScaledRen(targetW = 300, targetH = 110) {
                    if (!sigCanvasRen || !strokesRen.some(s => s && s.length > 1)) return {
                        dataURL: '',
                        w: 0,
                        h: 0
                    };
                    const rect = sigCanvasRen.getBoundingClientRect();
                    const off = document.createElement('canvas');
                    off.width = targetW;
                    off.height = targetH;
                    const ctx = off.getContext('2d');
                    ctx.fillStyle = '#fff';
                    ctx.fillRect(0, 0, targetW, targetH);
                    const sx = targetW / rect.width,
                        sy = targetH / rect.height;
                    ctx.lineCap = 'round';
                    ctx.strokeStyle = '#111';
                    ctx.lineWidth = 2 * Math.min(sx, sy);
                    for (const s of strokesRen) {
                        if (!s || s.length < 2) continue;
                        ctx.beginPath();
                        ctx.moveTo(s[0].x * sx, s[0].y * sy);
                        for (let j = 1; j < s.length; j++) ctx.lineTo(s[j].x * sx, s[j].y * sy);
                        ctx.stroke();
                    }
                    return {
                        dataURL: off.toDataURL('image/png'),
                        w: targetW,
                        h: targetH
                    };
                }

                /* =========================
                   Autofill NEW <-> RENEWAL
                ========================== */
                function autofillRenewalFromNew() {
                    const map = [
                        ['first-name', 'first-name-ren'],
                        ['middle-name', 'middle-name-ren'],
                        ['last-name', 'last-name-ren'],
                        ['business-name', 'business-name-ren'], // <-- added
                        ['business-address', 'business-address-ren'],
                        ['operation-place', 'operation-place-ren'],
                        ['annual-volume', 'annual-volume-ren'],
                        ['annual-worth', 'annual-worth-ren'],
                        ['employees-count', 'employees-count-ren'],
                        ['dependents-count', 'dependents-count-ren'],
                        ['intended-market', 'intended-market-ren'],
                        ['experience', 'experience-ren'],
                        ['declaration-name', 'declaration-name-ren'],
                    ];
                    map.forEach(([a, b]) => {
                        const src = document.getElementById(a),
                            dst = document.getElementById(b);
                        if (src && dst) dst.value = src.value;
                    });
                    (document.getElementById('govt-employee-ren-yes') || {}).checked = !!document.getElementById('govt-employee-yes')?.checked;
                    (document.getElementById('govt-employee-ren-no') || {}).checked = !!document.getElementById('govt-employee-no')?.checked;

                    const rows = $all('#suppliers-table tbody tr');
                    $all('#suppliers-table-ren tbody tr').forEach((r, i) => {
                        if (i > 0) r.remove();
                    });
                    const firstRen = $('#suppliers-table-ren tbody tr');
                    rows.forEach((r, idx) => {
                        const name = r.querySelector('.supplier-name')?.value || '';
                        const vol = r.querySelector('.supplier-volume')?.value || '';
                        if (idx === 0 && firstRen) {
                            firstRen.querySelector('.supplier-name-ren').value = name;
                            firstRen.querySelector('.supplier-volume-ren').value = vol;
                        } else {
                            addSupplierRowRen(name, vol);
                        }
                    });
                }

                function autofillNewFromRenewal() {
                    const map = [
                        ['first-name-ren', 'first-name'],
                        ['middle-name-ren', 'middle-name'],
                        ['last-name-ren', 'last-name'],
                        ['business-name-ren', 'business-name'], // <-- added
                        ['business-address-ren', 'business-address'],
                        ['operation-place-ren', 'operation-place'],
                        ['annual-volume-ren', 'annual-volume'],
                        ['annual-worth-ren', 'annual-worth'],
                        ['employees-count-ren', 'employees-count'],
                        ['dependents-count-ren', 'dependents-count'],
                        ['intended-market-ren', 'intended-market'],
                        ['experience-ren', 'experience'],
                        ['declaration-name-ren', 'declaration-name'],
                    ];
                    map.forEach(([a, b]) => {
                        const src = document.getElementById(a),
                            dst = document.getElementById(b);
                        if (src && dst) dst.value = src.value;
                    });
                    (document.getElementById('govt-employee-yes') || {}).checked = !!document.getElementById('govt-employee-ren-yes')?.checked;
                    (document.getElementById('govt-employee-no') || {}).checked = !!document.getElementById('govt-employee-ren-no')?.checked;

                    const rows = $all('#suppliers-table-ren tbody tr');
                    $all('#suppliers-table tbody tr').forEach((r, i) => {
                        if (i > 0) r.remove();
                    });
                    const first = $('#suppliers-table tbody tr');
                    rows.forEach((r, idx) => {
                        const name = r.querySelector('.supplier-name-ren')?.value || '';
                        const vol = r.querySelector('.supplier-volume-ren')?.value || '';
                        if (idx === 0 && first) {
                            first.querySelector('.supplier-name').value = name;
                            first.querySelector('.supplier-volume').value = vol;
                        } else {
                            addSupplierRow(name, vol);
                        }
                    });
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

                    // Default single Close button
                    let buttons = [{
                        text: 'Close',
                        class: 'btn btn-primary',
                        onClick: () => closeValidation()
                    }];

                    // For the special "need_released_new" block, add a Request new button
                    // beside the Close button so users can quickly switch to filing a NEW permit.
                    if (code === 'need_released_new') {
                        buttons = [{
                                text: 'Request new',
                                class: 'btn btn-outline',
                                onClick: () => {
                                    closeValidation();
                                    // switch UI to new-permit flow and scroll to top
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
                        title: titles[code] || 'Validation',
                        html: message || 'Please resolve this before continuing.',
                        buttons: buttons
                    });
                }

                function showSuggestRenewal() {
                    openValidation({
                        title: 'Consider Renewal',
                        html: 'We detected a released NEW lumber permit for this client. You may file a renewal instead.',
                        buttons: [{
                                text: 'Request renewal',
                                class: 'btn btn-primary',
                                onClick: async () => {
                                    closeValidation();
                                    await requestRenewalFromNew();
                                }
                            },
                            {
                                text: 'Close',
                                class: 'btn btn-outline',
                                onClick: () => closeValidation()
                            }
                        ]
                    });
                }

                let chosenClientId = null;
                let precheckCache = null;
                let suggestedClient = null;
                let existingClientNames = null;
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

                    setVal('first-name', first);
                    setVal('middle-name', middle);
                    setVal('last-name', last);
                    setVal('declaration-name', decl);

                    setVal('first-name-ren', first);
                    setVal('middle-name-ren', middle);
                    setVal('last-name-ren', last);
                    setVal('declaration-name-ren', decl);

                    existingClientNames = {
                        first,
                        middle,
                        last
                    };
                }

                function clearPrecheckState() {
                    chosenClientId = null;
                    precheckCache = null;
                    suggestedClient = null;
                    existingClientNames = null;
                    window.__FORCE_NEW_CLIENT__ = false;
                    window.__USE_EXISTING_CLIENT_ID__ = null;
                }

                clearPrecheckState();

                /* =========================
                   Precheck + decision flow
                ========================== */
                const btnSubmit = document.getElementById('submitApplication');
                btnSubmit?.addEventListener('click', async (ev) => {
                    ev.preventDefault();
                    ev.stopPropagation();
                    ev.stopImmediatePropagation();

                    clearPrecheckState();

                    const type = activePermitType();
                    const first = type === 'renewal' ? v('first-name-ren') : v('first-name');
                    const middle = type === 'renewal' ? v('middle-name-ren') : v('middle-name');
                    const last = type === 'renewal' ? v('last-name-ren') : v('last-name');
                    const clientMode = (clientModeEl?.value || 'new').toLowerCase();
                    const usingExistingPick = type === 'new' && clientMode === 'existing';

                    if (usingExistingPick && !(clientPick?.value)) {
                        setClientPickError('Please select an existing client.');
                        toast('Please select an existing client.');
                        return;
                    }

                    if (usingExistingPick && clientPick?.value) {
                        setClientPickError('');
                        chosenClientId = clientPick.value;
                        window.__FORCE_NEW_CLIENT__ = false;
                        precheckCache = null;
                        existingClientNames = {
                            first: firstNameInput?.value || '',
                            middle: middleNameInput?.value || '',
                            last: lastNameInput?.value || ''
                        };
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
                            suggestedClient = json.client;
                            const full = json.client.full_name || [json.client.first_name, json.client.middle_name, json.client.last_name].filter(Boolean).join(' ') || 'Existing client';
                            const flags = json.flags || {};

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
                                    },
                                    {
                                        text: 'Submit as new',
                                        class: 'btn btn-outline',
                                        onClick: async () => {
                                            window.__FORCE_NEW_CLIENT__ = true;
                                            chosenClientId = null;
                                            window.__USE_EXISTING_CLIENT_ID__ = null;
                                            closeClientDecision();
                                            await finalSubmit();
                                        }
                                    },
                                    {
                                        text: 'Yes, submit',
                                        class: 'btn btn-primary',
                                        onClick: async () => {
                                            window.__FORCE_NEW_CLIENT__ = false;
                                            chosenClientId = String(json.client.client_id);
                                            window.__USE_EXISTING_CLIENT_ID__ = chosenClientId;
                                            applyClientNames(json.client);

                                            if (flags.has_for_payment) {
                                                closeClientDecision();
                                                showBlock('for_payment', 'You still have an unpaid lumber permit on record. Please settle this personally at the office.');
                                                return;
                                            }

                                            if (type === 'new') {
                                                if (flags.has_unexpired) {
                                                    closeClientDecision();
                                                    showBlock('unexpired_permit', 'You still have an unexpired lumber permit. You cannot file a new application.');
                                                    return;
                                                }
                                                if (json.suggest === 'renewal') {
                                                    closeClientDecision();
                                                    showSuggestRenewal();
                                                    return;
                                                }
                                                if (flags.has_pending_new) {
                                                    closeClientDecision();
                                                    showBlock('pending_new', 'You already have a pending NEW lumber application.');
                                                    return;
                                                }
                                                if (flags.has_pending_renewal) {
                                                    closeClientDecision();
                                                    showBlock('pending_renewal', 'You have a pending lumber renewal. Please wait for the update first.');
                                                    return;
                                                }
                                            } else {
                                                if (flags.has_pending_new || flags.has_pending_renewal) {
                                                    closeClientDecision();
                                                    showBlock('pending_renewal', 'You already have a pending lumber application. Please wait for the update first.');
                                                    return;
                                                }
                                                if (flags.has_unexpired) {
                                                    closeClientDecision();
                                                    showBlock('unexpired_permit', 'You still have an unexpired lumber permit. Please wait until it expires to renew.');
                                                    return;
                                                }
                                            }

                                            closeClientDecision();
                                            await finalSubmit();
                                        }
                                    }
                                ]
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
                                        },
                                        {
                                            text: 'Request new',
                                            class: 'btn btn-outline',
                                            onClick: () => {
                                                closeClientDecision();
                                                requestNewFromRenewal();
                                            }
                                        },
                                        {
                                            text: 'Continue renewal',
                                            class: 'btn btn-primary',
                                            onClick: async () => {
                                                window.__FORCE_NEW_CLIENT__ = true;
                                                chosenClientId = null;
                                                window.__USE_EXISTING_CLIENT_ID__ = null;
                                                closeClientDecision();
                                                await finalSubmit();
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
                                                window.__USE_EXISTING_CLIENT_ID__ = null;
                                                closeClientDecision();
                                                await finalSubmit();
                                            }
                                        }
                                    ]
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
                    if (typeof window.__validateLumberForm === 'function') {
                        const ok = window.__validateLumberForm();
                        if (!ok) {
                            if (typeof window.__scrollFirstErrorIntoView === 'function') {
                                window.__scrollFirstErrorIntoView();
                            }
                            toast('Please fix the highlighted fields.');
                            return;
                        }
                    }

                    if (precheckCache && precheckCache.block) {
                        showBlock(precheckCache.block, precheckCache.message || 'You already have a pending lumber application. Please wait for the update first.');
                        return;
                    }

                    showLoading('Submitting application...');
                    try {
                        window.__USE_EXISTING_CLIENT_ID__ = chosenClientId ? String(chosenClientId) : null;
                        await doSubmit();
                        toast(`Application submitted. We'll notify you once reviewed.`);
                        resetForm();
                    } catch (err) {
                        console.error(err);
                        const msg = String(err?.message || '');
                        if (/for payment/i.test(msg)) {
                            showBlock('for_payment', err?.message || 'You still have an unpaid lumber permit on record. Please settle this personally at the office.');
                        } else if (/^BLOCKED:/.test(msg)) {
                            // Blocked by server with a shown modal already; no toast needed.
                        } else {
                            toast(err?.message || 'Submission failed. Please try again.');
                        }
                    } finally {
                        hideLoading();
                    }
                }

                /* =========================
                   Final submit (generate doc + upload files)
                ========================== */
                async function doSubmit() {
                    const type = activePermitType();
                    const isRenewal = type === 'renewal';
                    const selectedId = window.__USE_EXISTING_CLIENT_ID__ ? String(window.__USE_EXISTING_CLIENT_ID__) : null;
                    const forceNew = !!window.__FORCE_NEW_CLIENT__;

                    // Scale signature and gather suppliers
                    const sigScaled = isRenewal ?
                        getSignatureDataURLScaledRen(300, 110) :
                        getSignatureDataURLScaled(300, 110);
                    const hasSignature = !!(sigScaled && sigScaled.dataURL);
                    const suppliersArr = gatherSuppliers(isRenewal);

                    // -------- collect UI values --------
                    let firstName, middleName, lastName,
                        applicantAge, businessName, businessAddress, govEmp,
                        operationPlace, annualVolume, annualWorth,
                        employeesCount, dependentsCount, intendedMarket, experience,
                        typedDeclarationName, prevCert, issuedDate, expiryDate, crLicense, sawmillPermit, buyingOther;

                    if (isRenewal) {
                        firstName = v('first-name-ren');
                        middleName = v('middle-name-ren');
                        lastName = v('last-name-ren');

                        applicantAge = v('applicant-age-ren');
                        businessName = v('business-name-ren');
                        businessAddress = v('business-address-ren');

                        govEmp = (document.getElementById('govt-employee-ren-yes')?.checked ? 'yes' : 'no');
                        operationPlace = v('operation-place-ren');
                        annualVolume = v('annual-volume-ren');
                        annualWorth = v('annual-worth-ren');

                        employeesCount = v('employees-count-ren');
                        dependentsCount = v('dependents-count-ren');

                        intendedMarket = v('intended-market-ren');
                        experience = v('experience-ren');

                        typedDeclarationName = v('declaration-name-ren');

                        prevCert = v('prev-certificate-ren');
                        issuedDate = v('issued-date-ren');
                        expiryDate = v('expiry-date-ren');
                        crLicense = v('cr-license-ren');
                        sawmillPermit = v('sawmill-permit-ren');
                        buyingOther = (document.getElementById('other-sources-ren-yes')?.checked ? 'yes' : 'no');
                    } else {
                        firstName = v('first-name');
                        middleName = v('middle-name');
                        lastName = v('last-name');

                        applicantAge = v('applicant-age');
                        businessName = v('business-name'); // added mapping in save_lumber.php
                        businessAddress = v('business-address');

                        govEmp = (document.getElementById('govt-employee-yes')?.checked ? 'yes' : 'no');
                        operationPlace = v('operation-place');
                        annualVolume = v('annual-volume');
                        annualWorth = v('annual-worth');

                        employeesCount = v('employees-count');
                        dependentsCount = v('dependents-count');

                        intendedMarket = v('intended-market');
                        experience = v('experience');

                        typedDeclarationName = v('declaration-name');
                    }

                    // -------- prefer DB names when user chose an existing client --------
                    // (existingClientNames are set during precheck; fall back to typed if missing)
                    const effFirst = selectedId ? ((existingClientNames?.first) || firstName) : firstName;
                    const effLast = selectedId ? ((existingClientNames?.last) || lastName) : lastName;

                    // Keep middle name as typed so the user can include or leave blank
                    const fullName = [effFirst, middleName, effLast].filter(Boolean).join(' ').trim();

                    // Declaration must be exactly FIRST + LAST from DB when using existing client
                    const declarationName = selectedId ? [effFirst, effLast].join(' ').trim() :
                        typedDeclarationName;

                    // -------- build Word document (MHTML) --------
                    const svgLogo = `
    <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 100 100">
      <circle cx="50" cy="50" r="45" fill="#2b6625"/>
      <path d="M35 35L65 65" stroke="white" stroke-width="5"/>
      <path d="M65 35L35 65" stroke="white" stroke-width="5"/>
      <circle cx="50" cy="50" r="20" stroke="white" stroke-width="3" fill="none"/>
    </svg>`;
                    const logoDataUrl = await svgToPngDataUrl(svgLogo);

                    const fields = {
                        fullName,
                        applicantAge,
                        businessAddress,
                        govEmp,
                        operationPlace,
                        annualVolume,
                        annualWorth,
                        employeesCount,
                        dependentsCount,
                        intendedMarket,
                        experience,
                        declarationName,
                        prevCert,
                        issuedDate,
                        expiryDate,
                        crLicense,
                        sawmillPermit,
                        buyingOther
                    };

                    const logoLoc = 'logo.png';
                    const sigLoc = 'signature.png';

                    const docHTML = isRenewal ?
                        buildRenewalDocHTML(logoLoc, hasSignature ? sigLoc : '', fields, suppliersArr) :
                        buildNewDocHTML(logoLoc, hasSignature ? sigLoc : '', fields, suppliersArr);

                    const parts = [];
                    if (logoDataUrl) {
                        parts.push({
                            location: logoLoc,
                            contentType: 'image/png',
                            base64: (logoDataUrl.split(',')[1] || '')
                        });
                    }
                    if (hasSignature) {
                        parts.push({
                            location: sigLoc,
                            contentType: 'image/png',
                            base64: (sigScaled.dataURL.split(',')[1] || '')
                        });
                    }

                    const mhtml = makeMHTML(docHTML, parts);
                    const docBlob = new Blob([mhtml], {
                        type: 'application/msword'
                    });

                    // Filename: when using existing client, force "First_Last"
                    const nameForFile = selectedId ? ([effFirst, effLast].join(' ').trim()) : fullName;
                    const docFileName = `${isRenewal ? 'Lumber_Renewal' : 'Lumber_New'}_${(nameForFile || 'Applicant').replace(/\s+/g, '_')}.doc`;
                    const docFile = new File([docBlob], docFileName, {
                        type: 'application/msword'
                    });

                    // -------- assemble FormData --------
                    const fd = new FormData();
                    fd.append('permit_type', isRenewal ? 'renewal' : 'new');

                    // Base identity fields (typed; server may override when use_client_id is set)
                    fd.append('first_name', firstName);
                    fd.append('middle_name', middleName);
                    fd.append('last_name', lastName);

                    // App-specific fields
                    fd.append('applicant_age', applicantAge);
                    fd.append('business_name', businessName); // mapped to company_name on server
                    fd.append('business_address', businessAddress);
                    fd.append('is_government_employee', govEmp);
                    fd.append('proposed_place_of_operation', operationPlace);
                    fd.append('expected_annual_volume', annualVolume);
                    fd.append('estimated_annual_worth', annualWorth);
                    fd.append('total_number_of_employees', employeesCount);
                    fd.append('total_number_of_dependents', dependentsCount);
                    fd.append('intended_market', intendedMarket);
                    fd.append('my_experience_as_alumber_dealer', experience);

                    // Enforced declaration (DB first + last if using existing)
                    fd.append('declaration_name', declarationName);

                    // Suppliers
                    fd.append('suppliers_json', JSON.stringify(suppliersArr));

                    // Renewal-only extras
                    if (isRenewal) {
                        fd.append('prev_certificate_no', prevCert);
                        fd.append('issued_date', issuedDate);
                        fd.append('expiry_date', expiryDate);
                        fd.append('cr_license_no', crLicense);
                        fd.append('sawmill_permit_no', sawmillPermit);
                        fd.append('buying_from_other_sources', buyingOther);
                    }

                    // Generated application document + signature
                    fd.append('application_doc', docFile);
                    if (hasSignature) {
                        const sigBlob = dataURLToBlob(sigScaled.dataURL);
                        if (sigBlob) {
                            fd.append('signature_file', new File([sigBlob], 'signature.png', {
                                type: 'image/png'
                            }));
                        }
                    }

                    // Attach uploads from the form
                    const pick = (id) => {
                        const input = document.getElementById(id);
                        if (!input) return remoteFileFallback[id] || null;
                        return input.files?.[0] || remoteFileFallback[id] || null;
                    };
                    const filesCommon = {
                        lumber_csw_document: pick('file-1'),
                        geo_photos: pick('file-2'),
                        application_form: null, // generated above
                        lumber_supply_contract: pick('file-4'),
                        lumber_mayors_permit: pick('file-6'),
                        lumber_registration_certificate: pick('file-7'),
                        lumber_or_copy: pick('file-10a'),
                        lumber_op_copy: pick('file-10b'),
                    };
                    const filesNew = {
                        lumber_business_plan: pick('file-5'),
                        lumber_tax_return: pick('file-8')
                    };
                    const filesRenewal = {
                        lumber_monthly_reports: pick('file-9')
                    };

                    Object.entries(filesCommon).forEach(([k, f]) => {
                        if (f) fd.append(k, f);
                    });
                    if (isRenewal) {
                        Object.entries(filesRenewal).forEach(([k, f]) => {
                            if (f) fd.append(k, f);
                        });
                    } else {
                        Object.entries(filesNew).forEach(([k, f]) => {
                            if (f) fd.append(k, f);
                        });
                    }

                    // Pass the client-choice flags to the server (server enforces DB names when use_client_id is present)
                    if (selectedId) {
                        fd.append('use_existing_client_id', selectedId);
                    }
                    if (forceNew) {
                        fd.append('force_new_client', '1');
                    }

                    // -------- submit --------
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
                        throw new Error(`HTTP ${res.status} ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ ${text.slice(0, 200)}`);
                    }
                    if (!res.ok || !json.ok) {
                        // If server returned a structured block (like need_released_new), show the
                        // validation modal so UX matches precheck behavior instead of a toast/notification.
                        if (json && json.block) {
                            hideLoading();
                            showBlock(json.block, json.message || 'Action required');
                            // Stop submission by throwing a sentinel error (already showed modal)
                            throw new Error('BLOCKED:' + (json.block || ''));
                        }
                        throw new Error(json?.error || `HTTP ${res.status}`);
                    }
                }


                /* =========================
                   Reset form after success
                ========================== */
                function resetForm() {
                    $all('input[type="text"], input[type="number"], input[type="date"]').forEach(i => i.value = '');
                    (document.getElementById('govt-employee-no') || {}).checked = true;
                    (document.getElementById('govt-employee-ren-no') || {}).checked = true;
                    (document.getElementById('other-sources-ren-no') || {}).checked = true;

                    $all('input[type="file"]').forEach(fi => {
                        fi.value = '';
                        const nameSpan = fi.parentElement?.querySelector('.file-name');
                        if (nameSpan) nameSpan.textContent = 'No file chosen';
                    });

                    $all('#suppliers-table tbody tr').forEach((r, i) => {
                        if (i > 0) r.remove();
                    });
                    const first = $('#suppliers-table tbody tr');
                    if (first) {
                        first.querySelector('.supplier-name').value = '';
                        first.querySelector('.supplier-volume').value = '';
                    }

                    $all('#suppliers-table-ren tbody tr').forEach((r, i) => {
                        if (i > 0) r.remove();
                    });
                    const firstRen = $('#suppliers-table-ren tbody tr');
                    if (firstRen) {
                        firstRen.querySelector('.supplier-name-ren').value = '';
                        firstRen.querySelector('.supplier-volume-ren').value = '';
                    }

                    strokes = [];
                    repaintSignature(true);
                    if (sigPreview) {
                        sigPreview.src = '';
                        sigPreview.classList.add('hidden');
                    }
                    strokesRen = [];
                    repaintSignatureRen(true);
                    if (sigPreviewRen) {
                        sigPreviewRen.src = '';
                        sigPreviewRen.classList.add('hidden');
                    }

                    permitTypeBtns.forEach(b => b.classList.toggle('active', b.getAttribute('data-type') === 'new'));
                    setPermitType('new');
                    if (renewalCrSelect) {
                        renewalCrSelect.value = '';
                        handleCrSelectChange('');
                    }
                    if (renewalCrPicker) renewalCrPicker.style.display = 'none';
                    clearPrecheckState();

                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                }
            })();
        </script>
        <script>
            /* ===========================================================
   Lumber Dealer ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â Client-side Validation (separate script)
   - Shows red error TEXT under each input (no red borders)
   - Validates NEW & RENEWAL sections
   - Intercepts Submit button (capture phase) to block submit when invalid
   - Does NOT validate: file inputs, signature canvases, middle names
   - Safe to paste after your existing scripts
=========================================================== */
            (function() {
                'use strict';

                /* ------------------ tiny helpers (scoped) ------------------ */
                const $ = (s, r = document) => r.querySelector(s);
                const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

                function isBlank(str) {
                    return !str || !str.trim();
                }

                function minChars(str, n) {
                    return (str || '').trim().length >= n;
                }

                function hasLetters(str) {
                    return /[A-Za-z]/.test(str || '');
                }

                function hasDigits(str) {
                    return /\d/.test(str || '');
                }

                function normalizeNum(str) {
                    if (!str) return '';
                    return str.replace(/[^\d.]/g, '').replace(/(\..*)\./g, '$1');
                }

                // create or get an error holder div right after a field
                function ensureErrorEl(el) {
                    if (!el) return null;
                    let next = el.nextElementSibling;
                    if (!(next && next.classList && next.classList.contains('field-error'))) {
                        next = document.createElement('div');
                        next.className = 'field-error';
                        next.style.cssText = 'color:#d32f2f;margin-top:6px;font-size:.9rem;display:none;';
                        el.insertAdjacentElement('afterend', next);
                    }
                    return next;
                }

                function setError(el, msg) {
                    if (!el) return;
                    const holder = ensureErrorEl(el);
                    if (!holder) return;
                    if (msg) {
                        holder.textContent = msg;
                        holder.style.display = 'block';
                    } else {
                        holder.textContent = '';
                        holder.style.display = 'none';
                    }
                    // keep borders clean
                    el.classList.remove('error', 'is-invalid');
                    el.style.borderColor = '';
                    el.style.outlineColor = '';
                    el.style.boxShadow = '';
                }

                /* ------------------ per-field rules ------------------ */
                // Names: allow basic letters plus space/hyphen/apostrophe/period
                const NAME_RX = /^[A-Za-z' .-]{2,50}$/;
                const MID_RX = /^[A-Za-z' .-]{1,50}$/;
                const GENERIC_ID_RX = /^[A-Za-z0-9\-\/\s]{3,}$/; // for IDs/permits

                // ===== NEW: File validation helpers =====
                const FILE_IDS = ['file-1', 'file-2', 'file-4', 'file-5', 'file-6', 'file-7', 'file-8', 'file-9'];

                // What’s required per mode:
                const REQUIRED_NEW = ['file-1', 'file-2', 'file-4', 'file-5', 'file-6', 'file-7', 'file-8']; // includes Mayor's Permit (file-6)
                const REQUIRED_RENEWAL = ['file-1', 'file-2', 'file-4', 'file-7', 'file-9']; // includes Monthly/Quarterly (file-9)

                function isDisplayed(el) {
                    if (!el) return false;
                    const cs = getComputedStyle(el);
                    return cs.display !== 'none' && cs.visibility !== 'hidden';
                }

                function hasFile(input) {
                    if (!input) return false;
                    // valid if: user picked a file OR a file is stored in dataset OR a CR-loaded remote file is attached
                    return (input.files && input.files.length > 0) || (input.dataset && (input.dataset.uploadedFile || input.dataset.loadedUrl));
                }

                function ensureFileError(container) {
                    if (!container) return null;
                    let holder = container.querySelector('.field-error');
                    if (!holder) {
                        holder = document.createElement('div');
                        holder.className = 'field-error';
                        holder.style.cssText = 'color:#d32f2f;margin-top:6px;font-size:.9rem;display:none;';
                        container.appendChild(holder);
                    }
                    return holder;
                }

                function setFileError(container, msg) {
                    const holder = ensureFileError(container);
                    if (!holder) return;
                    holder.textContent = msg || '';
                    holder.style.display = msg ? 'block' : 'none';
                }

                function validateFiles(isRenewal) {
                    const req = isRenewal ? REQUIRED_RENEWAL : REQUIRED_NEW;
                    let ok = true;
                    req.forEach(id => {
                        const input = document.getElementById(id);
                        if (!input) return;
                        const container = input.closest('.file-input-container') || input.parentElement;

                        // skip if its requirement row is currently hidden by the NEW/RENEWAL toggle
                        const reqRow = container?.closest('.requirement-item') || container;
                        if (reqRow && !isDisplayed(reqRow)) return;

                        const good = hasFile(input);
                        setFileError(container, good ? '' : 'This file is required.');
                        if (!good) ok = false;
                    });
                    return ok;
                }
                // ===== /NEW: File validation helpers =====



                const rules = {
                    // NEW
                    'first-name': v => {
                        if (isBlank(v)) return 'First name is required.';
                        if (!NAME_RX.test(v)) return 'First name must be 2-50 letters (no numbers).';
                    },
                    'middle-name': v => {
                        if (v && !MID_RX.test(v)) return 'Middle name should contain letters only.';
                    }, // optional
                    'last-name': v => {
                        if (isBlank(v)) return 'Last name is required.';
                        if (!NAME_RX.test(v)) return 'Last name must be 2-50 letters (no numbers).';
                    },
                    'applicant-age': v => {
                        if (isBlank(v)) return 'Age is required.';
                        const n = parseInt(v, 10);
                        if (Number.isNaN(n)) return 'Age must be a number.';
                        if (n < 18 || n > 120) return 'Age must be between 18 and 120.';
                    },
                    'business-name': v => {
                        if (isBlank(v)) return 'Business name is required.';
                        if (!minChars(v, 3) || !/[A-Za-z0-9]/.test(v)) return 'Enter a valid business name (min 3 chars).';
                    },
                    'business-address': v => {
                        if (isBlank(v)) return 'Business address is required.';
                        if (!minChars(v, 10)) return 'Business address must be at least 10 characters.';
                        if (!hasLetters(v) || !hasDigits(v)) return 'Include both street/house number and area (letters and numbers).';
                    },
                    'operation-place': v => {
                        if (isBlank(v)) return 'Proposed place of operation is required.';
                        if (!minChars(v, 5)) return 'Enter at least 5 characters.';
                    },
                    'annual-volume': v => {
                        if (isBlank(v)) return 'Expected annual volume is required.';
                        if (!hasDigits(v)) return 'Include a numeric volume (e.g., 1,000 bd ft).';
                    },
                    'annual-worth': v => {
                        if (isBlank(v)) return 'Worth is required.';
                        const num = normalizeNum(v);
                        if (!num) return 'Enter a numeric worth (e.g., 500000).';
                        if (parseFloat(num) <= 0) return 'Worth must be greater than 0.';
                    },
                    'employees-count': v => {
                        if (isBlank(v)) return 'Total employees is required.';
                        const n = parseInt(v, 10);
                        if (!Number.isInteger(n) || n < 0 || n > 5000) return 'Enter a whole number 0-5000.';
                    },
                    'dependents-count': v => {
                        if (isBlank(v)) return 'Total dependents is required.';
                        const n = parseInt(v, 10);
                        if (!Number.isInteger(n) || n < 0 || n > 5000) return 'Enter a whole number 0-5000.';
                    },
                    'intended-market': v => {
                        if (isBlank(v)) return 'Intended market is required.';
                        if (!minChars(v, 10)) return 'Describe the intended market (10+ chars).';
                    },
                    'experience': v => {
                        if (isBlank(v)) return 'Experience is required.';
                        if (!minChars(v, 10)) return 'Describe your experience in at least 10 characters.';
                    },
                    'declaration-name': v => {
                        if (isBlank(v)) return 'Declaration name is required.';
                        if (!/^[A-Za-z' .-]{4,100}$/.test(v)) return 'Use letters, spaces, and punctuation (.-\').';
                        const f = $('#first-name')?.value?.trim().toLowerCase();
                        const l = $('#last-name')?.value?.trim().toLowerCase();
                        if (f && l) {
                            const lower = v.trim().toLowerCase();
                            if (!(lower.includes(f) && lower.includes(l))) return 'Include your first and last name here.';
                        }
                    },

                    // RENEWAL (mirror NEW + extras)
                    'first-name-ren': v => rules['first-name'](v),
                    'middle-name-ren': v => rules['middle-name'](v),
                    'last-name-ren': v => rules['last-name'](v),
                    'applicant-age-ren': v => rules['applicant-age'](v),
                    'business-name-ren': v => rules['business-name'](v),
                    'business-address-ren': v => rules['business-address'](v),
                    'operation-place-ren': v => rules['operation-place'](v),
                    'annual-volume-ren': v => rules['annual-volume'](v),
                    'annual-worth-ren': v => rules['annual-worth'](v),
                    'employees-count-ren': v => rules['employees-count'](v),
                    'dependents-count-ren': v => rules['dependents-count'](v),
                    'intended-market-ren': v => rules['intended-market'](v),
                    'experience-ren': v => rules['experience'](v),
                    'declaration-name-ren': v => {
                        if (!v || !v.trim()) return 'Declaration name is required.';
                        if (!/^[A-Za-z' .-]{4,100}$/.test(v)) return 'Use letters, spaces, and punctuation (.-\').';
                        const f = document.querySelector('#first-name-ren')?.value?.trim().toLowerCase();
                        const l = document.querySelector('#last-name-ren')?.value?.trim().toLowerCase();
                        if (f && l) {
                            const lower = v.trim().toLowerCase();
                            if (!(lower.includes(f) && lower.includes(l))) return 'Include your first and last name here.';
                        }
                    },
                    'prev-certificate-ren': v => {
                        if (!v || !v.trim()) return 'Previous C.R. No. is required.';
                    },

                    'issued-date-ren': v => {
                        const exp = $('#expiry-date-ren')?.value;
                        if (v && exp && v > exp) return 'Issued date must be on/before the expiry date.';
                    },
                    'expiry-date-ren': v => {
                        const iss = $('#issued-date-ren')?.value;
                        if (v && iss && iss > v) return 'Expiry date must be on/after issued date.';
                    },
                    'cr-license-ren': v => {
                        if (v && !GENERIC_ID_RX.test(v)) return 'Use letters/numbers, slashes or dashes.';
                    },
                    'sawmill-permit-ren': v => {
                        if (v && !GENERIC_ID_RX.test(v)) return 'Use letters/numbers, slashes or dashes.';
                    }
                };

                function validateSuppliers(isRenewal) {
                    const rows = $$(isRenewal ? '#suppliers-table-ren tbody tr' : '#suppliers-table tbody tr');
                    let ok = true;
                    let hasData = false;

                    rows.forEach((row) => {
                        const nameEl = row.querySelector(isRenewal ? '.supplier-name-ren' : '.supplier-name');
                        const volEl = row.querySelector(isRenewal ? '.supplier-volume-ren' : '.supplier-volume');
                        const name = (nameEl?.value || '').trim();
                        const volume = (volEl?.value || '').trim();
                        const emptyRow = !name && !volume;

                        if (emptyRow) {
                            setError(nameEl, '');
                            setError(volEl, '');
                            return;
                        }

                        hasData = true;

                        if (!name || name.length < 3) {
                            setError(nameEl, 'Supplier name must be at least 3 characters.');
                            ok = false;
                        } else {
                            setError(nameEl, '');
                        }

                        if (!volume) {
                            setError(volEl, 'Volume is required.');
                            ok = false;
                        } else if (!hasDigits(volume)) {
                            setError(volEl, 'Include a numeric volume.');
                            ok = false;
                        } else {
                            setError(volEl, '');
                        }
                    });

                    if (!hasData && rows.length) {
                        const firstRow = rows[0];
                        const firstNameEl = firstRow.querySelector(isRenewal ? '.supplier-name-ren' : '.supplier-name');
                        const firstVolEl = firstRow.querySelector(isRenewal ? '.supplier-volume-ren' : '.supplier-volume');
                        setError(firstNameEl, 'Add at least one supplier entry.');
                        setError(firstVolEl, 'Add at least one supplier entry.');
                        ok = false;
                    }

                    return ok;
                }

                function validateSection(type) {
                    const isRenewal = type === 'renewal';
                    const newFields = [
                        'first-name', 'middle-name', 'last-name', 'applicant-age',
                        'business-name', 'business-address', 'operation-place',
                        'annual-volume', 'annual-worth', 'employees-count',
                        'dependents-count', 'intended-market', 'experience',
                        'declaration-name'
                    ];
                    const renewalFields = [
                        'first-name-ren', 'middle-name-ren', 'last-name-ren', 'applicant-age-ren',
                        'business-name-ren', 'business-address-ren', 'operation-place-ren',
                        'annual-volume-ren', 'annual-worth-ren', 'employees-count-ren',
                        'dependents-count-ren', 'intended-market-ren', 'experience-ren',
                        'declaration-name-ren', 'prev-certificate-ren', 'issued-date-ren',
                        'expiry-date-ren', 'cr-license-ren', 'sawmill-permit-ren'
                    ];

                    const targets = isRenewal ? renewalFields : newFields;
                    let ok = true;

                    targets.forEach((id) => {
                        const el = document.getElementById(id);
                        if (!el) return;
                        const rule = rules[id];
                        const msg = rule ? (rule(el.value) || '') : '';
                        setError(el, msg);
                        if (msg) ok = false;
                    });

                    if (!validateSuppliers(isRenewal)) ok = false;
                    if (!validateFiles(isRenewal)) ok = false;
                    return ok;
                }

                function scrollFirstErrorIntoView() {
                    const firstError = document.querySelector('.field-error:not([style*="display: none"])');
                    if (firstError) {
                        const target = firstError.previousElementSibling instanceof HTMLElement ?
                            firstError.previousElementSibling :
                            firstError;
                        target?.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                        if (target instanceof HTMLElement && typeof target.focus === 'function') {
                            target.focus();
                        }
                    }
                }

                window.__validateLumberForm = () => validateSection(activePermitType());
                window.validateLumberForm = window.__validateLumberForm;
                window.__scrollFirstErrorIntoView = scrollFirstErrorIntoView;

                /* ------------------ live validation + submit interception ------------------ */
                function attachLiveValidation() {
                    const allIds = [
                        'first-name', 'middle-name', 'last-name', 'applicant-age', 'business-name', 'business-address',
                        'operation-place', 'annual-volume', 'annual-worth', 'employees-count', 'dependents-count',
                        'intended-market', 'experience', 'declaration-name',
                        'first-name-ren', 'middle-name-ren', 'last-name-ren', 'applicant-age-ren', 'business-name-ren', 'business-address-ren',
                        'operation-place-ren', 'annual-volume-ren', 'annual-worth-ren', 'employees-count-ren', 'dependents-count-ren',
                        'intended-market-ren', 'experience-ren', 'declaration-name-ren',
                        'prev-certificate-ren', 'issued-date-ren', 'expiry-date-ren', 'cr-license-ren', 'sawmill-permit-ren'
                    ];
                    allIds.forEach(id => {
                        const el = document.getElementById(id);
                        if (!el) return;
                        ensureErrorEl(el);
                        const handler = () => {
                            const rule = rules[id];
                            let msg = rule ? (rule(el.value) || '') : '';
                            if (id === 'issued-date-ren' || id === 'expiry-date-ren') {
                                const iss = $('#issued-date-ren')?.value;
                                const exp = $('#expiry-date-ren')?.value;
                                // if ((iss && !exp) || (!iss && exp)) msg = 'Provide both issued and expiry dates.';
                                if (rule) msg = rule(el.value) || '';
                            }
                            setError(el, msg);
                        };
                        el.addEventListener('input', handler);
                        el.addEventListener('blur', handler);
                        // in case previous CSS/classes were adding red borders:
                        el.classList.remove('error', 'is-invalid');
                    });

                    // suppliers: attach per row + when adding rows
                    function hookRows(tableSel, isRenewal) {
                        $$(tableSel + ' tbody tr').forEach(tr => {
                            const nameEl = tr.querySelector(isRenewal ? '.supplier-name-ren' : '.supplier-name');
                            const volEl = tr.querySelector(isRenewal ? '.supplier-volume-ren' : '.supplier-volume');
                            if (nameEl) {
                                ensureErrorEl(nameEl);
                                nameEl.addEventListener('input', () => validateSuppliers(isRenewal));
                            }
                            if (volEl) {
                                ensureErrorEl(volEl);
                                volEl.addEventListener('input', () => validateSuppliers(isRenewal));
                            }
                        });
                    }
                    hookRows('#suppliers-table', false);
                    hookRows('#suppliers-table-ren', true);

                    $('#add-supplier-row')?.addEventListener('click', () => setTimeout(() => hookRows('#suppliers-table', false), 0));
                    $('#add-supplier-row-ren')?.addEventListener('click', () => setTimeout(() => hookRows('#suppliers-table-ren', true), 0));
                    // NEW: live validation for file inputs
                    // When a single file input changes, validate only that input instead
                    // of running full `validateFiles` which would show errors for all
                    // other required file inputs immediately.
                    FILE_IDS.forEach(id => {
                        const el = document.getElementById(id);
                        if (!el) return;
                        el.addEventListener('change', () => {
                            const mode = (typeof activePermitType === 'function' ? activePermitType() : 'new');
                            const isRenewal = mode === 'renewal';

                            const container = el.closest('.file-input-container') || el.parentElement;
                            // If the requirement row is hidden for current mode, clear any message and skip
                            const reqRow = container?.closest('.requirement-item') || container;
                            if (reqRow && !isDisplayed(reqRow)) {
                                setFileError(container, '');
                                return;
                            }

                            const good = hasFile(el);
                            setFileError(container, good ? '' : 'This file is required.');
                        });
                    });

                }

                function interceptSubmitClicks() {
                    // No pre-submit guard; validation happens during final submit.
                }

                // init
                attachLiveValidation();
                interceptSubmitClicks();
            })();
        </script>



    </body>






    </html>