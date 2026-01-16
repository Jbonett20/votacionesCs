$(document).ready(function() {
    let table;
    const esLider = $('#es_lider').val() === '1';
    
    // Configurar columnas según rol
    let columns = [
        { data: 'id_votante' },
        { data: 'nombres' },
        { data: 'apellidos' },
        { data: 'identificacion' },
        { data: 'nombre_tipo' },
        { 
            data: 'sexo',
            render: function(data) {
                return data === 'M' ? 'Masculino' : (data === 'F' ? 'Femenino' : 'Otro');
            }
        }
    ];
    
    // Si no es líder, agregar columna de líder
    if (!esLider) {
        columns.push({
            data: null,
            render: function(data) {
                return data.lider_nombres + ' ' + data.lider_apellidos;
            }
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
                    <button class="btn btn-sm btn-info btn-action" onclick="editarVotante(${data.id_votante})" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-danger btn-action" onclick="eliminarVotante(${data.id_votante})" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                `;
            }
        }
    );
    
    // Inicializar DataTable
    function initDataTable() {
        table = $('#tableVotantes').DataTable({
            ajax: {
                url: '../controllers/votantes_controller.php',
                type: 'POST',
                data: { action: 'listar' },
                dataSrc: 'data'
            },
            columns: columns,
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
            url: '../controllers/votantes_controller.php',
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
    
    // Cargar líderes (solo para admin)
    function cargarLideres() {
        if (!esLider) {
            $.ajax({
                url: '../controllers/votantes_controller.php',
                type: 'POST',
                data: { action: 'obtener_lideres' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        let options = '<option value="">Seleccione...</option>';
                        options += '<option value="yo">Por mí (<?php echo htmlspecialchars($_SESSION["usuario_nombre"] ?? ""); ?>)</option>';
                        response.data.forEach(function(lider) {
                            options += `<option value="${lider.id_usuario}">${lider.nombres} ${lider.apellidos}</option>`;
                        });
                        $('#id_lider').html(options);
                        
                        // Inicializar Select2
                        $('#id_lider').select2({
                            theme: 'bootstrap-5',
                            dropdownParent: $('#modalVotante'),
                            placeholder: 'Seleccione un líder',
                            allowClear: true
                        });
                    }
                }
            });
        }
    }
    
    // Limpiar formulario
    function limpiarFormulario() {
        $('#formVotante')[0].reset();
        $('#votante_id').val('');
        $('#action').val('crear');
        $('#modalTitleText').text('Nuevo Votante');
        $('#estadoField').hide();
        
        if (!esLider) {
            $('#id_lider').val('').trigger('change');
        }
    }
    
    // Abrir modal para nuevo votante
    $('#modalVotante').on('show.bs.modal', function() {
        if ($('#action').val() === 'crear') {
            limpiarFormulario();
        }
    });
    
    // Guardar votante
    $('#formVotante').on('submit', function(e) {
        e.preventDefault();
        
        let formData = $(this).serializeArray();
        
        // Si es líder, agregar su ID como id_lider
        if (esLider) {
            formData.push({ name: 'id_lider', value: 'actual' });
        } else {
            // Si seleccionó "Por mí", usar su propio ID
            const liderSeleccionado = $('#id_lider').val();
            if (liderSeleccionado === 'yo') {
                formData = formData.filter(item => item.name !== 'id_lider');
                formData.push({ name: 'id_lider', value: 'actual' });
            }
        }
        
        $.ajax({
            url: '../controllers/votantes_controller.php',
            type: 'POST',
            data: $.param(formData),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: response.message,
                        timer: 2000
                    });
                    $('#modalVotante').modal('hide');
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
    
    // Editar votante
    window.editarVotante = function(id) {
        $.ajax({
            url: '../controllers/votantes_controller.php',
            type: 'POST',
            data: { action: 'obtener', id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const votante = response.data;
                    
                    $('#votante_id').val(votante.id_votante);
                    $('#action').val('editar');
                    $('#nombres').val(votante.nombres);
                    $('#apellidos').val(votante.apellidos);
                    $('#id_tipo_identificacion').val(votante.id_tipo_identificacion);
                    $('#identificacion').val(votante.identificacion);
                    $('#sexo').val(votante.sexo);
                    $('#id_estado').val(votante.id_estado);
                    
                    if (!esLider) {
                        $('#id_lider').val(votante.id_lider).trigger('change');
                    }
                    
                    $('#modalTitleText').text('Editar Votante');
                    $('#estadoField').show();
                    
                    $('#modalVotante').modal('show');
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
    
    // Eliminar votante
    window.eliminarVotante = function(id) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: "Esta acción cambiará el estado del votante a inactivo",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '../controllers/votantes_controller.php',
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
    cargarLideres();
});
