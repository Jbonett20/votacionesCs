<?php
require_once '../config/db.php';
require_once '../config/session.php';

// Solo usuarios autorizados pueden acceder
requerirRol([1, 2, 3]); // SuperAdmin, Admin, Líder

$es_lider = $_SESSION['usuario_rol'] == 3;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Votantes - Sistema de Votaciones</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
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
                <h1><i class="fas fa-users"></i> Gestión de Votantes</h1>
                <p>Administra los votantes del sistema</p>
            </div>
            
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-list"></i> Lista de Votantes</h5>
                    <div>
                        <?php if (!$es_lider): ?>
                        <button class="btn btn-success me-2" onclick="descargarPlantilla()">
                            <i class="fas fa-download"></i> Descargar Plantilla
                        </button>
                        <button class="btn btn-warning me-2" data-bs-toggle="modal" data-bs-target="#modalImportar">
                            <i class="fas fa-file-upload"></i> Importar Excel
                        </button>
                        <?php endif; ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalVotante">
                            <i class="fas fa-plus"></i> Nuevo Votante
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tableVotantes" class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombres</th>
                                    <th>Apellidos</th>
                                    <th>Identificación</th>
                                    <th>Tipo ID</th>
                                    <th>Sexo</th>
                                    <th>Mesa</th>
                                    <th>Lugar Mesa</th>
                                    <?php if (!$es_lider): ?>
                                    <th>Líder / Admin</th>
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
    
    <!-- Modal Votante -->
    <div class="modal fade" id="modalVotante" >
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalVotanteTitle">
                        <i class="fas fa-user-plus"></i> <span id="modalTitleText">Nuevo Votante</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formVotante">
                    <div class="modal-body">
                        <input type="hidden" id="votante_id" name="votante_id">
                        <input type="hidden" id="action" name="action" value="crear">
                        <input type="hidden" id="es_lider" value="<?php echo $es_lider ? '1' : '0'; ?>">
                        <input type="hidden" id="usuario_nombre_actual" value="<?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? ''); ?>">
                        
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
                                <input type="text" class="form-control" id="telefono" name="telefono" placeholder="Opcional">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="mesa" class="form-label">Mesa</label>
                                <input type="number" class="form-control" id="mesa" name="mesa" placeholder="Opcional" min="1">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="lugar_mesa" class="form-label">Lugar / Ubicación de la Mesa</label>
                                <input type="text" class="form-control" id="lugar_mesa" name="lugar_mesa" placeholder="Opcional">
                            </div>
                        </div>
                        
                        <?php if (!$es_lider): ?>
                        <div class="mb-3">
                            <label for="id_lider" class="form-label">Líder Responsable *</label>
                            <select class="form-select" id="id_lider" name="id_lider" required>
                                <option value="">Seleccione...</option>
                                <option value="yo">Por mí (<?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?>)</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        
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
    
    <!-- Modal Importar -->
    <div class="modal fade" id="modalImportar" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-file-upload"></i> Importar Votantes desde Excel
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formImportar" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> Instrucciones:</h6>
                            <ol class="mb-0">
                                <li>Descarga la plantilla Excel usando el botón "Descargar Plantilla"</li>
                                <li>Completa los datos de los votantes en el archivo</li>
                                <li>Sube el archivo completado aquí</li>
                                <li>El sistema validará duplicados y mostrará los resultados</li>
                            </ol>
                        </div>
                        
                        <div class="mb-3">
                            <label for="archivo" class="form-label">Archivo Excel/CSV *</label>
                            <input type="file" class="form-control" id="archivo" name="archivo" accept=".csv,.txt,.xlsx,.xls" required>
                            <small class="text-muted">Formatos permitidos: .csv, .txt, .xlsx, .xls</small>
                        </div>
                        
                        <div id="resultadoImportacion" class="mt-3" style="display: none;">
                            <h6>Resultado de la Importación:</h6>
                            <div id="mensajeImportacion"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cerrar
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnImportar">
                            <i class="fas fa-upload"></i> Importar
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
    <!-- Select2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom JS -->
    <script src="../assets/js/dashboard.js"></script>
    <script src="../assets/js/votantes.js"></script>
</body>
</html>
