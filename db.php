<?php

/**
 * Database Connection & Seeding for DevTrack
 */

require_once __DIR__ . '/helpers/utils.php';
require_once __DIR__ . '/helpers/auth.php';

function ensureColumnExists(PDO $pdo, string $table, string $column, string $columnDefinition): void
{
    $columns = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll();
    foreach ($columns as $columnInfo) {
        if (($columnInfo['name'] ?? '') === $column) {
            return;
        }
    }

    $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $columnDefinition);
}

function initDatabase(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            role TEXT DEFAULT 'member',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS projects (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            description TEXT,
            start_date TEXT,
            end_date TEXT,
            status TEXT DEFAULT 'active',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS members (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            role TEXT DEFAULT 'member',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS tasks (
            id TEXT PRIMARY KEY,
            title TEXT NOT NULL,
            description TEXT,
            date TEXT,
            status TEXT DEFAULT 'todo',
            priority TEXT DEFAULT 'medium',
            assignee_id TEXT,
            project_id TEXT,
            depends_on TEXT DEFAULT '[]',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS issues (
            id TEXT PRIMARY KEY,
            title TEXT NOT NULL,
            description TEXT,
            type TEXT DEFAULT 'bug',
            priority TEXT DEFAULT 'medium',
            status TEXT DEFAULT 'open',
            assignee_id TEXT,
            project_id TEXT,
            date TEXT,
            reproduction_steps TEXT,
            fix_summary TEXT,
            verification_result TEXT,
            comments TEXT DEFAULT '[]',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS releases (
            id TEXT PRIMARY KEY,
            project_id TEXT,
            version TEXT NOT NULL,
            target_date TEXT,
            status TEXT DEFAULT 'planned',
            release_notes TEXT,
            checklist TEXT DEFAULT '[]',
            task_ids TEXT DEFAULT '[]',
            conditions TEXT DEFAULT '[]',
            change_list TEXT DEFAULT '[]',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS activity_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            entity TEXT NOT NULL,
            entity_id TEXT NOT NULL,
            action TEXT NOT NULL,
            message TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS project_members (
            project_id TEXT NOT NULL,
            member_id TEXT NOT NULL,
            assigned_at TEXT DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(project_id, member_id),
            FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY(member_id) REFERENCES members(id) ON DELETE CASCADE
        )"
    );

    ensureColumnExists($pdo, 'releases', 'task_ids', 'TEXT DEFAULT "[]"');
    ensureColumnExists($pdo, 'releases', 'conditions', 'TEXT DEFAULT "[]"');
    ensureColumnExists($pdo, 'releases', 'change_list', 'TEXT DEFAULT "[]"');

    // Đồng bộ thành viên ban đầu vào các dự án sẵn có nếu bảng project_members chưa có dữ liệu
    $pmCheck = $pdo->query('SELECT COUNT(*) as cnt FROM project_members')->fetch();
    if (empty($pmCheck['cnt'])) {
        $existingProjects = $pdo->query('SELECT id FROM projects')->fetchAll(PDO::FETCH_COLUMN);
        $existingMembers = $pdo->query('SELECT id FROM members')->fetchAll(PDO::FETCH_COLUMN);
        $pmStmt = $pdo->prepare('INSERT OR IGNORE INTO project_members (project_id, member_id) VALUES (?, ?)');
        foreach ($existingProjects as $pId) {
            foreach ($existingMembers as $mId) {
                $pmStmt->execute([$pId, $mId]);
            }
        }
    }

    // Default admin user check
    $adminUser = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $adminUser->execute(['admin']);
    if (!$adminUser->fetch()) {
        $stmt = $pdo->prepare('INSERT INTO users (id, name, email, username, password, role) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            'user-admin',
            'Quản trị viên',
            'admin@devtrack.local',
            'admin',
            password_hash('admin123', PASSWORD_DEFAULT),
            'admin'
        ]);
    }

    // Seed sample data if projects table is empty
    $projectCheck = $pdo->query('SELECT COUNT(*) as cnt FROM projects')->fetch();
    if (empty($projectCheck['cnt'])) {
        $seedSql = [
            'INSERT OR IGNORE INTO projects (id, name, description, start_date, end_date, status) VALUES (?,?,?,?,?,?)',
            [
                'project-main',
                'Phần mềm quản lý dự án',
                'Nền tảng theo dõi lỗi và quản lý kế hoạch phát triển phần mềm',
                '2026-10-01',
                '2026-12-30',
                'active'
            ],
            'INSERT OR IGNORE INTO members (id, name, email, role) VALUES (?,?,?,?)',
            ['admin-1', 'Admin', 'admin@devtrack.local', 'admin'],
            'INSERT OR IGNORE INTO members (id, name, email, role) VALUES (?,?,?,?)',
            ['member-minh', 'Minh Nguyễn', 'minh@devtrack.local', 'manager'],
            'INSERT OR IGNORE INTO members (id, name, email, role) VALUES (?,?,?,?)',
            ['member-linh', 'Linh Trần', 'linh@devtrack.local', 'member'],
            'INSERT OR IGNORE INTO members (id, name, email, role) VALUES (?,?,?,?)',
            ['member-tuan', 'Tuấn Lê', 'tuan@devtrack.local', 'member'],
            'INSERT OR IGNORE INTO members (id, name, email, role) VALUES (?,?,?,?)',
            ['member-hoa', 'Hà Phạm', 'ha@devtrack.local', 'viewer'],
            'INSERT OR IGNORE INTO tasks (id, title, description, date, status, priority, assignee_id, project_id, depends_on) VALUES (?,?,?,?,?,?,?,?,?)',
            ['PLAN-01', 'Phân tích yêu cầu và lập tài liệu đặc tả', '', '2026-10-01', 'done', 'high', 'member-minh', 'project-main', '[]'],
            'INSERT OR IGNORE INTO tasks (id, title, description, date, status, priority, assignee_id, project_id, depends_on) VALUES (?,?,?,?,?,?,?,?,?)',
            ['PLAN-02', 'Thiết kế cơ sở dữ liệu và kiến trúc hệ thống', '', '2026-10-10', 'done', 'high', 'member-minh', 'project-main', '[]'],
            'INSERT OR IGNORE INTO tasks (id, title, description, date, status, priority, assignee_id, project_id, depends_on) VALUES (?,?,?,?,?,?,?,?,?)',
            ['PLAN-03', 'Phát triển giao diện và các chức năng chính', '', '2026-10-25', 'progress', 'high', 'member-linh', 'project-main', '["PLAN-01","PLAN-02"]'],
            'INSERT OR IGNORE INTO tasks (id, title, description, date, status, priority, assignee_id, project_id, depends_on) VALUES (?,?,?,?,?,?,?,?,?)',
            ['PLAN-04', 'Kiểm thử và sửa lỗi hệ thống', '', '2026-11-15', 'todo', 'medium', 'member-tuan', 'project-main', '["PLAN-03"]'],
            'INSERT OR IGNORE INTO issues (id, title, description, type, priority, status, assignee_id, project_id, date, reproduction_steps, fix_summary, verification_result, comments) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            ['BUG-024', 'Lỗi hiển thị sai tổng tiền ở trang thanh toán', 'Giá trị tổng tiền chưa được cập nhật sau khi áp dụng mã giảm giá.', 'bug', 'high', 'progress', 'member-minh', 'project-main', '2026-10-22', "1. Chọn sản phẩm\n2. Áp dụng mã giảm giá\n3. Xem tổng tiền", 'Cập nhật giá cuối cùng sau khi tính lại mã giảm giá.', 'Đã kiểm tra trên Chrome và Edge, số tiền hiển thị đúng.', '[{"author":"Minh Nguyễn","text":"Đã xác định nguyên nhân do dữ liệu không được tính lại khi áp dụng mã giảm giá.","createdAt":"2026-10-22T08:00:00Z"}]'],
            'INSERT OR IGNORE INTO issues (id, title, description, type, priority, status, assignee_id, project_id, date, reproduction_steps, fix_summary, verification_result, comments) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            ['BUG-023', 'Nút lưu thay đổi không hoạt động trên Safari', 'Nút lưu không phản hồi khi sử dụng Safari trên iPhone.', 'bug', 'medium', 'open', 'member-linh', 'project-main', '2026-10-21', 'Mở Safari trên iPhone, nhấn nút lưu.', '', '', '[]'],
            'INSERT OR IGNORE INTO releases (id, project_id, version, target_date, status, release_notes, checklist, task_ids, conditions, change_list) VALUES (?,?,?,?,?,?,?,?,?,?)',
            ['REL-1', 'project-main', 'v1.0.0', '2026-11-30', 'planned', 'Phát hành bản MVP hệ thống quản lý lỗi và công việc.', '["Kiểm thử chức năng chính","Chốt danh sách thay đổi","Xác nhận quyền truy cập"]', '["PLAN-01","PLAN-02","PLAN-03"]', '["Không còn bug nghiêm trọng","Đã pass test case MVP"]', '["Giao diện dashboard theo dõi lỗi","Quản lý task và quan hệ phụ thuộc","Xuất release notes"]']
        ];

        foreach (array_chunk($seedSql, 2) as $pair) {
            $statement = $pdo->prepare($pair[0]);
            $statement->execute($pair[1]);
        }
    }
}

function getPdo(): PDO
{
    static $pdoInstance = null;
    if ($pdoInstance !== null) {
        return $pdoInstance;
    }

    $dbDir = __DIR__ . '/data';
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0777, true);
    }

    $dbPath = $dbDir . '/devtrack.sqlite';
    $pdoInstance = new PDO('sqlite:' . $dbPath);
    $pdoInstance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdoInstance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdoInstance->exec('PRAGMA foreign_keys = ON');

    initDatabase($pdoInstance);

    return $pdoInstance;
}

function detectTaskCycles(PDO $pdo): array
{
    $tasks = $pdo->query('SELECT id, depends_on FROM tasks')->fetchAll();
    $graph = [];
    foreach ($tasks as $task) {
        $graph[$task['id']] = decodeJsonList($task['depends_on']);
    }

    $visited = [];
    $stack = [];
    $cycles = [];

    $visit = function ($node) use (&$visit, &$graph, &$visited, &$stack, &$cycles) {
        if (isset($visited[$node]) && $visited[$node] === 'visiting') {
            $index = array_search($node, $stack, true);
            $cycles[] = array_slice($stack, $index);
            return;
        }

        if (isset($visited[$node])) {
            return;
        }

        $visited[$node] = 'visiting';
        $stack[] = $node;

        foreach ($graph[$node] ?? [] as $dep) {
            if (!isset($graph[$dep])) {
                continue;
            }
            $visit($dep);
        }

        array_pop($stack);
        $visited[$node] = 'done';
    };

    foreach (array_keys($graph) as $node) {
        $visit($node);
    }

    return $cycles;
}

function logActivity(PDO $pdo, string $entity, string $entityId, string $action, ?string $message = null): void
{
    $statement = $pdo->prepare(
        'INSERT INTO activity_log (entity, entity_id, action, message) VALUES (?, ?, ?, ?)'
    );
    $statement->execute([$entity, $entityId, $action, $message]);
}

function findUserByUsername(PDO $pdo, string $username): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    return $user ?: null;
}
