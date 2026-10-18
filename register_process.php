<?php
require 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Collect and sanitize inputs
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password']; // New field
    $phone    = trim($_POST['phone']);
    $country  = trim($_POST['country']);
    $city     = trim($_POST['city']);
    $address  = trim($_POST['address']);

    // 1. Validation: Check empty fields
    if (empty($username) || empty($email) || empty($password) || empty($phone)) {
        $_SESSION['error'] = "Please fill in all required fields.";
        header("Location: register.php");
        exit();
    }

    // --- NEW: 1.5 Validation: Check if email is a valid Gmail address ---
    // filter_var checks if it's a valid email format
    // preg_match checks if it ends specifically with @gmail.com (case-insensitive)
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/@gmail\.com$/i', $email)) {
        $_SESSION['error'] = "Only @gmail.com addresses are allowed for registration.";
        header("Location: register.php");
        exit();
    }

    // 2. Validation: Check if passwords match
    if ($password !== $confirm) {
        $_SESSION['error'] = "Passwords do not match!";
        header("Location: register.php");
        exit();
    }

    // Password Hashing
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    try {
        // 3. Check if email or username already exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $checkStmt->execute([$email, $username]);

        if ($checkStmt->rowCount() > 0) {
            $_SESSION['error'] = "Username or Email already exists.";
            header("Location: register.php");
            exit();
        }

        // Define the role
        $role = 'student';

        // Insert Query
        $sql = "INSERT INTO users (username, email, password, phone, country, city, address, role, created_at) 
                VALUES (:username, :email, :password, :phone, :country, :city, :address, :role, NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':username' => $username,
            ':email'    => $email,
            ':password' => $hashedPassword,
            ':phone'    => $phone,
            ':country'  => $country,
            ':city'     => $city,
            ':address'  => $address,
            ':role'     => $role
        ]);

        $_SESSION['success'] = "Registration successful! Please log in.";
        header("Location: login.php");
        exit();
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $_SESSION['error'] = "Database error. Please try again later.";
        header("Location: register.php");
        exit();
    }
}
