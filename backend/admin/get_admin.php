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
// if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
//     echo json_encode(['success' => false, 'error' => 'Invalid ID']);
//     exit();
// }
// $id = intval($_POST['id']);
// include_once __DIR__ . '/../connection.php';
// $stmt = $conn->prepare('SELECT first_name, last_name, age, email, department, phone, status FROM users WHERE id = ? AND role = "Admin"');
// $stmt->bind_param('i', $id);
// $stmt->execute();
// $stmt->bind_result($first_name, $last_name, $age, $email, $department, $phone, $status);
// if ($stmt->fetch()) {
//     echo json_encode([
//         'success' => true,
//         'data' => [
//             'first_name' => $first_name,
//             'last_name' => $last_name,
//             'age' => $age,
//             'email' => $email,
//             'department' => $department,
//             'phone' => $phone,
//             'status' => $status
//         ]
//     ]);
// } else {
//     echo json_encode(['success' => false, 'error' => 'User not found']);
// }
// $stmt->close();
// $conn->close();

declare(strict_types=1);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

session_start();
require_once __DIR__ . '/../connection.php';

header('Content-Type: application/json');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid id']);
    exit;
}

try {
    $st = $pdo->prepare("
        SELECT id, user_id, first_name, last_name, age, email, department, phone, status
        FROM public.users
        WHERE id = :id
        LIMIT 1
    ");
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Not found']);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $row]);
} catch (Throwable $e) {
    error_log('[GET ADMIN] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
