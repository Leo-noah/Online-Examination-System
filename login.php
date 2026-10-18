<?php
session_start();
require 'db.php';

$admin_notice = "";
// Check if user is redirected back with an identity or just submitted the form
$check_id = isset($_POST['login_identity']) ? $_POST['login_identity'] : (isset($_GET['identity']) ? $_GET['identity'] : '');

if (!empty($check_id)) {
    $stmt = $pdo->prepare("SELECT id FROM password_permissions WHERE identity = ? AND status = 'messaged' LIMIT 1");
    $stmt->execute([trim($check_id)]);
    if ($stmt->fetch()) {
        $admin_notice = "Please contact the Admin Team to proceed with your account.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Online Examination System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            margin: 0;
        }

        .main-container {
            width: 100%;
            max-width: 950px;
            display: flex;
            background: var(--glass);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.15);
            margin: 20px;
        }

        .side-accent {
            background: var(--primary-grad);
            width: 40%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            padding: 40px;
            text-align: center;
        }

        .form-section {
            width: 60%;
            padding: 60px;
            background: white;
        }

        .input-group-custom {
            position: relative;
            margin-bottom: 24px;
        }

        .input-group-custom i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            z-index: 10;
            font-size: 1.1rem;
        }

        .form-control-custom {
            width: 100%;
            padding: 14px 18px 14px 50px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            background: #f8fafc;
            transition: all 0.3s;
            font-size: 1.05rem;
            outline: none;
        }

        .form-control-custom:focus {
            border-color: var(--accent-blue);
            background: white;
            box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1);
        }

        .btn-upgrade {
            width: 100%;
            background: var(--accent-blue);
            border: none;
            color: white;
            padding: 16px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.2s;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.2);
        }

        .btn-upgrade:hover {
            background: #0b5ed7;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(13, 110, 253, 0.3);
        }

        .password-toggle {
            position: absolute;
            right: 18px;
            left: auto !important;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
            }

            .side-accent,
            .form-section {
                width: 100%;
            }

            .form-section {
                padding: 40px 30px;
            }
        }
    </style>
</head>

<body>

    <div class="main-container">
        <div class="side-accent">
            <div class="mb-6 bg-white/20 p-6 rounded-2xl backdrop-blur-md border border-white/30">
                <i class="fa-solid fa-right-to-bracket text-5xl"></i>
            </div>
            <h1 class="text-3xl font-bold mb-3">Welcome Back</h1>
            <p class="opacity-90 text-base mb-8">Secure access to your examination portal.</p>
            <img src="Screenshot 2026-01-29 135738.png" alt="Logo" class="w-36 h-36 object-contain rounded-full bg-white p-2 shadow-xl">
        </div>

        <div class="form-section">
            <div class="mb-10">
                <h2 class="text-3xl font-extrabold text-gray-900">Sign In</h2>
                <p class="text-gray-500 text-lg mt-1">Please enter your credentials</p>
            </div>

            <?php if (isset($_GET['wait'])): ?>
                <div class="bg-orange-50 border-l-4 border-orange-500 p-5 mb-6 rounded-r-xl shadow-sm flex items-start">
                    <i class="fa-solid fa-clock-rotate-left text-orange-500 mr-4 mt-1 text-lg"></i>
                    <div>
                        <p class="text-sm text-orange-900 font-bold uppercase tracking-tight">System Activation Scheduled</p>
                        <p class="text-xs text-orange-800 mt-1">
                            Logins will be enabled on: <span class="font-bold"><?= htmlspecialchars($_GET['date']); ?></span>.
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error']) && $_GET['error'] === 'pending'): ?>
                <div class="bg-blue-50 border-l-4 border-blue-500 p-5 mb-6 rounded-r-xl shadow-sm flex items-start">
                    <i class="fa-solid fa-user-clock text-blue-500 mr-4 mt-1 text-lg"></i>
                    <p class="text-sm text-blue-900 font-semibold leading-relaxed">Your account is pending Admin approval. Please try again after your role is assigned.</p>
                </div>
            <?php endif; ?>

            <?php if ($admin_notice): ?>
                <div class="bg-blue-50 border-l-4 border-blue-500 p-5 mb-6 rounded-r-xl shadow-sm flex items-start">
                    <i class="fa-solid fa-headset text-blue-500 mr-4 mt-1 text-lg"></i>
                    <p class="text-sm text-blue-900 font-semibold leading-relaxed"><?= $admin_notice; ?></p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error']) && $_GET['error'] === 'invalid'): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-5 mb-6 rounded-r-xl shadow-sm flex items-center">
                    <i class="fa-solid fa-circle-exclamation text-red-500 mr-4 text-lg"></i>
                    <p class="text-sm text-red-900 font-bold">Incorrect credentials. Please try again.</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['locked']) && $_GET['locked'] == '1'): ?>
                <div class="bg-orange-50 border-l-4 border-orange-500 p-5 mb-6 rounded-r-xl shadow-sm">
                    <div class="flex items-start mb-2">
                        <i class="fa-solid fa-user-lock text-orange-500 mr-4 mt-1 text-lg"></i>
                        <div>
                            <p class="text-sm text-orange-900 font-bold uppercase tracking-tight">Too many failed attempts</p>
                            <p class="text-xs text-orange-800 mt-1">Your account has been temporarily restricted for security.</p>
                        </div>
                    </div>
                    <button type="button" onclick="openResetModal()" class="ml-9 text-sm text-orange-700 font-bold hover:text-orange-900 underline underline-offset-4 decoration-2 transition">
                        Reset password to unlock
                    </button>
                </div>
            <?php endif; ?>

            <form action="login_process.php" method="POST">
                <label class="block text-gray-700 font-bold text-sm mb-2 ml-1">Username or Email</label>
                <div class="input-group-custom">
                    <i class="fa-solid fa-user-tag"></i>
                    <input type="text" name="login_identity" required placeholder="name@example.com" class="form-control-custom">
                </div>

                <label class="block text-gray-700 font-bold text-sm mb-2 ml-1">Password</label>
                <div class="input-group-custom">
                    <i class="fa-solid fa-key"></i>
                    <input id="password" type="password" name="password" required placeholder="••••••••" class="form-control-custom">
                    <i class="fa-regular fa-eye-slash password-toggle" id="togglePassword"></i>
                </div>

                <button type="submit" name="login" class="btn-upgrade mt-4">Login to Dashboard</button>
            </form>

            <div class="mt-10 text-center">
                <p class="text-gray-600 text-base">
                    Don't have an account?
                    <a href="register.php" class="text-blue-600 font-extrabold hover:text-blue-800 transition">Register Now</a>
                </p>
            </div>
        </div>
    </div>

    <div id="resetModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl p-10 transform transition-all">
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-orange-100 rounded-full mb-4">
                    <i class="fa-solid fa-user-shield text-orange-600 text-3xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-900">Account Recovery</h3>
                <p class="text-sm text-gray-500 mt-2 font-medium">An administrator will review your request to unlock your account.</p>
            </div>

            <form action="teacher/request_password.php" method="POST" class="space-y-6">
                <input type="hidden" name="source" value="login">
                <div class="input-group-custom">
                    <i class="fa-solid fa-at"></i>
                    <input type="text" name="login_identity" required placeholder="Enter registered Email/Username" class="form-control-custom">
                </div>

                <div class="flex gap-4">
                    <button type="button" onclick="closeResetModal()" class="flex-1 py-4 px-4 rounded-xl border-2 border-gray-100 text-gray-600 font-bold hover:bg-gray-50 transition">Cancel</button>
                    <button type="submit" name="request_pw_change" class="flex-1 py-4 px-4 rounded-xl bg-orange-500 text-white font-bold hover:bg-orange-600 transition shadow-lg shadow-orange-100">Send Request</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const passwordInput = document.querySelector('#password');

        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        function openResetModal() {
            document.getElementById('resetModal').classList.remove('hidden');
            document.getElementById('resetModal').classList.add('flex');
        }

        function closeResetModal() {
            document.getElementById('resetModal').classList.add('hidden');
            document.getElementById('resetModal').classList.remove('flex');
        }
        document.addEventListener('DOMContentLoaded', function() {
            // 1. Select all possible alert containers (orange, blue, and red ones)
            const allAlerts = document.querySelectorAll('.bg-orange-50, .bg-blue-50, .bg-red-50');

            allAlerts.forEach(function(alert) {
                // 2. Set timer for 7 seconds (7000ms)
                setTimeout(function() {
                    // 3. Apply smooth fade out
                    alert.style.transition = "opacity 0.6s ease, transform 0.6s ease";
                    alert.style.opacity = "0";
                    alert.style.transform = "translateY(-10px)"; // Subtle lift effect

                    // 4. Remove from layout after fade finishes
                    setTimeout(() => alert.remove(), 600);
                }, 7000);
            });
        });
    </script>
</body>

</html>