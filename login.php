<?php
// ── Configuración segura de sesión ────────────────────────
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);  // Cambiar a 1 si usas HTTPS
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', 1800);

session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: /views/dashboard.php');
    exit;
}

require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/includes/actividad.php';

// ── Directorio de datos para brute force ──────────────────
function getDataDir(): string {
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    return $dir;
}

// ── Brute Force Protection ────────────────────────────────
function getLoginAttempts(): int {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $file = getDataDir() . '/login_' . md5($ip) . '.json';

    if (!file_exists($file)) return 0;

    $data = json_decode(file_get_contents($file), true) ?: [];
    $now = time();

    // Limpiar si pasó el tiempo de bloqueo
    if (isset($data['locked_until']) && $now > $data['locked_until']) {
        unset($data['locked_until']);
        $data['attempts'] = 0;
        file_put_contents($file, json_encode($data));
        return 0;
    }

    if (isset($data['locked_until']) && $now <= $data['locked_until']) {
        return $LOGIN_MAX_ATTEMPTS + 1; // Bloqueado
    }

    return $data['attempts'] ?? 0;
}

function recordLoginAttempt(): void {
    global $LOGIN_MAX_ATTEMPTS, $LOGIN_LOCKOUT_TIME;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $file = getDataDir() . '/login_' . md5($ip) . '.json';

    $data = json_decode(file_exists($file) ? file_get_contents($file) : '{}', true) ?: [];
    $now = time();

    $data['attempts'] = ($data['attempts'] ?? 0) + 1;
    $data['last_attempt'] = $now;

    if ($data['attempts'] >= $LOGIN_MAX_ATTEMPTS) {
        $data['locked_until'] = $now + $LOGIN_LOCKOUT_TIME;
    }

    file_put_contents($file, json_encode($data));
}

function isLoginLocked(): bool {
    global $LOGIN_LOCKOUT_TIME;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $file = getDataDir() . '/login_' . md5($ip) . '.json';

    if (!file_exists($file)) return false;

    $data = json_decode(file_get_contents($file), true) ?: [];
    $now = time();

    return isset($data['locked_until']) && $now <= $data['locked_until'];
}

function getLockoutRemaining(): int {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $file = getDataDir() . '/login_' . md5($ip) . '.json';

    if (!file_exists($file)) return 0;

    $data = json_decode(file_get_contents($file), true) ?: [];
    $now = time();

    if (isset($data['locked_until']) && $now <= $data['locked_until']) {
        return $data['locked_until'] - $now;
    }
    return 0;
}

// ── CSRF Token ────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Procesar Login ────────────────────────────────────────
$error = '';
$locked = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isLoginLocked()) {
        $locked = true;
        $remaining = getLockoutRemaining();
        $error = "Demasiados intentos. Espera {$remaining} segundos.";
    } else {
        // Validar CSRF
        $csrf = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
            $error = 'Token de seguridad inválido. Recarga la página.';
        } else {
            require_once __DIR__ . '/config/database.php';

            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($username === '' || $password === '') {
                $error = 'Todos los campos son obligatorios';
            } else {
                $stmt = $pdo->prepare('SELECT id, username, password_hash, rol, activo FROM usuarios WHERE username = :username');
                $stmt->execute([':username' => $username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    if (!$user['activo']) {
                        $error = 'Tu cuenta está desactivada. Contacta al administrador.';
                    } else {
                        // Login exitoso — limpiar intentos
                        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                        $file = getDataDir() . '/login_' . md5($ip) . '.json';
                        if (file_exists($file)) unlink($file);

                        // Regenerar sesión
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['rol'] = $user['rol'];
                        $_SESSION['user_admin'] = ($user['rol'] === 'admin' || $user['rol'] === 'root');
                        $_SESSION['user_root']  = ($user['rol'] === 'root');
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                        registrarActividad('login', 'Inicio de sesión');
                        header('Location: /views/dashboard.php');
                        exit;
                    }
                } else {
                    registrarActividad('login_fallido', 'Usuario: ' . substr($username, 0, 50));
                    recordLoginAttempt();
                    $attempts = getLoginAttempts();
                    global $LOGIN_MAX_ATTEMPTS;
                    $remaining = $LOGIN_MAX_ATTEMPTS - $attempts;

                    if ($remaining > 0) {
                        $error = "Credenciales inválidas. Te quedan {$remaining} intentos.";
                    } else {
                        $locked = true;
                        $lockTime = getLockoutRemaining();
                        $error = "Cuenta bloqueada temporalmente. Espera {$lockTime} segundos.";
                    }
                }
            }
        }
    }
}

// Regenerar CSRF token para el form
$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — ALERTA</title>
    <link href="/assets/lib/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/lib/fontawesome/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="login-page">
    <div class="container d-flex align-items-center justify-content-center min-vh-100">
        <div class="login-card">
            <div class="card-body">
                <div class="login-icon">
                    <img src="assets/img/icono.png" alt="ALERTA">
                </div>
                <h3 class="text-center">ALERTA</h3>
                <p class="login-subtitle text-center">Sistema de monitoreo de alertas</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <div class="mb-3">
                        <label for="username" class="form-label">Usuario</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="username" name="username" required autofocus maxlength="50">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required maxlength="128">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold" <?php echo $locked ? 'disabled' : ''; ?>>
                        <i class="fas fa-arrow-right"></i> Iniciar sesión
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
