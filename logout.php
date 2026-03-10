<?php
session_start();
include 'db_connection.php';

if (isset($_SESSION['scholarNo'])) {
    $scholar_no = $_SESSION['scholarNo'];

    // Get client IP and OS
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $os = php_uname('s') . " " . php_uname('r');

    // Connect to the database
    $conn = db_connect();

    // Log the logout event
    $log_stmt = $conn->prepare("INSERT INTO log_entries (scholar_no, event_type, ip_address, os) VALUES (?, 'Logout', ?, ?)");
    $log_stmt->bind_param("sss", $scholar_no, $ip, $os);
    $log_stmt->execute();
    $log_stmt->close();

    // Remove the session token from the database
    if (isset($_COOKIE['portal_auth'])) {
        $token = $_COOKIE['portal_auth'];
        $delete_stmt = $conn->prepare("DELETE FROM session_tokens WHERE token = ?");
        $delete_stmt->bind_param("s", $token);
        $delete_stmt->execute();
        $delete_stmt->close();

        // Remove the authentication cookie
        setcookie('portal_auth', '', time() - 3600, "/"); // Expire immediately
    }

    // Close the database connection
    $conn->close();

    // Destroy session
    session_unset();
    session_destroy();
}

// Redirect to login page
header('Location: index.php');
exit;
?>
