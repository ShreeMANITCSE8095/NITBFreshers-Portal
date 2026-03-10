<?php
// Include your database connection file
include 'db_connection.php';

// Function to get the client's IP address
function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Function to get OS details from User-Agent
function get_os() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $os_platform = "Unknown OS Platform";

    // OS detection
    $os_array = array(
        '/windows nt 10.0/i' => 'Windows 10',
        '/windows nt 6.3/i' => 'Windows 8.1',
        '/windows nt 6.2/i' => 'Windows 8',
        '/windows nt 6.1/i' => 'Windows 7',
        '/windows nt 6.0/i' => 'Windows Vista',
        '/windows nt 5.1/i' => 'Windows XP',
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

// Define the root path where subjects are stored
$subjectsRootPath = 'study_material'; // Path to the study material directory

// Get the group (MT or ST) and subject from the query parameters
$group = isset($_GET['group']) ? urldecode($_GET['group']) : '';
$subject = isset($_GET['subject']) ? urldecode($_GET['subject']) : '';
$folder = isset($_GET['folder']) ? urldecode($_GET['folder']) : '';

// The path to the group folder (either MT or ST)
$groupFolderPath = $subjectsRootPath . '/' . $group;

// Check if the group folder exists
if (!is_dir($groupFolderPath)) {
    echo json_encode(['error' => 'Group folder does not exist']);
    exit;
}

// If a subject is selected, fetch folders and files within that subject folder
$subjectFolderPath = $groupFolderPath . '/' . $subject;
$folderPath = $subjectFolderPath . '/' . $folder;

// Initialize the response array
$response = [];

// Helper function to recursively fetch files and subfolders
function getFolderContent($folderPath) {
    $content = [
        'files' => [],
        'folders' => []
    ];

    if (is_dir($folderPath)) {
        // Fetch files inside the folder
        $files = array_filter(glob($folderPath . '/*'), 'is_file');
        foreach ($files as $file) {
            $content['files'][] = basename($file);
        }

        // Fetch subfolders inside the folder
        $folders = array_filter(glob($folderPath . '/*'), 'is_dir');
        foreach ($folders as $folder) {
            $content['folders'][] = basename($folder);
        }
    }

    return $content;
}

// Function to log events to the database
function log_event($scholarNo, $eventType, $details) {
    $ipAddress = get_client_ip();
    $os = get_os();
    $conn = db_connect(); // Database connection
    $stmt = $conn->prepare("INSERT INTO log_entries (scholar_no, event_type, event_time, ip_address, os, details) VALUES (?, ?, NOW(), ?, ?, ?)");
    $stmt->bind_param("sssss", $scholarNo, $eventType, $ipAddress, $os, $details);
    $stmt->execute();
}

// Fetch subjects (folders inside the group)
if (!$subject && !$folder) {
    // Fetch all folders (subjects) in the group
    $subjects = array_filter(glob($groupFolderPath . '/*'), 'is_dir');
    $subjectNames = array_map(function($subject) {
        return basename($subject);
    }, $subjects);
    $response['subjects'] = $subjectNames;

    // Log the event
    if (isset($_SESSION['scholarNo'])) {
        log_event($_SESSION['scholarNo'], 'View Subjects', 'Viewed subjects in group: ' . $group);
    }
}

// Fetch folders (subdirectories inside the subject folder)
elseif ($subject && !$folder) {
    if (is_dir($subjectFolderPath)) {
        // Fetch subfolders (folders inside the subject folder)
        $folders = array_filter(glob($subjectFolderPath . '/*'), 'is_dir');
        $folderNames = array_map(function($folder) {
            return basename($folder);
        }, $folders);
        $response['folders'] = $folderNames;

        // Log the event
        if (isset($_SESSION['scholarNo'])) {
            log_event($_SESSION['scholarNo'], 'View Folders', 'Viewed folders in subject: ' . $subject);
        }
    } else {
        $response['error'] = 'Subject folder does not exist';
    }
}

// Fetch files and subfolders within a specific folder
elseif ($folder) {
    if (is_dir($folderPath)) {
        // Fetch files and folders recursively within the selected folder
        $content = getFolderContent($folderPath);

        // Add the fetched content to the response
        if (count($content['files']) > 0) {
            $response['files'] = $content['files'];
        }

        if (count($content['folders']) > 0) {
            $response['folders'] = $content['folders'];
        }

        if (count($content['files']) == 0 && count($content['folders']) == 0) {
            $response['error'] = 'No files or subfolders available in this folder';
        }

        // Log the event
        if (isset($_SESSION['scholarNo'])) {
            log_event($_SESSION['scholarNo'], 'View Folder Content', 'Viewed folder: ' . $folder . ' in subject: ' . $subject);
        }
    } else {
        $response['error'] = 'Folder does not exist';
    }
}

// Return the response in JSON format
header('Content-Type: application/json');
echo json_encode($response);
?>
