$(document).ready(function() {
    // Toggle password visibility
    $('.toggle-password-btn').on('click', function() {
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
    
    // Cambiar contraseña propia
    $('#formCambiarClave').on('submit', function(e) {
        e.preventDefault();
        
        const claveActual = $('#clave_actual').val();
        const nuevaClave = $('#nueva_clave').val();
        const nuevaClaveConfirm = $('#nueva_clave_confirm').val();
        
        if (nuevaClave !== nuevaClaveConfirm) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Las contraseñas nuevas no coinciden'
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
            url: '../controllers/perfil_controller.php',
            type: 'POST',
            data: {
                action: 'cambiar_clave_propia',
                clave_actual: claveActual,
                nueva_clave: nuevaClave
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
                    $('#modalCambiarClave').modal('hide');
                    $('#formCambiarClave')[0].reset();
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
});
