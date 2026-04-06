<?php
session_start();

// --- 0.1 GLOBAL MAINTENANCE CHECK (KILL SWITCH) ---
$MAINTENANCE_FILE = 'maintenance_config.json';
if (file_exists($MAINTENANCE_FILE)) {
    $mConfig = json_decode(file_get_contents($MAINTENANCE_FILE), true);
    if (($mConfig['maintenance_mode'] ?? 'OFF') === 'ON') {
        $mReason = $mConfig['reason'] ?? "We are performing scheduled maintenance.";
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Service Unavailable | NITBFreshers</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <script src="https://unpkg.com/lucide@latest"></script>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
            <style>body { font-family: 'Inter', sans-serif; }</style>
        </head>
        <body class="bg-slate-50 flex items-center justify-center min-h-screen p-4">
            <div class="bg-white max-w-md w-full rounded-2xl shadow-xl border border-slate-200 p-8 text-center">
                <div class="w-20 h-20 bg-amber-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i data-lucide="cone" class="w-10 h-10 text-amber-500"></i>
                </div>
                <h1 class="text-2xl font-bold text-slate-800 mb-2">Under Maintenance</h1>
                <p class="text-slate-500 mb-6 text-sm leading-relaxed">
                    The portal is currently offline for system upgrades. Access is temporarily suspended.
                </p>
                <div class="bg-amber-50 border border-amber-100 rounded-xl p-4 text-left mb-6">
                    <p class="text-xs font-bold text-amber-600 uppercase mb-1">Admin Message:</p>
                    <p class="text-sm text-amber-800 font-medium"><?php echo htmlspecialchars($mReason); ?></p>
                </div>
                <button onclick="location.reload()" class="w-full py-3 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl transition-colors">
                    Check Again
                </button>
            </div>
            <script>lucide.createIcons();</script>
        </body>
        </html>
        <?php
        exit; // STOP ALL FURTHER EXECUTION
    }
}

// Check if user is logged in
if (!isset($_SESSION['scholarNo'])) {
    header('Location: index.php');
    exit;
}

// Retrieve session data
$scholarNo = $_SESSION['scholarNo'];
$name = $_SESSION['name'] ?? 'Loading...';
$rollNo = $_SESSION['rollNo'] ?? 'Loading...';
$semester = $_SESSION['semester'] ?? 'Loading...';

// --- AVATAR RANDOMIZATION LOGIC (EXPANDED ZOO) ---
$animals = [
    'Lion', 'Giraffe', 'Panda', 'Koala', 'Eagle', 'Fox', 'Owl', 'Elephant', 
    'Cat', 'Dog', 'Tiger Face', 'Rabbit Face', 'Hamster', 'Unicorn', 'Bear', 'Leopard'
];
$animalIndex = abs(crc32($scholarNo)) % count($animals);
$animalName = $animals[$animalIndex];
$avatarUrl = "https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Animals/" . rawurlencode($animalName) . ".png";

include_once 'db_connection.php'; 
$conn = db_connect();

// --- 4. FETCH STUDENT DETAILS (If Missing) ---
// We need Roll No for Section detection immediately
if ($name === 'Loading...' || $rollNo === 'Loading...' || $semester === 'Loading...') {
    $stmt = $conn->prepare("SELECT name, roll_no, semester FROM students WHERE scholar_no = ?");
    $stmt->bind_param("s", $scholarNo);
    $stmt->execute();
    $stmt->bind_result($name, $rollNo, $semester);
    $stmt->fetch();
    $stmt->close();
    $_SESSION['name'] = $name;
    $_SESSION['rollNo'] = $rollNo;
    $_SESSION['semester'] = $semester;
}

// --- 0.0 SMART CLUSTER REDIRECTION LOGIC (SECTION BASED) ---
// Identifies current host, checks DB mode, and routes A-E vs F-J
$current_host = $_SERVER['HTTP_HOST'];
$my_domain_id = 1; // Default Master

if (strpos($current_host, 'nitbfreshers2') !== false) $my_domain_id = 2;
elseif (strpos($current_host, 'nitbfreshers3') !== false) $my_domain_id = 3;
elseif (strpos($current_host, 'nitbfreshers4') !== false) $my_domain_id = 4;
elseif (strpos($current_host, 'nitbfreshers5') !== false) $my_domain_id = 5;

// Determine Group (ST vs MT) based on Roll No
$is_ST_Group = false; // Default to MT if unknown
if ($rollNo !== 'Loading...' && !empty($rollNo)) {
    $cleanRoll = trim($rollNo);
    // E section or A,B,C,D,E in 3rd char
    $secChar = (strlen($cleanRoll) >= 3) ? strtoupper($cleanRoll[2]) : '';
    if (strpos(strtoupper($cleanRoll), 'E') !== false || in_array($secChar, ['A','B','C','D','E'])) {
        $is_ST_Group = true;
    }
}

try {
    // Check Redirection Settings
    $redirSql = "SELECT mode, target_link_2, target_link_3, target_link_4, target_link_5 FROM redirection_settings WHERE id=1";
    $redirRes = $conn->query($redirSql);
    
    if ($redirRes && $redirRes->num_rows > 0) {
        $row_redir = $redirRes->fetch_assoc();
        $mode = $row_redir['mode'];
        
        $master_link = "https://nitbfreshers.42web.io/userlogin/dashboard.php";
        $link_2 = $row_redir['target_link_2']; // Site 2
        $link_3 = $row_redir['target_link_3']; // Site 3
        $link_4 = $row_redir['target_link_4']; // Site 4
        $link_5 = $row_redir['target_link_5']; // Site 5

        $target_url = "";

        if ($mode === 'off') {
            // Everyone goes to Master (1)
            if ($my_domain_id !== 1) $target_url = $master_link;
        }
        elseif ($mode === '2_3') {
            // ST Group (A-E) -> Site 2
            // MT Group (F-J) -> Site 3
            if ($is_ST_Group) {
                if ($my_domain_id !== 2 && !empty($link_2)) $target_url = $link_2;
            } else {
                if ($my_domain_id !== 3 && !empty($link_3)) $target_url = $link_3;
            }
        }
        elseif ($mode === '4_5') {
            // ST Group (A-E) -> Site 4
            // MT Group (F-J) -> Site 5
            if ($is_ST_Group) {
                if ($my_domain_id !== 4 && !empty($link_4)) $target_url = $link_4;
            } else {
                if ($my_domain_id !== 5 && !empty($link_5)) $target_url = $link_5;
            }
        }

        // Execute Redirect
        if (!empty($target_url)) {
            // Prevent redirect loop if target is current URL (roughly)
            if (strpos($target_url, $current_host) === false) {
                header("Location: " . $target_url);
                exit();
            }
        }
    }
} catch (Exception $e) {
    // Fail silently on DB error, allow page load
}

// --- 0.2 FEEDBACK LOGIC (UPDATED FOR MULTI-QUESTION) ---
$FEEDBACK_CONFIG = 'feedback_config.json';
$FEEDBACK_SUBMISSIONS = 'feedback_submissions.json';
$FEEDBACK_DATA_FILE = 'feedback_data.txt';
$isFeedbackPending = false;
$feedbackSchema = [];

// Initialize Config if missing
if (!file_exists($FEEDBACK_CONFIG)) {
    // Default schema creation
    $defaultSchema = [
        'status' => 'OFF', 
        'schema' => [
            ['id' => uniqid(), 'text' => 'How can we improve?', 'type' => 'text', 'options' => '']
        ]
    ];
    file_put_contents($FEEDBACK_CONFIG, json_encode($defaultSchema));
}

// Load Configuration
$fConfig = json_decode(file_get_contents($FEEDBACK_CONFIG), true);
$feedbackSchema = $fConfig['schema'] ?? [];

// Handle Feedback Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_user_feedback'])) {
    $answers = $_POST['answers'] ?? [];
    $compiledResponse = [];

    // Compile answers based on schema to ensure context
    foreach ($feedbackSchema as $q) {
        $qid = $q['id'];
        $val = isset($answers[$qid]) ? trim($answers[$qid]) : 'Skipped';
        $compiledResponse[] = "Q: " . $q['text'] . " -> " . $val;
    }
    
    $finalString = implode(" | ", $compiledResponse);

    if (!empty($finalString)) {
        // Save Response
        $entry = date('Y-m-d H:i:s') . " | " . $scholarNo . " | " . $name . " | " . str_replace(["\n", "\r"], " ", $finalString) . PHP_EOL;
        file_put_contents($FEEDBACK_DATA_FILE, $entry, FILE_APPEND);
        
        // Mark as Submitted
        $subs = [];
        if (file_exists($FEEDBACK_SUBMISSIONS)) {
            $subs = json_decode(file_get_contents($FEEDBACK_SUBMISSIONS), true);
        }
        if (!in_array($scholarNo, $subs)) {
            $subs[] = $scholarNo;
            file_put_contents($FEEDBACK_SUBMISSIONS, json_encode($subs));
        }
        
        // Reload to clear modal
        header("Location: dashboard.php");
        exit;
    }
}

// Check if Feedback is Required
if (($fConfig['status'] ?? 'OFF') === 'ON' && !empty($feedbackSchema)) {
    $subs = [];
    if (file_exists($FEEDBACK_SUBMISSIONS)) {
        $subs = json_decode(file_get_contents($FEEDBACK_SUBMISSIONS), true);
    }
    if (!in_array($scholarNo, $subs)) {
        $isFeedbackPending = true;
    }
}

// --- 0.3 GLOBAL UPDATE NOTICE LOGIC (NEW FEATURE) ---
$NOTICE_CONFIG_FILE = 'notice_config.json';
$NOTICE_VIEWS_FILE = 'notice_views.json';
$showGlobalNotice = false;
$globalNoticeData = [];

// 1. Check if Notice is Active
if (file_exists($NOTICE_CONFIG_FILE)) {
    $nConfig = json_decode(file_get_contents($NOTICE_CONFIG_FILE), true);
    
    if (($nConfig['status'] ?? 'OFF') === 'ON') {
        // 2. Check if user has already seen THIS specific notice ID
        $nViews = [];
        if (file_exists($NOTICE_VIEWS_FILE)) {
            $nViews = json_decode(file_get_contents($NOTICE_VIEWS_FILE), true);
        }

        $currentNoticeId = $nConfig['id'] ?? 'default_id';
        
        // If scholar entry doesn't exist OR the last seen ID is different from current ID
        if (!isset($nViews[$scholarNo]) || $nViews[$scholarNo] !== $currentNoticeId) {
            $showGlobalNotice = true;
            $globalNoticeData = $nConfig;
        }
    }
}

// 3. Handle Notice Dismissal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dismiss_global_notice'])) {
    $noticeIdToDismiss = $_POST['notice_id'];
    
    $nViews = [];
    if (file_exists($NOTICE_VIEWS_FILE)) {
        $nViews = json_decode(file_get_contents($NOTICE_VIEWS_FILE), true);
    }
    
    // Update user's seen status
    $nViews[$scholarNo] = $noticeIdToDismiss;
    file_put_contents($NOTICE_VIEWS_FILE, json_encode($nViews));
    
    header("Location: dashboard.php");
    exit;
}


// --- 0. TIMEZONE & DATE SETUP (CORRECTED WITH OFFSET) ---
// Server is 13 hours 30 minutes behind, so we add 13H 30M
$serverTime = new DateTime();
$serverTime->add(new DateInterval('PT13H30M'));
$adjustedTodayDate = $serverTime->format('Y-m-d');

// --- 1. CHECK FOR DEFAULT PASSWORD ---
$isDefaultPassword = false;
$pwdStmt = $conn->prepare("SELECT password FROM students WHERE scholar_no = ?");
if ($pwdStmt) {
    $pwdStmt->bind_param("s", $scholarNo);
    $pwdStmt->execute();
    $pwdStmt->bind_result($dbHash);
    if ($pwdStmt->fetch()) {
        // Check if password matches scholar number (Default login)
        if (password_verify($scholarNo, $dbHash) || $dbHash === $scholarNo) {
            $isDefaultPassword = true;
        }
    }
    $pwdStmt->close();
}

// --- 2. FETCH CHAT MESSAGES & CHECK PERMISSIONS ---
$chatHistory = [];
$stmt = $conn->prepare("SELECT sender, message, created_at FROM messages WHERE scholar_no = ? ORDER BY created_at ASC");
$stmt->bind_param("s", $scholarNo);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $chatHistory[] = $row;
}
$stmt->close();

// Determine Messaging Permissions
$lastMessage = end($chatHistory);
$canReply = false;
$statusMessage = "You cannot start a conversation.";

if (empty($chatHistory)) {
    $canReply = true;
    $statusMessage = "Start a conversation...";
} else {
    if ($lastMessage['sender'] === 'admin') {
        $canReply = true;
        $statusMessage = "Type a message...";
    } else {
        $statusMessage = "Waiting for admin reply...";
    }
}

// --- 3. HANDLE MESSAGE SENDING (AJAX SUPPORT) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = isset($_POST['is_ajax']);
    
    // Chat Message
    if (isset($_POST['send_message']) || ($isAjax && isset($_POST['message_content']))) {
        if ($canReply) {
            $msgContent = trim($_POST['message_content']);
            if (!empty($msgContent)) {
                $stmt = $conn->prepare("INSERT INTO messages (scholar_no, sender, message) VALUES (?, 'student', ?)");
                $stmt->bind_param("ss", $scholarNo, $msgContent);
                $stmt->execute();
                $stmt->close();
                
                if ($isAjax) {
                    header('Content-Type: application/json');
                    // Return time with corrected offset
                    $msgTime = new DateTime();
                    $msgTime->add(new DateInterval('PT13H30M'));
                    echo json_encode(['status' => 'success', 'message' => htmlspecialchars($msgContent), 'time' => $msgTime->format('M d, H:i')]);
                    exit; 
                }
                header("Location: dashboard.php?chat=open");
                exit;
            }
        } else {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Reply not allowed']);
                exit;
            }
        }
    }
}

// --- 5. ATTENDANCE LOGIC ---
$todayClasses = [];
$attendanceMap = [];
$sectionChar = '';
$isWeekend = false;
$isHoliday = false;
$holidayName = '';
$subjectAggregates = [];

// Determine Date for Editing
$startDate = '2025-12-22'; // Academic start date
$selectedDate = isset($_GET['date']) ? $_GET['date'] : $adjustedTodayDate;

// Clamp date
if ($selectedDate < $startDate) $selectedDate = $startDate;
if ($selectedDate > $adjustedTodayDate) $selectedDate = $adjustedTodayDate;

$dayOfWeek = date('l', strtotime($selectedDate));

if ($rollNo !== 'Loading...' && !empty($rollNo)) {
    $cleanRoll = trim($rollNo);
    if (strpos(strtoupper($cleanRoll), 'E') !== false) {
        $sectionChar = 'E'; 
    } elseif (strlen($cleanRoll) >= 3) {
        $sectionChar = strtoupper($cleanRoll[2]);
    }

    // A. CHECK FOR HOLIDAY
    $holStmt = $conn->prepare("SELECT description FROM holidays WHERE holiday_date = ?");
    if ($holStmt) {
        $holStmt->bind_param("s", $selectedDate);
        $holStmt->execute();
        $holStmt->bind_result($hName);
        if ($holStmt->fetch()) {
            $isHoliday = true;
            $holidayName = $hName;
        }
        $holStmt->close();
    }

    // B. FETCH TIMETABLE FOR SELECTED DAY
    if ($dayOfWeek === 'Saturday' || $dayOfWeek === 'Sunday') {
        $isWeekend = true;
    } elseif (!$isHoliday) { // Only fetch classes if it's not a holiday
        $stmt = $conn->prepare("SELECT subject_name, start_time, end_time FROM timetable WHERE section = ? AND day_of_week = ? ORDER BY start_time ASC");
        $stmt->bind_param("ss", $sectionChar, $dayOfWeek);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $todayClasses[] = $row;
        }
        $stmt->close();

        // Fetch logs for the selected date
        $stmt = $conn->prepare("SELECT subject_name, status FROM attendance_logs WHERE scholar_no = ? AND log_date = ?");
        $stmt->bind_param("ss", $scholarNo, $selectedDate);
        $stmt->execute();
        $logResult = $stmt->get_result();
        while ($row = $logResult->fetch_assoc()) {
            $attendanceMap[$row['subject_name']] = $row['status'];
        }
        $stmt->close();
    }

    // C. FETCH OVERALL STATISTICS FOR ATTENDANCE POPUP
    if (!empty($sectionChar)) {
        // Get all subjects for this section
        $subStmt = $conn->prepare("SELECT DISTINCT subject_name FROM timetable WHERE section = ?");
        $subStmt->bind_param("s", $sectionChar);
        $subStmt->execute();
        $subRes = $subStmt->get_result();
        while ($row = $subRes->fetch_assoc()) {
            $subjectAggregates[$row['subject_name']] = [
                'Present' => 0, 'Absent' => 0, 'Cancelled' => 0,
                'Percentage' => 0, 'StatusMsg' => 'No Data', 'Color' => 'text-slate-400'
            ];
        }
        $subStmt->close();

        // Count logs
        $aggStmt = $conn->prepare("SELECT subject_name, status, COUNT(*) as count FROM attendance_logs WHERE scholar_no = ? GROUP BY subject_name, status");
        $aggStmt->bind_param("s", $scholarNo);
        $aggStmt->execute();
        $aggRes = $aggStmt->get_result();
        while ($row = $aggRes->fetch_assoc()) {
            if (isset($subjectAggregates[$row['subject_name']])) {
                $subjectAggregates[$row['subject_name']][$row['status']] = (int)$row['count'];
            }
        }
        $aggStmt->close();

        // Calculate Percentages
        foreach ($subjectAggregates as $subj => &$data) {
            $p = $data['Present'];
            $a = $data['Absent'];
            $total = $p + $a; 
            
            if ($total > 0) {
                $percent = ($p / $total) * 100;
                $data['Percentage'] = round($percent, 1);
                
                if ($percent < 75) {
                    $req = ceil((0.75 * $total - $p) / 0.25);
                    $data['StatusMsg'] = "Attend next $req";
                    $data['Color'] = 'text-red-500';
                } else {
                    $canMiss = floor(($p - 0.75 * $total) / 0.75);
                    $data['StatusMsg'] = "Safe to miss $canMiss";
                    $data['Color'] = 'text-green-600';
                }
            } else {
                $data['StatusMsg'] = "New Class";
            }
        }
    }
}

// --- CLOSE DB ---
$conn->close();

// --- 6. ANNOUNCEMENTS FETCHING ---
$ANNOUNCEMENT_FILE = 'announcements.json';
$announcementsList = [];
if (file_exists($ANNOUNCEMENT_FILE)) {
    $announcementsList = json_decode(file_get_contents($ANNOUNCEMENT_FILE), true);
} else {
    // Default fallbacks if file doesn't exist yet
    $announcementsList = [
        ['text' => 'Access PYQs 2024 Bank', 'link' => 'pyqs2024/'],
        ['text' => 'Access PYQs 2025 Bank', 'link' => 'pyqs2025/']
    ];
}

// --- 7. REDIRECTION & BAN CHECKS ---
$REDIRECT_CONFIG_FILE = 'redirection_config.json'; 
if (file_exists($REDIRECT_CONFIG_FILE)) {
    $configData = json_decode(file_get_contents($REDIRECT_CONFIG_FILE), true);
    if (($configData['status'] ?? 'OFF') === 'ON' && !empty($sectionChar)) {
        $stSections = ['A', 'B', 'C', 'D', 'E'];
        $mtSections = ['F', 'G', 'H', 'I', 'J'];
        if (in_array($sectionChar, $stSections) && !empty($configData['url_ae'])) { header("Location: " . $configData['url_ae']); exit; }
        elseif (in_array($sectionChar, $mtSections) && !empty($configData['url_fj'])) { header("Location: " . $configData['url_fj']); exit; }
    }
}

// === STRICT ACCESS CHECK (WHITELIST) ===
$ACCESS_CONFIG_FILE = 'access_config.json';
$ALLOWED_FILE = 'allowed_users.txt';

if (file_exists($ACCESS_CONFIG_FILE)) {
    $accConfig = json_decode(file_get_contents($ACCESS_CONFIG_FILE), true);
    if (($accConfig['mode'] ?? 'OPEN') === 'STRICT') {
        $isAllowed = false;
        if (file_exists($ALLOWED_FILE)) {
            $allowedLines = file($ALLOWED_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($allowedLines as $line) {
                $parts = explode(',', $line);
                if (trim($parts[0]) === (string)$scholarNo) {
                    $isAllowed = true;
                    break;
                }
            }
        }

        if (!$isAllowed) {
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Service Unavailable | NITBFreshers</title>
                <script src="https://cdn.tailwindcss.com"></script>
                <script src="https://unpkg.com/lucide@latest"></script>
                <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
                <style>body { font-family: 'Inter', sans-serif; }</style>
            </head>
            <body class="bg-slate-50 flex items-center justify-center min-h-screen p-4">
                <div class="bg-white max-w-md w-full rounded-2xl shadow-xl border border-slate-200 p-8 text-center">
                    <div class="w-20 h-20 bg-amber-50 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i data-lucide="cone" class="w-10 h-10 text-amber-500"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-slate-800 mb-2">Under Maintenance</h1>
                    <p class="text-slate-500 mb-6 text-sm leading-relaxed">
                        The portal is currently offline for system upgrades. Access is temporarily suspended.
                    </p>
                    <div class="bg-amber-50 border border-amber-100 rounded-xl p-4 text-left mb-6">
                        <p class="text-xs font-bold text-amber-600 uppercase mb-1">Status:</p>
                        <p class="text-sm text-amber-800 font-medium">System Under Upgradation.</p>
                    </div>
                    
                    <a href="logout.php" class="block w-full py-3 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl transition-colors">Sign Out</a>
                </div>
                <script>lucide.createIcons();</script>
            </body>
            </html>
            <?php
            exit;
        }
    }
}

// === BANNED USER LOGIC (UPDATED WITH ADMIN SUPPORT) ===
$bannedUsersFile = 'bannedusers.txt';
if (file_exists($bannedUsersFile)) {
    $fileContents = file($bannedUsersFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($fileContents as $line) {
        $parts = explode(',', $line, 2);
        if (count($parts) > 0 && trim($parts[0]) === (string)$scholarNo) { 
            // Capture the reason
            $banReason = isset($parts[1]) ? trim($parts[1]) : "Violation of terms of service.";
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Access Suspended | NITBFreshers </title>
                <script src="https://cdn.tailwindcss.com"></script>
                <script src="https://unpkg.com/lucide@latest"></script>
                <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
                <style>
                    body { font-family: 'Inter', sans-serif; }
                    .fade-in { animation: fadeIn 0.3s ease-in-out; }
                    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
                    .scrollbar-thin::-webkit-scrollbar { width: 6px; }
                    .scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
                    .scrollbar-thin::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 20px; }
                    .chat-popup { transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); transform-origin: bottom right; }
                    .chat-popup.hidden-chat { opacity: 0; transform: scale(0.9) translateY(20px); pointer-events: none; visibility: hidden; }
                    .chat-popup.visible-chat { opacity: 1; transform: scale(1) translateY(0); pointer-events: auto; visibility: visible; }
                    /* Fix scroll behavior on mobile */
                    .overscroll-contain { overscroll-behavior: contain; }
                </style>
            </head>
            <body class="bg-slate-100 flex items-center justify-center min-h-screen font-sans">
                <div class="bg-white p-8 rounded-2xl shadow-xl max-w-md w-full text-center border border-slate-200">
                    <div class="w-20 h-20 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i data-lucide="shield-alert" class="w-10 h-10 text-red-500"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-slate-800 mb-2">Access Suspended</h2>
                    <p class="text-sm text-slate-500 font-medium mb-4">Hello, <?php echo htmlspecialchars($name); ?></p>
                    <p class="text-slate-500 mb-6 text-sm">Your account (<?php echo htmlspecialchars($scholarNo); ?>) has been temporarily suspended.</p>
                    <div class="bg-red-50 border border-red-100 rounded-lg p-3 mb-6 text-left">
                        <p class="text-xs text-red-500 font-bold uppercase mb-1 flex items-center gap-1"><i data-lucide="info" class="w-3 h-3"></i> Reason</p>
                        <p class="text-sm text-red-700 font-medium break-words"><?php echo htmlspecialchars($banReason); ?></p>
                    </div>
                    
                    <button onclick="toggleChat()" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition-colors mb-3 flex items-center justify-center gap-2">
                        <i data-lucide="message-circle" class="w-4 h-4"></i> Contact Admin
                    </button>

                    <a href="logout.php" class="block w-full py-3 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold rounded-xl transition-colors">Sign Out</a>
                </div>

                <div id="chatPopup" class="hidden-chat fixed bottom-4 right-4 z-[60] w-80 md:w-96 bg-white rounded-2xl shadow-2xl border border-slate-200 flex flex-col overflow-hidden chat-popup transition-colors origin-bottom-right max-h-[500px]">
                    <div class="bg-blue-600 p-4 flex justify-between items-center text-white">
                        <div class="flex flex-col">
                            <div class="flex items-center gap-2">
                                <i data-lucide="message-circle" class="w-5 h-5"></i>
                                <h3 class="font-bold text-sm">Admin Support</h3>
                            </div>
                            <span class="text-[10px] text-blue-100 mt-0.5">Appeals & Support</span>
                        </div>
                        <button onclick="toggleChat()" class="hover:bg-blue-700 p-1 rounded transition-colors">
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>

                    <div class="flex-1 p-4 overflow-y-auto bg-slate-50 scrollbar-thin overscroll-contain" id="chatContainer">
                        <?php if (empty($chatHistory)): ?>
                            <div class="h-full flex flex-col items-center justify-center text-slate-400">
                                <i data-lucide="message-square-plus" class="w-10 h-10 mb-2 opacity-50"></i>
                                <p class="text-xs text-center px-6">No messages yet. Appropriate questions are encouraged.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($chatHistory as $chat): ?>
                                <div class="flex flex-col <?php echo $chat['sender'] === 'student' ? 'items-end' : 'items-start'; ?> mb-3">
                                    <div class="max-w-[85%] px-3 py-2 rounded-xl text-xs <?php echo $chat['sender'] === 'student' ? 'bg-blue-600 text-white rounded-br-none' : 'bg-white border border-slate-200 text-slate-800 rounded-bl-none shadow-sm'; ?>">
                                        <?php echo htmlspecialchars($chat['message']); ?>
                                    </div>
                                    <span class="text-[10px] text-slate-400 mt-1 px-1">
                                        <?php 
                                            try {
                                                // Correct timing for Banned User Chat
                                                $msgTime = new DateTime($chat['created_at']);
                                                $msgTime->add(new DateInterval('PT13H30M'));
                                                echo ($chat['sender'] === 'student' ? 'You' : 'Admin') . ' • ' . $msgTime->format('M d, H:i'); 
                                            } catch (Exception $e) {
                                                echo ($chat['sender'] === 'student' ? 'You' : 'Admin');
                                            }
                                        ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="p-3 border-t border-slate-200 bg-white">
                        <form id="chatForm" method="POST" class="flex gap-2 relative">
                            <input type="text" name="message_content" placeholder="<?php echo $statusMessage; ?>" <?php echo (!$canReply) ? 'disabled' : 'required'; ?> autocomplete="off" class="flex-1 px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none disabled:opacity-60 disabled:cursor-not-allowed">
                            <button type="submit" name="send_message" id="sendBtn" <?php echo (!$canReply) ? 'disabled' : ''; ?> class="p-2 bg-blue-600 hover:bg-blue-700 disabled:bg-slate-400 text-white rounded-lg transition-colors flex items-center justify-center">
                                <i data-lucide="send" class="w-4 h-4"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <script>
                    lucide.createIcons();
                    
                    const chatPopup = document.getElementById('chatPopup');
                    const chatContainer = document.getElementById('chatContainer');
                    const chatForm = document.getElementById('chatForm');
                    
                    function toggleChat() {
                        if(chatPopup.classList.contains('hidden-chat')) {
                            chatPopup.classList.remove('hidden-chat'); chatPopup.classList.add('visible-chat');
                            if(chatContainer) chatContainer.scrollTop = chatContainer.scrollHeight;
                        } else {
                            chatPopup.classList.remove('visible-chat'); chatPopup.classList.add('hidden-chat');
                        }
                    }

                    // Check URL for chat open command (after reload)
                    const urlParams = new URLSearchParams(window.location.search);
                    if (urlParams.get('chat') === 'open') { 
                        toggleChat(); 
                        window.history.replaceState({}, document.title, window.location.pathname); 
                    }

                    if(chatForm) {
                        chatForm.addEventListener('submit', function(e) {
                            e.preventDefault();
                            const input = this.querySelector('input[name="message_content"]');
                            const msg = input.value.trim();
                            if(!msg) return;

                            const formData = new FormData();
                            formData.append('send_message', '1');
                            formData.append('is_ajax', '1');
                            formData.append('message_content', msg);

                            input.disabled = true;
                            const btn = document.getElementById('sendBtn');
                            if(btn) btn.disabled = true;

                            fetch('dashboard.php', { method: 'POST', body: formData })
                            .then(r => r.json())
                            .then(data => {
                                if(data.status === 'success') {
                                    const div = document.createElement('div');
                                    div.className = 'flex flex-col items-end mb-3';
                                    div.innerHTML = `<div class="max-w-[85%] px-3 py-2 rounded-xl text-xs bg-blue-600 text-white rounded-br-none fade-in">${data.message}</div><span class="text-[9px] text-slate-400 mt-1 px-1">You • ${data.time}</span>`;
                                    
                                    const empty = chatContainer.querySelector('.flex-col.items-center');
                                    if(empty) empty.remove();
                                    
                                    chatContainer.appendChild(div);
                                    chatContainer.scrollTop = chatContainer.scrollHeight;
                                    input.value = '';
                                } else { alert(data.message); }
                            })
                            .finally(() => { input.disabled = false; if(btn) btn.disabled = false; input.focus(); });
                        });
                    }
                </script>
            </body>
            </html>
            <?php
            exit; 
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | NITBFreshers</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class', 
        }
    </script>
    
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .fade-in { animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .scrollbar-thin::-webkit-scrollbar { width: 6px; }
        .scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
        .scrollbar-thin::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 20px; }
        .dark .scrollbar-thin::-webkit-scrollbar-thumb { background-color: #475569; }

        /* Popups & Dropdowns */
        .chat-popup { transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); transform-origin: bottom right; }
        .chat-popup.hidden-chat { opacity: 0; transform: scale(0.9) translateY(20px); pointer-events: none; visibility: hidden; }
        .chat-popup.visible-chat { opacity: 1; transform: scale(1) translateY(0); pointer-events: auto; visibility: visible; }
        
        .dropdown-menu { transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1); transform-origin: top right; }
        .dropdown-menu.hidden-menu { opacity: 0; transform: scale(0.95) translateY(-10px); pointer-events: none; visibility: hidden; }
        .dropdown-menu.visible-menu { opacity: 1; transform: scale(1) translateY(0); pointer-events: auto; visibility: visible; }
        
        /* Modal Blur */
        .modal-blur { backdrop-filter: blur(5px); }

        /* Mobile Scroll Fix: Prevents pull-to-refresh and body scroll chaining */
        .overscroll-contain { overscroll-behavior: contain; }
    </style>
</head>

<body class="bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 min-h-screen flex flex-col transition-colors duration-200">

    <?php if ($isFeedbackPending): ?>
    <div class="fixed inset-0 z-[100] bg-slate-900/80 modal-blur flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-slate-200 dark:border-slate-700 animate-[bounce_0.5s_ease-out] max-h-[90vh] flex flex-col">
            <div class="bg-blue-600 p-6 text-center shrink-0">
                <i data-lucide="message-square-heart" class="w-12 h-12 text-white mx-auto mb-2"></i>
                <h2 class="text-2xl font-bold text-white">We Value Your Feedback</h2>
                <p class="text-blue-100 text-sm mt-1">Help us improve the portal experience.</p>
            </div>
            <div class="p-8 overflow-y-auto scrollbar-thin">
                <form method="POST" class="space-y-6">
                    <?php foreach ($feedbackSchema as $q): ?>
                        <div class="text-left">
                            <p class="font-bold text-slate-700 dark:text-slate-200 text-sm mb-3"><?php echo htmlspecialchars($q['text']); ?></p>
                            
                            <?php if ($q['type'] === 'mcq'): ?>
                                <div class="space-y-2 bg-slate-50 dark:bg-slate-900 p-3 rounded-xl border border-slate-100 dark:border-slate-700">
                                    <?php 
                                    $opts = explode(',', $q['options']); 
                                    foreach ($opts as $opt): $opt = trim($opt);
                                    if(empty($opt)) continue;
                                    ?>
                                        <label class="flex items-center space-x-3 cursor-pointer p-2 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-colors">
                                            <input type="radio" name="answers[<?php echo $q['id']; ?>]" value="<?php echo htmlspecialchars($opt); ?>" required class="form-radio text-blue-600 w-4 h-4 focus:ring-blue-500 bg-white dark:bg-slate-700 border-slate-300 dark:border-slate-600">
                                            <span class="text-sm text-slate-600 dark:text-slate-300"><?php echo htmlspecialchars($opt); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <textarea name="answers[<?php echo $q['id']; ?>]" rows="3" required class="w-full p-4 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none text-slate-800 dark:text-white resize-none text-sm placeholder-slate-400" placeholder="Your answer..."></textarea>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <button type="submit" name="submit_user_feedback" class="w-full py-3.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition-colors shadow-lg shadow-blue-600/30 flex items-center justify-center gap-2 mt-4">
                        Submit Feedback <i data-lucide="send" class="w-4 h-4"></i>
                    </button>
                </form>
                <p class="text-center text-xs text-slate-400 mt-4">
                    <i data-lucide="lock" class="w-3 h-3 inline mr-1"></i> Dashboard access is locked until feedback is submitted.
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($showGlobalNotice): ?>
    <div class="fixed inset-0 z-[100] bg-black/60 modal-blur flex items-center justify-center p-4 transition-opacity duration-300">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md p-0 overflow-hidden border border-slate-200 dark:border-slate-700 animate-[bounce_0.5s_ease-out]">
            <div class="bg-indigo-600 p-6 flex flex-col items-center justify-center relative overflow-hidden">
                <div class="absolute -top-10 -right-10 w-32 h-32 bg-indigo-500 rounded-full blur-2xl opacity-50"></div>
                <div class="relative z-10 p-3 bg-white/20 backdrop-blur-md rounded-full mb-3">
                    <i data-lucide="megaphone" class="w-8 h-8 text-white"></i>
                </div>
                <h2 class="relative z-10 text-xl font-bold text-white text-center"><?php echo htmlspecialchars($globalNoticeData['title'] ?? 'New Update'); ?></h2>
            </div>
            
            <div class="p-6">
                <div class="prose dark:prose-invert prose-sm max-w-none text-slate-600 dark:text-slate-300 mb-6 text-center">
                    <?php echo nl2br(htmlspecialchars($globalNoticeData['message'] ?? 'We have updated the portal.')); ?>
                </div>

                <form method="POST">
                    <input type="hidden" name="notice_id" value="<?php echo htmlspecialchars($globalNoticeData['id']); ?>">
                    <button type="submit" name="dismiss_global_notice" class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition-all shadow-lg shadow-indigo-600/20 active:scale-95 flex items-center justify-center gap-2">
                        Got it, Thanks! <i data-lucide="check" class="w-4 h-4"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <nav class="sticky top-0 z-50 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 shadow-sm transition-colors duration-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center gap-3">
                    <img src="./images/logo.png" alt="MANIT Logo" class="h-10 w-auto object-contain">
                    <div class="flex flex-col ml-1">
                        <h1 class="font-bold text-lg leading-tight text-slate-800 dark:text-white">NITB<span class="text-red-500">Freshers</span></h1>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium hidden md:block">Study Portal</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 sm:gap-4">
                    
                    <button id="themeToggle" class="p-2 rounded-full text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                        <span class="hidden dark:block"><i data-lucide="sun" class="w-5 h-5"></i></span>
                        <span class="block dark:hidden"><i data-lucide="moon" class="w-5 h-5"></i></span>
                    </button>

                    <div class="relative ml-2" id="profileContainer">
                        <button type="button" id="profileButton" class="relative flex items-center focus:outline-none cursor-pointer">
                            <div class="h-9 w-9 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden 
                                <?php echo $isDefaultPassword ? 'ring-2 ring-yellow-500 ring-offset-2 animate-pulse' : 'border-2 border-slate-200 dark:border-slate-600 hover:border-blue-50 dark:hover:border-blue-400'; ?> 
                                transition-colors">
                                <!-- UPDATED AVATAR SRC WITH EXPANDED ANIMAL ZOO -->
                                <img src="<?php echo $avatarUrl; ?>" alt="Avatar" class="h-full w-full object-cover">
                            </div>
                            
                            <span id="profileRedDot" class="hidden absolute top-0 right-0 h-3 w-3 bg-red-500 border-2 border-white dark:border-slate-800 rounded-full animate-pulse z-10"></span>
                        </button>

                        <div id="profileDropdown" class="dropdown-menu hidden-menu absolute right-0 mt-2 w-72 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-100 dark:border-slate-700 z-50 overflow-hidden ring-1 ring-black ring-opacity-5">
                            <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800">
                                <p class="text-sm font-bold text-slate-900 dark:text-white truncate"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Student'); ?></p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Scholar: <?php echo htmlspecialchars($scholarNo); ?></p>
                            </div>
                            <div class="px-5 py-3 space-y-2">
                                <div class="flex justify-between text-xs">
                                    <span class="text-slate-500 dark:text-slate-400">Roll No</span>
                                    <span class="font-semibold text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($_SESSION['rollNo'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="flex justify-between text-xs">
                                    <span class="text-slate-500 dark:text-slate-400">Semester</span>
                                    <span class="font-semibold text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($_SESSION['semester'] ?? 'N/A'); ?></span>
                                </div>
                            </div>
                            
                            <div class="border-t border-slate-100 dark:border-slate-700"></div>
                            
                            <div class="py-1">
                                <button type="button" onclick="openAttendanceFromDropdown()" class="w-full flex items-center px-5 py-2.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 hover:text-blue-600 dark:hover:text-blue-400 transition-colors text-left group cursor-pointer border-b border-slate-100 dark:border-slate-700">
                                    <div class="relative mr-3"><i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i></div>
                                    <span class="font-medium">Attendance Tracker</span>
                                </button>

                                <button type="button" onclick="openChatFromDropdown()" class="w-full flex items-center px-5 py-2.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 hover:text-blue-600 dark:hover:text-blue-400 transition-colors text-left group cursor-pointer">
                                    <div class="relative mr-3">
                                        <i data-lucide="message-circle" class="w-4 h-4"></i>
                                        <span id="chatRedDot" class="hidden absolute -top-1 -right-1 h-2.5 w-2.5 bg-red-500 border-2 border-white dark:border-slate-800 rounded-full"></span>
                                    </div>
                                    Admin Support
                                </button>

                                <button type="button" onclick="openUploadModalFromDropdown()" class="w-full flex items-center px-5 py-2.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 hover:text-blue-600 dark:hover:text-blue-400 transition-colors text-left group cursor-pointer">
                                    <div class="relative mr-3">
                                        <i data-lucide="upload-cloud" class="w-4 h-4"></i>
                                    </div>
                                    Contribute
                                </button>
                            </div>

                            <div class="px-5 py-2 border-t border-slate-100 dark:border-slate-700 bg-slate-50/30 dark:bg-slate-800/30">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Quick Links</p>
                                <div class="space-y-1">
                                    <a href="http://manit.ac.in" target="_blank" class="flex items-center text-xs font-medium text-slate-600 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                        <i data-lucide="globe" class="w-3 h-3 mr-2"></i> Main Website
                                    </a>
                                    <a href="http://students.manit.ac.in" target="_blank" class="flex items-center text-xs font-medium text-slate-600 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                        <i data-lucide="layout-grid" class="w-3 h-3 mr-2"></i> ERP Portal
                                    </a>
                                </div>
                            </div>

                            <?php if($isDefaultPassword): ?>
                            <div class="px-5 py-3 bg-amber-50 dark:bg-amber-900/20 border-t border-b border-amber-100 dark:border-amber-900/30">
                                <div class="flex items-start gap-2">
                                    <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-500 shrink-0 mt-0.5 animate-bounce"></i>
                                    <div>
                                        <p class="text-xs font-bold text-amber-700 dark:text-amber-400">Security Alert</p>
                                        <p class="text-[10px] text-amber-600 dark:text-amber-500 leading-tight mt-0.5">
                                            You are using the default password. Please change it immediately.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="py-1 <?php echo !$isDefaultPassword ? 'border-t border-slate-100 dark:border-slate-700' : ''; ?>">
                                <a href="change_password.php" class="flex items-center px-5 py-2.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 hover:text-blue-600 dark:hover:text-blue-400 transition-colors <?php echo $isDefaultPassword ? 'animate-pulse text-amber-600 font-bold' : ''; ?>">
                                    <i data-lucide="key-round" class="w-4 h-4 mr-3"></i> Change Password
                                </a>
                                <a href="logout.php" class="flex items-center px-5 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                    <i data-lucide="log-out" class="w-4 h-4 mr-3"></i> Sign Out
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div id="uploadModal" class="fixed inset-0 z-[70] hidden bg-black/50 backdrop-blur-sm flex items-center justify-center opacity-0 transition-opacity duration-300">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md p-6 transform scale-95 transition-transform duration-300" id="uploadModalContent">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-slate-800 dark:text-white">Contribute Material</h3>
                <button onclick="toggleUploadModal(false)" class="text-slate-400 hover:text-red-500 transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            
            <form id="uploadForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 dark:text-slate-300 mb-1">Group</label>
                    <select name="group" id="uploadGroup" class="w-full p-2.5 bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none dark:text-white">
                        <option value="MT">MT Group</option>
                        <option value="ST">ST Group</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 dark:text-slate-300 mb-1">Subject</label>
                    <input type="text" name="subject" id="uploadSubject" placeholder="e.g., Mathematics-I" required
                        class="w-full p-2.5 bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none dark:text-white">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 dark:text-slate-300 mb-1">Folder / Unit (Optional)</label>
                    <input type="text" name="folder" id="uploadFolder" placeholder="e.g., Unit-1 (Suggested)"
                        class="w-full p-2.5 bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none dark:text-white">
                </div>

                <div class="relative border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-xl p-6 text-center hover:border-blue-500 transition-colors bg-slate-50 dark:bg-slate-900/50">
                    <input type="file" name="file" id="fileInput" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" required>
                    <div id="fileLabelContent">
                        <i data-lucide="cloud-upload" class="w-10 h-10 text-blue-500 mx-auto mb-2"></i>
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Click to upload or drag file</p>
                        <p class="text-xs text-slate-500 mt-1">PDF, DOCX, IMG (Max 10MB)</p>
                    </div>
                    <p id="fileNameDisplay" class="hidden text-sm font-bold text-blue-600 dark:text-blue-400 mt-2 truncate"></p>
                </div>

                <div id="uploadProgressContainer" class="hidden w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2.5">
                    <div id="uploadProgressBar" class="bg-blue-600 h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>

                <div id="uploadStatusMsg" class="text-xs text-center h-4"></div>

                <button type="submit" id="btnSubmitUpload" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors flex items-center justify-center gap-2">
                    <i data-lucide="upload" class="w-4 h-4"></i> Submit for Approval
                </button>
            </form>
        </div>
    </div>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 relative flex-1 w-full">
        
        <div id="announcementsWidget" class="mb-6 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-900/50 rounded-xl p-4 relative shadow-sm transition-all duration-300">
            <!-- Increased visibility for close button -->
            <button onclick="dismissBanner()" class="absolute right-3 top-3 bg-white/60 dark:bg-black/30 hover:bg-white dark:hover:bg-slate-700 text-amber-900 dark:text-amber-100 p-1 rounded-full transition-colors shadow-sm z-10">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
            <div class="flex items-start gap-4 pr-6">
                <div class="p-2 bg-amber-100 dark:bg-amber-900/40 rounded-lg shrink-0">
                    <i data-lucide="bell" class="w-5 h-5 text-amber-600 dark:text-amber-500"></i>
                </div>
                <div>
                    <h3 class="font-bold text-amber-900 dark:text-amber-200 text-sm mb-1">Latest Updates</h3>
                    <div class="text-sm text-amber-800 dark:text-amber-300/80 flex flex-wrap gap-x-4 gap-y-1">
                        <?php if(!empty($announcementsList)): ?>
                            <?php foreach($announcementsList as $ann): ?>
                                <span>• <?php echo htmlspecialchars($ann['text']); ?>: <a href="<?php echo htmlspecialchars($ann['link']); ?>" target="_blank" class="font-semibold text-amber-700 dark:text-amber-100 hover:underline">Click here</a></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="italic text-amber-700/50">No updates currently.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-6">
            <div class="space-y-6">
                <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 transition-colors">
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-6 border-b border-slate-100 dark:border-slate-700 pb-4">
                        <div>
                            <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Study Resources</h2>
                            <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Select your group to access course materials</p>
                        </div>
                        <div class="flex items-center gap-3 w-full sm:w-auto">
                            <label for="group" class="text-sm font-medium text-slate-600 dark:text-slate-300 whitespace-nowrap">Select Group:</label>
                            <div class="relative flex-1 sm:flex-none">
                                <select id="group" onchange="updateSubjects()" class="appearance-none bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-900 dark:text-white text-sm rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full pl-4 pr-10 py-2.5 font-semibold shadow-sm transition-all hover:border-blue-400 dark:hover:border-blue-500 cursor-pointer min-w-[150px]">
                                    <option value="None" class="bg-white dark:bg-slate-800">Choose Group...</option>
                                    <option value="MT" class="bg-white dark:bg-slate-800">MT Group</option>
                                    <option value="ST" class="bg-white dark:bg-slate-800">ST Group</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-500 dark:text-slate-400">
                                    <i data-lucide="chevron-down" class="w-4 h-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="subjects-section" style="display:none;">
                        <div id="subjectsContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5"></div>
                    </div>

                    <div id="emptyState" class="text-center py-16">
                        <div class="bg-slate-50 dark:bg-slate-700/50 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4 border border-slate-100 dark:border-slate-600">
                            <i data-lucide="folder-open" class="w-10 h-10 text-slate-400 dark:text-slate-500"></i>
                        </div>
                        <h3 class="text-lg text-slate-900 dark:text-white font-medium">No Group Selected</h3>
                        <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Please select your group (MT/ST) to view subjects.</p>
                    </div>

                    <div id="resourceSelectionPanel" class="hidden fade-in">
                        <button onclick="goBackToSubjectPanel()" class="group mb-6 flex items-center gap-2.5 px-3 py-2 -ml-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-all">
                            <div class="w-8 h-8 rounded-full bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 flex items-center justify-center text-slate-500 dark:text-slate-400 group-hover:border-blue-500 dark:group-hover:border-blue-400 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors shadow-sm">
                                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                            </div>
                            <span class="text-sm font-semibold text-slate-600 dark:text-slate-300 group-hover:text-blue-700 dark:group-hover:text-blue-400 transition-colors">Back to Subjects</span>
                        </button>
                        
                        <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-4" id="resourceTitle">Select a Folder</h3>
                        <div id="resourceDisplayContainer" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"></div>
                    </div>

                    <div id="folderDisplayPanel" class="hidden fade-in">
                        <div class="flex flex-col gap-4 mb-6">
                            <div class="flex items-center justify-between">
                                <button onclick="goBackToResourceSelectionPanel()" class="group flex items-center gap-2.5 px-3 py-2 -ml-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-all">
                                    <div class="w-8 h-8 rounded-full bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 flex items-center justify-center text-slate-500 dark:text-slate-400 group-hover:border-blue-500 dark:group-hover:border-blue-400 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors shadow-sm">
                                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                                    </div>
                                    <span class="text-sm font-semibold text-slate-600 dark:text-slate-300 group-hover:text-blue-700 dark:group-hover:text-blue-400 transition-colors">Back to Folders</span>
                                </button>
                            </div>
                            
                            <!-- Breadcrumb Directory Display (Cleaned) -->
                            <div id="folderBreadcrumb" class="px-2 py-1 bg-slate-50 dark:bg-slate-700/30 rounded-lg text-sm text-slate-500 dark:text-slate-400 font-medium flex items-center flex-wrap gap-2 border border-slate-100 dark:border-slate-700/50">
                                <!-- Populated by JS -->
                            </div>
                        </div>

                        <div id="folderContent" class="space-y-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="mt-12 py-8 bg-white dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 text-center transition-colors">
        <p class="text-slate-500 dark:text-slate-400 text-sm">Version - 3.0.5.1 | © <?php echo date("Y"); ?> NITBFreshers Portal.</p>
    </footer>

    <div id="attendanceModal" class="fixed inset-0 z-[70] hidden bg-black/50 backdrop-blur-sm flex items-center justify-center opacity-0 transition-opacity duration-300">
        <div id="attendanceModalContent" class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-lg mx-4 flex flex-col max-h-[85vh] transform scale-95 transition-transform duration-300 overflow-hidden">
            <div class="bg-blue-600 p-4 flex justify-between items-center text-white shrink-0">
                <div class="flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-5 h-5"></i>
                    <h3 class="font-bold text-lg">Attendance Tracker</h3>
                </div>
                <button onclick="toggleAttendance(false)" class="hover:bg-blue-700 p-1 rounded transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            
            <!-- Added overscroll-contain for mobile scroll fix -->
            <div class="flex-1 p-4 overflow-y-auto bg-slate-50 dark:bg-slate-900 scrollbar-thin overscroll-contain">
                
                <div class="mb-6">
                    <h4 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-3">Modify Daily Log</h4>
                    
                    <div class="flex justify-between items-center mb-4 gap-2">
                        <input type="date" id="attendanceDate" value="<?php echo $selectedDate; ?>" min="2025-12-22" max="<?php echo $adjustedTodayDate; ?>" onchange="changeAttendanceDate(this.value)" class="flex-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 text-xs rounded-lg p-2 focus:ring-1 focus:ring-blue-500 outline-none">
                        <span class="px-2 py-1.5 bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300 rounded text-[10px] font-bold border border-blue-200 dark:border-blue-800 whitespace-nowrap">Sec <?php echo $sectionChar; ?></span>
                    </div>

                    <?php if ($isWeekend): ?>
                        <div class="text-center py-4 text-slate-400 text-xs italic">Weekend - No Classes</div>
                    <?php elseif ($isHoliday): ?>
                        <div class="text-center py-4 text-slate-400 text-xs italic">
                            <span class="block font-bold text-amber-500 mb-1">Holiday</span>
                            <?php echo htmlspecialchars($holidayName); ?>
                        </div>
                    <?php elseif (empty($todayClasses)): ?>
                        <div class="text-center py-4 text-slate-400 text-xs italic">No timetable found.</div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($todayClasses as $class): ?>
                                <?php 
                                    $safeId = md5($class['subject_name']); 
                                    $alreadyMarked = isset($attendanceMap[$class['subject_name']]);
                                    $status = $alreadyMarked ? $attendanceMap[$class['subject_name']] : '';
                                ?>
                                <div class="p-3 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                                    <div class="mb-2">
                                        <h4 class="font-bold text-slate-800 dark:text-white text-xs"><?php echo htmlspecialchars($class['subject_name']); ?></h4>
                                        <div class="flex justify-between items-center mt-1">
                                            <div class="flex items-center gap-1">
                                                <i data-lucide="clock" class="w-3 h-3 text-slate-400"></i>
                                                <span class="text-[10px] text-slate-500">
                                                    <?php echo date('h:i A', strtotime($class['start_time'])); ?> - <?php echo date('h:i A', strtotime($class['end_time'])); ?>
                                                </span>
                                            </div>
                                            <div id="status-<?php echo $safeId; ?>" class="text-[10px]">
                                                <?php if ($alreadyMarked): ?>
                                                    <span class="<?php echo ($status=='Present'?'text-green-600':($status=='Absent'?'text-red-500':'text-slate-500')); ?> font-bold">
                                                        Marked: <?php echo $status; ?>
                                                    </span>
                                                <?php else: ?><span class="text-slate-400 italic">Pending</span><?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex gap-1 justify-between" id="btns-<?php echo $safeId; ?>">
                                        <button onclick="markAttendance('<?php echo $class['subject_name']; ?>', 'Present', '<?php echo $safeId; ?>', '<?php echo $selectedDate; ?>')" class="flex-1 py-1 bg-green-50 hover:bg-green-100 text-green-700 border border-green-200 rounded text-[10px] font-bold transition-colors">P</button>
                                        <button onclick="markAttendance('<?php echo $class['subject_name']; ?>', 'Absent', '<?php echo $safeId; ?>', '<?php echo $selectedDate; ?>')" class="flex-1 py-1 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 rounded text-[10px] font-bold transition-colors">A</button>
                                        <button onclick="markAttendance('<?php echo $class['subject_name']; ?>', 'Cancelled', '<?php echo $safeId; ?>', '<?php echo $selectedDate; ?>')" class="flex-1 py-1 bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200 rounded text-[10px] font-bold transition-colors">C</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="border-t border-slate-200 dark:border-slate-700 pt-4">
                    <h4 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-3">Overall Performance</h4>
                    <div id="attendanceChartsContainer" class="space-y-3">
                        </div>
                </div>

            </div>
        </div>
    </div>

    <div id="chatPopup" class="hidden-chat fixed bottom-4 right-4 z-[60] w-80 md:w-96 bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 flex flex-col overflow-hidden chat-popup transition-colors origin-bottom-right max-h-[500px]">
        <div class="bg-blue-600 p-4 flex justify-between items-center text-white">
            <div class="flex flex-col">
                <div class="flex items-center gap-2">
                    <i data-lucide="message-circle" class="w-5 h-5"></i>
                    <h3 class="font-bold text-sm">Admin Support</h3>
                </div>
                <span class="text-[10px] text-blue-100 mt-0.5">Replies usually within 24 hrs</span>
            </div>
            <button onclick="toggleChat()" class="hover:bg-blue-700 p-1 rounded transition-colors">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <!-- Added overscroll-contain for mobile scroll fix -->
        <div class="flex-1 p-4 overflow-y-auto bg-slate-50 dark:bg-slate-900 scrollbar-thin h-80 overscroll-contain" id="chatContainer">
            <?php if (empty($chatHistory)): ?>
                <div class="h-full flex flex-col items-center justify-center text-slate-400">
                    <i data-lucide="message-square-plus" class="w-10 h-10 mb-2 opacity-50"></i>
                    <p class="text-xs text-center px-6">No messages yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($chatHistory as $chat): ?>
                    <div class="flex flex-col <?php echo $chat['sender'] === 'student' ? 'items-end' : 'items-start'; ?> mb-3">
                        <div class="max-w-[85%] px-3 py-2 rounded-xl text-xs <?php echo $chat['sender'] === 'student' ? 'bg-blue-600 text-white rounded-br-none' : 'bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-800 dark:text-slate-200 rounded-bl-none shadow-sm'; ?>">
                            <?php echo htmlspecialchars($chat['message']); ?>
                        </div>
                        <span class="text-[10px] text-slate-400 mt-1 px-1">
                            <?php 
                                try {
                                    // Correct timing with +13h 30m offset
                                    $msgTime = new DateTime($chat['created_at']);
                                    $msgTime->add(new DateInterval('PT13H30M'));
                                    echo ($chat['sender'] === 'student' ? 'You' : 'Admin') . ' • ' . $msgTime->format('M d, H:i'); 
                                } catch (Exception $e) {
                                    echo ($chat['sender'] === 'student' ? 'You' : 'Admin');
                                }
                            ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="p-3 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
            <form id="chatForm" method="POST" class="flex gap-2 relative">
                <input type="text" name="message_content" placeholder="<?php echo $statusMessage; ?>" <?php echo (!$canReply) ? 'disabled' : 'required'; ?> autocomplete="off" class="flex-1 px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none dark:text-white dark:placeholder-slate-500 disabled:opacity-60 disabled:cursor-not-allowed">
                <button type="submit" name="send_message" id="sendBtn" <?php echo (!$canReply) ? 'disabled' : ''; ?> class="p-2 bg-blue-600 hover:bg-blue-700 disabled:bg-slate-400 text-white rounded-lg transition-colors flex items-center justify-center">
                    <i data-lucide="send" class="w-4 h-4"></i>
                </button>
            </form>
        </div>
    </div>

    <script>
        const USER_ROLL_NO = "<?php echo htmlspecialchars((string)$rollNo); ?>";
        const TOTAL_MESSAGES = <?php echo count($chatHistory); ?>;
        const LAST_SENDER = "<?php echo !empty($chatHistory) ? end($chatHistory)['sender'] : ''; ?>";
        const SUBJECT_DATA = <?php echo json_encode($subjectAggregates); ?>;
    </script>

    <script>
        lucide.createIcons();

        // --- 1. THEME LOGIC ---
        function initTheme() {
            const themeToggleBtn = document.getElementById('themeToggle');
            if (!themeToggleBtn) return;
            if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
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

        // --- 2. DROPDOWNS & MODALS ---
        const profileButton = document.getElementById('profileButton');
        const profileDropdown = document.getElementById('profileDropdown');

        if(profileButton && profileDropdown) {
            profileButton.addEventListener('click', (e) => {
                e.stopPropagation();
                if(profileDropdown.classList.contains('hidden-menu')) {
                    profileDropdown.classList.remove('hidden-menu'); profileDropdown.classList.add('visible-menu');
                } else {
                    profileDropdown.classList.remove('visible-menu'); profileDropdown.classList.add('hidden-menu');
                }
            });
            document.addEventListener('click', () => {
                profileDropdown.classList.remove('visible-menu'); profileDropdown.classList.add('hidden-menu');
            });
        }

        // --- ANNOUNCEMENT BANNER LOGIC ---
        let isBannerManuallyDismissed = false;

        function dismissBanner() {
            const banner = document.getElementById('announcementsWidget'); 
            if(banner) {
                banner.style.opacity = '0';
                banner.style.transform = 'translateY(-10px)';
                setTimeout(() => { banner.style.display = 'none'; }, 300);
                isBannerManuallyDismissed = true;
            }
        }

        // --- 3. ATTENDANCE LOGIC (MODAL) ---
        window.toggleAttendance = function(show) {
            const modal = document.getElementById('attendanceModal');
            const content = document.getElementById('attendanceModalContent');
            if (!modal || !content) return;
            
            if (show === undefined) {
                show = modal.classList.contains('hidden');
            }

            if (show) {
                // Lock body scroll to prevent background scrolling/refresh on mobile
                document.body.style.overflow = 'hidden';

                modal.classList.remove('hidden');
                setTimeout(() => {
                    modal.classList.remove('opacity-0');
                    content.classList.remove('scale-95');
                    content.classList.add('scale-100');
                }, 10);
                
                const chatPopup = document.getElementById('chatPopup');
                if (chatPopup && !chatPopup.classList.contains('hidden-chat')) {
                    toggleChat();
                }
            } else {
                // Unlock body scroll
                document.body.style.overflow = '';

                modal.classList.add('opacity-0');
                content.classList.remove('scale-100');
                content.classList.add('scale-95');
                setTimeout(() => {
                    modal.classList.add('hidden');
                }, 300);
            }
        };

        window.openAttendanceFromDropdown = function() { 
            toggleAttendance(true); 
            if(profileDropdown) { profileDropdown.classList.remove('visible-menu'); profileDropdown.classList.add('hidden-menu'); } 
        };

        window.changeAttendanceDate = function(date) {
            const url = new URL(window.location.href);
            url.searchParams.set('date', date);
            url.searchParams.set('tracker', 'open'); 
            window.location.href = url.toString();
        };

        window.markAttendance = function(subject, status, safeId, dateOverride) {
            const formData = new FormData();
            formData.append('action', 'mark_attendance');
            formData.append('subject', subject);
            formData.append('status', status);
            if (dateOverride) formData.append('date', dateOverride);

            const statusDiv = document.getElementById('status-' + safeId);
            if(statusDiv) statusDiv.innerHTML = '<span class="text-[10px] text-blue-500 italic">Updating...</span>';

            fetch('attendance_handler.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    if(statusDiv) {
                        let colorClass = data.percent < 75 ? 'text-red-500' : 'text-green-600';
                        statusDiv.innerHTML = `<span class="font-bold ${colorClass}">${data.percent}%</span> <span class="text-[10px] font-bold text-blue-600 ml-1">(${status})</span>`;
                    }
                } else { alert("Error: " + data.message); }
            })
            .catch(err => console.error("Attendance Error:", err));
        };

        // --- 4. CHAT LOGIC ---
        const chatPopup = document.getElementById('chatPopup');
        const chatContainer = document.getElementById('chatContainer');
        const chatForm = document.getElementById('chatForm');
        const chatRedDot = document.getElementById('chatRedDot');
        const profileRedDot = document.getElementById('profileRedDot'); 
        const LOCAL_STORAGE_CHAT_KEY = 'chat_seen_count_<?php echo $scholarNo; ?>';

        window.toggleChat = function() {
            if(chatPopup.classList.contains('hidden-chat')) {
                chatPopup.classList.remove('hidden-chat'); chatPopup.classList.add('visible-chat');
                if(chatContainer) chatContainer.scrollTop = chatContainer.scrollHeight;
                localStorage.setItem(LOCAL_STORAGE_CHAT_KEY, TOTAL_MESSAGES);
                
                // Hide both dots
                if(chatRedDot) chatRedDot.classList.add('hidden');
                if(profileRedDot) profileRedDot.classList.add('hidden');
            } else {
                chatPopup.classList.remove('visible-chat'); chatPopup.classList.add('hidden-chat');
            }
        };

        window.openChatFromDropdown = function() { toggleChat(); if(profileDropdown) { profileDropdown.classList.remove('visible-menu'); profileDropdown.classList.add('hidden-menu'); } };

        if(chatForm) {
            chatForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const input = this.querySelector('input[name="message_content"]');
                const msg = input.value.trim();
                if(!msg) return;

                const formData = new FormData();
                formData.append('send_message', '1');
                formData.append('is_ajax', '1');
                formData.append('message_content', msg);

                input.disabled = true;
                const btn = document.getElementById('sendBtn');
                if(btn) btn.disabled = true;

                fetch('dashboard.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if(data.status === 'success') {
                        const div = document.createElement('div');
                        div.className = 'flex flex-col items-end mb-3';
                        div.innerHTML = `<div class="max-w-[85%] px-3 py-2 rounded-xl text-xs bg-blue-600 text-white rounded-br-none fade-in">${data.message}</div><span class="text-[9px] text-slate-400 mt-1 px-1">You • ${data.time}</span>`;
                        
                        const empty = chatContainer.querySelector('.flex-col.items-center');
                        if(empty) empty.remove();
                        
                        chatContainer.appendChild(div);
                        chatContainer.scrollTop = chatContainer.scrollHeight;
                        input.value = '';
                    } else { alert(data.message); }
                })
                .finally(() => { input.disabled = false; if(btn) btn.disabled = false; input.focus(); });
            });
        }

        // --- 5. UPLOAD LOGIC ---
        const uploadModal = document.getElementById('uploadModal');
        const uploadModalContent = document.getElementById('uploadModalContent');
        const fileInput = document.getElementById('fileInput');
        const fileNameDisplay = document.getElementById('fileNameDisplay');
        const fileLabelContent = document.getElementById('fileLabelContent');
        const uploadForm = document.getElementById('uploadForm');

        window.toggleUploadModal = function(show) {
            if (show) {
                const currentGroup = sessionStorage.getItem('currentGroup');
                const currentSubject = sessionStorage.getItem('currentSubject');
                const currentFolder = sessionStorage.getItem('currentFolder');
                if(currentGroup && currentGroup !== 'None') document.getElementById('uploadGroup').value = currentGroup;
                if(currentSubject) document.getElementById('uploadSubject').value = currentSubject;
                if(currentFolder) document.getElementById('uploadFolder').value = currentFolder;
                
                uploadModal.classList.remove('hidden');
                setTimeout(() => { uploadModal.classList.remove('opacity-0'); uploadModalContent.classList.remove('scale-95'); uploadModalContent.classList.add('scale-100'); }, 10);
            } else {
                uploadModal.classList.add('opacity-0'); uploadModalContent.classList.remove('scale-100'); uploadModalContent.classList.add('scale-95');
                setTimeout(() => { uploadModal.classList.add('hidden'); if(uploadForm) uploadForm.reset(); resetFileUI(); }, 300);
            }
        };

        window.openUploadModalFromDropdown = function() { toggleUploadModal(true); if(profileDropdown) { profileDropdown.classList.remove('visible-menu'); profileDropdown.classList.add('hidden-menu'); } };

        function resetFileUI() { if(fileNameDisplay) { fileNameDisplay.textContent = ''; fileNameDisplay.classList.add('hidden'); } if(fileLabelContent) { fileLabelContent.classList.remove('opacity-50'); } }

        if (fileInput) {
            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    if (this.files[0].size > 10 * 1024 * 1024) { alert('File too large (Max 10MB)'); this.value = ''; resetFileUI(); return; }
                    fileNameDisplay.textContent = this.files[0].name; fileNameDisplay.classList.remove('hidden'); fileLabelContent.classList.add('opacity-50');
                } else { resetFileUI(); }
            });
        }

        if (uploadForm) {
            uploadForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const progressBar = document.getElementById('uploadProgressBar');
                const progressContainer = document.getElementById('uploadProgressContainer');
                const statusMsg = document.getElementById('uploadStatusMsg');
                const btn = document.getElementById('btnSubmitUpload');

                btn.disabled = true; btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Uploading...';
                progressContainer.classList.remove('hidden'); statusMsg.textContent = '';

                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'upload_handler.php', true);
                xhr.upload.onprogress = function(e) { if (e.lengthComputable) { progressBar.style.width = ((e.loaded / e.total) * 100) + '%'; } };
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.status === 'success') {
                                statusMsg.textContent = response.message; statusMsg.className = 'text-xs text-center h-4 text-green-600 font-bold';
                                setTimeout(() => toggleUploadModal(false), 2000);
                            } else { statusMsg.textContent = response.message; statusMsg.className = 'text-xs text-center h-4 text-red-500 font-bold'; }
                        } catch (e) { statusMsg.textContent = "Server Error"; }
                    } else { statusMsg.textContent = "Upload failed."; }
                    btn.disabled = false; btn.innerHTML = '<i data-lucide="upload" class="w-4 h-4"></i> Submit for Approval';
                };
                xhr.send(formData);
            });
        }

        // --- 6. RESOURCE LOGIC ---
        window.updateSubjects = function() {
            // Hide drill-down panels when main dropdown changes
            document.getElementById('resourceSelectionPanel').style.display = 'none';
            document.getElementById('folderDisplayPanel').style.display = 'none';

            // Restore announcement banner if visible
            const banner = document.getElementById('announcementsWidget');
            if(banner && !isBannerManuallyDismissed) {
                 banner.style.display = 'block';
                 banner.style.opacity = '1';
                 banner.style.transform = 'translateY(0)';
            }

            const groupSelect = document.getElementById('group');
            if (!groupSelect) return;
            const group = groupSelect.value;
            const subjectsContainer = document.getElementById('subjectsContainer');
            const subjectsSection = document.querySelector('.subjects-section');
            const emptyState = document.getElementById('emptyState');
            
            if (subjectsContainer) subjectsContainer.innerHTML = '';

            // --- LUCIDE ICON & COLOR MAPPING ---
            // Added background colors for more colorful UI
            const subjectIcons = {
                // ST Group
                "Communication Skills": { icon: "message-square-text", color: "text-indigo-600", bg: "bg-indigo-100 dark:bg-indigo-900/30" },
                "Computer Programming": { icon: "terminal-square", color: "text-slate-700 dark:text-slate-300", bg: "bg-slate-200 dark:bg-slate-700/50" },
                "Engineering Graphics": { icon: "ruler", color: "text-orange-600", bg: "bg-orange-100 dark:bg-orange-900/30" },
                "Engineering Mechanics": { icon: "settings-2", color: "text-red-600", bg: "bg-red-100 dark:bg-red-900/30" },
                "Life Skill Management": { icon: "heart-handshake", color: "text-green-600", bg: "bg-green-100 dark:bg-green-900/30" },
                "Mathematics 1": { icon: "sigma-square", color: "text-blue-600", bg: "bg-blue-100 dark:bg-blue-900/30" },
                "Mathematics 2": { icon: "function-square", color: "text-cyan-600", bg: "bg-cyan-100 dark:bg-cyan-900/30" },
                "Physics Theory": { icon: "atom", color: "text-purple-600", bg: "bg-purple-100 dark:bg-purple-900/30" },

                // MT Group
                "Basic Electrical and Electronics Engg": { icon: "zap", color: "text-yellow-600", bg: "bg-yellow-100 dark:bg-yellow-900/30" },
                "Biology for Engineers": { icon: "microscope", color: "text-pink-600", bg: "bg-pink-100 dark:bg-pink-900/30" },
                "Engineering Chemistry": { icon: "flask-conical", color: "text-teal-600", bg: "bg-teal-100 dark:bg-teal-900/30" },
                "Environmental Sciences": { icon: "leaf", color: "text-emerald-600", bg: "bg-emerald-100 dark:bg-emerald-900/30" },
                "Manufacturing Sciences": { icon: "factory", color: "text-amber-700", bg: "bg-amber-100 dark:bg-amber-900/30" }
            };

            if (group && group !== 'None') {
                if (subjectsSection) subjectsSection.style.display = 'block';
                if (emptyState) emptyState.style.display = 'none';
                fetch(`fetch_subjects.php?group=${group}`)
                    .then(response => response.json())
                    .then(data => {
                        if (subjectsContainer && data.length > 0) {
                            data.forEach(subject => {
                                const tile = document.createElement('div');
                                tile.className = 'group bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 hover:border-blue-300 dark:hover:border-blue-500 hover:shadow-md transition-all cursor-pointer flex items-center gap-4';
                                
                                const iconContainer = document.createElement('div');
                                
                                // Default Icon Fallback
                                let iconName = "book-open";
                                let iconColor = "text-blue-500";
                                let bgClass = "bg-blue-50 dark:bg-blue-900/20"; // Default bg

                                if (subjectIcons[subject]) {
                                    iconName = subjectIcons[subject].icon;
                                    iconColor = subjectIcons[subject].color;
                                    bgClass = subjectIcons[subject].bg;
                                }

                                // Apply larger colorful background circle
                                iconContainer.className = `w-14 h-14 rounded-full ${bgClass} flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform shadow-sm`;
                                
                                const iconElement = document.createElement('i');
                                iconElement.setAttribute('data-lucide', iconName);
                                iconElement.className = `w-7 h-7 ${iconColor}`;
                                
                                iconContainer.appendChild(iconElement);

                                const textDiv = document.createElement('div');
                                const name = document.createElement('h3'); name.textContent = subject; 
                                name.className = 'font-bold text-slate-800 dark:text-slate-100 group-hover:text-blue-700 dark:group-hover:text-blue-400 transition-colors text-sm';
                                const subText = document.createElement('p'); subText.textContent = "View Resources"; 
                                subText.className = 'text-xs text-slate-500 dark:text-slate-400 mt-1';
                                
                                textDiv.appendChild(name); textDiv.appendChild(subText); tile.appendChild(iconContainer); tile.appendChild(textDiv);
                                tile.onclick = () => showResourceSelection(subject);
                                subjectsContainer.appendChild(tile);
                            });
                            // Re-initialize icons for newly added elements
                            lucide.createIcons();
                        }
                    });
            } else {
                if(subjectsSection) subjectsSection.style.display = 'none';
                if(emptyState) emptyState.style.display = 'block';
            }
        };

        function showResourceSelection(subject) {
            document.querySelector('.subjects-section').style.display = 'none';
            document.getElementById('emptyState').style.display = 'none';
            
            // Auto-hide announcement banner
            const banner = document.getElementById('announcementsWidget');
            if(banner) banner.style.display = 'none';

            const resourcePanel = document.getElementById('resourceSelectionPanel');
            resourcePanel.style.display = 'block';
            document.getElementById('resourceTitle').textContent = `${subject}`;
            resourcePanel.setAttribute('data-subject', subject);
            fetchFolders(subject);
        }

        function fetchFolders(subject) {
            const container = document.getElementById('resourceDisplayContainer');
            const group = document.getElementById('group').value;
            container.innerHTML = '<p class="text-slate-400 text-sm">Loading...</p>';
            fetch(`fetch_resources.php?group=${group}&subject=${subject}`)
                .then(r => r.json()).then(data => {
                    container.innerHTML = '';
                    if (data.folders) {
                        data.folders.forEach(folder => {
                            const btn = document.createElement('button');
                            btn.className = 'flex items-center gap-3 w-full p-4 bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-all text-left group';
                            btn.innerHTML = `
                                <i data-lucide="folder" class="w-5 h-5 text-amber-400 fill-amber-400/20 group-hover:scale-110 transition-transform"></i>
                                <span class="font-semibold text-slate-700 dark:text-slate-200">${folder}</span>
                            `;
                            btn.onclick = () => updateResourceContent(folder);
                            container.appendChild(btn);
                        });
                        lucide.createIcons();
                    }
                });
        }

        // Helper function for redirection
        window.openResourceFile = function(file, group, subject, folder) {
            sessionStorage.setItem('currentGroup', group);
            sessionStorage.setItem('currentSubject', subject);
            sessionStorage.setItem('currentFolder', folder);
            window.location.href = `resource_viewer.php?file=${encodeURIComponent(file)}&subject=${encodeURIComponent(subject)}&group=${encodeURIComponent(group)}&folder=${encodeURIComponent(folder)}`;
        };

        function updateResourceContent(folder) {
            document.getElementById('resourceSelectionPanel').style.display = 'none';
            document.getElementById('folderDisplayPanel').style.display = 'block';
            const subject = document.getElementById('resourceSelectionPanel').getAttribute('data-subject');
            const group = document.getElementById('group').value;
            const content = document.getElementById('folderContent');
            
            // Update Breadcrumb Display (Removed "Directory:" label)
            const breadcrumb = document.getElementById('folderBreadcrumb');
            if(breadcrumb) {
                breadcrumb.innerHTML = `
                    <span class="text-blue-600 dark:text-blue-400 font-medium">${subject}</span>
                    <i data-lucide="chevron-right" class="w-3 h-3 text-slate-300"></i>
                    <span class="text-slate-800 dark:text-white font-bold">${folder}</span>
                `;
                lucide.createIcons();
            }

            // UPDATED: Two Column Grid for Files
            content.className = "grid grid-cols-1 md:grid-cols-2 gap-3"; 
            content.innerHTML = '<p class="text-slate-400 text-sm col-span-full">Loading...</p>';
            
            fetch(`fetch_resources.php?group=${encodeURIComponent(group)}&subject=${encodeURIComponent(subject)}&folder=${encodeURIComponent(folder)}`)
            .then(r => r.json()).then(data => {
                content.innerHTML = '';
                if(data.files) {
                    const groups = {};

                    // 1. Grouping Pass
                    data.files.forEach(file => {
                        // Heuristic: Check for " solution" or " solutions" at end of name (case insensitive)
                        const lower = file.toLowerCase();
                        const extIdx = lower.lastIndexOf('.');
                        if(extIdx < 0) return; // skip no extension

                        const nameOnly = file.substring(0, extIdx);
                        // Regex: matches space or hyphen or underscore followed by solution(s) at end of string
                        const solMatch = nameOnly.match(/[\s\-_]+solutions?$/i);
                        const isSol = !!solMatch;

                        let baseName = file;
                        if(isSol) {
                            // Remove the solution part + extension to get base name
                            // e.g. "A Solutions" -> "A"
                            baseName = file.substring(0, extIdx).replace(/[\s\-_]+solutions?$/i, '').trim();
                        } else {
                            baseName = file.substring(0, extIdx).trim();
                        }

                        // Initialize group if not exists
                        if(!groups[baseName]) groups[baseName] = { base: baseName, paper: null, solution: null };

                        if(isSol) groups[baseName].solution = file;
                        else groups[baseName].paper = file;
                    });

                    // 2. Rendering Pass
                    Object.values(groups).forEach(item => {
                        // Logic: If both exist, show combined card. If one exists, show regular button.
                        
                        if(item.paper && item.solution) {
                            // Render Combined Card
                            const div = document.createElement('div');
                            div.className = 'flex items-center justify-between p-3 bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors group shadow-sm';

                            div.innerHTML = `
                                <div class="flex items-center gap-3 overflow-hidden">
                                     <i data-lucide="file-text" class="w-5 h-5 text-purple-500"></i>
                                     <span class="text-sm font-medium text-slate-700 dark:text-slate-200 truncate">${item.base}</span>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <button onclick="openResourceFile('${item.paper}', '${group}', '${subject}', '${folder}')" class="px-2 py-1 text-xs font-bold text-blue-600 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-300 dark:hover:bg-blue-900/50 rounded border border-blue-200 dark:border-blue-800 transition-colors" title="View Paper">Questions</button>
                                    <button onclick="openResourceFile('${item.solution}', '${group}', '${subject}', '${folder}')" class="px-2 py-1 text-xs font-bold text-green-600 bg-green-50 hover:bg-green-100 dark:bg-green-900/30 dark:text-green-300 dark:hover:bg-green-900/50 rounded border border-green-200 dark:border-green-800 transition-colors" title="View Solution">Solution</button>
                                </div>
                            `;
                            content.appendChild(div);
                        } else {
                            // Render Single File (Standard Logic)
                            const file = item.paper || item.solution;
                            const btn = document.createElement('button');
                            btn.className = 'flex items-center gap-3 w-full p-3 bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-lg text-left hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors group';
                            
                            let iconName = 'file';
                            let iconColor = 'text-slate-400';
                            if (file.toLowerCase().endsWith('.pdf')) { iconName = 'file-text'; iconColor = 'text-red-500'; } 
                            else if (file.toLowerCase().endsWith('.doc') || file.toLowerCase().endsWith('.docx')) { iconName = 'file-type-2'; iconColor = 'text-blue-500'; }
                            
                            btn.innerHTML = `
                                <i data-lucide="${iconName}" class="w-5 h-5 ${iconColor} group-hover:scale-110 transition-transform"></i>
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-200 truncate flex-1">${file}</span>
                            `;
                            btn.onclick = () => openResourceFile(file, group, subject, folder);
                            content.appendChild(btn);
                        }
                    });
                    lucide.createIcons();
                }
            });
        }

        window.goBackToSubjectPanel = function() { 
            document.getElementById('resourceSelectionPanel').style.display = 'none'; 
            updateSubjects(); 
        };
        window.goBackToResourceSelectionPanel = function() { document.getElementById('folderDisplayPanel').style.display = 'none'; document.getElementById('resourceSelectionPanel').style.display = 'block'; };

        function restorePreviousState() {
            let group = sessionStorage.getItem('currentGroup');
            const subject = sessionStorage.getItem('currentSubject');
            const folder = sessionStorage.getItem('currentFolder');
            
            if ((!group || group === 'None') && typeof USER_ROLL_NO !== 'undefined' && USER_ROLL_NO && USER_ROLL_NO !== 'Loading...') {
                const cleanRoll = USER_ROLL_NO.trim();
                if (cleanRoll.length >= 3) {
                    const sectionChar = cleanRoll.charAt(2).toUpperCase();
                    // SWAPPED LOGIC: ABCDE -> MT, FGHIJ -> ST
                    if (['A', 'B', 'C', 'D', 'E'].includes(sectionChar) || cleanRoll.toUpperCase().includes('E')) group = 'MT'; 
                    else if (['F', 'G', 'H', 'I', 'J'].includes(sectionChar)) group = 'ST';
                }
            }

            if (group && group !== 'None') {
                const groupSelect = document.getElementById('group');
                if(groupSelect) { groupSelect.value = group; updateSubjects(); }
                if (subject) {
                    setTimeout(() => {
                        showResourceSelection(subject);
                        if (folder) { setTimeout(() => { updateResourceContent(folder); }, 300); }
                    }, 300);
                }
            }
        }

        // --- 7. INITIALIZATION & CHARTS ---
        document.addEventListener('DOMContentLoaded', () => {
            initTheme();
            restorePreviousState();

            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('chat') === 'open') { toggleChat(); window.history.replaceState({}, document.title, window.location.pathname); }
            
            if (urlParams.get('tracker') === 'open') { 
                toggleAttendance(true); 
                // UPDATED: Remove 'tracker' from URL so refresh doesn't re-open it
                const newUrl = new URL(window.location.href);
                newUrl.searchParams.delete('tracker');
                window.history.replaceState({}, document.title, newUrl.toString());
            }
            
            // Notification Logic (Updated for Profile Dot)
            const seenCount = parseInt(localStorage.getItem(LOCAL_STORAGE_CHAT_KEY) || '0');
            if (TOTAL_MESSAGES > seenCount && LAST_SENDER === 'admin') { 
                if(chatRedDot) chatRedDot.classList.remove('hidden');
                if(profileRedDot) profileRedDot.classList.remove('hidden');
            }

            // --- CHART 2: ATTENDANCE CHARTS (POPUP) ---
            const attendContainer = document.getElementById('attendanceChartsContainer');
            if (attendContainer) {
                if (SUBJECT_DATA && Object.keys(SUBJECT_DATA).length > 0) {
                    for (const [subject, stats] of Object.entries(SUBJECT_DATA)) {
                        const card = document.createElement('div');
                        card.className = "flex items-center gap-4 p-3 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm";
                        
                        const canvasDiv = document.createElement('div');
                        canvasDiv.className = "relative h-16 w-16 shrink-0";
                        const canvas = document.createElement('canvas');
                        canvasDiv.appendChild(canvas);
                        
                        const details = document.createElement('div');
                        details.className = "flex-1 min-w-0";
                        details.innerHTML = `
                            <h5 class="text-xs font-bold text-slate-800 dark:text-white truncate">${subject}</h5>
                            <p class="text-[10px] font-semibold text-slate-500">Current: <span class="${stats.Percentage < 75 ? 'text-red-500' : 'text-green-600'}">${stats.Percentage}%</span></p>
                            <p class="text-[10px] ${stats.Color} font-bold">${stats.StatusMsg}</p>
                        `;

                        card.appendChild(canvasDiv);
                        card.appendChild(details);
                        attendContainer.appendChild(card);

                        new Chart(canvas, {
                            type: 'doughnut',
                            data: {
                                labels: ['P', 'A', 'C'],
                                datasets: [{
                                    data: [stats.Present, stats.Absent, stats.Cancelled],
                                    backgroundColor: ['#22c55e', '#ef4444', '#94a3b8'],
                                    borderWidth: 0
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                cutout: '70%',
                                plugins: { legend: { display: false }, tooltip: { enabled: false } },
                                animation: { duration: 0 }
                            }
                        });
                    }
                } else {
                    attendContainer.innerHTML = '<p class="text-xs text-slate-400 text-center italic">No data available yet.</p>';
                }
            }
        });
    </script>
</body>
</html>