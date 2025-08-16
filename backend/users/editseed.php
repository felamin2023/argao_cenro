<?php
session_start();

// Security check: must be logged in as "user"
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
    $id = intval($_POST['id'] ?? 0);
    $userId = $_SESSION['user_id'];
    $fields = [];
    $params = [];
    $types = '';

    // Check if any fields are being updated
    $dataFieldsChanged = false;

    // Process text fields
    $textFields = [
        'first_name' => 's',
        'middle_name' => 's',
        'last_name' => 's',
        'seedling_name' => 's',
        'quantity' => 'i'
    ];

    foreach ($textFields as $field => $type) {
        if (isset($_POST[$field])) {
            $fields[] = "$field = ?";
            $params[] = $field === 'quantity' ? intval($_POST[$field]) : trim($_POST[$field]);
            $types .= $type;
            $dataFieldsChanged = true;
        }
    }

    // Handle file upload
    if (isset($_FILES['request_letter']) && $_FILES['request_letter']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png'
        ];
        $fileType = $_FILES['request_letter']['type'];

        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = "Only PDF, DOC, DOCX, JPG, and PNG files are allowed.";
        } elseif ($_FILES['request_letter']['size'] > 5242880) {
            $errors[] = "File size must be less than 5MB.";
        } else {
            $requestLetter = uploadFile($_FILES['request_letter']);
            if (!$requestLetter) {
                $errors[] = "Failed to upload request letter. Please try again.";
            } else {
                $fields[] = 'request_letter = ?';
                $params[] = $requestLetter;
                $types .= 's';
                $dataFieldsChanged = true;
            }
        }
    }

    // Set status_updated_by to NULL
    $fields[] = 'status_updated_by = NULL';

    if (empty($id)) {
        $errors[] = "ID is required.";
    }

    if (!$dataFieldsChanged && empty($errors)) {
        $errors[] = "No changes detected.";
    }

    if (empty($errors)) {
        $params[] = $id;
        $params[] = $userId;
        $types .= 'ii';

        $sql = "UPDATE seedling_requests SET " . implode(', ', $fields) . " WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);

        // Only bind parameters if we have them
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Record updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'errors' => ['Failed to update record. Please try again.']]);
        }
        $stmt->close();
        $conn->close();
        exit();
    }

    echo json_encode(['success' => false, 'errors' => $errors]);
    $conn->close();
    exit();
}

$conn->close();
