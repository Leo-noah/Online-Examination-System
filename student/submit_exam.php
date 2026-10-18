<?php
require '../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        die("Unauthorized access.");
    }

    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];
    $exam_id = (int)$_POST['exam_id'];

    // Capture flags from the form
    $is_reexam_attempt = isset($_POST['is_reexam']) && $_POST['is_reexam'] == '1';
    $violation_triggered = isset($_POST['violation_triggered']) && $_POST['violation_triggered'] == '1';

    $submitted_answers = $_POST['ans'] ?? [];
    $actual_ids = $_POST['question_actual_id'] ?? [];

    try {
        $stmt = $pdo->prepare("SELECT id, q_type, correct_answer FROM questions WHERE exam_id = ?");
        $stmt->execute([$exam_id]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $answers_to_save = [];
        $marks_to_save = [];
        $total_score = 0;

        foreach ($questions as $q) {
            $q_id = $q['id'];
            $displayIndex = array_search($q_id, $actual_ids);
            $ans = ($displayIndex !== false && isset($submitted_answers[$displayIndex])) ? trim($submitted_answers[$displayIndex]) : "";

            $answers_to_save[$q_id] = $ans;

            // Simple Auto-grading
            $type = strtoupper($q['q_type']);
            $correct = trim(strtolower($q['correct_answer']));
            $student_ans = trim(strtolower($ans));

            if (in_array($type, ['MCQ', 'TRUE/FALSE', 'TF']) && $student_ans !== '') {
                $is_correct = ($student_ans === $correct);
                $marks_to_save[$q_id] = $is_correct ? 1.0 : 0.0;
                if ($is_correct) $total_score += 1.0;
            } else {
                $marks_to_save[$q_id] = 0.0;
            }
        }

        // Determine Final Status
        if ($violation_triggered) {
            $status = 'disqualified';
        } elseif ($is_reexam_attempt) {
            $status = 'reexam';
        } else {
            $status = 'pending';
        }

        $sql = "INSERT INTO student_submissions 
                (exam_id, student_name, student_id, answers_json, marks_json, total_score, status, submitted_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

        $insert = $pdo->prepare($sql);
        $insert->execute([
            $exam_id,
            $username,
            $user_id,
            json_encode($answers_to_save),
            json_encode($marks_to_save),
            (float)$total_score,
            $status
        ]);

        header("Location: student_dashboard.php?exam_submitted=1");
        exit();
    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
}
