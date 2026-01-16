<?php
require_once '../config/db.php';
require_once '../config/session.php';
require_once '../models/LiderModel.php';

// Validar sesión
requerirSesion();

// Obtener estadísticas según rol
$stats = [];

if (esSuperAdmin()) {
    // SuperAdmin ve todo
    $stats['total_usuarios'] = DB::queryOneValue("SELECT COUNT(*) FROM usuarios WHERE id_estado = 1");
    $stats['total_administradores'] = DB::queryOneValue("SELECT COUNT(*) FROM usuarios WHERE id_rol = 2 AND id_estado = 1");
    $stats['total_lideres'] = LiderModel::contarLideres($_SESSION['usuario_id'], 1);
    $stats['total_votantes'] = DB::queryOneValue("SELECT COUNT(*) FROM votantes WHERE id_estado = 1");
} elseif (esAdmin()) {
    // Admin ve solo sus líderes y votantes que le pertenecen
    $stats['total_lideres'] = LiderModel::contarLideres($_SESSION['usuario_id'], 2);
    // Contar votantes de sus líderes o registrados directamente por él
    $stats['total_votantes'] = DB::queryOneValue(
        "SELECT COUNT(DISTINCT v.id_votante) 
         FROM votantes v
         LEFT JOIN lideres l ON v.id_lider = l.id_lider
         WHERE v.id_estado = 1 
         AND (l.id_usuario_creador = ? OR v.id_administrador_directo = ?)",
        $_SESSION['usuario_id'],
        $_SESSION['usuario_id']
    );
} else {
    // Líder ve solo sus votantes
    $idLider = DB::queryOneValue("SELECT id_lider FROM lideres WHERE usuario = ?", $_SESSION['usuario_username']);
    if ($idLider) {
        $stats['mis_votantes'] = DB::queryOneValue("SELECT COUNT(*) FROM votantes WHERE id_lider = ? AND id_estado = 1", $idLider);
    } else {
        $stats['mis_votantes'] = 0;
    }
}

// Obtener actividad reciente
$actividad_reciente = [];
if (esSuperAdmin()) {
    // SuperAdmin ve toda la actividad
    $actividad_reciente = DB::queryAllRows(
        "SELECT v.*, l.nombres as lider_nombres, l.apellidos as lider_apellidos,
                CONCAT(u.nombres, ' ', u.apellidos) as admin_directo
         FROM votantes v
         LEFT JOIN lideres l ON v.id_lider = l.id_lider
         LEFT JOIN usuarios u ON v.id_administrador_directo = u.id_usuario
         ORDER BY v.fecha_creacion DESC
         LIMIT 5"
    );
} elseif (esAdmin()) {
    // Admin solo ve actividad de sus líderes o registros directos
    $actividad_reciente = DB::queryAllRows(
        "SELECT v.*, l.nombres as lider_nombres, l.apellidos as lider_apellidos,
                CONCAT(u.nombres, ' ', u.apellidos) as admin_directo
         FROM votantes v
         LEFT JOIN lideres l ON v.id_lider = l.id_lider
         LEFT JOIN usuarios u ON v.id_administrador_directo = u.id_usuario
         WHERE (l.id_usuario_creador = ? OR v.id_administrador_directo = ?)
         ORDER BY v.fecha_creacion DESC
         LIMIT 5",
        $_SESSION['usuario_id'],
        $_SESSION['usuario_id']
    );
} else {
    $idLider = DB::queryOneValue("SELECT id_lider FROM lideres WHERE usuario = ?", $_SESSION['usuario_username']);
    if ($idLider) {
        $actividad_reciente = DB::queryAllRows(
            "SELECT * FROM votantes WHERE id_lider = ? ORDER BY fecha_creacion DESC LIMIT 5",
            $idLider
        );
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Votaciones</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-vote-yea"></i>
            <span>Sistema Electoral</span>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="menu-item active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <?php if (esSuperAdmin()): ?>
            <a href="usuarios.php" class="menu-item">
                <i class="fas fa-users-cog"></i>
                <span>Usuarios</span>
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
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
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
        
        <!-- Page Content -->
        <div class="page-content">
            <div class="page-header">
                <h1>Dashboard</h1>
                <p>Bienvenido al sistema de gestión electoral</p>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <?php if (esSuperAdmin()): ?>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="stat-card card-primary">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['total_usuarios']); ?></h3>
                            <p>Total Usuarios</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="stat-card card-success">
                        <div class="stat-icon">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['total_administradores']); ?></h3>
                            <p>Administradores</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="stat-card card-warning">
                        <div class="stat-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['total_lideres']); ?></h3>
                            <p>Líderes</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="stat-card card-info">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['total_votantes']); ?></h3>
                            <p>Votantes</p>
                        </div>
                    </div>
                </div>
                
                <?php elseif (esAdmin()): ?>
                <div class="col-12 col-sm-6 col-lg-6">
                    <div class="stat-card card-warning">
                        <div class="stat-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['total_lideres']); ?></h3>
                            <p>Líderes Registrados</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-12 col-sm-6 col-lg-6">
                    <div class="stat-card card-info">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['total_votantes']); ?></h3>
                            <p>Total Votantes</p>
                        </div>
                    </div>
                </div>
                
                <?php else: ?>
                <div class="col-12">
                    <div class="stat-card card-primary">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['mis_votantes']); ?></h3>
                            <p>Mis Votantes Registrados</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-clock"></i> Actividad Reciente</h5>
                </div>
                <div class="card-body">
                    <?php if (count($actividad_reciente) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Votante</th>
                                    <th>Identificación</th>
                                    <?php if (esSuperAdmin() || esAdmin()): ?>
                                    <th>Líder</th>
                                    <?php endif; ?>
                                    <th>Sexo</th>
                                    <th>Fecha Registro</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($actividad_reciente as $registro): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($registro['nombres'] . ' ' . $registro['apellidos']); ?></td>
                                    <td><?php echo htmlspecialchars($registro['identificacion']); ?></td>
                                    <?php if (esSuperAdmin() || esAdmin()): ?>
                                    <td><?php echo htmlspecialchars($registro['lider_nombres'] . ' ' . $registro['lider_apellidos']); ?></td>
                                    <?php endif; ?>
                                    <td><?php echo $registro['sexo'] == 'M' ? 'Masculino' : ($registro['sexo'] == 'F' ? 'Femenino' : 'Otro'); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($registro['fecha_creacion'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-center text-muted">No hay actividad reciente</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
