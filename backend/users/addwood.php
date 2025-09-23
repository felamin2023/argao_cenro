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
    $uploadDir = dirname(__DIR__, 2) . '/upload/user/requestwood/';
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

        // Process file uploads in batches
        $fileFields = [
            'a',
            'b',
            'c',
            'd',
            'e',
            'f',
            'g',
            'h',
            'i',
            'j',
            'k',
            'l',
            'o2',
            'o3',
            'o4',
            'o5',
            'o6',
            'o7',
            'o8',
            'q1',
            'q2',
            'q3',
            'q4'
        ];

        $uploadedFiles = [];
        $processedFiles = 0;
        $maxFilesPerBatch = 10; // Process files in batches to avoid limits

        foreach (array_chunk($fileFields, $maxFilesPerBatch) as $batch) {
            foreach ($batch as $field) {
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
                $processedFiles++;
            }
        }

        // Validate required files based on permit type
        $requiredFiles = ($permitType === 'new') ?
            ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'o2', 'o3', 'o4', 'o5', 'o7'] :
            ['a', 'b', 'c', 'd', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'o2', 'o3', 'o4', 'o5', 'o6', 'o7', 'o8'];

        foreach ($requiredFiles as $field) {
            if (empty($uploadedFiles[$field])) {
                $errors[] = "Document " . strtoupper($field) . " is required.";
            }
        }

        if (empty($errors)) {
            $userId = $_SESSION['user_id'];
            $tableName = ($permitType === 'new') ?
                'wood_processing_new_permits' :
                'wood_processing_renewal_permits';

            // Prepare the database insert
            if ($permitType === 'new') {
                $stmt = $conn->prepare("INSERT INTO $tableName (
                    user_id, first_name, middle_name, last_name,
                    application_form, application_fee, registration_certificate,
                    authorization_doc, business_plan, business_permit,
                    ecc_certificate, citizenship_proof, machine_ownership,
                    gis_map, hotspot_certification, sustainable_sources,
                    supply_contracts, tree_inventory, inventory_data,
                    validation_report, ctp_ptpr, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");

                $stmt->bind_param(
                    "issssssssssssssssssss",
                    $userId,
                    $firstName,
                    $middleName,
                    $lastName,
                    $uploadedFiles['a'],
                    $uploadedFiles['b'],
                    $uploadedFiles['c'],
                    $uploadedFiles['d'],
                    $uploadedFiles['e'],
                    $uploadedFiles['f'],
                    $uploadedFiles['g'],
                    $uploadedFiles['h'],
                    $uploadedFiles['i'],
                    $uploadedFiles['j'],
                    $uploadedFiles['k'],
                    $uploadedFiles['l'],
                    $uploadedFiles['o2'],
                    $uploadedFiles['o3'],
                    $uploadedFiles['o4'],
                    $uploadedFiles['o5'],
                    $uploadedFiles['o7']
                );
                // In the renewal permit section of addwood.php, modify the INSERT statement:
            } else {
                $stmt = $conn->prepare("INSERT INTO $tableName (
        user_id, first_name, middle_name, last_name,
        application_form, application_fee, registration_certificate,
        authorization_doc, business_permit, ecc_certificate,
        citizenship_proof, machine_ownership, gis_map,
        hotspot_certification, sustainable_sources, supply_contracts,
        tree_inventory, inventory_data, validation_report,
        tenure_instrument, ctp_ptpr, monthly_reports,
        importer_registration, importer_supply_contracts,
        proof_of_importation, importer_monthly_reports,
        status, created_at
    ) VALUES (
        ?, ?, ?, ?, 
        ?, ?, ?, ?, 
        ?, ?, ?, ?, 
        ?, ?, ?, ?, 
        ?, ?, ?, ?, 
        ?, ?, ?, ?, 
        ?, ?, 'pending', NOW()
    )");

                $stmt->bind_param(
                    "isssssssssssssssssssssssss",
                    $userId,
                    $firstName,
                    $middleName,
                    $lastName,
                    $uploadedFiles['a'],   // application_form
                    $uploadedFiles['b'],   // application_fee
                    $uploadedFiles['c'],   // registration_certificate
                    $uploadedFiles['d'],   // authorization_doc
                    $uploadedFiles['f'],   // business_permit
                    $uploadedFiles['g'],   // ecc_certificate
                    $uploadedFiles['h'],   // citizenship_proof
                    $uploadedFiles['i'],   // machine_ownership
                    $uploadedFiles['j'],   // gis_map
                    $uploadedFiles['k'],   // hotspot_certification
                    $uploadedFiles['l'],   // sustainable_sources
                    $uploadedFiles['o2'],  // supply_contracts
                    $uploadedFiles['o3'],  // tree_inventory
                    $uploadedFiles['o4'],  // inventory_data
                    $uploadedFiles['o5'],  // validation_report
                    $uploadedFiles['o6'],  // tenure_instrument
                    $uploadedFiles['o7'],  // ctp_ptpr
                    $uploadedFiles['o8'],  // monthly_reports
                    $uploadedFiles['q1'],  // importer_registration
                    $uploadedFiles['q2'],  // importer_supply_contracts
                    $uploadedFiles['q3'],  // proof_of_importation
                    $uploadedFiles['q4']   // importer_monthly_reports
                );
            }

            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Wood processing plant permit application submitted successfully!'];
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
