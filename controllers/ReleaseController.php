<?php

require_once __DIR__ . '/../models/ReleaseModel.php';
require_once __DIR__ . '/../helpers/auth.php';

class ReleaseController
{
    private ReleaseModel $releaseModel;

    public function __construct(PDO $pdo)
    {
        $this->releaseModel = new ReleaseModel($pdo);
    }

    public function index(?string $projectId = null): array
    {
        return $this->releaseModel->getAll($projectId);
    }

    public function show(string $id): ?array
    {
        return $this->releaseModel->getById($id);
    }

    public function create(array $data, string $currentRole = 'member'): string
    {
        assertPermission($currentRole, 'create');
        if (!canManage($currentRole)) {
            throw new RuntimeException('Vai trò hiện tại không được phép tạo phiên bản phát hành.');
        }

        return $this->releaseModel->create($data);
    }

    public function update(string $id, array $data, string $currentRole = 'member', ?string $updatedAt = null): void
    {
        assertPermission($currentRole, 'update');
        if (!canManage($currentRole)) {
            throw new RuntimeException('Vai trò hiện tại không được phép cập nhật phiên bản phát hành.');
        }

        $this->releaseModel->update($id, $data, $updatedAt);
    }

    public function delete(string $id, string $currentRole = 'admin'): void
    {
        assertPermission($currentRole, 'delete');
        if (!canDelete($currentRole)) {
            throw new RuntimeException('Vai trò hiện tại không được phép xóa phiên bản phát hành.');
        }

        $this->releaseModel->delete($id);
    }

    public function export(string $id): ?array
    {
        return $this->releaseModel->exportText($id);
    }
}
