<?php

require_once __DIR__ . '/BaseModel.php';

class ReleaseModel extends BaseModel
{
    public function getAll(?string $projectId = null): array
    {
        if ($projectId !== null && $projectId !== '') {
            $stmt = $this->pdo->prepare('SELECT * FROM releases WHERE project_id = ? ORDER BY created_at DESC');
            $stmt->execute([$projectId]);
            return $stmt->fetchAll();
        }

        $stmt = $this->pdo->query('SELECT * FROM releases ORDER BY created_at DESC');
        return $stmt->fetchAll();
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM releases WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $release = $stmt->fetch();
        return $release ?: null;
    }

    public function create(array $data): string
    {
        $id = !empty($data['id']) ? $data['id'] : generateId('REL');
        $projectId = $data['project_id'] ?? ($data['projectId'] ?? null);
        $version = trim($data['version'] ?? '');
        $targetDate = $data['target_date'] ?? ($data['targetDate'] ?? null);
        $status = $data['status'] ?? 'planned';
        $releaseNotes = trim($data['release_notes'] ?? ($data['releaseNotes'] ?? ''));

        $checklist = normalizeArrayInput($data['checklist'] ?? []);
        $taskIds = normalizeArrayInput($data['task_ids'] ?? ($data['taskIds'] ?? []));
        $conditions = normalizeArrayInput($data['conditions'] ?? []);
        $changeList = normalizeArrayInput($data['change_list'] ?? ($data['changeList'] ?? []));

        if ($version === '') {
            throw new InvalidArgumentException('Phiên bản phát hành không được để trống.');
        }

        $stmt = $this->pdo->prepare('INSERT INTO releases (id, project_id, version, target_date, status, release_notes, checklist, task_ids, conditions, change_list, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
        $stmt->execute([
            $id,
            $projectId,
            $version,
            $targetDate,
            $status,
            $releaseNotes,
            json_encode($checklist, JSON_UNESCAPED_UNICODE),
            json_encode($taskIds, JSON_UNESCAPED_UNICODE),
            json_encode($conditions, JSON_UNESCAPED_UNICODE),
            json_encode($changeList, JSON_UNESCAPED_UNICODE)
        ]);

        $this->log('releases', $id, 'create', 'Tạo phiên bản phát hành: ' . $version);
        return $id;
    }

    public function update(string $id, array $data, ?string $updatedAt = null): void
    {
        $this->checkConflict('releases', $id, $updatedAt);

        $projectId = $data['project_id'] ?? ($data['projectId'] ?? null);
        $version = trim($data['version'] ?? '');
        $targetDate = $data['target_date'] ?? ($data['targetDate'] ?? null);
        $status = $data['status'] ?? 'planned';
        $releaseNotes = trim($data['release_notes'] ?? ($data['releaseNotes'] ?? ''));

        $checklist = normalizeArrayInput($data['checklist'] ?? []);
        $taskIds = normalizeArrayInput($data['task_ids'] ?? ($data['taskIds'] ?? []));
        $conditions = normalizeArrayInput($data['conditions'] ?? []);
        $changeList = normalizeArrayInput($data['change_list'] ?? ($data['changeList'] ?? []));

        if ($version === '') {
            throw new InvalidArgumentException('Phiên bản phát hành không được để trống.');
        }

        $stmt = $this->pdo->prepare('UPDATE releases SET project_id = ?, version = ?, target_date = ?, status = ?, release_notes = ?, checklist = ?, task_ids = ?, conditions = ?, change_list = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([
            $projectId,
            $version,
            $targetDate,
            $status,
            $releaseNotes,
            json_encode($checklist, JSON_UNESCAPED_UNICODE),
            json_encode($taskIds, JSON_UNESCAPED_UNICODE),
            json_encode($conditions, JSON_UNESCAPED_UNICODE),
            json_encode($changeList, JSON_UNESCAPED_UNICODE),
            $id
        ]);

        $this->log('releases', $id, 'update', 'Cập nhật phiên bản phát hành: ' . $version);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM releases WHERE id = ?');
        $stmt->execute([$id]);

        $this->log('releases', $id, 'delete', 'Xóa phiên bản phát hành');
    }

    public function exportText(string $id): ?array
    {
        $release = $this->getById($id);
        if (!$release) {
            return null;
        }

        $taskList = decodeJsonList($release['task_ids'] ?? '[]');
        $conditions = decodeJsonList($release['conditions'] ?? '[]');
        $changes = decodeJsonList($release['change_list'] ?? '[]');
        $checklist = decodeJsonList($release['checklist'] ?? '[]');

        $output = "====================================================\n";
        $output .= "               THÔNG TIN PHÁT HÀNH                 \n";
        $output .= "====================================================\n\n";
        $output .= "Phiên bản: {$release['version']}\n";
        $output .= "Trạng thái: {$release['status']}\n";
        $output .= "Ngày dự kiến: {$release['target_date']}\n\n";
        $output .= "Ghi chú phát hành:\n" . ($release['release_notes'] ?? '(Không có)') . "\n\n";
        $output .= "Danh sách công việc gắn kèm:\n" . (empty($taskList) ? '- Không có' : '- ' . implode("\n- ", $taskList)) . "\n\n";
        $output .= "Điều kiện phát hành:\n" . (empty($conditions) ? '- Không có' : '- ' . implode("\n- ", $conditions)) . "\n\n";
        $output .= "Danh sách thay đổi:\n" . (empty($changes) ? '- Không có' : '- ' . implode("\n- ", $changes)) . "\n\n";
        $output .= "Checklist kiểm tra:\n" . (empty($checklist) ? '- Không có' : '- ' . implode("\n- ", $checklist)) . "\n";

        $filename = 'release-' . preg_replace('/[^a-zA-Z0-9._-]+/', '-', $release['version']) . '.txt';

        return [
            'filename' => $filename,
            'content' => $output
        ];
    }
}
