<?php
/**
 * GUARDAR CONFIGURACIÓN DEL SISTEMA — Solo administradores + CSRF
 * Body JSON: { csrf_token, nombre_sistema }
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/actividad.php';

if (!esAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado. Solo el administrador.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$csrf = $input['csrf_token'] ?? '';
if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido']);
    exit;
}

$nombreSistema = trim($input['nombre_sistema'] ?? '');
$nombreSistema = htmlspecialchars($nombreSistema, ENT_QUOTES, 'UTF-8');
$nombreSistema = mb_substr($nombreSistema, 0, 40);

if ($nombreSistema === '') {
    http_response_code(400);
    echo json_encode(['error' => 'El nombre del sistema es obligatorio.']);
    exit;
}

try {
    $chk = $pdo->prepare('SELECT COUNT(*) AS c FROM configuracion WHERE clave = :clave');
    $chk->execute([':clave' => 'nombre_sistema']);

    if ((int)$chk->fetch()['c'] > 0) {
        $stmt = $pdo->prepare('UPDATE configuracion SET valor = :valor WHERE clave = :clave');
    } else {
        $stmt = $pdo->prepare('INSERT INTO configuracion (clave, valor) VALUES (:clave, :valor)');
    }
    $stmt->execute([':clave' => 'nombre_sistema', ':valor' => $nombreSistema]);
} catch (PDOException $e) {
    global $pdo;
    try {
        $stmt = $pdo->prepare('CREATE TABLE IF NOT EXISTS configuracion (clave VARCHAR(50) PRIMARY KEY, valor TEXT NOT NULL)');
        $stmt->execute();
    } catch (PDOException $e2) {
        http_response_code(500);
        echo json_encode(['error' => 'Error interno']);
        exit;
    }
    http_response_code(500);
    echo json_encode(['error' => 'Error al guardar la configuración']);
    exit;
}

registrarActividad('config_sistema', 'Nombre del sistema: ' . $nombreSistema);
echo json_encode(['success' => true, 'mensaje' => 'Configuración guardada correctamente.']);