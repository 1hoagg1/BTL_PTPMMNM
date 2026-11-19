<?php

require_once __DIR__ . '/../models/TaskModel.php';
require_once __DIR__ . '/../helpers/auth.php';

class TaskController
{
    private TaskModel $taskModel;

    public function __construct(PDO $pdo)
    {
        $this->taskModel = new TaskModel($pdo);
    }

    public function index(?string $projectId = null): array
    {
        return $this->taskModel->getAll($projectId);
    }

    public function show(string $id): ?array
    {
        return $this->taskModel->getById($id);
    }

    public function create(array $data, string $currentRole = 'member'): string
    {
        assertPermission($currentRole, 'create');
        if (!canManage($currentRole)) {
            throw new RuntimeException('Vai trò hiện tại không được phép tạo công việc.');
        }

        return $this->taskModel->create($data);
    }

    public function update(string $id, array $data, string $currentRole = 'member', ?string $updatedAt = null): void
    {
        assertPermission($currentRole, 'update');
        if (!canManage($currentRole)) {
            throw new RuntimeException('Vai trò hiện tại không được phép cập nhật công việc.');
        }

        $this->taskModel->update($id, $data, $updatedAt);
    }

    public function delete(string $id, string $currentRole = 'admin'): void
    {
        assertPermission($currentRole, 'delete');
        if (!canDelete($currentRole)) {
            throw new RuntimeException('Vai trò hiện tại không được phép xóa công việc.');
        }

        $this->taskModel->delete($id);
    }

    public function isBlocked(string $taskId, ?array $taskRows = null): bool
    {
        return $this->taskModel->isTaskBlocked($taskId, $taskRows);
    }

    public function completion(string $projectId): int
    {
        return $this->taskModel->getProjectCompletion($projectId);
    }

    public function detectCycles(): array
    {
        return $this->taskModel->detectCycles();
    }
}
