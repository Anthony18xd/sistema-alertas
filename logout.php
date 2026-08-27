<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/actividad.php';

$username = (string)($_SESSION['username'] ?? '');
$userId   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

registrarActividad('logout', 'Cierre de sesión', $userId, $username);

session_unset();
session_destroy();

header('Location: /login.php');
exit;