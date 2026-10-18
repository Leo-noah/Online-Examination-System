<?php
session_start();
require '../db.php';
require_once __DIR__ . '/../assets/includes/header.php';

// Security Check - Ensure only students can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$exam_id = $_GET['id'] ?? null;

if (!$exam_id) {
    header("Location: student_dashboard.php");
    exit();
}

try {
    // Fetch Exam Details
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        die("Exam not found.");
    }

    // Fetch Question Count
    $q_stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE exam_id = ?");
    $q_stmt->execute([$exam_id]);
    $total_questions = $q_stmt->fetchColumn();

    // --- TIME VALIDATION LOGIC ---
    $current_time = new DateTime();
    $exam_start = new DateTime($exam['exam_date']);

    // Check if the current time is before the start time
    $is_locked = ($current_time < $exam_start);

    // Check if the current time is past the deadline (due_date)
    $is_expired = false;
    if (!empty($exam['due_date'])) {
        $exam_deadline = new DateTime($exam['due_date']);
        $is_expired = ($current_time > $exam_deadline);
    }
    // Formatted strings for the UI badges
    $formatted_date = $exam_start->format('M d, Y');
    $formatted_time = $exam_start->format('h:i A');
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Take_exam | Online Examination System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        .start-exam-container {
            max-width: 850px;
            margin: 20px auto;

        }

        .instruction-card {
            border: none;
            border-radius: 15px;
            background: white;
            overflow: hidden;
        }

        .instruction-header {
            background-color: var(--uit-blue);
            color: white;
            padding: 35px;
            text-align: center;
        }

        .instruction-body {
            padding: 40px;
        }

        .info-badge {
            background: #f0f7ff;
            border: 1px solid #d0e5ff;
            border-radius: 12px;
            padding: 18px;
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-badge i {
            font-size: 1.8rem;
            color: var(--uit-blue);
        }

        .section-title {
            font-weight: 700;
            color: #333;
            border-left: 5px solid var(--uit-blue);
            padding-left: 15px;
            margin-top: 30px;
            margin-bottom: 20px;
        }

        .rule-item {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
            font-size: 0.95rem;
            color: #444;
        }

        .rule-item i {
            color: var(--uit-blue);
            margin-top: 4px;
        }

        /* Anti-Cheat Styling */
        .anti-cheat-box {
            background-color: #fff8f8;
            border: 1px solid #ffe5e5;
            border-radius: 10px;
            padding: 20px;
        }

        .anti-cheat-box i {
            color: #dc3545;
            /* Warning Red */
        }

        .btn-begin {
            padding: 18px 50px;
            font-size: 1.25rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: transform 0.2s;
        }

        .btn-begin:hover {
            transform: scale(1.03);
        }
    </style>
</head>

<body>
    <?php include 'student_header.php'; ?>

    <div class="start-exam-container">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="student_dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Exam Instructions</li>
            </ol>
        </nav>

        <div class="card instruction-card shadow-sm">
            <div class="instruction-header">
                <i class="fa fa-user-shield fa-4x mb-3"></i>
                <h2 class="fw-bold mb-0"><?= htmlspecialchars($exam['title']); ?></h2>
                <p class="opacity-75 mb-0 mt-2"><?= htmlspecialchars($exam['course']); ?></p>
            </div>

            <div class="instruction-body">
                <div class="row g-3 mb-2 align-items-stretch">
                    <div class="col-md-3">
                        <div class="info-badge h-100">
                            <i class="fa-solid fa-hourglass-start"></i>
                            <div>
                                <small class="text-primary d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Time Duration</small>
                                <span class="fw-bold fs-5 text-dark"><?= htmlspecialchars($exam['time_limit'] ?? '0'); ?> Mins</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="info-badge h-100">
                            <i class="fa-solid fa-layer-group"></i>
                            <div>
                                <small class="text-primary d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Total Questions</small>
                                <span class="fw-bold fs-5 text-dark"><?= $total_questions; ?> Qs</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="info-badge h-100">
                            <i class="fa-solid fa-calendar-check text-primary"></i>
                            <div>
                                <small class="text-primary d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Exam Starts</small>
                                <span class="fw-bold text-dark" style="font-size: 1rem;"><?= $formatted_date; ?></span><br>
                                <span class="text-primary fw-bold" style="font-size: 0.85rem;">@ <?= $formatted_time; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="info-badge h-100">
                            <i class="fa-solid fa-clock-rotate-left text-primary"></i>
                            <div>
                                <small class="text-primary d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Exam Deadline</small>
                                <span class="fw-bold text-dark" style="font-size: 1rem;">
                                    <?= !empty($exam['due_date']) ? date("M d, Y", strtotime($exam['due_date'])) : 'No Limit'; ?>
                                </span><br>
                                <span class="text-primary fw-bold" style="font-size: 0.85rem;">
                                    <?= !empty($exam['due_date']) ? date("h:i A", strtotime($exam['due_date'])) : '--:--'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <h5 class="section-title"><i class="fa fa-clock me-2"></i>Time Limit Policy</h5>
                <div class="px-3">
                    <div class="rule-item">
                        <i class="fa fa-circle-check"></i>
                        <span>Once you click <strong>"Begin Exam"</strong>, the timer will start immediately and cannot be paused.</span>
                    </div>
                    <div class="rule-item">
                        <i class="fa fa-circle-check"></i>
                        <span>Do not refresh the page or close the browser window. Doing so may result in loss of progress.</span>
                    </div>
                    <div class="rule-item">
                        <i class="fa fa-circle-check"></i>
                        <span>The system will <strong>automatically submit</strong> your exam when the timer reaches zero.</span>
                    </div>
                </div>

                <h5 class="section-title"><i class="fa fa-shield-halved me-2"></i>Anti-Cheat Monitoring</h5>
                <div class="anti-cheat-box mx-2">
                    <div class="rule-item mb-3">
                        <i class="fa fa-triangle-exclamation"></i>
                        <span class="fw-bold text-danger">Strict Monitoring: Tab switching or minimizing the window is logged.</span>
                    </div>
                    <div class="rule-item">
                        <i class="fa fa-ban"></i>
                        <span>Opening other websites, applications, or developer tools is strictly prohibited.</span>
                    </div>
                    <div class="rule-item">
                        <i class="fa fa-microchip"></i>
                        <span>AI tools, external resources, or smart devices are not permitted during the session.</span>
                    </div>
                    <div class="rule-item">
                        <i class="fa fa-wifi"></i>
                        <span>Ensure you have a stable internet connection. Disconnections do not stop the timer.</span>
                    </div>
                </div>

                <div class="text-center mt-5">
                    <?php if ($is_expired): ?>
                        <button class="btn btn-danger btn-begin rounded-pill shadow" disabled style="background-color: #dc3545; border: none;">
                            <i class="fa fa-clock-rotate-left me-2"></i> Exam Expired
                        </button>
                        <p class="text-danger fw-bold mt-3">
                            The deadline for this exam was <?= date("M d, Y h:i A", strtotime($exam['due_date'])); ?>.
                        </p>

                    <?php elseif ($is_locked): ?>
                        <button class="btn btn-secondary btn-begin rounded-pill btn-locked shadow" disabled>
                            <i class="fa fa-lock me-2"></i> Locked
                        </button>
                        <p class="text-danger fw-bold mt-3">
                            Exam opens at <?= $formatted_time; ?>. Please wait for the scheduled time.
                        </p>

                    <?php else: ?>
                        <form action="exam_paper.php" method="GET">
                            <input type="hidden" name="id" value="<?= $exam['id']; ?>">
                            <button type="submit" class="btn btn-primary btn-begin rounded-pill shadow">
                                Begin Exam <i class="fa fa-paper-plane ms-2"></i>
                            </button>
                        </form>
                    <?php endif; ?>

                    <p class="text-muted small mt-4">
                        <i class="fa fa-info-circle me-1"></i> By clicking begin, you agree to comply with all rules.
                    </p>
                </div>
            </div>
        </div>
    </div>

    </div>
    </div>

    <footer class="footer-container mt-5">
        <?php include '../footer.php'; ?>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Refresh page every 30 seconds to automatically unlock the button when time is reached
        <?php if ($is_locked): ?>
            setTimeout(function() {
                location.reload();
            }, 30000);
        <?php endif; ?>
    </script>
</body>

</html>