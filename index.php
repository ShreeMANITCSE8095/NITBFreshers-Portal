<?php
session_start();
include 'db_connection.php'; // Include database connection file

// Function to generate a random token
function generate_token() {
    return bin2hex(random_bytes(32)); // Generate a 64-character secure token
}

// Function to get the client's IP address
function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]; // Get the first forwarded IP
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Initialize variables
$error = "";

// Check if user is already logged in via cookie
if (isset($_COOKIE['portal_auth'])) {
    $token = $_COOKIE['portal_auth'];
    $conn = db_connect(); // Use database connection
    $stmt = $conn->prepare("SELECT * FROM session_tokens WHERE token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        // Token found, fetch user details
        $row = $result->fetch_assoc();
        $_SESSION['scholarNo'] = $row['scholar_no'];
        $_SESSION['name'] = $row['name'];
        $_SESSION['rollNo'] = $row['roll_no'];
        $_SESSION['semester'] = $row['semester'];
        $_SESSION['from_index'] = true;
        header('Location: dashboard.php');
        exit;
    }
}

// Handle form submission (Manual login)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Validate Scholar Number format
    if (preg_match('/^2411[1-9]01[0-1][0-5][0-9]{2}$/', $username) || preg_match('/^24404011[0-9]{3}$/', $username)) {
        $conn = db_connect(); // Use database connection
        $stmt = $conn->prepare("SELECT * FROM students WHERE scholar_no = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();

            // Compare password (assuming it's stored as plain text)
            if ($password === $row['password']) {
                session_regenerate_id(true); // Prevent session fixation attacks

                // Generate and store session token
                $token = generate_token();
                $expiry = date('Y-m-d H:i:s', time() + (7 * 24 * 60 * 60)); // Convert to DATETIME format

                $insert_stmt = $conn->prepare("INSERT INTO session_tokens (token, scholar_no, expiry) VALUES (?, ?, ?)");
                $insert_stmt->bind_param("sss", $token, $username, $expiry);
                
                $insert_stmt->execute();

                // Set secure HttpOnly cookie for authentication
                setcookie('portal_auth', $token, time() + (7 * 24 * 60 * 60), "/", "", true, true);

                // Log login event
                $os = php_uname('s') . " " . php_uname('r'); // Get OS info
                $ip = get_client_ip();
                $log_stmt = $conn->prepare("INSERT INTO log_entries (scholar_no, event_type, ip_address, os) VALUES (?, 'Login', ?, ?)");
                $log_stmt->bind_param("sss", $username, $ip, $os);
                $log_stmt->execute();

                // Set session variables and redirect
                $_SESSION['scholarNo'] = $username;
                $_SESSION['name'] = $row['name'];
                $_SESSION['rollNo'] = $row['roll_no'];
                $_SESSION['semester'] = $row['semester'];
                $_SESSION['from_index'] = true;
                header('Location: dashboard.php');
                exit;
            } else {
                $error = "Invalid credentials.";
            }
        } else {
            $error = "No user found with the given Scholar Number.";
        }
    } else {
        $error = "Invalid Scholar Number format.";
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Unofficial MANIT Bhopal Study Material</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-4006646080655252"
        crossorigin="anonymous"></script>
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

        .header-pc {
            display: none;
        }

        .container {
            background: #fff;
            width: 400px;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            text-align: center;
        }

        .header {
            margin-bottom: 20px;
        }

        .header img {
            max-width: 100px;
            height: auto;
        }

        .header h1 {
            margin: 10px 0;
            color: #004085;
            font-size: 22px;
            font-weight: 600;
        }

        h2 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #004085;
        }

        p {
            font-size: 14px;
            color: #495057;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        input {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }

        button {
            background-color: #007bff;
            color: #fff;
            border: none;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background-color: #0056b3;
        }

        .error {
            color: red;
            font-size: 14px;
            margin-top: 10px;
        }

        @media screen and (min-width: 768px) {
            .header-pc {
                display: flex;
                justify-content: center;
                align-items: center;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 60px;
                background-color: #004085;
                color: white;
                font-size: 20px;
                font-weight: 600;
            }

            .header-pc img {
                height: 40px;
                margin-right: 10px;
            }

            .container {
                margin-top: 80px;
            }

            .header {
                display: none;
            }
        }

        .disclaimer {
            font-size: 12px;
            text-align: center;
            color: #6c757d;
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .disclaimer {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="header-pc">
        <img src="images/logo.png" alt="Institute Logo">
        <span>MANIT Bhopal - Unofficial Study Portal</span>
    </div>
    <div class="container">
        <div class="header">
            <img src="images/logo.png" alt="Institute Logo">
            <h1>MANIT Bhopal - Unofficial Study Portal</h1>
        </div>
        <h2>Student Login</h2>
        <p><b>For first time login, your username and password is your Scholar Number.</b></p>
        <form action="index.php" method="post">
            <input type="text" name="username" placeholder="Scholar No." required>
            <input type="password" name="password" placeholder="Password" required>
            <label>
                <input type="checkbox" name="remember_me"> Keep me logged in for 7 days
            </label>
            <button type="submit">Login</button>
            <!-- <p style="color: red;">Chatbot facility has been resumed in the portal. Feedback portal will be opened soon.</p> -->
            <?php
            if (isset($error) && $error) {
                echo '<p class="error">' . htmlspecialchars($error) . '</p>';
                echo '<p><a href="forget_password.php">I am unable to login</a></p>';
            }
            ?>
        </form>

        <footer class="footer-disclaimer">
            <p class="disclaimer">
                Disclaimer: Scholar No. is used for portal functionality and improvements. <br>Managed by Devansh Soni (B.Tech 2nd Year).
            </p>
        </footer>
        <!-- <p>© 2024 MANIT Bhopal - Unofficial Website</p>
        <p style="font-size: 12px;">Disclaimer: Scholar No. is used for portal functionality and improvements. Managed by Devansh Soni (B.Tech 2nd Year).</p> -->
    </div>


    <script src="disable_rightclick.js"></script>
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