<?php
session_start();
require '../db.php';

// Backup database as SQL file
if (isset($_POST['backup_db'])) {
    $dbHost = 'localhost';
    $dbName = 'uit_exam_system'; // change to your DB name
    $dbUser = 'root';
    $dbPass = '';

    $backupFile = 'backup_' . date("Y-m-d_H-i-s") . '.sql';
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $backupFile . '"');

    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        // CREATE TABLE
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        echo $create['Create Table'] . ";\n\n";

        // INSERT DATA
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $columns = array_map(fn($c) => "`$c`", array_keys($row));
            $values = array_map(fn($v) => $pdo->quote($v), array_values($row));
            echo "INSERT INTO `$table` (" . implode(",", $columns) . ") VALUES (" . implode(",", $values) . ");\n";
        }
        echo "\n\n";
    }
    exit;
}
// Fetch the current saved activation date
$stmt_get = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'login_start_date'");
$stmt_get->execute();
$current_setting = $stmt_get->fetchColumn() ?: ""; // Falls back to empty string if no record exists
// Handle Login Activation Date Update
if (isset($_POST['set_duration'])) {
    $activation_date = $_POST['activation_date'];
    // REPLACE INTO works like an update if the key exists, or insert if it doesn't
    $stmt = $pdo->prepare("REPLACE INTO system_settings (setting_key, setting_value) VALUES ('login_start_date', ?)");
    if ($stmt->execute([$activation_date])) {
        echo "<script>alert('Login activation date updated successfully!'); window.location='admin_dashboard.php';</script>";
    }
}

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['username'];

// Search filter
$search = $_GET['search'] ?? '';
$search_sql = '';
$params = [':admin_id' => $admin_id];

if (!empty($search)) {
    $search_sql = "AND username LIKE :search";
    $params[':search'] = "%" . $search . "%";
}

// Fetch total non-admin users count
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role != 'admin' AND id != :admin_id $search_sql");
$count_stmt->execute($params);
$total_users = $count_stmt->fetchColumn();

// Fetch first 4 non-admin users for dashboard preview
$limit_sql = "LIMIT 12";
$stmt = $pdo->prepare("
    SELECT id, username, email, role, country, city, profile_image
    FROM users
    WHERE role != 'admin' AND id != :admin_id
    $search_sql
    ORDER BY role ASC
    $limit_sql
");
$stmt->execute($params);
$users_preview = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle deletion
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $del_stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
    $del_stmt->execute([$delete_id]);
    header("Location: admin_dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | Online Examination System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .dashboard-wrapper {
            padding: 10px;
            width: 100%;
            background-color: #f4f7f9;
            min-height: 100vh;
        }

        .dashboard-container {
            width: 100%;
            max-width: none;
            margin: 0;
        }

        .card {
            border-radius: 12px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1) !important;
        }

        .btn-action {
            padding-top: 8px;
            padding-bottom: 8px;
        }

        .profile-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 50%;
            display: inline-block;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="dashboard-wrapper">
        <div class="dashboard-container">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-0">Welcome, <?= htmlspecialchars($admin_name); ?></h2>
                    <p class="text-secondary">Manage user roles and year for your system.</p>
                </div>
                <div class="right d-flex align-items-center">
                    <form method="post" style="margin-right: 10px;">
                        <button name="backup_db" class="btn btn-secondary px-4 py-2 rounded shadow-sm fw-bold">
                            <i class="fa fa-database me-2"></i>Backup Database
                        </button>
                    </form>

                    <button type="button" class="btn btn-warning px-4 py-2 rounded shadow-sm fw-bold me-2" data-bs-toggle="modal" data-bs-target="#durationModal">
                        <i class="fa fa-clock me-2"></i>Set Login Duration
                    </button>

                    <a href="manage_users.php" class="btn btn-primary px-4 py-2 rounded shadow-sm fw-bold">
                        <i class="fa fa-user-shield me-2"></i>Manage Users
                    </a>
                </div>
            </div>

            <!-- Search -->
            <!-- <form method="get" class="mb-4">
            <div class="input-group">
                <input type="text" name="search" class="form-control" placeholder="Search users by name..." value="<?= htmlspecialchars($search); ?>">
                <button class="btn btn-primary" type="submit">Search</button>
            </div>
        </form> -->

            <div class="row">
                <?php if (!empty($users_preview)): ?>
                    <?php foreach ($users_preview as $user): ?>
                        <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
                            <div class="card h-100 shadow-sm border-0 p-3 text-center">
                                <?php
                                // IMAGE LOGIC FROM USER VIEW PAGE
                                $db_image = $user['profile_image'];
                                $user_img = '../d_user_icon.png'; // Default fallback

                                if (!empty($db_image)) {
                                    // Remove 'uploads/' if it's already in the DB string
                                    $clean_name = str_replace("uploads/", "", $db_image);

                                    // Check all possible directories
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

                                <img src="<?= htmlspecialchars($user_img); ?>" class="profile-img mx-auto" alt="Profile" style="width: 80px; height: 80px; object-fit: cover; border-radius: 50%;">

                                <div class="bg-primary text-white text-center py-1 small fw-bold mb-2 mt-2">
                                    <?= htmlspecialchars($user['role']); ?>
                                </div>

                                <h5 class="fw-bold mb-2"><?= htmlspecialchars($user['username']); ?></h5>

                                <div class="mb-1 small text-muted text-truncate">
                                    <i class="fa fa-envelope text-primary me-2"></i><?= htmlspecialchars($user['email']); ?>
                                </div>

                                <div class="mb-3 small text-muted">
                                    <i class="fa fa-map-marker-alt text-primary me-2"></i><?= htmlspecialchars($user['city'] . ', ' . $user['country']); ?>
                                </div>

                                <div class="d-flex gap-2 justify-content-center">
                                    <a href="assign_rcy.php?user_id=<?= $user['id']; ?>" class="btn btn-outline-primary btn-sm flex-grow-1 rounded-pill fw-bold btn-action">
                                        Manage
                                    </a>
                                    <a href="admin_dashboard.php?delete_id=<?= $user['id']; ?>" onclick="return confirm('Are you sure you want to delete this user?');" class="btn btn-outline-danger btn-sm">
                                        Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5 bg-white rounded shadow-sm mt-3">
                        <h5 class="text-muted">No users found.</h5>
                        <a href="assign_rcy.php" class="btn btn-primary mt-3 px-4">Add Users / Assign Roles</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- More Users Button -->
            <?php if ($total_users > 4): ?>
                <div class="text-center mt-3">
                    <a href="manage_users.php" class="btn btn-primary px-4 py-2 fw-bold">
                        <i class="fa fa-users me-2"></i>More Users
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <footer class="footer-container">
        <?php include '../footer.php'; ?>
    </footer>
    <div class="modal fade" id="durationModal" tabindex="-1" aria-labelledby="durationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
                <div class="modal-header bg-warning text-dark border-0" style="border-radius: 20px 20px 0 0;">
                    <h5 class="modal-title fw-bold" id="durationModalLabel"><i class="fa fa-stopwatch me-2"></i>System Access Timing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <p class="text-muted small mb-4">Set the exact date and time when Students and Teachers (non-admins) will be allowed to log in.</p>

                        <label class="form-label fw-bold text-secondary small uppercase">Activation Date & Time</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fa fa-calendar-alt text-warning"></i></span>
                            <input type="datetime-local"
                                name="activation_date"
                                class="form-control border-start-0 bg-light"
                                value="<?= htmlspecialchars($current_setting); ?>"
                                required>
                        </div>

                        <?php if ($current_setting): ?>
                            <div class="mt-2 p-2 bg-light rounded border-start border-warning border-4">
                                <small class="text-muted">Current Access Starts: </small>
                                <span class="fw-bold"><?= date("M d, Y - h:i A", strtotime($current_setting)); ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="mt-3 p-3 bg-light rounded-3">
                            <small class="text-info"><i class="fa fa-info-circle me-1"></i> Admins can always log in regardless of this setting.</small>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-3">
                        <button type="button" class="btn btn-light px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="set_duration" class="btn btn-warning px-4 fw-bold shadow-sm">Save Activation Time</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>