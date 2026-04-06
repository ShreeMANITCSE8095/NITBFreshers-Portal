<?php
session_start();
include 'db_connection.php';

header('Content-Type: application/json');

// 1. Auth Check
if (!isset($_SESSION['scholarNo'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$scholarNo = $_SESSION['scholarNo'];
$name = $_SESSION['name'];

// 2. Check Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

// 3. Check Inputs
if (!isset($_FILES['file']) || !isset($_POST['subject']) || !isset($_POST['group'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing fields']);
    exit;
}

$file = $_FILES['file'];
$subject = trim($_POST['subject']);
$group = trim($_POST['group']);
$folder = isset($_POST['folder']) ? trim($_POST['folder']) : null; // NEW: Capture folder

// 4. Validate File Size (Max 10MB)
$maxSize = 10 * 1024 * 1024; // 10MB in bytes
if ($file['size'] > $maxSize) {
    echo json_encode(['status' => 'error', 'message' => 'File too large. Max 10MB allowed.']);
    exit;
}

// 5. Validate File Type (Security)
$allowedExts = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'ppt', 'pptx', 'txt'];
$fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($fileExt, $allowedExts)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Only PDF, Docs, and Images allowed.']);
    exit;
}

$conn = db_connect();

// 6. Check Daily Limit (Max 10 files per day)
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) FROM uploads WHERE scholar_no = ? AND DATE(upload_date) = ?");
$stmt->bind_param("ss", $scholarNo, $today);
$stmt->execute();
$stmt->bind_result($count);
$stmt->fetch();
$stmt->close();

if ($count >= 10) {
    echo json_encode(['status' => 'error', 'message' => 'Daily upload limit (10 files) reached.']);
    $conn->close();
    exit;
}

// 7. Process Upload
$uploadDir = 'uploads/pending/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Create unique name to prevent overwriting
$storedName = time() . '_' . uniqid() . '.' . $fileExt;
$destination = $uploadDir . $storedName;

if (move_uploaded_file($file['tmp_name'], $destination)) {
    // 8. Insert into Database (Updated query to include folder_name)
    $stmt = $conn->prepare("INSERT INTO uploads (scholar_no, uploader_name, file_name, stored_name, subject, group_name, folder_name, file_size, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("sssssssi", $scholarNo, $name, $file['name'], $storedName, $subject, $group, $folder, $file['size']);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'File uploaded successfully! Sent for approval.']);
    } else {
        // Rollback: delete file if DB insert fails
        unlink($destination);
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file.']);
}

$conn->close();
?>