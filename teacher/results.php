<?php
require '../db.php';

session_start();

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['teacher', 'tr'])) {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

$teacher_name = $_SESSION['username'];
$exam_id = (int)($_GET['exam_id'] ?? 0);
$submission_id = (int)($_GET['grade_id'] ?? 0);

// --- ACTION: SAVE MARKS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_grading'])) {
    $sub_id = (int)$_POST['submission_id'];
    $ex_id = (int)$_POST['exam_id'];

    // Simplified check: Just ensure the submission exists
    $check = $pdo->prepare("SELECT id FROM student_submissions WHERE id = ?");
    $check->execute([$sub_id]);

    if ($check->fetch()) {
        $submitted_marks = $_POST['marks'] ?? [];
        $highlighted_answers = $_POST['annotations'] ?? [];
        $teacher_recs = $_POST['recommendations'] ?? [];
        $total = array_sum(array_map('floatval', $submitted_marks));

        $stmt = $pdo->prepare("UPDATE student_submissions 
                               SET teacher_highlights_json = ?, 
                                   marks_json = ?, 
                                   teacher_recommendations_json = ?, 
                                   total_score = ?, 
                                   status = 'Graded' 
                               WHERE id = ?");

        $stmt->execute([
            json_encode($highlighted_answers),
            json_encode($submitted_marks),
            json_encode($teacher_recs),
            $total,
            $sub_id
        ]);

        header("Location: results.php?exam_id=$ex_id&success=1");
        exit();
    } else {
        die("Error: Submission not found.");
    }
}

// --- DATA FETCHING ---
$grading_data = null;
$questions = [];

if ($submission_id > 0) {
    // MODE 1: GRADING A SPECIFIC STUDENT
    $stmt = $pdo->prepare("SELECT s.*, e.title FROM student_submissions s JOIN exams e ON s.exam_id = e.id WHERE s.id = ?");
    $stmt->execute([$submission_id]);
    $grading_data = $stmt->fetch();

    if ($grading_data) {
        $stmt = $pdo->prepare("
            SELECT q.*, r.marks_per_q 
            FROM questions q
            LEFT JOIN question_ranges r ON q.exam_id = r.exam_id 
                AND q.q_number BETWEEN r.start_q AND r.end_q
            WHERE q.exam_id = ? 
            ORDER BY q.q_number ASC
        ");
        $stmt->execute([$grading_data['exam_id']]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} elseif ($exam_id > 0) {
    // MODE 2: LISTING ALL STUDENTS FOR ONE EXAM
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
    $stmt->execute([$exam_id]);
    $exam_info = $stmt->fetch();

    if ($exam_info) {
        // USE LEFT JOIN: If student_id doesn't exist in 'users' table, 
        // the submission will STILL show up (with stu_id as NULL)
        $stmt = $pdo->prepare("
            SELECT s.*, u.stu_id 
            FROM student_submissions s 
            LEFT JOIN users u ON s.student_id = u.id 
            WHERE s.exam_id = ? 
            ORDER BY s.submitted_at DESC
        ");
        $stmt->execute([$exam_id]);
        $submissions = $stmt->fetchAll();
    }
} else {
    // --- MODE 3: LISTING ONLY EXAMS CREATED BY THIS TEACHER ---
    $stmt = $pdo->prepare("
    SELECT e.id, e.title, e.show_details, 
    (SELECT COUNT(*) FROM student_submissions s WHERE s.exam_id = e.id) as students_count 
    FROM exams e 
    WHERE e.teacher_name = ? 
    ORDER BY e.id DESC
");
    $stmt->execute([$teacher_name]); // Uses the session variable defined at the top
    $exam_summaries = $stmt->fetchAll();
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
        :root {
            --uit-blue: #007bff;
            --light-bg: #f8f9fa;
        }

        body {
            background: var(--light-bg);
            font-family: 'Segoe UI', sans-serif;
        }

        /* --- CRITICAL: ANTI-BLUR & SHARPENING --- */

        /* 1. Force remove all filters from the background when modal is open */
        body.modal-open .main-content,
        body.modal-open .wrapper,
        body.modal-open #layout-wrapper,
        body.modal-open>*:not(.modal) {
            filter: none !important;
            -webkit-filter: none !important;
            backdrop-filter: none !important;
            transition: none !important;
            /* Prevents animation-induced blurring */
        }

        /* 2. Fix Modal Stacking & Sharpness */
        .modal {
            background: rgba(0, 0, 0, 0.7) !important;
            z-index: 99999 !important;
            pointer-events: none;
            display: none;
            /* Ensure it starts hidden */
        }

        .modal-backdrop {
            width: 0 !important;
            height: 0 !important;
            display: none !important;
        }

        .modal.show {
            display: block;
        }

        .modal-dialog {
            pointer-events: auto !important;
            z-index: 100000 !important;
            transform: none !important;
        }

        .modal-content {
            position: relative;
            z-index: 100001 !important;
            pointer-events: auto !important;
        }

        /* Force the Apply button to be on the very top layer */
        .btn-primary[onclick="applyChanges()"] {
            position: relative !important;
            z-index: 100005 !important;
            pointer-events: auto !important;
            cursor: pointer !important;
        }

        /* 3. Editor Styling for High Clarity */
        #m-body-editable {
            min-height: 400px;
            padding: 20px;
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            outline: none;
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
            font-size: 16px;
            line-height: 1.6;
            color: #212529;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        /* 4. Highlight Styling */
        .highlight-err {
            background-color: #ffe3e3 !important;
            border-bottom: 2px solid #dc3545 !important;
            color: #b02a37 !important;
            font-weight: 600;
            display: inline !important;
        }

        /* Prevent Bootstrap from adding padding to body */
        body.modal-open {
            overflow: hidden !important;
            padding-right: 0 !important;
        }

        .auto-correct-card {
            border-left: 5px solid #28a745 !important;
        }

        .auto-wrong-card {
            border-left: 5px solid #dc3545 !important;
        }

        .manual-card {
            border-left: 5px solid #ffc107 !important;
        }

        .mark-input {
            width: 85px;
            text-align: center;
        }

        /* Update your existing styling or add this to the <style> section */
        .ans-box {
            max-height: 200px;
            /* Adjust this height as you prefer */
            overflow-y: auto;
            /* Show vertical scrollbar only when needed */
            padding: 12px;
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            word-wrap: break-word;
            /* Ensure long words don't break the layout */
        }

        /* Optional: Make the scrollbar look cleaner */
        .ans-box::-webkit-scrollbar {
            width: 8px;
        }

        .ans-box::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 4px;
        }

        /* Add space between cards */
        .card.auto-correct-card,
        .card.auto-wrong-card,
        .card.manual-card {
            margin-bottom: 2rem !important;
        }

        /* Constrain long answers with a scrollbar */
        .ans-box {
            max-height: 250px;
            overflow-y: auto;
            padding: 10px;
            background: #fff;
            border: 1px solid #eee;
            border-radius: 5px;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="main-content">
        <?php if ($submission_id > 0): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold mb-0">Grading: <?= htmlspecialchars($grading_data['student_name']) ?></h2>
                <a href="?exam_id=<?= $grading_data['exam_id'] ?>" class="btn btn-sm btn-outline-secondary">Back</a>
            </div>

            <form method="POST">
                <input type="hidden" name="submission_id" id="current_submission_id" value="<?= $submission_id ?>">
                <input type="hidden" name="exam_id" value="<?= $grading_data['exam_id'] ?>">

                <?php
                // 1. Decode all existing data columns
                $stu_ans = json_decode($grading_data['answers_json'], true) ?: [];
                $saved_marks = json_decode($grading_data['marks_json'], true) ?: [];
                $saved_recs = json_decode($grading_data['teacher_recommendations_json'] ?? '[]', true) ?: [];
                $saved_highlights = json_decode($grading_data['teacher_highlights_json'] ?? '[]', true) ?: [];

                foreach ($questions as $q):
                    // 1. Get both the sequence number and the actual DB Question ID
                    $num = (string)$q['q_number'];
                    $db_id = (string)$q['id'];

                    // 2. CHECK BOTH CASES: Search JSON for sequence number OR Database ID
                    if (isset($stu_ans[$num])) {
                        $raw_student_answer = $stu_ans[$num];
                        $key_to_use = $num; // Used for highlights/marks/recs
                    } elseif (isset($stu_ans[$db_id])) {
                        $raw_student_answer = $stu_ans[$db_id];
                        $key_to_use = $db_id; // Used for highlights/marks/recs
                    } else {
                        $raw_student_answer = '';
                        $key_to_use = $num; // Fallback
                    }

                    // 3. Update variables to use the correct key found above
                    $ans = !empty($saved_highlights[$key_to_use]) ? $saved_highlights[$key_to_use] : $raw_student_answer;
                    $current_rec = $saved_recs[$key_to_use] ?? '';

                    $correct = trim($q['correct_answer']);
                    $type = strtoupper(trim($q['q_type'] ?? ''));
                    $max_mark = !empty($q['marks_per_q']) ? (float)$q['marks_per_q'] : 1.0;

                    $is_objective = in_array($type, ['MCQ', 'TRUE_FALSE', 'TF', 'TRUE/FALSE']);

                    if ($is_objective) {
                        $is_correct = (strcasecmp(trim(strip_tags($ans)), $correct) === 0);
                        $final_mark = $is_correct ? $max_mark : 0.0;
                        $card_class = $is_correct ? "auto-correct-card" : "auto-wrong-card";
                    } else {
                        // Use the specific key (num or db_id) to retrieve saved marks
                        $final_mark = isset($saved_marks[$key_to_use]) ? $saved_marks[$key_to_use] : 0.0;
                        $card_class = "manual-card";
                    }
                ?>
                    <input type="hidden" name="recommendations[<?= $num ?>]" id="input-rec-<?= $num ?>" value="<?= htmlspecialchars($current_rec) ?>">
                    <input type="hidden" name="annotations[<?= $num ?>]" id="input-ans-<?= $num ?>" value="<?= htmlspecialchars($ans) ?>">

                    <div class="card <?= $card_class ?> mb-4">
                        <div class="card-body">
                            <h6 class="fw-bold text-primary">Q<?= $num ?>: <span class="text-dark fw-normal"><?= htmlspecialchars($q['question_text']) ?></span></h6>
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <div class="d-flex justify-content-between">
                                        <label class="small fw-bold text-secondary">STUDENT ANSWER</label>
                                        <?php if (!$is_objective): ?>
                                            <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none" onclick="openReliableModal('q-ans-<?= $num ?>', 'Q<?= $num ?> Detailed View')">
                                                <i class="fas fa-expand-arrows-alt me-1"></i>Full View & Edit
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <div class="ans-box" id="q-ans-<?= $num ?>">
                                        <?= ($ans !== '') ? $ans : '<em class="text-muted">No answer submitted</em>' ?>
                                    </div>
                                </div>

                                <div class="col-md-5">
                                    <label class="small fw-bold text-success">CORRECT KEY</label>
                                    <div class="ans-box bg-light text-success fw-bold">
                                        <?= ($is_objective) ? htmlspecialchars($correct) : 'Manual Grading Required' ?>
                                    </div>
                                </div>

                                <div class="col-md-2 text-end">
                                    <label class="small fw-bold">MARK</label>
                                    <input type="number" name="marks[<?= $num ?>]" class="form-control mark-input ms-auto" value="<?= $final_mark ?>" step="0.1" min="0" max="<?= $max_mark ?>">
                                    <small class="text-muted">Out of <?= $max_mark ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <button type="submit" name="save_grading" class="btn btn-primary btn-lg w-100 py-3 mb-5 shadow">Save Final Grading</button>
            </form>

        <?php elseif ($exam_id > 0): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Submissions: <?= htmlspecialchars($exam_info['title']) ?></h2>

                <div class="d-flex gap-3 align-items-center">
                    <div class="input-group shadow-sm" style="width: 350px;">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" id="studentSearch" class="form-control border-start-0 ps-0"
                            placeholder="Search Name or Student ID (STU-...)" onkeyup="filterTable()">
                    </div>

                    <a href="view_marks.php?exam_id=<?= $exam_id ?>" class="btn btn-primary px-4 shadow-sm">
                        <i class="fas fa-file-invoice me-2"></i>View Marks
                    </a>

                    <a href="results.php" class="btn btn-outline-secondary px-4 shadow-sm">
                        <i class="fa-solid fa-arrow-left me-2"></i>Back
                    </a>
                </div>
            </div>

            <?php if (empty($submissions)): ?>
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center py-5">
                        <i class="fa-solid fa-user-slash fa-3x text-muted mb-3"></i>
                        <h4 class="text-secondary">No students have taken this exam yet.</h4>
                        <p class="text-muted">Results will appear here once students submit their answers.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="submissionsTable">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Student ID</th>
                                    <th>Student Name</th>
                                    <th>Score</th>
                                    <th>Submission Date</th>
                                    <th>Submission Time</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($submissions as $sub):
                                    $dateObj = new DateTime($sub['submitted_at']);

                                    // Determine badge color based on status
                                    $currentStatus = strtolower($sub['status']);
                                    switch ($currentStatus) {
                                        case 'graded':
                                            $statusClass = 'success';
                                            break;
                                        case 'disqualified':
                                            $statusClass = 'danger'; // Red for security issues
                                            break;
                                        case 'reexam':
                                            $statusClass = 'info text-dark'; // Light blue for re-exams
                                            break;
                                        case 'pending':
                                        default:
                                            $statusClass = 'warning'; // Yellow for others
                                            break;
                                    }
                                ?>
                                    <tr class="student-row">
                                        <td class="ps-4">
                                            <span class="badge bg-light text-dark border fw-normal">
                                                <?= htmlspecialchars($sub['stu_id'] ?? 'N/A') ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold"><?= htmlspecialchars($sub['student_name']) ?></td>
                                        <td>
                                            <span class="badge bg-light text-primary border">
                                                <?= number_format((float)$sub['total_score'], 2) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-dark small">
                                                <i class="far fa-calendar-alt me-1 text-muted"></i>
                                                <?= $dateObj->format('M d, Y') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-dark small">
                                                <i class="far fa-clock me-1 text-muted"></i>
                                                <?= $dateObj->format('h:i A') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $statusClass ?>">
                                                <?= htmlspecialchars($sub['status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="?grade_id=<?= $sub['id'] ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-edit me-1"></i> Check Ans
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold m-0" style="font-size: 1.75rem; color: #1a1a1a;">Manage Results</h2>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 py-3" style="width: 10%;">No</th>
                                    <th class="py-3" style="width: 20%;">Exam Title</th>
                                    <th class="py-3" style="width: 20%;">Students Participated</th>
                                    <th class="text-center pe-4 py-3 " style="width: 40%;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $display_id = 1;
                                foreach ($exam_summaries as $row):
                                    $showDetails = $row['show_details'] ?? 0;
                                ?>
                                    <tr>
                                        <td class="ps-4 text-secondary">#<?= $display_id++ ?></td>
                                        <td class="fw-bold"><?= htmlspecialchars($row['title']) ?></td>
                                        <td>
                                            <span class="text-dark"><?= $row['students_count'] ?> Students</span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="toggle_access.php?exam_id=<?= $row['id'] ?>&status=<?= $showDetails ?>"
                                                class="btn btn-sm px-3 me-1 <?= $showDetails ? 'text-white' : 'btn-outline-secondary' ?>"
                                                style="background-color: <?= $showDetails ? '#007bff' : 'transparent' ?>; 
                                                        border: 1px solid <?= $showDetails ? '#007bff' : '#6c757d' ?>; 
                                                        min-width: 150px;">
                                                <i class="fa-solid <?= $showDetails ? 'fa-eye' : 'fa-eye-slash' ?> me-1"></i>
                                                <?= $showDetails ? 'Access Enabled' : 'Access Disabled' ?>
                                            </a>

                                            <a href="?exam_id=<?= $row['id'] ?>" class="btn btn-primary btn-sm px-3 me-1" style="background-color: #007bff; border: none;">
                                                View Results & Submissions
                                            </a>

                                            <a href="view_marks.php?exam_id=<?= $row['id'] ?>" class="btn btn-primary btn-sm px-3 me-1" style="background-color: #007bff; border: none;">
                                                View Marks
                                            </a>

                                            <button class="btn btn-danger btn-sm shadow-sm">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="reliableModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="m-title">Review Submission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="forceCleanup()"></button>
                </div>
                <div class="modal-body bg-light">
                    <div class="mb-2 d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-warning" onclick="doHighlight()">Highlight Error</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearHighlights()">Clear Highlight</button>
                    </div>

                    <div id="m-body-editable" class="p-3 bg-white border rounded mb-3" contenteditable="true" style="min-height: 200px; max-height: 400px; overflow-y: auto;">
                    </div>

                    <div id="recommendation-container" class="mt-4">
                        <label class="fw-bold mb-1 text-primary small uppercase">Teacher's Recommendation</label>
                        <div
                            id="recommendation-text"
                            contenteditable="true"
                            class="p-3 border rounded bg-white shadow-sm"
                            style="border-left: 5px solid #0d6efd !important; min-height: 60px;"
                            placeholder="Type recommendations here...">

                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary px-5" onclick="applyChanges()">Apply Changes</button>
                </div>
            </div>
        </div>
    </div>
    <footer class="footer-container mt-5">
        <?php include '../footer.php'; ?>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let activeId = null;
        let reliableModalInstance = null;

        function openReliableModal(id, title) {
            activeId = id;
            document.getElementById('m-title').innerText = title;

            // 1. Get the student's answer and TRIM it to remove extra spaces
            const sourceHtml = document.getElementById(id).innerHTML.trim();
            document.getElementById('m-body-editable').innerHTML = sourceHtml;

            // 2. Get existing recommendation
            const qNum = id.replace('q-ans-', '');
            const recInput = document.getElementById('input-rec-' + qNum);
            const recDisplay = document.getElementById('recommendation-text');

            // Check if the value actually exists and isn't just whitespace
            if (recInput && recInput.value.trim() !== "") {
                recDisplay.innerText = recInput.value;
            } else {
                recDisplay.innerText = "";
            }

            const modalEl = document.getElementById('reliableModal');
            if (!reliableModalInstance) {
                reliableModalInstance = new bootstrap.Modal(modalEl, {
                    backdrop: false,
                    keyboard: true
                });
            }
            modalEl.style.pointerEvents = 'auto';
            reliableModalInstance.show();
        }

        function applyChanges() {
            if (!activeId) {
                alert("Selection error. Please reopen the modal.");
                return;
            }

            const editor = document.getElementById('m-body-editable');
            const updatedHtml = editor.innerHTML;
            // Changed to innerText to keep recommendations clean, or innerHTML if you want formatting there too
            const recommendationText = document.getElementById('recommendation-text').innerText;

            // 1. Update the visual boxes on the background page
            const targetBox = document.getElementById(activeId);
            if (targetBox) {
                targetBox.innerHTML = updatedHtml;
            }

            // 2. Update hidden inputs for PHP form submission
            const qNum = activeId.replace('q-ans-', '');

            const hiddenAnsInput = document.getElementById('input-ans-' + qNum);
            if (hiddenAnsInput) hiddenAnsInput.value = updatedHtml;

            const hiddenRecInput = document.getElementById('input-rec-' + qNum);
            if (hiddenRecInput) hiddenRecInput.value = recommendationText;

            // 3. Close the modal and cleanup
            if (reliableModalInstance) {
                reliableModalInstance.hide();
                forceCleanup();
            }
        }

        function doHighlight() {
            const sel = window.getSelection();
            if (sel.rangeCount && !sel.isCollapsed) {
                const range = sel.getRangeAt(0);
                const span = document.createElement("span");
                span.className = "highlight-err";

                try {
                    // Robust method: extract the content and wrap it
                    span.appendChild(range.extractContents());
                    range.insertNode(span);
                    sel.removeAllRanges();
                } catch (e) {
                    // Fallback for very complex cross-element selections
                    alert("Selection too complex. Please highlight text within one section at a time.");
                }
            }
        }

        function clearHighlights() {
            const editor = document.getElementById('m-body-editable');
            // Regex fix: Removes the highlight spans but keeps the text and other HTML (like <br>)
            editor.innerHTML = editor.innerHTML.replace(/<span class="highlight-err">(.*?)<\/span>/g, '$1');
        }

        function filterTable() {
            // 1. Get the search input value
            const input = document.getElementById('studentSearch');
            const filter = input.value.toLowerCase();

            // 2. Get all rows in the table body
            const rows = document.querySelectorAll('.student-row');

            rows.forEach(row => {
                // We look at the first cell (ID) and second cell (Name)
                const studentId = row.cells[0].innerText.toLowerCase();
                const studentName = row.cells[1].innerText.toLowerCase();

                // 3. If the filter matches either ID or Name, show the row; otherwise, hide it
                if (studentId.includes(filter) || studentName.includes(filter)) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        }
    </script>
</body>

</html>