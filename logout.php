<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/middleware/Auth.php';

Auth::logout();

header('Location: ' . BASE_URL . '/login.php');
exit;
