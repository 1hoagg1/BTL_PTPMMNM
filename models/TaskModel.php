<?php

require_once __DIR__ . '/BaseModel.php';

class TaskModel extends BaseModel
{
    public function getAll(?string $projectId = null): array
    {
        if ($projectId !== null && $projectId !== '') {
            $stmt = $this->pdo->prepare('SELECT * FROM tasks WHERE project_id = ? ORDER BY date DESC, created_at DESC');
            $stmt->execute([$projectId]);
            return $stmt->fetchAll();
        }

        $stmt = $this->pdo->query('SELECT * FROM tasks ORDER BY date DESC, created_at DESC');
        return $stmt->fetchAll();
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tasks WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $task = $stmt->fetch();
        return $task ?: null;
    }

    public function create(array $data): string
    {
        $id = !empty($data['id']) ? $data['id'] : generateId('TASK');
        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $date = $data['date'] ?? '';
        $status = $data['status'] ?? 'todo';
        $priority = $data['priority'] ?? 'medium';
        $projectId = $data['project_id'] ?? ($data['projectId'] ?? null);
        $assigneeId = $data['assignee_id'] ?? ($data['assigneeId'] ?? null);
        $dependsOn = normalizeArrayInput($data['depends_on'] ?? ($data['dependsOn'] ?? []));

        if ($title === '') {
            throw new InvalidArgumentException('Tên công việc không được để trống.');
        }

        // Check cycle before insertion
        $this->validateNoCycle($id, $dependsOn);

        $stmt = $this->pdo->prepare('INSERT INTO tasks (id, title, description, date, status, priority, assignee_id, project_id, depends_on, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
        $stmt->execute([
            $id,
            $title,
            $description,
            $date,
            $status,
            $priority,
            $assigneeId,
            $projectId,
            json_encode($dependsOn, JSON_UNESCAPED_UNICODE)
        ]);

        $this->log('tasks', $id, 'create', 'Thêm công việc mới: ' . $title);
        return $id;
    }

    public function update(string $id, array $data, ?string $updatedAt = null): void
    {
        $this->checkConflict('tasks', $id, $updatedAt);

        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $date = $data['date'] ?? '';
        $status = $data['status'] ?? 'todo';
        $priority = $data['priority'] ?? 'medium';
        $projectId = $data['project_id'] ?? ($data['projectId'] ?? null);
        $assigneeId = $data['assignee_id'] ?? ($data['assigneeId'] ?? null);
        $dependsOn = normalizeArrayInput($data['depends_on'] ?? ($data['dependsOn'] ?? []));

        if ($title === '') {
            throw new InvalidArgumentException('Tên công việc không được để trống.');
        }

        // Check cycle before committing update
        $this->validateNoCycle($id, $dependsOn);

        $stmt = $this->pdo->prepare('UPDATE tasks SET title = ?, description = ?, date = ?, status = ?, priority = ?, assignee_id = ?, project_id = ?, depends_on = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([
            $title,
            $description,
            $date,
            $status,
            $priority,
            $assigneeId,
            $projectId,
            json_encode($dependsOn, JSON_UNESCAPED_UNICODE),
            $id
        ]);

        $this->log('tasks', $id, 'update', 'Cập nhật công việc: ' . $title);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM tasks WHERE id = ?');
        $stmt->execute([$id]);

        $this->log('tasks', $id, 'delete', 'Xóa công việc');
    }

    public function detectCycles(?string $candidateId = null, ?array $candidateDeps = null): array
    {
        $tasks = $this->getAll();
        $graph = [];
        foreach ($tasks as $task) {
            $graph[$task['id']] = decodeJsonList($task['depends_on'] ?? '[]');
        }

        if ($candidateId !== null && $candidateDeps !== null) {
            $graph[$candidateId] = $candidateDeps;
        }

        $visited = [];
        $stack = [];
        $cycles = [];

        $visit = function ($node) use (&$visit, &$graph, &$visited, &$stack, &$cycles) {
            if (isset($visited[$node]) && $visited[$node] === 'visiting') {
                $index = array_search($node, $stack, true);
                if ($index !== false) {
                    $cycles[] = array_slice($stack, $index);
                }
                return;
            }

            if (isset($visited[$node])) {
                return;
            }

            $visited[$node] = 'visiting';
            $stack[] = $node;

            foreach ($graph[$node] ?? [] as $dep) {
                if (isset($graph[$dep])) {
                    $visit($dep);
                }
            }

            array_pop($stack);
            $visited[$node] = 'done';
        };

        foreach (array_keys($graph) as $node) {
            $visit($node);
        }

        return $cycles;
    }

    public function validateNoCycle(string $taskId, array $dependsOn): void
    {
        $cycles = $this->detectCycles($taskId, $dependsOn);
        if (!empty($cycles)) {
            throw new RuntimeException('Vòng phụ thuộc phát hiện: ' . json_encode($cycles[0], JSON_UNESCAPED_UNICODE));
        }
    }

    public function isTaskBlocked(string $taskId, ?array $taskRows = null): bool
    {
        if ($taskRows === null) {
            $taskRows = $this->getAll();
        }

        $depends = [];
        foreach ($taskRows as $task) {
            if ($task['id'] === $taskId) {
                $depends = decodeJsonList($task['depends_on'] ?? '[]');
                break;
            }
        }

        foreach ($depends as $depId) {
            foreach ($taskRows as $task) {
                if ($task['id'] === $depId && $task['status'] !== 'done') {
                    return true;
                }
            }
        }

        return false;
    }

    public function getProjectCompletion(string $projectId): int
    {
        $tasks = $this->getAll($projectId);
        $total = count($tasks);
        if ($total === 0) {
            return 0;
        }

        $done = count(array_filter($tasks, fn ($t) => ($t['status'] ?? '') === 'done'));
        return (int) round(($done / $total) * 100);
    }
}
