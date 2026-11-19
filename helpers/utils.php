<?php

/**
 * Common utilities and helper functions for DevTrack
 */

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('generateId')) {
    function generateId(string $prefix): string
    {
        return strtoupper($prefix . '-' . date('ymdHis') . '-' . rand(100, 999));
    }
}

if (!function_exists('slugify')) {
    function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim($value, '-');
    }
}

if (!function_exists('decodeJsonList')) {
    function decodeJsonList(?string $value): array
    {
        if (empty($value)) {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('encodeJsonList')) {
    function encodeJsonList(array $value): string
    {
        return json_encode(array_values($value), JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('normalizeArrayInput')) {
    function normalizeArrayInput(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value), fn ($item) => $item !== ''));
        }

        if (is_string($value)) {
            $parts = preg_split('/[\r\n,]+/', $value);
            return array_values(array_filter(array_map('trim', $parts), fn ($item) => $item !== ''));
        }

        return [];
    }
}

if (!function_exists('issueCommentsList')) {
    function issueCommentsList(?string $value): array
    {
        $decoded = json_decode($value ?? '[]', true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('jsonResponse')) {
    function jsonResponse(mixed $data, int $statusCode = 200): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

if (!function_exists('readJsonInput')) {
    function readJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return $_POST;
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : $_POST;
    }
}
