<?php

/**
 * DevTrack RESTful JSON API Router
 */

require_once __DIR__ . '/db.php';
startSessionSafe();
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/ProjectController.php';
require_once __DIR__ . '/controllers/MemberController.php';
require_once __DIR__ . '/controllers/TaskController.php';
require_once __DIR__ . '/controllers/IssueController.php';
require_once __DIR__ . '/controllers/ReleaseController.php';
require_once __DIR__ . '/controllers/ActivityController.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$pdo = getPdo();

$authController = new AuthController($pdo);
$projectController = new ProjectController($pdo);
$memberController = new MemberController($pdo);
$taskController = new TaskController($pdo);
$issueController = new IssueController($pdo);
$releaseController = new ReleaseController($pdo);
$activityController = new ActivityController($pdo);

// Determine route resource
$resource = $_GET['resource'] ?? '';
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? '';
$projectId = $_GET['project_id'] ?? ($_GET['projectId'] ?? null);

// Parse PATH_INFO if query param 'resource' is empty (e.g. /api.php/tasks)
if ($resource === '' && !empty($_SERVER['PATH_INFO'])) {
    $pathParts = explode('/', trim($_SERVER['PATH_INFO'], '/'));
    $resource = $pathParts[0] ?? '';
    if (isset($pathParts[1])) {
        $id = $pathParts[1];
    }
    if (isset($pathParts[2])) {
        $action = $pathParts[2];
    }
}

$method = strtoupper($_SERVER['REQUEST_METHOD']);
$input = readJsonInput();

$currentUser = currentUser($pdo);
$currentRole = $_SESSION['current_user_role'] ?? ($currentUser['role'] ?? ($input['role'] ?? ($_GET['role'] ?? 'admin')));

try {
    switch ($resource) {
        case 'auth':
            if ($action === 'login' && $method === 'POST') {
                $login = trim((string) ($input['login'] ?? ($input['username'] ?? '')));
                $password = (string) ($input['password'] ?? '');
                $user = $authController->login($login, $password);
                if ($user) {
                    jsonResponse(['success' => true, 'message' => 'Đăng nhập thành công', 'user' => $user]);
                } else {
                    jsonResponse(['success' => false, 'error' => 'Tên đăng nhập hoặc mật khẩu không đúng.'], 401);
                }
            } elseif ($action === 'register' && $method === 'POST') {
                $name = trim((string) ($input['name'] ?? ''));
                $email = trim((string) ($input['email'] ?? ''));
                $username = trim((string) ($input['username'] ?? ''));
                $password = (string) ($input['password'] ?? '');
                $userId = $authController->register($name, $email, $username, $password);
                if ($userId) {
                    jsonResponse(['success' => true, 'message' => 'Đăng ký thành công', 'id' => $userId], 201);
                } else {
                    jsonResponse(['success' => false, 'error' => 'Email hoặc tên đăng nhập đã tồn tại hoặc thiếu thông tin.'], 400);
                }
            } elseif ($action === 'logout') {
                $authController->logout();
                jsonResponse(['success' => true, 'message' => 'Đã đăng xuất']);
            } elseif ($action === 'me') {
                jsonResponse(['success' => true, 'user' => $currentUser, 'role' => $currentRole]);
            } else {
                jsonResponse(['success' => false, 'error' => 'Action auth không hợp lệ.'], 400);
            }
            break;

        case 'projects':
            if ($method === 'GET') {
                if ($id !== '') {
                    $item = $projectController->show($id);
                    if ($item) {
                        $item['members'] = $projectController->getMembers($id);
                        jsonResponse($item);
                    } else {
                        jsonResponse(['error' => 'Không tìm thấy dự án'], 404);
                    }
                } else {
                    $search = $_GET['search'] ?? null;
                    $status = $_GET['status'] ?? null;
                    jsonResponse($projectController->index($search, $status));
                }
            } elseif ($method === 'POST') {
                $newId = $projectController->create($input, $currentRole);
                jsonResponse(['success' => true, 'id' => $newId], 201);
            } elseif ($method === 'PUT') {
                $targetId = $id ?: ($input['id'] ?? '');
                $projectController->update($targetId, $input, $currentRole, $input['updated_at'] ?? null);
                jsonResponse(['success' => true, 'message' => 'Cập nhật dự án thành công']);
            } elseif ($method === 'DELETE') {
                $targetId = $id ?: ($input['id'] ?? '');
                $projectController->delete($targetId, $currentRole);
                jsonResponse(['success' => true, 'message' => 'Xóa dự án thành công']);
            }
            break;

        case 'members':
            if ($method === 'GET') {
                $projectId = trim((string)($_GET['project_id'] ?? ''));
                if ($id !== '') {
                    $item = $memberController->show($id);
                    $item ? jsonResponse($item) : jsonResponse(['error' => 'Không tìm thấy thành viên'], 404);
                } elseif ($projectId !== '') {
                    jsonResponse($projectController->getMembers($projectId));
                } else {
                    jsonResponse($memberController->index());
                }
            } elseif ($method === 'POST') {
                $newId = $memberController->create($input, $currentRole);
                jsonResponse(['success' => true, 'id' => $newId], 201);
            } elseif ($method === 'PUT') {
                $targetId = $id ?: ($input['id'] ?? '');
                $memberController->update($targetId, $input, $currentRole, $input['updated_at'] ?? null);
                jsonResponse(['success' => true, 'message' => 'Cập nhật thành viên thành công']);
            } elseif ($method === 'DELETE') {
                $targetId = $id ?: ($input['id'] ?? '');
                $memberController->delete($targetId, $currentRole);
                jsonResponse(['success' => true, 'message' => 'Xóa thành viên thành công']);
            }
            break;

        case 'tasks':
            if ($action === 'cycles') {
                jsonResponse(['cycles' => $taskController->detectCycles()]);
            }

            if ($method === 'GET') {
                if ($id !== '') {
                    $item = $taskController->show($id);
                    $item ? jsonResponse($item) : jsonResponse(['error' => 'Không tìm thấy công việc'], 404);
                } else {
                    jsonResponse($taskController->index($projectId));
                }
            } elseif ($method === 'POST') {
                $newId = $taskController->create($input, $currentRole);
                jsonResponse(['success' => true, 'id' => $newId], 201);
            } elseif ($method === 'PUT') {
                $targetId = $id ?: ($input['id'] ?? '');
                $taskController->update($targetId, $input, $currentRole, $input['updated_at'] ?? null);
                jsonResponse(['success' => true, 'message' => 'Cập nhật công việc thành công']);
            } elseif ($method === 'DELETE') {
                $targetId = $id ?: ($input['id'] ?? '');
                $taskController->delete($targetId, $currentRole);
                jsonResponse(['success' => true, 'message' => 'Xóa công việc thành công']);
            }
            break;

        case 'issues':
            if ($action === 'comment' && $method === 'POST') {
                $targetId = $id ?: ($input['id'] ?? '');
                $author = $input['author'] ?? ($currentUser['name'] ?? 'Người dùng');
                $text = trim((string) ($input['text'] ?? ($input['comment'] ?? '')));
                if ($text === '') {
                    jsonResponse(['error' => 'Nội dung bình luận không được để trống'], 400);
                }
                $comments = $issueController->addComment($targetId, $author, $text, $currentRole);
                jsonResponse(['success' => true, 'comments' => $comments]);
            }

            if ($method === 'GET') {
                if ($id !== '') {
                    $item = $issueController->show($id);
                    $item ? jsonResponse($item) : jsonResponse(['error' => 'Không tìm thấy lỗi'], 404);
                } else {
                    jsonResponse($issueController->index($projectId));
                }
            } elseif ($method === 'POST') {
                $newId = $issueController->create($input, $currentRole);
                jsonResponse(['success' => true, 'id' => $newId], 201);
            } elseif ($method === 'PUT') {
                $targetId = $id ?: ($input['id'] ?? '');
                $issueController->update($targetId, $input, $currentRole, $input['updated_at'] ?? null);
                jsonResponse(['success' => true, 'message' => 'Cập nhật lỗi thành công']);
            } elseif ($method === 'DELETE') {
                $targetId = $id ?: ($input['id'] ?? '');
                $issueController->delete($targetId, $currentRole);
                jsonResponse(['success' => true, 'message' => 'Xóa lỗi thành công']);
            }
            break;

        case 'releases':
            if ($action === 'export' && $method === 'GET') {
                $targetId = $id ?: ($_GET['id'] ?? '');
                $exportData = $releaseController->export($targetId);
                if ($exportData) {
                    header('Content-Type: text/plain; charset=utf-8');
                    header('Content-Disposition: attachment; filename="' . $exportData['filename'] . '"');
                    echo $exportData['content'];
                    exit;
                }
                jsonResponse(['error' => 'Không tìm thấy phiên bản phát hành'], 404);
            }

            if ($method === 'GET') {
                if ($id !== '') {
                    $item = $releaseController->show($id);
                    $item ? jsonResponse($item) : jsonResponse(['error' => 'Không tìm thấy phiên bản'], 404);
                } else {
                    jsonResponse($releaseController->index($projectId));
                }
            } elseif ($method === 'POST') {
                $newId = $releaseController->create($input, $currentRole);
                jsonResponse(['success' => true, 'id' => $newId], 201);
            } elseif ($method === 'PUT') {
                $targetId = $id ?: ($input['id'] ?? '');
                $releaseController->update($targetId, $input, $currentRole, $input['updated_at'] ?? null);
                jsonResponse(['success' => true, 'message' => 'Cập nhật phiên bản thành công']);
            } elseif ($method === 'DELETE') {
                $targetId = $id ?: ($input['id'] ?? '');
                $releaseController->delete($targetId, $currentRole);
                jsonResponse(['success' => true, 'message' => 'Xóa phiên bản thành công']);
            }
            break;

        case 'activity_log':
        case 'activities':
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
            jsonResponse($activityController->recent($limit));
            break;

        case 'summary':
            $summary = $issueController->summary($projectId);
            $completion = $projectId ? $taskController->completion($projectId) : 0;
            jsonResponse([
                'issues' => $summary,
                'completion_percent' => $completion,
                'cycles' => $taskController->detectCycles()
            ]);
            break;

        default:
            jsonResponse([
                'name' => 'DevTrack API',
                'version' => '1.0.0',
                'endpoints' => [
                    '/api.php?resource=auth&action=[login|register|logout|me]',
                    '/api.php?resource=projects',
                    '/api.php?resource=members',
                    '/api.php?resource=tasks',
                    '/api.php?resource=issues',
                    '/api.php?resource=releases',
                    '/api.php?resource=activity_log',
                    '/api.php?resource=summary'
                ]
            ]);
            break;
    }
} catch (Throwable $e) {
    jsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 400);
}
