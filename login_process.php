<?php
ob_start();
session_start();
require 'db.php';

// Set your timezone to match Myanmar for accurate time comparison
date_default_timezone_set('Asia/Yangon');

if (isset($_POST['login'])) {
    $identity = trim($_POST['login_identity']);
    $password = $_POST['password'];

    try {
        // 1. Fetch the user and global system settings
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :id1 OR email = :id2 LIMIT 1");
        $stmt->execute([':id1' => $identity, ':id2' => $identity]);
        $user = $stmt->fetch();

        // Fetch the activation date set by Admin from your settings table
        $settingsStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'login_start_date'");
        $startDateStr = $settingsStmt->fetchColumn() ?: '2000-01-01 00:00:00';
        $loginAllowedTime = strtotime($startDateStr);
        $currentTime = time();

        // 2. CHECK FOR ADMIN "MESSAGED" STATUS (Your original lock logic)
        $msg_stmt = $pdo->prepare("SELECT id FROM password_permissions WHERE identity = ? AND status = 'messaged' LIMIT 1");
        $msg_stmt->execute([$identity]);
        if ($msg_stmt->fetch()) {
            header("Location: login.php?identity=" . urlencode($identity));
            exit();
        }

        if ($user && password_verify($password, $user['password'])) {

            $userRole = strtolower(trim($user['role']));

            // --- FIXED: AUTO-APPROVAL & TIME DURATION LOGIC ---
            // --- FIXED: AUTO-APPROVAL & TIME DURATION LOGIC ---

            // Only apply restrictions if the user is NOT an admin
            if ($userRole !== 'admin') {

                $timeHasStarted = ($currentTime >= $loginAllowedTime);
                $isManuallyApproved = ($user['status'] === 'approved');

                // If the scheduled time HAS NOT started AND they aren't manually approved, block them
                if (!$timeHasStarted && !$isManuallyApproved) {
                    $readableDate = date("M d, Y h:i A", $loginAllowedTime);
                    header("Location: login.php?wait=1&date=" . urlencode($readableDate));
                    exit();
                }

                // If the time HAS started, we ignore the 'pending' status and let them in
            }

            // --- SUCCESS LOGIC ---
            unset($_SESSION['attempts']);
            $pdo->prepare("UPDATE users SET login_attempts = 0 WHERE id = ?")->execute([$user['id']]);

            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $userRole;

            // Redirection based on your specific roles
            if ($userRole === 'admin') {
                header("Location: admin/admin_dashboard.php");
            } elseif ($userRole === 'teacher' || $userRole === 'tr') {
                header("Location: teacher/teacher_dashboard.php");
            } else {
                header("Location: student/student_dashboard.php");
            }
            exit();
        } else {
            // FAILURE LOGIC (Handling attempts and locking)
            if (!isset($_SESSION['attempts'])) {
                $_SESSION['attempts'] = 0;
            }
            $_SESSION['attempts']++;

            if ($user) {
                $pdo->prepare("UPDATE users SET login_attempts = login_attempts + 1 WHERE id = ?")->execute([$user['id']]);
                // Refresh user data to get the updated count for redirection logic
                $stmt_ref = $pdo->prepare("SELECT login_attempts FROM users WHERE id = ?");
                $stmt_ref->execute([$user['id']]);
                $current_total = $stmt_ref->fetchColumn();
            } else {
                $current_total = $_SESSION['attempts'];
            }

            if ($current_total >= 5) {
                header("Location: login.php?error=invalid&locked=1&identity=" . urlencode($identity));
            } else {
                header("Location: login.php?error=invalid");
            }
            exit();
        }
    } catch (Exception $e) {
        die("Error: " . $e->getMessage());
    }
}
ob_end_flush();
