<?php

session_start();

include 'db_connection.php'; // Adjust the file path as needed



// Ensure the user is logged in

if (!isset($_SESSION['scholarNo'])) {

    header('Location: index.php');

    exit;

}



// Function to get the client's IP address

function get_client_ip()

{

    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {

        return $_SERVER['HTTP_CLIENT_IP'];

    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {

        return $_SERVER['HTTP_X_FORWARDED_FOR'];

    } else {

        return $_SERVER['REMOTE_ADDR'];

    }

}



// Function to get OS details from User-Agent

function get_os()

{

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



// Function to log events to the database

function log_event($scholarNo, $eventType, $details)

{

    $ipAddress = get_client_ip();

    $os = get_os();

    $conn = db_connect(); // Database connection (replace this with your actual DB connection logic)

    $stmt = $conn->prepare("INSERT INTO log_entries (scholar_no, event_type, event_time, ip_address, os, details) VALUES (?, ?, NOW(), ?, ?, ?)");

    $stmt->bind_param("sssss", $scholarNo, $eventType, $ipAddress, $os, $details);

    $stmt->execute();

}



// Validate and process the parameters

if (isset($_GET['file'], $_GET['subject'], $_GET['group'], $_GET['folder'])) {

    // Decode the URL-encoded spaces and other special characters

    $file = urldecode($_GET['file']); // Decode file name to get spaces properly

    $subject = urldecode($_GET['subject']);

    $group = urldecode($_GET['group']);

    $folder = urldecode($_GET['folder']);



    // Replace any '+' with spaces in the file and folder names

    $file = str_replace('+', ' ', $file);

    $folder = str_replace('+', ' ', $folder);

    $subject = str_replace('+', ' ', $subject);



    // Base directory for study materials

    $baseDir = str_replace('\\', '/', realpath(__DIR__ . "/study_material"));



    // Construct the full file path with the decoded values

    $constructedPath = $baseDir . '/' . $group . '/' . $subject . '/' . $folder . '/' . $file;



    // Check if the file exists

    if (file_exists($constructedPath)) {

        $filePath = $constructedPath; // Use constructed path directly

        $fileUrl = str_replace($_SERVER['DOCUMENT_ROOT'], '', $filePath); // Convert to relative URL

        $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));





        log_event($_SESSION['scholarNo'], 'Access',  $group . '/' . $subject . '/' . $file);

    } else {

        echo ("File not found at: " . var_export($constructedPath, true));

        die("Error: File not found or access denied.");

    }

} else {

    die("Error: Missing required parameters.");

}





function getUrlFromFile($filePath)

{

    $lines = @file($filePath, FILE_IGNORE_NEW_LINES);

    if (!$lines) {

        return null;

    }

    foreach ($lines as $line) {

        if (stripos($line, 'URL=') !== false) {

            return trim(substr($line, 4));

        }

    }

    return null;

}



// Function to convert Dropbox URLs for direct file access

function convertDropboxLink($url)

{

    if (strpos($url, 'dropbox.com') !== false) {

        return str_replace('dl=0', 'raw=1', $url);

    }

    return $url;

}



// Handle .url files

$isUrlFile = isset($fileExtension) && $fileExtension === 'url';

if ($isUrlFile) {

    $url = getUrlFromFile($filePath);

    if ($url) {

        $fileUrl = convertDropboxLink($url);

    } else {

        die("Error: Invalid .url file.");

    }

}





?>



<!DOCTYPE html>

<html lang="en">



<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Resource Viewer</title>

    <link rel="stylesheet" href="pdfjs/web/viewer.css">

    <script src="pdfjs/build/pdf.js"></script>

    <script src="pdfjs/web/viewer.js"></script>

    <link rel="stylesheet" href="styles.css">

    <!-- <script async type='module' src='https://interfaces.zapier.com/assets/web-components/zapier-interfaces/zapier-interfaces.esm.js'></script> -->



    <style>

        /* Basic reset */

        * {

            margin: 0;

            padding: 0;

            box-sizing: border-box;

        }



        body {

            font-family: 'Poppins', sans-serif;

            background-color: #f4f4f4;

            height: 100%;

            display: flex;

            flex-direction: column;

        }



        html,

        body {

            margin: 0;

            padding: 0;

            width: 100%;

            height: 100%;

            overflow: hidden;

            /* Prevent scrolling */

        }



        /* Header Section */

        .header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            background-color: #004085;

            color: white;

            padding: 15px 30px;

            width: 100%;

            position: fixed;

            top: 0;

            left: 0;

            right: 0;

            z-index: 10;

        }





        .header .title {

            font-size: 1.2rem;

            font-weight: bold;

        }



        /* Content Section */

        .content {

            margin-top: 50px;

            padding: 10px;

            flex-grow: 1;

        }



        .resource-panel {

            display: flex;

            align-items: center;

            justify-content: space-between;

            background-color: white;

            padding: 20px;

            border-radius: 5px;

            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);

            margin-bottom: 10px;

        }



        .resource-panel h2 {

            margin-right: auto;

        }



        .back-button {

            background-color: #007bff;

            border: none;

            color: white;

            padding: 10px 20px;

            text-align: center;

            cursor: pointer;

            border-radius: 5px;

            margin-left: 20px;

        }



        .back-button:hover {

            background-color: #0056b3;

        }



        .viewer {

            flex-grow: 1;

            width: 100%;

            height: 100%;

            border-radius: 10px;

            overflow: hidden;

        }



        iframe {

            width: 100%;

            height: 100%;

            border: none;

        }



        /* Chatbot bubble adjustments */

        zapier-interfaces-chatbot-embed {

            position: fixed;

            bottom: 20px;

            right: 20px;

            z-index: 100;

        }



        @media (max-width: 768px) {

            .header {

                display: none;

            }



            .content {

                margin-top: 0;

            }



            .header .title {

                font-size: 1.5rem;

            }



            .header .subtitle {

                font-size: 1rem;

            }



            .mobile-only {

                display: none;

                /* Hidden by default */

            }



            @media (max-width: 768px) {

                .mobile-only {

                    display: block;

                    /* Show only on mobile devices */

                }

            }

        }

    </style>

</head>



<body>



    <!-- Header Section -->

    <div class="header">

        <div class="title">Maulana Azad National Institute of Technology</div>

        <div class="subtitle">Resource Viewer</div>

    </div>



    <!-- Content Section -->

    <div class="content">

        <div class="resource-panel">

            <h3>Viewing: <?php echo htmlspecialchars(basename($file)); ?></h3>

            <button class="back-button" onclick="window.history.back();">Back</button>

        </div>



        <!-- PDF Viewer Section -->

        <div class="viewer">

            <?php

            // Check if device is mobile or desktop

            $isMobile = (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/Mobile|Android|iPhone|iPad/i', $_SERVER['HTTP_USER_AGENT']));



            // File URL for iframe

            $fileUrlEncoded = 'https://' . $_SERVER['HTTP_HOST'] . '/userlogin/study_material/' . $group . '/' . $subject . '/' . $folder . '/' . $file;



            // Decode any URL-encoded parts of the path

            $fileUrlDecoded = urldecode($fileUrlEncoded);

            $fileUrlDecoded = str_replace('+', ' ', $fileUrlDecoded);  // Replace '+' with space for additional safety



            // Check file type and display appropriate viewer

            if ($isUrlFile) { ?>

                <?php

                $isMobile = (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/Mobile|Android|iPhone|iPad/i', $_SERVER['HTTP_USER_AGENT']));

                if ($isMobile) {

                    echo '<p class="mobile-only">For mobile users, click on open to download the PDF.</p>';

                }

                ?>





                <iframe src="<?php echo htmlspecialchars($fileUrl); ?>" class="regular-viewer"></iframe>

                <?php

            } elseif ($fileExtension === 'pdf') {

                if ($isMobile) { ?>

                    <iframe src="https://drive.google.com/viewerng/viewer?embedded=true&url=<?php echo urlencode($fileUrlEncoded); ?>" title="Resource Viewer" style="width: 100%; height: 100% ;"></iframe>

                <?php } else { ?>

                    <iframe id="pdfViewer" src="web/viewer.html?file=<?php echo urlencode($fileUrlEncoded); ?>" title="Resource Viewer" style="width: 100%; height: 100%; border: none;"></iframe>

                <?php

                }

            } elseif (in_array($fileExtension, ['ppt', 'pptx'])) { ?>

                <iframe src="https://drive.google.com/viewerng/viewer?embedded=true&url=<?php echo urlencode($fileUrlEncoded); ?>" title="Resource Viewer" style="width: 100%; height: 100%;"></iframe>

            <?php } else { ?>

                <iframe src="<?php echo htmlspecialchars($fileUrlEncoded); ?>" class="regular-viewer"></iframe>

            <?php } ?>

        </div>

    </div>



    <!-- AI Chatbot -->

    <!-- <zapier-interfaces-chatbot-embed 

    is-popup='true' 

    chatbot-id='cm585k93c003j87o0y8o4dso3' 

    id="ai-chatbot">

</zapier-interfaces-chatbot-embed> -->



<script>

    document.addEventListener('DOMContentLoaded', function () {

        const chatbot = document.getElementById('ai-chatbot');



        // Monitor for when the chatbot is clicked or activated

        chatbot.addEventListener('click', function () {

            // Log the event via an AJAX request

            fetch('log_chatroom_access.php', {

                method: 'POST',

                headers: { 'Content-Type': 'application/json' },

                body: JSON.stringify({ source: 'RV' })

            })

            .then(response => response.json())

            .then(data => {

                if (data.success) {

                    console.log('Chatroom access logged successfully.');

                } else {

                    console.error('Error logging chatroom access:', data.error);

                }

            })

            .catch(error => {

                console.error('AJAX error:', error);

            });

        });

    });

</script>



    <script>

        function blockRestrictedActions() {

            // Prevent right-click context menu

            document.addEventListener("contextmenu", (event) => {

                event.preventDefault();

            });



            // Prevent specific key combinations

            document.addEventListener("keydown", (event) => {

                if (

                    event.key === "F12" || // F12 key

                    (event.ctrlKey && event.key === "s") || // Ctrl+S

                    (event.ctrlKey && event.shiftKey && event.key === "I") // Ctrl+Shift+I

                ) {

                    event.preventDefault();

                }

            });



            // Prevent drag-and-drop

            document.addEventListener("dragstart", (event) => {

                event.preventDefault();

            });



            document.addEventListener("drop", (event) => {

                event.preventDefault();

            });



            // Detect and close Developer Tools

            const detectDevTools = () => {

                const threshold = 160; // Approximate size to detect DevTools

                const widthThreshold = window.outerWidth - window.innerWidth > threshold;

                const heightThreshold = window.outerHeight - window.innerHeight > threshold;



                if (widthThreshold || heightThreshold) {

                    // Attempt to close the window

                    window.close();



                    // Optionally redirect or reload the page

                    location.reload();

                }

            };





        }



        // Call the function to activate restrictions

        blockRestrictedActions();

    </script>

</body>



</html>