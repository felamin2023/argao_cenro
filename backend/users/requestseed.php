<?php

session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'user') {
    echo json_encode(['success' => false, 'errors' => ['Not authorized. Please log in as a user.']]);
    exit();
}

require_once __DIR__ . '/../../backend/connection.php';

function uploadFile($file)
{
    $uploadDir = dirname(__DIR__, 2) . '/upload/user/requestseed/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'request_letter_' . time() . '.' . $fileExt;
    $filePath = $uploadDir . $fileName;
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return $fileName;
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    $firstName = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $seedlingName = trim($_POST['seedling_name'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 0);

    if (empty($firstName) || empty($lastName)) {
        $errors[] = "First name and last name are required.";
    }

    if (empty($seedlingName)) {
        $errors[] = "Seedling name is required.";
    }
    if ($quantity <= 0) {
        $errors[] = "Quantity must be greater than 0.";
    }

    if (!isset($_FILES['request_letter']) || $_FILES['request_letter']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Request letter is required.";
    } else {
        $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png'];
        $fileType = $_FILES['request_letter']['type'];

        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = "Only PDF, DOC, DOCX, JPG, and PNG files are allowed.";
        }

        if ($_FILES['request_letter']['size'] > 5242880) {
            $errors[] = "File size must be less than 5MB.";
        }
    }

    if (empty($errors)) {
        $userId = $_SESSION['user_id'];
        $requestLetter = uploadFile($_FILES['request_letter']);

        if ($requestLetter) {
            // Insert without status_updated_by (it will be NULL by default)
            $stmt = $conn->prepare("INSERT INTO seedling_requests (
                        user_id, first_name, middle_name, last_name, seedling_name, quantity, request_letter
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)");

            $stmt->bind_param("issssis", $userId, $firstName, $middleName, $lastName, $seedlingName, $quantity, $requestLetter);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Seedling request submitted successfully!']);
                $stmt->close();
                $conn->close();
                exit();
            } else {
                $errors[] = "Failed to submit request. Please try again.";
            }

            $stmt->close();
        } else {
            $errors[] = "Failed to upload request letter. Please try again.";
        }
    }
    echo json_encode(['success' => false, 'errors' => $errors]);
    $conn->close();
    exit();
}

$conn->close();
