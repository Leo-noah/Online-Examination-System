<?php
session_start();
require '../db.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";
$success = "";

// 2. FETCH USER DATA (To get role and username)
$stmt_user = $pdo->prepare("SELECT username, email, role FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$user = $stmt_user->fetch(PDO::FETCH_ASSOC);

// DETERMINES THE REDIRECT LINK BASED ON ROLE
// If teacher -> profile.php | If student -> student_profile.php
// --- UPDATED LOGIC IN THE PHP HEADER ---
// If your teacher files are in a folder named 'teacher' and 
// your student files are in 'student', adjust the paths accordingly.

$profile_url = ($user['role'] === 'teacher') ? "profile.php" : "student_profile.php";

// If the change_password file is in a subfolder, you likely need:
// $profile_url = ($user['role'] === 'teacher') ? "../teacher/profile.php" : "../student/student_profile.php";
// 3. PERMISSION CHECK
$stmt_permit = $pdo->prepare("SELECT id FROM password_permissions WHERE user_id = ? AND status = 'approved'");
$stmt_permit->execute([$user_id]);
if (!$stmt_permit->fetch()) {
    header("Location: $profile_url?error=no_permission");
    exit();
}

// 4. HANDLE UPDATE LOGIC
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_pw'])) {
    $new_pass = $_POST['new_password'];
    $conf_pass = $_POST['confirm_password'];

    if ($new_pass !== $conf_pass) {
        $error = "Passwords do not match.";
    } elseif (!preg_match('/^(?=(.*[A-Z]))(?=(.*[!@#$%^&*(),.?":{}|<>]))(?=(.*[0-9].*[0-9].*[0-9])).{8,}$/', $new_pass)) {
        $error = "Password does not meet the security requirements.";
    } else {
        try {
            $pdo->beginTransaction();
            $hashed_password = password_hash($new_pass, PASSWORD_DEFAULT);

            $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->execute([$hashed_password, $user_id]);

            $delete = $pdo->prepare("DELETE FROM password_permissions WHERE user_id = ?");
            $delete->execute([$user_id]);

            $pdo->commit();

            $success = "Security updated! Redirecting...";
            // REDIRECTS TO THE CORRECT PROFILE PAGE AFTER 2 SECONDS
            header("refresh:2;url=$profile_url?pw_success=1");
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Update failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Vault | Update Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root {
            --primary-grad: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            --glass: rgba(255, 255, 255, 0.95);
            --accent-blue: #0d6efd;
        }

        body {
            background: #f0f4f8;
            background-image: radial-gradient(#3b82f6 0.5px, #f0f4f8 0.5px);
            background-size: 20px 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .main-container {
            width: 100%;
            max-width: 900px;
            display: flex;
            background: var(--glass);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            margin: 20px;
        }

        .side-accent {
            background: var(--primary-grad);
            width: 35%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            padding: 40px;
            text-align: center;
        }

        .form-section {
            width: 65%;
            padding: 50px;
            background: white;
        }

        .avatar-vault {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .input-group-custom {
            position: relative;
            margin-bottom: 25px;
        }

        .input-group-custom i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            z-index: 10;
        }

        .form-control-custom {
            padding: 14px 15px 14px 45px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            background: #f8fafc;
            transition: all 0.3s;
        }

        .form-control-custom:focus {
            border-color: var(--accent-blue);
            background: white;
            box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1);
            outline: none;
        }

        .strength-meter {
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            margin: -15px 0 20px;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0;
            transition: all 0.5s ease;
        }

        .req-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 25px;
        }

        .req-chip {
            padding: 8px 12px;
            border-radius: 8px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            font-size: 0.75rem;
            color: #64748b;
            display: flex;
            align-items: center;
        }

        .req-chip i {
            margin-right: 8px;
            font-size: 0.6rem;
        }

        .req-chip.valid {
            background: #ecfdf5;
            border-color: #10b981;
            color: #065f46;
        }

        .btn-upgrade {
            background: var(--accent-blue);
            border: none;
            color: white;
            padding: 14px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-upgrade:hover {
            background: #0b5ed7;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
            }

            .side-accent,
            .form-section {
                width: 100%;
                padding: 30px;
            }
        }
    </style>
</head>

<body>

    <<div class="main-container">
        <div class="side-accent">
            <div class="avatar-vault">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h3 class="fw-bold">Hi, <?= htmlspecialchars($user['username']); ?></h3>
            <p class="opacity-75 small">Enhance your account security by choosing a strong, unique password.</p>
        </div>

        <div class="form-section">
            <div class="mb-4">
                <h4 class="fw-bold text-dark">Change Password</h4>
                <p class="text-muted small">Enter your new credentials below</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger border-0 small py-2 mb-4"><i class="fa fa-circle-exclamation me-2"></i><?= $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success border-0 small py-2 mb-4"><i class="fa fa-circle-check me-2"></i><?= $success; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-group-custom">
                    <i class="fa fa-lock"></i>
                    <input type="password" name="new_password" id="new_password" class="form-control form-control-custom w-100" placeholder="New Password" required>
                </div>

                <div class="strength-meter">
                    <div id="strength-bar" class="strength-bar"></div>
                </div>

                <div class="req-grid">
                    <div id="req-len" class="req-chip"><i class="fa-solid fa-circle"></i> 8+ Characters</div>
                    <div id="req-cap" class="req-chip"><i class="fa-solid fa-circle"></i> Capital Letter</div>
                    <div id="req-spec" class="req-chip"><i class="fa-solid fa-circle"></i> Special Char</div>
                    <div id="req-num" class="req-chip"><i class="fa-solid fa-circle"></i> 3+ Numbers</div>
                </div>

                <div class="input-group-custom mt-3">
                    <i class="fa fa-shield-check"></i>
                    <input type="password" name="confirm_password" class="form-control form-control-custom w-100" placeholder="Confirm Password" required>
                </div>

                <button type="submit" name="change_pw" class="btn btn-upgrade w-100 mb-3">
                    Update Credentials
                </button>

                <div class="text-center">
                    <a href="profile.php?reset_permission=1" class="text-decoration-none text-muted small fw-medium">
                        <i class="fa-solid fa-chevron-left me-1"></i> Cancel and Go Back
                    </a>
                </div>
            </form>
        </div>
        </div>

        <script>
            const pwInput = document.getElementById('new_password');
            const sBar = document.getElementById('strength-bar');

            const checks = {
                len: {
                    el: document.getElementById('req-len'),
                    reg: /.{8,}/
                },
                cap: {
                    el: document.getElementById('req-cap'),
                    reg: /[A-Z]/
                },
                spec: {
                    el: document.getElementById('req-spec'),
                    reg: /[!@#$%^&*(),.?":{}|<>]/
                },
                num: {
                    el: document.getElementById('req-num'),
                    reg: /(?=(.*[0-9]){3,})/
                }
            };

            pwInput.addEventListener('input', () => {
                const val = pwInput.value;
                let score = 0;

                for (const key in checks) {
                    const item = checks[key];
                    if (item.reg.test(val)) {
                        item.el.classList.add('valid');
                        item.el.querySelector('i').classList.replace('fa-circle', 'fa-check');
                        score++;
                    } else {
                        item.el.classList.remove('valid');
                        item.el.querySelector('i').classList.replace('fa-check', 'fa-circle');
                    }
                }

                const percent = (score / 4) * 100;
                sBar.style.width = percent + '%';
                if (score <= 1) sBar.style.background = '#f56565';
                else if (score <= 3) sBar.style.background = '#3b82f6'; // Match Blue Theme
                else sBar.style.background = '#10b981';
            });
        </script>
</body>

</html>