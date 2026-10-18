<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require '../db.php';
require_once __DIR__ . '/../assets/includes/header.php';

// 1. SECURITY & ROLE CHECK
$role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// 2. THE GRADING FUNCTION
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

$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

if ($exam_id <= 0) {
    die("Invalid Exam ID.");
}

try {
    // 3. FETCH EXAM DETAILS & CALCULATE MAX POSSIBLE MARKS
    $stmt_exam = $pdo->prepare("
        SELECT e.title, 
        (SELECT SUM(r.marks_per_q * (r.end_q - r.start_q + 1)) 
         FROM question_ranges r WHERE r.exam_id = e.id) as max_points
        FROM exams e WHERE e.id = ?
    ");
    $stmt_exam->execute([$exam_id]);
    $exam_info = $stmt_exam->fetch(PDO::FETCH_ASSOC);

    if (!$exam_info) {
        die("Exam not found.");
    }

    $max_points = ($exam_info['max_points'] > 0) ? (float)$exam_info['max_points'] : 100;

    // 4. FETCH ALL SUBMISSIONS WITH FORMATTED STU_ID
    $stmt_subs = $pdo->prepare("
    SELECT 
        ss.id, 
        ss.student_id as numeric_id, 
        u.stu_id as formatted_stu_id, 
        ss.student_name, 
        ss.total_score, 
        ss.status 
    FROM student_submissions ss
    LEFT JOIN users u ON ss.student_id = u.id
    WHERE ss.exam_id = ? 
    ORDER BY ss.total_score DESC
");
    $stmt_subs->execute([$exam_id]);
    $submissions = $stmt_subs->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Exam Results | Online Examination System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .card {
            overflow: hidden;
        }

        .bg-success-subtle {
            background-color: #d1e7dd;
        }

        .bg-warning-subtle {
            background-color: #fff3cd;
        }

        @media print {

            .btn-group,
            .btn,
            .breadcrumb,
            footer,
            .sidebar {
                display: none !important;
            }

            .container {
                max-width: 100% !important;
                width: 100% !important;
                margin: 0 !important;
            }

            .card {
                border: 1px solid #ddd !important;
            }
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="mt-4 mb-5" style="margin-left: 20px; margin-right: 100px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark">Exam Performance Report</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="results.php">Manage Results</a></li>
                        <li class="breadcrumb-item active"><?= htmlspecialchars($exam_info['title']) ?></li>
                    </ol>
                </nav>
            </div>

            <div class="d-flex gap-3 align-items-center">
                <div class="input-group shadow-sm" style="width: 350px;"> <span class="input-group-text bg-white border-end-0">
                        <i class="fa fa-search text-muted"></i>
                    </span>
                    <input type="text" id="studentSearch" class="form-control border-start-0 ps-0"
                        placeholder="Search Name or Student ID (STU-...)">
                </div>

                <div class="btn-group shadow-sm">
                    <a href="results.php?exam_id=<?= $exam_id ?>" class="btn btn-primary">
                        <i class="fa fa-edit me-2"></i> Open Student Submissions
                    </a>

                </div>

                <a href="results.php" class="btn btn-outline-secondary px-4 shadow-sm">
                    <i class="fa-solid fa-arrow-left me-2"></i>Back
                </a>
            </div>
        </div>

        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white py-3 border-bottom">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="mb-0 fw-bold">Student Rankings</h5>
                    </div>
                    <div class="col text-end">
                        <span class="badge bg-light text-dark border">Max Points: <?= $max_points ?></span>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-uppercase small fw-bold">
                            <tr>
                                <th class="ps-4">No</th>
                                <th>Student ID</th>
                                <th>Student</th>
                                <th class="text-center">Score</th>
                                <th class="text-center">Percentage</th>
                                <th class="text-center">Grade</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $rank = 1;
                            foreach ($submissions as $sub):
                                $score = (float)$sub['total_score'];
                                $percentage = ($score / $max_points) * 100;
                                $grade_data = getGrade($percentage);
                                $is_graded = (strtolower(trim($sub['status'])) === 'graded');

                                // Use the joined formatted ID or fallback to the numeric one if NULL
                                $display_id = !empty($sub['formatted_stu_id']) ? $sub['formatted_stu_id'] : "STU-" . str_pad($sub['numeric_id'], 4, '0', STR_PAD_LEFT);
                            ?>
                                <tr>
                                    <td class="ps-4 text-muted"><?= $rank++ ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border fw-medium">
                                            <?= htmlspecialchars($display_id) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($sub['student_name']) ?></div>
                                    </td>
                                    <td class="text-center fw-bold text-primary"><?= number_format($score, 1) ?></td>
                                    <td class="text-center">
                                        <div class="fw-bold"><?= number_format($percentage, 1) ?>%</div>
                                    </td>
                                    <td class="text-center"><span class="fs-5 fw-bold <?= $grade_data['color'] ?>"><?= $grade_data['grade'] ?></span></td>
                                    <td class="text-center">
                                        <?php if ($is_graded): ?>
                                            <span class="badge rounded-pill bg-success px-3">Graded</span>
                                        <?php else: ?>
                                            <span class="badge rounded-pill bg-warning text-dark px-3"><?= htmlspecialchars($sub['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="results.php?grade_id=<?= $sub['id'] ?>" class="btn btn-sm btn-primary rounded-pill px-4">Check Detail</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <footer class="footer-container mt-5">
        <?php include '../footer.php'; ?>
    </footer>

</body>
<script>
    document.getElementById('studentSearch').addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const tableRows = document.querySelectorAll('tbody tr');

        tableRows.forEach(row => {
            // Skip the "No submissions found" row if it exists
            if (row.cells.length < 3) return;

            // Column 2: Student ID (STU-****)
            const studentId = row.cells[1].textContent.toLowerCase();
            // Column 3: Student Name
            const studentName = row.cells[2].textContent.toLowerCase();

            // If either the ID or the Name matches the search term, show the row
            if (studentName.includes(searchTerm) || studentId.includes(searchTerm)) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        });
    });
</script>

</html>