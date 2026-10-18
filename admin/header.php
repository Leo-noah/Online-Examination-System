<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// DB Connection
$conn_h = new mysqli(
    getenv('DB_HOST') ?: 'localhost',
    getenv('DB_USER') ?: 'root',
    getenv('DB_PASSWORD') ?: '',
    getenv('DB_NAME') ?: 'uit_exam_system'
);

if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $check_name = $conn_h->query("SELECT username FROM users WHERE id = $uid");

    if ($check_name && $user_row = $check_name->fetch_assoc()) {
        $_SESSION['admin_name'] = $user_row['username'];
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 260px;
            --nav-height: 95px;
            --uit-blue: #0d6efd;
            --transition-speed: 0.3s;
        }

        body {
            margin: 0;
            background-color: #f8f9fa;
            font-family: "Inter", "Segoe UI", sans-serif;
            overflow-x: hidden;
        }

        /* --- NAVBAR STYLE --- */
        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            height: var(--nav-height);
            background: white !important;
            border-bottom: 2px solid #ececec;
            z-index: 1050;
            display: flex;
            align-items: center;
            padding: 0 30px;
        }

        .navbar-brand img {
            height: 75px !important;
            width: auto;
            margin-right: 25px !important;
        }

        .navbar-brand span {
            font-size: 2rem !important;
            font-weight: 650 !important;
            letter-spacing: -1px;
            color: var(--uit-blue);
        }

        /* --- SIDEBAR STYLE --- */
        #mainSidebar {
            width: var(--sidebar-width);
            background: white;
            border-right: 1px solid #eee;
            position: fixed;
            top: var(--nav-height);
            bottom: 0;
            left: 0;
            z-index: 1040;
            transition: transform var(--transition-speed) ease;
            padding-top: 10px;
        }

        body.sidebar-toggled #mainSidebar {
            transform: translateX(-100%);
        }

        .nav-link {
            color: #555;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.2s;
        }

        .nav-link:hover {
            background: #f8f9fa;
            color: var(--uit-blue);
        }

        .active-link {
            background: #e7f1ff !important;
            color: var(--uit-blue) !important;
            font-weight: 600;
            border-right: 4px solid var(--uit-blue);
        }

        .nav-link i {
            width: 25px;
            text-align: center;
        }

        /* --- WRAPPER & CONTENT --- */
        .wrapper {
            margin-top: var(--nav-height);
            margin-left: var(--sidebar-width);
            min-height: calc(100vh - var(--nav-height));
            transition: all var(--transition-speed) ease;
            padding: 40px;
            width: calc(100% - var(--sidebar-width));
        }

        body.sidebar-toggled .wrapper {
            margin-left: 0;
            width: 100%;
        }

        /* --- THE BLUR LOGIC --- */

        /* We ONLY blur the background elements, NEVER the body or the wrapper itself */
        body.modal-open #blur-me,
        body.modal-open .navbar,
        body.modal-open #mainSidebar {
            filter: blur(8px) !important;
            pointer-events: none;
            transition: filter 0.3s ease;
        }

        /* MODAL SETTINGS (Ensures it pops over the blur) */
        .modal {
            z-index: 10001 !important;
            filter: none !important;
            /* Explicitly tell browser: DO NOT BLUR THIS */
        }

        .modal-backdrop {
            z-index: 10000 !important;
            background-color: rgba(0, 0, 0, 0.4) !important;
        }

        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>

<body>
    <nav class="navbar border-bottom shadow-sm">
        <div class="d-flex align-items-center w-100 justify-content-between">
            <div class="d-flex align-items-center">
                <button class="btn btn-light border-0 me-4" id="sidebarToggle" type="button">
                    <i class="fa fa-bars fa-lg"></i>
                </button>
                <a class="navbar-brand mb-0 fw-bold text-primary d-flex align-items-center" href="admin_dashboard.php" style="text-decoration: none;">
                    <img src="../Screenshot 2026-01-29 135738.png" alt="Logo">
                    <span class="d-none d-md-inline">Online Examination System</span>
                </a>
            </div>
            <div class="d-flex align-items-center">
                <a href="admin_dashboard.php" class="text-decoration-none">
                    <span class="me-4 fw-bold text-muted">
                        <i class="fa fa-user-circle me-2"></i>
                        <?= htmlspecialchars($_SESSION['admin_name'] ?? $_SESSION['username'] ?? 'User'); ?>
                    </span>
                </a>
                <button type="button" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#logoutConfirmModal">
                    Logout
                </button>
            </div>
        </div>
    </nav>

    <div id="mainSidebar">
        <ul class="nav flex-column" style="list-style: none; padding: 0;">
            <li class="nav-item">
                <a class="nav-link <?= ($current_page == 'admin_dashboard.php') ? 'active-link' : ''; ?>" href="admin_dashboard.php">
                    <i class="fa fa-gauge-high me-3"></i> <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($current_page == 'view_users.php') ? 'active-link' : ''; ?>" href="view_users.php">
                    <i class="fa fa-users me-3"></i> <span>View Users</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($current_page == 'manage_dept.php') ? 'active-link' : ''; ?>" href="manage_dept.php">
                    <i class="fa-solid fa-building me-3"></i> <span>Manage Department</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($current_page == 'manage_courses.php') ? 'active-link' : ''; ?>" href="manage_courses.php">
                    <i class="fa fa-book-open me-3 "></i> <span>Manage Course</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($current_page == 'ps_permit.php') ? 'active-link' : ''; ?>" href="ps_permit.php">
                    <i class="fa-solid fa-unlock me-3"></i> <span>Password Permit</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="modal fade" id="logoutConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <div class="modal-body text-center p-5">
                    <i class="fa-solid fa-circle-question text-primary fa-5x mb-4"></i>
                    <h2 class="fw-bold">Confirm Logout</h2>
                    <p class="text-muted fs-5">Are you sure you want to leave this page and sign out?</p>
                    <div class="d-flex justify-content-center gap-3 mt-4">
                        <button type="button" class="btn btn-light px-5 py-2 fw-semibold rounded-pill" data-bs-dismiss="modal">Cancel</button>
                        <a href="../logout.php" class="btn btn-primary px-5 py-2 fw-semibold rounded-pill">Yes, Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <div class="wrapper">
        <div class="container-fluid">
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const sidebarToggle = document.getElementById('sidebarToggle');
                    const body = document.body;

                    if (sidebarToggle) {
                        sidebarToggle.addEventListener('click', function() {
                            // This toggles the class your CSS is already looking for
                            body.classList.toggle('sidebar-toggled');
                        });
                    }
                });
            </script>