<?php
session_start();
require '../db.php';
require_once __DIR__ . '/../assets/includes/header.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['username'];

// Helper function to determine Grade based on Percentage
function getGrade($percentage)
{
    if ($percentage >= 90) return ['grade' => 'A+', 'color' => 'text-success'];
    if ($percentage >= 80) return ['grade' => 'A',  'color' => 'text-success'];
    if ($percentage >= 75) return ['grade' => 'A-', 'color' => 'text-info'];
    if ($percentage >= 70) return ['grade' => 'B+', 'color' => 'text-info'];
    if ($percentage >= 65) return ['grade' => 'B',  'color' => 'text-primary'];
    if ($percentage >= 60) return ['grade' => 'B-', 'color' => 'text-primary'];
    if ($percentage >= 55) return ['grade' => 'C+', 'color' => 'text-warning'];
    if ($percentage >= 50) return ['grade' => 'C',  'color' => 'text-warning'];
    if ($percentage >= 40) return ['grade' => 'D',  'color' => 'text-secondary'];
    return ['grade' => 'F', 'color' => 'text-danger'];
}

try {
    // Fetch all submissions joined with exam details AND the show_details toggle
    $stmt = $pdo->prepare("
        SELECT s.*, e.title, e.course, e.teacher_name, e.show_details 
        FROM student_submissions s
        JOIN exams e ON s.exam_id = e.id
        WHERE s.student_id = ?
        ORDER BY s.submitted_at DESC
    ");
    $stmt->execute([$student_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $results = [];
}

include 'student_header.php';
?>

<style>
    .result-card {
        border-radius: 15px;
        border: 1px solid #eee;
        transition: all 0.3s ease;
    }

    .result-card:hover {
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08) !important;
    }

    .percentage-text {
        font-size: 1.5rem;
        font-weight: 800;
    }

    .grade-box {
        font-size: 2rem;
        font-weight: 900;
        line-height: 1;
    }

    .progress {
        height: 8px;
        border-radius: 10px;
    }
</style>

<div class="dashboard-content pt-3">
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-dark">Academic Performance</h2>
            <p class="text-secondary">Summary of your completed examinations.</p>
        </div>
        <div class="text-end mb-3">
            <div class="bg-white border px-4 py-2 rounded-pill shadow-sm d-inline-block">
                <span class="text-muted small fw-bold text-uppercase">Total Exams:</span>
                <span class="text-primary fw-bold ms-2"><?= count($results); ?></span>
            </div>
        </div>
    </div>

    <div class="row">
        <?php if (!empty($results)): ?>
            <?php foreach ($results as $res):
                $score_obtained = (float)$res['total_score'];

                // We must fetch the max possible marks to calculate the percentage correctly
                $stmt_max = $pdo->prepare("SELECT SUM((end_q - start_q + 1) * marks_per_q) as max_m FROM question_ranges WHERE exam_id = ?");
                $stmt_max->execute([$res['exam_id']]);
                $max_data = $stmt_max->fetch();
                $total_possible = ($max_data['max_m'] > 0) ? (float)$max_data['max_m'] : 100.0;

                // Calculate actual percentage
                $actual_percentage = ($score_obtained / $total_possible) * 100;

                // Get grade based on percentage, NOT raw score
                $gradeData = getGrade($actual_percentage);
            ?>
                <div class="col-12 mb-4">
                    <div class="card result-card shadow-sm">
                        <div class="card-body p-4">
                            <div class="row align-items-center">
                                <div class="col-md-4 border-end">
                                    <span class="text-uppercase small fw-bold text-primary"><?= htmlspecialchars($res['course']); ?></span>
                                    <h4 class="fw-bold mt-1"><?= htmlspecialchars($res['title']); ?></h4>
                                    <p class="small text-muted mb-0">
                                        <i class="fa fa-user-circle me-1"></i> Instructor: <?= htmlspecialchars($res['teacher_name']); ?>
                                    </p>
                                    <p class="small text-muted">
                                        <i class="fa fa-calendar-check me-1"></i> <?= date('d M Y', strtotime($res['submitted_at'])); ?>
                                    </p>
                                </div>

                                <div class="col-md-4 px-4">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="small fw-bold text-muted text-uppercase">Performance</span>
                                        <span class="percentage-text <?= $gradeData['color']; ?>"><?= number_format($actual_percentage, 1); ?>%</span>
                                    </div>
                                    <div class="progress mb-2">
                                        <div class="progress-bar <?= str_replace('text', 'bg', $gradeData['color']); ?>"
                                            role="progressbar"
                                            style="width: <?= $actual_percentage; ?>%"></div>
                                    </div>
                                    <span class="small text-muted">Score: <strong><?= number_format($score_obtained, 1) ?> / <?= number_format($total_possible, 1) ?></strong></span>
                                    <br>
                                    <span class="small text-muted">Status: <strong class="text-capitalize"><?= htmlspecialchars($res['status']); ?></strong></span>
                                </div>

                                <div class="col-md-2 text-center border-start">
                                    <span class="small fw-bold text-muted text-uppercase d-block mb-1">Grade</span>
                                    <div class="grade-box <?= $gradeData['color']; ?>">
                                        <?= $gradeData['grade']; ?>
                                    </div>
                                </div>
                                <div class="col-md-2 text-end">
                                    <?php if (isset($res['show_details']) && (int)$res['show_details'] === 1): ?>
                                        <a href="view_details.php?id=<?= $res['id']; ?>" class="btn btn-primary rounded-pill w-100 mb-2 shadow-sm">
                                            <i class="fas fa-eye me-1"></i> Details
                                        </a>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-outline-secondary rounded-pill w-100 mb-1" style="opacity: 0.7;" onclick="toggleLockMsg(this)">
                                            <i class="fas fa-lock me-1"></i> Details
                                        </button>
                                        <div class="lock-alert-msg text-danger fw-bold" style="display: none; font-size: 0.65rem; line-height: 1.2; text-align: center;">
                                            Permission required from instructor
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5 bg-white rounded shadow-sm border">
                <i class="fa-solid fa-graduation-cap text-light fa-5x mb-3"></i>
                <h5 class="text-muted">No results available yet.</h5>
                <p class="small text-secondary">Complete your first exam to see your grade report here.</p>
                <a href="student_dashboard.php" class="btn btn-primary mt-2 rounded-pill px-4">Go to Exams</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<footer class="footer-container mt-5">
    <?php include '../footer.php'; ?>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleLockMsg(btn) {
        // Find the alert message div directly following the button
        const msg = btn.nextElementSibling;

        // Toggle visibility
        if (msg.style.display === "none") {
            msg.style.display = "block";

            // Auto-hide after 3 seconds for a clean look
            setTimeout(() => {
                msg.style.display = "none";
            }, 3000);
        }
    }
</script>
</body>

</html>