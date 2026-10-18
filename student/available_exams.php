<?php
session_start();
require '../db.php';
require_once __DIR__ . '/../assets/includes/header.php';

// 1. Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// 2. Define Variables
$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['username'] ?? 'Student';
$available_exams = [];
$total_available = 0;
$year_error = false; // Initialized to fix the Undefined Variable warning

// 3. Security Check for specific exam (Only if ID is passed in URL)
if (isset($_GET['id'])) {
    $exam_id = $_GET['id'];
    $check = $pdo->prepare("SELECT allow_retake, is_posted FROM exams WHERE id = ?");
    $check->execute([$exam_id]);
    $exam_data = $check->fetch();

    $sub_check = $pdo->prepare("SELECT id FROM student_submissions WHERE exam_id = ? AND student_id = ?");
    $sub_check->execute([$exam_id, $student_id]);

    if ($sub_check->fetch() && ($exam_data['allow_retake'] ?? 0) == 0) {
        header("Location: student_dashboard.php?error=no_retake");
        exit();
    }
}

try {
    // 4. Get student year
    $user_stmt = $pdo->prepare("SELECT year_level FROM users WHERE id = ?");
    $user_stmt->execute([$student_id]);
    $student_year = trim($user_stmt->fetchColumn());

    if (!$student_year) {
        $year_error = true; // Set to true so the HTML shows the Profile link
    } else {
        // 5. Fetch Exams - Year Level & Course Prefix Validation
        $stmt = $pdo->prepare("
    SELECT e.* FROM exams e 
    WHERE e.is_posted = 1 
    -- 1. Ensure the teacher targeted the student's specific year
    AND FIND_IN_SET(?, e.tr_year_level)
    
    -- 2. Validate course digit: Matches 'CST-2%' if student year is 2
    -- This hides CST-5105 for Year 2 students
    AND e.course LIKE CONCAT('CST-', ?, '%')
    
    -- 3. Submission/Retake Logic
    AND (
        e.id NOT IN (SELECT exam_id FROM student_submissions WHERE student_id = ?)
        OR 
        (e.id IN (SELECT exam_id FROM student_submissions WHERE student_id = ?) AND e.allow_retake = 1)
    )
    ORDER BY e.id DESC
");

        // You now have FOUR '?' placeholders, so you must pass FOUR variables
        $stmt->execute([$student_year, $student_year, $student_id, $student_id]);

        $available_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_available = count($available_exams);
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

include 'student_header.php';
?>
<style>
    .exam-card {
        border-radius: 12px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border: none;
        overflow: hidden;
        background: white;
    }

    .exam-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1) !important;
    }

    .btn-start {
        padding: 10px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .dashboard-content {
        padding-top: 10px;
    }

    .status-badge {
        font-size: 0.75rem;
        padding: 5px 12px;
    }
</style>

<div class="dashboard-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-dark">Upcoming Exams</h2>
            <p class="text-secondary">Ready to test your knowledge? Choose an available exam below.</p>
        </div>
        <div class="text-end">
            <div class="bg-white border px-4 py-2 rounded-pill shadow-sm d-inline-block">
                <span class="text-muted small fw-bold text-uppercase">Pending Exams:</span>
                <span class="text-danger fw-bold ms-2"><?= $total_available; ?></span>
            </div>
        </div>
    </div>

    <div class="row">
        <?php if ($year_error): ?>
            <div class="col-12 text-center py-5 bg-white rounded shadow-sm mt-3 border">
                <div class="mb-3">
                    <i class="fa-solid fa-circle-exclamation text-warning" style="font-size: 3rem;"></i>
                </div>
                <h4 class="fw-bold text-dark">Year Level Not Assigned</h4>
                <p class="text-muted"> Please update your profile.</p>
                <a href="student_profile.php" class="btn btn-primary rounded-pill px-4">Go to Profile</a>
            </div>

        <?php elseif (!empty($available_exams)): ?>
            <?php foreach ($available_exams as $exam):
                // 1. Fetch Question Count
                $q_stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE exam_id = ?");
                $q_stmt->execute([$exam['id']]);
                $q_count = $q_stmt->fetchColumn();

                // 2. Check if this is a retake 
                // Logic: Check if exam ID exists in the student's submission history
                $check_sub = $pdo->prepare("SELECT id FROM student_submissions WHERE exam_id = ? AND student_id = ? LIMIT 1");
                $check_sub->execute([$exam['id'], $student_id]);
                $is_retake = $check_sub->fetch();
            ?>
                <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
                    <div class="card h-100 exam-card shadow-sm border-0 <?= $is_retake ? 'border-top border-primary border-4' : '' ?> position-relative">

                        <?php if ($is_retake): ?>
                            <div class="position-absolute top-0 end-0 m-2">

                            </div>
                        <?php endif; ?>

                        <div class="bg-primary text-white text-center py-2 small fw-bold">
                            <i class="fa fa-book-open me-1"></i> <?= htmlspecialchars($exam['course']); ?>
                        </div>

                        <div class="card-body p-4 d-flex flex-column">
                            <h5 class="fw-bold mb-3 text-dark" style="min-height: 2.4em; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                <?= htmlspecialchars($exam['title']); ?>
                            </h5>

                            <div class="mb-2 small text-muted">
                                <i class="fa fa-user-tie text-primary me-2"></i>
                                <strong>Instructor:</strong> <?= htmlspecialchars($exam['teacher_name'] ?? 'Staff'); ?>
                            </div>

                            <div class="mb-2 small text-muted">
                                <i class="fa fa-stopwatch text-primary me-2"></i>
                                <strong>Time Limit:</strong>
                                <?= htmlspecialchars($exam['time_limit'] ?? '0'); ?> Mins
                            </div>

                            <div class="mb-4 small text-muted">
                                <i class="fa fa-clipboard-question text-primary me-2"></i>
                                <strong>Items:</strong> <?= $q_count; ?> Questions
                            </div>

                            <div class="mt-auto">
                                <a href="take_exam.php?id=<?= $exam['id']; ?>"
                                    class="btn <?= $is_retake ? 'btn-outline-primary' : 'btn-primary' ?> w-100 rounded-pill btn-start shadow-sm btn-start-validation"
                                    data-qcount="<?= $q_count; ?>">
                                    <i class="fa <?= $is_retake ? 'fa-arrow-rotate-left' : 'fa-play-circle' ?> me-2"></i>
                                    <?= $is_retake ? 'Retake Exam' : 'See Exam Details to Begin' ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5 bg-white rounded shadow-sm mt-3 border">
                <h4 class="fw-bold">All caught up!</h4>
                <p class="text-muted">You have no upcoming or pending exams at the moment.</p>
                <a href="my_results.php" class="btn btn-outline-primary rounded-pill px-4">View My Results</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<footer class="footer-container mt-5">
    <?php include '../footer.php'; ?>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.btn-start-validation').forEach(button => {
            button.addEventListener('click', function(e) {
                const qCount = parseInt(this.getAttribute('data-qcount'));
                if (qCount === 0 || isNaN(qCount)) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Empty Exam',
                        text: 'You cannot start this exam because there are no questions added yet. Please wait for teacher assigning.',
                        confirmButtonColor: '#0d6efd',
                        confirmButtonText: 'Understood'
                    });
                }
            });
        });
    });
</script>
</body>

</html>