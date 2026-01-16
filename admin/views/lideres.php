<?php
require_once '../config/db.php';
require_once '../config/session.php';

// Solo SuperAdmin y Admin pueden acceder
requerirRol([1, 2]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Líderes - Sistema de Votaciones</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/tables.css">
</head>
<body>
    <!-- Sidebar -->
    <?php include 'partials/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <?php include 'partials/topbar.php'; ?>
        
        <!-- Page Content -->
        <div class="page-content">
            <div class="page-header">
                <h1><i class="fas fa-user-tie"></i> Gestión de Líderes</h1>
                <p>Administra los líderes del sistema</p>
            </div>
            
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-list"></i> Lista de Líderes</h5>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalLider">
                        <i class="fas fa-plus"></i> Nuevo Líder
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tableLideres" class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombres</th>
                                    <th>Apellidos</th>
                                    <th>Identificación</th>
                                    <th>Teléfono</th>
                                    <th>Sexo</th>
                                    <?php if (esSuperAdmin()): ?>
                                    <th>Creado Por</th>
                                    <?php endif; ?>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Líder -->
    <div class="modal fade" id="modalLider" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalLiderTitle">
                        <i class="fas fa-user-tie"></i> <span id="modalTitleText">Nuevo Líder</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formLider">
                    <div class="modal-body">
                        <input type="hidden" id="lider_id" name="lider_id">
                        <input type="hidden" id="action" name="action" value="crear">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nombres" class="form-label">Nombres *</label>
                                <input type="text" class="form-control" id="nombres" name="nombres" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="apellidos" class="form-label">Apellidos *</label>
                                <input type="text" class="form-control" id="apellidos" name="apellidos" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="id_tipo_identificacion" class="form-label">Tipo de Identificación *</label>
                                <select class="form-select" id="id_tipo_identificacion" name="id_tipo_identificacion" required>
                                    <option value="">Seleccione...</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="identificacion" class="form-label">Identificación *</label>
                                <input type="text" class="form-control" id="identificacion" name="identificacion" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="sexo" class="form-label">Sexo *</label>
                                <select class="form-select" id="sexo" name="sexo" required>
                                    <option value="">Seleccione...</option>
                                    <option value="M">Masculino</option>
                                    <option value="F">Femenino</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="telefono" class="form-label">Teléfono</label>
                                <input type="text" class="form-control" id="telefono" name="telefono">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="direccion" class="form-label">Dirección</label>
                            <input type="text" class="form-control" id="direccion" name="direccion">
                        </div>
                        
                        <div class="mb-3" id="estadoField" style="display: none;">
                            <label for="id_estado" class="form-label">Estado *</label>
                            <select class="form-select" id="id_estado" name="id_estado">
                                <option value="1">Activo</option>
                                <option value="2">Inactivo</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Guardar
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
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom JS -->
    <script src="../assets/js/dashboard.js"></script>
    <script>
        const ES_SUPER_ADMIN = <?php echo esSuperAdmin() ? 'true' : 'false'; ?>;
    </script>
    <script src="../assets/js/lideres.js"></script>
</body>
</html>
