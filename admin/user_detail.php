<?php
session_start();
require '../db.php';

$user_id = $_GET['user_id'] ?? null;
if (!$user_id) die("User ID is required.");

// Fetch main user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role != 'admin'");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) die("User not found or cannot view admin role.");


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>User Detail | Online Examination System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        .info-label {
            font-weight: bold;
            width: 150px;
            text-align: right;
        }

        .info-value {
            text-align: left;
        }

        .user-info-row {
            margin-bottom: 8px;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="fw-bold">User Detail: <?= htmlspecialchars($user['username']); ?></h2>
            <a href="view_users.php" class="btn btn-secondary">Back to All Users</a>
        </div>
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show text-center" role="alert">
                User Configured Successfully
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm p-4">
            <div class="text-center mb-4">
                <?php
                $db_image = $user['profile_image'];
                $user_img = '../d_user_icon.png'; // Default fallback

                if (!empty($db_image)) {
                    // Remove 'uploads/' if it's already in the DB string to prevent ../uploads/uploads/
                    $clean_name = str_replace("uploads/", "", $db_image);

                    // Check possible directories
                    $possible_paths = [
                        '../uploads/' . $clean_name,
                        '../teacher/uploads/' . $clean_name,
                        '../student/uploads/' . $clean_name
                    ];

                    foreach ($possible_paths as $path) {
                        if (file_exists($path)) {
                            $user_img = $path;
                            break;
                        }
                    }
                }
                ?>
                <img src="<?= htmlspecialchars($user_img); ?>" class="rounded-circle shadow-sm" width="120" height="120" style="object-fit: cover; border: 3px solid #f8f9fa;" alt="Profile">
            </div>

            <div class="d-flex flex-column align-items-start" style="padding-left: 333px;">
                <div class="user-info-row d-flex">
                    <div class="info-label">Username :</div>
                    <div class="info-value ms-2"><?= htmlspecialchars($user['username']); ?></div>
                </div>
                <div class="user-info-row d-flex">
                    <div class="info-label">ID Number :</div>
                    <div class="info-value ms-2">
                        <?php
                        // If it's a student, show stu_id; if teacher, show tr_id
                        if ($user['role'] === 'student') {
                            echo htmlspecialchars($user['stu_id'] ?? 'Not Assigned');
                        } else {
                            echo htmlspecialchars($user['tr_id'] ?? 'Not Assigned');
                        }
                        ?>
                    </div>
                </div>
                <div class="user-info-row d-flex">
                    <div class="info-label">Email :</div>
                    <div class="info-value ms-2"><?= htmlspecialchars($user['email']); ?></div>
                </div>
                <div class="user-info-row d-flex">
                    <div class="info-label">Country :</div>
                    <div class="info-value ms-2"><?= htmlspecialchars($user['country']); ?></div>
                </div>
                <div class="user-info-row d-flex">
                    <div class="info-label">City :</div>
                    <div class="info-value ms-2"><?= htmlspecialchars($user['city']); ?></div>
                </div>
                <div class="user-info-row d-flex">
                    <div class="info-label">Address :</div>
                    <div class="info-value ms-2"><?= htmlspecialchars($user['address']); ?></div>
                </div>
                <div class="user-info-row d-flex">
                    <div class="info-label">Phone :</div>
                    <div class="info-value ms-2"><?= htmlspecialchars($user['phone']); ?></div>
                </div>
                <div class="user-info-row d-flex">
                    <div class="info-label">Department :</div>
                    <div class="info-value ms-2"><?= htmlspecialchars($user['department']); ?></div>
                </div>
                <div class="user-info-row d-flex">
                    <div class="info-label">Course :</div>
                    <div class="info-value ms-2"><?= htmlspecialchars($user['course']); ?></div>
                </div>
                <div class="user-info-row d-flex">
                    <div class="info-label">Role :</div>
                    <div class="info-value ms-2"><?= htmlspecialchars($user['role']); ?></div>
                </div>

                <?php if ($user['role'] === 'student'): ?>
                    <div class="user-info-row d-flex">
                        <div class="info-label">Student ID :</div>
                        <div class="info-value ms-2"><?= htmlspecialchars($user['stu_id'] ?? 'Not Assigned'); ?></div>
                    </div>
                    <div class="user-info-row d-flex">
                        <div class="info-label">Year Level :</div>
                        <div class="info-value ms-2"><?= htmlspecialchars($user['year_level'] ?? 'N/A'); ?></div>
                    </div>
                <?php elseif ($user['role'] === 'teacher'): ?>
                    <div class="user-info-row d-flex">
                        <div class="info-label">Teacher ID :</div>
                        <div class="info-value ms-2"><?= htmlspecialchars($user['tr_id'] ?? 'Not Assigned'); ?></div>
                    </div>
                    <div class="user-info-row d-flex">
                        <div class="info-label">Teaching Semester :</div>
                        <div class="info-value ms-2"><?= htmlspecialchars($user['tr_year_level'] ?? 'N/A'); ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>