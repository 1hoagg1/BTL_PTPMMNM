<?php

require_once __DIR__ . '/BaseModel.php';

class IssueModel extends BaseModel
{
    public function getAll(?string $projectId = null): array
    {
        if ($projectId !== null && $projectId !== '') {
            $stmt = $this->pdo->prepare('SELECT * FROM issues WHERE project_id = ? ORDER BY date DESC, created_at DESC');
            $stmt->execute([$projectId]);
            return $stmt->fetchAll();
        }

        $stmt = $this->pdo->query('SELECT * FROM issues ORDER BY date DESC, created_at DESC');
        return $stmt->fetchAll();
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM issues WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $issue = $stmt->fetch();
        return $issue ?: null;
    }

    public function create(array $data): string
    {
        $id = !empty($data['id']) ? $data['id'] : generateId('BUG');
        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $type = $data['type'] ?? 'bug';
        $priority = $data['priority'] ?? 'medium';
        $status = $data['status'] ?? 'open';
        $projectId = $data['project_id'] ?? ($data['projectId'] ?? null);
        $assigneeId = $data['assignee_id'] ?? ($data['assigneeId'] ?? null);
        $date = $data['date'] ?? '';
        $reproductionSteps = trim($data['reproduction_steps'] ?? ($data['reproductionSteps'] ?? ''));
        $fixSummary = trim($data['fix_summary'] ?? ($data['fixSummary'] ?? ''));
        $verificationResult = trim($data['verification_result'] ?? ($data['verificationResult'] ?? ''));

        $comments = $data['comments'] ?? [];
        if (is_string($comments)) {
            $comments = decodeJsonList($comments);
        }
        $newComment = trim($data['comment'] ?? '');
        if ($newComment !== '') {
            $comments[] = [
                'author' => $data['author'] ?? 'Hệ thống',
                'text' => $newComment,
                'createdAt' => date('c')
            ];
        }

        if ($title === '') {
            throw new InvalidArgumentException('Tiêu đề lỗi không được để trống.');
        }

        $stmt = $this->pdo->prepare('INSERT INTO issues (id, title, description, type, priority, status, assignee_id, project_id, date, reproduction_steps, fix_summary, verification_result, comments, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
        $stmt->execute([
            $id,
            $title,
            $description,
            $type,
            $priority,
            $status,
            $assigneeId,
            $projectId,
            $date,
            $reproductionSteps,
            $fixSummary,
            $verificationResult,
            json_encode($comments, JSON_UNESCAPED_UNICODE)
        ]);

        $this->log('issues', $id, 'create', 'Thêm lỗi mới: ' . $title);
        return $id;
    }

    public function update(string $id, array $data, ?string $updatedAt = null): void
    {
        $this->checkConflict('issues', $id, $updatedAt);

        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $type = $data['type'] ?? 'bug';
        $priority = $data['priority'] ?? 'medium';
        $status = $data['status'] ?? 'open';
        $projectId = $data['project_id'] ?? ($data['projectId'] ?? null);
        $assigneeId = $data['assignee_id'] ?? ($data['assigneeId'] ?? null);
        $date = $data['date'] ?? '';
        $reproductionSteps = trim($data['reproduction_steps'] ?? ($data['reproductionSteps'] ?? ''));
        $fixSummary = trim($data['fix_summary'] ?? ($data['fixSummary'] ?? ''));
        $verificationResult = trim($data['verification_result'] ?? ($data['verificationResult'] ?? ''));

        // Handle existing comments + optional new comment
        $comments = $data['comments'] ?? null;
        if ($comments === null && isset($data['existing_comments'])) {
            $comments = decodeJsonList($data['existing_comments']);
        } elseif (is_string($comments)) {
            $comments = decodeJsonList($comments);
        } elseif ($comments === null) {
            $current = $this->getById($id);
            $comments = decodeJsonList($current['comments'] ?? '[]');
        }

        $newComment = trim($data['comment'] ?? '');
        if ($newComment !== '') {
            $comments[] = [
                'author' => $data['author'] ?? 'Hệ thống',
                'text' => $newComment,
                'createdAt' => date('c')
            ];
        }

        if ($title === '') {
            throw new InvalidArgumentException('Tiêu đề lỗi không được để trống.');
        }

        $stmt = $this->pdo->prepare('UPDATE issues SET title = ?, description = ?, type = ?, priority = ?, status = ?, assignee_id = ?, project_id = ?, date = ?, reproduction_steps = ?, fix_summary = ?, verification_result = ?, comments = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([
            $title,
            $description,
            $type,
            $priority,
            $status,
            $assigneeId,
            $projectId,
            $date,
            $reproductionSteps,
            $fixSummary,
            $verificationResult,
            json_encode($comments, JSON_UNESCAPED_UNICODE),
            $id
        ]);

        $this->log('issues', $id, 'update', 'Cập nhật lỗi: ' . $title);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM issues WHERE id = ?');
        $stmt->execute([$id]);

        $this->log('issues', $id, 'delete', 'Xóa lỗi');
    }

    public function addComment(string $id, string $author, string $text): array
    {
        $issue = $this->getById($id);
        if (!$issue) {
            throw new RuntimeException('Không tìm thấy lỗi: ' . $id);
        }

        $comments = decodeJsonList($issue['comments'] ?? '[]');
        $comments[] = [
            'author' => $author ?: 'Người dùng',
            'text' => $text,
            'createdAt' => date('c')
        ];

        $stmt = $this->pdo->prepare('UPDATE issues SET comments = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([json_encode($comments, JSON_UNESCAPED_UNICODE), $id]);

        $this->log('issues', $id, 'comment', 'Thêm bình luận mới');
        return $comments;
    }

    public function getSummary(?string $projectId = null): array
    {
        $issues = $this->getAll($projectId);
        $count = count($issues);
        $open = 0;
        $high = 0;

        foreach ($issues as $issue) {
            $status = $issue['status'] ?? '';
            $priority = $issue['priority'] ?? '';

            if ($status === 'open' || $status === 'progress') {
                $open++;
            }
            if ($priority === 'high' || $priority === 'urgent') {
                $high++;
            }
        }

        return ['count' => $count, 'open' => $open, 'high' => $high];
    }
}
