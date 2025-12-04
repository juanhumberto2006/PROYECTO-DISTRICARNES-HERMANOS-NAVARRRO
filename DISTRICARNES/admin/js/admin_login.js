document.addEventListener('DOMContentLoaded', function() {
    // Obtener elementos del DOM (con verificación de existencia)
    const loginForm = document.getElementById('loginForm');
    const passwordError = document.getElementById('passwordError');
    const togglePassword = document.getElementById('togglePassword1');
    const passwordInput = document.getElementById('password');
    const emailInput = document.getElementById('email');
    const loginButton = document.getElementById('loginButton');

    // Si no existe el formulario, salir silenciosamente
    if (!loginForm) return;

    // Mostrar/ocultar contraseña (solo si existen los elementos)
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function() {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            this.textContent = isPassword ? '🔒' : '👁️';
        });
    }

    // Manejar el envío del formulario
    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Validar que los campos existan y tengan valor
        const email = emailInput ? emailInput.value.trim() : '';
        const password = passwordInput ? passwordInput.value : '';

        if (!email) {
            Swal.fire({
                icon: 'warning',
                title: 'Campo requerido',
                text: 'Por favor ingresa tu correo electrónico.',
                confirmButtonColor: '#ff0000',
                background: '#1a1a1a',
                color: '#ffffff'
            });
            return;
        }
        if (!password) {
            Swal.fire({
                icon: 'warning',
                title: 'Campo requerido',
                text: 'Por favor ingresa tu contraseña.',
                confirmButtonColor: '#ff0000',
                background: '#1a1a1a',
                color: '#ffffff'
            });
            return;
        }

        // Guardar texto original del botón
        const originalText = loginButton ? loginButton.innerHTML : 'Iniciar sesión';

        // Estado de carga
        if (loginButton) {
            loginButton.disabled = true;
            loginButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Iniciando sesión...';
        }

        try {
            // Ruta corregida desde el directorio admin
            const response = await fetch('../backend/php/login_verify.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({ email, password })
            });

            // Verificar si la respuesta es JSON válido
            let result;
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                result = await response.json();
            } else {
                // Si no es JSON, probablemente es un error 404 o 500
                throw new Error('El servidor no respondió correctamente.');
            }

            // Procesar respuesta
            if (result.success) {
                // Guardar datos completos del usuario
                const userData = {
                    isLoggedIn: true,
                    user: result.user,
                    loginTime: new Date().toISOString()
                };
                
                // Verificar que el usuario tenga rol de admin
                if (result.user && result.user.rol === 'admin') {
                    // Guardar en localStorage (persiste entre sesiones)
                    localStorage.setItem('userData', JSON.stringify(userData));
                    
                    // Guardar en sessionStorage (solo para la sesión actual)
                    sessionStorage.setItem('currentSession', JSON.stringify(userData));
                    
                    // Mostrar mensaje de éxito con SweetAlert2
                    Swal.fire({
                        icon: 'success',
                        title: `¡Bienvenido ${result.user.nombre}!`,
                        text: 'Inicio de sesión exitoso. Redirigiendo al panel de administración...',
                        timer: 2000,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        confirmButtonColor: '#ff0000',
                        background: '#1a1a1a',
                        color: '#ffffff'
                    });
                    
                    // Mostrar estado en el botón
                    if (loginButton) {
                        loginButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ¡Éxito! Redirigiendo...';
                    }
                    
                    // Redirección con delay para mejor UX
                    setTimeout(() => {
                        window.location.href = 'admin_dashboard.html';
                    }, 2000);
                } else {
                    // Solo permitir acceso a usuarios con rol 'admin'
                    Swal.fire({
                        icon: 'error',
                        title: 'Acceso denegado',
                        text: 'Rol no autorizado.',
                        confirmButtonColor: '#ff0000',
                        background: '#1a1a1a',
                        color: '#ffffff'
                    });
                    
                    // Limpiar datos de sesión ya que no es admin
                    localStorage.removeItem('userData');
                    sessionStorage.removeItem('currentSession');
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de autenticación',
                    text: result.message || 'Credenciales incorrectas. Verifica tu email y contraseña.',
                    confirmButtonColor: '#ff0000',
                    background: '#1a1a1a',
                    color: '#ffffff'
                });
            }

        } catch (error) {
            console.error('Error durante el inicio de sesión:', error);
            // Distinguir entre error de red y otro tipo
            if (error.message.includes('fetch') || error.message.includes('Failed to fetch')) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: 'No se pudo conectar al servidor. Verifica tu conexión a internet.',
                    confirmButtonColor: '#ff0000',
                    background: '#1a1a1a',
                    color: '#ffffff'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error inesperado',
                    text: 'Ocurrió un error inesperado. Por favor, inténtalo más tarde.',
                    confirmButtonColor: '#ff0000',
                    background: '#1a1a1a',
                    color: '#ffffff'
                });
            }
        } finally {
            // Restaurar botón
            if (loginButton) {
                loginButton.disabled = false;
                loginButton.innerHTML = originalText;
            }
        }
    });

    // Función para mostrar errores (mantenida para compatibilidad)
    function showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: message,
            confirmButtonColor: '#ff0000',
            background: '#1a1a1a',
            color: '#ffffff'
        });
    }
});