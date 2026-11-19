<?php

require_once __DIR__ . '/BaseModel.php';

class ActivityModel extends BaseModel
{
    public function log(string $entity, string $entityId, string $action, ?string $message = null): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO activity_log (entity, entity_id, action, message) VALUES (?, ?, ?, ?)');
        $stmt->execute([$entity, $entityId, $action, $message]);
    }

    public function getRecent(int $limit = 10): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->pdo->prepare('SELECT * FROM activity_log ORDER BY created_at DESC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

