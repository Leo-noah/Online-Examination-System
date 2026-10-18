<?php
session_start();
require '../db.php';
require_once __DIR__ . '/../assets/includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['username'] ?? 'Student';
$submitted_exams = [];
$available_exams = [];
$total_active = 0;
$year_error = false;
try {
    $user_stmt = $pdo->prepare("SELECT year_level FROM users WHERE id = ?");
    $user_stmt->execute([$student_id]);
    $student_year = trim($user_stmt->fetchColumn());

    // --- INITIALIZE VARIABLES HERE SO THEY ALWAYS EXIST ---
    $is_pass_approved = false;
    $is_pass_rejected = false;

    if (!$student_year) {
        $year_error = true;
    } else {
        // --- LOGIC 1: FETCH AVAILABLE EXAMS ---
        $avail_stmt = $pdo->prepare("
    SELECT e.* FROM exams e 
    WHERE e.is_posted = 1 
    -- 1. Check if Year 2 is in the teacher's target list (tr_year_level)
    AND FIND_IN_SET(?, e.tr_year_level)
    
    -- 2. Validate that the course code starts with CST-2
    -- This ensures CST-5105 is hidden for a Year 2 student
    AND e.course LIKE CONCAT('CST-', ?, '%')
    
    -- 3. Submission/Retake Logic
    AND (
        e.id NOT IN (SELECT exam_id FROM student_submissions WHERE student_id = ?)
        OR 
        (e.id IN (SELECT exam_id FROM student_submissions WHERE student_id = ?) AND e.allow_retake = 1)
    )
    ORDER BY e.id DESC
");

        // Pass $student_year (2) twice, then $student_id twice
        $avail_stmt->execute([$student_year, $student_year, $student_id, $student_id]);
        $available_exams = $avail_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Update total active count
        $total_active = count($available_exams);

        // --- LOGIC 2: FETCH ONLY THE LATEST SUBMISSION PER EXAM ---
        $sub_stmt = $pdo->prepare("
            SELECT e.*, MAX(s.id) as sub_id 
            FROM exams e 
            INNER JOIN student_submissions s ON e.id = s.exam_id 
            WHERE s.student_id = ?
            GROUP BY e.id
            ORDER BY sub_id DESC
        ");
        $sub_stmt->execute([$student_id]);
        $submitted_exams = $sub_stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- LOGIC 3: CHECK FOR PASSWORD CHANGE PERMISSION STATUS ---
        $perm_stmt = $pdo->prepare("SELECT status FROM password_permissions WHERE user_id = ? LIMIT 1");
        $perm_stmt->execute([$student_id]);
        $permission = $perm_stmt->fetch(PDO::FETCH_ASSOC);

        if ($permission) {
            if ($permission['status'] === 'approved') {
                $is_pass_approved = true;
            } elseif ($permission['status'] === 'rejected') {
                $is_pass_rejected = true;
            }
        }
    }
} catch (PDOException $e) {
    // Also initialize here just in case the database fails
    $is_pass_approved = false;
    $is_pass_rejected = false;
    error_log("Dashboard Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Student Dashboard | Online Examination System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .exam-card {
            border-radius: 12px;
            transition: all 0.2s ease;
            border: none;
            background: white;
        }

        .exam-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1) !important;
        }

        .dashboard-content {
            padding: 20px;
            min-height: 85vh;
        }

        /* Submission Blue Styling */
        .card-submitted {
            border-left: 5px solid #0d6efd !important;
        }

        .badge-submitted {
            background-color: #e7f1ff;
            color: #0d6efd;
            border: 1px solid #0d6efd;
        }
    </style>
</head>

<body>
    <?php include 'student_header.php'; ?>

    <div class="container-fluid dashboard-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0 text-dark">Welcome, <?= htmlspecialchars($student_name); ?>!</h2>
                <p class="text-secondary">Explore your courses and start your scheduled exams.</p>
            </div>
            <div class="text-end">
                <div class="bg-white border px-4 py-2 rounded-pill shadow-sm d-inline-block">
                    <span class="text-muted small fw-bold text-uppercase">Available Now:</span>
                    <span class="text-primary fw-bold ms-2"><?= $total_active; ?></span>
                </div>
            </div>

        </div>

        <?php if ($is_pass_approved): ?>
            <div class="alert alert-success border-0 shadow-sm d-flex align-items-center mb-4" role="alert" style="border-radius: 12px;">
                <i class="fa-solid fa-circle-check fs-4 me-3"></i>
                <div>
                    <strong>Permission Granted!</strong> Your request to change your password has been approved by the Admin.
                    <a href="student_profile.php" class="alert-link ms-2">Go to Profile to change it now <i class="fa-solid fa-arrow-right small"></i></a>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($is_pass_rejected): ?>
            <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center mb-4" role="alert" style="border-radius: 12px;">
                <i class="fa-solid fa-circle-xmark fs-4 me-3"></i>
                <div>
                    <strong>Request Rejected.</strong> Your password change request was not approved at this time.
                    Please <a href="student_profile.php" class="alert-link mx-1">visit your profile</a> to request again or
                    <span class="fw-bold">contact the Admin team</span> for more details.
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($year_error): ?>
            <div class="col-12 text-center py-5 bg-white rounded shadow-sm mt-3 border">
                <div class="mb-3">
                    <i class="fa-solid fa-circle-exclamation text-warning" style="font-size: 3rem;"></i>
                </div>
                <h4 class="fw-bold text-dark">Year Level Not Assigned</h4>
                <p class="text-muted"> Please update your profile.</p>
                <a href="student_profile.php" class="btn btn-primary rounded-pill px-4">Go to Profile</a>
            </div>
        <?php else: ?>

            <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Available Exams</h5>
            <div class="row mb-5">
                <?php if (empty($available_exams)): ?>
                    <div class="col-12 text-center py-4 bg-white rounded border">
                        <p class="text-muted mb-0">No exams are currently posted for your level.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($available_exams as $index => $exam):
                        // 1. Fetch Question Count
                        $q_stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE exam_id = ?");
                        $q_stmt->execute([$exam['id']]);
                        $q_count = $q_stmt->fetchColumn();

                        // 2. Check if this is a retake (does it exist in the submitted_exams array?)
                        $is_retake = false;
                        foreach ($submitted_exams as $sub) {
                            if ($sub['id'] == $exam['id']) {
                                $is_retake = true;
                                break;
                            }
                        }
                    ?>
                        <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
                            <div class="card h-100 exam-card shadow-sm border position-relative">

                                <?php if ($is_retake): ?>
                                    <div class="position-absolute top-0 end-0 m-2">

                                    </div>
                                <?php endif; ?>

                                <div class="bg-primary text-white text-center py-2 small fw-bold">
                                    <i class="fa fa-book-open me-1"></i> <?= htmlspecialchars($exam['course']); ?>
                                </div>

                                <div class="card-body p-4 d-flex flex-column">
                                    <h5 class="fw-bold mb-3 text-dark"><?= htmlspecialchars($exam['title']); ?></h5>

                                    <div class="small text-muted mb-1">
                                        <i class="fa fa-user-tie text-primary me-2"></i>Instructor:
                                        <strong><?= htmlspecialchars($exam['teacher_name'] ?? 'Leo Noah'); ?></strong>
                                    </div>

                                    <div class="small text-muted mb-1">
                                        <i class="fa fa-stopwatch text-primary me-2"></i>Time Limit:
                                        <strong><?= $exam['time_limit']; ?> Mins</strong>
                                    </div>

                                    <div class="small text-muted mb-4">
                                        <i class="fa fa-clipboard-question text-primary me-2"></i>Items:
                                        <strong><?= $q_count; ?> Questions</strong>
                                    </div>

                                    <div class="mt-auto">
                                        <a href="take_exam.php?id=<?= $exam['id']; ?>"
                                            class="btn <?= $is_retake ? 'btn-outline-primary' : 'btn-primary' ?> w-100 rounded-pill shadow-sm btn-start-validation"
                                            data-qcount="<?= $q_count; ?>">
                                            <i class="fa <?= $is_retake ? 'fa-arrow-rotate-left' : 'fa-play-circle' ?> me-2"></i>
                                            <?= $is_retake ? 'Retake Exam' : 'See Exam Details to Begin' ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <hr class="mb-5">

            <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>My Submissions</h5>
            <div class="row">
                <?php if (empty($submitted_exams)): ?>
                    <div class="col-12">
                        <p class="text-muted small italic">No previous submissions found.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($submitted_exams as $exam):
                        $q_stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE exam_id = ?");
                        $q_stmt->execute([$exam['id']]);
                        $q_count = $q_stmt->fetchColumn();
                    ?>
                        <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
                            <div class="card h-100 exam-card shadow-sm border card-submitted">
                                <div class="card-body p-4 d-flex flex-column">
                                    <div class="badge badge-submitted mb-2 align-self-start text-dark">Submitted</div>
                                    <h6 class="fw-bold text-dark"><?= htmlspecialchars($exam['title']); ?></h6>
                                    <p class="small text-muted mb-3"><?= htmlspecialchars($exam['course']); ?></p>

                                    <div class="small text-muted mb-1"><i class="fa fa-user-tie text-primary me-2"></i>Instructor: <strong><?= htmlspecialchars($exam['teacher_name'] ?? 'Leo Noah'); ?></strong></div>
                                    <div class="small text-muted mb-1"><i class="fa fa-stopwatch text-primary me-2"></i>Time Limit: <strong><?= $exam['time_limit']; ?> Mins</strong></div>
                                    <div class="small text-muted mb-4"><i class="fa fa-clipboard-question text-primary me-2"></i>Items: <strong><?= $q_count; ?> Questions</strong></div>

                                    <button class="btn btn-primary w-100 rounded-pill btn-sm mt-auto shadow-sm" style="background-color: #0d6efd; border: none; opacity: 0.8;" disabled>
                                        <i class="fa fa-check-circle me-2"></i>Completed
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.querySelectorAll('.btn-start-validation').forEach(button => {
            button.addEventListener('click', function(e) {
                if (parseInt(this.getAttribute('data-qcount')) === 0) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Empty Exam',
                        text: 'No questions added yet.'
                    });
                }
            });
        });
    </script>
</body>

</html>