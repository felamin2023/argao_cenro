<?php
session_start();
include '../connection.php';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $errors = [];

    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $role = "User";

    if (strlen($username) < 4) {
        $errors['username'] = "Min 4 characters";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email";
    }

    if (!preg_match('/^09\d{9}$/', $phone)) {
        $errors['phone'] = "Must be 11 digits (start with 09)";
    }

    if (strlen($password) < 6) {
        $errors['password'] = "Min 6 characters";
    }

    $checkUser = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $checkUser->bind_param("s", $username);
    $checkUser->execute();
    $checkUser->store_result();

    if ($checkUser->num_rows > 0) {
        $errors['username'] = "Username is already taken";
    }

    $checkUser->close();

    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        $_SESSION['old'] = [
            'username' => !isset($errors['username']) ? $username : '',
            'email' => !isset($errors['email']) ? $email : '',
            'phone' => !isset($errors['phone']) ? $phone : '',
        ];
        header("Location: ../../user/user_register.php");
        exit();
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, email, phone, role, password)
          VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $username, $email, $phone, $role, $password_hash);

    if ($stmt->execute()) {
        header("Location: ../../user/user_register.php?success=1");
        exit();
    } else {
        echo "<script>alert('Registration failed: " . $stmt->error . "'); window.history.back();</script>";
    }

    $stmt->close();
}

$conn->close();
