<?php

require_once __DIR__ . '/BaseModel.php';

class UserModel extends BaseModel
{
    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, email, username, role, created_at, updated_at FROM users ORDER BY created_at DESC');
        return $stmt->fetchAll();
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, email, username, role, created_at, updated_at FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function verifyPassword(string $login, string $password): ?array
    {
        $user = $this->findByUsername($login);
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return null;
    }

    public function create(string $name, string $email, string $username, string $password, string $role = 'member'): string
    {
        $id = generateId('USR');
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare('INSERT INTO users (id, name, email, username, password, role, updated_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
        $stmt->execute([$id, $name, $email, $username, $hashedPassword, $role]);

        // Keep members table synchronized
        try {
            $memberStmt = $this->pdo->prepare('INSERT OR IGNORE INTO members (id, name, email, role, updated_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)');
            $memberStmt->execute([$id, $name, $email, $role]);
        } catch (Throwable $e) {
            // Member sync is optional if already exists
        }

        $this->log('users', $id, 'create', 'Tạo tài khoản người dùng: ' . $username);
        return $id;
    }

    public function update(string $id, array $data, ?string $updatedAt = null): void
    {
        $this->checkConflict('users', $id, $updatedAt);

        $fields = [];
        $params = [];

        if (isset($data['name'])) {
            $fields[] = 'name = ?';
            $params[] = trim($data['name']);
        }
        if (isset($data['email'])) {
            $fields[] = 'email = ?';
            $params[] = trim($data['email']);
        }
        if (isset($data['role'])) {
            $fields[] = 'role = ?';
            $params[] = $data['role'];
        }
        if (!empty($data['password'])) {
            $fields[] = 'password = ?';
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if (empty($fields)) {
            return;
        }

        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        $params[] = $id;

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $this->log('users', $id, 'update', 'Cập nhật tài khoản');
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $this->log('users', $id, 'delete', 'Xóa tài khoản người dùng');
    }
}
