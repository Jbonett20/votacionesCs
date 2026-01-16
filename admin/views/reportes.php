<?php
require_once '../config/db.php';
require_once '../config/session.php';
requerirRol([1, 2]); // Solo SuperAdmin y Admin

$usuario_id = $_SESSION['usuario_id'];
$usuario_rol = $_SESSION['usuario_rol'];
$es_superadmin = ($usuario_rol == 1);

// Consultas base con filtros según el rol
if ($es_superadmin) {
    // SuperAdmin ve todo
    $query_lideres = "SELECT l.*, CONCAT(u.nombres, ' ', u.apellidos) as creador,
                      (SELECT COUNT(*) FROM votantes v WHERE v.id_lider = l.id_lider) as total_votantes
                      FROM lideres l
                      LEFT JOIN usuarios u ON l.id_usuario_creador = u.id_usuario
                      WHERE l.id_estado = 1
                      ORDER BY l.fecha_creacion DESC";
    $lideres = DB::queryAllRows($query_lideres);
    
    $query_votantes_por_lider = "SELECT l.id_lider, CONCAT(l.nombres, ' ', l.apellidos) as lider,
                                  COUNT(v.id_votante) as total
                                  FROM lideres l
                                  LEFT JOIN votantes v ON l.id_lider = v.id_lider
                                  WHERE l.id_estado = 1
                                  GROUP BY l.id_lider
                                  ORDER BY total DESC";
    $votantes_por_lider = DB::queryAllRows($query_votantes_por_lider);
    
    $total_lideres = DB::queryOneValue("SELECT COUNT(*) FROM lideres WHERE id_estado = 1");
    $total_votantes = DB::queryOneValue("SELECT COUNT(*) FROM votantes WHERE id_estado = 1");
    $votantes_con_lider = DB::queryOneValue("SELECT COUNT(*) FROM votantes WHERE id_lider IS NOT NULL AND id_estado = 1");
    $votantes_directos = DB::queryOneValue("SELECT COUNT(*) FROM votantes WHERE id_administrador_directo IS NOT NULL AND id_estado = 1");
    
    $administradores = DB::queryAllRows("SELECT u.id_usuario, CONCAT(u.nombres, ' ', u.apellidos) as nombre,
                                         (SELECT COUNT(*) FROM lideres WHERE id_usuario_creador = u.id_usuario) as total_lideres,
                                         (SELECT COUNT(*) FROM votantes WHERE id_usuario_creador = u.id_usuario) as total_votantes
                                         FROM usuarios u
                                         WHERE u.id_rol = 2 AND u.id_estado = 1
                                         ORDER BY u.nombres");
} else {
    // Admin solo ve lo suyo
    $query_lideres = "SELECT l.*, CONCAT(u.nombres, ' ', u.apellidos) as creador,
                      (SELECT COUNT(*) FROM votantes v WHERE v.id_lider = l.id_lider) as total_votantes
                      FROM lideres l
                      LEFT JOIN usuarios u ON l.id_usuario_creador = u.id_usuario
                      WHERE l.id_usuario_creador = ? AND l.id_estado = 1
                      ORDER BY l.fecha_creacion DESC";
    $lideres = DB::queryAllRows($query_lideres, $usuario_id);
    
    $query_votantes_por_lider = "SELECT l.id_lider, CONCAT(l.nombres, ' ', l.apellidos) as lider,
                                  COUNT(v.id_votante) as total
                                  FROM lideres l
                                  LEFT JOIN votantes v ON l.id_lider = v.id_lider
                                  WHERE l.id_usuario_creador = ? AND l.id_estado = 1
                                  GROUP BY l.id_lider
                                  ORDER BY total DESC";
    $votantes_por_lider = DB::queryAllRows($query_votantes_por_lider, $usuario_id);
    
    $total_lideres = DB::queryOneValue("SELECT COUNT(*) FROM lideres WHERE id_usuario_creador = ? AND id_estado = 1", $usuario_id);
    $total_votantes = DB::queryOneValue("SELECT COUNT(*) FROM votantes WHERE id_usuario_creador = ? AND id_estado = 1", $usuario_id);
    $votantes_con_lider = DB::queryOneValue("SELECT COUNT(*) FROM votantes v 
                                               INNER JOIN lideres l ON v.id_lider = l.id_lider 
                                               WHERE l.id_usuario_creador = ? AND v.id_estado = 1", $usuario_id);
    $votantes_directos = DB::queryOneValue("SELECT COUNT(*) FROM votantes WHERE id_administrador_directo = ? AND id_estado = 1", $usuario_id);
    
    $administradores = [];
}

// Distribución por sexo
if ($es_superadmin) {
    $votantes_por_sexo = DB::queryAllRows("SELECT sexo, COUNT(*) as total FROM votantes WHERE id_estado = 1 GROUP BY sexo");
} else {
    $votantes_por_sexo = DB::queryAllRows("SELECT sexo, COUNT(*) as total FROM votantes WHERE id_usuario_creador = ? AND id_estado = 1 GROUP BY sexo", $usuario_id);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Sistema de Votaciones</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .report-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .stat-box h3 {
            margin: 0;
            font-size: 36px;
            font-weight: bold;
        }
        .stat-box p {
            margin: 5px 0 0;
            opacity: 0.9;
        }
        .table-responsive {
            max-height: 400px;
            overflow-y: auto;
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
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
                    <h1><i class="fas fa-chart-bar"></i> Reportes y Estadísticas</h1>
                    <p><?php echo $es_superadmin ? 'Vista general del sistema' : 'Tus datos y estadísticas'; ?></p>
                </div>
                
                <!-- Resumen General -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-box" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <h3><?php echo number_format($total_lideres); ?></h3>
                            <p><i class="fas fa-users"></i> Total Líderes</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <h3><?php echo number_format($total_votantes); ?></h3>
                            <p><i class="fas fa-user-check"></i> Total Votantes</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <h3><?php echo number_format($votantes_con_lider); ?></h3>
                            <p><i class="fas fa-link"></i> Con Líder</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <h3><?php echo number_format($votantes_directos); ?></h3>
                            <p><i class="fas fa-user-plus"></i> Directos</p>
                        </div>
                    </div>
                </div>
                
                <?php if ($es_superadmin && count($administradores) > 0): ?>
                <!-- Reporte por Administrador (Solo SuperAdmin) -->
                <div class="report-card">
                    <h5><i class="fas fa-user-shield"></i> Reporte por Administrador</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Administrador</th>
                                    <th class="text-center">Líderes Creados</th>
                                    <th class="text-center">Votantes Creados</th>
                                    <th class="text-center">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($administradores as $admin): ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-user-shield text-primary"></i>
                                        <?php echo htmlspecialchars($admin['nombre']); ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary"><?php echo $admin['total_lideres']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success"><?php echo $admin['total_votantes']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <strong><?php echo $admin['total_lideres'] + $admin['total_votantes']; ?></strong>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Votantes por Líder -->
                    <div class="col-md-8">
                        <div class="report-card">
                            <h5><i class="fas fa-chart-pie"></i> Votantes por Líder</h5>
                            <?php if (count($votantes_por_lider) > 0): ?>
                            <div class="chart-container">
                                <canvas id="chartVotantesPorLider"></canvas>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No hay líderes registrados todavía
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Distribución por Sexo -->
                    <div class="col-md-4">
                        <div class="report-card">
                            <h5><i class="fas fa-venus-mars"></i> Distribución por Sexo</h5>
                            <?php if (count($votantes_por_sexo) > 0): ?>
                            <div class="chart-container">
                                <canvas id="chartSexo"></canvas>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No hay votantes registrados
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Detalle de Líderes -->
                <div class="report-card">
                    <h5><i class="fas fa-list"></i> Detalle de Líderes y sus Votantes</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Líder</th>
                                    <th>Identificación</th>
                                    <th>Teléfono</th>
                                    <?php if ($es_superadmin): ?>
                                    <th>Creado por</th>
                                    <?php endif; ?>
                                    <th class="text-center">Total Votantes</th>
                                    <th class="text-center">Fecha Creación</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($lideres) > 0): ?>
                                    <?php foreach ($lideres as $lider): ?>
                                    <tr>
                                        <td>
                                            <i class="fas fa-user-tie text-info"></i>
                                            <?php echo htmlspecialchars($lider['nombres'] . ' ' . $lider['apellidos']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($lider['identificacion']); ?></td>
                                        <td><?php echo htmlspecialchars($lider['telefono'] ?? 'N/A'); ?></td>
                                        <?php if ($es_superadmin): ?>
                                        <td><small><?php echo htmlspecialchars($lider['creador']); ?></small></td>
                                        <?php endif; ?>
                                        <td class="text-center">
                                            <span class="badge bg-primary"><?php echo $lider['total_votantes']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <small><?php echo date('d/m/Y', strtotime($lider['fecha_creacion'])); ?></small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="<?php echo $es_superadmin ? 6 : 5; ?>" class="text-center">
                                            <i class="fas fa-info-circle"></i> No hay líderes registrados
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Botones de Exportación -->
                <div class="text-end mt-3">
                    <button class="btn btn-success" onclick="exportarExcel('lideres')">
                        <i class="fas fa-file-excel"></i> Exportar Líderes
                    </button>
                    <button class="btn btn-success" onclick="exportarExcel('votantes')">
                        <i class="fas fa-file-excel"></i> Exportar Votantes
                    </button>
                    <button class="btn btn-success" onclick="exportarExcel('completo')">
                        <i class="fas fa-file-excel"></i> Reporte Completo
                    </button>
                    <button class="btn btn-primary" onclick="imprimirReporte()">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
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
    <script>
        // Datos para gráficas
        const votantesPorLider = <?php echo json_encode($votantes_por_lider); ?>;
        const votantesPorSexo = <?php echo json_encode($votantes_por_sexo); ?>;
        
        // Gráfica de Votantes por Líder
        <?php if (count($votantes_por_lider) > 0): ?>
        const ctxLideres = document.getElementById('chartVotantesPorLider').getContext('2d');
        new Chart(ctxLideres, {
            type: 'bar',
            data: {
                labels: votantesPorLider.map(item => item.lider),
                datasets: [{
                    label: 'Votantes',
                    data: votantesPorLider.map(item => item.total),
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
        <?php endif; ?>
        
        // Gráfica de Distribución por Sexo
        <?php if (count($votantes_por_sexo) > 0): ?>
        const ctxSexo = document.getElementById('chartSexo').getContext('2d');
        new Chart(ctxSexo, {
            type: 'doughnut',
            data: {
                labels: votantesPorSexo.map(item => {
                    return item.sexo === 'M' ? 'Masculino' : (item.sexo === 'F' ? 'Femenino' : 'Otro');
                }),
                datasets: [{
                    data: votantesPorSexo.map(item => item.total),
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(240, 147, 251, 0.8)',
                        'rgba(67, 233, 123, 0.8)'
                    ],
                    borderColor: [
                        'rgba(102, 126, 234, 1)',
                        'rgba(240, 147, 251, 1)',
                        'rgba(67, 233, 123, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        <?php endif; ?>
        
        function imprimirReporte() {
            window.print();
        }
        
        function exportarExcel(tipo) {
            let action = '';
            switch(tipo) {
                case 'lideres':
                    action = 'exportar_lideres';
                    break;
                case 'votantes':
                    action = 'exportar_votantes';
                    break;
                case 'completo':
                    action = 'exportar_reporte_completo';
                    break;
            }
            window.location.href = '../controllers/exportar_controller.php?action=' + action;
        }
    </script>
</body>
</html>
