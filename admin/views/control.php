<?php
require_once '../config/db.php';
require_once '../config/session.php';

requerirRol([1, 2, 3]);

$usuario_rol = $_SESSION['usuario_rol'];
$es_lider = ($usuario_rol == 3);
$es_superadmin = ($usuario_rol == 1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Votación - Sistema de Votaciones</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/tables.css">
</head>
<body>
    <?php include 'partials/sidebar.php'; ?>

    <div class="main-content">
        <?php include 'partials/topbar.php'; ?>

        <div class="page-content">
            <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h1><i class="fas fa-check-double"></i> Control de Votación</h1>
                    <p>Marca y consulta quién ya votó (votantes y líderes)</p>
                </div>
                <button class="btn btn-success" onclick="exportarControlExcel()">
                    <i class="fas fa-file-excel"></i> Exportar a Excel
                </button>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-md-4">
                    <div class="card h-100">
                        <div class="card-body d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted">Total registros</small>
                                <h4 class="mb-0" id="totalVotantesControl">0</h4>
                            </div>
                            <i class="fas fa-users fs-4 text-primary"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card h-100">
                        <div class="card-body d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted">Sin votar</small>
                                <h4 class="mb-0" id="totalSinVotarControl">0</h4>
                            </div>
                            <i class="fas fa-clock fs-4 text-warning"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card h-100">
                        <div class="card-body d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted">Ya votó</small>
                                <h4 class="mb-0" id="totalYaVotoControl">0</h4>
                            </div>
                            <i class="fas fa-check-circle fs-4 text-success"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body py-3">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="fw-semibold me-2">Estado:</span>
                        <div class="btn-group" role="group" aria-label="Filtro estado voto">
                            <button type="button" class="btn btn-outline-primary active" id="filtroTodos" data-filtro="todos">
                                Todos
                            </button>
                            <button type="button" class="btn btn-outline-warning" id="filtroSinVotar" data-filtro="sin_votar">
                                Sin votar
                            </button>
                            <button type="button" class="btn btn-outline-success" id="filtroYaVoto" data-filtro="ya_voto">
                                Ya votó
                            </button>
                        </div>

                        <span class="fw-semibold ms-2 me-2">Tipo:</span>
                        <div class="btn-group" role="group" aria-label="Filtro tipo registro">
                            <button type="button" class="btn btn-outline-primary active" data-filtro-tipo="todos">
                                Todos
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-filtro-tipo="votante">
                                Solo votantes
                            </button>
                            <button type="button" class="btn btn-outline-info" data-filtro-tipo="lider">
                                Solo líderes
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Registros de control</h5>
                </div>
                <div class="card-body">
                    <input type="hidden" id="es_lider" value="<?php echo $es_lider ? '1' : '0'; ?>">
                    <input type="hidden" id="es_superadmin" value="<?php echo $es_superadmin ? '1' : '0'; ?>">

                    <div class="table-responsive">
                        <table id="tablaControl" class="table table-hover align-middle w-100">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombres</th>
                                    <th>Apellidos</th>
                                    <th>Identificación</th>
                                    <th>Tipo ID</th>
                                    <th>Teléfono</th>
                                    <th>Sexo</th>
                                    <th>Mesa</th>
                                    <th>Lugar Mesa</th>
                                    <?php if (!$es_lider): ?>
                                    <th>Líder / Admin</th>
                                    <?php endif; ?>
                                    <?php if ($es_superadmin): ?>
                                    <th>Administrador</th>
                                    <?php endif; ?>
                                    <th>Estado Voto</th>
                                    <th>Opciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/dashboard.js"></script>
    <script src="../assets/js/control.js"></script>
</body>
</html>
