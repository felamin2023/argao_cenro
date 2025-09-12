<?php
// backend/admin/update_status.php
// session_start();
// header('Content-Type: application/json');
// if (!isset($_SESSION['user_id'])) {
//     echo json_encode(['success' => false, 'error' => 'Unauthorized']);
//     exit();
// }
// if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
//     echo json_encode(['success' => false, 'error' => 'Invalid request']);
//     exit();
// }
// if (!isset($_POST['id']) || !is_numeric($_POST['id']) || !isset($_POST['status'])) {
//     echo json_encode(['success' => false, 'error' => 'Invalid input']);
//     exit();
// }
// $id = intval($_POST['id']);
// $status = $_POST['status'];
// $allowed = ['Pending', 'Verified', 'Rejected'];
// if (!in_array($status, $allowed)) {
//     echo json_encode(['success' => false, 'error' => 'Invalid status']);
//     exit();
// }
// include_once __DIR__ . '/../connection.php';

// if ($status === 'Rejected' && isset($_POST['reason']) && trim($_POST['reason']) !== '') {
//     $reason = trim($_POST['reason']);
//     $rejected_by = $_SESSION['user_id'];

//     $logStmt = $conn->prepare('INSERT INTO registration_rejection_logs (user_id, rejected_by, reason) VALUES (?, ?, ?)');
//     $logStmt->bind_param('iis', $id, $rejected_by, $reason);
//     $logStmt->execute();
//     $logStmt->close();


//     $emailStmt = $conn->prepare('SELECT email, first_name FROM users WHERE id = ?');
//     $emailStmt->bind_param('i', $id);
//     $emailStmt->execute();
//     $emailStmt->bind_result($user_email, $user_first_name);
//     if ($emailStmt->fetch()) {

//         include_once __DIR__ . '/../../send_mail_smtp.php';
//         $subject = 'DENR Admin Registration Rejected';
//         $body = "Dear $user_first_name,\n\nWe regret to inform you that your admin registration has been rejected.\n\nReason: $reason\n\nIf you have questions, please contact the system administrator.\n\nThank you.";
//         send_smtp_mail($user_email, $subject, $body);
//     }
//     $emailStmt->close();
// }
// $stmt = $conn->prepare('UPDATE users SET status=? WHERE id=? AND role="Admin"');
// $stmt->bind_param('si', $status, $id);
// $success = $stmt->execute();
// $stmt->close();
// $conn->close();
// if ($success) {
//     echo json_encode(['success' => true]);
// } else {
//     echo json_encode(['success' => false, 'error' => 'Update failed']);
// }

declare(strict_types=1);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

session_start();
require_once __DIR__ . '/../connection.php';
header('Content-Type: application/json');

$id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = trim((string)($_POST['status'] ?? ''));
$reason = trim((string)($_POST['reason'] ?? '')); // optional; not stored without a column

if ($id <= 0 || $status === '') {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

try {
    $st = $pdo->prepare("UPDATE public.users SET status = :status WHERE id = :id");
    $st->execute([':status' => $status, ':id' => $id]);

    // If you later add a table/column to log $reason, insert it here.
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('[UPDATE STATUS] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
