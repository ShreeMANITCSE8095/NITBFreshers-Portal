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

// Function to get OS details from User-Agent
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

// Function to handle Traffic Redirection based on Roll No
function check_and_perform_redirection($rollNo) {
    $REDIRECT_CONFIG_FILE = 'redirection_config.json';
    
    if (file_exists($REDIRECT_CONFIG_FILE)) {
        $configData = json_decode(file_get_contents($REDIRECT_CONFIG_FILE), true);
        
        $status = $configData['status'] ?? 'OFF';
        $urlAE = $configData['url_ae'] ?? '';
        $urlFJ = $configData['url_fj'] ?? '';
        
        // Only redirect if Admin has enabled it AND we have a valid Roll No
        if ($status === 'ON' && !empty($rollNo)) {
            $cleanRoll = trim($rollNo);
            if (strlen($cleanRoll) >= 3) {
                $sectionChar = strtoupper($cleanRoll[2]);
                
                $stSections = ['A', 'B', 'C', 'D', 'E'];
                $mtSections = ['F', 'G', 'H', 'I', 'J'];

                // Check specifically for Section E bug (if numeric 'E' issue persists, this covers it)
                if (in_array($sectionChar, $stSections) || strpos(strtoupper($cleanRoll), 'E') !== false) {
                    if (!empty($urlAE)) {
                        header("Location: " . $urlAE);
                        exit;
                    }
                } elseif (in_array($sectionChar, $mtSections)) {
                    if (!empty($urlFJ)) {
                        header("Location: " . $urlFJ);
                        exit;
                    }
                }
            }
        }
    }
}

// Initialize variables
$error = "";

// Check if user is already logged in via cookie
if (isset($_COOKIE['portal_auth'])) {
    $token = $_COOKIE['portal_auth'];
    $conn = db_connect(); // Use database connection
    $stmt = $conn->prepare("SELECT st.*, s.name, s.roll_no, s.semester FROM session_tokens st JOIN students s ON st.scholar_no = s.scholar_no WHERE st.token = ? LIMIT 1");
    
    if (!$stmt) {
         $stmt = $conn->prepare("SELECT * FROM session_tokens WHERE token = ? LIMIT 1");
    }
    
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
        
        // --- CHECK REDIRECTION ---
        check_and_perform_redirection($row['roll_no']);
        
        header('Location: dashboard.php');
        exit;
    }
}

// Handle form submission (Manual login)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Validate Scholar Number format
    if (
        preg_match('/^2511[1-9]01[0-1][0-5][0-9]{2}$/', $username) ||
        preg_match('/^25404011[0-9]{3}$/', $username) ||
        preg_match('/^254160111[0-9]{2}$/', $username)
    ) {
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
                $os = get_client_os(); 
                $ip = get_client_ip();
                $log_stmt = $conn->prepare("INSERT INTO log_entries (scholar_no, event_type, ip_address, os) VALUES (?, 'Login', ?, ?)");
                $log_stmt->bind_param("sss", $username, $ip, $os);
                $log_stmt->execute();

                // Set session variables
                $_SESSION['scholarNo'] = $username;
                $_SESSION['name'] = $row['name'];
                $_SESSION['rollNo'] = $row['roll_no'];
                $_SESSION['semester'] = $row['semester'];
                $_SESSION['from_index'] = true;

                // --- CHECK REDIRECTION ---
                check_and_perform_redirection($row['roll_no']);

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
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class', // Config preserved, but class removed from HTML tag
        }
    </script>
    
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-4006646080655252" crossorigin="anonymous"></script>

    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>

<body class="bg-slate-50 min-h-screen flex flex-col justify-center items-center p-4 text-slate-900">

    <div class="mb-8 text-center">
        <div class="bg-white p-3 rounded-2xl shadow-sm inline-block mb-4 border border-slate-200">
            <img src="./images/logo.png" alt="Institute Logo" class="h-16 w-auto object-contain">
        </div>
        <h1 class="text-2xl font-bold text-slate-900">MANIT Bhopal</h1>
        <p class="text-slate-500 text-sm font-medium mt-1">Unofficial Study Portal</p>
    </div>

    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
        <div class="p-8">
            <div class="text-center mb-8">
                <h2 class="text-xl font-bold text-slate-900">Student Login</h2>
                <p class="text-xs text-blue-700 mt-2 bg-blue-50 border border-blue-100 py-2 px-4 rounded-lg inline-block font-medium">
                    First time? Use your Scholar No. as Password.
                </p>
            </div>

            <!-- Error Display Block (Centered) -->
            <?php if ($error): ?>
                <div class="mb-6 p-4 rounded-lg bg-red-50 border border-red-100 flex flex-col items-center justify-center text-center gap-2">
                    <i data-lucide="alert-circle" class="w-6 h-6 text-red-500"></i>
                    <div>
                        <h3 class="text-sm font-semibold text-red-700">Login Failed</h3>
                        <p class="text-xs text-red-600 mt-1"><?php echo htmlspecialchars($error); ?></p>
                        <?php if (strpos($error, 'Invalid credentials') !== false || strpos($error, 'No user found') !== false): ?>
                            <a href="forget_password.php" class="text-xs text-red-700 underline mt-2 block hover:text-red-900 font-medium">Forgot Password?</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form action="index.php" method="post" class="space-y-5">
                <div>
                    <label for="username" class="block text-sm font-medium text-slate-700 mb-1">Scholar Number</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i data-lucide="user" class="h-5 w-5 text-slate-400"></i>
                        </div>
                        <input type="text" name="username" id="username" placeholder="e.g. 251110100" required 
                            class="pl-10 w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-blue-600 transition-all text-slate-900 font-medium placeholder-slate-400">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i data-lucide="lock" class="h-5 w-5 text-slate-400"></i>
                        </div>
                        <input type="password" name="password" id="password" placeholder="••••••••" required 
                            class="pl-10 w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-blue-600 transition-all text-slate-900 font-medium placeholder-slate-400">
                    </div>
                </div>

                <div class="flex items-center">
                    <input id="remember_me" name="remember_me" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-slate-300 bg-white rounded cursor-pointer">
                    <label for="remember_me" class="ml-2 block text-sm text-slate-600 cursor-pointer select-none">
                        Keep me logged in for 7 days
                    </label>
                </div>

                <button type="submit" class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition-colors shadow-lg shadow-blue-200 flex justify-center items-center gap-2">
                    <span>Login</span>
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </form>
        </div>
    </div>

    <p class="mt-8 text-xs text-center text-slate-500 max-w-sm leading-relaxed">
        Disclaimer: Scholar No. is used solely for portal functionality and improvements. This is a student-run initiative.
    </p>

    <script>
        lucide.createIcons();
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
                    (event.ctrlKey && event.shiftKey && event.key === "I") || // Ctrl+Shift+I
                    (event.ctrlKey && event.key === "u") // View Source
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
                const threshold = 160; 
                const widthThreshold = window.outerWidth - window.innerWidth > threshold;
                const heightThreshold = window.outerHeight - window.innerHeight > threshold;

                if (widthThreshold || heightThreshold) {
                    window.close(); // Attempt to close tab
                    location.reload(); // Fallback
                }
            };
            // setInterval(detectDevTools, 1000); // Uncomment for aggressive checking
        }

        blockRestrictedActions();
    </script>

</body>
</html>