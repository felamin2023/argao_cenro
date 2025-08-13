<?php

header('Content-Type: application/json');
require_once '../connection.php';

$response = ['success' => false, 'message' => 'Unknown error'];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');

    $file_fields = [
        'application_form',
        'sec_cda_dti_registration',
        'scientific_expertise',
        'financial_plan',
        'facility_design',
        'community_clearance',
        'vicinity_map',
        'proof_of_purchase',
        'deed_of_donation',
        'inspection_report'
    ];

    $file_names = [];
    foreach ($file_fields as $field) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $file_names[$field] = $_FILES[$field]['name'];
        } else {
            $file_names[$field] = null;
        }
    }

    error_log("First Name: $first_name");
    error_log("Middle Name: $middle_name");
    error_log("Last Name: $last_name");
    foreach ($file_fields as $field) {
        error_log("$field: " . ($file_names[$field] ?? 'NULL'));
    }
    echo json_encode(['success' => true, 'message' => 'Printed to console.']);
    exit();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}
