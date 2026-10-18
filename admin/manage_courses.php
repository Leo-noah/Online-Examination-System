<?php
session_start();
require '../db.php';

// --- HANDLE UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_course'])) {
    $id = $_POST['course_id'];
    $code = trim($_POST['course_code']);
    $name = trim($_POST['course_name']);
    $dept = $_POST['dept_name'] ?? ''; // Fix: Handle missing key
    $sem  = (int)$_POST['semester'];
    $major = ($sem >= 7) ? ($_POST['major_name'] ?? 'Core') : 'Core';

    if (!empty($dept)) {
        $stmt = $pdo->prepare("UPDATE courses SET course_code = ?, course_name = ?, department_name = ?, semester = ?, major_name = ? WHERE id = ?");
        $stmt->execute([$code, $name, $dept, $sem, $major, $id]);
        header("Location: manage_courses.php?updated=1");
        exit;
    }
}

// --- HANDLE CREATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
    $code = trim($_POST['course_code']);
    $name = trim($_POST['course_name']);
    $dept = $_POST['dept_name'] ?? ''; // Fix: Handle missing key
    $sem  = (int)$_POST['semester'];
    $major = ($sem >= 7) ? ($_POST['major_name'] ?? 'Core') : 'Core';

    // Fix: Prevent database crash if department is not selected
    if (!empty($dept)) {
        $stmt = $pdo->prepare("INSERT INTO courses (course_code, course_name, department_name, semester, major_name) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$code, $name, $dept, $sem, $major]);
        header("Location: manage_courses.php?success=1");
        exit;
    } else {
        $error = "Please select a faculty/department.";
    }
}

// --- HANDLE DELETE ---
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: manage_courses.php?deleted=1");
    exit;
}

// Fetch departments for the dropdown
$depts = $pdo->query("SELECT name FROM departments")->fetchAll();
$courses = $pdo->query("SELECT * FROM courses ORDER BY semester ASC, major_name ASC, course_code ASC")->fetchAll();

include 'header.php';
?>

<div id="blur-me">
    <div class="container-fluid">
        <h2 class="fw-bold text-dark mb-4"><i class="fa fa-book-open me-2 text-primary"></i>Course Management</h2>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger shadow-sm"><?= $error ?></div>
        <?php endif; ?>

        <div class="card mb-4 shadow-sm border-0" style="border-radius: 12px;">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3">Add New Course</h5>
                <form method="POST" class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="small fw-bold text-muted text-uppercase">Course Code</label>
                        <input type="text" name="course_code" class="form-control" placeholder="CST-4104" required>
                    </div>
                    <div class="col-md-3">
                        <label class="small fw-bold text-muted text-uppercase">Course Title</label>
                        <input type="text" name="course_name" class="form-control" placeholder="e.g. Software Engineering" required>
                    </div>
                    <div class="col-md-2">
                        <label class="small fw-bold text-muted text-uppercase">Semester</label>
                        <select name="semester" id="add_sem" class="form-select" onchange="checkMajor(this.value, 'add_major_div')">
                            <?php for ($i = 1; $i <= 10; $i++) echo "<option value='$i'>Semester $i</option>"; ?>
                        </select>
                    </div>
                    <div class="col-md-2" id="add_major_div" style="display:none;">
                        <label class="small fw-bold text-primary text-uppercase">Specialization</label>
                        <select name="major_name" class="form-select border-primary shadow-sm">
                            <option value="Core">Core Subject</option>
                            <option value="SE">SE</option>
                            <option value="BIS">BIS</option>
                            <option value="HPC">HPC</option>
                            <option value="KE">KE</option>
                            <option value="CN">CN</option>
                            <option value="ES">ES</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="small fw-bold text-muted text-uppercase">Faculty</label>
                        <select name="dept_name" class="form-select" required>
                            <option value="" disabled selected>Select Faculty</option>
                            <?php foreach ($depts as $d): ?>
                                <option value="<?= htmlspecialchars($d['name']) ?>"><?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button name="add_course" class="btn btn-primary w-100 fw-bold">Add</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0" style="border-radius: 12px;">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted">
                        <tr>
                            <th class="ps-4">Semester</th>
                            <th>Code</th>
                            <th>Course Name</th>
                            <th>Major</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $c): ?>
                            <tr>
                                <td class="ps-4"><span class="badge rounded-pill bg-light text-primary border">Sem <?= $c['semester'] ?></span></td>
                                <td class="fw-bold"><?= htmlspecialchars($c['course_code']) ?></td>
                                <td><?= htmlspecialchars($c['course_name']) ?></td>
                                <td>
                                    <?php if ($c['major_name'] !== 'Core'): ?>
                                        <span class="badge bg-primary px-3 shadow-sm"><?= htmlspecialchars($c['major_name']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">General Core</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-warning text-white shadow-sm" onclick='triggerEdit(<?= json_encode($c) ?>)'>
                                        <i class="fa fa-edit"></i>
                                    </button>
                                    <a href="?delete=<?= $c['id'] ?>" class="btn btn-sm btn-danger shadow-sm" onclick="return confirm('Delete course?')">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">Update Course Information</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="course_id" id="e_id">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">COURSE CODE</label>
                        <input type="text" name="course_code" id="e_code" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">COURSE NAME</label>
                        <input type="text" name="course_name" id="e_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">FACULTY</label>
                        <select name="dept_name" id="e_dept" class="form-select" required>
                            <?php foreach ($depts as $d): ?>
                                <option value="<?= htmlspecialchars($d['name']) ?>"><?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">SEMESTER</label>
                            <select name="semester" id="e_sem" class="form-select" onchange="checkMajor(this.value, 'edit_major_div')">
                                <?php for ($i = 1; $i <= 10; $i++) echo "<option value='$i'>Semester $i</option>"; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3" id="edit_major_div">
                            <label class="form-label small fw-bold text-primary">MAJOR</label>
                            <select name="major_name" id="e_major" class="form-select border-primary shadow-sm">
                                <option value="Core">Core Subject</option>
                                <option value="SE">SE</option>
                                <option value="BIS">BIS</option>
                                <option value="HPC">HPC</option>
                                <option value="KE">KE</option>
                                <option value="CN">CN</option>
                                <option value="ES">ES</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="update_course" class="btn btn-primary px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    var editModalObj = new bootstrap.Modal(document.getElementById('editModal'));

    function checkMajor(sem, targetId) {
        const div = document.getElementById(targetId);
        div.style.display = (sem >= 7) ? 'block' : 'none';
    }

    function triggerEdit(data) {
        document.getElementById('e_id').value = data.id;
        document.getElementById('e_code').value = data.course_code;
        document.getElementById('e_name').value = data.course_name;
        document.getElementById('e_dept').value = data.department_name; // Set dept in modal
        document.getElementById('e_sem').value = data.semester;
        document.getElementById('e_major').value = data.major_name;

        checkMajor(data.semester, 'edit_major_div');
        editModalObj.show();
    }
</script>