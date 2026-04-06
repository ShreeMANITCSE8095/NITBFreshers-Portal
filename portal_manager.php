<?php
session_start();
include 'db_connection.php'; 

// --- CONFIGURATION ---
$BASE_DIR = 'study_material';   
$BANNED_FILE = 'bannedusers.txt';
$ALLOWED_FILE = 'allowed_users.txt'; // New: Whitelist File
$REDIRECT_CONFIG_FILE = 'redirection_config.json'; 
$ANNOUNCEMENTS_FILE = 'announcements.json';
$MAINTENANCE_FILE = 'maintenance_config.json';
$FEEDBACK_CONFIG = 'feedback_config.json';
$FEEDBACK_DATA_FILE = 'feedback_data.txt';
$NOTICE_CONFIG_FILE = 'notice_config.json';
$ACCESS_CONFIG_FILE = 'access_config.json'; // New: Access Mode Config

// Initialize Configs if missing
if (!file_exists($MAINTENANCE_FILE)) {
    file_put_contents($MAINTENANCE_FILE, json_encode(['maintenance_mode' => 'OFF', 'reason' => 'Scheduled maintenance in progress.']));
}

// Initialize Access Config (New Feature)
if (!file_exists($ACCESS_CONFIG_FILE)) {
    // Mode: 'OPEN' (Everyone except banned) or 'STRICT' (Only allowed users)
    file_put_contents($ACCESS_CONFIG_FILE, json_encode(['mode' => 'OPEN'])); 
}

// Initialize Feedback Config
if (!file_exists($FEEDBACK_CONFIG)) {
    $defaultSchema = [
        'status' => 'OFF', 
        'schema' => [
            ['id' => uniqid(), 'text' => 'How can we improve?', 'type' => 'text', 'options' => '']
        ]
    ];
    file_put_contents($FEEDBACK_CONFIG, json_encode($defaultSchema));
}

// Initialize Notice Config
if (!file_exists($NOTICE_CONFIG_FILE)) {
    $defaultNotice = [
        'status' => 'OFF',
        'title' => 'Portal Update',
        'message' => 'We have added new features to the dashboard.',
        'id' => uniqid()
    ];
    file_put_contents($NOTICE_CONFIG_FILE, json_encode($defaultNotice));
}

// --- AUTHENTICATION ---
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: portal_manager.php');
    exit;
}

// --- HELPER: FLASH MESSAGE & REDIRECT ---
function redirectWithFlash($message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    
    $url = $_SERVER['PHP_SELF'];
    $queryParams = [];
    if (isset($_GET['path'])) $queryParams['path'] = $_GET['path'];
    if (isset($_GET['chat_user'])) $queryParams['chat_user'] = $_GET['chat_user'];
    
    if (!empty($queryParams)) {
        $url .= '?' . http_build_query($queryParams);
    }
    
    header("Location: " . $url);
    exit;
}

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT id, password_hash FROM admins WHERE username = ?");
    if ($stmt) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password_hash'])) {
                $_SESSION['is_admin'] = true;
                $_SESSION['admin_id'] = $row['id'];
                header('Location: portal_manager.php');
                exit;
            } else { $error = "Invalid credentials."; }
        } else { $error = "Invalid credentials."; }
        $stmt->close();
    }
    $conn->close();
}

// Require Login Interface
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Manager Login | NITBFreshers Portal</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-slate-900 flex items-center justify-center min-h-screen text-slate-100">
        <div class="w-full max-w-md p-8 space-y-6 bg-slate-800 rounded-xl shadow-lg border border-slate-700">
            <div class="text-center">
                <h1 class="text-3xl font-bold text-white">Portal Manager</h1>
                <p class="text-slate-400 mt-2">Sign in to manage the portal</p>
            </div>
            <?php if (isset($error)): ?>
                <div class="bg-red-500/10 border border-red-500 text-red-500 p-3 rounded-lg text-sm text-center">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300">Username</label>
                    <input type="text" name="username" required class="w-full mt-1 px-4 py-2 bg-slate-700 border border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300">Password</label>
                    <input type="password" name="password" required class="w-full mt-1 px-4 py-2 bg-slate-700 border border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-white">
                </div>
                <button type="submit" name="login" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors">Sign In</button>
            </form>
        </div>
    </body>
    </html>
<?php
    exit;
}

// --- ACTIONS HANDLER (POST) ---
$currentPath = isset($_GET['path']) ? $_GET['path'] : $BASE_DIR;
// Security: Prevent directory traversal
if (strpos(realpath($currentPath), realpath($BASE_DIR)) !== 0 || strpos($currentPath, '..') !== false) {
    $currentPath = $BASE_DIR;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Toggle Maintenance Mode
    if (isset($_POST['toggle_maintenance'])) {
        $currentConfig = json_decode(file_get_contents($MAINTENANCE_FILE), true);
        $newStatus = ($currentConfig['maintenance_mode'] === 'ON') ? 'OFF' : 'ON';
        $reason = trim($_POST['reason_text']);
        
        $newConfig = [
            'maintenance_mode' => $newStatus,
            'reason' => !empty($reason) ? $reason : $currentConfig['reason']
        ];
        
        file_put_contents($MAINTENANCE_FILE, json_encode($newConfig));
        redirectWithFlash("Maintenance mode turned " . $newStatus, ($newStatus === 'ON') ? 'warning' : 'success');
    }

    // 2. Feedback Settings
    if (isset($_POST['update_feedback'])) {
        $status = isset($_POST['feedback_enabled']) ? 'ON' : 'OFF';
        
        $schema = [];
        if (isset($_POST['q_text'])) {
            $texts = $_POST['q_text'];
            $types = $_POST['q_type'];
            $options = $_POST['q_options'];
            
            for ($i = 0; $i < count($texts); $i++) {
                if (!empty(trim($texts[$i]))) {
                    $schema[] = [
                        'id' => uniqid(),
                        'text' => trim($texts[$i]),
                        'type' => $types[$i],
                        'options' => trim($options[$i]) 
                    ];
                }
            }
        }
        
        $config = ['status' => $status, 'schema' => $schema];
        file_put_contents($FEEDBACK_CONFIG, json_encode($config, JSON_PRETTY_PRINT));
        redirectWithFlash("Feedback configuration saved.");
    }

    // 3. Clear Feedback Data
    if (isset($_POST['clear_feedback_data'])) {
        file_put_contents($FEEDBACK_DATA_FILE, ""); 
        file_put_contents('feedback_submissions.json', json_encode([])); 
        redirectWithFlash("All feedback data cleared.");
    }

    // 4. Create Folder
    if (isset($_POST['create_folder'])) {
        $newFolder = $currentPath . '/' . preg_replace('/[^a-zA-Z0-9 _-]/', '', $_POST['folder_name']);
        if (!file_exists($newFolder)) {
            mkdir($newFolder, 0777, true);
            redirectWithFlash("Folder created successfully.");
        } else {
            redirectWithFlash("Folder already exists.", "error");
        }
    }
    
    // 5. Upload File (Admin)
    if (isset($_FILES['file_upload'])) {
        $targetFile = $currentPath . '/' . basename($_FILES['file_upload']['name']);
        if (move_uploaded_file($_FILES['file_upload']['tmp_name'], $targetFile)) {
            redirectWithFlash("File uploaded successfully.");
        } else {
            redirectWithFlash("Error uploading file.", "error");
        }
    }

    // 6. Delete Item
    if (isset($_POST['delete_item'])) {
        $item = $_POST['item_path'];
        if (strpos(realpath($item), realpath($BASE_DIR)) === 0) { 
            if (is_dir($item)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($item, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $fileinfo) {
                    $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                    $todo($fileinfo->getRealPath());
                }
                rmdir($item);
                redirectWithFlash("Folder deleted.");
            } else {
                unlink($item);
                redirectWithFlash("File deleted.");
            }
        }
    }

    // 7. Ban User
    if (isset($_POST['ban_user'])) {
        $entry = trim($_POST['scholar_no']) . "," . trim($_POST['reason']) . PHP_EOL;
        file_put_contents($BANNED_FILE, $entry, FILE_APPEND);
        redirectWithFlash("User banned successfully.");
    }

    // 8. Unban User
    if (isset($_POST['unban_user'])) {
        $lines = file($BANNED_FILE);
        $output = [];
        foreach ($lines as $line) {
            if (strpos($line, $_POST['scholar_no_to_remove']) === false) {
                $output[] = $line;
            }
        }
        file_put_contents($BANNED_FILE, implode("", $output));
        redirectWithFlash("User unbanned successfully.");
    }

    // 9. Update Redirection (UPDATED FOR DB & JSON)
    if (isset($_POST['update_redirection'])) {
        // A. Handle JSON Config (Old/Section URLs)
        $status = isset($_POST['redirection_enabled']) ? 'ON' : 'OFF';
        $url_ae = trim($_POST['url_ae']);
        $url_fj = trim($_POST['url_fj']);
        
        $config = ['status' => $status, 'url_ae' => $url_ae, 'url_fj' => $url_fj];
        file_put_contents($REDIRECT_CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
        
        // B. Handle Database Config (Server Redirection)
        $redir_mode = $_POST['redir_mode']; // 'off', '2_3', '4_5'
        
        // FIXED: Retrieve all 4 links instead of just 2
        $link_2 = trim($_POST['link_2']);
        $link_3 = trim($_POST['link_3']);
        $link_4 = trim($_POST['link_4']);
        $link_5 = trim($_POST['link_5']);

        $conn = db_connect();
        
        // Ensure ID 1 exists
        $check = $conn->query("SELECT id FROM redirection_settings WHERE id=1");
        if ($check->num_rows == 0) {
            $conn->query("INSERT INTO redirection_settings (id, mode) VALUES (1, 'off')");
        }

        // Update DB (Saving ALL links correctly)
        $stmt = $conn->prepare("UPDATE redirection_settings SET mode=?, target_link_2=?, target_link_3=?, target_link_4=?, target_link_5=? WHERE id=1");
        $stmt->bind_param("sssss", $redir_mode, $link_2, $link_3, $link_4, $link_5);
        $stmt->execute();
        $stmt->close();
        $conn->close();

        redirectWithFlash("Redirection settings updated (Database & Config).");
    }

    // 10. Add Announcement
    if (isset($_POST['add_announcement'])) {
        $text = trim($_POST['ann_text']);
        $link = trim($_POST['ann_link']);
        if (!empty($text)) {
            $currentAnnouncements = [];
            if (file_exists($ANNOUNCEMENTS_FILE)) {
                $currentAnnouncements = json_decode(file_get_contents($ANNOUNCEMENTS_FILE), true);
            }
            $currentAnnouncements[] = ['text' => $text, 'link' => $link];
            file_put_contents($ANNOUNCEMENTS_FILE, json_encode($currentAnnouncements, JSON_PRETTY_PRINT));
            redirectWithFlash("Announcement added.");
        }
    }

    // 11. Delete Announcement
    if (isset($_POST['delete_announcement'])) {
        $index = (int)$_POST['ann_index'];
        if (file_exists($ANNOUNCEMENTS_FILE)) {
            $currentAnnouncements = json_decode(file_get_contents($ANNOUNCEMENTS_FILE), true);
            if (isset($currentAnnouncements[$index])) {
                array_splice($currentAnnouncements, $index, 1);
                file_put_contents($ANNOUNCEMENTS_FILE, json_encode($currentAnnouncements, JSON_PRETTY_PRINT));
                redirectWithFlash("Announcement deleted.");
            }
        }
    }

    // 12. Admin Reply to Chat
    if (isset($_POST['admin_reply'])) {
        $targetScholar = $_POST['target_scholar'];
        $replyMsg = trim($_POST['reply_message']);
        
        if (!empty($replyMsg) && !empty($targetScholar)) {
            $conn = db_connect();
            $stmt = $conn->prepare("INSERT INTO messages (scholar_no, sender, message) VALUES (?, 'admin', ?)");
            $stmt->bind_param("ss", $targetScholar, $replyMsg);
            $stmt->execute();
            $stmt->close();
            $conn->close();
            redirectWithFlash("Reply sent.");
        }
    }

    // 13. Approve Upload
    if (isset($_POST['approve_upload'])) {
        $uploadId = intval($_POST['upload_id']);
        $targetGroup = isset($_POST['target_group']) ? trim($_POST['target_group']) : '';
        $targetSubject = isset($_POST['target_subject']) ? trim($_POST['target_subject']) : '';
        $targetFolder = isset($_POST['target_folder']) ? trim($_POST['target_folder']) : '';

        $conn = db_connect();
        $stmt = $conn->prepare("SELECT * FROM uploads WHERE id = ?");
        $stmt->bind_param("i", $uploadId);
        $stmt->execute();
        $result = $stmt->get_result();
        $upload = $result->fetch_assoc();
        $stmt->close();

        if ($upload && !empty($targetGroup) && !empty($targetSubject)) {
            $source = 'uploads/pending/' . $upload['stored_name'];
            $groupFolder = preg_replace('/[^a-zA-Z0-9]/', '', $targetGroup);
            $subjectFolder = preg_replace('/[^a-zA-Z0-9 \-_]/', '', $targetSubject);
            $subFolder = preg_replace('/[^a-zA-Z0-9 \-_]/', '', $targetFolder);
            
            $targetDir = $BASE_DIR . '/' . $groupFolder . '/' . $subjectFolder;
            if (!empty($subFolder)) $targetDir .= '/' . $subFolder;
            
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            
            $targetFile = $targetDir . '/' . $upload['file_name'];
            if (file_exists($targetFile)) {
                $pathInfo = pathinfo($targetFile);
                $targetFile = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_' . time() . '.' . $pathInfo['extension'];
            }

            if (file_exists($source) && rename($source, $targetFile)) {
                $stmt = $conn->prepare("UPDATE uploads SET status = 'approved' WHERE id = ?");
                $stmt->bind_param("i", $uploadId);
                $stmt->execute();
                $stmt->close();
                redirectWithFlash("File approved and moved.");
            } else {
                redirectWithFlash("Source file missing or permission error.", "error");
            }
        } else {
            redirectWithFlash("Invalid Target parameters.", "error");
        }
        $conn->close();
    }

    // 14. Reject Upload
    if (isset($_POST['reject_upload'])) {
        $uploadId = intval($_POST['upload_id']);
        $conn = db_connect();
        
        $stmt = $conn->prepare("SELECT stored_name FROM uploads WHERE id = ?");
        $stmt->bind_param("i", $uploadId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if ($row) {
            $fileToDelete = 'uploads/pending/' . $row['stored_name'];
            if (file_exists($fileToDelete)) unlink($fileToDelete);
            
            $stmt = $conn->prepare("UPDATE uploads SET status = 'rejected' WHERE id = ?");
            $stmt->bind_param("i", $uploadId);
            $stmt->execute();
            $stmt->close();
            redirectWithFlash("File rejected and deleted.");
        }
        $conn->close();
    }

    // 15. Approve Password Reset
    if (isset($_POST['approve_reset'])) {
        $reqId = intval($_POST['request_id']);
        $conn = db_connect();
        
        $stmt = $conn->prepare("SELECT scholar_no FROM password_reset_requests WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("i", $reqId);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($row = $res->fetch_assoc()) {
            $scholarNo = $row['scholar_no'];
            
            $update_stmt = $conn->prepare("UPDATE students SET password = ? WHERE scholar_no = ?");
            $update_stmt->bind_param("ss", $scholarNo, $scholarNo);
            
            if ($update_stmt->execute()) {
                $status_stmt = $conn->prepare("UPDATE password_reset_requests SET status = 'approved' WHERE id = ?");
                $status_stmt->bind_param("i", $reqId);
                $status_stmt->execute();
                
                redirectWithFlash("Password reset approved. Student can now login with Scholar No.");
            } else {
                redirectWithFlash("Failed to update student password.", "error");
            }
        } else {
            redirectWithFlash("Request not found or already processed.", "error");
        }
        $conn->close();
    }

    // 16. Reject Password Reset
    if (isset($_POST['reject_reset'])) {
        $reqId = intval($_POST['request_id']);
        $conn = db_connect();
        
        $stmt = $conn->prepare("UPDATE password_reset_requests SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $reqId);
        $stmt->execute();
        
        $conn->close();
        redirectWithFlash("Request rejected.");
    }

    // 17. Update Global Notice
    if (isset($_POST['update_notice'])) {
        $status = isset($_POST['notice_enabled']) ? 'ON' : 'OFF';
        $title = trim($_POST['notice_title']);
        $message = trim($_POST['notice_message']);

        // Load existing to compare
        $currentNotice = json_decode(file_get_contents($NOTICE_CONFIG_FILE), true);
        
        $newId = $currentNotice['id'];
        if ($title !== $currentNotice['title'] || $message !== $currentNotice['message']) {
            $newId = uniqid(); 
        }

        $config = [
            'status' => $status,
            'title' => $title,
            'message' => $message,
            'id' => $newId
        ];
        
        file_put_contents($NOTICE_CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
        redirectWithFlash("Global notice updated.");
    }

    // 18. Update Access Mode (New)
    if (isset($_POST['update_access_mode'])) {
        $mode = isset($_POST['strict_access_enabled']) ? 'STRICT' : 'OPEN';
        file_put_contents($ACCESS_CONFIG_FILE, json_encode(['mode' => $mode]));
        redirectWithFlash("Access mode updated to " . $mode . " (Only allowed users can access).", "warning");
    }

    // 19. Add Allowed User (New)
    if (isset($_POST['allow_user'])) {
        $entry = trim($_POST['scholar_no']) . "," . trim($_POST['note']) . PHP_EOL;
        file_put_contents($ALLOWED_FILE, $entry, FILE_APPEND);
        redirectWithFlash("User added to allowlist.");
    }

    // 20. Remove Allowed User (New)
    if (isset($_POST['remove_allowed_user'])) {
        $lines = file($ALLOWED_FILE);
        $output = [];
        foreach ($lines as $line) {
            if (strpos($line, $_POST['scholar_no_to_remove']) === false) {
                $output[] = $line;
            }
        }
        file_put_contents($ALLOWED_FILE, implode("", $output));
        redirectWithFlash("User removed from allowlist.");
    }
}

// --- CHECK FLASH MESSAGES ---
$message = '';
$messageType = '';
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

// --- DATA FETCHING ---
$conn = db_connect();

// 1. Total Visits
$result = $conn->query("SELECT COUNT(*) as count FROM log_entries");
$totalVisits = $result->fetch_assoc()['count'];

// 2. Unique Users (UPDATED: Only counts scholar numbers starting with '25')
$result = $conn->query("SELECT COUNT(DISTINCT scholar_no) as count FROM log_entries WHERE scholar_no LIKE '25%'");
$uniqueUsers = $result->fetch_assoc()['count'];

// 3. Top Resources
$topResources = $conn->query("SELECT details, COUNT(*) as count FROM log_entries WHERE event_type='Access' GROUP BY details ORDER BY count DESC LIMIT 5");

// 4. Recent Logs
$recentLogs = $conn->query("SELECT * FROM log_entries ORDER BY event_time DESC LIMIT 10");

// 5. Get DB Redirection Settings (New)
// Default fallback arrays adjusted to fetch all links
$dbRedirectQuery = $conn->query("SELECT * FROM redirection_settings WHERE id=1");
$dbRedirectSettings = ($dbRedirectQuery && $dbRedirectQuery->num_rows > 0) 
    ? $dbRedirectQuery->fetch_assoc() 
    : ['mode' => 'off', 'target_link_2' => '', 'target_link_3' => '', 'target_link_4' => '', 'target_link_5' => ''];

// Chat Conversations
$conversations = [];
$convQuery = "SELECT m1.scholar_no, m1.sender, m1.message, m1.created_at FROM messages m1 INNER JOIN (SELECT scholar_no, MAX(created_at) as max_date FROM messages GROUP BY scholar_no) m2 ON m1.scholar_no = m2.scholar_no AND m1.created_at = m2.max_date ORDER BY m1.created_at DESC";
$convResult = $conn->query($convQuery);
if ($convResult) { while($row = $convResult->fetch_assoc()) { $conversations[] = $row; } }

// Active Chat Messages
$activeChatUser = isset($_GET['chat_user']) ? $_GET['chat_user'] : (isset($_POST['target_scholar']) ? $_POST['target_scholar'] : null);
$activeMessages = [];
if ($activeChatUser) {
    $stmt = $conn->prepare("SELECT * FROM messages WHERE scholar_no = ? ORDER BY created_at ASC");
    $stmt->bind_param("s", $activeChatUser);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()) { $activeMessages[] = $row; }
    $stmt->close();
}

// Pending Uploads
$pendingUploads = [];
$uploadResult = $conn->query("SELECT * FROM uploads WHERE status = 'pending' ORDER BY upload_date ASC");
if ($uploadResult) { while ($row = $uploadResult->fetch_assoc()) { $pendingUploads[] = $row; } }

// Pending Password Resets
$pendingResets = [];
$resetResult = $conn->query("SELECT * FROM password_reset_requests WHERE status = 'pending' ORDER BY request_time ASC");
if ($resetResult) { while ($row = $resetResult->fetch_assoc()) { $pendingResets[] = $row; } }

$conn->close();

// File Manager List
$items = scandir($currentPath);
$folders = [];
$files = [];
foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue;
    $fullPath = $currentPath . '/' . $item;
    if (is_dir($fullPath)) $folders[] = $item; else $files[] = $item;
}

// Banned Users
$bannedUsers = [];
if (file_exists($BANNED_FILE)) {
    $bannedLines = file($BANNED_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($bannedLines as $line) {
        $parts = explode(',', $line, 2);
        if (count($parts) === 2) $bannedUsers[] = ['id' => $parts[0], 'reason' => $parts[1]];
    }
}

// Allowed Users (New)
$allowedUsers = [];
if (file_exists($ALLOWED_FILE)) {
    $allowedLines = file($ALLOWED_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($allowedLines as $line) {
        $parts = explode(',', $line, 2);
        if (count($parts) === 2) $allowedUsers[] = ['id' => $parts[0], 'note' => $parts[1]];
    }
}

// Access Config (New)
$accessConfig = json_decode(file_get_contents($ACCESS_CONFIG_FILE), true);
$isStrictMode = ($accessConfig['mode'] === 'STRICT');

// Redirection Config (JSON - Old Style)
$redirectionConfig = ['status' => 'OFF', 'url_ae' => '', 'url_fj' => ''];
if (file_exists($REDIRECT_CONFIG_FILE)) {
    $loadedConfig = json_decode(file_get_contents($REDIRECT_CONFIG_FILE), true);
    if ($loadedConfig) $redirectionConfig = array_merge($redirectionConfig, $loadedConfig);
}
$isRedirectionOn = ($redirectionConfig['status'] === 'ON');

// Announcements
$announcements = [];
if (file_exists($ANNOUNCEMENTS_FILE)) $announcements = json_decode(file_get_contents($ANNOUNCEMENTS_FILE), true);

// Maintenance Config
$maintenanceConfig = json_decode(file_get_contents($MAINTENANCE_FILE), true);
$isMaintenanceOn = ($maintenanceConfig['maintenance_mode'] === 'ON');

// Feedback Config & Data
$feedbackConfig = json_decode(file_get_contents($FEEDBACK_CONFIG), true);
$isFeedbackOn = ($feedbackConfig['status'] === 'ON');
$feedbackSchema = $feedbackConfig['schema'] ?? [['text' => 'How can we improve?', 'type' => 'text', 'options' => '']];

$feedbackData = [];
if (file_exists($FEEDBACK_DATA_FILE)) {
    $feedbackLines = file($FEEDBACK_DATA_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $feedbackData = array_reverse($feedbackLines); 
}

// Notice Config
$noticeConfig = json_decode(file_get_contents($NOTICE_CONFIG_FILE), true);
$isNoticeOn = ($noticeConfig['status'] === 'ON');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | NITBFreshers Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .scrollbar-hide::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 min-h-screen flex flex-col lg:flex-row transition-colors duration-200">

    <aside class="w-full lg:w-64 bg-slate-900 dark:bg-slate-950 text-white flex-shrink-0 lg:h-screen lg:fixed overflow-y-auto border-r border-slate-800">
        <div class="p-6 border-b border-slate-800">
            <div class="flex items-center gap-3">
                <img src="./images/logo.png" class="h-8 w-auto bg-white rounded-md p-1">
                <h1 class="font-bold text-lg">Admin Panel</h1>
            </div>
        </div>
        <nav class="p-4 space-y-2">
            <a href="#maintenance" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-lg transition <?php echo $isMaintenanceOn ? 'text-red-400' : ''; ?>">
                <i data-lucide="power" class="w-5 h-5"></i> System Maintenance
            </a>
            <a href="#notice" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-lg transition <?php echo $isNoticeOn ? 'text-indigo-400' : ''; ?>">
                <i data-lucide="megaphone" class="w-5 h-5"></i> Global Notice
            </a>
            <a href="#stats" class="flex items-center gap-3 px-4 py-3 bg-blue-600 rounded-lg text-white shadow-lg shadow-blue-900/20">
                <i data-lucide="bar-chart-2" class="w-5 h-5"></i> Statistics
            </a>
            
            <a href="#resets" class="flex items-center justify-between px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-lg transition">
                <div class="flex items-center gap-3"><i data-lucide="key-round" class="w-5 h-5"></i> Password Resets</div>
                <?php if(count($pendingResets) > 0): ?>
                    <span class="bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full"><?php echo count($pendingResets); ?></span>
                <?php endif; ?>
            </a>

            <a href="#contributions" class="flex items-center justify-between px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-lg transition">
                <div class="flex items-center gap-3"><i data-lucide="upload-cloud" class="w-5 h-5"></i> Student Uploads</div>
                <?php if(count($pendingUploads) > 0): ?>
                    <span class="bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full"><?php echo count($pendingUploads); ?></span>
                <?php endif; ?>
            </a>
            <a href="#feedback" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-lg transition">
                <i data-lucide="message-square-heart" class="w-5 h-5"></i> Feedback System
            </a>
            <a href="#redirect" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-lg transition">
                <i data-lucide="shuffle" class="w-5 h-5"></i> Redirect User
            </a>
            <a href="#announcements" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-lg transition">
                <i data-lucide="bell" class="w-5 h-5"></i> Announcements
            </a>
            <a href="#files" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-lg transition">
                <i data-lucide="folder" class="w-5 h-5"></i> File Manager
            </a>
            <a href="#users" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-lg transition">
                <i data-lucide="users" class="w-5 h-5"></i> User Control
            </a>
            <a href="#messages" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-lg transition">
                <i data-lucide="message-square" class="w-5 h-5"></i> Messages
            </a>
            <div class="border-t border-slate-800 my-4 pt-4">
                <a href="?logout=true" class="flex items-center gap-3 px-4 py-3 text-red-400 hover:bg-red-900/20 rounded-lg transition">
                    <i data-lucide="log-out" class="w-5 h-5"></i> Logout
                </a>
            </div>
        </nav>
    </aside>

    <main class="flex-1 lg:ml-64 p-6 lg:p-8 space-y-8">
        
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800 dark:text-white">Dashboard Overview</h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm">Welcome back, Admin</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="px-3 py-1.5 rounded-lg text-xs font-bold border <?php echo $isMaintenanceOn ? 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 border-red-200 dark:border-red-800' : 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 border-green-200 dark:border-green-800'; ?>">
                    SYSTEM: <?php echo $isMaintenanceOn ? 'OFFLINE' : 'ONLINE'; ?>
                </div>
                <button id="themeToggle" class="p-2.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors shadow-sm">
                    <span class="hidden dark:block"><i data-lucide="sun" class="w-5 h-5"></i></span>
                    <span class="block dark:hidden"><i data-lucide="moon" class="w-5 h-5"></i></span>
                </button>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-800' : ($messageType === 'warning' ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800' : 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-800'); ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <section id="maintenance" class="scroll-mt-20">
            <div class="bg-gradient-to-r from-slate-900 to-slate-800 rounded-xl shadow-lg border border-slate-700 p-6 text-white">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                    <div>
                        <h2 class="text-xl font-bold flex items-center gap-2 text-white">
                            <i data-lucide="shield-alert" class="text-red-400"></i> Emergency Kill Switch
                        </h2>
                        <p class="text-slate-300 text-sm mt-2 max-w-xl">
                            Enable this to immediately block all student access to the dashboard. 
                        </p>
                    </div>
                    <form method="POST" class="flex flex-col gap-3 w-full md:w-auto">
                        <textarea name="reason_text" rows="2" placeholder="Maintenance Reason (Visible to users)..." class="w-full md:w-64 px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-sm text-white focus:ring-2 focus:ring-blue-500 outline-none"><?php echo htmlspecialchars($maintenanceConfig['reason']); ?></textarea>
                        <button type="submit" name="toggle_maintenance" class="px-6 py-3 rounded-lg font-bold shadow-lg transition-all flex items-center justify-center gap-2 <?php echo $isMaintenanceOn ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-red-600 hover:bg-red-700 text-white'; ?>">
                            <?php if($isMaintenanceOn): ?>
                                <i data-lucide="play" class="w-4 h-4"></i> Restore Services
                            <?php else: ?>
                                <i data-lucide="octagon-alert" class="w-4 h-4"></i> SHUT DOWN PORTAL
                            <?php endif; ?>
                        </button>
                    </form>
                </div>
            </div>
        </section>

        <section id="notice" class="scroll-mt-20">
            <h2 class="text-2xl font-bold text-slate-800 dark:text-white mb-6 pt-4 border-t border-slate-200 dark:border-slate-700 flex items-center gap-2">
                <i data-lucide="megaphone" class="text-indigo-600 dark:text-indigo-400"></i> Global Popup Notice
            </h2>
            <div class="bg-white dark:bg-slate-800 p-6 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 transition-colors">
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 dark:text-slate-300 mb-1">Notice Title</label>
                        <input type="text" name="notice_title" value="<?php echo htmlspecialchars($noticeConfig['title']); ?>" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-900 dark:border-slate-600 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 dark:text-slate-300 mb-1">Notice Message (Supports basic HTML)</label>
                        <textarea name="notice_message" rows="3" class="w-full px-4 py-2 border rounded-lg bg-slate-50 dark:bg-slate-900 dark:border-slate-600 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" required><?php echo htmlspecialchars($noticeConfig['message']); ?></textarea>
                    </div>
                    <div class="flex items-center justify-between bg-slate-50 dark:bg-slate-700/50 p-4 rounded-lg border border-slate-200 dark:border-slate-600">
                        <div class="flex items-center gap-4">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Show Popup to Users</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="notice_enabled" class="sr-only peer" <?php echo $isNoticeOn ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>
                        <button type="submit" name="update_notice" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">Save & Push Update</button>
                    </div>
                    <p class="text-xs text-slate-400 mt-2">
                        <i data-lucide="info" class="w-3 h-3 inline"></i> Note: Changing the Title or Message content will reset the view status for all students (the popup will appear again). Toggling ON/OFF preserves view status.
                    </p>
                </form>
            </div>
        </section>

        <section id="stats" class="scroll-mt-20">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white dark:bg-slate-800 p-6 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 transition-colors">
                    <div class="text-slate-500 dark:text-slate-400 text-sm font-medium mb-1">Total Logs</div>
                    <div class="text-3xl font-bold text-slate-900 dark:text-white"><?php echo $totalVisits; ?></div>
                </div>
                
                <div class="bg-white dark:bg-slate-800 p-6 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 transition-colors">
                    <div class="text-slate-500 dark:text-slate-400 text-sm font-medium mb-1">Unique Students (Batch '25)</div>
                    <div class="text-3xl font-bold text-slate-900 dark:text-white"><?php echo $uniqueUsers; ?></div>
                </div>
                
                <div class="bg-white dark:bg-slate-800 p-6 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 transition-colors md:col-span-2">
                    <div class="text-slate-500 dark:text-slate-400 text-sm font-medium mb-3">Top Resources</div>
                    <div class="space-y-2">
                        <?php while($row = $topResources->fetch_assoc()): ?>
                            <div class="flex justify-between items-center text-sm border-b border-slate-50 dark:border-slate-700/50 pb-1 last:border-0 last:pb-0">
                                <span class="truncate max-w-[200px] text-slate-700 dark:text-slate-300" title="<?php echo $row['details']; ?>"><?php echo $row['details']; ?></span>
                                <span class="font-bold text-blue-600 dark:text-blue-400"><?php echo $row['count']; ?> views</span>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden transition-colors mt-6">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 font-semibold text-slate-800 dark:text-white">Recent Activity Log</div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-slate-50 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400 font-medium">
                            <tr>
                                <th class="px-6 py-3">Time</th>
                                <th class="px-6 py-3">User</th>
                                <th class="px-6 py-3">Action</th>
                                <th class="px-6 py-3">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            <?php while($log = $recentLogs->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                                    <td class="px-6 py-3 whitespace-nowrap text-slate-500 dark:text-slate-400">
                                    <?php 
                                        $dbTime = new DateTime($log['event_time']);
                                        $dbTime->add(new DateInterval('PT13H30M')); 
                                        echo $dbTime->format('M d, H:i'); 
                                    ?>
                                    </td>
                                    <td class="px-6 py-3 font-medium text-slate-900 dark:text-slate-200"><?php echo $log['scholar_no']; ?></td>
                                    <td class="px-6 py-3">
                                        <span class="px-2 py-1 rounded-full text-xs font-semibold <?php echo $log['event_type'] === 'Access' ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300' : 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300'; ?>">
                                            <?php echo $log['event_type']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-slate-600 dark:text-slate-400 truncate max-w-xs" title="<?php echo $log['details']; ?>"><?php echo $log['details']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </section>

        <section id="resets" class="scroll-mt-20">
            <h2 class="text-2xl font-bold text-slate-800 dark:text-white mb-6 pt-4 border-t border-slate-200 dark:border-slate-700 flex items-center gap-2">
                <i data-lucide="key-round" class="text-blue-600 dark:text-blue-400"></i> Password Resets
                <?php if(count($pendingResets) > 0): ?>
                    <span class="bg-red-500 text-white text-xs font-bold px-2.5 py-1 rounded-full ml-2"><?php echo count($pendingResets); ?> Pending</span>
                <?php endif; ?>
            </h2>
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden transition-colors">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 font-semibold text-slate-800 dark:text-white">Requests</div>
                <?php if (empty($pendingResets)): ?>
                    <div class="p-8 text-center text-slate-500 dark:text-slate-400">
                        <i data-lucide="shield-check" class="w-12 h-12 mx-auto mb-3 text-green-500 opacity-50"></i>
                        <p>No pending password reset requests.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-slate-50 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400 font-medium">
                                <tr>
                                    <th class="px-6 py-3">Date</th>
                                    <th class="px-6 py-3">Scholar No</th>
                                    <th class="px-6 py-3">Type</th>
                                    <th class="px-6 py-3">Proof</th>
                                    <th class="px-6 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <?php foreach($pendingResets as $req): ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                                        <td class="px-6 py-3 whitespace-nowrap text-slate-500 dark:text-slate-400"><?php echo date('M d, H:i', strtotime($req['request_time'])); ?></td>
                                        <td class="px-6 py-3 font-medium font-mono text-slate-900 dark:text-slate-200"><?php echo htmlspecialchars($req['scholar_no']); ?></td>
                                        <td class="px-6 py-3">
                                            <span class="px-2 py-1 rounded text-xs font-semibold <?php echo ($req['type'] == 'Compromised Account') ? 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400' : 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400'; ?>">
                                                <?php echo htmlspecialchars($req['type']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-3">
                                            <?php if (!empty($req['id_card_path'])): ?>
                                                <a href="<?php echo htmlspecialchars($req['id_card_path']); ?>" target="_blank" class="flex items-center gap-1 text-blue-600 dark:text-blue-400 hover:underline font-medium">
                                                    <i data-lucide="eye" class="w-4 h-4"></i> View ID
                                                </a>
                                            <?php else: ?>
                                                <span class="text-slate-400 italic">No File</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-3 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <form method="POST" onsubmit="return confirm('Approve reset? Password will become Scholar No.');">
                                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                                    <button type="submit" name="approve_reset" class="p-2 bg-green-100 text-green-600 rounded-lg hover:bg-green-200 border border-green-200 transition" title="Approve & Reset">
                                                        <i data-lucide="check" class="w-4 h-4"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" onsubmit="return confirm('Reject this request?');">
                                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                                    <button type="submit" name="reject_reset" class="p-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 border border-red-200 transition" title="Reject">
                                                        <i data-lucide="x" class="w-4 h-4"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section id="contributions" class="scroll-mt-20">
            <h2 class="text-2xl font-bold text-slate-800 dark:text-white mb-6 pt-4 border-t border-slate-200 dark:border-slate-700 flex items-center gap-2">
                <i data-lucide="upload-cloud" class="text-blue-600 dark:text-blue-400"></i> Student Contributions
                <?php if(count($pendingUploads) > 0): ?>
                    <span class="bg-red-500 text-white text-xs font-bold px-2.5 py-1 rounded-full ml-2"><?php echo count($pendingUploads); ?> New</span>
                <?php endif; ?>
            </h2>
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden transition-colors">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 font-semibold text-slate-800 dark:text-white">Pending Approvals</div>
                <?php if (empty($pendingUploads)): ?>
                    <div class="p-8 text-center text-slate-500 dark:text-slate-400"><i data-lucide="check-circle" class="w-12 h-12 mx-auto mb-3 text-green-500 opacity-50"></i><p>No pending uploads found.</p></div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-slate-50 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400 font-medium">
                                <tr>
                                    <th class="px-6 py-3">Date</th><th class="px-6 py-3">Student</th><th class="px-6 py-3">File Info</th><th class="px-6 py-3">Target</th><th class="px-6 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <?php foreach($pendingUploads as $up): ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                                        <td class="px-6 py-3 whitespace-nowrap text-slate-500 dark:text-slate-400"><?php echo date('M d, H:i', strtotime($up['upload_date'])); ?></td>
                                        <td class="px-6 py-3"><div class="font-medium text-slate-900 dark:text-slate-200"><?php echo htmlspecialchars($up['uploader_name']); ?></div><div class="text-xs text-slate-500"><?php echo htmlspecialchars($up['scholar_no']); ?></div></td>
                                        <td class="px-6 py-3"><div class="flex items-center gap-2"><i data-lucide="file" class="w-4 h-4 text-slate-400"></i><a href="uploads/pending/<?php echo $up['stored_name']; ?>" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline font-medium truncate max-w-[200px]"><?php echo htmlspecialchars($up['file_name']); ?></a></div></td>
                                        <td class="px-6 py-3">
                                            <form method="POST" class="flex items-center gap-2">
                                                <input type="hidden" name="upload_id" value="<?php echo $up['id']; ?>">
                                                <div class="flex flex-col gap-1">
                                                    <div class="flex gap-1">
                                                        <input type="text" name="target_group" value="<?php echo htmlspecialchars($up['group_name']); ?>" class="text-xs border rounded px-2 py-1 w-20 dark:bg-slate-800 dark:border-slate-600 dark:text-white" placeholder="Group">
                                                        <input type="text" name="target_subject" value="<?php echo htmlspecialchars($up['subject']); ?>" class="text-xs border rounded px-2 py-1 w-32 dark:bg-slate-800 dark:border-slate-600 dark:text-white" placeholder="Subject">
                                                    </div>
                                                    <input type="text" name="target_folder" value="<?php echo isset($up['folder_name']) ? htmlspecialchars($up['folder_name']) : ''; ?>" class="text-xs border rounded px-2 py-1 w-full dark:bg-slate-800 dark:border-slate-600 dark:text-white" placeholder="Sub-folder">
                                                </div>
                                                <button type="submit" name="approve_upload" class="p-2 bg-green-100 text-green-600 rounded-lg hover:bg-green-200"><i data-lucide="check" class="w-4 h-4"></i></button>
                                            </form>
                                        </td>
                                        <td class="px-6 py-3 text-right">
                                            <form method="POST" onsubmit="return confirm('Reject this file?');">
                                                <input type="hidden" name="upload_id" value="<?php echo $up['id']; ?>">
                                                <button type="submit" name="reject_upload" class="p-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200"><i data-lucide="x" class="w-4 h-4"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section id="feedback" class="scroll-mt-20">
            <h2 class="text-2xl font-bold text-slate-800 dark:text-white mb-6 pt-4 border-t border-slate-200 dark:border-slate-700 flex items-center gap-2">
                <i data-lucide="message-square-heart" class="text-blue-600 dark:text-blue-400"></i> Feedback System
            </h2>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-slate-800 p-6 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 h-fit">
                    <h3 class="font-bold text-lg mb-4 text-slate-800 dark:text-white">Form Builder</h3>
                    <form method="POST" class="space-y-4">
                        <div id="questionsContainer" class="space-y-4">
                            <?php foreach ($feedbackSchema as $idx => $q): ?>
                            <div class="p-4 bg-slate-50 dark:bg-slate-700/30 rounded-lg border border-slate-200 dark:border-slate-600 relative group">
                                <button type="button" onclick="this.parentElement.remove()" class="absolute top-2 right-2 text-red-400 hover:text-red-600"><i data-lucide="x" class="w-4 h-4"></i></button>
                                <div class="space-y-2">
                                    <input type="text" name="q_text[]" value="<?php echo htmlspecialchars($q['text']); ?>" placeholder="Question Text" class="w-full px-3 py-2 border rounded-lg bg-white dark:bg-slate-800 dark:border-slate-600 dark:text-white text-sm" required>
                                    <select name="q_type[]" onchange="toggleOptions(this)" class="w-full px-3 py-2 border rounded-lg bg-white dark:bg-slate-800 dark:border-slate-600 dark:text-white text-sm">
                                        <option value="text" <?php echo $q['type'] == 'text' ? 'selected' : ''; ?>>Text Answer</option>
                                        <option value="mcq" <?php echo $q['type'] == 'mcq' ? 'selected' : ''; ?>>Multiple Choice</option>
                                    </select>
                                    <input type="text" name="q_options[]" value="<?php echo htmlspecialchars($q['options']); ?>" placeholder="Options (comma separated)" class="w-full px-3 py-2 border rounded-lg bg-white dark:bg-slate-800 dark:border-slate-600 dark:text-white text-sm <?php echo $q['type'] == 'text' ? 'hidden' : ''; ?>">
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="button" onclick="addQuestion()" class="text-sm text-blue-600 hover:underline flex items-center gap-1"><i data-lucide="plus" class="w-3 h-3"></i> Add Question</button>

                        <div class="flex items-center justify-between bg-slate-50 dark:bg-slate-700/50 p-3 rounded-lg border border-slate-200 dark:border-slate-600 mt-4">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Enable Feedback</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="feedback_enabled" class="sr-only peer" <?php echo $isFeedbackOn ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        
                        <button type="submit" name="update_feedback" class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">Save Configuration</button>
                    </form>
                    
                    <form method="POST" onsubmit="return confirm('Delete all data?');" class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                        <button type="submit" name="clear_feedback_data" class="w-full px-4 py-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 text-sm font-medium rounded-lg transition-colors border border-red-200 dark:border-red-800">Clear All Data</button>
                    </form>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 font-semibold text-slate-800 dark:text-white flex justify-between items-center">
                        <span>Student Responses</span>
                        <span class="text-xs bg-slate-100 dark:bg-slate-700 px-2 py-1 rounded text-slate-500 dark:text-slate-300"><?php echo count($feedbackData); ?></span>
                    </div>
                    <div class="max-h-[500px] overflow-y-auto">
                        <?php if (empty($feedbackData)): ?>
                            <p class="p-8 text-center text-slate-400">No feedback submitted yet.</p>
                        <?php else: ?>
                            <table class="w-full text-sm text-left">
                                <thead class="bg-slate-50 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400 font-medium sticky top-0">
                                    <tr>
                                        <th class="px-6 py-3">Time</th>
                                        <th class="px-6 py-3">Student</th>
                                        <th class="px-6 py-3">Response Data</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                    <?php foreach ($feedbackData as $line): 
                                        $parts = explode(' | ', $line, 4);
                                        if(count($parts) < 4) continue;
                                    ?>
                                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                            <td class="px-6 py-3 whitespace-nowrap text-slate-500 dark:text-slate-400 text-xs"><?php echo htmlspecialchars($parts[0]); ?></td>
                                            <td class="px-6 py-3">
                                                <div class="font-medium text-slate-900 dark:text-slate-200"><?php echo htmlspecialchars($parts[2]); ?></div>
                                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($parts[1]); ?></div>
                                            </td>
                                            <td class="px-6 py-3 text-slate-700 dark:text-slate-300 text-xs break-all"><?php echo htmlspecialchars($parts[3]); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <section id="redirect" class="scroll-mt-20">
            <h2 class="text-2xl font-bold text-slate-800 dark:text-white mb-6 pt-4 border-t border-slate-200 dark:border-slate-700 flex items-center gap-2"><i data-lucide="shuffle" class="text-blue-600 dark:text-blue-400"></i> Traffic Redirection</h2>
            <div class="bg-white dark:bg-slate-800 p-6 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 h-fit transition-colors">
                <form method="POST" class="space-y-6">
                    
                    <div class="p-4 bg-blue-50 dark:bg-slate-900/50 rounded-lg border border-blue-100 dark:border-slate-700 space-y-4">
                        <h3 class="font-bold text-slate-800 dark:text-white text-sm">Server Cluster Control (Database)</h3>
                        
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Active Mode</label>
                            <select name="redir_mode" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg outline-none bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm">
                                <option value="off" <?php echo ($dbRedirectSettings['mode'] == 'off') ? 'selected' : ''; ?>>Redirection OFF (All Point to Master)</option>
                                <option value="2_3" <?php echo ($dbRedirectSettings['mode'] == '2_3') ? 'selected' : ''; ?>>Active: 2 & 3 (Redirect 4 & 5 to Master)</option>
                                <option value="4_5" <?php echo ($dbRedirectSettings['mode'] == '4_5') ? 'selected' : ''; ?>>Active: 4 & 5 (Redirect 2 & 3 to Master)</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">ST Group Link (Site 2)</label>
                                <input type="url" name="link_2" placeholder="http://nitbfreshers2..." value="<?php echo htmlspecialchars($dbRedirectSettings['target_link_2'] ?? ''); ?>" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg outline-none bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">MT Group Link (Site 3)</label>
                                <input type="url" name="link_3" placeholder="http://nitbfreshers3..." value="<?php echo htmlspecialchars($dbRedirectSettings['target_link_3'] ?? ''); ?>" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg outline-none bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">ST Group Link (Site 4)</label>
                                <input type="url" name="link_4" placeholder="http://nitbfreshers4..." value="<?php echo htmlspecialchars($dbRedirectSettings['target_link_4'] ?? ''); ?>" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg outline-none bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">MT Group Link (Site 5)</label>
                                <input type="url" name="link_5" placeholder="http://nitbfreshers5..." value="<?php echo htmlspecialchars($dbRedirectSettings['target_link_5'] ?? ''); ?>" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg outline-none bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm">
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 dark:border-slate-700 my-4"></div>

                    <div class="space-y-4 opacity-75 hover:opacity-100 transition-opacity">
                        <h3 class="font-bold text-slate-800 dark:text-white text-sm">Section URL Config (JSON)</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div><label class="block text-sm font-medium text-slate-600 dark:text-slate-300 mb-1">Sections A - E URL</label><input type="url" name="url_ae" placeholder="https://..." value="<?php echo htmlspecialchars($redirectionConfig['url_ae']); ?>" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg outline-none bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm"></div>
                            <div><label class="block text-sm font-medium text-slate-600 dark:text-slate-300 mb-1">Sections F - J URL</label><input type="url" name="url_fj" placeholder="https://..." value="<?php echo htmlspecialchars($redirectionConfig['url_fj']); ?>" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg outline-none bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm"></div>
                        </div>
                        <div class="flex items-center gap-4 bg-slate-50 dark:bg-slate-700/50 p-3 rounded-lg"><span class="text-sm font-medium text-slate-700 dark:text-slate-200">Enable JSON Redirect</span><label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" name="redirection_enabled" class="sr-only peer" <?php echo $isRedirectionOn ? 'checked' : ''; ?>><div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div></label></div>
                    </div>

                    <div class="flex justify-end pt-4">
                        <button type="submit" name="update_redirection" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">Save All Settings</button>
                    </div>
                </form>
            </div>
        </section>

        <section id="announcements" class="scroll-mt-20">
             <h2 class="text-2xl font-bold text-slate-800 dark:text-white mb-6 pt-4 border-t border-slate-200 dark:border-slate-700 flex items-center gap-2"><i data-lucide="bell" class="text-blue-600 dark:text-blue-400"></i> Announcements</h2>
             <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-slate-800 p-6 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 h-fit transition-colors">
                    <form method="POST" class="space-y-4">
                        <input type="text" name="ann_text" required class="w-full px-3 py-2 border rounded-lg bg-white dark:bg-slate-900 dark:text-white" placeholder="Text">
                        <input type="text" name="ann_link" class="w-full px-3 py-2 border rounded-lg bg-white dark:bg-slate-900 dark:text-white" placeholder="Link (Optional)">
                        <button type="submit" name="add_announcement" class="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">Add</button>
                    </form>
                </div>
                <div class="bg-white dark:bg-slate-800 p-6 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 h-fit transition-colors">
                    <ul class="space-y-3">
                        <?php foreach($announcements as $index => $ann): ?>
                            <li class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-700/30 rounded-lg">
                                <div class="text-sm dark:text-white"><?php echo htmlspecialchars($ann['text']); ?></div>
                                <form method="POST"><input type="hidden" name="ann_index" value="<?php echo $index; ?>"><button type="submit" name="delete_announcement" class="text-red-500"><i data-lucide="trash-2" class="w-4 h-4"></i></button></form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
             </div>
        </section>

        <section id="files" class="scroll-mt-20">
            <h2 class="text-2xl font-bold text-slate-800 dark:text-white mb-6 pt-4 border-t border-slate-200 dark:border-slate-700 flex items-center gap-2"><i data-lucide="folder-open" class="text-blue-600 dark:text-blue-400"></i> File Manager</h2>
            <div class="text-sm bg-slate-100 dark:bg-slate-700/50 px-3 py-2 rounded-lg text-slate-600 dark:text-slate-300 font-mono break-all border border-slate-200 dark:border-slate-600 mb-4">
                <span class="text-slate-400 dark:text-slate-500">Path:</span> <?php echo $currentPath; ?>
                <?php if($currentPath !== $BASE_DIR): ?>
                    <a href="?path=<?php echo dirname($currentPath); ?>#files" class="ml-2 text-blue-600 dark:text-blue-400 hover:underline font-bold">[ Up Level ]</a>
                <?php endif; ?>
            </div>
            <div class="bg-slate-800 dark:bg-slate-950 p-4 rounded-t-xl flex flex-wrap gap-4 items-center">
                <form method="POST" class="flex gap-2"><input type="text" name="folder_name" placeholder="New Folder" required class="px-3 py-1.5 rounded text-sm bg-slate-700 text-white"><button type="submit" name="create_folder" class="px-3 py-1.5 bg-blue-600 text-white text-sm rounded">Folder</button></form>
                <form method="POST" enctype="multipart/form-data" class="flex gap-2 items-center"><input type="file" name="file_upload" required class="text-sm text-slate-300"><button type="submit" class="px-3 py-1.5 bg-green-600 text-white text-sm rounded">Upload</button></form>
            </div>
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-b-xl p-6 transition-colors">
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    <?php foreach($folders as $folder): ?>
                        <div class="group relative bg-slate-50 dark:bg-slate-700/50 p-4 rounded-lg text-center">
                            <a href="?path=<?php echo $currentPath . '/' . $folder; ?>#files" class="absolute inset-0 z-0"></a>
                            <i data-lucide="folder" class="w-10 h-10 text-yellow-500 mx-auto mb-2"></i>
                            <span class="text-sm dark:text-white"><?php echo $folder; ?></span>
                            <form method="POST" class="absolute top-2 right-2 z-20" onsubmit="return confirm('Delete?');"><input type="hidden" name="item_path" value="<?php echo $currentPath . '/' . $folder; ?>"><button type="submit" name="delete_item" class="text-red-500"><i data-lucide="trash-2" class="w-3 h-3"></i></button></form>
                        </div>
                    <?php endforeach; ?>
                    <?php foreach($files as $file): ?>
                        <div class="group relative bg-white dark:bg-slate-800 p-4 rounded-lg text-center border dark:border-slate-600">
                            <i data-lucide="file" class="w-10 h-10 text-slate-400 mx-auto mb-2"></i>
                            <span class="text-xs dark:text-white"><?php echo $file; ?></span>
                            <form method="POST" class="absolute top-2 right-2 z-20" onsubmit="return confirm('Delete?');"><input type="hidden" name="item_path" value="<?php echo $currentPath . '/' . $file; ?>"><button type="submit" name="delete_item" class="text-red-500"><i data-lucide="trash-2" class="w-3 h-3"></i></button></form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section id="users" class="scroll-mt-20">
            <h2 class="text-2xl font-bold text-slate-800 dark:text-white mb-6 pt-4 border-t border-slate-200 dark:border-slate-700 flex items-center gap-2"><i data-lucide="users" class="text-blue-600 dark:text-blue-400"></i> User Access Control</h2>
            
            <div class="mb-8 bg-white dark:bg-slate-800 p-6 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
                <form method="POST" class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <h3 class="font-bold text-lg text-slate-800 dark:text-white flex items-center gap-2">
                            <i data-lucide="lock" class="w-5 h-5 <?php echo $isStrictMode ? 'text-amber-500' : 'text-slate-400'; ?>"></i> 
                            Strict Access Mode (Whitelist Only)
                        </h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                            When enabled, <strong class="text-amber-600 dark:text-amber-400">ONLY</strong> users in the Allowed list below can access the portal. All others will be blocked.
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                         <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="strict_access_enabled" class="sr-only peer" <?php echo $isStrictMode ? 'checked' : ''; ?> onchange="this.form.submit()">
                            <input type="hidden" name="update_access_mode" value="1">
                            <div class="w-14 h-7 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-amber-300 dark:peer-focus:ring-amber-800 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all dark:border-gray-600 peer-checked:bg-amber-500"></div>
                        </label>
                        <span class="text-sm font-bold <?php echo $isStrictMode ? 'text-amber-600 dark:text-amber-400' : 'text-slate-500'; ?>"><?php echo $isStrictMode ? 'ENABLED' : 'DISABLED'; ?></span>
                    </div>
                </form>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                
                <div class="space-y-4 <?php echo $isStrictMode ? '' : 'opacity-70 grayscale-[0.5] hover:opacity-100 hover:grayscale-0 transition-all'; ?>">
                    <div class="bg-white dark:bg-slate-800 p-6 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 h-fit">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-lg text-green-700 dark:text-green-400 flex items-center gap-2">
                                <i data-lucide="check-circle" class="w-5 h-5"></i> Allowed Users
                            </h3>
                            <span class="text-xs font-mono bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 px-2 py-1 rounded"><?php echo count($allowedUsers); ?></span>
                        </div>
                        <form method="POST" class="space-y-4 mb-6">
                            <div class="grid grid-cols-2 gap-2">
                                <input type="text" name="scholar_no" required placeholder="Scholar No" class="w-full px-3 py-2 border rounded-lg dark:bg-slate-900 dark:text-white text-sm">
                                <input type="text" name="note" required placeholder="Name/Note" class="w-full px-3 py-2 border rounded-lg dark:bg-slate-900 dark:text-white text-sm">
                            </div>
                            <button type="submit" name="allow_user" class="w-full py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg text-sm flex items-center justify-center gap-2">
                                <i data-lucide="plus" class="w-4 h-4"></i> Add to Whitelist
                            </button>
                        </form>

                        <div class="max-h-[300px] overflow-y-auto border-t border-slate-100 dark:border-slate-700 pt-2">
                            <table class="w-full text-sm text-left">
                                <?php if(empty($allowedUsers)): ?>
                                    <tr><td class="py-4 text-center text-slate-400 text-xs">No users in allowlist.</td></tr>
                                <?php else: ?>
                                    <?php foreach($allowedUsers as $user): ?>
                                        <tr class="border-b border-slate-50 dark:border-slate-700/50 last:border-0">
                                            <td class="py-2 font-mono dark:text-white"><?php echo htmlspecialchars($user['id']); ?></td>
                                            <td class="py-2 text-slate-500 dark:text-slate-400 text-xs"><?php echo htmlspecialchars($user['note']); ?></td>
                                            <td class="py-2 text-right">
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="scholar_no_to_remove" value="<?php echo htmlspecialchars($user['id']); ?>">
                                                    <button type="submit" name="remove_allowed_user" class="text-red-500 hover:text-red-700"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="bg-white dark:bg-slate-800 p-6 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 h-fit">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-lg text-red-600 dark:text-red-400 flex items-center gap-2">
                                <i data-lucide="ban" class="w-5 h-5"></i> Banned Users
                            </h3>
                            <span class="text-xs font-mono bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 px-2 py-1 rounded"><?php echo count($bannedUsers); ?></span>
                        </div>
                        <form method="POST" class="space-y-4 mb-6">
                            <div class="grid grid-cols-2 gap-2">
                                <input type="text" name="scholar_no" required placeholder="Scholar No" class="w-full px-3 py-2 border rounded-lg dark:bg-slate-900 dark:text-white text-sm">
                                <input type="text" name="reason" required placeholder="Reason" class="w-full px-3 py-2 border rounded-lg dark:bg-slate-900 dark:text-white text-sm">
                            </div>
                            <button type="submit" name="ban_user" class="w-full py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg text-sm flex items-center justify-center gap-2">
                                <i data-lucide="ban" class="w-4 h-4"></i> Ban User
                            </button>
                        </form>

                        <div class="max-h-[300px] overflow-y-auto border-t border-slate-100 dark:border-slate-700 pt-2">
                            <table class="w-full text-sm text-left">
                                <?php if(empty($bannedUsers)): ?>
                                    <tr><td class="py-4 text-center text-slate-400 text-xs">No banned users.</td></tr>
                                <?php else: ?>
                                    <?php foreach($bannedUsers as $user): ?>
                                        <tr class="border-b border-slate-50 dark:border-slate-700/50 last:border-0">
                                            <td class="py-2 font-mono dark:text-white"><?php echo htmlspecialchars($user['id']); ?></td>
                                            <td class="py-2 text-red-500 text-xs"><?php echo htmlspecialchars($user['reason']); ?></td>
                                            <td class="py-2 text-right">
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="scholar_no_to_remove" value="<?php echo htmlspecialchars($user['id']); ?>">
                                                    <button type="submit" name="unban_user" class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 text-xs font-medium">Unban</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </section>
        
        <section id="messages" class="scroll-mt-20 pb-20">
             <h2 class="text-2xl font-bold text-slate-800 dark:text-white mb-6 pt-4 border-t border-slate-200 dark:border-slate-700 flex items-center gap-2"><i data-lucide="message-square" class="text-blue-600 dark:text-blue-400"></i> Student Messages</h2>
             <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 h-[600px]">
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden flex flex-col">
                    <div class="p-4"><form method="GET" action="#messages" class="flex gap-2"><input type="text" name="chat_user" placeholder="Scholar No" class="w-full px-3 py-2 text-sm border rounded-lg dark:bg-slate-900 dark:text-white"><button type="submit" class="p-2 bg-blue-600 text-white rounded-lg"><i data-lucide="plus" class="w-4 h-4"></i></button></form></div>
                    <div class="overflow-y-auto flex-1">
                        <?php foreach($conversations as $conv): 
                            $userNo = $conv['scholar_no']; 
                            $isUnread = ($conv['sender'] === 'student'); 
                        ?>
                            <a href="?chat_user=<?php echo $userNo; ?>#messages" class="block px-4 py-3 border-b dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 <?php echo ($activeChatUser == $userNo) ? 'bg-blue-50 dark:bg-slate-700' : ''; ?>">
                                <div class="font-medium dark:text-white flex justify-between items-center">
                                    <?php echo htmlspecialchars($userNo); ?>
                                    <?php if($isUnread): ?>
                                        <span class="h-2 w-2 rounded-full bg-red-500 block" title="Needs Reply"></span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-slate-500 truncate <?php echo $isUnread ? 'font-semibold text-slate-700 dark:text-slate-300' : ''; ?>"><?php echo htmlspecialchars($conv['message']); ?></div>
                                <div class="text-[10px] text-slate-400 mt-1">
                                    <?php 
                                    $msgTime = new DateTime($conv['created_at']);
                                    $msgTime->add(new DateInterval('PT13H30M'));
                                    echo $msgTime->format('M d, H:i'); 
                                    ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 flex flex-col">
                    <?php if ($activeChatUser): ?>
                        <div class="flex-1 overflow-y-auto p-4 space-y-4" id="adminChatContainer">
                            <?php foreach($activeMessages as $msg): ?>
                                <div class="flex flex-col <?php echo $msg['sender'] === 'admin' ? 'items-end' : 'items-start'; ?>">
                                    <div class="max-w-[70%] px-4 py-2 rounded-lg text-sm <?php echo $msg['sender'] === 'admin' ? 'bg-blue-600 text-white' : 'bg-slate-100 dark:bg-slate-700 dark:text-white'; ?>">
                                        <?php echo htmlspecialchars($msg['message']); ?>
                                    </div>
                                    <span class="text-xs text-slate-400 mt-1">
                                        <?php 
                                        $msgTime = new DateTime($msg['created_at']);
                                        $msgTime->add(new DateInterval('PT13H30M'));
                                        echo $msgTime->format('M d, H:i'); 
                                        ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="p-4 border-t dark:border-slate-700"><form method="POST" class="flex gap-3"><input type="hidden" name="target_scholar" value="<?php echo htmlspecialchars($activeChatUser); ?>"><input type="text" name="reply_message" placeholder="Reply..." class="flex-1 px-4 py-2 border rounded-lg dark:bg-slate-900 dark:text-white"><button type="submit" name="admin_reply" class="px-6 py-2 bg-blue-600 text-white rounded-lg">Send</button></form></div>
                        <script>const adminChat = document.getElementById('adminChatContainer'); if(adminChat) adminChat.scrollTop = adminChat.scrollHeight;</script>
                    <?php else: ?>
                        <div class="flex-1 flex flex-col items-center justify-center text-slate-400"><p>Select a chat</p></div>
                    <?php endif; ?>
                </div>
             </div>
        </section>

    </main>

    <script>
        lucide.createIcons();
        
        // Dynamic Question Builder Logic
        function addQuestion() {
            const container = document.getElementById('questionsContainer');
            const div = document.createElement('div');
            div.className = 'p-4 bg-slate-50 dark:bg-slate-700/30 rounded-lg border border-slate-200 dark:border-slate-600 relative group animate-[fadeIn_0.3s_ease-out]';
            div.innerHTML = `
                <button type="button" onclick="this.parentElement.remove()" class="absolute top-2 right-2 text-red-400 hover:text-red-600"><i data-lucide="x" class="w-4 h-4"></i></button>
                <div class="space-y-2">
                    <input type="text" name="q_text[]" placeholder="Question Text" class="w-full px-3 py-2 border rounded-lg bg-white dark:bg-slate-800 dark:border-slate-600 dark:text-white text-sm" required>
                    <select name="q_type[]" onchange="toggleOptions(this)" class="w-full px-3 py-2 border rounded-lg bg-white dark:bg-slate-800 dark:border-slate-600 dark:text-white text-sm">
                        <option value="text">Text Answer</option>
                        <option value="mcq">Multiple Choice</option>
                    </select>
                    <input type="text" name="q_options[]" placeholder="Options (comma separated)" class="w-full px-3 py-2 border rounded-lg bg-white dark:bg-slate-800 dark:border-slate-600 dark:text-white text-sm hidden">
                </div>
            `;
            container.appendChild(div);
            lucide.createIcons();
        }

        function toggleOptions(select) {
            const input = select.nextElementSibling;
            if (select.value === 'mcq') {
                input.classList.remove('hidden');
                input.required = true;
            } else {
                input.classList.add('hidden');
                input.required = false;
            }
        }

        // Theme Toggle Logic
        const themeToggleBtn = document.getElementById('themeToggle');
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', () => {
                if (document.documentElement.classList.contains('dark')) {
                    document.documentElement.classList.remove('dark');
                    localStorage.setItem('theme', 'light');
                } else {
                    document.documentElement.classList.add('dark');
                    localStorage.setItem('theme', 'dark');
                }
            });
        }
    </script>
</body>
</html>