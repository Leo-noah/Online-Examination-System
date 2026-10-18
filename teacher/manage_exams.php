<?php
require '../db.php';
require_once __DIR__ . '/../assets/includes/header.php';
session_start();

// 1. SECURITY CHECK: Ensure user is logged in
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['teacher', 'tr'])) {
    header("Location: ../login.php?error=unauthorized");
    exit();
}
if (isset($_GET['action']) && $_GET['action'] === 'toggle_retake' && isset($_GET['exam_id'])) {
    $exam_id = intval($_GET['exam_id']);
    $current_state = intval($_GET['current_state']);

    // If current_state was 0, we want to turn it ON (1)
    $new_state = ($current_state == 0) ? 1 : 0;

    try {
        if ($new_state == 1) {
            // Extend deadline by 24 hours so it appears in "Available Exams"
            $new_deadline = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $stmt = $pdo->prepare("UPDATE exams SET allow_retake = 1, due_date = ? WHERE id = ?");
            $stmt->execute([$new_deadline, $exam_id]);
        } else {
            // Turn retake off
            $stmt = $pdo->prepare("UPDATE exams SET allow_retake = 0 WHERE id = ?");
            $stmt->execute([$exam_id]);
        }

        // IMPORTANT: Redirect back to the same page to "solidify" the change
        header("Location: manage_exams.php?msg=RetakeUpdated");
        exit();
    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
}
// Global Variables
$teacher_name = $_SESSION['username'] ?? "Unknown Teacher";
$user_id = $_SESSION['user_id'];
$view = $_GET['action'] ?? 'list_exams';
$selected_exam_id = (int)($_GET['exam_id'] ?? 0);
$error_msg = "";

// 2. DEPARTMENT MAPPING
$dept_mapping = [
    'Faculty of Computer Science'                  => 1,
    'Faculty of Computing'                         => 2,
    'Faculty of Information Science'               => 3,
    'Faculty of Computer Systems and Technologies' => 4,
    'Faculty of Languages'                         => 5
];
// 1. FETCH TEACHER DATA (Including the assigned courses column)
$stmt_teacher = $pdo->prepare("SELECT department, tr_year_level, course FROM users WHERE id = ?");
$stmt_teacher->execute([$user_id]);
$teacher_data = $stmt_teacher->fetch(PDO::FETCH_ASSOC);

// 2. PARSE THE ASSIGNED COURSES
// This converts "CST-1401, CST-2402..." into an array
$raw_assigned_courses = $teacher_data['course'] ?? '';
$assigned_course_codes = !empty($raw_assigned_courses)
    ? array_map('trim', explode(',', $raw_assigned_courses))
    : [];

$available_courses = [];

// 3. FETCH ONLY ASSIGNED COURSES
if (!empty($assigned_course_codes)) {
    // Because your 'course' column in 'users' table contains "CODE: Name", 
    // we need to extract just the CODE (e.g., CST-1401) to match the 'courses' table.
    $cleaned_codes = [];
    foreach ($assigned_course_codes as $full_string) {
        // Explode by colon and take the first part (the code)
        $parts = explode(':', $full_string);
        $cleaned_codes[] = trim($parts[0]);
    }

    // Create placeholders (?, ?, ?) for the cleaned codes
    $placeholders = implode(',', array_fill(0, count($cleaned_codes), '?'));

    // Query courses where the course_code matches the cleaned codes
    $sql = "SELECT course_code, course_name FROM courses WHERE course_code IN ($placeholders)";
    $stmt_courses = $pdo->prepare($sql);
    $stmt_courses->execute($cleaned_codes);

    $available_courses = []; // Reset to ensure it's fresh
    while ($row = $stmt_courses->fetch(PDO::FETCH_ASSOC)) {
        $available_courses[] = [
            'display' => $row['course_code'] . ": " . $row['course_name'],
            'value'   => $row['course_code']
        ];
    }
}
// --- 2. HELPER FUNCTIONS ---
function getRangeType($pdo, $ex_id, $q_num)
{
    $stmt = $pdo->prepare("SELECT q_type FROM question_ranges WHERE exam_id = ? AND ? BETWEEN start_q AND end_q");
    $stmt->execute([$ex_id, $q_num]);
    $row = $stmt->fetch();
    return $row ? $row['q_type'] : false;
}

function isValidRange($pdo, $ex_id, $q_num, $submitted_type)
{
    $rangeType = getRangeType($pdo, $ex_id, $q_num);
    if (!$rangeType) return false;
    return ($rangeType === 'Random' || $rangeType === $submitted_type);
}

// --- 3. EXAM & QUESTION DATA FETCHING ---
$exams_query = $pdo->prepare("SELECT * FROM exams WHERE teacher_name = ? ORDER BY id DESC");
$exams_query->execute([$teacher_name]);
$all_exams = $exams_query->fetchAll();

$current_exam = null;
if ($selected_exam_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ? AND teacher_name = ?");
    $stmt->execute([$selected_exam_id, $teacher_name]);
    $current_exam = $stmt->fetch();

    if ($current_exam) {
        $stmt = $pdo->prepare("SELECT * FROM question_ranges WHERE exam_id = ?");
        $stmt->execute([$selected_exam_id]);
        $current_exam['groups'] = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ?");
        $stmt->execute([$selected_exam_id]);
        $qs = $stmt->fetchAll();
        $current_exam['questions'] = [];
        foreach ($qs as $q) {
            $current_exam['questions'][$q['q_number']] = $q;
        }
    } else {
        header("Location: manage_exams.php?error=access_denied");
        exit();
    }
}

// --- 4. POST ACTIONS HANDLERS ---

// Delete Exam
if (isset($_GET['delete_exam_id'])) {
    $id = (int)$_GET['delete_exam_id'];
    $stmt = $pdo->prepare("DELETE FROM exams WHERE id = ? AND teacher_name = ?");
    $stmt->execute([$id, $teacher_name]);
    header("Location: manage_exams.php");
    exit();
}

// Toggle Retake (Deadline Logic)
if (($view == 'toggle_retake') && isset($_GET['exam_id'])) {
    $ex_id = (int)$_GET['exam_id'];
    $current_state = (int)$_GET['current_state'];

    $new_start = ($current_state == 0) ? date("Y-m-d H:i:s") : date("Y-m-d H:i:s", strtotime('-2 days'));
    $new_due   = ($current_state == 0) ? date("Y-m-d H:i:s", strtotime('+24 hours')) : date("Y-m-d H:i:s", strtotime('-1 minute'));
    $post_status = ($current_state == 0) ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE exams SET exam_date = ?, due_date = ?, is_posted = ? WHERE id = ? AND teacher_name = ?");
    $stmt->execute([$new_start, $new_due, $post_status, $ex_id, $teacher_name]);
    header("Location: manage_exams.php");
    exit();
}
// --- LOGIC 2: HANDLE EXAM SAVING (INSERT OR UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_exam'])) {

    // 1. Capture teacher identity from session
    $teacher_id = $_SESSION['user_id'];
    $teacher_name = $_SESSION['username'];

    // 2. Capture basic fields
    $id = !empty($_POST['exam_id']) ? (int)$_POST['exam_id'] : null;
    $title = !empty($_POST['exam_title']) ? $_POST['exam_title'] : 'Untitled Exam';

    // 3. FIX: Format Start Schedule (Line 215 Error Fix)
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
    $start_hrs  = str_pad($_POST['start_hrs'] ?? '09', 2, "0", STR_PAD_LEFT);
    $start_mins = str_pad($_POST['start_mins'] ?? '00', 2, "0", STR_PAD_LEFT);
    $full_exam_schedule = $start_date . " " . $start_hrs . ":" . $start_mins . ":00";

    // 4. FIX: Format Due Schedule (Line 216 Error Fix)
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : date('Y-m-d');
    $due_hrs  = str_pad($_POST['due_hrs'] ?? '23', 2, "0", STR_PAD_LEFT);
    $due_mins = str_pad($_POST['due_mins'] ?? '59', 2, "0", STR_PAD_LEFT);
    $full_due_schedule = $due_date . " " . $due_hrs . ":" . $due_mins . ":00";

    // 5. FIX: Calculate time limit (Line 217 & Integrity Constraint Fix)
    $time_hrs = (int)($_POST['time_hrs'] ?? 0);
    $time_mins = (int)($_POST['time_mins'] ?? 0);
    $total_limit_minutes = ($time_hrs * 60) + $time_mins;

    // Ensure time_limit is never 0 or null to satisfy database constraints
    if ($total_limit_minutes <= 0) {
        $total_limit_minutes = 60;
    }

    // 6. Handle Course Data & AUTO-EXTRACT Year Level
    $course_raw = $_POST['course_data'] ?? '';
    $year_levels = '';

    if (!empty($course_raw) && strpos($course_raw, '|') !== false) {
        list($course_db_id, $course_name) = explode('|', $course_raw);
        $course_db_id = (int)$course_db_id;

        // Auto-assign year based on CST-X pattern (e.g., CST-2101 becomes Year 2)
        if (preg_match('/CST-(\d)/', $course_name, $matches)) {
            $year_levels = $matches[1];
        }
    } else {
        $course_db_id = null;
        $course_name = 'Unknown Course';
    }

    // 7. Combine with manual Checkboxes if they exist
    if (isset($_POST['year_level']) && is_array($_POST['year_level'])) {
        $manual_years = implode(',', $_POST['year_level']);
        $all_years = array_unique(array_filter(array_merge([$year_levels], explode(',', $manual_years))));
        $year_levels = implode(',', $all_years);
    }

    try {
        if ($id) {
            // UPDATE existing exam
            $sql = "UPDATE exams SET 
                    title=?, course=?, course_id=?, exam_date=?, due_date=?, 
                    time_limit=?, tr_year_level=? 
                    WHERE id=? AND teacher_id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $title,
                $course_name,
                $course_db_id,
                $full_exam_schedule,
                $full_due_schedule,
                $total_limit_minutes,
                $year_levels,
                $id,
                $teacher_id
            ]);
        } else {
            // INSERT new exam
            $sql = "INSERT INTO exams (title, course, course_id, exam_date, due_date, time_limit, teacher_name, teacher_id, tr_year_level, is_posted) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $title,
                $course_name,
                $course_db_id,
                $full_exam_schedule,
                $full_due_schedule,
                $total_limit_minutes,
                $teacher_name,
                $teacher_id,
                $year_levels
            ]);
        }

        header("Location: manage_exams.php?status=success");
        exit();
    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
}
// Add Question Range Handler
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_range'])) {
    $ex_id = (int)($_POST['exam_id'] ?? 0);
    $start = (int)$_POST['range_from'];
    $end = (int)$_POST['range_to'];
    $type = $_POST['question_type'];
    $marks = (float)$_POST['marks'];

    // 1. Validation: Check for overlaps using a mathematical range check
    $stmt = $pdo->prepare("SELECT id FROM question_ranges WHERE exam_id = ? AND NOT (end_q < ? OR start_q> ?)");
    $stmt->execute([$ex_id, $start, $end]);

    if ($stmt->fetch()) {
        // 2. Redirect with error in URL if overlap is found
        header("Location: manage_exams.php?action=manage_questions&exam_id=$ex_id&error=Range Overlap!");
        exit();
    } else {
        // 3. Success: Insert the new range
        $stmt = $pdo->prepare("INSERT INTO question_ranges (exam_id, start_q, end_q, q_type, marks_per_q) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$ex_id, $start, $end, $type, $marks]);
        header("Location: manage_exams.php?action=manage_questions&exam_id=$ex_id&success=Range Added");
        exit();
    }
}
// Default values if no ranges exist yet
$auto_q_num = '';
$auto_type = 'MCQ';

if (!empty($current_exam['groups'])) {
    $last_group = end($current_exam['groups']);
    $auto_q_num = $last_group['start_q']; // Syncs Start Q#
    $auto_type = $last_group['q_type']; // Syncs Question Type
}
/// --- DELETE HANDLERS ---
if (isset($_GET['delete_range'])) {
    $range_id = (int)$_GET['delete_range'];
    // Delete the range definition
    $pdo->prepare("DELETE FROM question_ranges WHERE id = ?")->execute([$range_id]);
    header("Location: manage_exams.php?action=manage_questions&exam_id=$selected_exam_id");
    exit();
}

if (isset($_GET['delete_q'])) {
    $q_num = (int)$_GET['delete_q'];
    // Delete specific question from this exam
    $pdo->prepare("DELETE FROM questions WHERE exam_id = ? AND q_number = ?")->execute([$selected_exam_id, $q_num]);
    header("Location: manage_exams.php?action=manage_questions&exam_id=$selected_exam_id");
    exit();
}
// Bulk Import Questions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_import'])) {
    $ex_id = (int)$_POST['exam_id'];
    $import_type = $_POST['bulk_type'];
    $start_q = (int)$_POST['bulk_start_num'];
    $bulk_text = trim($_POST['bulk_text']);
    $count = 0;

    if ($import_type == 'MCQ') {
        // Split the bulk text into individual lines
        $lines = explode("\n", str_replace("\r", "", $bulk_text));

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Split by the pipe symbol
            $parts = explode('|', $line);

            // Ensure there are exactly 6 parts (Question + 4 Options + Answer)
            if (count($parts) == 6) {
                $q_num = $start_q + $count;

                if (isValidRange($pdo, $ex_id, $q_num, 'MCQ')) {
                    // Encode options into JSON to match your table structure
                    $opts = json_encode([
                        "A" => trim($parts[1]),
                        "B" => trim($parts[2]),
                        "C" => trim($parts[3]),
                        "D" => trim($parts[4])
                    ]);

                    $stmt = $pdo->prepare("INSERT INTO questions (exam_id, q_number, q_type, question_text, correct_answer, options)
    VALUES (?, ?, 'MCQ', ?, ?, ?)
    ON DUPLICATE KEY UPDATE question_text=VALUES(question_text), correct_answer=VALUES(correct_answer), options=VALUES(options)");
                    $stmt->execute([$ex_id, $q_num, trim($parts[0]), trim($parts[5]), $opts]);
                    $count++;
                }
            }
        }
    } else {
        $lines = explode("\n", $bulk_text);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $q_num = $start_q + $count;
            if (isValidRange($pdo, $ex_id, $q_num, $import_type)) {
                $final_text = $line;
                $final_ans = ($import_type == 'True/False') ? "TBD" : "Manual Grading";
                if ($import_type == 'True/False' && strpos($line, '|') !== false) {
                    list($final_text, $final_ans) = array_map('trim', explode('|', $line));
                }
                $stmt = $pdo->prepare("INSERT INTO questions (exam_id, q_number, q_type, question_text, correct_answer) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE question_text=VALUES(question_text), correct_answer=VALUES(correct_answer)");
                $stmt->execute([$ex_id, $q_num, $import_type, $final_text, $final_ans]);
                $count++;
            }
        }
    }
    header("Location: manage_exams.php?action=manage_questions&exam_id=$ex_id&bulk_success=$count");
    exit();
}

// Single Question Save
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_question_detail'])) {
    $ex_id = (int)$_POST['exam_id'];
    $q_num = (int)$_POST['q_number'];
    $type = $_POST['type'];
    $ans = $_POST['answer'] ?? (($type == 'Short Question' || $type == 'Long Question') ? "Manual Grading" : "");

    if (isValidRange($pdo, $ex_id, $q_num, $type)) {
        $opts = isset($_POST['options']) ? json_encode($_POST['options']) : null;

        // --- START IMAGE UPLOAD LOGIC ---
        $image_name = null;
        if (isset($_FILES['question_image']) && $_FILES['question_image']['error'] === 0) {
            $upload_dir = '../assets/uploads/questions/';

            // Create folder if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_ext = pathinfo($_FILES['question_image']['name'], PATHINFO_EXTENSION);
            $image_name = "q_" . time() . "_" . uniqid() . "." . $file_ext;

            if (!move_uploaded_file($_FILES['question_image']['tmp_name'], $upload_dir . $image_name)) {
                $image_name = null; // Reset if upload fails
            }
        }
        // --- END IMAGE UPLOAD LOGIC ---

        // Updated SQL to include question_image and ON DUPLICATE KEY UPDATE for the image
        $stmt = $pdo->prepare("INSERT INTO questions (exam_id, q_number, q_type, question_text, correct_answer, options, question_image)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
    question_text=VALUES(question_text),
    correct_answer=VALUES(correct_answer),
    options=VALUES(options),
    question_image=IFNULL(VALUES(question_image), question_image)");

        $stmt->execute([$ex_id, $q_num, $type, $_POST['question_text'], $ans, $opts, $image_name]);

        header("Location: manage_exams.php?action=manage_questions&exam_id=$ex_id");
        exit();
    } else {
        $error_msg = "Error: Q#$q_num does not fall into a valid range for type $type.";
    }
}

// Toggle Post Status
if (isset($_GET['toggle_post'])) {
    $stmt = $pdo->prepare("UPDATE exams SET is_posted = ? WHERE id = ? AND teacher_name = ?");
    $stmt->execute([(int)$_GET['status'] == 1 ? 0 : 1, (int)$_GET['toggle_post'], $teacher_name]);
    header("Location: manage_exams.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Exams | Online Examination System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Body & Layout */
        body {
            background-color: #f4f7f9;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin: 0;
        }

        body.modal-open #main-wrapper,
        body.modal-open .content-body,
        body.modal-open .container-fluid {
            filter: none !important;
            -webkit-filter: none !important;
            backdrop-filter: none !important;
            transition: none !important;
            /* This stops the 1-second delay */
        }

        /* Ensure the backdrop doesn't cause a freeze */
        .modal-backdrop {
            display: none !important;
        }

        .modal {
            background: rgba(0, 0, 0, 0.5);
            /* Semi-transparent background instead of blur */
        }


        .content-body {
            padding: 10px;
            min-height: calc(100vh - 120px);
        }

        /* Sidebar Cards Styling */
        .sticky-column .card {
            border: 1px solid #e0e6ed !important;
            border-radius: 12px;
            padding: 20px !important;
            margin-bottom: 25px !important;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
            background: #fff;
        }

        /* FIX: Change Focus Color to BLUE (Replaces Green) */
        .form-control:focus,
        .form-select:focus,
        .btn:focus {
            border-color: #007bff !important;
            /* Standard Bootstrap Blue */
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25) !important;
            /* Blue glow */
            outline: 0 !important;
        }

        /* Section 3: Quick Question Upload Fixes */
        #bulkTextarea {
            min-height: 280px;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-top: 10px;
            border: 1px solid #ced4da;
        }

        /* Change Section Title color to Blue */
        .text-success {
            color: #007bff !important;
            /* Changed from green to blue */
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 15px !important;
            display: block;
        }

        /* Right Side: Questions List Styling */
        .range-block {
            border: 1px solid #e0e6ed;
            border-radius: 12px;
            background: #fff;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .range-header {
            background: #f8f9fa;
            padding: 15px 20px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }

        /* Arrow icon color to Blue */
        .range-header .fa-chevron-down {
            transition: transform 0.3s ease;
            transform: rotate(-90deg);
            color: #007bff;
        }

        .range-header.active .fa-chevron-down {
            transform: rotate(0deg);
        }

        /* BUTTON FIX: Change Green Buttons to Blue */
        .btn-success {
            background-color: #007bff !important;
            border-color: #007bff !important;
            color: #ffffff !important;
            font-weight: 600;
        }

        .btn-success:hover {
            background-color: #0069d9 !important;
            border-color: #0062cc !important;
        }

        .btn-success:active,
        .btn-success:focus {
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.5) !important;

        }

        /* Container for each question - Balanced Spacing */
        .q-item {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1.25rem 1.5rem;
            /* Balanced internal breathing room */
            margin-bottom: 1.25rem;
            /* Perfect gap between different questions */
            position: relative;
            transition: all 0.2s ease-in-out;
        }

        /* Hover effect to show reliability */
        .q-item:hover {
            border-color: #cbd5e0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        /* Question Title - Bold but not crowded */
        .q-title {
            font-size: 1rem;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 0.75rem;
            /* Gap between question and meta-info */
            line-height: 1.5;
            padding-right: 30px;
        }

        /* Meta Data Box - Groups Type and Answer together */
        .q-meta-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            background-color: #f8fafc;
            /* Very light gray to group the info */
            padding: 6px 12px;
            border-radius: 6px;
            width: fit-content;
        }

        .q-type-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: #64748b;
        }

        .q-ans-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #059669;
            /* Balanced Green */
        }

        /* Individual Delete (X) - Properly Aligned */
        .q-delete-x {
            position: absolute;
            top: 1.25rem;
            right: 1.25rem;
            color: #ef4444;
            font-size: 1.1rem;
            cursor: pointer;
            opacity: 0.5;
            transition: opacity 0.2s;
        }

        .q-delete-x:hover {
            opacity: 1;
        }
    </style>
</head>

<body class="bg-light">
    <?php include 'header.php'; ?>

    <div class="content-body">
        <div class="container-fluid">
            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger shadow-sm"><?= $error_msg ?></div>
            <?php endif; ?>

            <?php if ($view == 'list_exams'): ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold m-0" style="font-size: 1.75rem; color: #1a1a1a;">Manage Exams</h2>
                    <button class="btn btn-primary shadow-sm px-4 py-2" onclick="openExamModal()" style="background-color: #007bff; border: none; font-weight: 500;">
                        Create New Exam
                    </button>
                </div>

                <div class="card shadow-sm border-0" style="border-radius: 10px;">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4 py-3 text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.05em; width: 5%;">No.</th>
                                        <th class="py-3 text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.05em; width: 8%;">Title</th>
                                        <th class="py-3 text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.05em; width: 15%;">Course</th>
                                        <th class="py-3 text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.05em; width: 10%;">Start Date</th>
                                        <th class="py-3 text-uppercase " style="font-size: 0.8rem; letter-spacing: 0.05em; width: 10%;">Deadline</th>
                                        <th class="py-3 text-uppercase text-center" style="font-size: 0.8rem; letter-spacing: 0.05em; width: 10%;">Time(Mins)</th>
                                        <th class="pe-4 py-3 text-uppercase text-center" style="font-size: 0.8rem; letter-spacing: 0.05em; width: 20%;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($all_exams)):
                                        $display_id = 1;
                                        foreach ($all_exams as $exam): ?>
                                            <tr style="border-bottom: 1px solid #f2f2f2;">
                                                <td class="ps-4 text-secondary">#<?= $display_id++ ?></td>

                                                <td class="fw-bold text-dark"><?= htmlspecialchars($exam['title']) ?></td>

                                                <td class="text-muted"><?= htmlspecialchars($exam['course']) ?></td>

                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <span class="text-dark small fw-bold">
                                                            <i class="far fa-calendar-alt me-1 text-primary"></i>
                                                            <?= !empty($exam['exam_date']) ? date("M d, Y", strtotime($exam['exam_date'])) : 'Not Set' ?>
                                                        </span>
                                                        <span class="text-primary small" style="font-size: 0.75rem;">
                                                            <i class="far fa-clock me-1"></i>
                                                            <?= !empty($exam['exam_date']) ? date("h:i A", strtotime($exam['exam_date'])) : '--:--' ?>
                                                        </span>
                                                    </div>
                                                </td>

                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <span class="text-dark small fw-bold">
                                                            <i class="far fa-calendar-times me-1 text-primary"></i>
                                                            <?= !empty($exam['due_date']) ? date("M d, Y", strtotime($exam['due_date'])) : 'No Limit' ?>
                                                        </span>
                                                        <span class="text-primary small" style="font-size: 0.75rem; opacity: 0.8;">
                                                            <i class="fas fa-hourglass-end me-1 text-primary"></i>
                                                            <?= !empty($exam['due_date']) ? date("h:i A", strtotime($exam['due_date'])) : '--:--' ?>
                                                        </span>
                                                    </div>
                                                </td>

                                                <td class="text-center">
                                                    <span class="badge bg-light text-dark border">
                                                        <?= htmlspecialchars($exam['time_limit'] ?? $exam['time']) ?> mins
                                                    </span>
                                                </td>

                                                <td class="text-end pe-4">
                                                    <div class="d-flex justify-content-center align-items-center gap-2">

                                                        <?php
                                                        // Logic: Retake is active if allow_retake is 1 AND the due_date hasn't passed yet
                                                        $isRetakeLive = ($exam['allow_retake'] == 1 && !empty($exam['due_date']) && strtotime($exam['due_date']) > time());
                                                        ?>

                                                        <div class="d-flex flex-column align-items-center justify-content-center" style="min-width: 80px; height: 60px;">
                                                            <div class="form-check form-switch p-0 m-0 d-flex align-items-center justify-content-center" style="height: 30px;">
                                                                <input class="form-check-input" type="checkbox" role="switch"
                                                                    id="retakeSwitch<?= $exam['id'] ?>"
                                                                    <?= $isRetakeLive ? 'checked' : '' ?>
                                                                    style="cursor: pointer; transform: scale(1.6); margin: 0; padding: 0; float: none;"
                                                                    onclick="const confirmMsg = '<?= $isRetakeLive ? 'End this retake session?' : 'Allow a 24-hour retake starting now?' ?>';
                     if(confirm(confirmMsg)) {
                         window.location.href='manage_exams.php?action=toggle_retake&exam_id=<?= $exam['id'] ?>&current_state=<?= $isRetakeLive ? 1 : 0 ?>';
                     } else {
                         this.checked = <?= $isRetakeLive ? 'true' : 'false' ?>;
                         return false;
                     }">
                                                            </div>

                                                            <label class="fw-bold <?= $isRetakeLive ? 'text-success' : 'text-muted' ?>"
                                                                for="retakeSwitch<?= $exam['id'] ?>"
                                                                style="font-size: 0.75rem; margin-top: 8px;">
                                                                <?= $isRetakeLive ? 'Retake ON' : 'Retake' ?>
                                                            </label>
                                                        </div>

                                                        <a href="?action=manage_questions&exam_id=<?= $exam['id'] ?>"
                                                            class="btn btn-primary btn-sm px-3"
                                                            style="background-color: #007bff; border: none; border-radius: 6px;">
                                                            Manage Question
                                                        </a>



                                                        <?php $isPosted = $exam['is_posted'] ?? 0; ?>
                                                        <a href="?toggle_post=<?= $exam['id'] ?>&status=<?= $isPosted ?>"
                                                            class="btn btn-sm px-3 shadow-sm text-white"
                                                            style="background-color: <?= $isPosted ? '#007bff' : '#6c757d' ?>; border: none; border-radius: 6px; min-width: 80px;">
                                                            <i class="fa <?= $isPosted ? 'fa-check-circle' : 'fa-paper-plane' ?> me-1"></i>
                                                            <?= $isPosted ? 'Unpost' : 'Post' ?>
                                                        </a>

                                                        <button class="btn btn-warning btn-sm text-white shadow-sm"
                                                            onclick='openExamModal(<?= json_encode($exam) ?>)'
                                                            style="border-radius: 6px;">
                                                            <i class="fa fa-edit"></i>
                                                        </button>

                                                        <a href="?delete_exam_id=<?= $exam['id'] ?>"
                                                            class="btn btn-danger btn-sm shadow-sm"
                                                            onclick="return confirm('Delete this exam?')"
                                                            style="border-radius: 6px;">
                                                            <i class="fa fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-5 text-muted">
                                                <i class="fas fa-exclamation-circle mb-2 fa-2x"></i><br>
                                                No exams found. Click "Add Exam" to get started.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($view == 'manage_questions' && $current_exam): ?>
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <a href="manage_exams.php" class="btn btn-outline-secondary btn-sm px-3 shadow-sm" style="border-radius: 8px;">
                        <i class="fa fa-chevron-left me-1"></i> Back to Exams
                    </a>

                    <div class="d-flex align-items-center">
                        <?php if (isset($_GET['error'])): ?>
                            <div id="auto-hide-badge" class="alert alert-danger d-flex align-items-center mb-0 py-2 px-3 me-3 animate__animated animate__fadeInRight"
                                style="font-size: 0.85rem; border-radius: 12px; border: none; background: rgba(220, 53, 69, 0.1); color: #dc3545; backdrop-filter: blur(4px); border-left: 4px solid #dc3545; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                                <i class="fa fa-exclamation-triangle me-2"></i>
                                <span class="fw-semibold text-uppercase" style="letter-spacing: 0.5px;"><?= htmlspecialchars($_GET['error']) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['success'])): ?>
                            <div id="auto-hide-badge" class="alert alert-success d-flex align-items-center mb-0 py-2 px-3 me-3 animate__animated animate__fadeInRight"
                                style="font-size: 0.85rem; border-radius: 12px; border: none; background: rgba(40, 167, 69, 0.1); color: #28a745; backdrop-filter: blur(4px); border-left: 4px solid #28a745; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                                <i class="fa fa-check-circle me-2"></i>
                                <span class="fw-semibold text-uppercase" style="letter-spacing: 0.5px;">Changes Saved Successfully</span>
                            </div>
                        <?php endif; ?>

                        <div class="text-end">

                            <h4 class="fw-bold mb-0 text-dark" style="letter-spacing: -0.5px;"><?= htmlspecialchars($current_exam['title']) ?></h4>
                        </div>
                    </div>
                </div>


                <div class="row">
                    <div class="col-md-4">
                        <div class="sticky-column">
                            <div class="card p-3 shadow-sm mb-3">
                                <h6 class="fw-bold">Define Range</h6>
                                <form method="POST">
                                    <input type="hidden" name="exam_id" value="<?= $selected_exam_id ?>">
                                    <div class="row g-2 mb-2">
                                        <div class="col-6">
                                            <label class="small">From</label>
                                            <input type="number" name="range_from" class="form-control" placeholder="1" value="1">
                                        </div>
                                        <div class="col-6">
                                            <label class="small">To</label>
                                            <input type="number" name="range_to" class="form-control" placeholder="10" value="10">
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <label class="small">Question Type</label>
                                        <select name="question_type" id="q_type_select" class="form-select" onchange="updateMarksLabel()">
                                            <option value="MCQ">MCQ</option>
                                            <option value="True/False">True/False</option>
                                            <option value="Short Question">Short Question</option>
                                            <option value="Long Question">Long Question</option>

                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="small" id="marksLabel">Marks per Question</label>
                                        <div class="input-group">
                                            <input type="number" step="0.1" name="marks" id="marksInput" class="form-control" placeholder="1.0" value="1.0">
                                            <span class="input-group-text text-warning">★</span>
                                        </div>
                                    </div>

                                    <button type="submit" name="add_range" class="btn btn-primary w-100">Add Range</button>
                                </form>
                            </div>

                            <div class="card p-3 shadow-sm mb-3">
                                <h6 class="fw-bold">Add Content</h6>
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="exam_id" value="<?= $selected_exam_id ?>">

                                    <div class="row g-2 mb-2">
                                        <div class="col-4">
                                            <input type="number" name="q_number" id="q_num_input" class="form-control form-control-sm" placeholder="Q#" value="<?= $auto_q_num ?>" required>
                                        </div>
                                        <div class="col-8">
                                            <select name="type" id="typeInp" class="form-select form-select-sm" onchange="toggleForm()">
                                                <option value="MCQ" <?= $auto_type == 'MCQ' ? 'selected' : '' ?>>MCQ</option>
                                                <option value="True/False" <?= $auto_type == 'True/False' ? 'selected' : '' ?>>True/False</option>
                                                <option value="Short Question" <?= $auto_type == 'Short Question' ? 'selected' : '' ?>>Short Question</option>
                                                <option value="Long Question" <?= $auto_type == 'Long Question' ? 'selected' : '' ?>>Long Question</option>
                                            </select>
                                        </div>
                                    </div>

                                    <textarea name="question_text" class="form-control form-control-sm mb-2" rows="2" placeholder="Question Text" required></textarea>

                                    <div id="image_upload_box" class="mb-2" style="display:none;">
                                        <label class="small fw-bold">Question Image (Optional):</label>
                                        <input type="file" name="question_image" class="form-control form-control-sm" accept="image/*">
                                    </div>

                                    <div id="mcq_box" class="mb-2">
                                        <div class="row g-1 mb-1">
                                            <div class="col-6"><input type="text" name="options[A]" class="form-control form-control-sm" placeholder="Choice A"></div>
                                            <div class="col-6"><input type="text" name="options[B]" class="form-control form-control-sm" placeholder="Choice B"></div>
                                        </div>
                                        <div class="row g-1 mb-1">
                                            <div class="col-6"><input type="text" name="options[C]" class="form-control form-control-sm" placeholder="Choice C"></div>
                                            <div class="col-6"><input type="text" name="options[D]" class="form-control form-control-sm" placeholder="Choice D"></div>
                                        </div>
                                    </div>

                                    <div id="ans_box" class="mb-2">
                                        <input type="text" name="answer" id="ans_field" class="form-control form-control-sm" placeholder="Correct Answer">
                                    </div>

                                    <button type="submit" name="save_question_detail" class="btn btn-success btn-sm w-100">Save Question</button>
                                </form>
                            </div>

                            <div class="card p-3 shadow-sm border-success">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="fw-bold mb-0">Quick Question Upload</h6>
                                </div>

                                <form method="POST" id="bulkUploadForm" onsubmit="return validateBulkData(event)">
                                    <input type="hidden" name="exam_id" value="<?= $selected_exam_id ?>">

                                    <select name="bulk_type" id="bulkTypeInp" class="form-select mb-3" onchange="updateBulkPlaceholder()">
                                        <option value="MCQ" <?= $auto_type == 'MCQ' ? 'selected' : '' ?>>MCQ Pattern</option>
                                        <option value="True/False" <?= $auto_type == 'True/False' ? 'selected' : '' ?>>True/False</option>
                                        <option value="Short Question" <?= $auto_type == 'Short Question' ? 'selected' : '' ?>>Short Qs</option>
                                        <option value="Long Question" <?= $auto_type == 'Long Question' ? 'selected' : '' ?>>Long Qs</option>
                                    </select>

                                    <input type="number" name="bulk_start_num" id="bulk_num_input" class="form-control mb-2" placeholder="Start Q#" value="<?= $auto_q_num ?>" required>

                                    <div id="formatHelper" class="mb-3 p-2 border rounded bg-light text-muted" style="font-size: 0.85rem; cursor: pointer; border-style: dashed !important;">
                                        Select a type to see the format.
                                    </div>

                                    <textarea name="bulk_text" id="bulkTextarea" class="form-control mb-3" rows="6" placeholder="Paste your questions here..." required></textarea>

                                    <button type="submit" name="bulk_import" class="btn btn-success w-100 py-1" style="color: #fdfdfd;">
                                        <i class="fa fa-bolt"></i> Process Bulk
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h5 class="fw-bold text-dark mb-0" style="font-size: 1.15rem;"> <i class="fa fa-list-ul me-2 text-primary"></i>Questions List
                            </h5>
                            <span class="badge bg-soft-primary text-primary px-3 py-2" style="background: #e7f1ff; border-radius: 50px; font-size: 0.85rem;"> <?= count($current_exam['groups'] ?? []) ?> Sections
                            </span>
                        </div>

                        <div id="qAccordion">
                            <?php if (isset($current_exam['groups'])): foreach ($current_exam['groups'] as $idx => $g): ?>
                                    <div class="range-block shadow-sm mb-3 bg-white" style="border-radius: 12px; overflow: hidden; border: 1px solid #eaeaea;">
                                        <div class="range-header p-3 d-flex justify-content-between align-items-center"
                                            style="cursor: pointer; background: #f8f9fa; transition: background 0.2s;"
                                            onclick="toggleRange(<?= $g['id'] ?>)"
                                            onmouseover="this.style.background='#f1f3f5'"
                                            onmouseout="this.style.background='#f8f9fa'">

                                            <div class="d-flex align-items-center">
                                                <div class="icon-box me-3 text-center" style="width: 30px;">
                                                    <i id="icon_<?= $g['id'] ?>" class="fa fa-chevron-right text-muted" style="transition: transform 0.3s; font-size: 0.9rem;"></i>
                                                </div>
                                                <div>
                                                    <span class="fw-bold d-block" style="font-size: 1rem;">Q<?= $g['start_q'] ?> - Q<?= $g['end_q'] ?></span> <small class="text-primary text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.5px;"> <?= $g['q_type'] ?> Group
                                                    </small>
                                                </div>
                                            </div>

                                            <a href="?action=manage_questions&exam_id=<?= $selected_exam_id ?>&delete_range=<?= $g['id'] ?>"
                                                class="btn btn-link text-danger p-0 text-decoration-none"
                                                style="font-size: 0.85rem;"
                                                onclick="return confirm('Delete this entire range?') && event.stopPropagation();">
                                                <i class="fa fa-trash-alt me-1"></i> Delete
                                            </a>
                                        </div>

                                        <div id="rng_<?= $g['id'] ?>" class="collapse shadow-inner" style="background: #fff;">
                                            <div class="p-0">
                                                <?php
                                                $found = false;
                                                for ($i = $g['start_q']; $i <= $g['end_q']; $i++):
                                                    if (isset($current_exam['questions'][$i])):
                                                        $q = $current_exam['questions'][$i];
                                                        $found = true; ?>
                                                        <div class="q-item p-3 d-flex justify-content-between align-items-center border-top hover-bg-light">
                                                            <div style="max-width: 85%;">
                                                                <div class="mb-1">
                                                                    <span class="badge bg-light text-dark me-2" style="font-size: 0.8rem;">Q<?= $i ?></span>
                                                                    <span class="text-dark" style="font-size: 0.95rem; line-height: 1.5;"><?= htmlspecialchars($q['question_text']) ?></span>
                                                                </div>
                                                                <div class="d-flex align-items-center gap-3 text-muted" style="font-size: 0.8rem;">
                                                                    <span><i class="fa fa-tag me-1"></i> <?= $q['q_type'] ?></span>

                                                                    <span class="text-success fw-medium">
                                                                        <i class="fa fa-check-circle me-1"></i> Ans:
                                                                        <?php
                                                                        if ($q['q_type'] === 'Long Question' && (empty($q['correct_answer']) || $q['correct_answer'] == 'Manual Grading')) {
                                                                            echo "Manual Grading";
                                                                        } else {
                                                                            echo htmlspecialchars($q['correct_answer']);
                                                                        }
                                                                        ?>
                                                                    </span>

                                                                    <?php if (!empty($q['question_image'])): ?>
                                                                        <span class="badge bg-soft-info text-info border border-info-subtle px-2" style="font-size: 0.75rem; background: #e0f7fa;">
                                                                            <i class="fa fa-image me-1"></i> Image exists
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                            <a href="?action=manage_questions&exam_id=<?= $selected_exam_id ?>&delete_q=<?= $i ?>"
                                                                class="btn btn-sm btn-outline-light text-danger border-0"
                                                                style="font-size: 1rem;"
                                                                onclick="return confirm('Delete question Q<?= $i ?>?')">
                                                                <i class="fa fa-times"></i>
                                                            </a>
                                                        </div>
                                                    <?php endif;
                                                endfor;
                                                if (!$found): ?>
                                                    <div class="p-4 text-center">
                                                        <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" style="width: 40px; opacity: 0.3;" class="mb-2">
                                                        <p class="text-muted mb-0" style="font-size: 0.85rem;">No questions added to this range yet.</p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                            <?php endforeach;
                            endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="modal fade" id="examModal" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius: 15px;">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Exam Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="exam_id" id="modal_exam_id">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Exam Title</label>
                            <input type="text" name="exam_title" id="modal_title" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Course</label>
                            <select name="course_data" class="form-control" required>
                                <option value="">-- Choose Course --</option>
                                <?php if (!empty($available_courses) && is_array($available_courses)): ?>
                                    <?php foreach ($available_courses as $c): ?>
                                        <?php
                                        // Use 'id' for the database and 'display' for the text
                                        $cid = $c['id'] ?? '';
                                        $cdisplay = $c['display'] ?? '';
                                        ?>
                                        <option value="<?= htmlspecialchars($cid . '|' . $cdisplay) ?>">
                                            <?= htmlspecialchars($cdisplay) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold ">Start Schedule</label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-primary text-white">Date</span>
                                <input type="date" name="start_date" id="modal_start_date" class="form-control" required>
                                <span class="input-group-text bg-light">Hour</span>
                                <input type="number" name="start_hrs" id="modal_start_hrs" class="form-control" placeholder="Hrs" min="0" max="23" required>
                                <span class="input-group-text">Min</span>
                                <input type="number" name="start_mins" id="modal_start_mins" class="form-control" placeholder="Min" min="0" max="59" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold text-danger">Due Date / Deadline</label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-danger text-white">Date</span>
                                <input type="date" name="due_date" id="modal_due_date" class="form-control" required>
                                <span class="input-group-text bg-light">Hour</span>
                                <input type="number" name="due_hrs" id="modal_due_hrs" class="form-control" placeholder="Hrs" min="0" max="23" required>
                                <span class="input-group-text">Min</span>
                                <input type="number" name="due_mins" id="modal_due_mins" class="form-control" placeholder="Min" min="0" max="59" required>
                            </div>
                            <small class="text-muted">Students cannot start the exam after this time.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Duration</label>
                            <div class="input-group">
                                <input type="number" name="time_hrs" id="modal_hrs" class="form-control" placeholder="0">
                                <span class="input-group-text">Hours</span>
                                <input type="number" name="time_mins" id="modal_mins" class="form-control" placeholder="0">
                                <span class="input-group-text">Minutes</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_exam" class="btn btn-success">Save Exam</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </div>

    <footer class="footer-container">
        <?php include '../footer.php'; ?>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let examModalInstance;
        const questionRanges = <?= json_encode($current_exam['groups'] ?? []) ?>;

        function autoUpdateType(numInput, targetTypeSelectId) {
            const qNum = parseInt(numInput.value);
            const typeSelect = document.getElementById(targetTypeSelectId);
            if (!qNum || !typeSelect) return;

            const match = questionRanges.find(r => qNum >= parseInt(r.start_q) && qNum <= parseInt(r.end_q));
            if (match) {
                typeSelect.value = match.q_type;
                if (targetTypeSelectId === 'typeInp') toggleForm();
                if (targetTypeSelectId === 'bulkTypeInp') updateBulkPlaceholder();
            }
        }

        function updateBulkPlaceholder() {
            const typeSelect = document.getElementById('bulkTypeInp');
            const textarea = document.getElementById('bulkTextarea');
            const helper = document.getElementById('formatHelper');

            if (!typeSelect || !textarea) return;

            const type = typeSelect.value;

            const formats = {
                'MCQ': "💡 MCQ FORMAT:\nQuestion | Opt A | Opt B | Opt C | Opt D | A\n\n(Keep on one line)",
                'True/False': "💡 TRUE/FALSE FORMAT:\nYour statement here | True\n\n(Keep on one line)",
                'Short Question': "💡 SHORT QUESTION FORMAT:\nType your question here. It can be many sentences.\n\n(⚠️ Leave a BLANK LINE between different questions. No '|' needed.)",
                'Long Question': "💡 LONG QUESTION FORMAT:\nWrite your full essay prompt here. You can use multiple sentences.\n\n(⚠️ Leave a BLANK LINE between different questions. No '|' needed.)"
            };

            textarea.placeholder = formats[type] || "Select a question type...";

            const templates = {
                'MCQ': "<strong>Copy:</strong> Question | Choice A | Choice B | Choice C | Choice D | A",
                'True/False': "<strong>Copy:</strong> Statement | True",
                'Short Question': "<strong>Copy:</strong> Write your question text here. (Press Enter twice for next Q)",
                'Long Question': "<strong>Copy:</strong> Write your detailed essay prompt here.(Press Enter twice for next Q)"
            };

            if (helper) {
                helper.innerHTML = templates[type] || "Select a type to see the format.";
            }
        }

        function validateBulkData(event) {
            const type = document.getElementById('bulkTypeInp').value;
            const textarea = document.getElementById('bulkTextarea');
            const text = textarea.value.trim();

            if (!text) {
                alert("Please paste your questions first.");
                event.preventDefault();
                return false;
            }

            // Split by blank lines (Double Enter)
            const questionBlocks = text.split(/\n\s*\n/).filter(q => q.trim() !== "");
            let errors = [];

            questionBlocks.forEach((block, index) => {
                const qNum = index + 1;

                if (type === 'MCQ') {
                    const parts = block.split('|').map(p => p.trim());
                    if (parts.length !== 6) {
                        errors.push(`Question #${qNum}: MCQ must have 6 parts separated by '|'.`);
                    }
                } else if (type === 'True/False') {
                    const parts = block.split('|').map(p => p.trim());
                    if (parts.length < 2) {
                        errors.push(`Question #${qNum}: True/False needs '|' and the answer.`);
                    }
                } else {
                    // SHORT/LONG QUESTION: NO PIPELINE NEEDED
                    // We just ensure the block has enough text to be a valid question
                    if (block.trim().length < 10) {
                        errors.push(`Question #${qNum}: The text is too short to be a valid question.`);
                    }
                }
            });

            if (errors.length > 0) {
                alert("⚠️ FORMAT ERRORS:\n\n" + errors.join('\n') + "\n\n💡 Tip: Use a blank line (Press Enter twice) to separate questions.");
                event.preventDefault();
                return false;
            }

            return true;
        }
        // AUTO-CHANGE LOGIC: Run when the page is fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // 1. Run once immediately on load
            updateBulkPlaceholder();

            // 2. Add listener to the dropdown for any future changes
            const typeSelect = document.getElementById('bulkTypeInp');
            if (typeSelect) {
                typeSelect.addEventListener('change', updateBulkPlaceholder);
            }

            // 3. Optional: Copy functionality
            const helper = document.getElementById('formatHelper');
            if (helper) {
                helper.style.cursor = "pointer";
                helper.addEventListener('click', function() {
                    const textToCopy = this.innerText.replace(/^Copy:\s*/i, '').trim();
                    if (textToCopy && !textToCopy.includes("Select a type")) {
                        navigator.clipboard.writeText(textToCopy).then(() => {
                            const originalHTML = this.innerHTML;
                            this.innerHTML = "✅ <span style='color: green;'>Template Copied!</span>";
                            setTimeout(() => {
                                this.innerHTML = originalHTML;
                            }, 1500);
                        });
                    }
                });
            }
        });

        function toggleForm() {
            const typeInp = document.getElementById('typeInp');
            if (!typeInp) return;

            const t = typeInp.value;
            const mcqBox = document.getElementById('mcq_box');
            const ansBox = document.getElementById('ans_box');
            const imgBox = document.getElementById('image_upload_box');

            if (imgBox) imgBox.style.display = (t === 'Short Question' || t === 'Long Question') ? 'block' : 'none';
            if (mcqBox) mcqBox.style.display = (t === 'MCQ') ? 'block' : 'none';

            if (ansBox) {
                if (t === 'MCQ' || t === 'True/False') {
                    let options = (t === 'MCQ') ?
                        `<option value="" disabled selected>Select Correct Choice</option><option value="A">Choice A</option><option value="B">Choice B</option><option value="C">Choice C</option><option value="D">Choice D</option>` :
                        `<option value="" disabled selected>Select Truth Value</option><option value="True">True</option><option value="False">False</option>`;
                    ansBox.innerHTML = `<select name="answer" id="ans_field" class="form-select form-select-sm" required>${options}</select>`;
                } else {
                    ansBox.innerHTML = `<input type="text" name="answer" id="ans_field" class="form-control form-control-sm" placeholder="Answer Hint (Manual Grading - Optional)">`;
                }
            }
        }

        function openExamModal(exam = null) {
            const modalElement = document.getElementById('examModal');

            // Remove existing backdrops to prevent screen freezing
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
            document.body.classList.remove('modal-open');

            const form = modalElement.querySelector('form');
            form.reset();
            document.getElementById('modal_exam_id').value = "";

            if (exam) {
                document.getElementById('modal_exam_id').value = exam.id;
                document.getElementById('modal_title').value = exam.title;

                // Match the course string to the dropdown
                const courseDropdown = document.getElementById('modal_course');
                if (courseDropdown) courseDropdown.value = exam.course;

                // Date & Time logic
                if (exam.exam_date && exam.exam_date !== "0000-00-00 00:00:00") {
                    const parts = exam.exam_date.split(" ");
                    document.getElementById('modal_start_date').value = parts[0];
                    const timeParts = parts[1].split(":");
                    document.getElementById('modal_start_hrs').value = parseInt(timeParts[0]);
                    document.getElementById('modal_start_mins').value = parseInt(timeParts[1]);
                }

                // Duration logic
                const totalMins = parseInt(exam.time_limit || 0);
                document.getElementById('modal_hrs').value = Math.floor(totalMins / 60);
                document.getElementById('modal_mins').value = totalMins % 60;
            }

            const modal = new bootstrap.Modal(modalElement);
            modal.show();

            // Remove the blur effect from the background
            setTimeout(() => {
                const wrapper = document.getElementById('main-wrapper');
                if (wrapper) {
                    wrapper.style.filter = 'none';
                    wrapper.style.webkitFilter = 'none';
                }
            }, 150);
        }
        // --- RESTORED FIX: Global Listener for any modal ---
        document.addEventListener('show.bs.modal', function() {
            const mainWrapper = document.getElementById('main-wrapper');
            if (mainWrapper) {
                mainWrapper.style.filter = 'none';
                mainWrapper.style.webkitFilter = 'none';
            }
        });

        window.onload = function() {
            // 1. Initial form state setup
            toggleForm();
            updateBulkPlaceholder(); // Add this line to set initial placeholder on load

            // 2. Setup "Add Content" Sync
            const qNumInput = document.getElementById('q_num_input');
            if (qNumInput) {
                qNumInput.addEventListener('input', () => autoUpdateType(qNumInput, 'typeInp'));
                // Run once immediately to catch pre-filled PHP values
                autoUpdateType(qNumInput, 'typeInp');
            }

            // 3. Setup "Quick Question Upload" Sync (The Missing Link)
            const bulkNumInput = document.getElementById('bulk_num_input');
            if (bulkNumInput) {
                bulkNumInput.addEventListener('input', () => autoUpdateType(bulkNumInput, 'bulkTypeInp'));
                // Run once immediately to catch pre-filled PHP values (like 11)
                autoUpdateType(bulkNumInput, 'bulkTypeInp');
            }

            // 4. Modal Fixes (keeping your existing logic)
            const examModalEl = document.getElementById('examModal');
            if (examModalEl) {
                examModalEl.addEventListener('show.bs.modal', function() {
                    setTimeout(() => {
                        document.body.classList.remove('modal-open');
                        document.body.style.overflow = 'auto';
                    }, 0);
                });
            }
        };
        document.addEventListener('DOMContentLoaded', function() {
            const badge = document.getElementById('auto-hide-badge');
            if (badge) {
                // Wait 10 seconds
                setTimeout(function() {
                    // Smoothly fade out
                    badge.style.transition = "opacity 0.6s ease";
                    badge.style.opacity = "0";

                    // Remove from the DOM entirely after fade finishes
                    setTimeout(function() {
                        badge.remove();
                    }, 600);
                }, 10000); // 10000ms = 10 seconds
            }
        });

        function toggleRange(id) {
            const target = document.getElementById('rng_' + id);
            const icon = document.getElementById('icon_' + id);

            // Initialize or get the Bootstrap Collapse instance
            const bsCollapse = new bootstrap.Collapse(target, {
                toggle: false
            });

            if (target.classList.contains('show')) {
                bsCollapse.hide();
                icon.style.transform = 'rotate(0deg)';
            } else {
                bsCollapse.show();
                icon.style.transform = 'rotate(90deg)';
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            // Select all alert elements
            const alerts = document.querySelectorAll('.alert');

            alerts.forEach(function(alert) {
                // Set a timeout for 10 seconds (10000 milliseconds)
                setTimeout(function() {
                    // Use Bootstrap's built-in fade effect if available
                    alert.classList.add('fade');

                    // Wait for the fade animation to finish, then remove the element
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 600); // 600ms matches standard CSS transition times
                }, 10000);
            });
        });
    </script>
</body>