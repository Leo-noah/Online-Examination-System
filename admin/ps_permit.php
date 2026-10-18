<?php
session_start();
require '../db.php';

/* =========================
    SECURITY CHECK
========================= */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

/* =========================
    HANDLE APPROVE / REJECT / MESSAGE
========================= */
if (isset($_GET['action'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];

    if (in_array($action, ['approve', 'reject', 'message'])) {

        // 1. Fetch data first to check request_type
        $stmt_check = $pdo->prepare("SELECT user_id, request_type FROM password_permissions WHERE id = ?");
        $stmt_check->execute([$id]);
        $request_data = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($request_data) {
            // 2. Set the database status based on action
            $status = ($action === 'reject') ? 'rejected' : (($action === 'message') ? 'messaged' : 'approved');

            $stmt = $pdo->prepare("UPDATE password_permissions SET status = ?, approved_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $id]);

            // 3. UNLOCK LOGIC
            // Only resets login attempts if it's a login failure request and action was 'approve'
            if ($status === 'approved' && $request_data['request_type'] === 'login_reset') {
                $pdo->prepare("UPDATE users SET login_attempts = 0 WHERE id = ?")
                    ->execute([$request_data['user_id']]);
            }

            header("Location: ps_permit.php?msg=$status");
            exit();
        }
    }
}
/* =========================
    FETCH PENDING REQUESTS (1. Password Change)
========================= */
$stmt_change = $pdo->prepare("
    SELECT 
        pp.id,
        COALESCE(u.username, pp.identity) AS display_name,
        COALESCE(u.email, pp.identity) AS display_email,
        pp.role,
        pp.status,
        pp.requested_at
    FROM password_permissions pp
    LEFT JOIN users u ON pp.user_id = u.id
    WHERE pp.status = 'pending' 
    AND (pp.request_type = 'change' OR pp.request_type IS NULL)
    ORDER BY pp.requested_at DESC
");
$stmt_change->execute();
$change_requests = $stmt_change->fetchAll(PDO::FETCH_ASSOC);

/* =========================
    FETCH REQUESTS (2. Login Failure Reset)
========================= */
$stmt_fail = $pdo->prepare("
    SELECT 
        pp.id,
        COALESCE(u.username, pp.identity) AS display_name,
        COALESCE(u.email, pp.identity) AS display_email,
        pp.role,
        pp.status,
        pp.requested_at
    FROM password_permissions pp
    LEFT JOIN users u ON pp.user_id = u.id
    WHERE (pp.status = 'pending' OR pp.status = 'messaged') 
    AND pp.request_type = 'login_reset'
    ORDER BY pp.requested_at DESC
");
$stmt_fail->execute();
$fail_requests = $stmt_fail->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Password Permission | Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .card-box {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .05);
            padding: 20px;
        }

        .section-title {
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6c757d;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 10px;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold">
                <i class="fa fa-key me-2 text-primary"></i>Password & Login Permissions
            </h3>
            <a href="admin_dashboard.php" class="btn btn-outline-secondary">
                <i class="fa fa-arrow-left me-2"></i>Back
            </a>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Permission <strong><?= htmlspecialchars($_GET['msg']); ?></strong> successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="section-title mt-2">
            <i class="fa fa-user-edit text-primary"></i> Profile Password Change Requests
        </div>
        <div class="card-box mb-5">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>User / Identity</th>
                        <th>Email / Reference</th>
                        <th>Role</th>
                        <th>Requested At</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($change_requests)): ?>
                        <?php foreach ($change_requests as $r): ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($r['display_name']); ?></td>
                                <td class="text-muted"><?= htmlspecialchars($r['display_email']); ?></td>
                                <td>
                                    <span class="badge bg-info text-dark">
                                        <?= htmlspecialchars($r['role'] ?: 'Guest'); ?>
                                    </span>
                                </td>
                                <td><?= date('d M Y, h:i A', strtotime($r['requested_at'])); ?></td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <a href="ps_permit.php?action=approve&id=<?= $r['id']; ?>"
                                            class="btn btn-success btn-sm"
                                            onclick="return confirm('Approve for <?= htmlspecialchars($r['display_name']); ?>?')">
                                            <i class="fa fa-check"></i> Approve
                                        </a>
                                        <a href="ps_permit.php?action=reject&id=<?= $r['id']; ?>"
                                            class="btn btn-danger btn-sm"
                                            onclick="return confirm('Reject request?')">
                                            <i class="fa fa-xmark"></i> Reject
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                No pending profile change requests.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="section-title">
            <i class="fa fa-user-lock text-danger"></i> Login Failure Reset Requests
        </div>
        <div class="card-box">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>User / Identity</th>
                        <th>Email / Reference</th>
                        <th>Role</th>
                        <th>Requested At</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($fail_requests)): ?>
                        <?php foreach ($fail_requests as $r): ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($r['display_name']); ?></td>
                                <td class="text-muted"><?= htmlspecialchars($r['display_email']); ?></td>
                                <td>
                                    <span class="badge bg-warning text-dark">
                                        <?= htmlspecialchars($r['role'] ?: 'Guest'); ?>
                                    </span>
                                </td>
                                <td><?= date('d M Y, h:i A', strtotime($r['requested_at'])); ?></td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <a href="ps_permit.php?action=message&id=<?= $r['id']; ?>"
                                            class="btn btn-info btn-sm text-white"
                                            onclick="return confirm('Send notification to <?= htmlspecialchars($r['display_name']); ?>?')">
                                            <i class="fa-solid fa-paper-plane"></i> Message
                                        </a>

                                        <a href="ps_permit.php?action=approve&id=<?= $r['id']; ?>"
                                            class="btn btn-success btn-sm"
                                            onclick="return confirm('Unlock account and reset login attempts for <?= htmlspecialchars($r['display_name']); ?>?')">
                                            <i class="fa-solid fa-unlock"></i> Unlock
                                        </a>

                                        <a href="ps_permit.php?action=reject&id=<?= $r['id']; ?>"
                                            class="btn btn-outline-danger btn-sm"
                                            onclick="return confirm('Dismiss request?')">
                                            <i class="fa fa-trash"></i> Dismiss
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                No pending login reset requests.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>