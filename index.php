<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/modules/dashboard/select.php');
} else {
    header('Location: ' . BASE_URL . '/modules/auth/login.php');
}
exit;
