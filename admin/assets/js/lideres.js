$(document).ready(function() {
    let table;
    const esSuperAdmin = typeof ES_SUPER_ADMIN !== 'undefined' ? ES_SUPER_ADMIN : false;
    
    // Configurar columnas
    let columns = [
        { data: 'id_lider' },
        { data: 'nombres' },
        { data: 'apellidos' },
        { data: 'identificacion' },
        { 
            data: 'telefono',
            defaultContent: 'Sin teléfono'
        },
        { 
            data: 'sexo',
            render: function(data) {
                return data === 'M' ? 'Masculino' : (data === 'F' ? 'Femenino' : 'Otro');
            }
        }
    ];
    
    // Agregar columna "Creado Por" solo para SuperAdmin
    if (esSuperAdmin) {
        columns.push({
            data: 'creador',
            defaultContent: 'No asignado'
        });
    }
    
    // Agregar columnas de estado y acciones
    columns.push(
        { 
            data: 'id_estado',
            render: function(data) {
                return data == 1 
                    ? '<span class="badge badge-success">Activo</span>' 
                    : '<span class="badge badge-danger">Inactivo</span>';
            }
        },
        {
            data: null,
            render: function(data) {
                return `
                    <button class="btn btn-sm btn-info btn-action" onclick="editarLider(${data.id_lider})" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-danger btn-action" onclick="eliminarLider(${data.id_lider})" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                `;
            }
        }
    );
    
    // Inicializar DataTable
    function initDataTable() {
        table = $('#tableLideres').DataTable({
            ajax: {
                url: '../controllers/lideres_controller.php',
                type: 'POST',
                data: { action: 'listar' },
                dataSrc: 'data'
            },
            columns: columns,
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            responsive: true,
            order: [[0, 'desc']]
        });
    }
    
    // Cargar tipos de identificación
    function cargarTiposIdentificacion() {
        $.ajax({
            url: '../controllers/lideres_controller.php',
            type: 'POST',
            data: { action: 'obtener_tipos_identificacion' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    let options = '<option value="">Seleccione...</option>';
                    response.data.forEach(function(tipo) {
                        options += `<option value="${tipo.id_tipo_identificacion}">${tipo.nombre_tipo}</option>`;
                    });
                    $('#id_tipo_identificacion').html(options);
                }
            }
        });
    }
    
    // Limpiar formulario
    function limpiarFormulario() {
        $('#formLider')[0].reset();
        $('#lider_id').val('');
        $('#action').val('crear');
        $('#modalTitleText').text('Nuevo Líder');
        $('#estadoField').hide();
    }
    
    // Abrir modal para nuevo líder
    $('#modalLider').on('show.bs.modal', function() {
        if ($('#action').val() === 'crear') {
            limpiarFormulario();
        }
    });
    
    // Guardar líder
    $('#formLider').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        const action = $('#action').val();
        
        $.ajax({
            url: '../controllers/lideres_controller.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                console.log('Respuesta completa:', response);
                
                if (response && response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: response.message || 'Operación exitosa',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        $('#modalLider').modal('hide');
                        table.ajax.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        html: response.message || 'Error desconocido',
                        showConfirmButton: true
                    });
                }
            },
            error: function(xhr, status, error) {
                console.log('Error AJAX:', xhr, status, error);
                console.log('Respuesta:', xhr.responseText);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error de conexión'
                });
            }
        });
    });
    
    // Editar líder
    window.editarLider = function(id) {
        $.ajax({
            url: '../controllers/lideres_controller.php',
            type: 'POST',
            data: { action: 'obtener', id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const lider = response.data;
                    
                    $('#lider_id').val(lider.id_lider);
                    $('#action').val('editar');
                    $('#nombres').val(lider.nombres);
                    $('#apellidos').val(lider.apellidos);
                    $('#id_tipo_identificacion').val(lider.id_tipo_identificacion);
                    $('#identificacion').val(lider.identificacion);
                    $('#sexo').val(lider.sexo);
                    $('#telefono').val(lider.telefono || '');
                    $('#direccion').val(lider.direccion || '');
                    $('#id_estado').val(lider.id_estado);
                    
                    $('#modalTitleText').text('Editar Líder');
                    $('#estadoField').show();
                    
                    $('#modalLider').modal('show');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message
                    });
                }
            }
        });
    };
    
    // Eliminar líder
    window.eliminarLider = function(id) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: "Esta acción cambiará el estado del líder a inactivo",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '../controllers/lideres_controller.php',
                    type: 'POST',
                    data: { action: 'eliminar', id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Eliminado',
                                text: response.message,
                                timer: 2000
                            });
                            table.ajax.reload();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                html: response.message
                            });
                        }
                    }
                });
            }
        });
    };
    
    // Inicializar
    initDataTable();
    cargarTiposIdentificacion();
});
