<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Votaciones - Login</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="admin/assets/css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-left">
                <div class="logo-section">
                    <i class="fas fa-vote-yea"></i>
                    <h1>Sistema de Votaciones</h1>
                    <p>Gestión Electoral Inteligente</p>
                </div>
                <div class="features">
                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Sistema Seguro</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-users"></i>
                        <span>Gestión de Votantes</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Reportes en Tiempo Real</span>
                    </div>
                </div>
            </div>
            
            <div class="login-right">
                <div class="login-form-container">
                    <h2>Iniciar Sesión</h2>
                    <p class="subtitle">Ingresa tus credenciales para acceder</p>
                    
                    <div id="alertMessage"></div>
                    
                    <form id="loginForm" method="POST">
                        <div class="form-group">
                            <label for="usuario">
                                <i class="fas fa-user"></i> Usuario
                            </label>
                            <input type="text" class="form-control" id="usuario" name="usuario" 
                                   placeholder="Ingresa tu usuario" required autocomplete="username">
                        </div>
                        
                        <div class="form-group">
                            <label for="clave">
                                <i class="fas fa-lock"></i> Contraseña
                            </label>
                            <div class="password-input">
                                <input type="password" class="form-control" id="clave" name="clave" 
                                       placeholder="Ingresa tu contraseña" required autocomplete="current-password">
                                <button type="button" class="toggle-password" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="recordarme" name="recordarme">
                            <label class="form-check-label" for="recordarme">
                                Recordarme
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-login">
                            <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                        </button>
                    </form>
                    
                    <div class="login-footer">
                        <p>&copy; 2026 Sistema de Votaciones. Todos los derechos reservados.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="admin/assets/js/login.js"></script>
</body>
</html>
