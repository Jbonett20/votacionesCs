<?php
// Obtener la página actual
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-vote-yea"></i>
        <span>Sistema Electoral</span>
    </div>
    
    <div class="sidebar-menu">
        <a href="dashboard.php" class="menu-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        
        <?php if (esSuperAdmin()): ?>
        <a href="usuarios.php" class="menu-item <?php echo ($current_page == 'usuarios.php') ? 'active' : ''; ?>">
            <i class="fas fa-users-cog"></i>
            <span>Usuarios Admin</span>
        </a>
        <?php endif; ?>
        
        <?php if (esSuperAdmin() || esAdmin()): ?>
        <a href="lideres.php" class="menu-item <?php echo ($current_page == 'lideres.php') ? 'active' : ''; ?>">
            <i class="fas fa-user-tie"></i>
            <span>Líderes</span>
        </a>
        <a href="votantes.php" class="menu-item <?php echo ($current_page == 'votantes.php') ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Votantes</span>
        </a>
        <?php endif; ?>
        
        <a href="duplicados.php" class="menu-item <?php echo ($current_page == 'duplicados.php') ? 'active' : ''; ?>">
            <i class="fas fa-exclamation-triangle"></i>
            <span>Votantes Duplicados</span>
        </a>
        
        <a href="reportes.php" class="menu-item <?php echo ($current_page == 'reportes.php') ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Reportes</span>
        </a>
        
        <a href="perfil.php" class="menu-item <?php echo ($current_page == 'perfil.php') ? 'active' : ''; ?>">
            <i class="fas fa-user-circle"></i>
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
