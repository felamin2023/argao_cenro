<?php
// Ensure no output is sent before headers
ob_start();

// Set JSON header first
header('Content-Type: application/json');

// Start session and validate
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'errors' => ['Session expired. Please log in again.']]));
}

if (strtolower($_SESSION['role']) !== 'user') {
    http_response_code(403);
    die(json_encode(['success' => false, 'errors' => ['Not authorized. Please log in as a user.']]));
}

require_once __DIR__ . '/../../backend/connection.php';

function uploadFile($file, $fieldName)
{
    $uploadDir = dirname(__DIR__, 2) . '/upload/user/requestchainsaw/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

    if (!in_array($fileExt, $allowedExtensions)) {
        return false;
    }

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
    $response = ['success' => false, 'errors' => []];

    try {
        // Get form data
        $firstName = trim($_POST['first_name'] ?? '');
        $middleName = trim($_POST['middle_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $permitType = trim($_POST['permit_type'] ?? 'new');

        // Validate basic fields
        if (empty($firstName) || empty($lastName)) {
            $errors[] = "First name and last name are required.";
        }

        if (!in_array($permitType, ['new', 'renewal'])) {
            $errors[] = "Invalid permit type.";
        }

        // Process file uploads
        $fileFields = ['1a', '1b', '2', '3', '4', '5', '6', '7', '8', '9'];
        $uploadedFiles = [];

        foreach ($fileFields as $field) {
            if (!isset($_FILES[$field])) {
                $uploadedFiles[$field] = null;
                continue;
            }

            $file = $_FILES[$field];
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                $uploadedFiles[$field] = null;
                continue;
            }

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = "Error uploading file for field {$field}";
                continue;
            }

            $uploadedFileName = uploadFile($file, $field);
            if (!$uploadedFileName) {
                $errors[] = "Failed to upload file for field {$field} or invalid file type";
                continue;
            }

            $uploadedFiles[$field] = $uploadedFileName;
        }

        // Validate required files based on permit type
        $requiredFiles = ($permitType === 'new') ?
            ['1a', '1b', '2', '3', '4', '5', '6', '7', '8'] :
            ['1a', '1b', '2', '3', '4', '5', '6', '7', '8', '9'];

        foreach ($requiredFiles as $field) {
            if (empty($uploadedFiles[$field])) {
                $errors[] = "Document " . strtoupper($field) . " is required.";
            }
        }

        if (empty($errors)) {
            $userId = $_SESSION['user_id'];
            $tableName = ($permitType === 'new') ?
                'chainsaw_new_permits' :
                'chainsaw_renewal_permits';

            // Prepare the database insert
            if ($permitType === 'new') {
                $stmt = $conn->prepare("INSERT INTO $tableName (
                    user_id, first_name, middle_name, last_name,
                    cert_terms, cert_sticker, or_payment,
                    staff_work, geo_photos, application_letter,
                    official_receipt, permit_to_sell, business_permit,
                    status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");

                $stmt->bind_param(
                    "issssssssssss",
                    $userId,
                    $firstName,
                    $middleName,
                    $lastName,
                    $uploadedFiles['1a'],
                    $uploadedFiles['1b'],
                    $uploadedFiles['2'],
                    $uploadedFiles['3'],
                    $uploadedFiles['4'],
                    $uploadedFiles['5'],
                    $uploadedFiles['6'],
                    $uploadedFiles['7'],
                    $uploadedFiles['8']
                );
            } else {
                $stmt = $conn->prepare("INSERT INTO $tableName (
                    user_id, first_name, middle_name, last_name,
                    cert_terms, cert_sticker, or_payment,
                    staff_work, geo_photos, application_letter,
                    official_receipt, permit_to_sell, business_permit,
                    old_registration, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");

                $stmt->bind_param(
                    "isssssssssssss",
                    $userId,
                    $firstName,
                    $middleName,
                    $lastName,
                    $uploadedFiles['1a'],
                    $uploadedFiles['1b'],
                    $uploadedFiles['2'],
                    $uploadedFiles['3'],
                    $uploadedFiles['4'],
                    $uploadedFiles['5'],
                    $uploadedFiles['6'],
                    $uploadedFiles['7'],
                    $uploadedFiles['8'],
                    $uploadedFiles['9']
                );
            }

            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Chainsaw registration application submitted successfully!'];
            } else {
                $errors[] = "Database error: " . $stmt->error;
                $response['errors'] = $errors;
            }

            $stmt->close();
        } else {
            $response['errors'] = $errors;
        }
    } catch (Exception $e) {
        $response['errors'] = ['Server error: ' . $e->getMessage()];
    }

    // Ensure no output before this
    ob_end_clean();
    echo json_encode($response);
    exit();
}

// If not a POST request
http_response_code(405);
ob_end_clean();
echo json_encode(['success' => false, 'errors' => ['Invalid request method']]);
exit();
