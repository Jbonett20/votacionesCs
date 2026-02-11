$(document).ready(function() {
    console.log('votantes.js cargado correctamente');
    let votantesTable;
    const esLider = $('#es_lider').val() === '1';
    let importDuplicados = [];
    let modalVotanteEdicion = false;
    console.log('Es líder:', esLider);
    
    // Inicializar sistema de ubicaciones
    inicializarUbicaciones('id_departamento', 'id_municipio');
    
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
            data: 'departamento_nombre',
            render: function(data) {
                return data ? data : '<span class="text-muted">-</span>';
            }
        },
        { 
            data: 'municipio_nombre',
            render: function(data) {
                return data ? data : '<span class="text-muted">-</span>';
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

    function cargarLideresDuplicado() {
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
                        $('#id_lider_intento').html(options);

                        if ($('#id_lider_intento').hasClass('select2-hidden-accessible')) {
                            $('#id_lider_intento').select2('destroy');
                        }

                        $('#id_lider_intento').select2({
                            theme: 'bootstrap-5',
                            dropdownParent: $('#modalDuplicado'),
                            placeholder: 'Seleccione un líder',
                            allowClear: true,
                            width: '100%'
                        });
                    }
                }
            });
        }
    }

    function cargarLideresDuplicadoImport() {
        const usuarioNombre = $('#usuario_nombre_actual').val();
        $.ajax({
            url: '../controllers/votantes_controller.php',
            type: 'POST',
            data: { action: 'obtener_lideres' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    let options = '<option value="">Seleccione...</option>';
                    options += `<option value="yo">Por mi (${usuarioNombre})</option>`;
                    response.data.forEach(function(lider) {
                        options += `<option value="${lider.id_lider}">${lider.nombres} ${lider.apellidos}</option>`;
                    });
                    $('#id_lider_intento_import').html(options);

                    if ($('#id_lider_intento_import').hasClass('select2-hidden-accessible')) {
                        $('#id_lider_intento_import').select2('destroy');
                    }

                    $('#id_lider_intento_import').select2({
                        theme: 'bootstrap-5',
                        dropdownParent: $('#modalDuplicadosImport'),
                        placeholder: 'Seleccione un lider',
                        allowClear: true,
                        width: '100%'
                    });
                }
            }
        });
    }
    
    // Limpiar formulario
    function limpiarFormulario() {
        $('#formVotante')[0].reset();
        $('#votante_id').val('');
        $('#action').val('crear');
        $('#modalTitleText').text('Nuevo Votante');
        $('#estadoField').hide();
        
        // Limpiar ubicaciones
        limpiarUbicaciones('id_departamento', 'id_municipio');
        
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
        if (!modalVotanteEdicion) {
            limpiarFormulario();
        }
    });

    $('#modalVotante').on('hidden.bs.modal', function() {
        limpiarFormulario();
        modalVotanteEdicion = false;
    });

    function actualizarBotonVerificar() {
        const valor = $('#identificacion').val() || '';
        const tieneNumero = /\d/.test(valor);
        $('#btnVerificarIdentificacion').prop('disabled', !tieneNumero);
    }

    $('#identificacion').on('input', function() {
        actualizarBotonVerificar();
    });

    $('#btnVerificarIdentificacion').on('click', function() {
        const identificacion = ($('#identificacion').val() || '').trim();
        if (!/\d/.test(identificacion)) {
            return;
        }

        $.ajax({
            url: '../controllers/votantes_controller.php',
            type: 'POST',
            data: { action: 'verificar_identificacion', identificacion: identificacion },
            dataType: 'json',
            success: function(response) {
                if (response && response.success && response.exists) {
                    const data = response.data || {};
                    const sexoTexto = data.sexo === 'M' ? 'Masculino' : (data.sexo === 'F' ? 'Femenino' : (data.sexo || '-'));

                    $('#duplicado_identificacion').val(data.identificacion || identificacion);
                    $('#dup_nombres').val(data.nombres || '');
                    $('#dup_apellidos').val(data.apellidos || '');
                    $('#dup_tipo_identificacion').val(data.nombre_tipo || '');
                    $('#dup_identificacion').val(data.identificacion || identificacion);
                    $('#dup_sexo').val(sexoTexto || '-');
                    $('#dup_telefono').val(data.telefono || '-');
                    $('#dup_departamento').val(data.departamento_nombre || '-');
                    $('#dup_municipio').val(data.municipio_nombre || '-');
                    $('#dup_mesa').val(data.mesa || '0');
                    $('#dup_lugar_mesa').val(data.lugar_mesa || '-');
                    $('#dup_registrado_por').val(data.registrado_por || '-');
                    $('#dup_lider_responsable').val(data.lider_responsable || '-');

                    if (!esLider) {
                        $('#id_lider_intento').val('').trigger('change');
                    }

                    $('#modalDuplicado').modal('show');
                } else if (response && response.success && response.exists === false) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Sin coincidencias',
                        text: 'No hay un votante registrado con esa identificación.'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'No se pudo verificar la identificación.'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error de conexión al verificar la identificación.'
                });
            }
        });
    });

    $('#btnRegistrarDuplicado').on('click', function() {
        const identificacion = ($('#duplicado_identificacion').val() || '').trim();
        let idLiderIntento = 'actual';

        if (!esLider) {
            idLiderIntento = $('#id_lider_intento').val();
            if (!idLiderIntento) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Falta seleccionar líder',
                    text: 'Selecciona quién intentó registrar el duplicado.'
                });
                return;
            }
        }

        $.ajax({
            url: '../controllers/votantes_controller.php',
            type: 'POST',
            data: {
                action: 'registrar_duplicado_intento',
                identificacion: identificacion,
                id_lider_intento: idLiderIntento
            },
            dataType: 'json',
            success: function(response) {
                if (response && response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Registrado',
                        text: response.message || 'Intento duplicado registrado.'
                    });
                    $('#modalDuplicado').modal('hide');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'No se pudo registrar el duplicado.'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error de conexión al registrar el duplicado.'
                });
            }
        });
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
        modalVotanteEdicion = true;
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
                    actualizarBotonVerificar();
                    $('#sexo').val(votante.sexo);
                    $('#telefono').val(votante.telefono || '');
                    $('#mesa').val(votante.mesa || '');
                    $('#lugar_mesa').val(votante.lugar_mesa || '');
                    $('#id_estado').val(votante.id_estado);
                    
                    // Precargar ubicación
                    if (votante.id_departamento && votante.id_municipio) {
                        precargarUbicacion(votante.id_departamento, votante.id_municipio, 'id_departamento', 'id_municipio');
                    }
                    
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
    cargarLideresDuplicado();
    cargarLideresDuplicadoImport();
    actualizarBotonVerificar();
    
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
                            <p class="mb-0">Se mostraran en una ventana para decidir si se guardan como duplicados.</p>
                        </div>`;
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

                    if (response.duplicados_detalle && response.duplicados_detalle.length > 0) {
                        importDuplicados = response.duplicados_detalle;
                        const lista = $('#listaDuplicadosImport');
                        lista.empty();
                        response.duplicados_detalle.forEach(function(dup) {
                            const detalles = dup.detalles ? ` | ${dup.detalles}` : '';
                            const nombre = `${dup.nombres || ''} ${dup.apellidos || ''}`.trim();
                            const texto = `Linea ${dup.linea}: ${nombre} - ${dup.identificacion} (${dup.tipo}: ${dup.nombre})${detalles}`;
                            lista.append(`<li class="list-group-item">${texto}</li>`);
                        });
                        $('#id_lider_intento_import').val('').trigger('change');
                        $('#modalDuplicadosImport').modal('show');
                    }
                    
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

    $('#btnGuardarDuplicadosImport').on('click', function() {
        if (!importDuplicados || importDuplicados.length === 0) {
            return;
        }

        const idLiderIntento = $('#id_lider_intento_import').val();
        if (!idLiderIntento) {
            Swal.fire({
                icon: 'warning',
                title: 'Falta seleccionar lider',
                text: 'Selecciona quien intento registrar los duplicados.'
            });
            return;
        }

        const identificaciones = importDuplicados.map(item => item.identificacion);

        $.ajax({
            url: '../controllers/importar_controller.php',
            type: 'POST',
            data: {
                action: 'registrar_duplicados_importacion',
                identificaciones: JSON.stringify(identificaciones),
                id_lider_intento: idLiderIntento
            },
            dataType: 'json',
            success: function(response) {
                if (response && response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Guardado',
                        text: response.message || 'Duplicados guardados correctamente.'
                    });
                    $('#modalDuplicadosImport').modal('hide');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'No se pudieron guardar los duplicados.'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error de conexion al guardar duplicados.'
                });
            }
        });
    });

    $('#modalDuplicadosImport').on('hidden.bs.modal', function() {
        importDuplicados = [];
        $('#listaDuplicadosImport').empty();
        $('#id_lider_intento_import').val('').trigger('change');
    });
});

// Funciones globales fuera del document.ready

function descargarPlantilla() {
    window.location.href = '../controllers/exportar_controller.php?action=descargar_plantilla';
}
