$(document).ready(function() {
    console.log('votantes.js cargado correctamente');
    let votantesTable;
    const esLider = $('#es_lider').val() === '1';
    console.log('Es líder:', esLider);
    
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
        },
        { 
            data: 'mesa',
            render: function(data) {
                return data ? data : 0;
            }
        }
        ,{ data: 'lugar_mesa', render: function(data) { return data ? data : ''; } }
    ];
    
    // Si no es líder, agregar columna de líder/admin
    if (!esLider) {
        columns.push({
            data: null,
            render: function(data) {
                if (data.lider_nombres && data.lider_apellidos) {
                    return '<span class="badge bg-info">' + data.lider_nombres + ' ' + data.lider_apellidos + '</span>';
                } else if (data.admin_directo) {
                    return '<span class="badge bg-primary">Por ' + data.admin_directo + '</span>';
                } else {
                    return '<span class="badge bg-secondary">Sin asignar</span>';
                }
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
        votantesTable = $('#tableVotantes').DataTable({
            ajax: {
                url: '../controllers/votantes_controller.php',
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
            const usuarioNombre = $('#usuario_nombre_actual').val();
            $.ajax({
                url: '../controllers/votantes_controller.php',
                type: 'POST',
                data: { action: 'obtener_lideres' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        let options = '<option value="">Seleccione...</option>';
                        options += `<option value="yo">Por mí (${usuarioNombre})</option>`;
                        response.data.forEach(function(lider) {
                            options += `<option value="${lider.id_lider}">${lider.nombres} ${lider.apellidos}</option>`;
                        });
                        $('#id_lider').html(options);
                        
                        // Destruir Select2 anterior si existe
                        if ($('#id_lider').hasClass('select2-hidden-accessible')) {
                            $('#id_lider').select2('destroy');
                        }
                        
                        // Inicializar Select2
                        $('#id_lider').select2({
                            theme: 'bootstrap-5',
                            dropdownParent: $('#modalVotante'),
                            placeholder: 'Seleccione un líder',
                            allowClear: true,
                            width: '100%'
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
            // Limpiar Select2 correctamente
            if ($('#id_lider').hasClass('select2-hidden-accessible')) {
                $('#id_lider').val('').trigger('change');
            }
        } else {
            // Si es líder, quitar el required del campo id_lider si existe
            $('#id_lider').removeAttr('required');
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
        console.log('Submit capturado'); // DEBUG
        
        let formData = $(this).serializeArray();
        console.log('Datos del formulario:', formData); // DEBUG
        
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
                console.log('Respuesta completa:', response);
                
                if (response && response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: response.message || 'Operación exitosa',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        $('#modalVotante').modal('hide');
                        votantesTable.ajax.reload();
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
                    $('#telefono').val(votante.telefono || '');
                    $('#mesa').val(votante.mesa || '');
                    $('#lugar_mesa').val(votante.lugar_mesa || '');
                    $('#id_estado').val(votante.id_estado);
                    
                    if (!esLider) {
                        // Si tiene líder asignado, seleccionarlo; si no, seleccionar "Por mí"
                        if (votante.id_lider && votante.id_lider !== null && votante.id_lider !== '') {
                            $('#id_lider').val(votante.id_lider).trigger('change');
                        } else {
                            // Si no tiene líder (registrado por admin directo), seleccionar "Por mí"
                            $('#id_lider').val('yo').trigger('change');
                        }
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
                            votantesTable.ajax.reload();
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
    
    // Importar votantes
    $('#formImportar').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'importar_votantes');
        
        const btnImportar = $('#btnImportar');
        btnImportar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Importando...');
        
        $.ajax({
            url: '../controllers/importar_controller.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                btnImportar.prop('disabled', false).html('<i class="fas fa-upload"></i> Importar');
                
                if (response.success) {
                    let mensaje = `<div class="alert alert-success">
                        <h6><i class="fas fa-check-circle"></i> ${response.message}</h6>
                        <p><strong>${response.insertados}</strong> votantes importados exitosamente.</p>
                    </div>`;
                    
                    // Mostrar duplicados si existen
                    if (response.duplicados && response.duplicados.length > 0) {
                        mensaje += `<div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle"></i> Duplicados Encontrados (${response.duplicados.length})</h6>
                            <ul class="mb-0" style="max-height: 200px; overflow-y: auto;">`;
                        response.duplicados.forEach(dup => {
                            mensaje += `<li>${dup}</li>`;
                        });
                        mensaje += `</ul></div>`;
                    }
                    
                    // Mostrar errores si existen
                    if (response.errores && response.errores.length > 0) {
                        mensaje += `<div class="alert alert-danger">
                            <h6><i class="fas fa-times-circle"></i> Errores (${response.errores.length})</h6>
                            <ul class="mb-0" style="max-height: 200px; overflow-y: auto;">`;
                        response.errores.forEach(err => {
                            mensaje += `<li>${err}</li>`;
                        });
                        mensaje += `</ul></div>`;
                    }
                    
                    $('#mensajeImportacion').html(mensaje);
                    $('#resultadoImportacion').show();
                    $('#archivo').val('');
                    
                    // Recargar tabla si se importó algo
                    if (response.insertados > 0) {
                        votantesTable.ajax.reload();
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message
                    });
                }
            },
            error: function() {
                btnImportar.prop('disabled', false).html('<i class="fas fa-upload"></i> Importar');
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al procesar la importación'
                });
            }
        });
    });
    
    // Limpiar resultado al cerrar modal
    $('#modalImportar').on('hidden.bs.modal', function() {
        $('#formImportar')[0].reset();
        $('#resultadoImportacion').hide();
        $('#mensajeImportacion').html('');
    });
});

// Funciones globales fuera del document.ready

function descargarPlantilla() {
    window.location.href = '../controllers/exportar_controller.php?action=descargar_plantilla';
}
