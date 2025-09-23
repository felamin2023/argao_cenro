<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'user') {
    echo json_encode(['success' => false, 'errors' => ['Not authorized. Please log in as a user.']]);
    exit();
}

require_once __DIR__ . '/../../backend/connection.php';

function uploadFile($file)
{
    $uploadDir = dirname(__DIR__, 2) . '/upload/user/requesttreecut/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fieldName = isset($file['field_name']) ? $file['field_name'] : 'file';
    $uniqueId = bin2hex(random_bytes(5));
    $fileName = $fieldName . '_' . time() . '_' . $uniqueId . '.' . $fileExt;
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

    if (empty($firstName) || empty($lastName)) {
        $errors[] = "First name and last name are required.";
    }

    $fileFields = [
        'file_1',
        'file_2',
        'file_3',
        'file_4',
        'file_5',
        'file_6',
        'file_7a',
        'file_7b',
        'file_8',
        'file_9',
        'file_10a',
        'file_10b'
    ];

    $uploadedFiles = [];
    $allowedTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg',
        'image/png'
    ];

    foreach ($fileFields as $field) {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            $uploadedFiles[$field] = null;
            if ($field === 'file_1') {
                $errors[] = "Certificate of Verification is required.";
            }
        } else {
            $fileType = $_FILES[$field]['type'];
            if (!in_array($fileType, $allowedTypes)) {
                $errors[] = "Only PDF, DOC, DOCX, JPG, and PNG files are allowed for $field.";
            } elseif ($_FILES[$field]['size'] > 5242880) {
                $errors[] = "File size for $field must be less than 5MB.";
            } else {
                $_FILES[$field]['field_name'] = $field;
                $uploadedFiles[$field] = uploadFile($_FILES[$field]);
                if (!$uploadedFiles[$field]) {
                    $errors[] = "Failed to upload file for $field. Please try again.";
                }
            }
        }
    }

    if (empty($errors)) {
        $userId = $_SESSION['user_id'];

        $stmt = $conn->prepare("INSERT INTO tree_cutting_permits (
            user_id, first_name, middle_name, last_name,
            cov_certificate, payment_receipt, memorandum_report, tally_sheets,
            geo_tagged_photos, sworn_statement, conveyance_copy, drivers_license_copy,
            purchase_order, letter_request, tally_sheets_table, tree_charting, status_approved_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)");

        $stmt->bind_param(
            "isssssssssssssss",
            $userId,
            $firstName,
            $middleName,
            $lastName,
            $uploadedFiles['file_1'],
            $uploadedFiles['file_2'],
            $uploadedFiles['file_3'],
            $uploadedFiles['file_4'],
            $uploadedFiles['file_5'],
            $uploadedFiles['file_6'],
            $uploadedFiles['file_7a'],
            $uploadedFiles['file_7b'],
            $uploadedFiles['file_8'],
            $uploadedFiles['file_9'],
            $uploadedFiles['file_10a'],
            $uploadedFiles['file_10b']
        );

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Tree cutting permit application submitted successfully!']);
            $stmt->close();
            $conn->close();
            exit();
        } else {
            $errors[] = "Failed to submit application. Please try again.";
        }

        $stmt->close();
    }

    echo json_encode(['success' => false, 'errors' => $errors]);
    $conn->close();
    exit();
}

$conn->close();
