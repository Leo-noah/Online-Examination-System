<?php
session_start();
require '../db.php';

// Assume admin is logged in, and we have their ID stored in session
$admin_id = $_SESSION['user_id'] ?? null;

$search = $_GET['search'] ?? '';
$search_sql = '';
$params = [':admin_id' => $admin_id]; // Exclude current admin

if (!empty($search)) {
    $search_sql = "AND username LIKE :search";
    $params[':search'] = "%" . $search . "%";
}

// Fetch all users except admins and current admin
$stmt = $pdo->prepare("
    SELECT id, username, email, role, country, city, profile_image 
    FROM users 
    WHERE role != 'admin' AND id != :admin_id
    $search_sql
    ORDER BY role ASC
");
$stmt->execute($params);
$all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle deletion
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $del_stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
    $del_stmt->execute([$delete_id]);
    header("Location: manage_roles.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Roles | Online Examination System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="fw-bold">All Users</h2>
            <a href="admin_dashboard.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left px-2"></i>Back to Dashboard</a>
        </div>

        <form method="get" class="mb-4">
            <div class="input-group">
                <input type="text" name="search" class="form-control" placeholder="Search users by name..." value="<?= htmlspecialchars($search); ?>">
                <button class="btn btn-primary" type="submit">Search</button>
            </div>
        </form>

        <div class="row">
            <?php if (!empty($all_users)): ?>
                <?php foreach ($all_users as $user): ?>
                    <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
                        <div class="card h-100 shadow-sm border-0">
                            <?php
                            $db_image = $user['profile_image'];
                            $final_src = "../d_user_icon.png"; // Default fallback

                            if (!empty($db_image)) {
                                // Remove any existing "uploads/" prefix from the DB string to avoid doubling it
                                $clean_name = str_replace("uploads/", "", $db_image);

                                // Check the three possible actual folder locations
                                $possible_paths = [
                                    "../uploads/" . $clean_name,
                                    "../teacher/uploads/" . $clean_name,
                                    "../student/uploads/" . $clean_name
                                ];

                                foreach ($possible_paths as $path) {
                                    if (file_exists($path)) {
                                        $final_src = $path;
                                        break;
                                    }
                                }
                            }
                            ?>

                            <img src="<?= $final_src; ?>" class="card-img-top" alt="Profile" style="height: 200px; object-fit: cover;">

                            <div class="card-body">
                                <h5 class="fw-bold mb-2"><?= htmlspecialchars($user['username']); ?></h5>
                                <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($user['email']); ?></p>
                                <p class="mb-1"><strong>Role:</strong> <?= htmlspecialchars($user['role']); ?></p>
                                <p class="mb-1"><strong>Location:</strong> <?= htmlspecialchars($user['city'] . ', ' . $user['country']); ?></p>

                                <div class="d-flex gap-2 mt-2">
                                    <a href="assign_rcy.php?user_id=<?= $user['id']; ?>" class="btn btn-outline-primary btn-sm flex-grow-1">
                                        Edit
                                    </a>
                                    <a href="manage_users.php?delete_id=<?= $user['id']; ?>" onclick="return confirm('Are you sure you want to delete this user?');" class="btn btn-outline-danger btn-sm">
                                        Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5 bg-white rounded shadow-sm">
                    <h5 class="text-muted">No users found.</h5>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>