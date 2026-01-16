<?php
require_once '../config/db.php';
require_once '../config/session.php';
requerirSesion();

// Obtener datos del usuario actual
$usuario = DB::queryFirstRow(
    "SELECT u.*, r.nombre_rol, t.nombre_tipo
     FROM usuarios u
     INNER JOIN roles r ON u.id_rol = r.id_rol
     INNER JOIN tipos_identificacion t ON u.id_tipo_identificacion = t.id_tipo_identificacion
     WHERE u.id_usuario = ?",
    $_SESSION['usuario_id']
);

$nombre_completo = $usuario['nombres'] . ' ' . $usuario['apellidos'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Sistema de Votaciones</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .profile-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            color: #667eea;
            margin: 0 auto 20px;
        }
        .info-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #666;
        }
        .info-value {
            color: #333;
        }
        .role-badge {
            font-size: 14px;
            padding: 8px 16px;
            border-radius: 20px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include 'partials/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Topbar -->
            <?php include 'partials/topbar.php'; ?>
            
            <!-- Page Content -->
            <div class="page-content">
                <div class="page-header">
                    <h1><i class="fas fa-user-circle"></i> Mi Perfil</h1>
                    <p>Información de tu cuenta</p>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="profile-card text-center">
                            <div class="profile-avatar">
                                <?php 
                                // Icono según el rol
                                if ($usuario['id_rol'] == 1) {
                                    echo '<i class="fas fa-crown"></i>'; // SuperAdmin
                                } elseif ($usuario['id_rol'] == 2) {
                                    echo '<i class="fas fa-user-shield"></i>'; // Admin
                                } else {
                                    echo '<i class="fas fa-user-tie"></i>'; // Líder
                                }
                                ?>
                            </div>
                            <h3><?php echo htmlspecialchars($nombre_completo); ?></h3>
                            <p class="mb-2">
                                <span class="role-badge bg-light text-dark">
                                    <?php 
                                    if ($usuario['id_rol'] == 1) {
                                        echo '<i class="fas fa-crown"></i>';
                                    } elseif ($usuario['id_rol'] == 2) {
                                        echo '<i class="fas fa-user-shield"></i>';
                                    } else {
                                        echo '<i class="fas fa-user-tie"></i>';
                                    }
                                    echo ' ' . htmlspecialchars($usuario['nombre_rol']); 
                                    ?>
                                </span>
                            </p>
                            <p class="mb-0">
                                <i class="fas fa-at"></i> <?php echo htmlspecialchars($usuario['usuario']); ?>
                            </p>
                        </div>
                        
                        <div class="info-card">
                            <h5 class="mb-3"><i class="fas fa-cog"></i> Acciones</h5>
                            <button class="btn btn-primary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#modalCambiarClave">
                                <i class="fas fa-key"></i> Cambiar Mi Contraseña
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <div class="info-card">
                            <h5 class="mb-4"><i class="fas fa-info-circle"></i> Información Personal</h5>
                            
                            <div class="info-row">
                                <span class="info-label"><i class="fas fa-id-card"></i> Identificación:</span>
                                <span class="info-value"><?php echo htmlspecialchars($usuario['identificacion']); ?></span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label"><i class="fas fa-file-alt"></i> Tipo de Identificación:</span>
                                <span class="info-value"><?php echo htmlspecialchars($usuario['nombre_tipo']); ?></span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label"><i class="fas fa-venus-mars"></i> Sexo:</span>
                                <span class="info-value">
                                    <?php 
                                    echo $usuario['sexo'] === 'M' ? 'Masculino' : 
                                         ($usuario['sexo'] === 'F' ? 'Femenino' : 'Otro'); 
                                    ?>
                                </span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label"><i class="fas fa-shield-alt"></i> Rol:</span>
                                <span class="info-value"><?php echo htmlspecialchars($usuario['nombre_rol']); ?></span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label"><i class="fas fa-circle"></i> Estado:</span>
                                <span class="info-value">
                                    <?php if ($usuario['id_estado'] == 1): ?>
                                        <span class="badge badge-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactivo</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label"><i class="fas fa-calendar-plus"></i> Fecha de Creación:</span>
                                <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($usuario['fecha_creacion'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Cambiar Contraseña -->
    <div class="modal fade" id="modalCambiarClave" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-key"></i> Cambiar Mi Contraseña
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formCambiarClave">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="clave_actual" class="form-label">Contraseña Actual *</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="clave_actual" name="clave_actual" required>
                                <button class="btn btn-outline-secondary toggle-password-btn" type="button" data-target="clave_actual">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="nueva_clave" class="form-label">Nueva Contraseña *</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="nueva_clave" name="nueva_clave" required minlength="6">
                                <button class="btn btn-outline-secondary toggle-password-btn" type="button" data-target="nueva_clave">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted">Mínimo 6 caracteres</small>
                        </div>
                        <div class="mb-3">
                            <label for="nueva_clave_confirm" class="form-label">Confirmar Nueva Contraseña *</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="nueva_clave_confirm" required minlength="6">
                                <button class="btn btn-outline-secondary toggle-password-btn" type="button" data-target="nueva_clave_confirm">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-key"></i> Cambiar Contraseña
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom JS -->
    <script src="../assets/js/dashboard.js"></script>
    <script src="../assets/js/perfil.js"></script>
</body>
</html>
