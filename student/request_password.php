<?php
session_start();
require '../db.php';

if (isset($_POST['request_pw_change'])) {
    $identity = trim($_POST['login_identity']);
    $source   = $_POST['source'] ?? 'profile';
    $type     = ($source === 'login') ? 'login_reset' : 'change';

    try {
        $stmt = $pdo->prepare("SELECT id, role FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$identity, $identity]);
        $user = $stmt->fetch();

        if ($user) {
            // Updated SQL: explicitly updates identity and role in case they changed
            $sql = "INSERT INTO password_permissions (user_id, identity, role, status, request_type, requested_at) 
                    VALUES (?, ?, ?, 'pending', ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                        identity = VALUES(identity),
                        role = VALUES(role),
                        status = 'pending', 
                        request_type = VALUES(request_type), 
                        requested_at = NOW()";

            $pdo->prepare($sql)->execute([$user['id'], $identity, $user['role'], $type]);

            $loc = ($source === 'login') ? "../login.php" : ($user['role'] === 'student' ? "student_profile.php" : "profile.php");
            header("Location: $loc?msg=sent");
        } else {
            header("Location: ../login.php?error=usernotfound");
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        die("An error occurred processing your request.");
    }
    exit();
}
