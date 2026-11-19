<?php

require_once __DIR__ . '/BaseModel.php';

class ProjectModel extends BaseModel
{
    public function getAll(?string $keyword = null, ?string $status = null): array
    {
        if (($keyword !== null && trim($keyword) !== '') || ($status !== null && $status !== '' && $status !== 'all')) {
            return $this->search($keyword ?? '', $status);
        }
        $stmt = $this->pdo->query('SELECT * FROM projects ORDER BY created_at DESC');
        return $stmt->fetchAll();
    }

    public function search(string $keyword = '', ?string $status = null): array
    {
        $sql = 'SELECT * FROM projects WHERE 1=1';
        $params = [];

        $keyword = trim($keyword);
        if ($keyword !== '') {
            $sql .= ' AND (name LIKE ? OR description LIKE ?)';
            $params[] = '%' . $keyword . '%';
            $params[] = '%' . $keyword . '%';
        }

        if ($status !== null && $status !== '' && $status !== 'all') {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM projects WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $project = $stmt->fetch();
        if ($project) {
            $project['member_ids'] = $this->getMemberIds($id);
        }
        return $project ?: null;
    }

    public function getMemberIds(string $projectId): array
    {
        $stmt = $this->pdo->prepare('SELECT member_id FROM project_members WHERE project_id = ?');
        $stmt->execute([$projectId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    public function getMembers(string $projectId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT m.*, pm.assigned_at
            FROM members m
            INNER JOIN project_members pm ON m.id = pm.member_id
            WHERE pm.project_id = ?
            ORDER BY m.name ASC
        ');
        $stmt->execute([$projectId]);
        return $stmt->fetchAll() ?: [];
    }

    public function setMembers(string $projectId, array $memberIds): void
    {
        $del = $this->pdo->prepare('DELETE FROM project_members WHERE project_id = ?');
        $del->execute([$projectId]);

        $ins = $this->pdo->prepare('INSERT OR IGNORE INTO project_members (project_id, member_id) VALUES (?, ?)');
        foreach ($memberIds as $mId) {
            $mId = trim((string)$mId);
            if ($mId !== '') {
                $ins->execute([$projectId, $mId]);
            }
        }
    }

    public function addMemberToProject(string $projectId, string $memberId): void
    {
        $ins = $this->pdo->prepare('INSERT OR IGNORE INTO project_members (project_id, member_id) VALUES (?, ?)');
        $ins->execute([$projectId, $memberId]);
    }

    public function removeMemberFromProject(string $projectId, string $memberId): void
    {
        $del = $this->pdo->prepare('DELETE FROM project_members WHERE project_id = ? AND member_id = ?');
        $del->execute([$projectId, $memberId]);
    }

    public function create(array $data): string
    {
        $id = !empty($data['id']) ? $data['id'] : generateId('PRJ');
        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $startDate = $data['start_date'] ?? ($data['startDate'] ?? '');
        $endDate = $data['end_date'] ?? ($data['endDate'] ?? '');
        $status = $data['status'] ?? 'active';

        if ($name === '') {
            throw new InvalidArgumentException('Tên dự án không được để trống.');
        }

        $stmt = $this->pdo->prepare('INSERT INTO projects (id, name, description, start_date, end_date, status, updated_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
        $stmt->execute([$id, $name, $description, $startDate, $endDate, $status]);

        if (isset($data['member_ids']) && is_array($data['member_ids'])) {
            $this->setMembers($id, $data['member_ids']);
        }

        $this->log('projects', $id, 'create', 'Thêm dự án mới: ' . $name);
        return $id;
    }

    public function update(string $id, array $data, ?string $updatedAt = null): void
    {
        $this->checkConflict('projects', $id, $updatedAt);

        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $startDate = $data['start_date'] ?? ($data['startDate'] ?? '');
        $endDate = $data['end_date'] ?? ($data['endDate'] ?? '');
        $status = $data['status'] ?? 'active';

        if ($name === '') {
            throw new InvalidArgumentException('Tên dự án không được để trống.');
        }

        $stmt = $this->pdo->prepare('UPDATE projects SET name = ?, description = ?, start_date = ?, end_date = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$name, $description, $startDate, $endDate, $status, $id]);

        if (isset($data['member_ids']) && is_array($data['member_ids'])) {
            $this->setMembers($id, $data['member_ids']);
        }

        $this->log('projects', $id, 'update', 'Cập nhật dự án: ' . $name);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM projects WHERE id = ?');
        $stmt->execute([$id]);

        $this->log('projects', $id, 'delete', 'Xóa dự án');
    }
}
