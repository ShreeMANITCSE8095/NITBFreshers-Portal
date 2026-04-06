<?php
// attendance_handler.php
session_start();
header('Content-Type: application/json');

include 'db_connection.php';
$conn = db_connect();

// Check if user is logged in
if (!isset($_SESSION['scholarNo'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_attendance') {
    $scholarNo = $_SESSION['scholarNo'];
    $subject = $_POST['subject'] ?? '';
    $status = $_POST['status'] ?? '';
    
    // --- TIMEZONE CORRECTION (Server + 13h 30m) ---
    // User stated DB time is off, so we align backend validation to their "Real" time
    $serverTime = new DateTime();
    $serverTime->add(new DateInterval('PT13H30M'));
    $adjustedToday = $serverTime->format('Y-m-d');

    // Get date from POST, default to adjusted today
    $logDate = $_POST['date'] ?? $adjustedToday;
    
    // VALIDATION RANGE
    $minDate = '2025-12-22';
    
    // Validate: Cannot mark before start date AND Cannot mark future dates
    if ($logDate > $adjustedToday) {
         echo json_encode(['success' => false, 'message' => 'Cannot mark future dates']);
         exit;
    }
    if ($logDate < $minDate) {
         echo json_encode(['success' => false, 'message' => 'Cannot modify before 22 Dec 2025']);
         exit;
    }

    if (empty($subject) || empty($status)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }

    // 1. Insert or Update Log
    $stmt = $conn->prepare("INSERT INTO attendance_logs (scholar_no, log_date, subject_name, status) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)");
    $stmt->bind_param("ssss", $scholarNo, $logDate, $subject, $status);
    
    if ($stmt->execute()) {
        // 2. Recalculate Stats (Aggregated across ALL dates)
        // Count Present
        $stmtP = $conn->prepare("SELECT COUNT(*) as count FROM attendance_logs WHERE scholar_no = ? AND subject_name = ? AND status = 'Present'");
        $stmtP->bind_param("ss", $scholarNo, $subject);
        $stmtP->execute();
        $present = $stmtP->get_result()->fetch_assoc()['count'];

        // Count Total (Present + Absent) - Ignore Cancelled
        $stmtT = $conn->prepare("SELECT COUNT(*) as count FROM attendance_logs WHERE scholar_no = ? AND subject_name = ? AND status IN ('Present', 'Absent')");
        $stmtT->bind_param("ss", $scholarNo, $subject);
        $stmtT->execute();
        $total = $stmtT->get_result()->fetch_assoc()['count'];

        $percent = 0;
        $message = "Updated";

        if ($total > 0) {
            $percent = round(($present / $total) * 100, 1);
            
            if ($percent < 75) {
                // Formula: (P + x) / (T + x) >= 0.75
                $needed = ceil((0.75 * $total - $present) / 0.25);
                $message = "Attend next $needed classes";
            } else {
                // Formula: P / (T + x) >= 0.75
                $canMiss = floor(($present - 0.75 * $total) / 0.75);
                $message = "Can miss $canMiss classes";
            }
        }

        echo json_encode([
            'success' => true, 
            'percent' => $percent, 
            'message' => $message
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    $stmt->close();
}
$conn->close();
?>