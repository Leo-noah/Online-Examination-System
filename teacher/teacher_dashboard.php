<?php
session_start();
require '../db.php';
require_once __DIR__ . '/../assets/includes/header.php';

// Security Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['teacher', 'tr'])) {
    header("Location: ../login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$teacher_name = $_SESSION['username'];

/// --- REFRESHED VALIDATION: Check directly from DB every load ---
$tr_year_error = false;
try {
    // 1. Fetch fresh data directly from the users table
    // Notice we are now selecting 'tr_year_level' based on your database screenshot
    $tr_stmt = $pdo->prepare("SELECT tr_year_level FROM users WHERE id = ?");
    $tr_stmt->execute([$teacher_id]);
    $fresh_year_level = $tr_stmt->fetchColumn();

    // 2. Update the session so other pages know the change happened
    $_SESSION['year_level'] = $fresh_year_level;

    // 3. Robust check: is it false, null, or just empty spaces?
    if ($fresh_year_level === false || $fresh_year_level === null || trim((string)$fresh_year_level) === '') {
        $tr_year_error = true;
    } else {
        $tr_year_error = false;
        // Store the clean value for use in the query below
        $tr_year_level = $fresh_year_level;
    }
    // --- LOGIC 3: CHECK FOR PASSWORD CHANGE PERMISSION STATUS ---
    $is_pass_approved = false;
    $is_pass_rejected = false;

    try {
        $perm_stmt = $pdo->prepare("SELECT status FROM password_permissions WHERE user_id = ? LIMIT 1");
        $perm_stmt->execute([$teacher_id]);
        $permission = $perm_stmt->fetch(PDO::FETCH_ASSOC);

        if ($permission) {
            if ($permission['status'] === 'approved') {
                $is_pass_approved = true;
            } elseif ($permission['status'] === 'rejected') {
                $is_pass_rejected = true;
            }
        }
    } catch (PDOException $e) {
        // Silent fail for permission check
    }
} catch (PDOException $e) {
    $tr_year_error = true;
}

try {
    // UPDATED QUERY: Fetch exams
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE teacher_name = :tname ORDER BY id DESC");
    $stmt->execute([':tname' => $teacher_name]);
    $all_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_exams = count($all_exams);
} catch (PDOException $e) {
    $all_exams = [];
    $total_exams = 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Teacher_dashboard | Online Examination System</title>
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

        .btn-view {
            padding-top: 8px;
            padding-bottom: 8px;
        }

        .year-badge {
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 50px;
            background-color: #e9ecef;
            color: #495057;
            font-weight: 600;
        }

        /* Large Warning Card Style matching your image */
        .warning-card {
            background: white;
            border-radius: 15px;
            padding: 60px 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-top: 20px;
            border: 1px solid #eee;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="dashboard-wrapper">
        <div class="dashboard-container">

            <div class="d-flex justify-content-between align-items-center mb-4 px-2">
                <div>
                    <h2 class="fw-bold mb-0">Welcome, Tr. <?= htmlspecialchars($teacher_name); ?></h2>
                    <p class="text-secondary">Manage your courses and upcoming examinations.</p>
                </div>
                <a href="manage_exams.php" class="btn btn-primary px-4 py-2 rounded shadow-sm fw-bold">
                    <i class="fa fa-plus me-2"></i>Create New Exam
                </a>
            </div>

            <div class="px-2">
                <?php if ($is_pass_approved): ?>
                    <div class="alert alert-success border-0 shadow-sm d-flex align-items-center mb-4" role="alert" style="border-radius: 12px;">
                        <i class="fa-solid fa-circle-check fs-4 me-3"></i>
                        <div>
                            <strong>Permission Granted!</strong> Your request to change your password has been approved by the Admin.
                            <a href="profile.php" class="alert-link ms-2">Go to Profile to change it now <i class="fa-solid fa-arrow-right small"></i></a>
                        </div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($is_pass_rejected): ?>
                    <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center mb-4" role="alert" style="border-radius: 12px;">
                        <i class="fa-solid fa-circle-xmark fs-4 me-3"></i>
                        <div>
                            <strong>Request Rejected.</strong> Your password change request was not approved.
                            Please <a href="profile.php" class="alert-link mx-1">visit your profile</a> to request again or contact the Admin team.
                        </div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="row px-2">
                <?php if ($tr_year_error): ?>
                    <div class="col-12 text-center py-5 bg-white rounded shadow-sm mt-3 border">
                        <div class="mb-3">
                            <i class="fa-solid fa-circle-exclamation text-warning" style="font-size: 3rem;"></i>
                        </div>
                        <h4 class="fw-bold text-dark">Year Level Not Assigned</h4>
                        <p class="text-muted"> Please update your profile.</p>
                        <a href="profile.php" class="btn btn-primary rounded-pill px-4">Go to Profile</a>
                    </div>

                <?php elseif (!empty($all_exams)): ?>
                    <?php foreach ($all_exams as $exam):
                        $q_stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE exam_id = ?");
                        $q_stmt->execute([$exam['id']]);
                        $q_count = $q_stmt->fetchColumn();

                        preg_match('/\d/', $exam['course'], $matches);
                        $course_digit = $matches[0] ?? null;

                        $allowed_years = explode(',', $exam['tr_year_level']);
                        $is_valid_year = in_array($course_digit, $allowed_years);
                    ?>
                        <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
                            <div class="card h-100 shadow-sm border-0" style="overflow: hidden; background: white;">
                                <div class="bg-primary text-white text-center py-2 small fw-bold d-flex justify-content-between px-3 align-items-center">
                                    <span><i class="fa fa-book me-1"></i> <?= htmlspecialchars($exam['course']); ?></span>
                                    <?php if (!$is_valid_year): ?>

                                    <?php endif; ?>
                                </div>
                                <div class="card-body p-4">
                                    <h5 class="fw-bold mb-3" style="min-height: 2.4em; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                        <?= htmlspecialchars($exam['title']); ?>
                                    </h5>

                                    <div class="mb-2 small text-muted">
                                        <i class="fa fa-users text-primary me-2"></i>
                                        <strong>Target:</strong>
                                        <span class="year-badge"><?= htmlspecialchars($exam['tr_year_level'] ?: 'Not Set'); ?></span>
                                    </div>

                                    <div class="mb-2 small text-muted">
                                        <i class="fa fa-clock text-primary me-2"></i>
                                        <strong>Duration:</strong> <?= htmlspecialchars($exam['time_limit'] ?? $exam['time'] ?? 'N/A'); ?>
                                    </div>

                                    <div class="mb-4 small text-muted">
                                        <i class="fa fa-list-check text-primary me-2"></i>
                                        <strong><?= $q_count; ?></strong> Questions
                                    </div>

                                    <div class="d-flex gap-2">
                                        <a href="view_questions.php?exam_id=<?= $exam['id']; ?>" class="btn btn-outline-primary btn-sm flex-grow-1 rounded-pill fw-bold btn-view">
                                            View Questions
                                        </a>
                                        <a href="manage_exams.php?edit=<?= $exam['id']; ?>" class="btn btn-light btn-sm border rounded-circle d-flex align-items-center justify-content-center" style="width:38px; height:38px;">
                                            <i class="fa fa-pencil text-secondary"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5 bg-white rounded shadow-sm mt-3">
                        <i class="fa-regular fa-folder-open text-light fa-5x mb-3"></i>
                        <h5 class="text-muted">No exams found.</h5>
                        <a href="manage_exams.php" class="btn btn-primary mt-3 px-4">Add Your First Exam</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="footer-container mt-auto">
        <?php include '../footer.php'; ?>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>