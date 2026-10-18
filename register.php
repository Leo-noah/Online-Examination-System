<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Online Examination System</title>
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
            max-width: 1000px;
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
            padding: 40px 50px;
            background: white;
            max-height: 90vh;
            overflow-y: auto;
        }

        .input-group-custom {
            position: relative;
            margin-bottom: 22px;
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
            width: 100%;
            padding: 12px 15px 12px 45px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            background: #f8fafc;
            transition: all 0.3s;
            font-size: 0.95rem;
            outline: none;
        }

        .form-control-custom:focus {
            border-color: var(--accent-blue);
            background: white;
            box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1);
        }

        .error-hint {
            color: #ef4444;
            font-size: 0.7rem;
            position: absolute;
            bottom: -18px;
            left: 10px;
            display: none;
        }

        /* Strength Meter */
        .strength-meter {
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            margin: -10px 0 15px;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0;
            transition: all 0.5s ease;
        }

        /* Requirement Chips */
        .req-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 20px;
        }

        .req-chip {
            padding: 6px 10px;
            border-radius: 8px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            font-size: 0.7rem;
            color: #64748b;
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }

        .req-chip i {
            margin-right: 6px;
            font-size: 0.5rem;
        }

        .req-chip.valid {
            background: #ecfdf5;
            border-color: #10b981;
            color: #065f46;
        }

        .req-chip.valid i {
            color: #10b981;
        }

        .btn-upgrade {
            width: 100%;
            background: var(--accent-blue);
            border: none;
            color: white;
            padding: 14px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.2s;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }

        .btn-upgrade:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
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
        }
    </style>
</head>

<body>
    <div class="main-container">
        <div class="side-accent">
            <div class="mb-4 bg-white/20 p-5 rounded-2xl backdrop-blur-md border border-white/30">
                <i class="fa-solid fa-user-plus text-4xl"></i>
            </div>
            <h1 class="text-2xl font-bold mb-2">Join Us</h1>
            <p class="opacity-80 text-sm mb-6">Start your examination journey today.</p>
            <img src="Screenshot 2026-01-29 135738.png" alt="Logo" class="w-32 h-32 object-contain rounded-full bg-white p-2 shadow-lg">
        </div>

        <div class="form-section">
            <div class="mb-6">
                <h2 class="text-2xl font-extrabold text-gray-800">Create New Account</h2>
                <p class="text-gray-500 text-sm">Fill in your details to get started</p>
            </div>

            <form action="register_process.php" method="POST" id="registerForm">
                <div class="input-group-custom">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" name="username" required placeholder="Username" class="form-control-custom">
                </div>

                <div class="input-group-custom">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" id="email" name="email" required pattern=".+@gmail\.com" title="Please use a valid @gmail.com address" placeholder="Email Address (Gmail Only)" class="form-control-custom">
                    <span id="email-error" class="error-hint">Please enter a valid @gmail.com address.</span>
                </div>

                <div class="input-group-custom">
                    <i class="fa-solid fa-phone"></i>
                    <input type="tel" id="phone" name="phone" required placeholder="Phone Number" class="form-control-custom" oninput="this.value = this.value.replace(/[^0-9+]/g, '')">
                    <span id="phone-error" class="error-hint">Must be 10-15 digits.</span>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="input-group-custom">
                        <i class="fa-solid fa-globe"></i>
                        <input type="text" name="country" required placeholder="Country" class="form-control-custom">
                    </div>
                    <div class="input-group-custom">
                        <i class="fa-solid fa-city"></i>
                        <input type="text" name="city" required placeholder="City" class="form-control-custom">
                    </div>
                </div>

                <div class="input-group-custom">
                    <i class="fa-solid fa-location-dot"></i>
                    <input type="text" name="address" required placeholder="Full Address" class="form-control-custom">
                </div>

                <div class="input-group-custom">
                    <i class="fa-solid fa-lock"></i>
                    <input id="password" type="password" name="password" required placeholder="Password" class="form-control-custom">
                    <i class="fa-regular fa-eye-slash password-toggle" id="togglePassword"></i>
                </div>

                <div class="strength-meter">
                    <div id="strength-bar" class="strength-bar"></div>
                </div>

                <div class="req-grid">
                    <div id="req-len" class="req-chip"><i class="fa-solid fa-circle"></i> 8+ Characters</div>
                    <div id="req-cap" class="req-chip"><i class="fa-solid fa-circle"></i> Capital Letter</div>
                    <div id="req-spec" class="req-chip"><i class="fa-solid fa-circle"></i> Special Char</div>
                    <div id="req-num" class="req-chip"><i class="fa-solid fa-circle"></i> 1+ Number</div>
                </div>

                <div class="input-group-custom">
                    <i class="fa-solid fa-shield-halved"></i>
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" class="form-control-custom" required>
                    <span id="match-error" class="error-hint">Passwords do not match!</span>
                </div>

                <button type="submit" id="submitBtn" class="btn-upgrade mt-4">
                    <span id="btnText">Create Account</span>
                    <i id="spinner" class="fa-solid fa-circle-notch fa-spin hidden"></i>
                </button>
            </form>

            <div class="text-center mt-6">
                <a href="login.php" class="text-gray-500 text-sm hover:text-blue-600 transition">
                    <i class="fa-solid fa-chevron-left"></i> Already have an account? Sign in
                </a>
            </div>
        </div>
    </div>

    <script>
        const registerForm = document.querySelector('#registerForm');
        const passwordInput = document.querySelector('#password');
        const confirmInput = document.querySelector('#confirm_password');
        const emailInput = document.querySelector('#email');
        const phoneInput = document.querySelector('#phone');
        const submitBtn = document.querySelector('#submitBtn');

        // Toggle Password Visibility
        document.querySelector('#togglePassword').addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Real-time Email (GMAIL ONLY) & Phone validation
        emailInput.addEventListener('input', () => {
            // Updated regex to specifically check for gmail.com domain
            const valid = /^[^\s@]+@gmail\.com$/.test(emailInput.value.toLowerCase());
            document.querySelector('#email-error').style.display = valid || !emailInput.value ? 'none' : 'block';
            emailInput.style.borderColor = !valid && emailInput.value ? '#ef4444' : '#e2e8f0';
        });

        phoneInput.addEventListener('input', () => {
            const val = phoneInput.value.replace('+', '');
            const valid = val.length >= 10 && val.length <= 15;
            document.querySelector('#phone-error').style.display = valid || !phoneInput.value ? 'none' : 'block';
            phoneInput.style.borderColor = !valid && phoneInput.value ? '#ef4444' : '#e2e8f0';
        });

        // Password Strength & Requirement Logic
        const strengthBar = document.querySelector('#strength-bar');
        const reqs = {
            len: document.querySelector('#req-len'),
            cap: document.querySelector('#req-cap'),
            spec: document.querySelector('#req-spec'),
            num: document.querySelector('#req-num')
        };

        passwordInput.addEventListener('input', () => {
            const val = passwordInput.value;
            let score = 0;
            const checks = {
                len: val.length >= 8,
                cap: /[A-Z]/.test(val),
                spec: /[!@#$%^&*]/.test(val),
                num: /[0-9]/.test(val)
            };

            Object.keys(checks).forEach(key => {
                if (checks[key]) {
                    reqs[key].classList.add('valid');
                    score++;
                } else {
                    reqs[key].classList.remove('valid');
                }
            });

            const colors = ['#e2e8f0', '#ef4444', '#f59e0b', '#3b82f6', '#10b981'];
            strengthBar.style.width = (score / 4) * 100 + '%';
            strengthBar.style.background = colors[score];
        });

        // Final Form Submission Logic
        registerForm.addEventListener('submit', function(e) {
            let hasError = false;

            // Check Gmail Domain again on submit
            const emailVal = emailInput.value.toLowerCase();
            if (!emailVal.endsWith('@gmail.com')) {
                hasError = true;
                emailInput.style.borderColor = '#ef4444';
            }

            // Check Match
            if (passwordInput.value !== confirmInput.value) {
                document.querySelector('#match-error').style.display = 'block';
                confirmInput.style.borderColor = '#ef4444';
                hasError = true;
            } else {
                document.querySelector('#match-error').style.display = 'none';
                confirmInput.style.borderColor = '#e2e8f0';
            }

            // Check Phone Length
            const phoneVal = phoneInput.value.replace('+', '');
            if (phoneVal.length < 10 || phoneVal.length > 15) {
                hasError = true;
            }

            if (hasError) {
                e.preventDefault();
            } else {
                // Show Loading Spinner
                submitBtn.disabled = true;
                document.querySelector('#btnText').textContent = "Processing...";
                document.querySelector('#spinner').classList.remove('hidden');
            }
        });
    </script>
</body>

</html>