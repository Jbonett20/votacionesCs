$(document).ready(function() {
    let table;
    
    // Inicializar DataTable
    function initDataTable() {
        table = $('#tableLideres').DataTable({
            ajax: {
                url: '../controllers/lideres_controller.php',
                type: 'POST',
                data: { action: 'listar' },
                dataSrc: 'data'
            },
            columns: [
                { data: 'id_usuario' },
                { data: 'nombres' },
                { data: 'apellidos' },
                { data: 'identificacion' },
                { data: 'usuario' },
                { 
                    data: 'sexo',
                    render: function(data) {
                        return data === 'M' ? 'Masculino' : (data === 'F' ? 'Femenino' : 'Otro');
                    }
                },
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
                            <button class="btn btn-sm btn-info btn-action" onclick="editarLider(${data.id_usuario})" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger btn-action" onclick="eliminarLider(${data.id_usuario})" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        `;
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
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
        $('#passwordFields').show();
        $('#clave, #clave_confirm').prop('required', true);
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
        
        // Validar contraseñas si es creación
        if (action === 'crear') {
            const clave = $('#clave').val();
            const claveConfirm = $('#clave_confirm').val();
            
            if (clave !== claveConfirm) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Las contraseñas no coinciden'
                });
                return;
            }
            
            if (clave.length < 6) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'La contraseña debe tener al menos 6 caracteres'
                });
                return;
            }
        }
        
        $.ajax({
            url: '../controllers/lideres_controller.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: response.message,
                        timer: 2000
                    });
                    $('#modalLider').modal('hide');
                    table.ajax.reload();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message
                    });
                }
            },
            error: function() {
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
                    
                    $('#lider_id').val(lider.id_usuario);
                    $('#action').val('editar');
                    $('#nombres').val(lider.nombres);
                    $('#apellidos').val(lider.apellidos);
                    $('#id_tipo_identificacion').val(lider.id_tipo_identificacion);
                    $('#identificacion').val(lider.identificacion);
                    $('#sexo').val(lider.sexo);
                    $('#usuario').val(lider.usuario);
                    $('#id_estado').val(lider.id_estado);
                    
                    $('#modalTitleText').text('Editar Líder');
                    $('#passwordFields').hide();
                    $('#clave, #clave_confirm').prop('required', false);
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
                                text: response.message
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
