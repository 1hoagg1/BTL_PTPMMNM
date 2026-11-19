<?php
require_once __DIR__ . '/db.php';
startSessionSafe();

if (!empty($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
}

header('Location: login.php');
exit;
