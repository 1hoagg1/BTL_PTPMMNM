<?php

require_once __DIR__ . '/BaseModel.php';

class MemberModel extends BaseModel
{
    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM members ORDER BY name ASC');
        return $stmt->fetchAll();
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM members WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $member = $stmt->fetch();
        return $member ?: null;
    }

    public function create(array $data): string
    {
        $id = !empty($data['id']) ? $data['id'] : generateId('MEM');
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $role = $data['role'] ?? 'member';

        if ($name === '' || $email === '') {
            throw new InvalidArgumentException('Tên và email thành viên không được để trống.');
        }

        $stmt = $this->pdo->prepare('INSERT INTO members (id, name, email, role, updated_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)');
        $stmt->execute([$id, $name, $email, $role]);

        // Tự động đồng bộ tạo tài khoản đăng nhập nếu chưa có (Mật khẩu mặc định: 123456)
        try {
            $baseUsername = explode('@', $email)[0];
            $username = $baseUsername;
            $checkUser = $this->pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            $checkUser->execute([$username, $email]);
            if (!$checkUser->fetch()) {
                $hash = password_hash('123456', PASSWORD_DEFAULT);
                $uStmt = $this->pdo->prepare('INSERT INTO users (id, name, email, username, password, role, updated_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
                $uStmt->execute([$id, $name, $email, $username, $hash, $role]);
            }
        } catch (Throwable $e) {
            // Bỏ qua nếu đã tồn tại
        }

        $this->log('members', $id, 'create', 'Thêm thành viên mới: ' . $name);
        return $id;
    }

    public function update(string $id, array $data, ?string $updatedAt = null): void
    {
        $this->checkConflict('members', $id, $updatedAt);

        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $role = $data['role'] ?? 'member';

        if ($name === '' || $email === '') {
            throw new InvalidArgumentException('Tên và email thành viên không được để trống.');
        }

        $stmt = $this->pdo->prepare('UPDATE members SET name = ?, email = ?, role = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$name, $email, $role, $id]);

        $this->log('members', $id, 'update', 'Cập nhật thành viên: ' . $name);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM members WHERE id = ?');
        $stmt->execute([$id]);

        $this->log('members', $id, 'delete', 'Xóa thành viên');
    }
}

