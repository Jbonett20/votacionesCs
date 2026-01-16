<div class="topbar">
    <button class="toggle-sidebar" id="toggleSidebar">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="topbar-right">
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-details">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></span>
                <span class="user-role"><?php echo htmlspecialchars($_SESSION['usuario_rol_nombre']); ?></span>
            </div>
        </div>
    </div>
</div>
