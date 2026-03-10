<?php
session_start();
include 'db_connection.php'; // Include your DB connection file

$conn = db_connect(); // Connect to the database
$ip_address = $_SERVER['REMOTE_ADDR']; // Get system IP
$device_os = php_uname(); // Get device OS
$timestamp = date('Y-m-d H:i:s'); // Get current timestamp

$message = ""; // To display messages back to the user

// Handle form submission for Option 1, 2, and 3
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['option']) && ($_POST['option'] == "case1" || $_POST['option'] == "case2")) {
        $scholar_no = $_POST['scholar_no'];

        // Check if scholar number exists
        $stmt = $conn->prepare("SELECT * FROM students WHERE scholar_no = ?");
        $stmt->bind_param("s", $scholar_no);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Scholar number exists; Update password to scholar number
            $stmt = $conn->prepare("UPDATE students SET password = ? WHERE scholar_no = ?");
            $stmt->bind_param("ss", $scholar_no, $scholar_no);
            $stmt->execute();

            // Log the reset action
            $stmt = $conn->prepare("INSERT INTO password_reset_logs (scholar_no, reset_time, ip_address, device_os) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $scholar_no, $timestamp, $ip_address, $device_os);
            $stmt->execute();

            $message = "Password reset successfully. Your new password is your scholar number.";
        } else {
            $message = "Scholar number not found in the system.";
        }
    } elseif (isset($_POST['option']) && $_POST['option'] == "case3") {
        $scholar_no = $_POST['scholar_no'];

        // Add scholar number to newregistration table
        $stmt = $conn->prepare("INSERT INTO newregistration (scholar_no, request_time) VALUES (?, ?)");
        $stmt->bind_param("ss", $scholar_no, $timestamp);
        if ($stmt->execute()) {
            $message = "Your request for registration has been submitted.";
        } else {
            $message = "Failed to submit your request. Scholar number might already exist.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Assistance Utility</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f0f8ff;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .container {
            background: #fff;
            width: 400px;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        h1 {
            font-size: 20px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 20px;
            color: #004085;
        }

        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 16px;
            color: #495057;
        }

        .panel {
            display: none;
            margin-bottom: 10px;
        }

        .input-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .input-group input {
            width: 95%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            margin-bottom: 10px;
        }

        button {
            background-color: #007bff;
            color: #fff;
            border: none;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }

        button:hover {
            background-color: #0056b3;
        }

        .message {
            color: green;
            font-size: 14px;
            text-align: center;
            margin-top: 10px;
        }

        .warning {
            color: red;
            font-size: 12px;
            text-align: center;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }

        .logo {
            max-width: 100px;
            height: auto;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="logo-container" style="text-align: center; margin-bottom: 20px;">
            <img src="images/logo.png" alt="Logo" class="logo" style="max-width: 100px; height: auto;">
            <h1 style="margin: 10px 0 0; color: #004085; font-size: 22px; font-weight: 600;">Login Assistance Utility</h1>
        </div>

        <h1>Forget Password</h1>

        <select id="optionSelector" onchange="showPanel()">
            <option value="">Select an issue...</option>
            <option value="case1">I forgot my password and unable to login</option>
            <option value="case2">I don't know my password because someone else changed it</option>
            <option value="case3">Scholar Number not registered in the system</option>
        </select>



        <div id="panel1" class="panel">
            <form method="POST">
                <div class="input-group">
                    <label for="scholar_no1">Enter your Scholar Number:</label>
                    <input type="text" id="scholar_no1" name="scholar_no" required>
                </div>
                <input type="hidden" name="option" value="case1">
                <button type="submit">Reset Password</button>
            </form>
            <p class="warning">Warning: System IP and device information are being recorded for security purposes.</p>
        </div>

        <div id="panel2" class="panel">
            <form method="POST">
                <div class="input-group">
                    <label for="scholar_no2">Enter your Scholar Number:</label>
                    <input type="text" id="scholar_no2" name="scholar_no" required>
                </div>
                <input type="hidden" name="option" value="case2">
                <button type="submit">Reset Password</button>
            </form>
            <p class="warning">Warning: System IP and device information are being recorded for security purposes.</p>
        </div>

        <div id="panel3" class="panel">
            <form method="POST">
                <div class="input-group">
                    <label for="scholar_no3">Enter your Scholar Number:</label>
                    <input type="text" id="scholar_no3" name="scholar_no" required>
                </div>
                <input type="hidden" name="option" value="case3">
                <button type="submit">Submit Request</button>
            </form>
        </div>
        <button class="go-back-btn" onclick="goBack()">Go Back</button>
        <?php if (!empty($message)): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
    </div>

    <script>
        function showPanel() {
            let option = document.getElementById("optionSelector").value;
            document.querySelectorAll('.panel').forEach(panel => panel.style.display = 'none');
            if (option === "case1") {
                document.getElementById("panel1").style.display = 'block';
            } else if (option === "case2") {
                document.getElementById("panel2").style.display = 'block';
            } else if (option === "case3") {
                document.getElementById("panel3").style.display = 'block';
            }
        }

        function goBack() {
            window.location.href = 'index.php'; // Redirects to index.php
        }
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