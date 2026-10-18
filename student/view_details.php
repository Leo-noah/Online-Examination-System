<?php
session_start();
require '../db.php';


// 1. Security & Access Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$submission_id = $_GET['id'] ?? null;
if (!$submission_id) {
    header("Location: my_results.php");
    exit();
}

try {
    // Fetch submission and exam details
    $stmt = $pdo->prepare("
        SELECT s.*, e.show_details, e.title, e.course 
        FROM student_submissions s
        JOIN exams e ON s.exam_id = e.id
        WHERE s.id = ? AND s.student_id = ?
    ");
    $stmt->execute([$submission_id, $_SESSION['user_id']]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$submission) {
        die("Record not found.");
    }

    if ((int)$submission['show_details'] !== 1) {
        header("Location: my_results.php?error=access_denied");
        exit();
    }

    // Fetch questions
    $q_stmt = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY q_number ASC");
    $q_stmt->execute([$submission['exam_id']]);
    $questions = $q_stmt->fetchAll(PDO::FETCH_ASSOC);

    $student_answers_raw = json_decode($submission['answers_json'], true) ?: [];
    $teacher_highlights = json_decode($submission['teacher_highlights_json'], true) ?: [];
    $teacher_recommendations = json_decode($submission['teacher_recommendations_json'] ?? '[]', true) ?: [];

    // --- CRITICAL FIX: ACCURATE PERCENTAGE CALCULATION ---
    $stmt_max = $pdo->prepare("SELECT SUM((end_q - start_q + 1) * marks_per_q) as max_m FROM question_ranges WHERE exam_id = ?");
    $stmt_max->execute([$submission['exam_id']]);
    $max_data = $stmt_max->fetch();

    // If ranges aren't found, fallback to 100 to avoid division by zero
    $total_possible = ($max_data['max_m'] > 0) ? (float)$max_data['max_m'] : 100.0;
    $percentage = ($submission['total_score'] / $total_possible) * 100;
    // ---------------------------------------------------

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Submission Review | Online Examination System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* --- ADDED THIS FOR SMOOTH TRANSITION --- */
        html {
            scroll-behavior: smooth;
        }

        /* --- Layout Spacing --- */
        .main-content-wrapper {
            padding-top: 50px;
        }

        .review-card {
            border-radius: 15px;
            border: 1px solid #e0e0e0;
            margin-bottom: 20px;
            padding: 20px;
            background-color: #fff;
            margin-left: -110px;

            /* --- FIXED HERE: This prevents the header from covering the question --- */
            scroll-margin-top: 110px;
        }

        /* --- Final Score Card Positioning --- */
        .score-card {
            margin-top: 85px;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08) !important;
            overflow: hidden;
        }

        .score-card .card-body {
            padding: 10px 10px !important;
        }

        .score-number {
            font-size: 3.5rem !important;
            font-weight: 800;
        }

        /* --- Sidebar & Progress Grid --- */
        .sticky-sidebar {
            position: sticky;
            top: 110px;
            height: fit-content;
            transition: top 0.3s ease;
        }

        .progress-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }

        .q-nav-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            aspect-ratio: 1 / 1;
            border-radius: 8px;
            background: #fff;
            border: 1px solid #dee2e6;
            color: #333;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: bold;
            transition: all 0.2s ease;
        }

        .btn-green {
            background-color: #03884a !important;
            color: white !important;
            border-color: #03884a !important;
        }

        .btn-red {
            background-color: #d10418 !important;
            color: white !important;
            border-color: #d10418 !important;
        }

        .btn-yellow {
            background-color: #ffc107 !important;
            color: #212529 !important;
            border-color: #ffc107 !important;
        }

        /* Legend: Stretch downward */
        .legend {
            border-top: 1px solid #eee;
            padding-top: 20px;
            padding-bottom: 50px;
            min-height: 180px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            margin-bottom: 12px;
        }

        .dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            margin-right: 10px;
        }

        .dot-green {
            background: #03e468;
        }

        .dot-red {
            background: #d10418;
        }

        .dot-yellow {
            background: #ffc107;
        }

        .dot-empty {
            background: #fff;
            border: 1px solid #ccc;
        }

        /* --- Answer Styling --- */
        .option-item {
            padding: 15px;
            margin-top: 10px;
            border-radius: 10px;
            border: 1px solid #f0f0f0;
        }

        .opt-correct {
            background-color: #d1e7dd !important;
            color: #03884a !important;
            border-color: #badbcc !important;
            font-weight: 600;
        }

        .opt-wrong {
            background-color: #f8d7da !important;
            color: #d10418 !important;
            border-color: #f5c2c7 !important;
        }

        .highlight-err {
            background-color: #f8d7da !important;
            color: #dc3545 !important;
            padding: 2px 4px;
            border-radius: 4px;
            font-weight: bold;
            text-decoration: underline;
            display: inline;
            margin: 50px;
            padding: 2px 0;
        }

        .answer-content {
            text-indent: 0 !important;
            white-space: pre-line;
            line-height: 1.6;
            display: block;
            padding-top: 5px;
        }

        .score-circle-container {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .score-circle {
            width: 250px;
            height: 130px;
            border-radius: 50%;
            background: conic-gradient(#0d6efd var(--percentage), #e9ecef 0deg);
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            transition: transform 0.3s ease;
        }

        .score-circle::before {
            content: "";
            position: absolute;
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 50%;
        }

        .score-content {
            position: relative;
            z-index: 1;
        }

        .score-number {
            font-size: 2rem !important;
            font-weight: 800;
            color: #0d6efd;
            line-height: 1;
        }

        .percentage-text {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: bold;
        }

        .score-card:hover .score-circle {
            transform: scale(1.05);
        }

        .recommendation-box {
            margin-top: 15px;
            border-radius: 4px;
        }

        .rec-text {
            font-size: 0.95rem;
            color: #444;
            background-color: #f8f9fa !important;
            /* Light grey background */
            line-height: 1.5;
        }

        /* Ensure no extra padding at top of answer-content */
        .answer-content {
            display: block;
            margin-top: 0;
            padding-top: 0;
        }
    </style>
</head>

<body>
    <?php include 'student_header.php'; ?>

    <div class="container main-content-wrapper">
        <div class="row">
            <div class="col-lg-8">
                <div class="mb-4 text-start" style="margin-left: -110px;">
                    <h2 class="fw-bold mb-1">Submission Review</h2>
                    <p class="text-muted">Review your answers and teacher corrections below.</p>
                </div>

                <?php
                $nav_colors = [];
                foreach ($questions as $index => $q):
                    $num = $q['q_number'];
                    $has_highlight = !empty($teacher_highlights[$num]);
                    $raw_ans = $student_answers_raw[$num] ?? '';
                    $q_type = strtoupper(trim($q['q_type'] ?? ''));
                    $is_objective = in_array($q_type, ['MCQ', 'TF', 'TRUE_FALSE', 'TRUE/FALSE']);
                    $user_ans = $has_highlight ? $teacher_highlights[$num] : $raw_ans;

                    if ($raw_ans === '' && !$has_highlight) {
                        $state_class = '';
                    } elseif ($is_objective) {
                        $correct = trim($q['correct_answer']);
                        $is_correct = (strcasecmp(trim(strip_tags($user_ans)), $correct) === 0);
                        $state_class = $is_correct ? 'btn-green' : 'btn-red';
                    } else {
                        $state_class = $has_highlight ? 'btn-yellow' : 'btn-green';
                    }
                    $nav_colors[$index] = $state_class;
                ?>
                    <div class="card review-card shadow-sm" id="q-block-<?= $index + 1 ?>">
                        <div class="card-body">
                            <span class="badge bg-secondary mb-3">Question <?= $num ?></span>
                            <h5 class="fw-bold mb-4"><?= htmlspecialchars($q['question_text']) ?></h5>

                            <?php if ($is_objective): ?>
                                <?php
                                $correct = trim($q['correct_answer']);
                                $stripped_user_ans = trim(strip_tags($user_ans));
                                $is_correct = (strcasecmp($stripped_user_ans, $correct) === 0);
                                ?>
                                <div class="option-item opt-correct mb-2">
                                    <strong>Correct Answer:</strong> <?= htmlspecialchars($correct) ?>
                                </div>
                                <div class="option-item <?= $is_correct ? 'opt-correct' : 'opt-wrong' ?>">
                                    <strong>Your Answer:</strong> <?= htmlspecialchars($stripped_user_ans) ?: 'No answer' ?>
                                    <?php if ($stripped_user_ans !== ''): ?>
                                        <i class="fas <?= $is_correct ? 'fa-check-circle' : 'fa-times-circle' ?> ms-2"></i>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="written-answer-box <?= $has_highlight ? 'box-highlight' : '' ?>">
                                    <label class="d-block small text-muted fw-bold mb-2 text-uppercase">
                                        Your Answer <?= $has_highlight ? '<span class="text-warning ms-2">(Teacher Corrected)</span>' : '' ?>:
                                    </label>
                                    <div class="answer-content"><?php
                                                                if ($has_highlight) {
                                                                    echo trim($teacher_highlights[$num]);
                                                                } elseif ($raw_ans !== '') {
                                                                    echo nl2br(htmlspecialchars(trim($raw_ans)));
                                                                } else {
                                                                    echo '<i>No answer submitted</i>';
                                                                }
                                                                ?></div>
                                </div>

                                <?php
                                $recommendation = $teacher_recommendations[$num] ?? '';
                                if (!empty(trim($recommendation))):
                                ?>
                                    <div class="recommendation-box mt-3">
                                        <label class="d-block small text-primary fw-bold mb-1 text-uppercase">
                                            <i class="fas fa-comment-dots me-1"></i> Teacher's Recommendation:
                                        </label>
                                        <div class="rec-text p-3 border-start border-primary border-4 bg-light rounded-end">
                                            <?= nl2br(htmlspecialchars(trim($recommendation))) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="col-lg-4">
                <div class="sticky-sidebar">
                    <div class="card score-card shadow-sm mb-4">
                        <div class="card-body text-center">
                            <span class="d-block small text-muted fw-bold mb-3">EXAM PERFORMANCE</span>

                            <div class="score-circle-container mb-3">
                                <div class="score-circle" style="--percentage: <?= number_format($percentage, 1); ?>%">
                                    <div class="score-content">
                                        <h2 class="score-number mb-0"><?= number_format($submission['total_score'], 1); ?></h2>
                                        <span class="percentage-text"><?= number_format($percentage, 1); ?>%</span>
                                    </div>
                                </div>
                            </div>

                            <div class="text-muted small fw-bold">FINAL SCORE</div>
                        </div>
                    </div>

                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3">Exam Progress</h6>
                            <div class="progress-grid">
                                <?php foreach ($questions as $index => $q): ?>
                                    <a href="#q-block-<?= $index + 1 ?>" class="q-nav-btn <?= $nav_colors[$index] ?>">
                                        <?= $index + 1 ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
    </div>

    <footer class="mt-5"><?php include '../footer.php'; ?></footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>