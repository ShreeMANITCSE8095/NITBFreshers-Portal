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
    <title>Resource Viewer | MANIT Portal</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class', // Enable manual dark mode
        }
    </script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .fade-in { animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        /* Iframe height fix - Adjusted for single header */
        .viewer-container { height: calc(100vh - 100px); }
        
        @media (max-width: 768px) {
             /* Adjust height for mobile where header might be taller due to stacking */
            .viewer-container { height: calc(100vh - 140px); }
        }
    </style>
</head>

<body class="bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 min-h-screen transition-colors duration-200 flex flex-col">

    <!-- Unified Navigation Bar -->
    <nav class="sticky top-0 z-50 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 shadow-sm transition-colors duration-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center h-auto md:h-16 py-2 md:py-0 gap-2">
                
                <!-- Top Row on Mobile: Logo & Controls -->
                <div class="flex justify-between items-center w-full md:w-auto">
                    <div class="flex items-center gap-3">
                        <img src="./images/logo.png" alt="MANIT Logo" class="h-10 w-auto object-contain">
                        <div>
                            <h1 class="font-bold text-lg leading-tight text-slate-800 dark:text-white">MANIT <span class="text-red-500">Bhopal</span></h1>
                            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium md:block hidden">Unofficial Study Portal</p>
                        </div>
                    </div>

                    <!-- Mobile Controls -->
                    <div class="flex items-center md:hidden gap-3">
                         <button onclick="document.getElementById('themeToggle').click()" class="p-2 text-slate-600 dark:text-slate-300">
                            <i data-lucide="sun-moon" class="w-6 h-6"></i>
                        </button>
                        <a href="dashboard.php" class="p-2 text-slate-600 dark:text-slate-300 hover:text-blue-600">
                            <i data-lucide="arrow-left" class="w-6 h-6"></i>
                        </a>
                    </div>
                </div>

                <!-- Center: File Path (Merged Here) -->
                <div class="flex-1 flex justify-center items-center w-full md:w-auto order-last md:order-none bg-slate-100 dark:bg-slate-700/50 md:bg-transparent md:dark:bg-transparent rounded-lg py-1.5 px-3">
                    <div class="text-sm font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-2 truncate max-w-xs sm:max-w-md md:max-w-xl">
                        <i data-lucide="file-text" class="w-4 h-4 text-blue-500 shrink-0"></i>
                        <span class="truncate" title="<?php echo htmlspecialchars($group . '/' . $subject . '/' . basename($file)); ?>">
                            <?php echo htmlspecialchars($group); ?> 
                            <span class="text-slate-400">/</span> 
                            <?php echo htmlspecialchars($subject); ?> 
                            <span class="text-slate-400">/</span> 
                            <span class="text-blue-600 dark:text-blue-400"><?php echo htmlspecialchars(basename($file)); ?></span>
                        </span>
                    </div>
                </div>

                <!-- Desktop Controls -->
                <div class="hidden md:flex items-center space-x-4">
                    <!-- Theme Toggle -->
                    <button id="themeToggle" class="p-2 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                        <span class="hidden dark:block"><i data-lucide="sun" class="w-5 h-5"></i></span>
                        <span class="block dark:hidden"><i data-lucide="moon" class="w-5 h-5"></i></span>
                    </button>

                    <a href="dashboard.php" class="flex items-center px-3 py-2 rounded-md text-sm font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30">
                        <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i> Return to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        
        <!-- Viewer Container -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-slate-200 dark:border-slate-700 overflow-hidden viewer-container relative transition-colors">
            <?php
            // Check if device is mobile or desktop
            $isMobile = (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/Mobile|Android|iPhone|iPad/i', $_SERVER['HTTP_USER_AGENT']));

            // File URL for iframe
            $fileUrlEncoded = 'https://' . $_SERVER['HTTP_HOST'] . '/study_material/' . $group . '/' . $subject . '/' . $folder . '/' . $file;

            // Decode any URL-encoded parts of the path
            $fileUrlDecoded = urldecode($fileUrlEncoded);
            $fileUrlDecoded = str_replace('+', ' ', $fileUrlDecoded);  // Replace '+' with space for additional safety

            // Check file type and display appropriate viewer
            if ($isUrlFile) { ?>
                <?php if ($isMobile): ?>
                    <div class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center">
                        <i data-lucide="smartphone" class="w-12 h-12 text-slate-400 mb-2"></i>
                        <p class="text-slate-600 dark:text-slate-300 mb-4">Mobile Device Detected. Please open directly.</p>
                        <a href="<?php echo htmlspecialchars($fileUrl); ?>" target="_blank" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Open Resource</a>
                    </div>
                <?php endif; ?>
                <iframe src="<?php echo htmlspecialchars($fileUrl); ?>" class="w-full h-full border-none"></iframe>
            <?php } elseif ($fileExtension === 'pdf') {
                if ($isMobile) { ?>
                    <iframe src="https://drive.google.com/viewerng/viewer?embedded=true&url=<?php echo urlencode($fileUrlEncoded); ?>" class="w-full h-full border-none"></iframe>
                <?php } else { ?>
                    <!-- Using PDF.js viewer -->
                    <iframe id="pdfViewer" src="pdfjs/web/viewer.html?file=<?php echo urlencode($fileUrlEncoded); ?>" class="w-full h-full border-none"></iframe>
                <?php }
            } elseif (in_array($fileExtension, ['ppt', 'pptx'])) { ?>
                <iframe src="https://drive.google.com/viewerng/viewer?embedded=true&url=<?php echo urlencode($fileUrlEncoded); ?>" class="w-full h-full border-none"></iframe>
            <?php } else { ?>
                <iframe src="<?php echo htmlspecialchars($fileUrlEncoded); ?>" class="w-full h-full border-none"></iframe>
            <?php } ?>
        </div>
    </main>

    <script>
        // Init Icons
        lucide.createIcons();

        // Dark Mode Logic
        function initTheme() {
            const themeToggleBtn = document.getElementById('themeToggle');
            if (!themeToggleBtn) return;

            // Load preference
            if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }

            // Toggle
            themeToggleBtn.onclick = () => {
                if (document.documentElement.classList.contains('dark')) {
                    document.documentElement.classList.remove('dark');
                    localStorage.setItem('theme', 'light');
                } else {
                    document.documentElement.classList.add('dark');
                    localStorage.setItem('theme', 'dark');
                }
            };
        }
        initTheme();

        // Security Restrictions
        function blockRestrictedActions() {
            document.addEventListener("contextmenu", (e) => e.preventDefault());
            document.addEventListener("keydown", (e) => {
                if (e.key === "F12" || 
                   (e.ctrlKey && e.key === "s") || 
                   (e.ctrlKey && e.shiftKey && e.key === "I") ||
                   (e.ctrlKey && e.key === "U")) {
                    e.preventDefault();
                }
            });
            document.addEventListener("dragstart", (e) => e.preventDefault());
            document.addEventListener("drop", (e) => e.preventDefault());
            
            const detectDevTools = () => {
                const threshold = 160;
                if (window.outerWidth - window.innerWidth > threshold || window.outerHeight - window.innerHeight > threshold) {
                    window.close();
                    location.reload();
                }
            };
            // setInterval(detectDevTools, 2000); // Uncomment if aggressive protection is needed
        }
        blockRestrictedActions();
    </script>
</body>
</html>