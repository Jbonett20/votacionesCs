<?php
require_once '../config/db.php';
require_once '../config/session.php';

// Todos los usuarios pueden ver los duplicados
requerirRol([1, 2, 3]); // SuperAdmin, Admin, Líder

$es_super_admin = $_SESSION['usuario_rol'] == 1;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Votantes Duplicados - Sistema de Votaciones</title>
    
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
                <h1><i class="fas fa-exclamation-triangle text-warning"></i> Votantes Duplicados</h1>
                <p>Registro de intentos de inserción de votantes que ya existen en el sistema</p>
            </div>
            
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5><i class="fas fa-list"></i> Lista de Duplicados</h5>
                        <small class="text-muted">Este módulo es de solo lectura. No se pueden agregar ni editar registros.</small>
                    </div>
                    <div>
                        <button class="btn btn-success" onclick="exportarDuplicados()">
                            <i class="fas fa-file-excel"></i> Exportar a Excel
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Información:</strong> Aquí se registran todos los intentos de registro de votantes con identificación duplicada, 
                        ya sea desde el formulario manual o desde la importación de Excel. Esto permite llevar un control de los intentos de duplicación.
                    </div>
                    
                    <div class="table-responsive">
                        <table id="tableDuplicados" class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha/Hora</th>
                                    <th>Método</th>
                                    <th>Nombres</th>
                                    <th>Apellidos</th>
                                    <th>Identificación</th>
                                    <th>Teléfono</th>
                                    <th>Mesa</th>
                                    <th>Ya existe como</th>
                                    <th>Nombre existente</th>
                                    <th>Detalles</th>
                                    <th>Intentos de registro</th>
                                    <?php if ($es_super_admin): ?>
                                    <th>Acciones</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
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
    
    <script>
        $(document).ready(function() {
            // Inicializar DataTable
            const table = $('#tableDuplicados').DataTable({
                ajax: {
                    url: '../controllers/duplicados_controller.php',
                    type: 'POST',
                    data: { action: 'listar' },
                    dataSrc: 'data'
                },
                columns: [
                    { data: 'id_duplicado' },
                    { data: 'fecha_intento_formato' },
                    { 
                        data: 'metodo_intento',
                        render: function(data) {
                            if (data === 'excel') {
                                return '<span class="badge bg-success"><i class="fas fa-file-excel"></i> Excel</span>';
                            } else {
                                return '<span class="badge bg-primary"><i class="fas fa-keyboard"></i> Formulario</span>';
                            }
                        }
                    },
                    { data: 'nombres' },
                    { data: 'apellidos' },
                    { data: 'identificacion' },
                    { 
                        data: 'telefono',
                        render: function(data) {
                            return data || '<span class="text-muted">N/A</span>';
                        }
                    },
                    { data: 'mesa' },
                    { 
                        data: 'tipo_existente',
                        render: function(data) {
                            let badge = '';
                            switch(data) {
                                case 'votante':
                                    badge = '<span class="badge bg-info">Votante</span>';
                                    break;
                                case 'líder':
                                    badge = '<span class="badge bg-warning text-dark">Líder</span>';
                                    break;
                                case 'usuario':
                                    badge = '<span class="badge bg-danger">Usuario</span>';
                                    break;
                            }
                            return badge;
                        }
                    },
                    { data: 'nombre_existente' },
                    { 
                        data: 'detalles_existente',
                        render: function(data) {
                            return data || '<span class="text-muted">N/A</span>';
                        }
                    },
                    { data: 'nombre_usuario_intento' }
                    <?php if ($es_super_admin): ?>
                    ,{
                        data: null,
                        orderable: false,
                        render: function(data, type, row) {
                            return `<button class="btn btn-sm btn-danger" onclick="eliminarDuplicado(${row.id_duplicado})">
                                        <i class="fas fa-trash"></i>
                                    </button>`;
                        }
                    }
                    <?php endif; ?>
                ],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                },
                order: [[0, 'desc']],
                pageLength: 25,
                responsive: true
            });
        });
        
        // Función para exportar duplicados a Excel
        function exportarDuplicados() {
            window.location.href = '../controllers/duplicados_controller.php?action=exportar';
        }
        
        <?php if ($es_super_admin): ?>
        // Función para eliminar duplicado
        function eliminarDuplicado(id) {
            Swal.fire({
                title: '¿Estás seguro?',
                text: "Se eliminará este registro de duplicado",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '../controllers/duplicados_controller.php',
                        type: 'POST',
                        data: {
                            action: 'eliminar',
                            id: id
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire('Eliminado', response.message, 'success');
                                $('#tableDuplicados').DataTable().ajax.reload();
                            } else {
                                Swal.fire('Error', response.message, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
                        }
                    });
                }
            });
        }
        <?php endif; ?>
    </script>
</body>
</html>
