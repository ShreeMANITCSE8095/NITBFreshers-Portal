<?php
session_start(); // Start session

// Check if user is logged in
if (!isset($_SESSION['scholarNo'])) {
    header('Location: index.php');
    exit;
}

// Retrieve session data and provide fallback values
$scholarNo = $_SESSION['scholarNo'];
$name = $_SESSION['name'] ?? 'Loading...'; // Fallback to prevent "N/A" issue
$rollNo = $_SESSION['rollNo'] ?? 'Loading...';
$semester = $_SESSION['semester'] ?? 'Loading...';

include 'db_connection.php';
$conn = db_connect();

if ($name === 'Loading...' || $rollNo === 'Loading...' || $semester === 'Loading...') {
    $stmt = $conn->prepare("SELECT name, roll_no, semester FROM students WHERE scholar_no = ?");
    $stmt->bind_param("s", $scholarNo);
    $stmt->execute();
    $stmt->bind_result($name, $rollNo, $semester);
    $stmt->fetch();
    $stmt->close();

    // Update session variables
    $_SESSION['name'] = $name;
    $_SESSION['rollNo'] = $rollNo;
    $_SESSION['semester'] = $semester;
}

$conn->close();



// Determine if user navigated from index.php
$showAlert = isset($_SESSION['from_index']) && $_SESSION['from_index'] === true;
unset($_SESSION['from_index']); // Clear the flag after using it

$bannedUsersFile = 'bannedusers.txt';
$isBanned = false; // Default: user is not banned
$bannedReason = ''; // Default: no reason

if (file_exists($bannedUsersFile)) {
    $fileContents = file($bannedUsersFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($fileContents as $line) {
        $parts = explode(',', $line, 2);
        if (count($parts) < 2) {
            continue; // Skip invalid lines
        }
        list($bannedScholarNo, $reason) = $parts;
        if (trim($bannedScholarNo) === $scholarNo) {
            $isBanned = true;
            $bannedReason = trim($reason);
            break;
        }
    }
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Sharp" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <?php if ($isBanned && $showAlert): ?>
        <script>
            alert("You have been restricted to access the portal.\nReason: <?php echo htmlspecialchars($bannedReason); ?>");
        </script>
    <?php endif; ?>
    <header>
        <div class="logo">
            <img src="./images/logo.png" alt="Logo">
            <h2>MANIT <span class="danger">Bhopal </span>Unofficial Study Portal</h2>
        </div>
        <div class="navbar">

            <a href="#" class="active mat1">
                <span class="material-icons-sharp">dashboard</span>
                <h3>Dashboard</h3>
            </a>
            <?php if (!$isBanned): // Show Chatbot only if not banned 
            ?>
                <a href="ch_info.html">
                    <span class="material-icons-sharp">smart_toy</span>
                    <h3>ChatBot</h3>
                </a>
            <?php endif; ?>
            <a href="change_password.php">
                <span class="material-icons-sharp">password</span>
                <h3>Change Password</h3>
            </a>
            <a href="logout.php">
                <span class="material-icons-sharp">logout</span>
                <h3>Logout</h3>
            </a>
        </div>
        <!-- <div class="theme-toggler">
            <span class="material-icons-sharp active">light_mode</span>
            <span class="material-icons-sharp">dark_mode</span>
        </div> -->
    </header>

    <main>
        <section class="dashboard">
            <div class="aside">
                <br><br><br>
                <!-- Left Side: Student Details -->
                <div class="student-details">
                    <h3>Student Details</h3>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($_SESSION['name'] ?? 'N/A'); ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <strong>Semester:</strong> <?php echo htmlspecialchars($_SESSION['semester'] ?? 'N/A'); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <strong>Scholar No:</strong> <?php echo htmlspecialchars($_SESSION['scholarNo'] ?? 'N/A'); ?>&nbsp;&nbsp;&nbsp;&nbsp;
                        <strong>Roll No:</strong> <?php echo htmlspecialchars($_SESSION['rollNo'] ?? 'N/A'); ?>

                    </p>
                </div>
                <br>
                <!-- Right Side: Announcements -->
                <div class="announcements" style="position: relative; padding-right: 20px;">
                    <h3 style="display:inline">Announcements: </h3>
                    <p style="display:inline"> Access the 100+ Previous Year Papers Bank: <b><a href="pyqs2024/" style="color:red" target="_blank">Click here</a></b></p>          &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;| &nbsp;&nbsp;&nbsp;&nbsp;   Contact Us Feature is now available. <b><a href="https://forms.gle/WV5pkEJAvRW8zgbD9" style="color:blue" target="_blank">Click here</a></b>
                    <!--<p></p>-->
                    <p>MANIT Bhopal now offers 2 new branches for UG Admissions 2025-26. <b><a href="MANIT_UG_2526.pdf">Click here</a></b> to know more.</p>
                    <div id="announcementsContainer"></div>
                    <button onclick="this.parentNode.style.display='none'" style="position: absolute; right: 0; top: 0; background: none; border: none; cursor: pointer; padding: 0 5px;">
                        <span style="font-family: 'Material Icons'; font-size: 20px;">close</span>
                    </button>
                </div>
                <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
            </div>
            <br>
            <div class="container">
                <!-- Adding on my own -->
                <div class="subjects-section">
                    <div class="group-selection">
                        <label for="group">Select Group:</label>
                        <select id="group" onchange="updateSubjects()">
                            <option value="None">-- Select --</option>
                            <option value="MT">MT</option>
                            <option value="ST">ST</option>
                        </select>
                    </div>

                    <div class="subjects-container" id="subjectsContainer">
                        <!-- Subject tiles will appear here -->
                    </div>
                </div>

                <br><br>
                <!-- Subject Panel: The main container that holds all panels -->

                <div class="panel" id="subjectPanel">
                    <h3>Click on subjects to explore its contents.</h3>
                    <div class="subject-list">
                        <!-- Dynamically populated subject tiles -->
                    </div>
                </div>

                <!-- Resource Selection Panel: Shows after subject tile is clicked -->
                <div class="panel" id="resourceSelectionPanel" style="display: none;">
                    <button class="back-button" onclick="goBackToSubjectPanel()">Back</button>
                    <h3>Select a Folder</h3><br>
                    <div id="resourceDisplayContainer" class="resource-buttons-container">
                        <!-- Buttons for folders will be dynamically added here -->
                    </div>
                </div>


                <!-- Folder/File Display Panel: Displays contents of selected folder or files -->
                <div class="panel" id="folderDisplayPanel" style="display: none;">
                    <button class="back-button" onclick="goBackToResourceSelectionPanel()">Back</button>
                    <div id="folderContent"></div>

                </div>
            </div>

            <!-- Group Selection Section -->

        </section>
    </main>




    <script src="dashboard.js"></script>
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