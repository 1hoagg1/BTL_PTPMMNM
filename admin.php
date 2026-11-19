<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/models/UserModel.php';
require_once __DIR__ . '/models/ProjectModel.php';
require_once __DIR__ . '/models/TaskModel.php';
require_once __DIR__ . '/models/IssueModel.php';
require_once __DIR__ . '/models/ReleaseModel.php';
require_once __DIR__ . '/models/ActivityModel.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/utils.php';

startSessionSafe();
$pdo = getPdo();
initDatabase($pdo);

// Yêu cầu đăng nhập
requireLogin();

// Kiểm tra quyền: Chỉ Quản trị viên (Admin) mới có quyền truy cập
$currentUser = currentUser($pdo);
$currentUserRole = $_SESSION['current_user_role'] ?? $currentUser['role'] ?? 'member';

if ($currentUserRole !== 'admin') {
    $_SESSION['flash_error'] = 'Chỉ Quản trị viên (Admin) mới có quyền truy cập trang quản trị.';
    header('Location: dashboard.php');
    exit;
}

$userModel = new UserModel($pdo);
$projectModel = new ProjectModel($pdo);
$taskModel = new TaskModel($pdo);
$issueModel = new IssueModel($pdo);
$releaseModel = new ReleaseModel($pdo);
$activityModel = new ActivityModel($pdo);

$successMessage = '';
$errorMessage = '';

// Xử lý các thao tác quản trị
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // 1. Thêm tài khoản mới từ Admin
        if ($action === 'create_user') {
            $name = trim((string)($_POST['name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $role = $_POST['role'] ?? 'member';

            if ($name === '' || $email === '' || $username === '' || $password === '') {
                throw new InvalidArgumentException('Vui lòng điền đầy đủ các trường thông tin.');
            }

            $existing = $userModel->findByUsername($username);
            if ($existing) {
                throw new RuntimeException('Tên đăng nhập hoặc email này đã tồn tại.');
            }

            $userModel->create($name, $email, $username, $password, $role);
            $successMessage = 'Tạo tài khoản người dùng thành công!';
        }

        // 2. Cập nhật vai trò / thông tin tài khoản
        if ($action === 'update_user_role') {
            $targetUserId = $_POST['user_id'] ?? '';
            $newRole = $_POST['new_role'] ?? 'member';

            if ($targetUserId === '') {
                throw new InvalidArgumentException('ID tài khoản không hợp lệ.');
            }

            // Cập nhật bảng users
            $userModel->update($targetUserId, ['role' => $newRole]);

            // Đồng bộ cập nhật bảng members nếu có
            $updMember = $pdo->prepare('UPDATE members SET role = ? WHERE id = ? OR email = (SELECT email FROM users WHERE id = ?)');
            $updMember->execute([$newRole, $targetUserId, $targetUserId]);

            $successMessage = 'Cập nhật vai trò tài khoản thành công!';
        }

        // 3. Đặt lại mật khẩu tài khoản
        if ($action === 'reset_password') {
            $targetUserId = $_POST['user_id'] ?? '';
            $newPassword = trim((string)($_POST['new_password'] ?? ''));

            if ($targetUserId === '' || $newPassword === '') {
                throw new InvalidArgumentException('Vui lòng nhập mật khẩu mới.');
            }

            $userModel->update($targetUserId, ['password' => $newPassword]);
            $successMessage = 'Đặt lại mật khẩu cho tài khoản thành công!';
        }

        // 4. Xóa tài khoản
        if ($action === 'delete_user') {
            $targetUserId = $_POST['user_id'] ?? '';

            if ($targetUserId === ($currentUser['id'] ?? '')) {
                throw new RuntimeException('Không thể tự xóa tài khoản của chính mình.');
            }

            // Lấy email để đồng bộ xóa ở bảng members
            $targetUser = $userModel->getById($targetUserId);
            if ($targetUser) {
                $delMember = $pdo->prepare('DELETE FROM members WHERE id = ? OR email = ?');
                $delMember->execute([$targetUserId, $targetUser['email']]);
            }

            $userModel->delete($targetUserId);
            $successMessage = 'Xóa tài khoản người dùng thành công!';
        }

        // 5. Tối ưu hóa cơ sở dữ liệu (VACUUM SQLite)
        if ($action === 'vacuum_db') {
            $pdo->exec('VACUUM');
            $successMessage = 'Đã tối ưu hóa và chống phân mảnh cơ sở dữ liệu SQLite thành công!';
        }

        // 6. Dọn dẹp nhật ký hệ thống cũ
        if ($action === 'clear_old_logs') {
            $pdo->exec('DELETE FROM activity_log WHERE id NOT IN (SELECT id FROM activity_log ORDER BY id DESC LIMIT 50)');
            $successMessage = 'Đã dọn dẹp và giữ lại 50 nhật ký gần nhất!';
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

// Truy vấn dữ liệu thống kê quản trị
$allUsers = $userModel->getAll();
$allProjects = $projectModel->getAll();
$allTasks = $taskModel->getAll();
$allIssues = $issueModel->getAll();
$allReleases = $releaseModel->getAll();
$recentActivities = $activityModel->getRecent(50);

// Thống kê người dùng theo vai trò
$roleCounts = ['admin' => 0, 'manager' => 0, 'member' => 0, 'viewer' => 0];
foreach ($allUsers as $u) {
    $r = $u['role'] ?? 'member';
    if (isset($roleCounts[$r])) {
        $roleCounts[$r]++;
    }
}

// Thông tin tệp SQLite
$dbFile = __DIR__ . '/data/devtrack.sqlite';
$dbSizeFormatted = file_exists($dbFile) ? round(filesize($dbFile) / 1024, 2) . ' KB' : 'N/A';
$sqliteVersion = $pdo->query('SELECT sqlite_version()')->fetchColumn();

$roles = [
    'admin' => 'Quản trị viên',
    'manager' => 'Quản lý dự án',
    'member' => 'Thành viên',
    'viewer' => 'Người xem'
];

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang Quản trị Hệ thống | DevTrack Admin</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .admin-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
        }
        .admin-badge-admin { background: #fee2e2; color: #b91c1c; }
        .admin-badge-manager { background: #fef3c7; color: #b45309; }
        .admin-badge-member { background: #e0e7ff; color: #4338ca; }
        .admin-badge-viewer { background: #f1f5f9; color: #475569; }
        .admin-grid-2 {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        @media (max-width: 992px) {
            .admin-grid-2 { grid-template-columns: 1fr; }
        }
        .maintenance-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px;
            margin-top: 12px;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="dashboard.php">
            <span class="brand-mark"><span></span><span></span><span></span></span>
            <span>dev<span class="brand-light">track</span></span>
        </a>
        <div class="workspace-label">TRANG QUẢN TRỊ</div>
        <div style="padding: 10px 14px; background: rgba(99, 102, 241, 0.1); border-radius: 8px; margin: 0 14px 14px; color: #4338ca; font-size: 12px; font-weight: 600;">
            👑 Quyền Quản trị viên (Admin)
        </div>

        <div class="nav-label">ĐIỀU HƯỚNG</div>
        <nav class="main-nav">
            <a class="nav-item" href="dashboard.php"><span class="nav-icon">◫</span><span>Về Dashboard</span></a>
            <a class="nav-item active" href="admin.php"><span class="nav-icon">⚙️</span><span>Quản trị tài khoản</span></a>
            <a class="nav-item" href="#audit-log"><span class="nav-icon">✦</span><span>Nhật ký hệ thống</span></a>
            <a class="nav-item" href="#maintenance"><span class="nav-icon">💾</span><span>Bảo trì CSDL</span></a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumbs">
                <span>Hệ thống</span><span class="breadcrumb-slash">/</span><strong>Quản trị cấp cao (Admin)</strong>
            </div>
            <div class="topbar-actions">
                <a href="dashboard.php" class="secondary-button" style="text-decoration: none;">← Về Bảng điều khiển</a>
                <a href="logout.php" class="secondary-button">Đăng xuất</a>
                <span class="today-label"><span class="calendar-icon">📅</span><?= e(date('d/m/Y')) ?></span>
            </div>
        </header>

        <div class="page-content">
            <?php if (!empty($successMessage)): ?>
                <div class="flash success" style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
                    ✅ <?= e($successMessage) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errorMessage)): ?>
                <div class="flash error" style="margin-bottom: 16px;"><?= e($errorMessage) ?></div>
            <?php endif; ?>

            <section class="welcome-row">
                <div>
                    <div class="eyebrow"><span class="live-dot"></span> KHÔNG GIAN QUẢN TRỊ VIÊN</div>
                    <h1>Trung tâm Quản trị DevTrack</h1>
                    <p class="welcome-subtitle">Quản lý toàn diện tài khoản, phân quyền RBAC, kiểm toán hệ thống và bảo trì cơ sở dữ liệu.</p>
                </div>
                <div>
                    <a href="dashboard.php" class="primary-button" style="text-decoration: none;">Vào làm việc dự án ↗</a>
                </div>
            </section>

            <!-- Thống kê tổng quan hệ thống -->
            <section class="stats-grid">
                <article class="stat-card">
                    <div class="stat-top"><span class="stat-label">Tổng người dùng</span><span class="stat-icon icon-violet">👥</span></div>
                    <div class="stat-value"><?= count($allUsers) ?></div>
                    <div class="stat-foot"><span class="stat-note"><?= $roleCounts['admin'] ?> Admin, <?= $roleCounts['manager'] ?> Manager</span></div>
                </article>
                <article class="stat-card">
                    <div class="stat-top"><span class="stat-label">Tổng dự án</span><span class="stat-icon icon-orange">⌘</span></div>
                    <div class="stat-value"><?= count($allProjects) ?></div>
                    <div class="stat-foot"><span class="stat-note">Đang hoạt động trong CSDL</span></div>
                </article>
                <article class="stat-card">
                    <div class="stat-top"><span class="stat-label">Tổng công việc (Tasks)</span><span class="stat-icon icon-cyan">▤</span></div>
                    <div class="stat-value"><?= count($allTasks) ?></div>
                    <div class="stat-foot"><span class="stat-note">Kế hoạch phát triển</span></div>
                </article>
                <article class="stat-card">
                    <div class="stat-top"><span class="stat-label">Lỗi (Issues / Bugs)</span><span class="stat-icon icon-violet">◈</span></div>
                    <div class="stat-value"><?= count($allIssues) ?></div>
                    <div class="stat-foot"><span class="stat-note"><?= count($allReleases) ?> bản phát hành</span></div>
                </article>
            </section>

            <div class="admin-grid-2">
                <!-- Bảng danh sách người dùng -->
                <article class="panel">
                    <div class="panel-header">
                        <div>
                            <div class="section-title"><span class="section-icon member-section-icon">👥</span><h2>Quản lý tài khoản người dùng</h2></div>
                            <p class="panel-subtitle">Danh sách người dùng và phân quyền trong hệ thống</p>
                        </div>
                    </div>

                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>Tài khoản</th>
                            <th>Email</th>
                            <th>Vai trò</th>
                            <th>Hành động</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($allUsers as $u): ?>
                            <tr>
                                <td>
                                    <strong><?= e($u['name']) ?></strong>
                                    <div style="font-size: 11px; color: #64748b;">@<?= e($u['username']) ?></div>
                                </td>
                                <td><?= e($u['email']) ?></td>
                                <td>
                                    <form method="post" style="display: inline-block;">
                                        <input type="hidden" name="action" value="update_user_role">
                                        <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
                                        <select name="new_role" onchange="this.form.submit()" style="padding: 4px 8px; font-size: 12px; border-radius: 6px; border: 1px solid #cbd5e1;">
                                            <?php foreach ($roles as $val => $lbl): ?>
                                                <option value="<?= e($val) ?>" <?= ($u['role'] === $val) ? 'selected' : '' ?>><?= e($lbl) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <details style="display: inline-block; margin-right: 6px;">
                                        <summary style="font-size: 12px; cursor: pointer; color: #4338ca;">Đổi mật khẩu</summary>
                                        <form method="post" style="margin-top: 6px; background: #fff; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; position: absolute; z-index: 10; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
                                            <input type="password" name="new_password" placeholder="Mật khẩu mới" required style="padding: 4px 6px; font-size: 12px; width: 140px; margin-bottom: 4px; display: block;">
                                            <button type="submit" class="primary-button" style="padding: 3px 8px; font-size: 11px;">Lưu</button>
                                        </form>
                                    </details>

                                    <?php if ($u['id'] !== ($currentUser['id'] ?? '')): ?>
                                        <form method="post" class="inline-form" onsubmit="return confirm('Bạn có chắc muốn xóa tài khoản này?');">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
                                            <button type="submit" class="danger-button" style="padding: 4px 8px; font-size: 11px;">Xóa</button>
                                        </form>
                                    <?php else: ?>
                                        <small style="color: #64748b; font-style: italic;">(Đang đăng nhập)</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </article>

                <!-- Thêm tài khoản mới & Bảo trì -->
                <div>
                    <article class="panel">
                        <div class="panel-header">
                            <div>
                                <div class="section-title"><span class="section-icon project-section-icon">➕</span><h2>Tạo tài khoản mới</h2></div>
                                <p class="panel-subtitle">Cấp tài khoản trực tiếp từ trang Admin</p>
                            </div>
                        </div>

                        <form method="post" class="compact-form">
                            <input type="hidden" name="action" value="create_user">
                            <div class="form-grid">
                                <input type="text" name="name" placeholder="Họ và tên" required>
                            </div>
                            <div class="form-grid">
                                <input type="email" name="email" placeholder="Địa chỉ email" required>
                            </div>
                            <div class="form-grid">
                                <input type="text" name="username" placeholder="Tên đăng nhập" required>
                                <input type="password" name="password" placeholder="Mật khẩu" required>
                            </div>
                            <div class="form-grid">
                                <select name="role">
                                    <?php foreach ($roles as $val => $lbl): ?>
                                        <option value="<?= e($val) ?>"><?= e($lbl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="primary-button" style="width: 100%;">Tạo tài khoản ngay</button>
                            </div>
                        </form>
                    </article>

                    <!-- Bảo trì CSDL -->
                    <article class="panel" id="maintenance" style="margin-top: 20px;">
                        <div class="panel-header">
                            <div>
                                <div class="section-title"><span class="section-icon member-section-icon">💾</span><h2>Tình trạng CSDL</h2></div>
                                <p class="panel-subtitle">Thông tin SQLite & Tối ưu hóa</p>
                            </div>
                        </div>

                        <div class="maintenance-box">
                            <div style="font-size: 13px; margin-bottom: 6px;"><strong>Loại CSDL:</strong> SQLite <?= e($sqliteVersion) ?></div>
                            <div style="font-size: 13px; margin-bottom: 6px;"><strong>Dung lượng tệp:</strong> <?= e($dbSizeFormatted) ?></div>
                            <div style="font-size: 13px; margin-bottom: 12px;"><strong>Chế độ ghi:</strong> WAL Mode (Tối ưu đồng thời)</div>

                            <form method="post" style="margin-bottom: 8px;">
                                <input type="hidden" name="action" value="vacuum_db">
                                <button type="submit" class="secondary-button" style="width: 100%; font-size: 12px; padding: 8px;">🧹 Tối ưu hóa CSDL (VACUUM)</button>
                            </form>

                            <form method="post" onsubmit="return confirm('Chỉ giữ lại 50 nhật ký gần nhất và xóa các bản ghi cũ?');">
                                <input type="hidden" name="action" value="clear_old_logs">
                                <button type="submit" class="secondary-button" style="width: 100%; font-size: 12px; padding: 8px; color: #b45309;">🗑 Dọn dẹp bớt nhật ký cũ</button>
                            </form>
                        </div>
                    </article>
                </div>
            </div>

            <!-- Toàn bộ nhật ký kiểm toán hệ thống -->
            <section class="panel" id="audit-log" style="margin-top: 20px;">
                <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                    <div>
                        <div class="section-title"><span class="section-icon member-section-icon">✦</span><h2>Nhật ký kiểm toán hệ thống (Audit Trail)</h2></div>
                        <p class="panel-subtitle">Ghi vết toàn bộ hành vi thêm, sửa, xóa tài nguyên của các thành viên</p>
                    </div>
                    <span style="font-size: 12px; color: #64748b;"><?= count($recentActivities) ?> thao tác gần nhất</span>
                </div>

                <ul class="activity-list" style="max-height: 350px; overflow-y: auto;">
                    <?php if (empty($recentActivities)): ?>
                        <li style="color: #64748b; text-align: center;">Chưa có hoạt động nào được ghi nhận.</li>
                    <?php else: ?>
                        <?php foreach ($recentActivities as $item): ?>
                            <li>
                                <strong>[<?= e(strtoupper($item['entity'])) ?>]</strong> 
                                <span><?= e($item['action']) ?></span> - 
                                <span><?= e($item['message'] ?? '') ?></span>
                                <span style="float: right; color: #94a3b8; font-size: 11px;"><?= e($item['created_at']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </section>
        </div>
    </main>
</div>
</body>
</html>

