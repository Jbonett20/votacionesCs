$(document).ready(function() {
    let table;
    
    // Toggle password visibility
    $(document).on('click', '.toggle-password-btn', function() {
        const targetId = $(this).data('target');
        const input = $('#' + targetId);
        const icon = $(this).find('i');
        
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
    
    // Inicializar DataTable
    function initDataTable() {
        table = $('#tableUsuarios').DataTable({
            ajax: {
                url: '../controllers/usuarios_controller.php',
                type: 'POST',
                data: { action: 'listar' },
                dataSrc: 'data'
            },
            columns: [
                { data: 'id_usuario' },
                { data: 'nombres' },
                { data: 'apellidos' },
                { data: 'usuario' },
                { data: 'identificacion' },
                { 
                    data: 'nombre_rol',
                    render: function(data) {
                        return data === 'SuperAdmin' 
                            ? '<span class="badge bg-danger">' + data + '</span>'
                            : '<span class="badge bg-primary">' + data + '</span>';
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
                            <button class="btn btn-sm btn-info btn-action" onclick="editarUsuario(${data.id_usuario})" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-warning btn-action" onclick="cambiarClave(${data.id_usuario})" title="Cambiar Contraseña">
                                <i class="fas fa-key"></i>
                            </button>
                            <button class="btn btn-sm btn-danger btn-action" onclick="cambiarEstado(${data.id_usuario}, ${data.id_estado})" title="${data.id_estado == 1 ? 'Desactivar' : 'Activar'}">
                                <i class="fas fa-${data.id_estado == 1 ? 'ban' : 'check'}"></i>
                            </button>
                        `;
                    }
                }
            ],
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
            url: '../controllers/usuarios_controller.php',
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
        $('#formUsuario')[0].reset();
        $('#usuario_id').val('');
        $('#action').val('crear');
        $('#modalTitleText').text('Nuevo Usuario');
        $('#passwordFields').show();
        $('#clave, #clave_confirm').prop('required', true);
        $('#estadoField').hide();
    }
    
    // Inicializar
    initDataTable();
    cargarTiposIdentificacion();
    
    // Abrir modal para nuevo usuario
    $('#modalUsuario').on('show.bs.modal', function() {
        if ($('#action').val() === 'crear') {
            limpiarFormulario();
        }
    });
    
    // Guardar usuario
    $('#formUsuario').on('submit', function(e) {
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
            url: '../controllers/usuarios_controller.php',
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
                    $('#modalUsuario').modal('hide');
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
    
    // Editar usuario
    window.editarUsuario = function(id) {
        $.ajax({
            url: '../controllers/usuarios_controller.php',
            type: 'POST',
            data: { action: 'obtener', id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const usuario = response.data;
                    
                    $('#usuario_id').val(usuario.id_usuario);
                    $('#action').val('editar');
                    $('#nombres').val(usuario.nombres);
                    $('#apellidos').val(usuario.apellidos);
                    $('#id_tipo_identificacion').val(usuario.id_tipo_identificacion);
                    $('#identificacion').val(usuario.identificacion);
                    $('#sexo').val(usuario.sexo);
                    $('#usuario').val(usuario.usuario);
                    $('#id_rol').val(usuario.id_rol);
                    $('#id_estado').val(usuario.id_estado);
                    
                    $('#modalTitleText').text('Editar Usuario');
                    $('#passwordFields').hide();
                    $('#clave, #clave_confirm').prop('required', false);
                    $('#estadoField').show();
                    
                    $('#modalUsuario').modal('show');
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
    
    // Cambiar contraseña
    window.cambiarClave = function(id) {
        $('#usuario_id_clave').val(id);
        $('#formCambiarClave')[0].reset();
        $('#modalCambiarClave').modal('show');
    };
    
    // Guardar nueva contraseña
    $('#formCambiarClave').on('submit', function(e) {
        e.preventDefault();
        
        const nuevaClave = $('#nueva_clave').val();
        const nuevaClaveConfirm = $('#nueva_clave_confirm').val();
        
        if (nuevaClave !== nuevaClaveConfirm) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Las contraseñas no coinciden'
            });
            return;
        }
        
        if (nuevaClave.length < 6) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'La contraseña debe tener al menos 6 caracteres'
            });
            return;
        }
        
        $.ajax({
            url: '../controllers/usuarios_controller.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: response.message,
                        timer: 2000
                    });
                    $('#modalCambiarClave').modal('hide');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message
                    });
                }
            }
        });
    });
    
    // Cambiar estado
    window.cambiarEstado = function(id, estadoActual) {
        const nuevoEstado = estadoActual == 1 ? 2 : 1;
        const accion = estadoActual == 1 ? 'desactivar' : 'activar';
        
        Swal.fire({
            title: '¿Estás seguro?',
            text: `¿Deseas ${accion} este usuario?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, continuar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '../controllers/usuarios_controller.php',
                    type: 'POST',
                    data: { 
                        action: 'cambiar_estado',
                        id: id,
                        estado: nuevoEstado
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Éxito',
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
});
