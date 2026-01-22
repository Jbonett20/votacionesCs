/**
 * Módulo de Ubicaciones (Departamentos y Municipios)
 * Maneja los selects dependientes de ubicación geográfica
 */

// Variable global para almacenar los departamentos cargados
let departamentosData = [];

/**
 * Cargar departamentos en un select
 */
function cargarDepartamentos(selectId = 'id_departamento') {
    const $select = $(`#${selectId}`);
    
    if (!$select.length) {
        console.error(`Select #${selectId} no encontrado`);
        return;
    }
    
    // Si ya están cargados, no hacer otra petición
    if (departamentosData.length > 0) {
        llenarSelectDepartamentos($select, departamentosData);
        return;
    }
    
    $.ajax({
        url: '../controllers/ubicaciones_controller.php',
        type: 'POST',
        data: { action: 'obtener_departamentos' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                departamentosData = response.data;
                llenarSelectDepartamentos($select, response.data);
            } else {
                console.error('Error al cargar departamentos:', response.message);
                Swal.fire('Error', 'No se pudieron cargar los departamentos', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX al cargar departamentos:', error);
            Swal.fire('Error', 'Error de conexión al cargar departamentos', 'error');
        }
    });
}

/**
 * Llenar el select de departamentos con los datos
 */
function llenarSelectDepartamentos($select, departamentos) {
    $select.empty();
    $select.append('<option value="">Seleccione un departamento...</option>');
    
    departamentos.forEach(function(depto) {
        $select.append(`<option value="${depto.id_departamento}">${depto.nombre}</option>`);
    });
}

/**
 * Cargar municipios según el departamento seleccionado
 */
function cargarMunicipios(idDepartamento, selectId = 'id_municipio', valorSeleccionar = null) {
    const $select = $(`#${selectId}`);
    
    if (!$select.length) {
        console.error(`Select #${selectId} no encontrado`);
        return;
    }
    
    // Limpiar select de municipios
    $select.empty();
    $select.append('<option value="">Cargando...</option>');
    $select.prop('disabled', true);
    
    if (!idDepartamento) {
        $select.empty();
        $select.append('<option value="">Primero seleccione un departamento</option>');
        $select.prop('disabled', true);
        return;
    }
    
    $.ajax({
        url: '../controllers/ubicaciones_controller.php',
        type: 'POST',
        data: { 
            action: 'obtener_municipios',
            id_departamento: idDepartamento
        },
        dataType: 'json',
        success: function(response) {
            $select.prop('disabled', false);
            $select.empty();
            
            if (response.success && response.data.length > 0) {
                $select.append('<option value="">Seleccione un municipio...</option>');
                
                response.data.forEach(function(municipio) {
                    $select.append(`<option value="${municipio.id_municipio}">${municipio.nombre}</option>`);
                });
                
                // Si hay un valor a seleccionar, seleccionarlo
                if (valorSeleccionar) {
                    $select.val(valorSeleccionar);
                }
            } else {
                $select.append('<option value="">No hay municipios disponibles</option>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX al cargar municipios:', error);
            $select.prop('disabled', false);
            $select.empty();
            $select.append('<option value="">Error al cargar municipios</option>');
            Swal.fire('Error', 'Error de conexión al cargar municipios', 'error');
        }
    });
}

/**
 * Inicializar el sistema de ubicaciones en un formulario
 * @param {string} departamentoSelectId - ID del select de departamentos
 * @param {string} municipioSelectId - ID del select de municipios
 */
function inicializarUbicaciones(departamentoSelectId = 'id_departamento', municipioSelectId = 'id_municipio') {
    // Cargar departamentos al inicio
    cargarDepartamentos(departamentoSelectId);
    
    // Evento cuando cambia el departamento
    $(`#${departamentoSelectId}`).off('change').on('change', function() {
        const idDepartamento = $(this).val();
        cargarMunicipios(idDepartamento, municipioSelectId);
    });
}

/**
 * Precargar valores de ubicación (útil al editar)
 * @param {number} idDepartamento - ID del departamento a seleccionar
 * @param {number} idMunicipio - ID del municipio a seleccionar
 * @param {string} departamentoSelectId - ID del select de departamentos
 * @param {string} municipioSelectId - ID del select de municipios
 */
function precargarUbicacion(idDepartamento, idMunicipio, departamentoSelectId = 'id_departamento', municipioSelectId = 'id_municipio') {
    // Primero cargar y seleccionar el departamento
    if (departamentosData.length > 0) {
        $(`#${departamentoSelectId}`).val(idDepartamento);
        // Luego cargar los municipios y seleccionar el correspondiente
        cargarMunicipios(idDepartamento, municipioSelectId, idMunicipio);
    } else {
        // Si no hay datos, cargarlos primero
        setTimeout(function() {
            $(`#${departamentoSelectId}`).val(idDepartamento).trigger('change');
            // Esperar un poco para que carguen los municipios
            setTimeout(function() {
                $(`#${municipioSelectId}`).val(idMunicipio);
            }, 500);
        }, 500);
    }
}

/**
 * Limpiar selects de ubicación
 */
function limpiarUbicaciones(departamentoSelectId = 'id_departamento', municipioSelectId = 'id_municipio') {
    $(`#${departamentoSelectId}`).val('');
    $(`#${municipioSelectId}`).empty().append('<option value="">Primero seleccione un departamento</option>');
}
