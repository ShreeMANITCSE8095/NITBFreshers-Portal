<?php
session_start();
include 'db_connection.php';

// Function to get OS details from User-Agent (Same as resource_viewer.php)
function get_client_os() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $os_platform = "Unknown OS Platform";
    $os_array = array(
        '/windows nt 10.0/i' => 'Windows 10',
        '/windows nt 6.3/i' => 'Windows 8.1',
        '/windows nt 6.2/i' => 'Windows 8',
        '/windows nt 6.1/i' => 'Windows 7',
        '/macintosh|mac os x/i' => 'Mac OS X',
        '/linux/i' => 'Linux',
        '/ubuntu/i' => 'Ubuntu',
        '/iphone/i' => 'iPhone',
        '/ipod/i' => 'iPod',
        '/ipad/i' => 'iPad',
        '/android/i' => 'Android',
        '/blackberry/i' => 'BlackBerry',
        '/webos/i' => 'Mobile',
    );

    foreach ($os_array as $regex => $value) {
        if (preg_match($regex, $user_agent)) {
            $os_platform = $value;
        }
    }
    return $os_platform;
}

if (isset($_SESSION['scholarNo'])) {
    $scholar_no = $_SESSION['scholarNo'];

    // Get client IP and correct Client OS
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $os = get_client_os(); 

    // Connect to the database
    $conn = db_connect();

    // Log the logout event
    $log_stmt = $conn->prepare("INSERT INTO log_entries (scholar_no, event_type, ip_address, os) VALUES (?, 'Logout', ?, ?)");
    $log_stmt->bind_param("sss", $scholar_no, $ip, $os);
    $log_stmt->execute();
    $log_stmt->close();

    // Remove the session token from the database (if using "Remember Me")
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
}

// Destroy session data
session_unset();
session_destroy();

// Redirect to login page
header('Location: index.php');
exit;
?>