<?php
session_start();
require '../db.php';

/**
 * PASSWORD PERMISSION REQUEST HANDLER
 * This script processes requests from both the Login page (forgot password)
 * and the User Profile (security change).
 */

if (isset($_POST['request_pw_change'])) {
    $identity = trim($_POST['login_identity']);
    $source   = $_POST['source'] ?? 'profile';

    // Determine the type: 'login_reset' for outsiders, 'change' for logged-in users
    $request_type = ($source === 'login') ? 'login_reset' : 'change';

    try {
        // 1. Verify User Existence
        $stmt = $pdo->prepare("SELECT id, role FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$identity, $identity]);
        $user = $stmt->fetch();

        if ($user) {
            // 2. UPSERT LOGIC (Update or Insert)
            // This prevents duplicate rows while ensuring the Admin sees the most recent request
            $sql = "INSERT INTO password_permissions (user_id, identity, role, status, request_type, requested_at) 
                    VALUES (?, ?, ?, 'pending', ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                        identity     = VALUES(identity),
                        role         = VALUES(role),
                        status       = 'pending', 
                        request_type = VALUES(request_type), 
                        requested_at = NOW()";

            $pdo->prepare($sql)->execute([
                $user['id'],
                $identity,
                $user['role'],
                $request_type
            ]);

            // 3. SMART REDIRECT
            // Dynamic pathing based on source and user role
            if ($source === 'login') {
                header("Location: ../login.php?msg=sent");
            } else {
                $target_page = ($user['role'] === 'student') ? "student_profile.php" : "profile.php";
                header("Location: $target_page?msg=sent");
            }
        } else {
            // Security Tip: In a production environment, you might want to redirect to 'msg=sent' 
            // even if the user isn't found to prevent "user enumeration" attacks.
            header("Location: ../login.php?error=usernotfound");
        }
    } catch (Exception $e) {
        // Log the error and show a user-friendly message
        error_log("Password Request Error: " . $e->getMessage());
        die("An error occurred while processing your request. Please try again later.");
    }
    exit();
}
