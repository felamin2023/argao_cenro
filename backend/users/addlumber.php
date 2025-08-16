<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'user') {
    echo json_encode(['success' => false, 'errors' => ['Not authorized. Please log in as a user.']]);
    exit();
}

require_once __DIR__ . '/../../backend/connection.php';

function uploadFile($file, $fieldName)
{
    $uploadDir = dirname(__DIR__, 2) . '/upload/user/requestlumber/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Get file extension from the original name
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Generate unique filename
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
    $permitType = trim($_POST['permit_type'] ?? 'new');

    if (empty($firstName) || empty($lastName)) {
        $errors[] = "First name and last name are required.";
    }

    if (!in_array($permitType, ['new', 'renewal'])) {
        $errors[] = "Invalid permit type.";
    }

    $fileFields = [
        'file_1',
        'file_2',
        'file_3',
        'file_4',
        'file_5',
        'file_6',
        'file_7',
        'file_8',
        'file_9',
        'file_10a',
        'file_10b'
    ];

    $uploadedFiles = [];
    $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

    foreach ($fileFields as $field) {
        if (!isset($_FILES[$field])) {
            $uploadedFiles[$field] = null;
            continue;
        }

        $file = $_FILES[$field];

        // Skip if no file uploaded (unless required)
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            $uploadedFiles[$field] = null;
            continue;
        }

        // Handle upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Upload error for {$field}: " . $file['error'];
            continue;
        }

        // Validate file extension only (like treecut version)
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExt, $allowedExtensions)) {
            $errors[] = "Only PDF, DOC, DOCX, JPG, PNG files are allowed for {$field}.";
            continue;
        }

        if ($file['size'] > 5242880) { // 5MB
            $errors[] = "File too large for {$field}. Maximum 5MB allowed.";
            continue;
        }

        // Upload file
        $uploadedFileName = uploadFile($file, $field);
        if (!$uploadedFileName) {
            $errors[] = "Failed to upload file for {$field}. Please try again.";
            continue;
        }

        $uploadedFiles[$field] = $uploadedFileName;
    }

    // Check required files based on permit type
    $requiredFiles = ($permitType === 'new')
        ? ['file_1', 'file_2', 'file_3', 'file_4', 'file_5', 'file_6', 'file_7', 'file_8', 'file_10a', 'file_10b']
        : ['file_1', 'file_2', 'file_3', 'file_4', 'file_6', 'file_7', 'file_9', 'file_10a', 'file_10b'];

    foreach ($requiredFiles as $field) {
        if (empty($uploadedFiles[$field])) {
            $errors[] = str_replace('_', ' ', $field) . " is required.";
        }
    }

    if (empty($errors)) {
        $userId = $_SESSION['user_id'];
        $tableName = ($permitType === 'new')
            ? 'lumber_dealer_new_permits'
            : 'lumber_dealer_renewal_permits';

        if ($permitType === 'new') {
            $stmt = $conn->prepare("INSERT INTO $tableName (
                user_id, first_name, middle_name, last_name,
                csw_document, geo_tagged_photos, application_form,
                supply_contract, business_plan, mayors_permit,
                registration_certificate, tax_return, or_copy, op_copy,
                status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");

            $stmt->bind_param(
                "isssssssssssss",
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
                $uploadedFiles['file_8'],
                $uploadedFiles['file_10a'],
                $uploadedFiles['file_10b']
            );
        } else {
            $stmt = $conn->prepare("INSERT INTO $tableName (
                user_id, first_name, middle_name, last_name,
                csw_document, geo_tagged_photos, application_form,
                supply_contract, mayors_permit, registration_certificate,
                monthly_reports, or_copy, op_copy,
                status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");

            $stmt->bind_param(
                "issssssssssss",
                $userId,
                $firstName,
                $middleName,
                $lastName,
                $uploadedFiles['file_1'],
                $uploadedFiles['file_2'],
                $uploadedFiles['file_3'],
                $uploadedFiles['file_4'],
                $uploadedFiles['file_6'],
                $uploadedFiles['file_7'],
                $uploadedFiles['file_9'],
                $uploadedFiles['file_10a'],
                $uploadedFiles['file_10b']
            );
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Lumber dealer permit application submitted successfully!']);
        } else {
            $errors[] = "Failed to submit application. Please try again.";
            echo json_encode(['success' => false, 'errors' => $errors]);
        }

        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'errors' => $errors]);
    }

    $conn->close();
    exit();
}

echo json_encode(['success' => false, 'errors' => ['Invalid request method']]);
