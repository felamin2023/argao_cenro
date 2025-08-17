<?php
session_start();
require_once __DIR__ . '/../../backend/connection.php';

header('Content-Type: application/json');

function jsonResponse($success, $message, $data = [])
{
    die(json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]));
}

try {

    if (!isset($_SESSION['user_id'])) {
        jsonResponse(false, 'Unauthorized access');
    }

    $user_id = $_SESSION['user_id'];



    $who = filter_input(INPUT_POST, 'who', FILTER_SANITIZE_STRING);
    $what = filter_input(INPUT_POST, 'what', FILTER_SANITIZE_STRING);
    $where = filter_input(INPUT_POST, 'where', FILTER_SANITIZE_STRING);
    $when = filter_input(INPUT_POST, 'when', FILTER_SANITIZE_STRING);
    $why = filter_input(INPUT_POST, 'why', FILTER_SANITIZE_STRING);
    $contact = filter_input(INPUT_POST, 'contact', FILTER_SANITIZE_STRING);
    $category = filter_input(INPUT_POST, 'categories', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);

    if (
        empty($who) || empty($what) || empty($where) || empty($when) || empty($why) || empty($contact) || empty($category) || empty($description)
    ) {
        jsonResponse(false, 'All fields are required');
    }

    $contact = preg_replace('/[^0-9]/', '', $contact);
    if (!preg_match('/^09[0-9]{9}$/', $contact)) {
        jsonResponse(false, 'Please enter a valid 11-digit contact number starting with 09');
    }


    $upload_dir = __DIR__ . '/../../upload/user/reportincidents/';
    $uploaded_files = [];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_files = 5;


    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            jsonResponse(false, 'Failed to create upload directory');
        }
    }


    if (!isset($_FILES['photos']) || empty($_FILES['photos']['name'][0])) {
        jsonResponse(false, 'Please upload at least one photo');
    }


    $file_count = count($_FILES['photos']['name']);
    if ($file_count > $max_files) {
        jsonResponse(false, "Maximum $max_files photos allowed");
    }


    for ($i = 0; $i < $file_count; $i++) {

        if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }


        $tmp_name = $_FILES['photos']['tmp_name'][$i];
        $file_size = $_FILES['photos']['size'][$i];
        $detected_type = mime_content_type($tmp_name);

        if (!in_array($detected_type, $allowed_types)) {
            continue;
        }


        if ($file_size > 5 * 1024 * 1024) {
            continue;
        }


        $file_extension = strtolower(pathinfo($_FILES['photos']['name'][$i], PATHINFO_EXTENSION));
        $micro = str_replace('.', '', microtime(true));
        $rand = bin2hex(random_bytes(3));
        $photo_new_name = 'incident_' . $micro . '_' . $rand . '_' . ($i + 1) . '.' . $file_extension;
        $photo_path = $upload_dir . $photo_new_name;


        if (move_uploaded_file($tmp_name, $photo_path)) {
            $uploaded_files[] = $photo_new_name;
        }
    }

    if (empty($uploaded_files)) {
        jsonResponse(false, 'No valid photos were uploaded. Please upload JPG, PNG, GIF, or WEBP files (max 5MB each)');
    }


    $photos_json = json_encode($uploaded_files);


    $stmt = $conn->prepare("INSERT INTO incident_report 
        (user_id, who, what, `where`, `when`, why, contact_no, photos, category, description, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");

    if (!$stmt) {
        foreach ($uploaded_files as $file) {
            @unlink($upload_dir . $file);
        }
        jsonResponse(false, 'Database preparation failed: ' . $conn->error);
    }

    $stmt->bind_param(
        "isssssssss",
        $user_id,
        $who,
        $what,
        $where,
        $when,
        $why,
        $contact,
        $photos_json,
        $category,
        $description
    );

    if ($stmt->execute()) {
        jsonResponse(true, 'Incident report submitted successfully!');
    } else {
        foreach ($uploaded_files as $file) {
            @unlink($upload_dir . $file);
        }
        jsonResponse(false, 'Database error: ' . $stmt->error);
    }
} catch (Exception $e) {

    if (!empty($uploaded_files)) {
        foreach ($uploaded_files as $file) {
            @unlink($upload_dir . $file);
        }
    }
    jsonResponse(false, 'An unexpected error occurred: ' . $e->getMessage());
}
