<?php
session_start();

// Check if the 'group' parameter is set in the URL
if (isset($_GET['group'])) {
    // Sanitize the input to prevent directory traversal
    $group = basename($_GET['group']);
    
    // Define the directory path
    $directory = "study_material/" . $group;
    
    // Check if the directory exists
    if (is_dir($directory)) {
        // Get all folders within the directory
        $folders = array_filter(glob($directory . '/*'), 'is_dir');
        
        // Map folder paths to folder names (subjects)
        $subjects = array_map(function($folder) {
            return basename($folder);
        }, $folders);
        
        // Return subjects as JSON
        echo json_encode($subjects);
    } else {
        // Return an empty array if the directory does not exist
        echo json_encode([]);
    }
} else {
    // Return an empty array if the 'group' parameter is not provided
    echo json_encode([]);
}
?>