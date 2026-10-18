<?php
require '../db.php';
require_once __DIR__ . '/../assets/includes/header.php';
session_start();

// 1. SECURITY & SESSION CHECK
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$edit_mode = isset($_GET['edit']);
$success_msg = isset($_GET['success']) ? "Profile updated successfully!" : "";

// If the user is returning from a cancel, delete their permission
if (isset($_GET['reset_permission'])) {
    $stmt = $pdo->prepare("DELETE FROM password_permissions WHERE user_id = ?");
    $stmt->execute([$user_id]);
    header("Location: student_profile.php"); // Clean the URL
    exit();
}
// --- 2. SAVE & UPDATE LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $new_user       = $_POST['username'];
    $country        = $_POST['country'];
    $city           = $_POST['city'];
    $address        = $_POST['address'];
    $phone          = $_POST['phone'];
    $major          = $_POST['major'];
    $semester_input = $_POST['semester_level'] ?? '';

    // Fetch current state from DB
    $stmt_check = $pdo->prepare("SELECT stu_id, profile_image FROM users WHERE id = ?");
    $stmt_check->execute([$user_id]);
    $current_data = $stmt_check->fetch(PDO::FETCH_ASSOC);

    $stu_id = $current_data['stu_id'];
    $profile_image_path = $current_data['profile_image']; // Keep existing by default

    // --- ID GENERATION LOGIC ---
    if (empty($stu_id) && !empty($semester_input)) {
        $random_num = rand(1000, 9999);
        $stu_id = "STU-" . $random_num;
        $check_unique = $pdo->prepare("SELECT id FROM users WHERE stu_id = ?");
        $check_unique->execute([$stu_id]);
        if ($check_unique->fetch()) {
            $stu_id = "STU-" . rand(1000, 9999);
        }
    }

    // --- IMAGE UPLOAD LOGIC ---
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        $file_extension = strtolower(pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($file_extension, $allowed_types)) {
            $file_name = "std_" . $user_id . "_" . time() . "." . $file_extension;
            $target_file = $target_dir . $file_name;

            if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
                $profile_image_path = $target_file;
            }
        }
    }

    // --- UPDATE DATABASE ---
    $sql = "UPDATE users SET username=?, country=?, city=?, address=?, phone=?, department=?, year_level=?, stu_id=?, profile_image=? WHERE id=?";
    $params = [$new_user, $country, $city, $address, $phone, $major, $semester_input, $stu_id, $profile_image_path, $user_id];

    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($params)) {
        $_SESSION['username'] = $new_user;
        header("Location: student_profile.php?success=1");
        exit();
    }
}

// --- 3. FETCH DATA FOR DISPLAY ---
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Define variables for HTML
$name           = $user['username'] ?? 'Student';
$role           = $user['role'] ?? 'Student';
$email          = $user['email'] ?? 'Not set';
$country        = $user['country'] ?? '';
$city           = $user['city'] ?? '';
$address        = $user['address'] ?? '';
$phone          = $user['phone'] ?? '';
$major          = $user['department'] ?? '';
$semester_level = $user['year_level'] ?? '';
$stu_id_val     = $user['stu_id'] ?? '';
$profile_img    = $user['profile_image'] ?? '';

// --- Fix for the "Error word" / Initials logic ---
$initials = strtoupper(substr($name, 0, 1));

$request_status = 'none';
try {
    // With the UNIQUE constraint, this will always find the correct, single record
    $check_stmt = $pdo->prepare("SELECT status FROM password_permissions WHERE user_id = ? LIMIT 1");
    $check_stmt->execute([$user_id]);
    $permission = $check_stmt->fetch(PDO::FETCH_ASSOC);
    $request_status = $permission ? $permission['status'] : 'none';
} catch (PDOException $e) {
    $request_status = 'none';
}
$majors_list = ["Software Engineering", "High Performance Computing", "Cyber Security", "Knowledge Engineering", "Business Information System", "Communication and Networking", "Embedded Systems"];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile | Online Examination System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin: 0;
            background-color: #f4f7f9;
            font-family: 'Segoe UI', sans-serif;
        }

        .main-content-wrapper {
            flex: 1 0 auto;
            display: flex;
            flex-direction: column;
            width: 100%;
        }

        .content-body {
            padding: 30px 30px 30px 0;
        }

        .profile-container {
            width: 100%;
            padding-left: 20px;
        }

        .profile-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            background: #fff;
            margin-bottom: 24px;
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
        }
    </style>
</head>

<body>
    <?php include 'student_header.php'; ?>

    <div class="main-content-wrapper">
        <form action="student_profile.php" method="POST" enctype="multipart/form-data" class="content-body">
            <div class="profile-container">

                <div class="card profile-card">
                    <div class="card-body d-flex align-items-center p-4">
                        <div class="avatar-circle me-4">
                            <?php if (!empty($profile_img) && file_exists($profile_img)): ?>
                                <img src="<?php echo htmlspecialchars($profile_img); ?>" alt="Profile">
                            <?php else: ?>
                                <span><?php echo htmlspecialchars($initials); ?></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($edit_mode): ?>
                                <label class="small fw-bold text-muted">USERNAME</label>
                                <input type="text" name="username" class="form-control mb-2" value="<?php echo htmlspecialchars($name); ?>" required>
                                <input type="file" name="profile_image" class="form-control form-control-sm">
                            <?php else: ?>
                                <h3 class="mb-0 fw-bold"><?php echo htmlspecialchars($name); ?></h3>
                                <p class="text-primary fw-medium mb-0">
                                    <?php echo htmlspecialchars($role); ?>
                                    <?php if ($stu_id_val) echo " | <span class='text-dark fw-bold'>" . htmlspecialchars($stu_id_val) . "</span>"; ?>
                                </p>
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
                                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($phone); ?>">
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($phone ?: 'Not provided'); ?>
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
                                <div class="row mb-0">
                                    <div class="col-sm-4 text-muted small fw-bold">FULL ADDRESS</div>
                                    <div class="col-sm-8">
                                        <?php if ($edit_mode): ?>
                                            <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($address); ?></textarea>
                                        <?php else: ?>
                                            <?php echo nl2br(htmlspecialchars($address ?: 'Not provided')); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 mb-4">
                        <div class="card profile-card h-100">
                            <div class="card-header border-bottom bg-white py-3 fw-bold">Academic Details</div>
                            <div class="card-body p-4">
                                <div class="mb-4">
                                    <div class="text-muted small fw-bold">MAJOR (SEPCIALIZATION)</div>
                                    <?php if ($edit_mode): ?>
                                        <select name="major" class="form-select">
                                            <option value="">-- Select Major (Optional) --</option>
                                            <?php foreach ($majors_list as $m): ?>
                                                <option value="<?php echo $m; ?>" <?php echo ($major == $m) ? 'selected' : ''; ?>>
                                                    <?php echo $m; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <div class="fw-bold text-success"><?php echo htmlspecialchars($major ?: 'Not selected'); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="text-muted small fw-bold">SEMESTER</div>
                                    <?php if ($edit_mode): ?>
                                        <select name="semester_level" class="form-select">
                                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                                <option value="<?php echo $i; ?>" <?php echo ($semester_level == $i) ? 'selected' : ''; ?>>
                                                    Semester <?php echo $i; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    <?php else: ?>
                                        <div class="fw-bold">
                                            <?php echo ($semester_level) ? "Semester " . htmlspecialchars($semester_level) : '<span class="text-danger">Not selected</span>'; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mb-4">
                <?php if ($edit_mode): ?>
                    <a href="student_profile.php" class="btn btn-light px-4 border">Cancel</a>

                    <button type="submit" name="update_profile" class="btn btn-success px-4 shadow-sm fw-bold">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes
                    </button>
                <?php else: ?>
                    <a href="student_dashboard.php" class="btn btn-light px-4 border">Back</a>

                    <?php if ($request_status === 'approved'): ?>
                        <a href="change_password.php" class="btn btn-success px-4 shadow-sm fw-bold">
                            <i class="fa-solid fa-key me-1"></i> Change Password
                        </a>
                    <?php elseif ($request_status === 'pending'): ?>
                        <button type="button" class="btn btn-secondary px-4 shadow-sm fw-bold" disabled>
                            <i class="fa-solid fa-clock me-1"></i> Waiting for Admin...
                        </button>
                    <?php elseif ($request_status === 'rejected'): ?>
                        <button type="button" class="btn btn-danger px-4 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#requestPwModal">
                            <i class="fa-solid fa-circle-xmark me-1"></i> Rejected - Request Again?
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-warning px-4 shadow-sm text-dark fw-bold" data-bs-toggle="modal" data-bs-target="#requestPwModal">
                            <i class="fa-solid fa-lock me-1"></i> Request Password
                        </button>
                    <?php endif; ?>

                    <a href="student_profile.php?edit=1" class="btn btn-primary px-4 shadow-sm">
                        <i class="fa-solid fa-user-pen me-1"></i> Edit Profile
                    </a>
                <?php endif; ?>
            </div>

        </form>
    </div>
    <footer class="footer-container">
        <?php include '../footer.php'; ?>
    </footer>

    <div class="modal fade" id="requestPwModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
                <div class="modal-body text-center p-5">
                    <div class="mb-3">
                        <i class="fa-solid fa-shield-halved text-warning" style="font-size: 3.5rem;"></i>
                    </div>
                    <h4 class="fw-bold">Request Permission</h4>
                    <p class="text-muted">Do you want to send a password change request to the Admin?</p>

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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>