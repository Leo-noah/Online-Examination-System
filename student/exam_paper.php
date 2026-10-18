<?php
require '../db.php';
require_once __DIR__ . '/../assets/includes/header.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$exam_id = $_GET['exam_id'] ?? $_GET['id'] ?? null;
if (!$exam_id) {
    header("Location: student_dashboard.php");
    exit;
}

$student_id = $_SESSION['user_id'];

// --- FIX: CALCULATE RE-EXAM STATUS ---
$is_reexam = false;
try {
    $check_stmt = $pdo->prepare("SELECT id FROM student_submissions WHERE student_id = ? AND exam_id = ? LIMIT 1");
    $check_stmt->execute([$student_id, $exam_id]);
    if ($check_stmt->fetch()) {
        $is_reexam = true;
    }
} catch (PDOException $e) {
    // Silent fail to allow exam to proceed
}
// --- END FIX ---

try {
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    $q_stmt = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ?");
    $q_stmt->execute([$exam_id]);
    $questions_raw = $q_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($questions_raw)) {
        $grouped = [];
        foreach ($questions_raw as $q) {
            $type = $q['q_type'];
            $grouped[$type][] = $q;
        }
        foreach ($grouped as $type => &$group) {
            shuffle($group);
        }
        unset($group);
        $questions = [];
        foreach ($grouped as $type_group) {
            foreach ($type_group as $q) {
                $questions[] = $q;
            }
        }
    } else {
        $questions = [];
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

if (!$exam || empty($questions)) {
    header("Location: student_dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam: <?= htmlspecialchars($exam['title']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

    <style>
        body {
            background-color: #f4f7f9;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        .container {
            max-width: 100% !important;
            margin-left: 0 !important;
            padding-left: 20px;
            margin-right: auto;
        }

        .exam-layout {
            display: flex;
            gap: 25px;
            align-items: flex-start;
            margin-top: 50px;
            justify-content: center;
        }

        .questions-section {
            flex: 0 0 70%;
            max-width: 900px;
        }

        .sidebar-section {
            flex: 0 0 360px;
            position: sticky;
            top: 20px;
            height: calc(100vh - 40px);
            z-index: 1000;
        }

        .progress-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            background: #fff;
            height: 100%;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            padding: 0 !important;
        }

        .sidebar-header-title {
            padding: 1.25rem;
            border-bottom: 1px solid #eee;
            text-align: center;
            flex-shrink: 0;
        }

        .progress-grid-container {
            flex-grow: 2;
            overflow-y: auto;
            padding: 1rem 1.25rem;
        }

        .progress-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
        }

        .q-nav-btn {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            background: #fff;
            color: #333;
            font-weight: 600;
            font-size: 0.85rem;
            transition: 0.3s;
        }

        .q-nav-btn.answered {
            background-color: #20c997 !important;
            color: white !important;
            border-color: #20c997 !important;
        }

        .q-nav-btn.current {
            border: 2px solid #0d6efd !important;
            color: #0d6efd;
            background-color: #f0f7ff;
        }

        .submit-area {
            padding: 1.25rem;
            border-top: 1px solid #eee;
            background: #fff;
            flex-shrink: 0;
            text-align: center;
        }

        .q-card {
            border: none;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            scroll-margin-top: 100px;
            background: #fff;
        }

        .ans-option {
            display: block;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            cursor: pointer;
            transition: 0.2s;
            background: #fff;
            margin-bottom: 8px;
        }

        input[type="radio"]:checked+.ans-option {
            background-color: #e7f1ff;
            border-color: #0d6efd;
            color: #0d6efd;
            font-weight: 600;
        }

        .sidebar-timer {
            font-size: 2rem;
            font-weight: 800;
            color: #000;
            margin: 5px 0;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.8rem;
        }

        .dot {
            width: 12px;
            height: 12px;
            border-radius: 2px;
        }
    </style>
</head>

<body>

    <div id="fsOverlay" style="position: fixed; inset: 0; background: rgba(0, 0, 0, 0.7); display: flex; align-items: center; justify-content: center; z-index: 9999;">
        <div style="background: white; padding: 30px; border-radius: 12px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.3); max-width: 400px; width: 90%;">
            <div style="font-size: 50px; margin-bottom: 15px;">🖥️</div>
            <h4 style="color: #333; margin-bottom: 10px;">Full-Screen Required</h4>
            <p style="color: #666; font-size: 14px; margin-bottom: 25px;">To ensure a fair testing environment, this exam must be taken in full-screen mode.</p>
            <button id="startBtn" style="background: #0d6efd; color: white; border: none; padding: 12px 30px; border-radius: 6px; font-weight: bold; cursor: pointer; width: 100%;">I UNDERSTAND</button>
        </div>
    </div>

    <div class="container pb-5">
        <div class="exam-layout">
            <div class="questions-section">
                <form id="examForm" action="submit_exam.php" method="POST">
                    <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
                    <input type="hidden" name="is_reexam" value="<?= $is_reexam ? '1' : '0' ?>">

                    <input type="hidden" name="violation_triggered" id="violation_triggered" value="0">

                    <?php foreach ($questions as $index => $q):
                        $displayNum = $index + 1;
                        $q_id = $q['id'];
                        $type = $q['q_type'];
                        $opts = json_decode($q['options'], true) ?? [];
                    ?>
                        <input type="hidden" name="question_actual_id[<?= $displayNum ?>]" value="<?= $q_id ?>">

                        <div class="card q-card mb-4" id="q-block-<?= $displayNum ?>">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-primary fw-bold">Question <?= $displayNum ?></span>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($type) ?></span>
                                </div>

                                <h5 class="mb-4"><?= htmlspecialchars($q['question_text']) ?></h5>

                                <div class="answer-section">
                                    <?php if ($type === 'MCQ'): ?>
                                        <?php foreach (['A', 'B', 'C', 'D'] as $letter): ?>
                                            <div class="mb-2">
                                                <input type="radio" name="ans[<?= $displayNum ?>]" value="<?= $letter ?>" id="q<?= $q_id . $letter ?>" class="d-none radio-input">
                                                <label class="ans-option text-start d-block p-3 border rounded cursor-pointer" for="q<?= $q_id . $letter ?>">
                                                    <strong><?= $letter ?>.</strong> <?= htmlspecialchars($opts[$letter] ?? '') ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php elseif ($type === 'True/False' || $type === 'TF'): ?>
                                        <div class="d-flex gap-3">
                                            <div class="flex-grow-1">
                                                <input type="radio" name="ans[<?= $displayNum ?>]" value="True" id="q<?= $q_id ?>T" class="d-none radio-input">
                                                <label class="ans-option d-block p-3 border rounded text-center cursor-pointer" for="q<?= $q_id ?>T">True</label>
                                            </div>
                                            <div class="flex-grow-1">
                                                <input type="radio" name="ans[<?= $displayNum ?>]" value="False" id="q<?= $q_id ?>F" class="d-none radio-input">
                                                <label class="ans-option d-block p-3 border rounded text-center cursor-pointer" for="q<?= $q_id ?>F">False</label>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <textarea name="ans[<?= $displayNum ?>]" class="form-control" rows="5" placeholder="Type your answer here..."></textarea>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </form>
            </div>

            <div class="sidebar-section">
                <div class="card progress-card">
                    <div class="sidebar-header-title">
                        <h6 class="fw-bold mb-0 text-truncate"><?= htmlspecialchars($exam['title']) ?></h6>
                        <p class="small fw-bold mt-2 mb-0">Exam Progress</p>
                    </div>
                    <div class="progress-grid-container">
                        <div class="progress-grid mb-3">
                            <?php foreach ($questions as $index => $q): ?>
                                <a href="#q-block-<?= $index + 1 ?>" class="q-nav-btn" id="nav-dot-<?= $index + 1 ?>">
                                    <?= $index + 1 ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <div class="legend mb-3">
                            <div class="legend-item mb-1">
                                <div class="dot" style="background: #20c997;"></div><span>Answered</span>
                            </div>
                            <div class="legend-item">
                                <div class="dot" style="border: 2px solid #0d6efd; background: #f0f7ff;"></div><span>Current</span>
                            </div>
                        </div>
                    </div>
                    <div class="submit-area">
                        <small class="text-muted fw-bold d-block">TIME LEFT</small>
                        <div class="sidebar-timer" id="timer">--:--</div>
                        <button type="button" onclick="confirmSubmit()" class="btn btn-primary btn-lg w-100 fw-bold py-2 mt-1 shadow-sm">SUBMIT</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="customConfirmModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:10001; justify-content:center; align-items:center; font-family: sans-serif;">
        <div style="background:white; padding:30px; border-radius:15px; text-align:center; max-width:400px; width:90%;">
            <h4 style="margin-bottom: 15px;">Confirm Submission</h4>
            <p id="modalMessage" style="color: #0d6efd; margin-bottom: 25px;"></p>
            <div class="d-flex gap-2">
                <button onclick="handleModalResponse(false)" class="btn btn-secondary flex-grow-1">Go Back</button>
                <button onclick="handleModalResponse(true)" class="btn btn-primary flex-grow-1">Submit Anyway</button>
            </div>
        </div>
    </div>

    <div id="violationWarningModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:10000; justify-content:center; align-items:center; font-family: sans-serif;">
        <div style="background:white; padding:40px; border-radius:12px; text-align:center; max-width:500px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
            <h2 style="color:#333; margin-top:0;">⚠️ SECURITY ALERT</h2>
            <p id="warningText" style="font-size: 18px; color: #444; margin: 20px 0;"></p>
            <p style="color: #666; background: #f8f9fa; padding: 10px; border-radius: 5px;">
                <b>Final Warning:</b> Any further tab switching or exiting fullscreen will result in <b>immediate automatic submission</b>.
            </p>
            <button onclick="resumeAfterWarning()" style="background:#212529; color:white; border:none; padding:15px 30px; border-radius:5px; cursor:pointer; font-size: 16px; font-weight: bold; margin-top: 10px; width: 100%;">RETURN TO EXAM</button>
        </div>
    </div>

    <script>
        const overlay = document.getElementById("fsOverlay");
        const examForm = document.getElementById('examForm');
        const timerDisplay = document.getElementById('timer');
        let timeLeft = <?= (int)$exam['time_limit'] * 60 ?>;
        let examStarted = false;
        let isHandlingSubmit = false;
        let violationCount = 0;
        const MAX_VIOLATIONS = 1;

        function handleViolation(type) {
            if (!examStarted || isHandlingSubmit) return;

            violationCount++;

            if (violationCount > MAX_VIOLATIONS) {
                examStarted = false;
                isHandlingSubmit = true;

                // Flag the violation for the database
                document.getElementById('violation_triggered').value = "1";

                alert("SECURITY DISQUALIFICATION: Multiple violations detected. Your exam is being submitted now.");
                document.getElementById('examForm').submit();
            } else {
                showViolationWarning(type);
            }
        }

        function showViolationWarning(type) {
            isHandlingSubmit = true; // Pauses violation checking while modal is open
            const warningModal = document.getElementById('violationWarningModal');
            const warningMsg = type === "fullscreen_exit" ? "Fullscreen Mode Exited" : "Browser Tab Switched";

            document.getElementById('warningText').innerText = `Activity Detected: ${warningMsg}`;
            warningModal.style.display = 'flex';
        }

        function resumeAfterWarning() {
            document.getElementById('violationWarningModal').style.display = 'none';
            enterFullscreen();
            setTimeout(() => {
                isHandlingSubmit = false;
            }, 1000);
        }

        function enterFullscreen() {
            const el = document.documentElement;
            const requestMethod = el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen || el.msRequestFullscreen;
            if (requestMethod) return requestMethod.call(el);
            return Promise.reject("Not supported");
        }

        overlay.addEventListener("click", async () => {
            try {
                await enterFullscreen();
                overlay.style.display = "none";
                examStarted = true;
                startTimer();
            } catch (e) {
                alert("Fullscreen required to start.");
            }
        });

        document.addEventListener("fullscreenchange", () => {
            if (!document.fullscreenElement && examStarted && !isHandlingSubmit) handleViolation("fullscreen_exit");
        });

        window.addEventListener('blur', () => {
            if (examStarted && !isHandlingSubmit) handleViolation("tab_switch");
        });

        ['copy', 'paste', 'cut'].forEach(event => {
            document.addEventListener(event, (e) => {
                if (examStarted && !isHandlingSubmit) e.preventDefault();
            });
        });

        function startTimer() {
            const countdown = setInterval(() => {
                if (timeLeft <= 0) {
                    clearInterval(countdown);
                    examStarted = false;
                    examForm.submit();
                    return;
                }
                let mins = Math.floor(timeLeft / 60);
                let secs = timeLeft % 60;
                timerDisplay.innerHTML = mins.toString().padStart(2, '0') + ":" + secs.toString().padStart(2, '0');
                timeLeft--;
            }, 1000);
        }

        examForm.addEventListener('input', function(e) {
            const input = e.target;
            const qNumMatch = input.name.match(/ans\[(\d+)\]/);
            if (qNumMatch) {
                const qNum = qNumMatch[1];
                const navBtn = document.getElementById('nav-dot-' + qNum);
                if (navBtn) {
                    let hasVal = input.type === 'radio' ?
                        document.querySelector(`input[name="${input.name}"]:checked`) !== null :
                        input.value.trim() !== "";
                    hasVal ? navBtn.classList.add('answered') : navBtn.classList.remove('answered');
                }
            }
        });

        function confirmSubmit() {
            const total = <?= count($questions) ?>;
            const answered = document.querySelectorAll('.q-nav-btn.answered').length;
            const modal = document.getElementById('customConfirmModal');
            const modalMsg = document.getElementById('modalMessage');
            isHandlingSubmit = true;
            modalMsg.innerText = (answered < total) ?
                `You only answered ${answered}/${total}. Click "Submit Anyway" or "Go Back" to finish.` :
                "All questions answered! Are you sure you want to finish and submit?";
            modal.style.display = 'flex';
        }

        function handleModalResponse(isConfirmed) {
            const modal = document.getElementById('customConfirmModal');
            modal.style.display = 'none';
            if (isConfirmed) {
                examForm.submit();
            } else {
                enterFullscreen();
                setTimeout(() => {
                    isHandlingSubmit = false;
                    examStarted = true;
                }, 1000);
            }
        }
    </script>
</body>

</html>