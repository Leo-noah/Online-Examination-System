<?php
session_start();
require '../db.php';

$error = "";

/* ➕ CREATE DEPARTMENT */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_department'], $_POST['role'])) {

    $newDept = trim($_POST['new_department']);
    $role = $_POST['role'];

    if ($newDept === '') {
        $error = "Department name cannot be empty.";
    } elseif (!in_array($role, ['teacher', 'student'])) {
        $error = "Invalid role selected.";
    } else {
        // Check if already exists
        $check = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE name = ? AND role = ?");
        $check->execute([$newDept, $role]);

        if ($check->fetchColumn() == 0) {
            $stmt = $pdo->prepare("INSERT INTO departments (role, name) VALUES (?, ?)");
            $stmt->execute([$role, $newDept]);
            header("Location: manage_dept.php?created=1");
            exit;
        } else {
            $error = "Department already exists for this role.";
        }
    }
}

/* 🗑️ DELETE DEPARTMENT */
if (isset($_GET['delete'], $_GET['role'])) {
    $deptToDelete = $_GET['delete'];
    $role = $_GET['role'];

    $stmt = $pdo->prepare("DELETE FROM departments WHERE name = ? AND role = ?");
    $stmt->execute([$deptToDelete, $role]);

    // Optional: Remove department from users table if assigned
    $updateUser = $pdo->prepare("UPDATE users SET department = NULL WHERE department = ? AND role = ?");
    $updateUser->execute([$deptToDelete, $role]);

    header("Location: manage_dept.php?deleted=1");
    exit;
}

/* 🔍 SEARCH */
$search = $_GET['search'] ?? '';
$searchRole = $_GET['search_role'] ?? '';

$query = "SELECT * FROM departments WHERE 1=1";
$params = [];

if ($searchRole && in_array($searchRole, ['teacher','student'])) {
    $query .= " AND role = ?";
    $params[] = $searchRole;
}

if ($search !== '') {
    $query .= " AND name LIKE ?";
    $params[] = "%$search%";
}

$query .= " ORDER BY role ASC, name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Departments</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>

<?php include 'header.php'; ?>

<div class="container py-4" style="max-width:900px;">

    <h2 class="fw-bold text-center mb-4">Department Management</h2>

    <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success text-center">Department created successfully</div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-warning text-center">Department deleted successfully</div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger text-center"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- 🧩 PART 1 — CREATE + SEARCH -->
        <div class="col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">

                    <h5 class="fw-bold mb-3 text-decoration-underline">Department Tools</h5>

                    <!-- CREATE -->
                    <form method="post" class="mb-3">
                        <label class="form-label">Create New Department</label>
                        <div class="d-flex gap-2">
                            <input type="text" name="new_department" class="form-control" placeholder="Department name">

                            <select name="role" class="form-select" style="width:150px;">
                                <option value="teacher">Teacher</option>
                                <option value="student">Student</option>
                            </select>

                            <button class="btn btn-success">Create</button>
                        </div>
                    </form>


                    <!-- SEARCH -->
                    <form method="get">
                        <label class="form-label">Search Department</label>
                        <div class="d-flex gap-2">
                            <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($search); ?>">
                            <button class="btn btn-primary">Search</button>
                        </div>
                    </form>

                </div>
            </div>
        </div>

        <!-- 🧩 PART 2 — ASSIGN -->
        <div class="col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex flex-column justify-content-center text-center">

                    <h5 class="fw-bold mb-3 text-decoration-underline">Assign Department</h5>
                    <p class="text-muted mb-4">
                        You may assign teachers and students to <br> their respective departments.
                    </p>

                    <a href="manage_users.php" class="btn btn-warning">
                        Go to Manage Users
                    </a>

                </div>
            </div>
        </div>

    </div>

    <!-- 📋 RESULT LIST -->
    <div class="card mt-4 shadow-sm">
        <div class="card-body">

            <h5 class="fw-bold mb-3 text-decoration-underline">Existing Departments</h5>

            <?php if ($departments): ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($departments as $d): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?= htmlspecialchars($d['name']) . " (" . htmlspecialchars($d['role']) . ")"; ?>

                        <a 
                            href="manage_dept.php?delete=<?= urlencode($d['name']); ?>&role=<?= urlencode($d['role']); ?>" 
                            class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Delete this department and remove it from users?');"
                        >
                            Delete
                        </a>
                    </li>
                    <?php endforeach; ?>

                </ul>
            <?php else: ?>
                <p class="text-muted mb-0">No departments found.</p>
            <?php endif; ?>

        </div>
    </div>

</div>

</body>
</html>
