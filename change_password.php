<?php
session_start();
include 'db_connection.php'; // Include the database connection file

// Ensure the user is logged in
if (!isset($_SESSION['scholarNo'])) {
    header('Location: index.php');
    exit;
}

$message = "";

// Handle form submission for changing the password
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $scholarNo = $_POST['scholarNo'];
    $oldPassword = $_POST['oldPassword'];
    $newPassword = $_POST['newPassword'];
    $confirmPassword = $_POST['confirmPassword'];

    // Validate that the passwords match
    if ($newPassword != $confirmPassword) {
        $message = "New passwords do not match. Please try again.";
    } else {
        // Check if the old password is correct
        $conn = db_connect(); // Database connection
        $stmt = $conn->prepare("SELECT password FROM students WHERE scholar_no = ?");
        $stmt->bind_param("s", $scholarNo);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($storedPassword);
            $stmt->fetch();
            
            

            // Directly compare the old password with the stored password (since it's stored as plain text)
            if ($oldPassword === $storedPassword) {
                // Update the password in the database with the new plain-text password
                $updateStmt = $conn->prepare("UPDATE students SET password = ? WHERE scholar_no = ?");
                $updateStmt->bind_param("ss", $newPassword, $scholarNo);

                // Check for success
                if ($updateStmt->execute()) {
                    $message = "Password changed successfully!";
                } else {
                    $message = "Error updating password. Please try again.";
                }
            } else {
                $message = "Old password is incorrect.";
            }
        } else {
            $message = "Scholar No. not found.";
        }
        $stmt->close();
        $conn->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <divtitle>Change Password</title>
        <link rel="stylesheet" href="styles.css">
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

        /* Form Section */
        .form-container {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .form-container h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        .form-container label {
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
        }

        .form-container input {
            width: 100%;
            padding: 10px;
            margin: 10px 0 15px 0;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        .form-container button {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            width: 100%;
        }

        .form-container button:hover {
            background-color: #0056b3;
        }

        /* Feedback Message */
        .message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
        }

        /* Back Button */
        .back-button {
            background-color: #007bff;
            border: none;
            color: white;
            padding: 10px 20px;
            text-align: center;
            cursor: pointer;
            border-radius: 5px;
            margin-top: 10px;
            width: 100%;
        }

        .back-button:hover {
            background-color: #0056b3;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="title">Maulana Azad National Institute of Technology</div>
        <div class="subtitle">Change Password</div>
    </div>
    <div class="content">
        <div class="form-container">
            <h2>Change Your Password</h2>

            <?php if ($message) : ?>
                <div class="message <?php echo (strpos($message, 'success') !== false) ? 'success' : ''; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <label for="scholarNo">Scholar No:</label>
                <input type="text" name="scholarNo" id="scholarNo" value="<?php echo $_SESSION['scholarNo']; ?>" readonly required>

                <label for="oldPassword">Old Password:</label>
                <input type="password" name="oldPassword" id="oldPassword" required>

                <label for="newPassword">New Password:</label>
                <input type="password" name="newPassword" id="newPassword" required>

                <label for="confirmPassword">Confirm New Password:</label>
                <input type="password" name="confirmPassword" id="confirmPassword" required>

                <button type="submit">Change Password</button>
            </form>

            <button class="back-button" onclick="window.history.back();">Back</button>
        </div>
    </div>
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
