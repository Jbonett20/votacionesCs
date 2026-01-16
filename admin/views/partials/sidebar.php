<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-vote-yea"></i>
        <span>Sistema Electoral</span>
    </div>
    
    <div class="sidebar-menu">
        <a href="dashboard.php" class="menu-item">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        
        <?php if (esSuperAdmin()): ?>
        <a href="usuarios.php" class="menu-item">
            <i class="fas fa-users-cog"></i>
            <span>Usuarios</span>
        </a>
        <a href="administradores.php" class="menu-item">
            <i class="fas fa-user-shield"></i>
            <span>Administradores</span>
        </a>
        <?php endif; ?>
        
        <?php if (esAdmin()): ?>
        <a href="lideres.php" class="menu-item">
            <i class="fas fa-user-tie"></i>
            <span>Líderes</span>
        </a>
        <?php endif; ?>
        
        <?php if (esLider()): ?>
        <a href="votantes.php" class="menu-item">
            <i class="fas fa-users"></i>
            <span>Votantes</span>
        </a>
        <?php endif; ?>
        
        <a href="reportes.php" class="menu-item">
            <i class="fas fa-chart-bar"></i>
            <span>Reportes</span>
        </a>
        
        <a href="perfil.php" class="menu-item">
            <i class="fas fa-user"></i>
            <span>Mi Perfil</span>
        </a>
    </div>
    
    <div class="sidebar-footer">
        <a href="../controllers/logout_controller.php" class="menu-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Cerrar Sesión</span>
        </a>
    </div>
</div>
