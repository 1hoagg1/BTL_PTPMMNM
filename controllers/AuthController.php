<?php

require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../helpers/auth.php';

class AuthController
{
    private UserModel $userModel;
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->userModel = new UserModel($pdo);
    }

    public function login(string $login, string $password): ?array
    {
        $user = $this->userModel->verifyPassword($login, $password);
        if ($user) {
            startSessionSafe();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['current_user_role'] = $user['role'];
            return $user;
        }

        return null;
    }

    public function register(string $name, string $email, string $username, string $password): ?string
    {
        $name = trim($name);
        $email = trim($email);
        $username = trim($username);

        if ($name === '' || $email === '' || $username === '' || $password === '') {
            return null;
        }

        $existing = $this->userModel->findByUsername($username);
        if ($existing) {
            return null;
        }

        $userId = $this->userModel->create($name, $email, $username, $password, 'member');
        startSessionSafe();
        $_SESSION['user_id'] = $userId;
        $_SESSION['current_user_role'] = 'member';
        return $userId;
    }

    public function logout(): void
    {
        startSessionSafe();
        session_unset();
        session_destroy();
    }

    public function getCurrentUser(): ?array
    {
        return currentUser($this->pdo);
    }
}
