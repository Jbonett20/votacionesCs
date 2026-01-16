<div class="topbar">
    <button class="toggle-sidebar" id="toggleSidebar">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="topbar-right">
        <div class="user-info dropdown">
            <div class="user-avatar dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer;">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="user-details">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></span>
                <span class="user-role">
                    <?php if (esSuperAdmin()): ?>
                        <i class="fas fa-crown text-warning"></i> <?php echo htmlspecialchars($_SESSION['usuario_rol_nombre']); ?>
                    <?php elseif (esAdmin()): ?>
                        <i class="fas fa-user-shield text-primary"></i> <?php echo htmlspecialchars($_SESSION['usuario_rol_nombre']); ?>
                    <?php else: ?>
                        <i class="fas fa-user-tie text-info"></i> <?php echo htmlspecialchars($_SESSION['usuario_rol_nombre']); ?>
                    <?php endif; ?>
                </span>
            </div>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="perfil.php"><i class="fas fa-user-circle"></i> Mi Perfil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="../controllers/logout_controller.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></li>
            </ul>
        </div>
    </div>
</div>
