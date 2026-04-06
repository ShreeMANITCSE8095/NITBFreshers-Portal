<?php
session_start();
// Enable detailed error reporting to debug issues
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'db_connection.php'; 

// --- 0. GLOBAL MAINTENANCE CHECK ---
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
            <title>Service Unavailable | NITBFreshers Portal</title>
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
                    The portal is currently offline for system upgrades.
                </p>
                <div class="bg-amber-50 border border-amber-100 rounded-xl p-4 text-left mb-6">
                    <p class="text-xs font-bold text-amber-600 uppercase mb-1">Admin Message:</p>
                    <p class="text-sm text-amber-800 font-medium"><?php echo htmlspecialchars($mReason); ?></p>
                </div>
                <button onclick="location.reload()" class="w-full py-3 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl transition-colors">Check Again</button>
            </div>
            <script>lucide.createIcons();</script>
        </body>
        </html>
        <?php
        exit;
    }
}

$conn = db_connect(); 
$ip_address = $_SERVER['REMOTE_ADDR']; 
$device_os = php_uname(); 
$timestamp = date('Y-m-d H:i:s'); 

$message = ""; 
$msgType = ""; // 'success', 'warning', or 'error'

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $option = $_POST['option'] ?? '';
    $scholar_no = trim($_POST['scholar_no'] ?? '');

    if (empty($scholar_no)) {
        $message = "Please enter your Scholar Number.";
        $msgType = "error";
    } else {
        if ($option == "case1" || $option == "case2") {
            
            // 1. Verify Scholar Number Exists
            // FIX: Changed 'SELECT id' to 'SELECT scholar_no' because 'id' column might not exist in your students table
            $stmt = $conn->prepare("SELECT scholar_no FROM students WHERE scholar_no = ?");
            $stmt->bind_param("s", $scholar_no);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                
                // 2. CHECK FOR PENDING REQUESTS (Restrict one per scholar no)
                // Note: Ensure password_reset_requests table DOES have an 'id' column.
                $check_stmt = $conn->prepare("SELECT id FROM password_reset_requests WHERE scholar_no = ? AND status = 'pending'");
                $check_stmt->bind_param("s", $scholar_no);
                $check_stmt->execute();
                $pending = $check_stmt->get_result();

                if ($pending->num_rows > 0) {
                    $message = "You already have a pending request. Please wait for Admin approval.";
                    $msgType = "warning";
                } else {
                    // 3. FILE UPLOAD LOGIC
                    if (isset($_FILES['id_card'])) {
                        
                        if ($_FILES['id_card']['error'] !== UPLOAD_ERR_OK) {
                            // Map PHP upload errors to user messages
                            $uploadErrors = [
                                UPLOAD_ERR_INI_SIZE => "File is too large (Server Limit).",
                                UPLOAD_ERR_FORM_SIZE => "File is too large (Form Limit).",
                                UPLOAD_ERR_PARTIAL => "File only partially uploaded.",
                                UPLOAD_ERR_NO_FILE => "No file was uploaded.",
                                UPLOAD_ERR_NO_TMP_DIR => "Missing temporary folder.",
                                UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
                                UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload."
                            ];
                            $errCode = $_FILES['id_card']['error'];
                            $message = isset($uploadErrors[$errCode]) ? $uploadErrors[$errCode] : "Unknown upload error: $errCode";
                            $msgType = "error";
                        } else {
                            $fileTmpPath = $_FILES['id_card']['tmp_name'];
                            $fileName = $_FILES['id_card']['name'];
                            $fileSize = $_FILES['id_card']['size'];
                            $fileNameCmps = explode(".", $fileName);
                            $fileExtension = strtolower(end($fileNameCmps));
                            
                            // Check size: 1MB = 1048576 Bytes
                            if ($fileSize > 1048576) {
                                $message = "File too large. Max allowed size is 1MB.";
                                $msgType = "error";
                            } else {
                                $allowedfileExtensions = array('jpg', 'jpeg', 'png', 'pdf');
                                if (in_array($fileExtension, $allowedfileExtensions)) {
                                    
                                    $uploadFileDir = 'uploads/id_cards/';
                                    
                                    // Ensure directory exists
                                    if (!is_dir($uploadFileDir)) {
                                        if (!mkdir($uploadFileDir, 0755, true)) {
                                            $message = "Server Error: Could not create upload directory.";
                                            $msgType = "error";
                                        }
                                    }

                                    // Proceed only if directory is ready (and no previous error)
                                    if (empty($message) && is_dir($uploadFileDir)) {
                                        $newFileName = $scholar_no . '_' . time() . '.' . $fileExtension;
                                        $dest_path = $uploadFileDir . $newFileName;
                                        
                                        if(move_uploaded_file($fileTmpPath, $dest_path)) {
                                            // 4. DATABASE INSERT
                                            try {
                                                $requestType = ($option == "case1") ? "Forgot Password" : "Compromised Account";
                                                
                                                $insert_stmt = $conn->prepare("INSERT INTO password_reset_requests (scholar_no, type, request_time, ip_address, status, id_card_path) VALUES (?, ?, ?, ?, 'pending', ?)");
                                                $insert_stmt->bind_param("sssss", $scholar_no, $requestType, $timestamp, $ip_address, $dest_path);
                                                
                                                if ($insert_stmt->execute()) {
                                                    $message = "Request submitted successfully. Admin will review your ID card.";
                                                    $msgType = "success";
                                                } else {
                                                    // This will catch missing column errors in password_reset_requests
                                                    throw new Exception("Database execution failed: " . $insert_stmt->error);
                                                }
                                            } catch (Exception $e) {
                                                // Cleanup file if DB insert fails
                                                if (file_exists($dest_path)) unlink($dest_path);
                                                $message = "System Error: Failed to save request. " . $e->getMessage();
                                                $msgType = "error";
                                            }
                                        } else {
                                            $message = "Error moving uploaded file. Check folder permissions.";
                                            $msgType = "error";
                                        }
                                    }
                                } else {
                                    $message = "Invalid file format. Only JPG, PNG, PDF allowed.";
                                    $msgType = "error";
                                }
                            }
                        }
                    } else {
                        $message = "Please select a valid ID Card file.";
                        $msgType = "error";
                    }
                }
            } else {
                $message = "Scholar number not found in records.";
                $msgType = "error";
            }
        } elseif ($option == "case3") {
            // Case 3 (Registration)
            try {
                $stmt = $conn->prepare("INSERT INTO newregistration (scholar_no, request_time) VALUES (?, ?)");
                $stmt->bind_param("ss", $scholar_no, $timestamp);
                
                if ($stmt->execute()) {
                    $message = "Registration request submitted. Wait for approval.";
                    $msgType = "success";
                } else {
                    $message = "Request failed. Number might already be pending.";
                    $msgType = "error";
                }
            } catch (Exception $e) {
                $message = "Request failed. Invalid data or database error.";
                $msgType = "error";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Assistance | NITBFreshers Portal</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class', 
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .fade-in { animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>

<body class="bg-slate-50 min-h-screen flex flex-col justify-center items-center p-4 text-slate-900">

    <div class="mb-6 text-center">
        <div class="bg-white p-3 rounded-2xl shadow-sm inline-block mb-4 border border-slate-200">
            <img src="./images/logo.png" alt="Institute Logo" class="h-14 w-auto object-contain">
        </div>
        <h1 class="text-xl font-bold text-slate-900">Login Assistance</h1>
        <p class="text-slate-500 text-sm font-medium mt-1">Resolve account access issues</p>
    </div>

    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
        <div class="p-8">
            
            <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo ($msgType === 'success') ? 'bg-green-50 border border-green-100' : (($msgType === 'warning') ? 'bg-amber-50 border border-amber-100' : 'bg-red-50 border border-red-100'); ?> flex items-start gap-3 fade-in">
                    <i data-lucide="<?php echo $msgType === 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-5 h-5 <?php echo ($msgType === 'success') ? 'text-green-500' : (($msgType === 'warning') ? 'text-amber-500' : 'text-red-500'); ?> shrink-0 mt-0.5"></i>
                    <div>
                        <h3 class="text-sm font-semibold <?php echo ($msgType === 'success') ? 'text-green-700' : (($msgType === 'warning') ? 'text-amber-700' : 'text-red-700'); ?>">
                            <?php echo $msgType === 'success' ? 'Request Sent' : ($msgType === 'error' ? 'Error' : 'Notice'); ?>
                        </h3>
                        <p class="text-xs <?php echo ($msgType === 'success') ? 'text-green-600' : (($msgType === 'warning') ? 'text-amber-600' : 'text-red-600'); ?> mt-1"><?php echo htmlspecialchars($message); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="space-y-5">
                <div>
                    <label for="optionSelector" class="block text-sm font-medium text-slate-700 mb-1">What issue are you facing?</label>
                    <div class="relative">
                        <select id="optionSelector" onchange="showPanel()" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-blue-600 transition-all text-slate-900 font-medium appearance-none cursor-pointer">
                            <option value="">Select an option...</option>
                            <option value="case1">I forgot my password</option>
                            <option value="case2">My account was compromised</option>
                            <option value="case3">Scholar Number not found</option>
                        </select>
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i data-lucide="help-circle" class="h-5 w-5 text-slate-400"></i>
                        </div>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400"></i>
                        </div>
                    </div>
                </div>

                <!-- Case 1 Panel (Forgot Password) -->
                <div id="panel1" class="panel hidden fade-in">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="option" value="case1">
                        <div class="space-y-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Enter Scholar Number</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i data-lucide="user" class="h-5 w-5 text-slate-400"></i>
                                    </div>
                                    <input type="text" name="scholar_no" required placeholder="e.g. 221112233" class="pl-10 w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-600">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Upload College ID Card (Max 1MB)</label>
                                <div class="relative border-2 border-dashed border-slate-300 rounded-xl p-4 text-center hover:border-blue-500 transition-colors bg-slate-50 group cursor-pointer">
                                    <!-- Input triggers file selection -->
                                    <input type="file" id="id_card_1" name="id_card" accept=".jpg,.jpeg,.png,.pdf" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="updateFileName('id_card_1', 'placeholder_1', 'file_info_1', 'file_name_1')">
                                    
                                    <!-- Default Placeholder -->
                                    <div id="placeholder_1" class="flex flex-col items-center justify-center gap-1 pointer-events-none transition-opacity duration-200">
                                        <i data-lucide="upload-cloud" class="h-6 w-6 text-blue-500 group-hover:scale-110 transition-transform"></i>
                                        <p class="text-xs text-slate-600 font-medium">Click to upload ID (JPG/PDF)</p>
                                    </div>

                                    <!-- Selected File View -->
                                    <div id="file_info_1" class="hidden flex-col items-center justify-center gap-1 pointer-events-none animate-bounce">
                                        <i data-lucide="file-check" class="h-6 w-6 text-green-500"></i>
                                        <p id="file_name_1" class="text-xs text-green-700 font-bold truncate max-w-[200px]"></p>
                                    </div>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-1">Required for verification.</p>
                            </div>
                        </div>
                        
                        <div class="bg-blue-50 border border-blue-100 rounded-lg p-3 mb-4 flex gap-2">
                            <i data-lucide="clock" class="w-4 h-4 text-blue-500 shrink-0 mt-0.5"></i>
                            <p class="text-xs text-blue-700">Password reset requires admin approval. Please allow up to 24 hours. After reset the New Password will be your Scholar Number.</p>
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition-colors shadow-lg shadow-blue-200">Request Password Reset</button>
                    </form>
                </div>

                <!-- Case 2 Panel (Compromised Account) -->
                <div id="panel2" class="panel hidden fade-in">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="option" value="case2">
                        <div class="space-y-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Enter Scholar Number</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i data-lucide="shield-alert" class="h-5 w-5 text-slate-400"></i>
                                    </div>
                                    <input type="text" name="scholar_no" required placeholder="e.g. 221112233" class="pl-10 w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-600">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Upload College ID Card (Max 1MB)</label>
                                <div class="relative border-2 border-dashed border-slate-300 rounded-xl p-4 text-center hover:border-blue-500 transition-colors bg-slate-50 group cursor-pointer">
                                    <!-- Input triggers file selection -->
                                    <input type="file" id="id_card_2" name="id_card" accept=".jpg,.jpeg,.png,.pdf" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="updateFileName('id_card_2', 'placeholder_2', 'file_info_2', 'file_name_2')">
                                    
                                    <!-- Default Placeholder -->
                                    <div id="placeholder_2" class="flex flex-col items-center justify-center gap-1 pointer-events-none transition-opacity duration-200">
                                        <i data-lucide="upload-cloud" class="h-6 w-6 text-red-500 group-hover:scale-110 transition-transform"></i>
                                        <p class="text-xs text-slate-600 font-medium">Click to upload ID (JPG/PDF)</p>
                                    </div>

                                    <!-- Selected File View -->
                                    <div id="file_info_2" class="hidden flex-col items-center justify-center gap-1 pointer-events-none animate-bounce">
                                        <i data-lucide="file-check" class="h-6 w-6 text-green-500"></i>
                                        <p id="file_name_2" class="text-xs text-green-700 font-bold truncate max-w-[200px]"></p>
                                    </div>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-1">Proof of ownership required.</p>
                            </div>
                        </div>

                        <div class="bg-red-50 border border-red-100 rounded-lg p-3 mb-4 flex gap-2">
                            <i data-lucide="info" class="w-4 h-4 text-red-500 shrink-0 mt-0.5"></i>
                            <p class="text-xs text-red-700">We will freeze account access pending manual verification of your ID card.</p>
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-red-600 hover:bg-red-700 text-white font-bold rounded-xl transition-colors shadow-lg shadow-red-200">Report Compromise</button>
                    </form>
                </div>

                <!-- Case 3 Panel (Not Registered) -->
                <div id="panel3" class="panel hidden fade-in">
                    <form method="POST">
                        <input type="hidden" name="option" value="case3">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Enter Scholar Number</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i data-lucide="user-plus" class="h-5 w-5 text-slate-400"></i>
                                </div>
                                <input type="text" name="scholar_no" required placeholder="e.g. 221112233" class="pl-10 w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-600">
                            </div>
                        </div>
                        <p class="text-xs text-slate-500 mb-4">We will manually verify your details and add you to the system. This may take up to 24 hours.</p>
                        <button type="submit" class="w-full py-2.5 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-xl transition-colors shadow-lg">Submit Request</button>
                    </form>
                </div>

            </div>

            <div class="mt-8 border-t border-slate-100 pt-6 text-center">
                <a href="index.php" class="inline-flex items-center text-sm font-medium text-slate-500 hover:text-blue-600 transition-colors">
                    <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i> Back to Login
                </a>
            </div>
        </div>
    </div>

    <p class="mt-8 text-xs text-center text-slate-400 max-w-sm leading-relaxed">
        Secure Login Utility • Student Initiative
    </p>

    <script>
        lucide.createIcons();

        function showPanel() {
            let option = document.getElementById("optionSelector").value;
            
            // Hide all panels
            document.querySelectorAll('.panel').forEach(panel => {
                panel.classList.add('hidden');
            });

            // Show selected panel
            if (option === "case1") {
                document.getElementById("panel1").classList.remove('hidden');
            } else if (option === "case2") {
                document.getElementById("panel2").classList.remove('hidden');
            } else if (option === "case3") {
                document.getElementById("panel3").classList.remove('hidden');
            }
        }

        // JS to handle visual file upload feedback
        function updateFileName(inputId, placeholderId, infoId, nameId) {
            const input = document.getElementById(inputId);
            const placeholder = document.getElementById(placeholderId);
            const fileInfo = document.getElementById(infoId);
            const fileName = document.getElementById(nameId);

            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Optional: Frontend size check (1MB)
                if (file.size > 1048576) {
                    alert("File is too large! Maximum 1MB allowed.");
                    input.value = ""; // Clear input
                    return;
                }

                // Hide placeholder, show info
                placeholder.classList.add('hidden');
                fileInfo.classList.remove('hidden');
                fileInfo.classList.add('flex'); // Ensure flex display
                fileName.textContent = file.name;
            }
        }

        // Security Features
        function blockRestrictedActions() {
            document.addEventListener("contextmenu", (event) => event.preventDefault());
            
            document.addEventListener("keydown", (event) => {
                if (
                    event.key === "F12" || 
                    (event.ctrlKey && event.key === "s") || 
                    (event.ctrlKey && event.shiftKey && event.key === "I") ||
                    (event.ctrlKey && event.key === "u")
                ) {
                    event.preventDefault();
                }
            });

            const detectDevTools = () => {
                const threshold = 160; 
                const widthThreshold = window.outerWidth - window.innerWidth > threshold;
                const heightThreshold = window.outerHeight - window.innerHeight > threshold;
                if (widthThreshold || heightThreshold) {
                    // console.clear(); // Optional: Clear console to hide logic
                }
            };
            // setInterval(detectDevTools, 2000); 
        }
        blockRestrictedActions();
    </script>
</body>
</html>