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
// $fields = ['first_name', 'last_name', 'age', 'email', 'department', 'password', 'phone', 'status'];
// foreach ($fields as $f) {
//     if (!isset($_POST[$f]) || $_POST[$f] === '') {
//         echo json_encode(['success' => false, 'error' => 'Missing field: ' . $f]);
//         exit();
//     }
// }
// $first_name = $_POST['first_name'];
// $last_name = $_POST['last_name'];
// $age = $_POST['age'];
// $email = $_POST['email'];
// $department = $_POST['department'];
// $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
// $phone = $_POST['phone'];
// $status = $_POST['status'];
// $role = 'Admin';
// include_once __DIR__ . '/../connection.php';

// $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
// $stmt->bind_param('s', $email);
// $stmt->execute();
// $stmt->store_result();
// if ($stmt->num_rows > 0) {
//     echo json_encode(['success' => false, 'error' => 'Email already exists.']);
//     $stmt->close();
//     $conn->close();
//     exit();
// }
// $stmt->close();
// $stmt = $conn->prepare('INSERT INTO users (first_name, last_name, age, email, department, password, phone, status, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
// $stmt->bind_param('ssissssss', $first_name, $last_name, $age, $email, $department, $password, $phone, $status, $role);
// $success = $stmt->execute();
// $stmt->close();
// $conn->close();
// if ($success) {
//     echo json_encode(['success' => true]);
// } else {
//     echo json_encode(['success' => false, 'error' => 'Add failed']);
// }

declare(strict_types=1);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

session_start();
require_once __DIR__ . '/../connection.php';
header('Content-Type: application/json');

// Minimal validation
$first_name = trim((string)($_POST['first_name'] ?? ''));
$last_name  = trim((string)($_POST['last_name'] ?? ''));
$age        = isset($_POST['age']) && $_POST['age'] !== '' ? (int)$_POST['age'] : null;
$email      = trim((string)($_POST['email'] ?? ''));
$department = trim((string)($_POST['department'] ?? ''));
$password   = (string)($_POST['password'] ?? '');
$phone      = trim((string)($_POST['phone'] ?? ''));
$status     = trim((string)($_POST['status'] ?? 'Pending'));

// Basic checks
if ($first_name === '' || $last_name === '' || $email === '' || $password === '' || $department === '') {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Generate UUID v4 for user_id if your table doesn’t have default
function uuidv4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

try {
    $pdo->beginTransaction();

    $st = $pdo->prepare("
        INSERT INTO public.users (user_id, image, first_name, last_name, age, email, role, department, password, phone, status)
        VALUES (:user_id, NULL, :first_name, :last_name, :age, :email, :role, :department, :password, :phone, :status)
        RETURNING id
    ");
    $params = [
        ':user_id'    => uuidv4(),
        ':first_name' => $first_name,
        ':last_name'  => $last_name,
        ':age'        => $age,
        ':email'      => $email,
        ':role'       => 'admin', // store lowercase
        ':department' => $department,
        ':password'   => password_hash($password, PASSWORD_DEFAULT),
        ':phone'      => $phone,
        ':status'     => $status,
    ];
    $st->execute($params);
    $newId = (int)$st->fetchColumn();

    $pdo->commit();
    echo json_encode(['success' => true, 'id' => $newId]);
} catch (PDOException $e) {
    $pdo->rollBack();
    // Unique violation (email) -> 23505
    if ($e->getCode() === '23505') {
        echo json_encode(['success' => false, 'error' => 'Email already exists']);
        exit;
    }
    error_log('[ADD ADMIN] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('[ADD ADMIN] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
