$(document).ready(function() {
    // Toggle password visibility
    $('#togglePassword').on('click', function() {
        const passwordInput = $('#clave');
        const icon = $(this).find('i');
        
        if (passwordInput.attr('type') === 'password') {
            passwordInput.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            passwordInput.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
    
    // Handle form submission
    $('#loginForm').on('submit', function(e) {
        e.preventDefault();
        
        const usuario = $('#usuario').val().trim();
        const clave = $('#clave').val();
        const recordarme = $('#recordarme').is(':checked');
        
        // Validations
        if (!usuario || !clave) {
            mostrarAlerta('Por favor, completa todos los campos', 'warning');
            return;
        }
        
        // Disable button and show loading
        const btnSubmit = $('.btn-login');
        const originalText = btnSubmit.html();
        btnSubmit.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Iniciando sesión...');
        
        // Send AJAX request
        $.ajax({
            url: 'admin/controllers/login_controller.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'login',
                usuario: usuario,
                clave: clave,
                recordarme: recordarme
            },
            success: function(response) {
                if (response.success) {
                    mostrarAlerta(response.message, 'success');
                    setTimeout(function() {
                        window.location.href = 'admin/views/dashboard.php';
                    }, 1000);
                } else {
                    mostrarAlerta(response.message, 'danger');
                    btnSubmit.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                mostrarAlerta('Error de conexión. Por favor, intenta nuevamente.', 'danger');
                btnSubmit.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Show alert message
    function mostrarAlerta(mensaje, tipo) {
        const alertHtml = `
            <div class="alert alert-${tipo} alert-dismissible fade show" role="alert">
                <i class="fas fa-${getIconByType(tipo)}"></i> ${mensaje}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        $('#alertMessage').html(alertHtml);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut('slow', function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Get icon by alert type
    function getIconByType(tipo) {
        const icons = {
            'success': 'check-circle',
            'danger': 'exclamation-circle',
            'warning': 'exclamation-triangle',
            'info': 'info-circle'
        };
        return icons[tipo] || 'info-circle';
    }
    
    // Clear alerts when typing
    $('#usuario, #clave').on('input', function() {
        $('.alert').fadeOut('slow', function() {
            $(this).remove();
        });
    });
});
