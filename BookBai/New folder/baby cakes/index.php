<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';

header('Location: ' . (bookbai_current_user() ? 'dashboard.php' : 'login.php'));
exit;
