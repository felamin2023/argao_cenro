<?php

session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'user') {
    echo json_encode(['success' => false, 'errors' => ['Not authorized. Please log in as a user.']]);
    exit();
}

require_once __DIR__ . '/../../backend/connection.php';

function uploadFile($file)
{

    $uploadDir = dirname(__DIR__, 2) . '/upload/user/requestwildlife/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
    // Use a unique filename: fieldname_timestamp_random
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
        'file_7',
        'file_8a',
        'file_8b',
        'file_9'
    ];
    $uploadedFiles = [];
    $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png'];
    foreach ($fileFields as $field) {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            $uploadedFiles[$field] = null;
            if ($field === 'file_1') {
                $errors[] = "Application form is required.";
            }
        } else {
            $fileType = $_FILES[$field]['type'];
            if (!in_array($fileType, $allowedTypes)) {
                $errors[] = "Only PDF, DOC, DOCX, JPG, and PNG files are allowed for $field.";
            } elseif ($_FILES[$field]['size'] > 5242880) {
                $errors[] = "File size for $field must be less than 5MB.";
            } else {
                // Add field_name to file array for unique naming
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
        // Insert with status_updated_by set to the current user
        $stmt = $conn->prepare("INSERT INTO wildlife_permits (
            user_id, first_name, middle_name, last_name,
            application_form, sec_cda_registration, scientific_expertise, financial_plan,
            facility_design, prior_clearance, vicinity_map,
            proof_of_purchase, deed_of_donation, inspection_report, status_updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param(
            "isssssssssssssi",
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
            $uploadedFiles['file_7'],
            $uploadedFiles['file_8a'],
            $uploadedFiles['file_8b'],
            $uploadedFiles['file_9'],
            $userId
        );

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Wildlife permit application submitted successfully!']);
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
