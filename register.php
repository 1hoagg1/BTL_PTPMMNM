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
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($name === '' || $email === '' || $username === '' || $password === '') {
        $errorMessage = 'Vui lòng điền đầy đủ thông tin.';
    } elseif ($password !== $confirmPassword) {
        $errorMessage = 'Mật khẩu xác nhận không khớp.';
    } else {
        $authController = new AuthController($pdo);
        $userId = $authController->register($name, $email, $username, $password);

        if ($userId) {
            header('Location: dashboard.php');
            exit;
        }

        $errorMessage = 'Email hoặc tên đăng nhập đã tồn tại.';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký | DevTrack</title>
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
                <h1>Đăng ký tài khoản</h1>
                <p>Tạo tài khoản để theo dõi dự án, lỗi và phát hành.</p>
            </div>

            <?php if ($errorMessage !== ''): ?>
                <div class="flash error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" class="auth-form">
                <label>
                    <span>Họ và tên</span>
                    <input type="text" name="name" placeholder="Nguyễn Văn A" required>
                </label>
                <label>
                    <span>Email</span>
                    <input type="email" name="email" placeholder="example@email.com" required>
                </label>
                <label>
                    <span>Tên đăng nhập</span>
                    <input type="text" name="username" placeholder="username" required>
                </label>
                <label>
                    <span>Mật khẩu</span>
                    <input type="password" name="password" placeholder="••••••••" required>
                </label>
                <label>
                    <span>Xác nhận mật khẩu</span>
                    <input type="password" name="confirm_password" placeholder="Nhập lại mật khẩu" required>
                </label>
                <button type="submit" class="primary-button auth-button">Tạo tài khoản</button>
            </form>

            <div class="auth-footer">
                <span>Đã có tài khoản?</span>
                <a href="login.php">Đăng nhập</a>
            </div>
        </div>
    </div>
</body>
</html>
