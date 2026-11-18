<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirectByRole();
} else {
    header('Location: ' . getBasePath() . '/login.php');
    exit;
}
