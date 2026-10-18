<?php
require '../db.php';
session_start();

$allowed_roles = ['teacher', 'tr'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    die("Error: Unauthorized.");
}

if (isset($_GET['exam_id']) && isset($_GET['status'])) {
    $exam_id = (int)$_GET['exam_id'];
    $current_status = (int)$_GET['status'];
    $new_status = ($current_status == 1) ? 0 : 1;

    try {
        // This toggles access for the WHOLE exam (all students who took it)
        $stmt = $pdo->prepare("UPDATE exams SET show_details = ? WHERE id = ?");
        $stmt->execute([$new_status, $exam_id]);

        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
}
