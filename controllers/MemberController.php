<?php

require_once __DIR__ . '/../models/MemberModel.php';
require_once __DIR__ . '/../helpers/auth.php';

class MemberController
{
    private MemberModel $memberModel;

    public function __construct(PDO $pdo)
    {
        $this->memberModel = new MemberModel($pdo);
    }

    public function index(): array
    {
        return $this->memberModel->getAll();
    }

    public function show(string $id): ?array
    {
        return $this->memberModel->getById($id);
    }

    public function create(array $data, string $currentRole = 'member'): string
    {
        assertPermission($currentRole, 'create');
        if (!canManage($currentRole)) {
            throw new RuntimeException('Vai trò hiện tại không được phép thêm thành viên.');
        }

        return $this->memberModel->create($data);
    }

    public function update(string $id, array $data, string $currentRole = 'member', ?string $updatedAt = null): void
    {
        assertPermission($currentRole, 'update');
        if (!canManage($currentRole)) {
            throw new RuntimeException('Vai trò hiện tại không được phép cập nhật thành viên.');
        }

        $this->memberModel->update($id, $data, $updatedAt);
    }

    public function delete(string $id, string $currentRole = 'admin'): void
    {
        assertPermission($currentRole, 'delete');
        if (!canDelete($currentRole)) {
            throw new RuntimeException('Vai trò hiện tại không được phép xóa thành viên.');
        }

        $this->memberModel->delete($id);
    }
}

