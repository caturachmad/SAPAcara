<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
// includes/auth.php starts session; then destroy
session_destroy();
header('Location: ' . BASE_URL . '/modules/auth/login.php');
exit;