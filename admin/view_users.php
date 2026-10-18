<?php
session_start();
require '../db.php';

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Pagination & search setup
$teachers_page = max(1, (int)($_GET['teachers_page'] ?? 1));
$students_page = max(1, (int)($_GET['students_page'] ?? 1));
$limit = 5; // 5 users per page

$teacher_search = $_GET['teacher_search'] ?? '';
$student_search = $_GET['student_search'] ?? '';

// Function to fetch users by role with search and pagination
function getUsersByRole($pdo, $role, $search, $page, $limit)
{
    $offset = ($page - 1) * $limit;
    $params = [':role' => $role];
    $search_sql = '';

    if (!empty($search)) {
        $search_sql = " AND username LIKE :search";
        $params[':search'] = "%$search%";
    }

    // Total count for pagination
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = :role $search_sql");
    $count_stmt->execute($params);
    $total = (int)$count_stmt->fetchColumn();

    // Fetch paginated users
    $stmt = $pdo->prepare("
        SELECT id, username, email, role, country, city, profile_image
        FROM users
        WHERE role = :role $search_sql
        ORDER BY username ASC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return ['users' => $users, 'total' => $total];
}

// Fetch teachers and students
$teacher_data = getUsersByRole($pdo, 'teacher', $teacher_search, $teachers_page, $limit);
$student_data = getUsersByRole($pdo, 'student', $student_search, $students_page, $limit);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>View Users | Online Examination System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .profile-img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 50%;
        }

        .role-box {
            padding: 20px;
            margin-bottom: 40px;
            border-radius: 12px;
            background-color: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead tr {
            border-bottom: 1px solid #ddd;
        }

        tr {
            height: 60px;
            vertical-align: middle;
        }

        td {
            padding: 8px 12px;
        }

        .user-role {
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 8px;
            color: #fff;
        }

        .user-role.teacher {
            background-color: #0d6efd;
        }

        .user-role.student {
            background-color: #198754;
        }

        .pagination a {
            text-decoration: none;
        }

        .search-form {
            max-width: 300px;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">View Users</h2>
            <div class="right">
                <a href="manage_users.php" class="btn btn-primary mr-2 rounded shadow-sm fw-bold">
                    <i class="fa fa-user-gear me-2"></i>Manage Users
                </a>
                <a href="admin_dashboard.php" class="btn btn-secondary"><i class="fa fa-arrow-left me-2"></i>Back to Dashboard</a>
            </div>
        </div>

        <!-- TEACHERS -->
        <div class="role-box">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4>Teachers</h4>
                <form class="d-flex search-form" method="get">
                    <input type="text" class="form-control me-2" name="teacher_search" placeholder="Search teachers..." value="<?= htmlspecialchars($teacher_search) ?>">
                    <button class="btn btn-primary" type="submit">Search</button>
                </form>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 60px;">Photo</th>
                        <th style="width: 180px;">Name</th>
                        <th style="width: 220px;">Email</th>
                        <th style="width: 180px;">Location</th>
                        <th style="width: 90px;">Role</th>
                        <th style="width: 120px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($teacher_data['users'])): ?>
                        <?php foreach ($teacher_data['users'] as $user): ?>
                            <tr>
                                <td>
                                    <?php
                                    $db_image = $user['profile_image'];
                                    $user_img = '../d_user_icon.png'; // Default

                                    if (!empty($db_image)) {
                                        // Clean 'uploads/' prefix if it's already in the database string
                                        $clean_name = str_replace("uploads/", "", $db_image);

                                        // Check all three potential physical folder locations
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
                                    <img src="<?= htmlspecialchars($user_img); ?>" class="profile-img" alt="Profile">
                                </td>
                                <td><?= htmlspecialchars($user['username']); ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($user['email']); ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($user['city'] . ', ' . $user['country']); ?></td>
                                <td><span class="user-role teacher"><?= htmlspecialchars($user['role']); ?></span></td>
                                <td><a href="user_detail.php?user_id=<?= $user['id']; ?>" class="btn btn-outline-primary btn-sm">View Details</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-3">No teachers found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($teacher_data['total'] > $limit): ?>
                <?php $total_pages = ceil($teacher_data['total'] / $limit); ?>
                <nav>
                    <ul class="pagination mt-3">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $teachers_page ? 'active' : '' ?>">
                                <a class="page-link" href="?teachers_page=<?= $i ?>&teacher_search=<?= urlencode($teacher_search) ?>&students_page=<?= $students_page ?>&student_search=<?= urlencode($student_search) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>

        <!-- STUDENTS -->
        <div class="role-box">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4>Students</h4>
                <form class="d-flex search-form" method="get">
                    <input type="text" class="form-control me-2" name="student_search" placeholder="Search students..." value="<?= htmlspecialchars($student_search) ?>">
                    <button class="btn btn-primary" type="submit">Search</button>
                </form>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 60px;">Photo</th>
                        <th style="width: 180px;">Name</th>
                        <th style="width: 220px;">Email</th>
                        <th style="width: 180px;">Location</th>
                        <th style="width: 90px;">Role</th>
                        <th style="width: 120px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($student_data['users'])): ?>
                        <?php foreach ($student_data['users'] as $user): ?>
                            <tr>
                                <td>
                                    <?php
                                    $db_image = $user['profile_image'];
                                    $user_img = '../d_user_icon.png';

                                    if (!empty($db_image)) {
                                        $clean_name = str_replace("uploads/", "", $db_image);

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
                                    <img src="<?= htmlspecialchars($user_img); ?>" class="profile-img" alt="Profile">
                                </td>
                                <td><?= htmlspecialchars($user['username']); ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($user['email']); ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($user['city'] . ', ' . $user['country']); ?></td>
                                <td><span class="user-role student"><?= htmlspecialchars($user['role']); ?></span></td>
                                <td><a href="user_detail.php?user_id=<?= $user['id']; ?>" class="btn btn-outline-primary btn-sm">View Details</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-3">No students found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($student_data['total'] > $limit): ?>
                <?php $total_pages = ceil($student_data['total'] / $limit); ?>
                <nav>
                    <ul class="pagination mt-3">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $students_page ? 'active' : '' ?>">
                                <a class="page-link" href="?students_page=<?= $i ?>&student_search=<?= urlencode($student_search) ?>&teachers_page=<?= $teachers_page ?>&teacher_search=<?= urlencode($teacher_search) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>

    <footer class="footer-container">
        <?php include '../footer.php'; ?>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>