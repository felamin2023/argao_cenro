<?php
// supereach.php (PDO / Supabase Postgres)
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

// Request id (numeric id column)
$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($request_id <= 0) {
    header('Location: supernotif.php');
    exit();
}

// Fetch the selected request
try {
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
    if (!$request) {
        header('Location: supernotif.php');
        exit();
    }

    // Mark as read if unread
    if ((int)$request['is_read'] === 0) {
        $mk = $pdo->prepare("UPDATE public.profile_update_requests SET is_read = true WHERE id = :id");
        $mk->execute([':id' => $request_id]);
        $request['is_read'] = 1;
    }
} catch (Throwable $e) {
    error_log('[SUPEREACH FETCH] ' . $e->getMessage());
    header('Location: supernotif.php');
    exit();
}

// Image (handle Supabase public URL or fallback)
$imgVal = trim((string)($request['image'] ?? ''));
$isUrl  = (bool)preg_match('~^https?://~i', $imgVal);
$imageSrc = $isUrl && $imgVal !== '' ? htmlspecialchars($imgVal, ENT_QUOTES, 'UTF-8')
    : '/denr/superadmin/default-profile.jpg';

// Escape fields for display
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
            <div class="nav-item">
                <div class="nav-icon"><a href="supermessage.php"><i class="fas fa-envelope" style="color:black;"></i></a></div>
            </div>
            <div class="nav-item dropdown">
                <div class="nav-icon active"><i class="fas fa-bell"></i></div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3><a href="supernotif.php" class="mark-all-read">View All</a>
                    </div>
                </div>
            </div>
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
                        <button type="button" id="deleteBtn" class="delete-button" style="background:#dc3545;color:#fff;">DELETE</button>
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