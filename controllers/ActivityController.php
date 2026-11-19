<?php

require_once __DIR__ . '/../models/ActivityModel.php';

class ActivityController
{
    private ActivityModel $activityModel;

    public function __construct(PDO $pdo)
    {
        $this->activityModel = new ActivityModel($pdo);
    }

    public function recent(int $limit = 10): array
    {
        return $this->activityModel->getRecent($limit);
    }

    public function log(string $entity, string $entityId, string $action, ?string $message = null): void
    {
        $this->activityModel->log($entity, $entityId, $action, $message);
    }
}

