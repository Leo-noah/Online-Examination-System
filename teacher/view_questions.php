<?php
require '../db.php'; // Connect to database
require_once __DIR__ . '/../assets/includes/header.php';
session_start();

$exam_id = $_GET['exam_id'] ?? null;

if (!$exam_id) {
    header("Location: dashboard.php");
    exit;
}

// 1. Fetch Exam Details from Database
try {
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $exam = null;
}

if (!$exam) {
    header("Location: dashboard.php");
    exit;
}

// 2. Fetch Questions for this specific Exam
try {
    // Ordering by your q_number column
    $q_stmt = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY q_number ASC");
    $q_stmt->execute([$exam_id]);
    $questions = $q_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $questions = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Question Review - <?= htmlspecialchars($exam['title'] ?? 'Exam') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
        }

        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: -500px;
            padding: 10px;
            transition: 0.3s;
        }

        .question-view-area {
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
        }

        .option-box {
            background: #fdfdfd;
            border: 1px solid #dee2e6;
            padding: 12px;
            border-radius: 8px;
            height: 100%;
        }

        .answer-banner {
            background-color: #e6f4ea;
            border-left: 5px solid #28a745;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .card-custom {
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            margin-bottom: 25px;
            background: white;
        }

        .footer-container {
            margin-top: auto;
            padding-left: 250px;
            width: 100%;
            background: #fff;
        }

        @media (max-width: 768px) {
            .footer-container {
                padding-left: 0;
            }
        }
    </style>
</head>

<body>

    <?php include 'header.php'; ?>

    <main class="main-content">
        <div class="content-body">
            <div class="question-view-area">

                <div class="card card-custom p-4 text-center">
                    <h2 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($exam['title'] ?? 'N/A') ?></h2>
                    <p class="text-muted mb-0">
                        Course: <strong><?= htmlspecialchars($exam['course'] ?? 'N/A') ?></strong> |
                        Time: <?= htmlspecialchars($exam['time'] ?? $exam['time_limit'] ?? 'N/A') ?> |
                        <span class="badge bg-primary rounded-pill px-3">Total: <?= count($questions) ?> Qs</span>
                    </p>
                    <div class="mt-3">
                        <a href="teacher_dashboard.php" class="btn btn-outline-secondary btn-sm px-4 rounded-pill">
                            <i class="fa fa-arrow-left me-1"></i> Back to Dashboard
                        </a>
                    </div>
                </div>

                <div class="card card-custom overflow-hidden">
                    <div class="card-header bg-white py-3 border-bottom text-center">
                        <h5 class="mb-0 fw-bold text-primary">Exam Content Review</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($questions)): ?>
                            <div class="text-center py-5">
                                <p class="text-muted">No questions found for this exam.</p>
                                <a href="manage_exams.php" class="btn btn-primary btn-sm">Add Questions Now</a>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php
                                foreach ($questions as $q):
                                    // Decode JSON options
                                    $opts = json_decode($q['options'], true);
                                    $type = strtolower($q['q_type'] ?? 'multiple');
                                ?>
                                    <div class="list-group-item p-4">
                                        <?php if (!empty($q['question_image'])): ?>
                                            <div class="mb-4 text-center">
                                                <img src="../assets/uploads/questions/<?= htmlspecialchars($q['question_image']) ?>"
                                                    alt="Question Image"
                                                    class="img-fluid rounded border shadow-sm"
                                                    style="max-height: 350px; width: auto; display: inline-block;">
                                            </div>
                                        <?php endif; ?>

                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h6 class="fw-bold text-dark mb-0 lh-base" style="max-width: 85%;">
                                                Q<?= htmlspecialchars($q['q_number'] ?? '0') ?>. <?= htmlspecialchars($q['question_text'] ?? 'No Question Text') ?>
                                            </h6>
                                            <span class="badge bg-light text-secondary border text-capitalize">
                                                <?= htmlspecialchars($type) ?>
                                            </span>
                                        </div>
                                        <?php if ($type === 'multiple' || $type === 'mcq'): ?>
                                            <div class="row g-3 mb-3">
                                                <div class="col-md-6">
                                                    <div class="option-box">
                                                        <strong class="text-primary">A:</strong> <?= htmlspecialchars($opts['A'] ?? $opts['a'] ?? 'N/A') ?>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="option-box">
                                                        <strong class="text-primary">B:</strong> <?= htmlspecialchars($opts['B'] ?? $opts['b'] ?? 'N/A') ?>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="option-box">
                                                        <strong class="text-primary">C:</strong> <?= htmlspecialchars($opts['C'] ?? $opts['c'] ?? 'N/A') ?>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="option-box">
                                                        <strong class="text-primary">D:</strong> <?= htmlspecialchars($opts['D'] ?? $opts['d'] ?? 'N/A') ?>
                                                    </div>
                                                </div>
                                            </div>

                                        <?php elseif ($type === 'true/false' || $type === 'tf'): ?>
                                            <div class="row g-3 mb-3">
                                                <div class="col-md-6">
                                                    <div class="option-box text-center">True</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="option-box text-center">False</div>
                                                </div>
                                            </div>

                                        <?php else: ?>
                                            <div class="mb-3">
                                                <div class="option-box bg-light border-0">
                                                    <small class="text-muted"><i class="fa fa-info-circle me-1"></i> This is a descriptive answer question.</small>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <div class="answer-banner">
                                            <span class="text-success small fw-bold text-uppercase">Correct Answer:</span>
                                            <span class="ms-2 fw-bold text-dark">
                                                <?= htmlspecialchars($q['correct_answer'] ?? 'N/A') ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </main>
    </div>
    <footer class="footer-container">
        <?php include '../footer.php'; ?>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>