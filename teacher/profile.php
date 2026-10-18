<?php
require '../db.php';
require_once __DIR__ . '/../assets/includes/header.php';
session_start();

// 1. SECURITY: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
// --- RESET PERMISSION LOGIC ---
if (isset($_GET['reset_permission'])) {
    $user_id = $_SESSION['user_id'];
    try {
        // Delete the approved permission so it returns to 'Request Password' mode
        $reset_stmt = $pdo->prepare("DELETE FROM password_permissions WHERE user_id = ?");
        $reset_stmt->execute([$user_id]);

        // Optional: Redirect to clean the URL
        header("Location: profile.php");
        exit();
    } catch (PDOException $e) {
        error_log("Reset Error: " . $e->getMessage());
    }
}
// --- MISSING DATA DEFINITIONS (FIXES THE FATAL ERROR) ---
$dept_name_to_id = [
    'Faculty of Computer Science' => 1,
    'Faculty of Computing' => 2,
    'Faculty of Information Science' => 3,
    'Faculty of Computer Systems and Technologies' => 4,
    'Faculty of Languages' => 5
];

// --- UPDATED COURSE MAP GENERATION ---
// --- FIXED COURSE MAP GENERATION ---
$course_map = [];
$stmt_courses = $pdo->query("SELECT * FROM courses");
while ($row = $stmt_courses->fetch(PDO::FETCH_ASSOC)) {
    // Use ONLY the number as the key (e.g., 1, 2, 3)
    $sem_key = (string)$row['semester'];

    // IMPORTANT: Your dropdown value is 1, 2, 3, 4, 5. 
    // We must use the ID here to match 'deptSelect.value' in JS.
    $dept_id = $dept_name_to_id[$row['department_name']] ?? 0;

    $course_display = $row['course_code'] . ": " . $row['course_name'];
    $course_map[$sem_key][$dept_id][] = $course_display;
}
// -------------------------------------------------------

$user_id = $_SESSION['user_id'];

// Fetch user data from DB to prevent "undefined variable $user" errors
$stmt_user_data = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt_user_data->execute([$user_id]);
$user = $stmt_user_data->fetch(PDO::FETCH_ASSOC);

$edit_mode = isset($_GET['edit']);
$success_msg = "";

// --- UPDATED SAVE LOGIC (FIXED) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $new_username = $_POST['username'] ?? '';
    $country    = $_POST['country'] ?? '';
    $city       = $_POST['city'] ?? '';
    $address    = $_POST['address'] ?? '';
    $phone      = $_POST['phone'] ?? '';
    $department = $_POST['department'] ?? '';

    // FIX: Handle Multiple Course Selection
    $marked_courses = $_POST['courses'] ?? [];
    $course_string = !empty($marked_courses) ? implode(',', $marked_courses) : '';

    // Handle Semester Assignment Logic
    $mode = $_POST['teaching_mode'] ?? 'single';
    if ($mode === 'single') {
        $year_level = $_POST['year_level_single'] ?? '1';
    } else {
        $year_levels_array = $_POST['year_levels_multi'] ?? [];
        sort($year_levels_array);
        $year_level = implode(',', $year_levels_array);
        if (empty($year_level)) $year_level = '1';
    }

    // --- Image Upload Handling ---
    $stmt_img = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
    $stmt_img->execute([$user_id]);
    $current_img = $stmt_img->fetchColumn();
    $profile_img = $current_img;

    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $target_dir = "../assets/uploads/profiles/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        $file_ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $file_name = "user_" . $user_id . "_" . time() . "." . $file_ext;
        $target_file = $target_dir . $file_name;

        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
            $profile_img = $target_file;
        }
    }

    try {
        $pdo->beginTransaction();
        $sql_user = "UPDATE users SET username=?, country=?, city=?, address=?, phone=?, department=?, course=?, tr_year_level=?, profile_image=? WHERE id=?";
        $stmt_user = $pdo->prepare($sql_user);
        $stmt_user->execute([$new_username, $country, $city, $address, $phone, $department, $course_string, $year_level, $profile_img, $user_id]);

        $sql_exams = "UPDATE exams SET tr_year_level = ? WHERE teacher_id = ?";
        $stmt_exams = $pdo->prepare($sql_exams);
        $stmt_exams->execute([$year_level, $user_id]);

        $pdo->commit();
        $_SESSION['username'] = $new_username;
        header("Location: profile.php?success=1");
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Update Failed: " . $e->getMessage());
    }
}

// --- NEW CANCEL LOGIC ---
if (isset($_GET['action']) && $_GET['action'] === 'cancel_request') {
    $del_stmt = $pdo->prepare("DELETE FROM password_permissions WHERE user_id = ?");
    $del_stmt->execute([$user_id]);
    header("Location: profile.php");
    exit();
}

// Define variables for display
$username    = $user['username'] ?? ($_SESSION['username'] ?? 'Teacher');
$role        = $user['role'] ?? 'Teacher';
$email       = $user['email'] ?? 'Not set';
$country     = $user['country'] ?? 'Not provided';
$city        = $user['city'] ?? 'Not provided';
$address     = $user['address'] ?? 'Not provided';
$phone       = $user['phone'] ?? 'Not provided';
$course      = $user['course'] ?? '';
$year_level  = $user['tr_year_level'] ?? '1';
$profile_img = $user['profile_image'] ?? '';

// --- DEPARTMENT LOGIC ---
$dept_raw = $user['department'] ?? '1';
$dept_id = is_numeric($dept_raw) ? (int)$dept_raw : ($dept_name_to_id[$dept_raw] ?? 1);
$dept_id_to_name = array_flip($dept_name_to_id);
$department = $dept_id_to_name[$dept_id] ?? 'Faculty of Computer Science';

// --- DETAILED PERMISSION CHECK ---
$stmt_check = $pdo->prepare("SELECT status FROM password_permissions WHERE user_id = ? LIMIT 1");
$stmt_check->execute([$user_id]);
$permission = $stmt_check->fetch(PDO::FETCH_ASSOC);

$request_status = $permission ? $permission['status'] : 'none';
// status will be 'pending', 'approved', or 'none'

$initials = strtoupper(substr($username, 0, 2));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Online Examination System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* Ensure the body and wrapper use the full screen width */
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin: 0;
            background-color: #f4f7f9;
            font-family: 'Segoe UI', sans-serif;
        }

        /* The main wrapper needs to allow content to sit flush left */
        .main-content-wrapper {
            flex: 1 0 auto;
            display: flex;
            flex-direction: column;
            width: 100%;
        }

        /* FIX: Remove left padding so it touches the sidebar */
        .content-body {
            padding: 10px;
            /* Top, Right, Bottom, Left=0 */
        }

        /* FIX: Remove max-width and margin:auto to stop centering */
        .profile-container {
            width: 100%;
            padding-left: 20px;
            /* Small inner gap so text doesn't hit the sidebar line */
            margin: 0;
        }

        .profile-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            background: #ffffff;
            margin-bottom: 24px;
            width: 100%;
        }

        .avatar-circle {
            width: 90px;
            height: 90px;
            background: #e2e8f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            color: #4a5568;
            border: 3px solid #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .avatar-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .footer-container {
            flex-shrink: 0;
            background: white;
            border-top: 1px solid #eee;
            width: 100%;
        }

        /* Optional: Responsive fix for small screens */
        @media (max-width: 768px) {
            .content-body {
                padding: 15px;
            }

            .profile-container {
                padding-left: 0;
            }
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="main-content-wrapper">
        <form action="profile.php" method="POST" enctype="multipart/form-data" class="content-body">
            <div class="profile-container">
                <div class="card profile-card">
                    <div class="card-body d-flex align-items-center p-4">
                        <div class="avatar-circle me-4">
                            <?php if (!empty($profile_img) && file_exists($profile_img)): ?>
                                <img src="<?php echo $profile_img; ?>">
                            <?php else: ?>
                                <?php echo $initials; ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($edit_mode): ?>
                                <label class="small fw-bold text-muted">USERNAME</label>
                                <input type="text" name="username" class="form-control mb-2" value="<?php echo htmlspecialchars($username); ?>" required>
                                <input type="file" name="profile_image" class="form-control form-control-sm">
                            <?php else: ?>
                                <h3 class="mb-0 fw-bold"><?php echo htmlspecialchars($username); ?></h3>
                                <p class="text-primary fw-medium mb-0"><?php echo htmlspecialchars($role); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-8 mb-4">
                        <div class="card profile-card h-100">
                            <div class="card-header border-bottom bg-white py-3 fw-bold">Contact & Personal Details</div>
                            <div class="card-body p-4">
                                <div class="row mb-4">
                                    <div class="col-sm-4 text-muted small fw-bold">EMAIL ADDRESS</div>
                                    <div class="col-sm-8 fw-bold text-primary"><?php echo htmlspecialchars($email); ?></div>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-sm-4 text-muted small fw-bold">PHONE NUMBER</div>
                                    <div class="col-sm-8">
                                        <?php if ($edit_mode): ?>
                                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($phone); ?>" placeholder="+95 9...">
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($phone ?: 'Not provided'); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-sm-4 text-muted small fw-bold">FULL ADDRESS</div>
                                    <div class="col-sm-8">
                                        <?php if ($edit_mode): ?>
                                            <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($address); ?></textarea>
                                        <?php else: ?>
                                            <?php echo nl2br(htmlspecialchars($address ?: 'Not provided')); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-sm-4 text-muted small fw-bold">CITY & COUNTRY</div>
                                    <div class="col-sm-8">
                                        <?php if ($edit_mode): ?>
                                            <div class="input-group">
                                                <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($city); ?>" placeholder="City">
                                                <input type="text" name="country" class="form-control" value="<?php echo htmlspecialchars($country); ?>" placeholder="Country">
                                            </div>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($city ?: 'Yangon') . ", " . htmlspecialchars($country ?: 'Myanmar'); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 mb-4">
                        <div class="card profile-card h-100">
                            <div class="card-header border-bottom bg-white py-3 fw-bold">Course Details</div>
                            <div class="card-body p-4">
                                <div class="mb-4">
                                    <div class="text-muted small fw-bold">DEPARTMENT</div>
                                    <?php if ($edit_mode): ?>
                                        <select name="department" id="deptSelect" class="form-select mb-3" onchange="updateMultiSemesterCourses()">
                                            <option value="1" <?php echo ($dept_id == 1) ? 'selected' : ''; ?>>Faculty of Computer Science</option>
                                            <option value="2" <?php echo ($dept_id == 2) ? 'selected' : ''; ?>>Faculty of Computing</option>
                                            <option value="3" <?php echo ($dept_id == 3) ? 'selected' : ''; ?>>Faculty of Information Science</option>
                                            <option value="4" <?php echo ($dept_id == 4) ? 'selected' : ''; ?>>Faculty of Computer Systems and Technologies</option>
                                            <option value="5" <?php echo ($dept_id == 5) ? 'selected' : ''; ?>>Faculty of Languages</option>
                                        </select>
                                    <?php else: ?>
                                        <div class="fw-bold"><?php echo htmlspecialchars($department); ?></div>
                                    <?php endif; ?>

                                    <div class="text-muted small fw-bold mt-3">TEACHING SEMESTER ASSIGNMENT</div>
                                    <?php if ($edit_mode): ?>
                                        <?php
                                        // Split the stored value (e.g., "1,2,5") into an array
                                        $current_sems = explode(',', $year_level);
                                        $is_multi = count($current_sems) > 1;
                                        ?>

                                        <div class="btn-group w-100 mt-2 mb-3" role="group">
                                            <input type="radio" class="btn-check" name="teaching_mode" id="modeSingle" value="single" <?= !$is_multi ? 'checked' : ''; ?> onclick="toggleYearMode('single')">
                                            <label class="btn btn-outline-primary btn-sm" for="modeSingle">Single Semester</label>

                                            <input type="radio" class="btn-check" name="teaching_mode" id="modeMulti" value="multi" <?= $is_multi ? 'checked' : ''; ?> onclick="toggleYearMode('multi')">
                                            <label class="btn btn-outline-primary btn-sm" for="modeMulti">Multi Semester</label>
                                        </div>

                                        <div id="singleYearContainer" style="<?= $is_multi ? 'display:none;' : ''; ?>">
                                            <select name="year_level_single" class="form-select">
                                                <?php for ($s = 1; $s <= 10; $s++): ?>
                                                    <option value="<?= $s; ?>" <?= ($year_level == $s) ? 'selected' : ''; ?>>Semester <?= $s; ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>

                                        <div id="multiYearContainer" class="p-3 border rounded bg-white" style="<?= !$is_multi ? 'display:none;' : ''; ?>">
                                            <div class="row">
                                                <?php for ($s = 1; $s <= 10; $s++): ?>
                                                    <div class="col-6 mb-1">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="year_levels_multi[]" value="<?= $s; ?>" id="chkSem<?= $s; ?>" <?= in_array($s, $current_sems) ? 'checked' : ''; ?>>
                                                            <label class="form-check-label small" for="chkSem<?= $s; ?>">Semester <?= $s; ?></label>
                                                        </div>
                                                    </div>
                                                <?php endfor; ?>
                                            </div>
                                        </div>

                                    <?php else: ?>
                                        <div class="fw-bold">
                                            <?php
                                            // Check if $year_level (tr_year_level from DB) is not empty
                                            if (!empty(trim($year_level))): ?>
                                                <span class="text-success">
                                                    <?php
                                                    // Split the string (handles both single "1" and multi "1,2")
                                                    $labels = explode(',', $year_level);
                                                    $output = [];
                                                    foreach ($labels as $l) {
                                                        $trimmed = trim($l);
                                                        if ($trimmed !== '') {
                                                            // Prefix the number with "Semester "
                                                            $output[] = "Semester " . $trimmed;
                                                        }
                                                    }
                                                    // Join them back with commas if there are multiple
                                                    echo htmlspecialchars(implode(', ', $output));
                                                    ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-danger small">Not assigned</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="mb-4">
                                    <div class="text-muted small fw-bold mt-3">ASSIGNED COURSES (MARK ALL THAT APPLY)</div>
                                    <?php if ($edit_mode): ?>
                                        <div id="courseCheckboxContainer" class="p-3 border rounded bg-white mt-2" style="max-height: 250px; overflow-y: auto;">
                                            <div id="courseCheckboxList" class="row">
                                                <div class="col-12 text-muted small">Please select Semester(s) above to see available courses.</div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="fw-bold mt-1">
                                            <?= !empty($course) ? str_replace(',', ', ', htmlspecialchars($course)) : '<span class="text-danger small">No courses marked</span>'; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mb-4">
                    <?php if ($edit_mode): ?>
                        <a href="profile.php" class="btn btn-light px-4 border">Cancel</a>
                        <button type="submit" name="update_profile" class="btn btn-success px-4 shadow-sm">Save Changes</button>
                    <?php else: ?>
                        <a href="teacher_dashboard.php" class="btn btn-light px-4 border">Back</a>

                        <?php if ($request_status === 'approved'): ?>
                            <a href="change_password.php" class="btn btn-success px-4 shadow-sm fw-bold">
                                <i class="fa-solid fa-key me-1"></i> Change Password
                            </a>

                        <?php elseif ($request_status === 'pending'): ?>
                            <button type="button" class="btn btn-secondary px-4 shadow-sm fw-bold" disabled>
                                <i class="fa-solid fa-clock me-1"></i> Waiting for Admin...
                            </button>

                        <?php elseif ($request_status === 'rejected'): ?>
                            <button type="button" class="btn btn-danger px-4 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#pwRequestModal">
                                <i class="fa-solid fa-circle-xmark me-1"></i> Rejected - Request Again?
                            </button>

                        <?php else: ?>
                            <button type="button" class="btn btn-warning px-4 shadow-sm text-dark fw-bold" data-bs-toggle="modal" data-bs-target="#pwRequestModal">
                                <i class="fa-solid fa-lock me-1"></i> Request Password
                            </button>
                        <?php endif; ?>

                        <a href="profile.php?edit=1" class="btn btn-primary px-4 shadow-sm">Edit Profile</a>
                    <?php endif; ?>
                </div>

            </div>
        </form>

    </div>

    <footer class="footer-container">
        <?php include '../footer.php'; ?>
    </footer>

    <div class="modal fade" id="pwRequestModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
                <div class="modal-body text-center p-5">
                    <div class="mb-3">
                        <i class="fa-solid fa-shield-halved text-warning" style="font-size: 3.5rem;"></i>
                    </div>
                    <h4 class="fw-bold">Request Permission</h4>
                    <p class="text-muted">Send a password change request to the Admin? <br> You will be notified once they approve it.</p>

                    <div class="d-flex justify-content-center gap-2 mt-4">
                        <button type="button" class="btn btn-light px-4 border" data-bs-dismiss="modal">Cancel</button>

                        <form action="request_password.php" method="POST">
                            <input type="hidden" name="source" value="profile">

                            <input type="hidden" name="login_identity" value="<?php echo htmlspecialchars($email); ?>">

                            <button type="submit" name="request_pw_change" class="btn btn-warning px-4 text-dark fw-bold">
                                Yes, Send Request
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 1. Pass data from PHP to JS
        const fullCourseMap = <?php echo json_encode($course_map); ?>;
        const savedCoursesArray = <?php echo json_encode(!empty($course) ? explode(',', $course) : []); ?>;

        function updateMultiSemesterCourses() {
            const courseContainer = document.getElementById('courseCheckboxList');
            const deptSelect = document.getElementById('deptSelect');
            const modeMulti = document.getElementById('modeMulti');

            if (!courseContainer || !deptSelect) return;

            const currentSelectedDeptId = deptSelect.value; // This is "1", "2", etc.
            const isMultiMode = modeMulti.checked;

            courseContainer.innerHTML = ''; // Clear previous list

            let selectedSemesters = [];
            if (isMultiMode) {
                // Get all checked boxes in the multi-semester list
                const checkboxes = document.querySelectorAll('input[name="year_levels_multi[]"]:checked');
                checkboxes.forEach(cb => selectedSemesters.push(cb.value));
            } else {
                // Get the single value from the dropdown
                const singleSem = document.querySelector('select[name="year_level_single"]').value;
                selectedSemesters.push(singleSem);
            }

            let foundAny = false;

            selectedSemesters.forEach(sem => {
                // Check if this semester exists in map and if this department has courses in it
                if (fullCourseMap[sem] && fullCourseMap[sem][currentSelectedDeptId]) {
                    const courses = fullCourseMap[sem][currentSelectedDeptId];

                    courses.forEach((courseName, index) => {
                        foundAny = true;
                        const isChecked = savedCoursesArray.includes(courseName) ? 'checked' : '';

                        const div = document.createElement('div');
                        div.className = 'col-12 mb-2';
                        div.innerHTML = `
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="courses[]" 
                                   value="${courseName}" id="course_${sem}_${index}" ${isChecked}>
                            <label class="form-check-label small" for="course_${sem}_${index}">
                                <span class="badge bg-secondary me-1">Sem ${sem}</span> ${courseName}
                            </label>
                        </div>
                    `;
                        courseContainer.appendChild(div);
                    });
                }
            });

            if (!foundAny) {
                courseContainer.innerHTML = '<div class="col-12 text-muted small p-2">No courses found for selected Semester(s) and Faculty.</div>';
            }
        }

        // --- EVENT LISTENERS ---
        // Fix: Make sure elements exist before adding listeners
        document.addEventListener('DOMContentLoaded', function() {
            const deptSelect = document.getElementById('deptSelect');
            const singleSemSelect = document.querySelector('select[name="year_level_single"]');
            const multiCheckboxes = document.querySelectorAll('input[name="year_levels_multi[]"]');

            if (deptSelect) deptSelect.addEventListener('change', updateMultiSemesterCourses);
            if (singleSemSelect) singleSemSelect.addEventListener('change', updateMultiSemesterCourses);

            multiCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateMultiSemesterCourses);
            });

            // Run once on load
            updateMultiSemesterCourses();
        });

        function toggleYearMode(mode) {
            document.getElementById('singleYearContainer').style.display = (mode === 'single') ? 'block' : 'none';
            document.getElementById('multiYearContainer').style.display = (mode === 'multi') ? 'block' : 'none';
            // Re-run the course filter whenever we switch modes
            updateMultiSemesterCourses();
        }
    </script>

</html>