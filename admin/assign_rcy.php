<?php
session_start();
require '../db.php';

// 1. Get the ID but DON'T kill the page if it's missing
$user_id = $_GET['user_id'] ?? null;

// 2. If no ID is provided, redirect them to the full user list instead of showing an error
if (!$user_id) {
    header("Location: manage_users.php");
    exit();
}

// 3. Fetch user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role != 'admin'");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// 4. If ID exists but user isn't found in DB
if (!$user) {
    die("User not found or cannot edit admin role.");
}

// ... rest of your code ...

// 👉 FIX: Fetch departments for ALL roles so they appear in the dropdown
$deptStmt = $pdo->prepare("SELECT name, role FROM departments ORDER BY role ASC, name ASC");
$deptStmt->execute();
$departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_role = $_POST['role'] ?? $user['role'];
    $new_dept = $_POST['department'] ?? $user['department'];
    $new_pass = $_POST['password'] ?? '';

    if (in_array($new_role, ['teacher', 'student'])) {
        if (!empty($new_pass)) {
            // Hash the new password before saving
            $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET role = ?, department = ?, password = ? WHERE id = ?");
            $update->execute([$new_role, $new_dept, $hashed_pass, $user_id]);
        } else {
            $update = $pdo->prepare("UPDATE users SET role = ?, department = ? WHERE id = ?");
            $update->execute([$new_role, $new_dept, $user_id]);
        }
    }
    header("Location: user_detail.php?user_id=$user_id&success=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Online Examination System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="fw-bold">Assign for <?= htmlspecialchars($user['username']); ?></h2>
            <a href="manage_users.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left px-2"></i>Back to All Users</a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body text-center">

                <div class="pfp my-5 text-center">
                    <?php
                    $db_image = $user['profile_image'];
                    $user_img = '../d_user_icon.png'; // Default fallback

                    if (!empty($db_image)) {
                        // Remove 'uploads/' prefix if it exists in the database string
                        $clean_name = str_replace("uploads/", "", $db_image);

                        // Define all possible locations where the image might live
                        $possible_paths = [
                            '../uploads/' . $clean_name,
                            '../teacher/uploads/' . $clean_name,
                            '../student/uploads/' . $clean_name
                        ];

                        // Loop through paths and set $user_img to the first one that physically exists
                        foreach ($possible_paths as $path) {
                            if (file_exists($path)) {
                                $user_img = $path;
                                break;
                            }
                        }
                    }
                    ?>

                    <img src="<?= htmlspecialchars($user_img); ?>"
                        class="rounded-circle mb-3 shadow-sm"
                        width="120"
                        height="120"
                        style="object-fit: cover; border: 3px solid #f8f9fa;"
                        alt="Profile">
                </div>

                <p><strong>Username:</strong> <?= htmlspecialchars($user['username']); ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($user['email']); ?></p>
                <p><strong>Current Role:</strong> <?= htmlspecialchars($user['role']); ?></p>
                <p><strong>Current Department:</strong> <?= htmlspecialchars($user['department'] ?? 'Not Assigned'); ?></p>
            </div>
            <hr>
            <form method="post" class="mt-4">
                <div class="d-flex justify-content-center mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <label class="form-label mb-0"><b>Current Password :</b></label>
                        <div class="input-group input-group-sm" style="width:200px;">
                            <input type="text" class="form-control bg-light" value="••••••••" readonly>
                            <span class="input-group-text"><i class="fa-solid fa-lock text-muted"></i></span>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-center mb-4">
                    <div class="d-flex align-items-center gap-2">
                        <label class="form-label mb-0"><b>New Password :</b></label>
                        <input type="password" name="password" class="form-control form-control-sm"
                            placeholder="Enter to change" style="width:200px;">
                    </div>
                </div>
                <div class="d-flex justify-content-center mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <label class="form-label mb-0"><b>Assign Role :</b></label>
                        <select name="role" class="form-select form-select-sm" style="width:150px;">
                            <option value="teacher" <?= $user['role'] === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                            <option value="student" <?= $user['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex justify-content-center mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <label class="form-label mb-0"><b>Assign Department :</b></label>
                        <select name="department" class="form-select form-select-sm" style="width:180px;">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept['name']); ?>"
                                    <?= $user['department'] === $dept['name'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($dept['name']); ?> (<?= ucfirst($dept['role']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="d-flex justify-content-center">
                    <button class="btn btn-primary">Save Changes</button>
                </div>

            </form>
        </div>
    </div>
    </div>

</body>

</html>