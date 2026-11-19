<?php

require_once __DIR__ . '/../models/ProjectModel.php';
require_once __DIR__ . '/../helpers/auth.php';

class ProjectController
{
    private ProjectModel $projectModel;

    public function __construct(PDO $pdo)
    {
        $this->projectModel = new ProjectModel($pdo);
    }

    public function index(?string $keyword = null, ?string $status = null): array
    {
        return $this->projectModel->getAll($keyword, $status);
    }

    public function show(string $id): ?array
    {
        return $this->projectModel->getById($id);
    }

    public function getMembers(string $projectId): array
    {
        return $this->projectModel->getMembers($projectId);
    }

    public function getMemberIds(string $projectId): array
    {
        return $this->projectModel->getMemberIds($projectId);
    }

    public function addMember(string $projectId, string $memberId, string $currentRole = 'member'): void
    {
        assertPermission($currentRole, 'update');
        if (!canManage($currentRole)) {
            throw new RuntimeException('Vai trò hiện tại không được phép gán thành viên.');
        }

        $this->projectModel->addMemberToProject($projectId, $memberId);
    }

    public function removeMember(string $projectId, string $memberId, string $currentRole = 'member'): void
    {
        assertPermission($currentRole, 'update');
        if (!canManage($currentRole)) {
            throw new RuntimeException('Vai trò hiện tại không được phép gỡ thành viên.');
        }

        $this->projectModel->removeMemberFromProject($projectId, $memberId);
    }

    public function create(array $data, string $currentRole = 'member'): string
    {
        assertPermission($currentRole, 'create');
        if (!canManage($currentRole)) {
            throw new RuntimeException('Vai trò hiện tại không được phép tạo dự án.');
        }

        return $this->projectModel->create($data);
    }

    public function update(string $id, array $data, string $currentRole = 'member', ?string $updatedAt = null): void
    {
        assertPermission($currentRole, 'update');
        if (!canManage($currentRole)) {
            throw new RuntimeException('Vai trò hiện tại không được phép cập nhật dự án.');
        }

        $this->projectModel->update($id, $data, $updatedAt);
    }

    public function delete(string $id, string $currentRole = 'admin'): void
    {
        assertPermission($currentRole, 'delete');
        if (!canDelete($currentRole)) {
            throw new RuntimeException('Vai trò hiện tại không được phép xóa dự án.');
        }

        $this->projectModel->delete($id);
    }
}
