<?php

/**
 * Authentication and authorization helper functions for DevTrack
 */

require_once __DIR__ . '/utils.php';

if (!function_exists('startSessionSafe')) {
    function startSessionSafe(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            $sessDir = dirname(__DIR__) . '/data/sessions';
            if (!is_dir($sessDir)) {
                @mkdir($sessDir, 0777, true);
            }
            if (is_dir($sessDir) && is_writable($sessDir)) {
                @session_save_path($sessDir);
            }
            @session_start();
        }
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin(): void
    {
        startSessionSafe();

        if (empty($_SESSION['user_id'])) {
            header('Location: login.php');
            exit;
        }
    }
}

if (!function_exists('currentUser')) {
    function currentUser(PDO $pdo): ?array
    {
        startSessionSafe();

        if (empty($_SESSION['user_id'])) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT id, name, email, username, role, created_at, updated_at FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        return $user ?: null;
    }
}

if (!function_exists('requireRole')) {
    function requireRole(PDO $pdo, string $requiredRole): void
    {
        $user = currentUser($pdo);
        if (!$user || $user['role'] !== $requiredRole) {
            header('Location: dashboard.php');
            exit;
        }
    }
}

if (!function_exists('canManage')) {
    function canManage(string $role): bool
    {
        return in_array($role, ['admin', 'manager', 'member'], true);
    }
}

if (!function_exists('canDelete')) {
    function canDelete(string $role): bool
    {
        return in_array($role, ['admin', 'manager'], true);
    }
}

if (!function_exists('assertPermission')) {
    function assertPermission(string $role, string $action): void
    {
        if ($role === 'viewer') {
            throw new RuntimeException('Người xem chỉ có quyền xem, không thể ' . $action . '.');
        }

        if ($role === 'member' && $action === 'delete') {
            throw new RuntimeException('Thành viên không có quyền xóa dữ liệu.');
        }

        if (!in_array($role, ['admin', 'manager', 'member'], true)) {
            throw new RuntimeException('Bạn không có quyền thực hiện thao tác này.');
        }
    }
}

if (!function_exists('fetchRow')) {
    function fetchRow(PDO $pdo, string $table, string $id): ?array
    {
        $allowedTables = ['users', 'projects', 'members', 'tasks', 'issues', 'releases', 'activity_log'];
        if (!in_array($table, $allowedTables, true)) {
            throw new InvalidArgumentException('Bảng không hợp lệ: ' . $table);
        }

        $stmt = $pdo->prepare('SELECT * FROM ' . $table . ' WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}

if (!function_exists('ensureNoConflict')) {
    function ensureNoConflict(PDO $pdo, string $table, string $id, ?string $updatedAt): void
    {
        if ($updatedAt === null || $updatedAt === '') {
            return;
        }

        $row = fetchRow($pdo, $table, $id);
        if ($row && !empty($row['updated_at']) && $row['updated_at'] !== $updatedAt) {
            throw new RuntimeException('Dữ liệu đã bị người khác sửa trước khi lưu. Vui lòng tải lại nội dung và lưu lại sau.');
        }
    }
}
