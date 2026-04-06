<?php
session_start();
include 'db_connection.php'; // Include the database connection file

// Ensure the user is logged in
if (!isset($_SESSION['scholarNo'])) {
    header('Location: index.php');
    exit;
}

$message = "";
$messageType = "";

// Handle form submission for changing the password
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $scholarNo = $_SESSION['scholarNo'];
    $oldPassword = $_POST['oldPassword'];
    $newPassword = $_POST['newPassword'];
    $confirmPassword = $_POST['confirmPassword'];

    // Validate that the passwords match
    if ($newPassword != $confirmPassword) {
        $message = "New passwords do not match.";
        $messageType = "error";
    } else {
        // Check if the old password is correct
        $conn = db_connect();
        $stmt = $conn->prepare("SELECT password FROM students WHERE scholar_no = ?");
        $stmt->bind_param("s", $scholarNo);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($storedPassword);
            $stmt->fetch();

            // Directly compare the old password with the stored password
            if ($oldPassword === $storedPassword) {
                // Update the password
                $updateStmt = $conn->prepare("UPDATE students SET password = ? WHERE scholar_no = ?");
                $updateStmt->bind_param("ss", $newPassword, $scholarNo);

                if ($updateStmt->execute()) {
                    $message = "Password changed successfully!";
                    $messageType = "success";
                } else {
                    $message = "Error updating password. Please try again.";
                    $messageType = "error";
                }
                $updateStmt->close();
            } else {
                $message = "Old password is incorrect.";
                $messageType = "error";
            }
        } else {
            $message = "User record not found.";
            $messageType = "error";
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
    <title>Change Password | MANIT Portal</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>

<body class="bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 min-h-screen transition-colors duration-200 flex flex-col">

    <!-- Navigation Bar -->
    <nav class="sticky top-0 z-50 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 shadow-sm transition-colors duration-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center gap-3">
                    <img src="./images/logo.png" alt="MANIT Logo" class="h-10 w-auto object-contain">
                    <div>
                        <h1 class="font-bold text-lg leading-tight text-slate-800 dark:text-white">MANIT <span class="text-red-500">Bhopal</span></h1>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Unofficial Study Portal</p>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <button id="themeToggle" class="p-2 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                        <span class="hidden dark:block"><i data-lucide="sun" class="w-5 h-5"></i></span>
                        <span class="block dark:hidden"><i data-lucide="moon" class="w-5 h-5"></i></span>
                    </button>
                    <a href="dashboard.php" class="flex items-center px-3 py-2 rounded-md text-sm font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30">
                        <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i> <span class="hidden sm:inline">Back to Dashboard</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow flex items-center justify-center p-6">
        <div class="w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-700 overflow-hidden transition-colors">
            
            <div class="p-8">
                <div class="text-center mb-8">
                    <div class="bg-blue-100 dark:bg-blue-900/30 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 text-blue-600 dark:text-blue-400">
                        <i data-lucide="key-round" class="w-8 h-8"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Change Password</h2>
                    <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Update your login credentials securely.</p>
                </div>

                <?php if ($message): ?>
                    <div class="mb-6 p-4 rounded-lg flex items-start gap-3 <?php echo $messageType === 'success' ? 'bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300' : 'bg-red-100 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300'; ?>">
                        <i data-lucide="<?php echo $messageType === 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-5 h-5 shrink-0 mt-0.5"></i>
                        <p class="text-sm font-medium"><?php echo htmlspecialchars($message); ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Scholar Number</label>
                        <input type="text" value="<?php echo htmlspecialchars($_SESSION['scholarNo']); ?>" readonly 
                            class="w-full px-4 py-2.5 bg-slate-100 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600 rounded-lg text-slate-500 dark:text-slate-400 text-sm cursor-not-allowed font-mono">
                    </div>

                    <div>
                        <label for="oldPassword" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Current Password</label>
                        <input type="password" name="oldPassword" id="oldPassword" required 
                            class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-lg text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all placeholder-slate-400">
                    </div>

                    <div>
                        <label for="newPassword" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">New Password</label>
                        <input type="password" name="newPassword" id="newPassword" required 
                            class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-lg text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all placeholder-slate-400">
                    </div>

                    <div>
                        <label for="confirmPassword" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Confirm New Password</label>
                        <input type="password" name="confirmPassword" id="confirmPassword" required 
                            class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-lg text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all placeholder-slate-400">
                    </div>

                    <button type="submit" class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition-colors shadow-lg shadow-blue-600/20 flex justify-center items-center gap-2 mt-6">
                        <span>Update Password</span>
                        <i data-lucide="check" class="w-4 h-4"></i>
                    </button>
                </form>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="py-6 text-center text-slate-500 dark:text-slate-400 text-sm">
        <p>&copy; <?php echo date('Y'); ?> MANIT Bhopal - Unofficial Study Portal</p>
    </footer>

    <!-- Scripts -->
    <script>
        lucide.createIcons();

        // Dark Mode Logic
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

        // Security Restrictions
        function blockRestrictedActions() {
            document.addEventListener("contextmenu", (e) => e.preventDefault());
            document.addEventListener("keydown", (e) => {
                if (e.key === "F12" || 
                   (e.ctrlKey && e.key === "s") || 
                   (e.ctrlKey && e.shiftKey && e.key === "I") ||
                   (e.ctrlKey && e.key === "u")) {
                    e.preventDefault();
                }
            });
        }
        blockRestrictedActions();
    </script>
</body>
</html>