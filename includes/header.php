<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo ?? 'ALERTA'; ?> — ALERTA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body>

<div class="app-layout">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon"><img src="../assets/img/icono.png" alt="ALERTA"></div>
            <span class="brand-text">ALERTA</span>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Dashboard</span>
            </a>
            <a href="mapa.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'mapa.php' ? 'active' : ''; ?>">
                <i class="fas fa-map-marked-alt"></i>
                <span>Mapa</span>
            </a>
            <a href="alertas.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'alertas.php' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i>
                <span>Alertas</span>
            </a>
            <a href="usuarios.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'usuarios.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Usuarios</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></span>
                <?php if (($_SESSION['user_admin'] ?? false) === true): ?>
                    <span class="badge-admin">admin</span>
                <?php endif; ?>
            </div>
            <a href="cuenta.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'cuenta.php' ? 'active' : ''; ?>">
                <i class="fas fa-key"></i>
                <span>Mi cuenta</span>
            </a>
            <a href="../logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Cerrar sesión</span>
            </a>
        </div>
    </aside>

    <div class="main-area">
        <header class="topbar">
            <h5><?php echo $titulo ?? 'ALERTA'; ?></h5>
            <div class="topbar-right">
                <div class="dropdown dropdown-notif" id="notifDropdown">
                    <button class="btn btn-notif" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-bell"></i>
                        <span class="notif-badge" id="notifBadge" style="display:none;">0</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end notif-menu" id="notifMenu">
                        <div class="notif-header">
                            <span>Notificaciones</span>
                        </div>
                        <div class="notif-list" id="notifList">
                            <div class="notif-empty">Cargando...</div>
                        </div>
                        <a class="dropdown-item notif-footer" href="alertas.php">Ver todas las alertas</a>
                    </div>
                </div>
                <span class="user-badge">
                    <i class="fas fa-user-circle"></i>
                    <?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>
                </span>
            </div>
        </header>
        <div class="content">
