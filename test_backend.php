<?php

/**
 * DevTrack Backend Automated Test Suite
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "=== DevTrack Backend Test Suite ===\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $name, bool $condition, string $detail = ''): void
{
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo " [PASS] $name\n";
    } else {
        $failCount++;
        echo " [FAIL] $name: $detail\n";
    }
}

// 1. Database Connection & Initial Seed
require_once __DIR__ . '/db.php';
$pdo = getPdo();
assertTest('Database Connection', $pdo instanceof PDO);

$tables = ['users', 'projects', 'members', 'tasks', 'issues', 'releases', 'activity_log'];
foreach ($tables as $table) {
    $count = (int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    assertTest("Table $table exists and accessible", $count >= 0, "Count: $count");
}

// 2. AuthController Tests
require_once __DIR__ . '/controllers/AuthController.php';
$authController = new AuthController($pdo);

$admin = $authController->login('admin', 'admin123');
assertTest('Admin login with correct password', $admin !== null && $admin['username'] === 'admin');

$badLogin = $authController->login('admin', 'wrongpass');
assertTest('Admin login with wrong password rejected', $badLogin === null);

$testUsername = 'testuser_' . time();
$newUserId = $authController->register('Test User', 'test@example.com', $testUsername, 'password123');
assertTest('Register new user', $newUserId !== null);

$dupUserId = $authController->register('Dup User', 'dup@example.com', $testUsername, 'password123');
assertTest('Reject duplicate username registration', $dupUserId === null);

$testUserLogin = $authController->login($testUsername, 'password123');
assertTest('Login with newly registered user', $testUserLogin !== null && $testUserLogin['id'] === $newUserId);

// 3. ProjectController Tests
require_once __DIR__ . '/controllers/ProjectController.php';
$projectController = new ProjectController($pdo);

$projectId = $projectController->create([
    'name' => 'Dự án Kiểm thử Tự động',
    'description' => 'Mô tả dự án kiểm thử',
    'start_date' => '2026-10-01',
    'end_date' => '2026-12-31',
    'status' => 'active'
], 'admin');
assertTest('ProjectController create project as admin', !empty($projectId));

$proj = $projectController->show($projectId);
assertTest('ProjectController show project', $proj !== null && $proj['name'] === 'Dự án Kiểm thử Tự động');

// Optimistic locking test
$originalUpdatedAt = $proj['updated_at'];
$projectController->update($projectId, ['name' => 'Dự án Đã đổi tên'], 'admin', $originalUpdatedAt);
$updatedProj = $projectController->show($projectId);
assertTest('ProjectController update project with correct updated_at', $updatedProj['name'] === 'Dự án Đã đổi tên');

$conflictCaught = false;
try {
    // Pass old updated_at to simulate concurrent modification
    $projectController->update($projectId, ['name' => 'Dự án Gây xung đột'], 'admin', '2020-01-01 00:00:00');
} catch (RuntimeException $e) {
    $conflictCaught = true;
}
assertTest('ProjectController conflict detection on stale updated_at', $conflictCaught);

// RBAC: viewer cannot create project
$viewerBlocked = false;
try {
    $projectController->create(['name' => 'Viewer Project'], 'viewer');
} catch (RuntimeException $e) {
    $viewerBlocked = true;
}
assertTest('ProjectController blocks viewer from creating project', $viewerBlocked);

// Project Members Association test
$customProjId = $projectController->create([
    'name' => 'Dự án Phân quyền thành viên',
    'member_ids' => ['admin-1', 'member-linh']
], 'admin');
$assignedMembers = $projectController->getMembers($customProjId);
$assignedIds = array_column($assignedMembers, 'id');
assertTest('ProjectController assigns only selected members to new project', 
    count($assignedMembers) === 2 && in_array('admin-1', $assignedIds) && in_array('member-linh', $assignedIds) && !in_array('member-minh', $assignedIds));

$projectController->addMember($customProjId, 'member-minh', 'admin');
$updatedMembers = $projectController->getMembers($customProjId);
assertTest('ProjectController add member to project', count($updatedMembers) === 3);

$projectController->removeMember($customProjId, 'member-minh', 'admin');
$afterRemoveMembers = $projectController->getMembers($customProjId);
assertTest('ProjectController remove member from project', count($afterRemoveMembers) === 2);

// Project Search test
$searchResults = $projectController->index('Phân quyền');
assertTest('ProjectController search projects by keyword', count($searchResults) >= 1 && $searchResults[0]['id'] === $customProjId);
$searchEmpty = $projectController->index('Từ khóa không tồn tại 123456');
assertTest('ProjectController search returns empty on non-matching keyword', count($searchEmpty) === 0);

// 4. MemberController Tests
require_once __DIR__ . '/controllers/MemberController.php';
$memberController = new MemberController($pdo);

$memberId = $memberController->create([
    'name' => 'Nguyễn Kiểm Thử',
    'email' => 'kiemthu_' . time() . '@devtrack.local',
    'role' => 'member'
], 'admin');
assertTest('MemberController create member', !empty($memberId));

$mem = $memberController->show($memberId);
assertTest('MemberController show member', $mem !== null && $mem['name'] === 'Nguyễn Kiểm Thử');

$memberController->update($memberId, [
    'name' => 'Nguyễn Kiểm Thử (Đã sửa)',
    'email' => $mem['email'],
    'role' => 'manager'
], 'admin', $mem['updated_at']);
$updatedMem = $memberController->show($memberId);
assertTest('MemberController update member', $updatedMem['role'] === 'manager');

// 5. TaskController Tests & Cycle Detection
require_once __DIR__ . '/controllers/TaskController.php';
$taskController = new TaskController($pdo);

$t1 = $taskController->create([
    'title' => 'Task A Test',
    'project_id' => $projectId,
    'status' => 'progress',
    'priority' => 'high',
    'depends_on' => []
], 'admin');

$t2 = $taskController->create([
    'title' => 'Task B Test',
    'project_id' => $projectId,
    'status' => 'todo',
    'priority' => 'medium',
    'depends_on' => [$t1]
], 'admin');
assertTest('TaskController create tasks with dependency', !empty($t1) && !empty($t2));

// Check if Task B is blocked by Task A (since Task A status is 'progress', not 'done')
$isBlocked = $taskController->isBlocked($t2);
assertTest('TaskController isBlocked correctly detects unfinished dependency', $isBlocked === true);

// Cycle detection test: make Task A depend on Task B (creating A -> B -> A cycle)
$cycleDetected = false;
try {
    $taskController->update($t1, [
        'title' => 'Task A Test',
        'project_id' => $projectId,
        'status' => 'progress',
        'priority' => 'high',
        'depends_on' => [$t2]
    ], 'admin');
} catch (RuntimeException $e) {
    $cycleDetected = true;
}
assertTest('TaskController detects dependency cycle and blocks update', $cycleDetected);

// 6. IssueController Tests
require_once __DIR__ . '/controllers/IssueController.php';
$issueController = new IssueController($pdo);

$issueId = $issueController->create([
    'title' => 'Lỗi phát sinh kiểm thử',
    'description' => 'Mô tả lỗi test',
    'type' => 'bug',
    'priority' => 'high',
    'status' => 'open',
    'project_id' => $projectId,
    'reproduction_steps' => '1. Mở trang web',
    'fix_summary' => 'Chưa sửa',
    'verification_result' => 'Chưa verify'
], 'admin');
assertTest('IssueController create issue', !empty($issueId));

$comments = $issueController->addComment($issueId, 'Kỹ sư QA', 'Đang phân tích log máy chủ', 'member');
assertTest('IssueController add comment', count($comments) >= 1 && $comments[count($comments) - 1]['text'] === 'Đang phân tích log máy chủ');

$summary = $issueController->summary($projectId);
assertTest('IssueController summary statistics', $summary['count'] >= 1 && $summary['open'] >= 1 && $summary['high'] >= 1);

// 7. ReleaseController & Export Notes
require_once __DIR__ . '/controllers/ReleaseController.php';
$releaseController = new ReleaseController($pdo);

$releaseId = $releaseController->create([
    'project_id' => $projectId,
    'version' => 'v2.0.0-test',
    'target_date' => '2026-11-20',
    'status' => 'planned',
    'release_notes' => 'Bản phát hành kiểm thử backend MVC.',
    'checklist' => ['Chạy full test suite', 'Pass kiểm thử bảo mật'],
    'task_ids' => [$t1, $t2],
    'conditions' => ['0 critical bugs', 'Test coverage > 85%'],
    'change_list' => ['Refactor backend to MVC', 'Tích hợp REST API']
], 'admin');
assertTest('ReleaseController create release', !empty($releaseId));

$export = $releaseController->export($releaseId);
assertTest('ReleaseController export release notes', $export !== null && strpos($export['content'], 'v2.0.0-test') !== false && strpos($export['content'], 'Refactor backend to MVC') !== false);

// 8. ActivityController Tests
require_once __DIR__ . '/controllers/ActivityController.php';
$activityController = new ActivityController($pdo);
$recentLogs = $activityController->recent(10);
assertTest('ActivityController records system activities', count($recentLogs) > 0);

// Clean up test entities
$taskController->delete($t2, 'admin');
$taskController->delete($t1, 'admin');
$issueController->delete($issueId, 'admin');
$releaseController->delete($releaseId, 'admin');
$memberController->delete($memberId, 'admin');
$projectController->delete($projectId, 'admin');
$projectController->delete($customProjId, 'admin');
$pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$newUserId]);
assertTest('Clean up test records', true);

echo "\n=====================================\n";
echo "Results: $passCount PASSED, $failCount FAILED\n";
echo "=====================================\n";

if ($failCount > 0) {
    exit(1);
}

