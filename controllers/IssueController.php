<?php

require_once __DIR__ . '/../models/IssueModel.php';
require_once __DIR__ . '/../helpers/auth.php';

class IssueController
{
    private IssueModel $issueModel;

    public function __construct(PDO $pdo)
    {
        $this->issueModel = new IssueModel($pdo);
    }

    public function index(?string $projectId = null): array
    {
        return $this->issueModel->getAll($projectId);
    }

    public function show(string $id): ?array
    {
        return $this->issueModel->getById($id);
    }

    public function create(array $data, string $currentRole = 'member'): string
    {
        assertPermission($currentRole, 'create');
        if (!canManage($currentRole)) {
            throw new RuntimeException('Vai trò hiện tại không được phép tạo lỗi.');
        }

        return $this->issueModel->create($data);
    }

    public function update(string $id, array $data, string $currentRole = 'member', ?string $updatedAt = null): void
    {
        assertPermission($currentRole, 'update');
        if (!canManage($currentRole)) {
            throw new RuntimeException('Vai trò hiện tại không được phép cập nhật lỗi.');
        }

        $this->issueModel->update($id, $data, $updatedAt);
    }

    public function delete(string $id, string $currentRole = 'admin'): void
    {
        assertPermission($currentRole, 'delete');
        if (!canDelete($currentRole)) {
            throw new RuntimeException('Vai trò hiện tại không được phép xóa lỗi.');
        }

        $this->issueModel->delete($id);
    }

    public function addComment(string $id, string $author, string $text, string $currentRole = 'member'): array
    {
        if ($currentRole === 'viewer') {
            throw new RuntimeException('Người xem không có quyền thêm bình luận.');
        }

        return $this->issueModel->addComment($id, $author, $text);
    }

    public function summary(?string $projectId = null): array
    {
        return $this->issueModel->getSummary($projectId);
    }
}
