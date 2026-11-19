<?php

require_once __DIR__ . '/../helpers/utils.php';
require_once __DIR__ . '/../helpers/auth.php';

abstract class BaseModel
{
    protected PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    protected function fetchRow(string $table, string $id): ?array
    {
        return fetchRow($this->pdo, $table, $id);
    }

    protected function checkConflict(string $table, string $id, ?string $updatedAt): void
    {
        ensureNoConflict($this->pdo, $table, $id, $updatedAt);
    }

    protected function log(string $entity, string $entityId, string $action, ?string $message = null): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO activity_log (entity, entity_id, action, message) VALUES (?, ?, ?, ?)');
        $stmt->execute([$entity, $entityId, $action, $message]);
    }
}

