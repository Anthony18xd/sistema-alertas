<?php
/**
 * GESTIÓN DE USUARIOS — Solo administradores
 * Acciones: crear | editar | activo | eliminar | reset_password
 * Método: POST, body en JSON (incluye csrf_token)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/actividad.php';

if (!esAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado. Solo el administrador puede gestionar usuarios.']);
    exit;
}

// ── Validar CSRF ──────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$csrf = $input['csrf_token'] ?? '';
if (empty($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido']);
    exit;
}

$accion = $input['accion'] ?? '';
$accionesValidas = ['crear', 'editar', 'activo', 'eliminar', 'reset_password'];

if (!in_array($accion, $accionesValidas, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Acción inválida']);
    exit;
}

// ── Validaciones de formato ───────────────────────────────
function validarUsername(string $username): bool {
    return preg_match('/^[a-zA-Z0-9_.]{3,50}$/', $username) === 1;
}

function validarPassword(string $password): bool {
    return mb_strlen($password) >= 8 && mb_strlen($password) <= 128;
}

function logUsuarioError(string $mensaje): void {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
    }
    file_put_contents(
        $logDir . '/security.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $mensaje . "\n",
        FILE_APPEND | LOCK_EX
    );
}

try {
    switch ($accion) {

        // ── Crear usuario ────────────────────────────────
        case 'crear':
            $username = trim($input['username'] ?? '');
            $rol      = $input['rol'] ?? 'operador';
            $password = $input['password'] ?? '';

            if (!in_array($rol, ['admin', 'operador', 'root'], true)) {
                http_response_code(400);
                echo json_encode(['error' => 'Rol inválido. Usa: admin, operador o root']);
                exit;
            }
            if ($rol === 'root' && !esRoot()) {
                http_response_code(403);
                echo json_encode(['error' => 'Solo el superadministrador (root) puede crear cuentas root.']);
                exit;
            }
            if (!validarUsername($username)) {
                http_response_code(400);
                echo json_encode(['error' => 'Usuario inválido. Usa 3-50 caracteres: letras, números, punto o guion bajo.']);
                exit;
            }
            if (!validarPassword($password)) {
                http_response_code(400);
                echo json_encode(['error' => 'La contraseña debe tener mínimo 8 caracteres (máx. 128).']);
                exit;
            }

            $exists = $pdo->prepare('SELECT COUNT(*) AS c FROM usuarios WHERE username = :username');
            $exists->execute([':username' => $username]);
            if ((int)$exists->fetch()['c'] > 0) {
                http_response_code(409);
                echo json_encode(['error' => 'Ya existe un usuario con ese nombre.']);
                exit;
            }

            $stmt = $pdo->prepare('INSERT INTO usuarios (username, password_hash, rol, activo) VALUES (:u, :p, :r, 1)');
            $stmt->execute([
                ':u' => $username,
                ':p' => password_hash($password, PASSWORD_BCRYPT),
                ':r' => $rol,
            ]);

            registrarActividad('usuario_creado', "Usuario: {$username}, rol: {$rol}");
            echo json_encode(['success' => true, 'mensaje' => "Usuario '{$username}' creado correctamente."]);
            break;

        // ── Editar usuario / rol ─────────────────────────
        case 'editar':
            $id       = (int)($input['id'] ?? 0);
            $username = trim($input['username'] ?? '');
            $rol      = $input['rol'] ?? '';

            if ($id <= 0 || $id === (int)$_SESSION['user_id']) {
                http_response_code(400);
                echo json_encode(['error' => 'No puedes editar tu propia cuenta desde aquí. Usa "Mi cuenta".']);
                exit;
            }
            if (!in_array($rol, ['admin', 'operador', 'root'], true)) {
                http_response_code(400);
                echo json_encode(['error' => 'Rol inválido. Usa: admin, operador o root']);
                exit;
            }

            // El objetivo actual
            $stmtRolActual = $pdo->prepare('SELECT rol FROM usuarios WHERE id = :id');
            $stmtRolActual->execute([':id' => $id]);
            $rolActual = $stmtRolActual->fetch()['rol'] ?? '';

            // Gestionar cuentas root solo está permitido para root
            if ($rolActual === 'root' && !esRoot()) {
                http_response_code(403);
                echo json_encode(['error' => 'Solo el superadministrador (root) puede gestionar cuentas root.']);
                exit;
            }
            // Asignar el rol root solo está permitido para root
            if ($rol === 'root' && !esRoot()) {
                http_response_code(403);
                echo json_encode(['error' => 'Solo el superadministrador (root) puede asignar el rol root.']);
                exit;
            }

            if (!validarUsername($username)) {
                http_response_code(400);
                echo json_encode(['error' => 'Usuario inválido. Usa 3-50 caracteres: letras, números, punto o guion bajo.']);
                exit;
            }

            $exists = $pdo->prepare('SELECT COUNT(*) AS c FROM usuarios WHERE username = :username AND id <> :id');
            $exists->execute([':username' => $username, ':id' => $id]);
            if ((int)$exists->fetch()['c'] > 0) {
                http_response_code(409);
                echo json_encode(['error' => 'Ya existe otro usuario con ese nombre.']);
                exit;
            }

            // Evitar dejar al sistema sin administradores
            $stmt = $pdo->prepare('UPDATE usuarios SET username = :u, rol = :r WHERE id = :id');
            $stmt->execute([':u' => $username, ':r' => $rol, ':id' => $id]);

            if ($stmt->rowCount() > 0) {
                registrarActividad('usuario_editado', "ID: {$id}, usuario: {$username}, rol: {$rol}");
                echo json_encode(['success' => true, 'mensaje' => 'Usuario actualizado correctamente.']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Usuario no encontrado.']);
            }
            break;

        // ── Activar / desactivar ─────────────────────────
        case 'activo':
            $id   = (int)($input['id'] ?? 0);
            $activo = (int)($input['activo'] ?? -1);

            if ($id <= 0 || $id === (int)$_SESSION['user_id']) {
                http_response_code(400);
                echo json_encode(['error' => 'No puedes desactivar tu propia cuenta.']);
                exit;
            }
            if (!in_array($activo, [0, 1], true)) {
                http_response_code(400);
                echo json_encode(['error' => 'Valor de estado inválido.']);
                exit;
            }

            // No dejar al sistema sin un administrador activo
            if ($activo === 0) {
                $stmtAdmin = $pdo->prepare("SELECT COUNT(*) AS c FROM usuarios WHERE rol = 'admin' AND activo = 1");
                $stmtAdmin->execute();
                $adminsActivos = (int)$stmtAdmin->fetch()['c'];

                $stmtObjetivo = $pdo->prepare('SELECT rol FROM usuarios WHERE id = :id');
                $stmtObjetivo->execute([':id' => $id]);
                $rolObjetivo = $stmtObjetivo->fetch()['rol'] ?? null;

                // Solo root puede desactivar cuentas root
                if ($rolObjetivo === 'root' && !esRoot()) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Solo el superadministrador (root) puede desactivar cuentas root.']);
                    exit;
                }

                // Nunca dejar al sistema sin ningún root activo
                if ($rolObjetivo === 'root') {
                    $stmtRoot = $pdo->prepare("SELECT COUNT(*) AS c FROM usuarios WHERE rol = 'root' AND activo = 1");
                    $stmtRoot->execute();
                    $rootsActivos = (int)$stmtRoot->fetch()['c'];
                    if ($rootsActivos <= 1) {
                        http_response_code(409);
                        echo json_encode(['error' => 'No puedes desactivar al último superadministrador (root) activo.']);
                        exit;
                    }
                }

                if ($rolObjetivo === 'admin' && $adminsActivos <= 1) {
                    http_response_code(409);
                    echo json_encode(['error' => 'No puedes desactivar al último administrador activo.']);
                    exit;
                }
            }

            $stmt = $pdo->prepare('UPDATE usuarios SET activo = :a WHERE id = :id');
            $stmt->execute([':a' => $activo, ':id' => $id]);

            if ($stmt->rowCount() > 0) {
                registrarActividad($activo ? 'usuario_activo' : 'usuario_inactivo', "ID: {$id}");
                $mensaje = $activo ? 'Usuario activado correctamente.' : 'Usuario desactivado correctamente.';
                echo json_encode(['success' => true, 'mensaje' => $mensaje]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Usuario no encontrado.']);
            }
            break;

        // ── Eliminar usuario ─────────────────────────────
        case 'eliminar':
            $id = (int)($input['id'] ?? 0);

            if ($id <= 0 || $id === (int)$_SESSION['user_id']) {
                http_response_code(400);
                echo json_encode(['error' => 'No puedes eliminar tu propia cuenta.']);
                exit;
            }

            $stmtObjetivo = $pdo->prepare('SELECT rol, activo FROM usuarios WHERE id = :id');
            $stmtObjetivo->execute([':id' => $id]);
            $objetivo = $stmtObjetivo->fetch();

            if (!$objetivo) {
                http_response_code(404);
                echo json_encode(['error' => 'Usuario no encontrado.']);
                exit;
            }

            // Solo root puede eliminar cuentas root
            if ($objetivo['rol'] === 'root' && !esRoot()) {
                http_response_code(403);
                echo json_encode(['error' => 'Solo el superadministrador (root) puede eliminar cuentas root.']);
                exit;
            }

            if ($objetivo['rol'] === 'root') {
                $stmtRoot = $pdo->prepare("SELECT COUNT(*) AS c FROM usuarios WHERE rol = 'root' AND activo = 1");
                $stmtRoot->execute();
                $rootsActivos = (int)$stmtRoot->fetch()['c'];
                if ($rootsActivos <= 1) {
                    http_response_code(409);
                    echo json_encode(['error' => 'No puedes eliminar al último superadministrador (root) activo.']);
                    exit;
                }
            }

            if ($objetivo['rol'] === 'admin') {
                $stmtAdmin = $pdo->prepare("SELECT COUNT(*) AS c FROM usuarios WHERE rol = 'admin' AND activo = 1");
                $stmtAdmin->execute();
                $adminsActivos = (int)$stmtAdmin->fetch()['c'];
                if ($adminsActivos <= 1) {
                    http_response_code(409);
                    echo json_encode(['error' => 'No puedes eliminar al último administrador activo.']);
                    exit;
                }
            }

            $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = :id');
            $stmt->execute([':id' => $id]);

            registrarActividad('usuario_eliminado', 'ID: ' . $id . ' (' . ($objetivo['rol'] ?? '?') . ')');
            echo json_encode(['success' => true, 'mensaje' => 'Usuario eliminado correctamente.']);
            break;

        // ── Resetear contraseña ──────────────────────────
        case 'reset_password':
            $id       = (int)($input['id'] ?? 0);
            $password = $input['password'] ?? '';

            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'ID inválido.']);
                exit;
            }
            if (!validarPassword($password)) {
                http_response_code(400);
                echo json_encode(['error' => 'La contraseña debe tener mínimo 8 caracteres (máx. 128).']);
                exit;
            }

            $stmt = $pdo->prepare('UPDATE usuarios SET password_hash = :p WHERE id = :id');
            $stmt->execute([':p' => password_hash($password, PASSWORD_BCRYPT), ':id' => $id]);

            if ($stmt->rowCount() > 0) {
                registrarActividad('reset_password', 'ID: ' . $id);
                echo json_encode(['success' => true, 'mensaje' => 'Contraseña restablecida correctamente.']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Usuario no encontrado.']);
            }
            break;
    }
} catch (PDOException $e) {
    logUsuarioError('DB error en gestionar_usuario: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno al procesar la solicitud.']);
}