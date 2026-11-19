<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/controllers/AuthController.php';
startSessionSafe();

$pdo = getPdo();
initDatabase($pdo);

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$errorMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim((string) ($_POST['login'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $authController = new AuthController($pdo);
    $user = $authController->login($login, $password);

    if ($user) {
        header('Location: dashboard.php');
        exit;
    }

    $errorMessage = 'Tên đăng nhập hoặc mật khẩu không đúng.';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập | DevTrack</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="auth-body">
    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-brand">
                    <span class="brand-mark"><span></span><span></span><span></span></span>
                    <span>dev<span class="brand-light">track</span></span>
                </div>
                <h1>Đăng nhập</h1>
                <p>Quản lý lỗi, công việc và phiên bản phát hành.</p>
            </div>

            <?php if ($errorMessage !== ''): ?>
                <div class="flash error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" class="auth-form">
                <label>
                    <span>Tên đăng nhập / Email</span>
                    <input type="text" name="login" placeholder="Nhập tên đăng nhập hoặc email" required>
                </label>
                <label>
                    <span>Mật khẩu</span>
                    <input type="password" name="password" placeholder="••••••••" required>
                </label>
                <button type="submit" class="primary-button auth-button">Đăng nhập</button>
            </form>

            <div class="auth-footer">
                <span>Chưa có tài khoản?</span>
                <a href="register.php">Đăng ký ngay</a>
            </div>
        </div>
    </div>
</body>
</html>
