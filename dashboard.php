<?php
require_once __DIR__ . '/db.php';
startSessionSafe();
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/ProjectController.php';
require_once __DIR__ . '/controllers/MemberController.php';
require_once __DIR__ . '/controllers/TaskController.php';
require_once __DIR__ . '/controllers/IssueController.php';
require_once __DIR__ . '/controllers/ReleaseController.php';
require_once __DIR__ . '/controllers/ActivityController.php';
requireLogin();

$pdo = getPdo();

$authController = new AuthController($pdo);
$projectController = new ProjectController($pdo);
$memberController = new MemberController($pdo);
$taskController = new TaskController($pdo);
$issueController = new IssueController($pdo);
$releaseController = new ReleaseController($pdo);
$activityController = new ActivityController($pdo);

$roles = ['admin' => 'Quản trị viên', 'manager' => 'Quản lý dự án', 'member' => 'Thành viên', 'viewer' => 'Người xem'];
$priorities = ['urgent' => 'Khẩn cấp', 'high' => 'Cao', 'medium' => 'Trung bình', 'low' => 'Thấp'];
$statusOptions = ['open' => 'Mới', 'progress' => 'Đang xử lý', 'resolved' => 'Đã giải quyết'];
$taskStatusOptions = ['todo' => 'Cần làm', 'progress' => 'Đang làm', 'done' => 'Hoàn thành'];
$projectStatusOptions = ['active' => 'Đang hoạt động', 'paused' => 'Tạm dừng', 'completed' => 'Hoàn thành'];

if (isset($_GET['role'])) {
    $_SESSION['current_user_role'] = $_GET['role'];
}
if (isset($_POST['current_role'])) {
    $_SESSION['current_user_role'] = $_POST['current_role'];
}
$currentUser = currentUser($pdo) ?: ['role' => 'member'];
$currentUserRole = $_SESSION['current_user_role'] ?? $currentUser['role'] ?? 'member';

// Export release note text
if (isset($_GET['export_release'])) {
    $exportData = $releaseController->export($_GET['export_release']);
    if ($exportData) {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $exportData['filename'] . '"');
        echo $exportData['content'];
        exit;
    }
}

$errorMessage = '';
$selectedProjectId = $_GET['project_id'] ?? '';

// Handle POST actions using MVC Controllers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'delete_project') {
            $projectController->delete($_POST['id'] ?? '', $currentUserRole);
        }

        if ($action === 'delete_member') {
            $memberController->delete($_POST['id'] ?? '', $currentUserRole);
        }

        if ($action === 'delete_task') {
            $taskController->delete($_POST['id'] ?? '', $currentUserRole);
        }

        if ($action === 'delete_issue') {
            $issueController->delete($_POST['id'] ?? '', $currentUserRole);
        }

        if ($action === 'delete_release') {
            $releaseController->delete($_POST['id'] ?? '', $currentUserRole);
        }

        if ($action === 'add_project') {
            $newId = $projectController->create($_POST, $currentUserRole);
            $selectedProjectId = $newId;
        }

        if ($action === 'update_project') {
            $id = $_POST['id'] ?? '';
            $projectController->update($id, $_POST, $currentUserRole, $_POST['updated_at'] ?? null);
            $selectedProjectId = $id;
        }

        if ($action === 'add_member') {
            $newMemberId = $memberController->create($_POST, $currentUserRole);
            if (!empty($selectedProjectId)) {
                $projectController->addMember($selectedProjectId, $newMemberId, $currentUserRole);
            }
        }

        if ($action === 'remove_project_member') {
            $targetProjectId = $_POST['project_id'] ?? $selectedProjectId;
            $targetMemberId = $_POST['member_id'] ?? '';
            $projectController->removeMember($targetProjectId, $targetMemberId, $currentUserRole);
        }

        if ($action === 'update_member') {
            $memberController->update($_POST['id'] ?? '', $_POST, $currentUserRole, $_POST['updated_at'] ?? null);
        }

        if ($action === 'add_task') {
            $taskController->create($_POST, $currentUserRole);
        }

        if ($action === 'update_task') {
            $taskController->update($_POST['id'] ?? '', $_POST, $currentUserRole, $_POST['updated_at'] ?? null);
        }

        if ($action === 'add_issue') {
            $issueController->create($_POST, $currentUserRole);
        }

        if ($action === 'update_issue') {
            $issueController->update($_POST['id'] ?? '', $_POST, $currentUserRole, $_POST['updated_at'] ?? null);
        }

        if ($action === 'add_release') {
            $releaseController->create($_POST, $currentUserRole);
        }

        if ($action === 'update_release') {
            $releaseController->update($_POST['id'] ?? '', $_POST, $currentUserRole, $_POST['updated_at'] ?? null);
        }

        if ($action === 'save_conflict_demo') {
            ensureNoConflict($pdo, $_POST['table'] ?? '', $_POST['id'] ?? '', $_POST['updated_at'] ?? '');
        }

        $redirectProject = $selectedProjectId ?: ($_POST['project_id'] ?? '');
        header('Location: dashboard.php' . ($redirectProject !== '' ? '?project_id=' . rawurlencode($redirectProject) : ''));
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

// Fetch data via Controllers
$allProjects = $projectController->index();
$projectKeyword = trim((string)($_GET['project_search'] ?? ''));
$projectStatusFilter = trim((string)($_GET['project_status'] ?? ''));
$tableProjects = ($projectKeyword !== '' || $projectStatusFilter !== '') ? $projectController->index($projectKeyword, $projectStatusFilter) : $allProjects;
$projects = $allProjects;

$allMembers = $memberController->index();
$members = $allMembers;
$tasks = $taskController->index();
$issues = $issueController->index();
$releases = $releaseController->index();
$activityLog = $activityController->recent(10);

if ($selectedProjectId === '' && !empty($projects[0]['id'])) {
    $selectedProjectId = $projects[0]['id'];
}

// Thành viên riêng của dự án đang chọn
$projectMembers = $selectedProjectId !== '' ? $projectController->getMembers($selectedProjectId) : $allMembers;
$viewAllMembers = isset($_GET['view_all_members']) && $_GET['view_all_members'] == '1';
$displayMembers = $viewAllMembers ? $allMembers : $projectMembers;

$editProjectId = $_GET['edit_project'] ?? '';
$editMemberId = $_GET['edit_member'] ?? '';
$editTaskId = $_GET['edit_task'] ?? '';
$editIssueId = $_GET['edit_issue'] ?? '';
$editReleaseId = $_GET['edit_release'] ?? '';

function taskBlocked(string $taskId, array $taskRows): bool
{
    global $taskController;
    return $taskController->isBlocked($taskId, $taskRows);
}

$summary = $issueController->summary($selectedProjectId);
$cycles = $taskController->detectCycles();
$selectedProjectTasks = array_values(array_filter($tasks, fn ($task) => ($task['project_id'] ?? '') === $selectedProjectId));
$selectedProjectIssues = array_values(array_filter($issues, fn ($issue) => ($issue['project_id'] ?? '') === $selectedProjectId));
$filteredReleases = array_values(array_filter($releases, fn ($release) => ($release['project_id'] ?? '') === $selectedProjectId));

$projectCount = count($projects);
$memberCount = count($projectMembers);
$taskCompletion = $taskController->completion($selectedProjectId);

$activeProject = null;
foreach ($projects as $proj) {
    if ($proj['id'] === $selectedProjectId) {
        $activeProject = $proj;
        break;
    }
}
if (!$activeProject && !empty($projects[0])) {
    $activeProject = $projects[0];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevTrack - Theo dõi lỗi & phát hành</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="#"><span class="brand-mark"><span></span><span></span><span></span></span><span>dev<span class="brand-light">track</span></span></a>
        <div class="workspace-label">WORKSPACE</div>
        <button class="workspace-switcher">
            <span class="workspace-icon">P</span>
            <span class="workspace-copy"><strong>PTPM Project</strong><small>Không gian làm việc</small></span>
            <span class="chevron">⌄</span>
        </button>

        <div class="nav-label">MENU</div>
        <nav class="main-nav">
            <a class="nav-item active" href="#overview"><span class="nav-icon">◫</span><span>Tổng quan</span></a>
            <a class="nav-item" href="#issues"><span class="nav-icon">◈</span><span>Theo dõi lỗi</span></a>
            <a class="nav-item" href="#plan"><span class="nav-icon">◆</span><span>Kế hoạch phát triển</span></a>
            <a class="nav-item" href="#projects"><span class="nav-icon">⌘</span><span>Dự án</span></a>
            <a class="nav-item" href="#members"><span class="nav-icon">◆</span><span>Thành viên</span></a>
            <a class="nav-item" href="#release"><span class="nav-icon">⚿</span><span>Phát hành</span></a>
            <?php if ($currentUserRole === 'admin'): ?>
                <a class="nav-item" href="admin.php" style="color: #4338ca; font-weight: 700; background: #eef2ff;"><span class="nav-icon">⚙️</span><span>Trang Quản trị</span></a>
            <?php endif; ?>
        </nav>
    </aside>

    <main class="main-content" id="overview">
        <header class="topbar">
            <div class="breadcrumbs"><span>Workspace</span><span class="breadcrumb-slash">/</span><strong>Tổng quan</strong></div>
            <div class="topbar-actions">
                <?php if ($currentUserRole === 'admin'): ?>
                    <a href="admin.php" class="secondary-button" style="color: #4338ca; border-color: #c7d2fe; font-weight: 600; text-decoration: none;">⚙️ Quản trị Admin</a>
                <?php endif; ?>
                <form method="get" class="role-switcher-form">
                    <label>Vai trò:</label>
                    <select name="role" onchange="this.form.submit()">
                        <?php foreach ($roles as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $value === $currentUserRole ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <a href="logout.php" class="secondary-button">Đăng xuất</a>
                <span class="today-label"><span class="calendar-icon">📅</span><?= e(date('d/m/Y')) ?></span>
            </div>
        </header>

        <div class="page-content">
            <?php if (!empty($errorMessage)): ?>
                <div class="flash error"><?= e($errorMessage) ?></div>
            <?php endif; ?>

            <section class="welcome-row">
                <div>
                    <div class="eyebrow"><span class="live-dot"></span> BẢNG ĐIỀU KHIỂN DỰ ÁN</div>
                    <h1>Chào buổi sáng, <?= e($currentUser['name'] ?? 'Admin') ?> <span class="wave">✳</span></h1>
                    <p class="welcome-subtitle">Theo dõi lỗi, phân công xử lý và xác định phiên bản phát hành.</p>
                </div>
                <button class="primary-button" type="button" onclick="document.getElementById('issue-form').scrollIntoView({behavior:'smooth'})">+ Tạo lỗi mới</button>
            </section>

            <section class="project-banner">
                <div class="project-banner-content">
                    <div class="project-heading">
                        <span class="project-symbol">⌘</span>
                        <div>
                            <div class="project-title-line">
                                <h2><?= e($activeProject ? $activeProject['name'] : 'Dự án demo') ?></h2>
                                <span class="status-pill status-active"><span></span> <?= e($projectStatusOptions[$activeProject['status'] ?? 'active'] ?? 'Đang hoạt động') ?></span>
                            </div>
                            <p><?= e($activeProject ? ($activeProject['description'] ?? '') : 'Chưa có mô tả') ?></p>
                        </div>
                    </div>
                    <div class="project-meta">
                        <label class="project-picker"><span class="meta-icon">⌘</span>
                            <select id="active-project-picker" onchange="location.href='dashboard.php?project_id='+this.value">
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?= e($project['id']) ?>" <?= $project['id'] === $selectedProjectId ? 'selected' : '' ?>><?= e($project['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <span><span class="meta-icon">▦</span> <?= e($activeProject['start_date'] ?? 'N/A') ?> — <?= e($activeProject['end_date'] ?? 'N/A') ?></span>
                        <span><span class="meta-icon">♧</span> <?= e((string) $memberCount) ?> thành viên</span>
                    </div>
                </div>
            </section>

            <section class="stats-grid">
                <article class="stat-card">
                    <div class="stat-top"><span class="stat-label">Tổng số lỗi</span><span class="stat-icon icon-violet">◈</span></div>
                    <div class="stat-value" id="stat-total"><?= e((string) $summary['count']) ?></div>
                    <div class="stat-foot"><span class="stat-note">Các lỗi được ghi nhận</span><span class="stat-trend">↗ Theo dõi</span></div>
                </article>
                <article class="stat-card">
                    <div class="stat-top"><span class="stat-label">Lỗi đang xử lý</span><span class="stat-icon icon-orange">◷</span></div>
                    <div class="stat-value" id="stat-open"><?= e((string) $summary['open']) ?></div>
                    <div class="stat-foot"><span class="stat-note">Đang được đội ngũ xử lý</span><span class="stat-trend trend-orange">● Cần chú ý</span></div>
                </article>
                <article class="stat-card">
                    <div class="stat-top"><span class="stat-label">Công việc hoàn thành</span><span class="stat-icon icon-green">✓</span></div>
                    <div class="stat-value"><span><?= e((string) $taskCompletion) ?></span><small>%</small></div>
                    <div class="stat-foot"><span class="stat-note">Tiến độ kế hoạch</span><span class="stat-trend trend-green">↗ Tiến độ</span></div>
                </article>
                <article class="stat-card">
                    <div class="stat-top"><span class="stat-label">Ưu tiên cao</span><span class="stat-icon icon-red">⚠</span></div>
                    <div class="stat-value" id="stat-high"><?= e((string) $summary['high']) ?></div>
                    <div class="stat-foot"><span class="stat-note">Lỗi cần xử lý sớm</span><span class="stat-trend trend-red">● Ưu tiên</span></div>
                </article>
            </section>

            <section class="content-grid">
                <article class="panel" id="issues">
                    <div class="panel-header">
                        <div>
                            <div class="section-title"><span class="section-icon issue-section-icon">◈</span><h2>Theo dõi lỗi</h2></div>
                            <p class="panel-subtitle">Ghi nhận và theo dõi các lỗi trong dự án</p>
                        </div>
                    </div>
                    <div class="issue-detail-list">
                        <?php foreach ($selectedProjectIssues as $issue):
                            $assignee = array_values(array_filter($members, fn($m) => $m['id'] === $issue['assignee_id']))[0] ?? ['name' => 'Chưa phân công'];
                            $comments = issueCommentsList($issue['comments']);
                            ?>
                            <div class="issue-detail-card">
                                <div class="issue-detail-header">
                                    <div>
                                        <strong><?= e($issue['id']) ?></strong>
                                        <h3><?= e($issue['title']) ?></h3>
                                    </div>
                                    <div class="issue-detail-badges">
                                        <span class="priority-pill priority-<?= e($issue['priority']) ?>"><?= e($priorities[$issue['priority']] ?? ucfirst($issue['priority'])) ?></span>
                                        <span class="status-pill small status-<?= e($issue['status']) ?>"><?= e($statusOptions[$issue['status']] ?? ucfirst($issue['status'])) ?></span>
                                    </div>
                                </div>
                                <div class="issue-detail-grid">
                                    <div><label>Người xử lý</label><p><?= e($assignee['name']) ?></p></div>
                                    <div><label>Ngày</label><p><?= e($issue['date']) ?></p></div>
                                    <div><label>Phân công sửa</label><p><?= e($assignee['name']) ?></p></div>
                                </div>
                                <div class="issue-detail-section">
                                    <label>Bước tái hiện lỗi</label>
                                    <p><?= nl2br(e($issue['reproduction_steps'] ?? 'Chưa ghi nhận bước tái hiện.')) ?></p>
                                </div>
                                <div class="issue-detail-section">
                                    <label>Tóm tắt cách sửa</label>
                                    <p><?= nl2br(e($issue['fix_summary'] ?? 'Chưa có ghi chú sửa.')) ?></p>
                                </div>
                                <div class="issue-detail-section">
                                    <label>Kết quả kiểm tra sau sửa</label>
                                    <p><?= nl2br(e($issue['verification_result'] ?? 'Chưa có kết quả kiểm tra.')) ?></p>
                                </div>
                                <div class="issue-detail-section">
                                    <label>Bình luận</label>
                                    <?php if ($comments): ?>
                                        <ul class="comment-list">
                                            <?php foreach ($comments as $comment): ?>
                                                <li><strong><?= e($comment['author'] ?? 'Người dùng') ?></strong>: <?= e($comment['text'] ?? '') ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p>Chưa có bình luận.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="issue-card-actions">
                                    <a href="dashboard.php?edit_issue=<?= rawurlencode($issue['id']) ?>&project_id=<?= rawurlencode($selectedProjectId) ?>#issue-form">Sửa</a>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="current_role" value="<?= e($currentUserRole) ?>">
                                        <input type="hidden" name="action" value="delete_issue">
                                        <input type="hidden" name="id" value="<?= e($issue['id']) ?>">
                                        <button type="submit" class="danger-button">Xóa</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="panel" id="plan">
                    <div class="panel-header">
                        <div>
                            <div class="section-title"><span class="section-icon plan-section-icon">◆</span><h2>Kế hoạch phát triển</h2></div>
                            <p class="panel-subtitle">Phát hiện công việc đang chặn tiến độ và phụ thuộc lẫn nhau</p>
                        </div>
                    </div>
                    <?php $editingTask = $editTaskId ? fetchRow($pdo, 'tasks', $editTaskId) : null; ?>
                    <form method="post" class="compact-form">
                        <input type="hidden" name="current_role" value="<?= e($currentUserRole) ?>">
                        <input type="hidden" name="action" value="<?= $editingTask ? 'update_task' : 'add_task' ?>">
                        <input type="hidden" name="id" value="<?= e($editingTask['id'] ?? '') ?>">
                        <input type="hidden" name="updated_at" value="<?= e($editingTask['updated_at'] ?? '') ?>">
                        <div class="form-grid">
                            <input type="text" name="title" placeholder="Tên công việc" value="<?= e($editingTask['title'] ?? '') ?>" required>
                        </div>
                        <div class="form-grid">
                            <select name="project_id">
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?= e($project['id']) ?>" <?= ($editingTask['project_id'] ?? $selectedProjectId) === $project['id'] ? 'selected' : '' ?>><?= e($project['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="assignee_id">
                                <option value="">Chưa phân công</option>
                                <?php foreach ($projectMembers as $member): ?>
                                    <option value="<?= e($member['id']) ?>" <?= (($editingTask['assignee_id'] ?? '') === $member['id']) ? 'selected' : '' ?>><?= e($member['name']) ?> (<?= e($roles[$member['role']] ?? $member['role']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-grid">
                            <select name="priority">
                                <?php foreach ($priorities as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= ($editingTask['priority'] ?? 'medium') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="status">
                                <?php foreach ($taskStatusOptions as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= ($editingTask['status'] ?? 'todo') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="date" name="date" value="<?= e($editingTask['date'] ?? '') ?>">
                        </div>
                        <label>Khởi tạo công việc phụ thuộc</label>
                        <select name="depends_on[]" multiple size="5">
                            <?php $taskDeps = $editingTask ? json_decode($editingTask['depends_on'] ?? '[]', true) ?: [] : []; ?>
                            <?php foreach ($selectedProjectTasks as $task): ?>
                                <?php if (($editingTask['id'] ?? null) === $task['id']) { continue; } ?>
                                <option value="<?= e($task['id']) ?>" <?= in_array($task['id'], $taskDeps, true) ? 'selected' : '' ?>><?= e($task['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <textarea name="description" placeholder="Mô tả công việc"><?= e($editingTask['description'] ?? '') ?></textarea>
                        <div class="form-actions">
                            <button type="submit" class="primary-button"><?= $editingTask ? 'Lưu thay đổi' : 'Thêm công việc' ?></button>
                            <?php if ($editingTask): ?>
                                <a class="secondary-link" href="dashboard.php?project_id=<?= rawurlencode($selectedProjectId) ?>#projects">Hủy</a>
                            <?php endif; ?>
                        </div>
                    </form>
                    <div class="dependency-box">
                        <?php foreach ($selectedProjectTasks as $task): ?>
                            <?php $blocked = taskBlocked($task['id'], $selectedProjectTasks); ?>
                            <div class="roadmap-item <?= $task['status'] ?>">
                                <span class="roadmap-marker"><?= $task['status'] === 'done' ? '✓' : ($task['status'] === 'progress' ? '◷' : '·') ?></span>
                                <div class="roadmap-copy">
                                    <p class="roadmap-title"><?= e($task['title']) ?></p>
                                    <span class="roadmap-date"><?= e($task['date']) ?> · <?= e($priorities[$task['priority']] ?? ucfirst($task['priority'])) ?></span>
                                    <?php $deps = json_decode($task['depends_on'] ?? '[]', true) ?: []; ?>
                                    <?php if ($deps): ?>
                                        <div class="issue-meta"><span>Phụ thuộc:</span> <?= e(implode(', ', $deps)) ?></div>
                                    <?php endif; ?>
                                    <?php if ($blocked): ?>
                                        <div class="blocked-tag">Bị chặn bởi công việc đang thực hiện</div>
                                    <?php endif; ?>
                                </div>
                                <span class="task-state task-state-<?= e($task['status']) ?>"><?= e($taskStatusOptions[$task['status']] ?? ucfirst($task['status'])) ?></span>
                                <div class="issue-card-actions">
                                    <a href="dashboard.php?edit_task=<?= rawurlencode($task['id']) ?>&project_id=<?= rawurlencode($selectedProjectId) ?>#plan">Sửa</a>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="current_role" value="<?= e($currentUserRole) ?>">
                                        <input type="hidden" name="action" value="delete_task">
                                        <input type="hidden" name="id" value="<?= e($task['id']) ?>">
                                        <button type="submit" class="danger-button">Xóa</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($cycles): ?>
                        <div class="flash warning">
                            Phát hiện vòng phụ thuộc: <?= e(json_encode($cycles[0])) ?>
                        </div>
                    <?php endif; ?>
                </article>
            </section>

            <section class="secondary-grid">
                <article class="panel" id="projects">
                    <div class="panel-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <div class="section-title"><span class="section-icon project-section-icon">⌘</span><h2>Dự án</h2></div>
                            <p class="panel-subtitle">Quản lý dự án và thông tin giai đoạn phát triển (<?= count($tableProjects) ?> dự án)</p>
                        </div>
                    </div>

                    <!-- Thanh tìm kiếm & lọc dự án -->
                    <form method="get" class="project-search-bar" style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; align-items: center;">
                        <input type="hidden" name="project_id" value="<?= e($selectedProjectId) ?>">
                        <div style="flex: 1; min-width: 180px;">
                            <input type="text" id="project-realtime-search" name="project_search" 
                                value="<?= e($projectKeyword) ?>" 
                                placeholder="🔍 Tìm theo tên hoặc mô tả dự án..." 
                                style="width: 100%; padding: 7px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;"
                                oninput="filterProjectsRealtime(this.value)">
                        </div>
                        <select id="project-status-filter" name="project_status" 
                            style="padding: 7px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;"
                            onchange="filterProjectsRealtime(document.getElementById('project-realtime-search').value)">
                            <option value="">Tất cả trạng thái</option>
                            <?php foreach ($projectStatusOptions as $key => $lbl): ?>
                                <option value="<?= e($key) ?>" <?= $projectStatusFilter === $key ? 'selected' : '' ?>><?= e($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($projectKeyword !== '' || $projectStatusFilter !== ''): ?>
                            <a href="dashboard.php?project_id=<?= rawurlencode($selectedProjectId) ?>#projects" class="secondary-button" style="font-size: 12px; padding: 6px 10px; text-decoration: none;">Đặt lại</a>
                        <?php endif; ?>
                    </form>

                    <table class="data-table compact" id="projects-table">
                        <thead>
                        <tr><th>Tên dự án</th><th>Trạng thái</th><th>Hành động</th></tr>
                        </thead>
                        <tbody id="projects-table-body">
                        <?php if (empty($tableProjects)): ?>
                            <tr id="projects-no-result">
                                <td colspan="3" style="text-align: center; color: #64748b; padding: 16px;">
                                    Không tìm thấy dự án nào khớp với điều kiện tìm kiếm.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tableProjects as $project): ?>
                                <tr data-name="<?= e(mb_strtolower($project['name'], 'UTF-8')) ?>" data-desc="<?= e(mb_strtolower($project['description'] ?? '', 'UTF-8')) ?>" data-status="<?= e($project['status']) ?>">
                                    <td>
                                        <a href="dashboard.php?project_id=<?= rawurlencode($project['id']) ?>" title="Chọn dự án này" style="font-weight: 600; text-decoration: none; color: #1e293b;">
                                            <?= ($project['id'] === $selectedProjectId) ? '⭐ ' : '' ?><?= e($project['name']) ?>
                                        </a>
                                        <?php if (!empty($project['description'])): ?>
                                            <div style="font-size: 11px; color: #64748b; margin-top: 2px;"><?= e(mb_strimwidth($project['description'], 0, 80, '...')) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="status-pill status-<?= e($project['status']) ?>"><?= e($projectStatusOptions[$project['status']] ?? ucfirst($project['status'])) ?></span></td>
                                    <td>
                                        <a href="dashboard.php?edit_project=<?= rawurlencode($project['id']) ?>&project_id=<?= rawurlencode($project['id']) ?>#projects">Sửa</a>
                                        <form method="post" class="inline-form" onsubmit="return confirm('Bạn có chắc muốn xóa dự án này?');">
                                            <input type="hidden" name="current_role" value="<?= e($currentUserRole) ?>">
                                            <input type="hidden" name="action" value="delete_project">
                                            <input type="hidden" name="id" value="<?= e($project['id']) ?>">
                                            <button type="submit" class="danger-button">Xóa</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="projects-no-result-instant" style="display: none;">
                                <td colspan="3" style="text-align: center; color: #64748b; padding: 16px;">
                                    Không tìm thấy dự án nào khớp với từ khóa tìm kiếm.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    <?php 
                    $editingProject = $editProjectId ? fetchRow($pdo, 'projects', $editProjectId) : null; 
                    $editingProjectMemberIds = $editingProject ? $projectController->getMemberIds($editingProject['id']) : [];
                    ?>
                    <form method="post" class="compact-form">
                        <input type="hidden" name="current_role" value="<?= e($currentUserRole) ?>">
                        <input type="hidden" name="action" value="<?= $editingProject ? 'update_project' : 'add_project' ?>">
                        <input type="hidden" name="id" value="<?= e($editingProject['id'] ?? '') ?>">
                        <input type="hidden" name="updated_at" value="<?= e($editingProject['updated_at'] ?? '') ?>">
                        <div class="form-grid">
                            <input type="text" name="name" value="<?= e($editingProject['name'] ?? '') ?>" placeholder="Tên dự án" required>
                        </div>
                        <textarea name="description" placeholder="Mô tả dự án"><?= e($editingProject['description'] ?? '') ?></textarea>
                        <div class="form-grid">
                            <input type="date" name="start_date" value="<?= e($editingProject['start_date'] ?? '') ?>">
                            <input type="date" name="end_date" value="<?= e($editingProject['end_date'] ?? '') ?>">
                            <select name="status">
                                <?php foreach ($projectStatusOptions as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= ($editingProject['status'] ?? 'active') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="project-members-selector" style="margin: 12px 0; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                            <span style="font-size: 13px; font-weight: 600; color: #334155; display: block; margin-bottom: 8px;">
                                👥 Chọn thành viên tham gia dự án này:
                            </span>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px; max-height: 140px; overflow-y: auto;">
                                <?php foreach ($allMembers as $m): ?>
                                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; background: #fff; padding: 6px 10px; border-radius: 6px; border: 1px solid #cbd5e1;">
                                        <input type="checkbox" name="member_ids[]" value="<?= e($m['id']) ?>"
                                            <?= in_array($m['id'], $editingProjectMemberIds, true) ? 'checked' : '' ?>>
                                        <span><strong><?= e($m['name']) ?></strong> <small style="color: #64748b;">(<?= e($roles[$m['role']] ?? $m['role']) ?>)</small></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="primary-button"><?= $editingProject ? 'Lưu thay đổi' : 'Thêm dự án' ?></button>
                            <?php if ($editingProject): ?>
                                <a class="secondary-link" href="dashboard.php?project_id=<?= rawurlencode($selectedProjectId) ?>#projects">Hủy</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </article>

                <article class="panel" id="members">
                    <div class="panel-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <div class="section-title"><span class="section-icon member-section-icon">◆</span><h2>Thành viên</h2></div>
                            <p class="panel-subtitle">
                                <?= $viewAllMembers ? 'Tất cả nhân sự trong hệ thống' : 'Thành viên thuộc dự án: <strong>' . e($activeProject['name'] ?? 'Chung') . '</strong>' ?> 
                                (<?= count($displayMembers) ?> người)
                            </p>
                        </div>
                        <div>
                            <a href="dashboard.php?project_id=<?= rawurlencode($selectedProjectId) ?><?= $viewAllMembers ? '' : '&view_all_members=1' ?>#members" class="secondary-link" style="font-size: 12px; font-weight: 600;">
                                <?= $viewAllMembers ? '← Xem thành viên dự án này' : '👁 Xem tất cả nhân sự' ?>
                            </a>
                        </div>
                    </div>
                    <table class="data-table compact">
                        <thead>
                        <tr><th>Tên</th><th>Email</th><th>Vai trò</th><th>Hành động</th></tr>
                        </thead>
                        <tbody>
                        <?php if (empty($displayMembers)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #64748b; padding: 18px;">
                                    Dự án này chưa có thành viên nào. Hãy <a href="#projects">chọn thành viên khi sửa dự án</a> hoặc thêm mới bên dưới.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($displayMembers as $member): ?>
                                <tr>
                                    <td><?= e($member['name']) ?></td>
                                    <td><?= e($member['email']) ?></td>
                                    <td><?= e($roles[$member['role']] ?? ucfirst($member['role'])) ?></td>
                                    <td>
                                        <a href="dashboard.php?edit_member=<?= rawurlencode($member['id']) ?>&project_id=<?= rawurlencode($selectedProjectId) ?>#members">Sửa</a>
                                        <?php if (!$viewAllMembers && !empty($selectedProjectId)): ?>
                                            <form method="post" class="inline-form" onsubmit="return confirm('Bạn có chắc muốn gỡ thành viên này khỏi dự án?');">
                                                <input type="hidden" name="current_role" value="<?= e($currentUserRole) ?>">
                                                <input type="hidden" name="action" value="remove_project_member">
                                                <input type="hidden" name="project_id" value="<?= e($selectedProjectId) ?>">
                                                <input type="hidden" name="member_id" value="<?= e($member['id']) ?>">
                                                <button type="submit" class="warning-button" style="color: #d97706; background: none; border: none; cursor: pointer; text-decoration: underline; font-size: 12px; margin-right: 6px;">Gỡ</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" class="inline-form" onsubmit="return confirm('Bạn có chắc muốn xóa hẳn thành viên này khỏi hệ thống?');">
                                            <input type="hidden" name="current_role" value="<?= e($currentUserRole) ?>">
                                            <input type="hidden" name="action" value="delete_member">
                                            <input type="hidden" name="id" value="<?= e($member['id']) ?>">
                                            <button type="submit" class="danger-button">Xóa</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    <?php $editingMember = $editMemberId ? fetchRow($pdo, 'members', $editMemberId) : null; ?>
                    <form method="post" class="compact-form">
                        <input type="hidden" name="current_role" value="<?= e($currentUserRole) ?>">
                        <input type="hidden" name="action" value="<?= $editingMember ? 'update_member' : 'add_member' ?>">
                        <input type="hidden" name="id" value="<?= e($editingMember['id'] ?? '') ?>">
                        <input type="hidden" name="updated_at" value="<?= e($editingMember['updated_at'] ?? '') ?>">
                        <div class="form-grid">
                            <input type="text" name="name" value="<?= e($editingMember['name'] ?? '') ?>" placeholder="Tên thành viên" required>
                        </div>
                        <div class="form-grid">
                            <input type="email" name="email" value="<?= e($editingMember['email'] ?? '') ?>" placeholder="Email" required>
                            <select name="role">
                                <?php foreach ($roles as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= ($editingMember['role'] ?? 'member') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="primary-button"><?= $editingMember ? 'Lưu thay đổi' : 'Thêm thành viên' ?></button>
                            <?php if ($editingMember): ?>
                                <a class="secondary-link" href="dashboard.php?project_id=<?= rawurlencode($selectedProjectId) ?>#projects">Hủy</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </article>
            </section>

            <section class="secondary-grid">
                <article class="panel" id="issue-form">
                    <div class="panel-header">
                        <div>
                            <div class="section-title"><span class="section-icon issue-section-icon">◈</span><h2>Ghi nhận lỗi</h2></div>
                            <p class="panel-subtitle">Bước tái hiện lỗi, ghi chú sửa và kiểm thử sau khi sửa</p>
                        </div>
                    </div>
                    <?php $editingIssue = $editIssueId ? fetchRow($pdo, 'issues', $editIssueId) : null; ?>
                    <form method="post" class="compact-form">
                        <input type="hidden" name="current_role" value="<?= e($currentUserRole) ?>">
                        <input type="hidden" name="action" value="<?= $editingIssue ? 'update_issue' : 'add_issue' ?>">
                        <input type="hidden" name="id" value="<?= e($editingIssue['id'] ?? '') ?>">
                        <input type="hidden" name="updated_at" value="<?= e($editingIssue['updated_at'] ?? '') ?>">
                        <div class="form-grid">
                            <input type="text" name="title" value="<?= e($editingIssue['title'] ?? '') ?>" placeholder="Tiêu đề lỗi" required>
                        </div>
                        <div class="form-grid">
                            <select name="project_id">
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?= e($project['id']) ?>" <?= ($editingIssue['project_id'] ?? $selectedProjectId) === $project['id'] ? 'selected' : '' ?>><?= e($project['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="assignee_id">
                                <option value="">Chưa phân công</option>
                                <?php foreach ($projectMembers as $member): ?>
                                    <option value="<?= e($member['id']) ?>" <?= ($editingIssue['assignee_id'] ?? '') === $member['id'] ? 'selected' : '' ?>><?= e($member['name']) ?> (<?= e($roles[$member['role']] ?? $member['role']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-grid">
                            <select name="priority">
                                <?php foreach ($priorities as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= ($editingIssue['priority'] ?? 'medium') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="status">
                                <?php foreach ($statusOptions as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= ($editingIssue['status'] ?? 'open') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="type">
                                <option value="bug" <?= ($editingIssue['type'] ?? 'bug') === 'bug' ? 'selected' : '' ?>>Bug</option>
                                <option value="ui" <?= ($editingIssue['type'] ?? 'bug') === 'ui' ? 'selected' : '' ?>>UI</option>
                                <option value="feature" <?= ($editingIssue['type'] ?? 'bug') === 'feature' ? 'selected' : '' ?>>Feature</option>
                            </select>
                        </div>
                        <textarea name="description" placeholder="Mô tả lỗi"><?= e($editingIssue['description'] ?? '') ?></textarea>
                        <textarea name="reproduction_steps" placeholder="Các bước tái hiện lỗi"><?= e($editingIssue['reproduction_steps'] ?? '') ?></textarea>
                        <textarea name="fix_summary" placeholder="Tóm tắt cách sửa"><?= e($editingIssue['fix_summary'] ?? '') ?></textarea>
                        <textarea name="verification_result" placeholder="Kết quả kiểm tra sau sửa"><?= e($editingIssue['verification_result'] ?? '') ?></textarea>
                        <textarea name="comment" placeholder="Bình luận"><?= e('') ?></textarea>
                        <div class="form-actions">
                            <button type="submit" class="primary-button"><?= $editingIssue ? 'Lưu thay đổi' : 'Lưu lỗi' ?></button>
                            <?php if ($editingIssue): ?>
                                <a class="secondary-link" href="dashboard.php?project_id=<?= rawurlencode($selectedProjectId) ?>#projects">Hủy</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </article>

                <article class="panel" id="release">
                    <div class="panel-header">
                        <div>
                            <div class="section-title"><span class="section-icon release-section-icon">⚿</span><h2>Phát hành</h2></div>
                            <p class="panel-subtitle">Lập phiên bản dự kiến, gắn công việc, kiểm tra điều kiện và xuất ghi chú phát hành</p>
                        </div>
                    </div>
                    <?php foreach ($filteredReleases as $release): ?>
                        <div class="release-card">
                            <div class="release-head">
                                <strong><?= e($release['version']) ?></strong>
                                <div class="issue-card-actions">
                                    <a href="dashboard.php?edit_release=<?= rawurlencode($release['id']) ?>&project_id=<?= rawurlencode($selectedProjectId) ?>#release">Sửa</a>
                                    <a href="dashboard.php?export_release=<?= rawurlencode($release['id']) ?>&project_id=<?= rawurlencode($selectedProjectId) ?>">Xuất ghi chú</a>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="current_role" value="<?= e($currentUserRole) ?>">
                                        <input type="hidden" name="action" value="delete_release">
                                        <input type="hidden" name="id" value="<?= e($release['id']) ?>">
                                        <button type="submit" class="danger-button">Xóa</button>
                                    </form>
                                </div>
                            </div>
                            <p><?= e($release['release_notes']) ?></p>
                            <?php $releaseTasks = decodeJsonList($release['task_ids'] ?? '[]'); $releaseConditions = decodeJsonList($release['conditions'] ?? '[]'); $releaseChanges = decodeJsonList($release['change_list'] ?? '[]'); ?>
                            <?php if ($releaseTasks): ?><p><strong>Công việc gắn:</strong> <?= e(implode(', ', $releaseTasks)) ?></p><?php endif; ?>
                            <?php if ($releaseConditions): ?><p><strong>Điều kiện:</strong> <?= e(implode(', ', $releaseConditions)) ?></p><?php endif; ?>
                            <?php if ($releaseChanges): ?><p><strong>Thay đổi:</strong> <?= e(implode(', ', $releaseChanges)) ?></p><?php endif; ?>
                            <ul>
                                <?php foreach (json_decode($release['checklist'] ?? '[]', true) ?: [] as $item): ?>
                                    <li><?= e($item) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                    <?php $editingRelease = $editReleaseId ? fetchRow($pdo, 'releases', $editReleaseId) : null; ?>
                    <form method="post" class="compact-form">
                        <input type="hidden" name="current_role" value="<?= e($currentUserRole) ?>">
                        <input type="hidden" name="action" value="<?= $editingRelease ? 'update_release' : 'add_release' ?>">
                        <input type="hidden" name="id" value="<?= e($editingRelease['id'] ?? '') ?>">
                        <input type="hidden" name="updated_at" value="<?= e($editingRelease['updated_at'] ?? '') ?>">
                        <div class="form-grid">
                            <input type="text" name="version" value="<?= e($editingRelease['version'] ?? '') ?>" placeholder="Phiên bản ví dụ v1.0.0" required>
                            <input type="date" name="target_date" value="<?= e($editingRelease['target_date'] ?? '') ?>">
                        </div>
                        <div class="form-grid">
                            <select name="project_id">
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?= e($project['id']) ?>" <?= ($editingRelease['project_id'] ?? $selectedProjectId) === $project['id'] ? 'selected' : '' ?>><?= e($project['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="status">
                                <option value="planned" <?= ($editingRelease['status'] ?? 'planned') === 'planned' ? 'selected' : '' ?>>Dự kiến</option>
                                <option value="ready" <?= ($editingRelease['status'] ?? 'planned') === 'ready' ? 'selected' : '' ?>>Sẵn sàng</option>
                                <option value="released" <?= ($editingRelease['status'] ?? 'planned') === 'released' ? 'selected' : '' ?>>Đã phát hành</option>
                            </select>
                        </div>
                        <label>Công việc gắn với phiên bản</label>
                        <select name="task_ids[]" multiple size="5">
                            <?php $releaseTasksSelected = $editingRelease ? decodeJsonList($editingRelease['task_ids'] ?? '[]') : []; ?>
                            <?php foreach ($selectedProjectTasks as $task): ?>
                                <option value="<?= e($task['id']) ?>" <?= in_array($task['id'], $releaseTasksSelected, true) ? 'selected' : '' ?>><?= e($task['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label>Điều kiện phát hành</label>
                        <textarea name="conditions" placeholder="Mỗi dòng điều kiện phát hành hoặc nhập các mục phân cách bằng dấu phẩy"><?= e(implode("\n", decodeJsonList($editingRelease['conditions'] ?? '[]'))) ?></textarea>
                        <label>Danh sách thay đổi</label>
                        <textarea name="change_list" placeholder="Mỗi dòng là một thay đổi hoặc mục danh sách"><?= e(implode("\n", decodeJsonList($editingRelease['change_list'] ?? '[]'))) ?></textarea>
                        <textarea name="checklist" placeholder="Checklist phát hành (mỗi dòng một mục)"><?= e(implode("\n", json_decode($editingRelease['checklist'] ?? '[]', true) ?: [])) ?></textarea>
                        <textarea name="release_notes" placeholder="Ghi chú phát hành"><?= e($editingRelease['release_notes'] ?? '') ?></textarea>
                        <div class="form-actions">
                            <button type="submit" class="primary-button"><?= $editingRelease ? 'Lưu phiên bản' : 'Tạo phiên bản' ?></button>
                            <?php if ($editingRelease): ?>
                                <a class="secondary-link" href="dashboard.php?project_id=<?= rawurlencode($selectedProjectId) ?>#projects">Hủy</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </article>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <div class="section-title"><span class="section-icon member-section-icon">✦</span><h2>Nhật ký hoạt động</h2></div>
                        <p class="panel-subtitle">Lịch sử thao tác và thay đổi của hệ thống</p>
                    </div>
                </div>
                <ul class="activity-list">
                    <?php foreach ($activityLog as $item): ?>
                        <li><strong><?= e($item['entity']) ?></strong> - <?= e($item['action']) ?> - <?= e($item['message'] ?? '') ?> <span><?= e($item['created_at']) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </div>
    </main>
</div>
<script>
function filterProjectsRealtime(keyword) {
    keyword = (keyword || '').toLowerCase().trim();
    const statusSelect = document.getElementById('project-status-filter');
    const status = (statusSelect ? statusSelect.value : '').toLowerCase().trim();
    const rows = document.querySelectorAll('#projects-table-body tr[data-name]');
    let matchCount = 0;

    rows.forEach(row => {
        const name = (row.getAttribute('data-name') || '').toLowerCase();
        const desc = (row.getAttribute('data-desc') || '').toLowerCase();
        const rowStatus = (row.getAttribute('data-status') || '').toLowerCase();

        const matchKeyword = !keyword || name.includes(keyword) || desc.includes(keyword);
        const matchStatus = !status || rowStatus === status;

        if (matchKeyword && matchStatus) {
            row.style.display = '';
            matchCount++;
        } else {
            row.style.display = 'none';
        }
    });

    const emptyRow = document.getElementById('projects-no-result-instant');
    if (emptyRow) {
        emptyRow.style.display = (rows.length > 0 && matchCount === 0) ? '' : 'none';
    }
}
</script>
</body>
</html>

