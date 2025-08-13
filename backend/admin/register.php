<?php
session_start();
header('Content-Type: application/json');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['email_verified']) || !$_SESSION['email_verified']) {
        $errors['email'] = 'Please verify your email first.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';


        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email';
        }
        if (!preg_match('/^09\\d{9}$/', $phone)) {
            $errors['phone'] = 'Must be 11 digits (start with 09)';
        }
        if (empty($department)) {
            $errors['department'] = 'Required';
        }
        if (strlen($password) < 6) {
            $errors['password'] = 'Min 6 characters';
        }
        if ($password !== $confirm_password) {
            $errors['confirm_password'] = 'Passwords do not match';
        }


        if (empty($errors)) {
            include '../../backend/connection.php';
            $stmt = $conn->prepare("SELECT id, status FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            $existing_id = null;
            $existing_status = null;
            $stmt->bind_result($existing_id, $existing_status);
            if ($stmt->num_rows > 0) {
                $stmt->fetch();
                if (strtolower($existing_status) === 'rejected') {

                    $stmt->close();
                    $update = $conn->prepare("UPDATE users SET phone=?, role=?, department=?, password=?, status='Pending' WHERE id=?");
                    $role = 'Admin';
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $update->bind_param("ssssi", $phone, $role, $department, $password_hash, $existing_id);
                    if ($update->execute()) {
                        unset($_SESSION['email_verified'], $_SESSION['email_otp'], $_SESSION['email_otp_to']);
                        echo json_encode(['success' => true, 'updated' => true]);
                        $update->close();
                        $conn->close();
                        exit();
                    } else {
                        $errors['form'] = 'Registration failed: ' . $update->error;
                    }
                    $update->close();
                } else {
                    $errors['email'] = 'Email already exists.';
                    $stmt->close();
                }
                $conn->close();
            } else {
                $stmt->close();

                $role = 'Admin';
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (email, phone, role, department, password) VALUES (?, ?, ?, ?, ?)";
                $insert = $conn->prepare($sql);
                $insert->bind_param("sssss", $email, $phone, $role, $department, $password_hash);
                if ($insert->execute()) {
                    unset($_SESSION['email_verified'], $_SESSION['email_otp'], $_SESSION['email_otp_to']);
                    echo json_encode(['success' => true]);
                    $insert->close();
                    $conn->close();
                    exit();
                } else {
                    $errors['form'] = 'Registration failed: ' . $insert->error;
                }
                $insert->close();
                $conn->close();
            }
        }
    }
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}
echo json_encode(['success' => false, 'errors' => ['form' => 'Invalid request.']]);
