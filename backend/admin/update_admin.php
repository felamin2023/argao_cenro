<?php

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
// $fields = ['id', 'first_name', 'last_name', 'age', 'email', 'department', 'phone', 'status'];
// foreach ($fields as $f) {
//     if (!isset($_POST[$f])) {
//         echo json_encode(['success' => false, 'error' => 'Missing field: ' . $f]);
//         exit();
//     }
// }
// $id = intval($_POST['id']);
// $first_name = $_POST['first_name'];
// $last_name = $_POST['last_name'];
// $age = $_POST['age'];
// $email = $_POST['email'];
// $department = $_POST['department'];
// $phone = $_POST['phone'];
// $status = $_POST['status'];
// include_once __DIR__ . '/../connection.php';
// $stmt = $conn->prepare('UPDATE users SET first_name=?, last_name=?, age=?, email=?, department=?, phone=?, status=? WHERE id=? AND role="Admin"');
// $stmt->bind_param('sssssssi', $first_name, $last_name, $age, $email, $department, $phone, $status, $id);
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

$id         = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$first_name = trim((string)($_POST['first_name'] ?? ''));
$last_name  = trim((string)($_POST['last_name'] ?? ''));
$age        = isset($_POST['age']) && $_POST['age'] !== '' ? (int)$_POST['age'] : null;
$email      = trim((string)($_POST['email'] ?? ''));
$department = trim((string)($_POST['department'] ?? ''));
$phone      = trim((string)($_POST['phone'] ?? ''));
$status     = trim((string)($_POST['status'] ?? ''));

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid id']);
    exit;
}

try {
    $st = $pdo->prepare("
        UPDATE public.users
           SET first_name = :first_name,
               last_name  = :last_name,
               age        = :age,
               email      = :email,
               department = :department,
               phone      = :phone,
               status     = :status
         WHERE id = :id
    ");
    $st->execute([
        ':first_name' => $first_name,
        ':last_name'  => $last_name,
        ':age'        => $age,
        ':email'      => $email,
        ':department' => $department,
        ':phone'      => $phone,
        ':status'     => $status,
        ':id'         => $id,
    ]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($e->getCode() === '23505') {
        echo json_encode(['success' => false, 'error' => 'Email already exists']);
        exit;
    }
    error_log('[UPDATE ADMIN] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
