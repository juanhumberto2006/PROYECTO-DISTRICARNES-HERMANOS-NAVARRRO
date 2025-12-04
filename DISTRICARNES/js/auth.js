/**
 * Sistema de Autenticación Global - DISTRICARNES
 * Este script maneja la autenticación de usuarios en todas las páginas del sitio
 */

// Objeto global para manejar la autenticación
const AuthSystem = {
    
    /**
     * Inicializa el sistema de autenticación
     */
    init: function() {
        this.checkUserSession();
        this.setupEventListeners();
    },

    /**
     * Verifica si el usuario está logueado
     */
    checkUserSession: function() {
        const userData = localStorage.getItem('userData');
        const sessionData = sessionStorage.getItem('currentSession');
        
        if (userData || sessionData) {
            const user = JSON.parse(userData || sessionData);
            
            if (user && user.isLoggedIn) {
                this.showLoggedInState(user);
                return true;
            }
        }
        
        this.showLoggedOutState();
        return false;
    },

    /**
     * Muestra el estado de usuario logueado
     */
    showLoggedInState: function(user) {
        // Normalizar estructura: algunos flujos guardan { isLoggedIn, user: { ... } }
        const currentUser = (user && user.user) ? user.user : user;
        // Ocultar botones de login/registro
        const authButtons = document.getElementById('authButtons');
        if (authButtons) {
            authButtons.style.display = 'none';
        }

        // Mostrar botones de usuario logueado
        const userLoggedButtons = document.getElementById('userLoggedButtons');
        if (userLoggedButtons) {
            userLoggedButtons.style.display = 'block';
        }

        // Mostrar mensaje de bienvenida
        const welcomeElement = document.getElementById('userWelcome');
        if (welcomeElement && currentUser) {
            const displayName = currentUser.nombres_completos || currentUser.nombre || currentUser.correo_electronico || currentUser.email || 'Usuario';
            welcomeElement.textContent = `¡Bienvenido, ${displayName}!`;
        }

        // Actualizar elementos del menú de usuario si existen
        if (currentUser) {
            const nameForUI = currentUser.nombres_completos || currentUser.nombre || currentUser.correo_electronico || currentUser.email || 'Usuario';
            const initials = (nameForUI.charAt(0) || 'U').toUpperCase();

            const userAvatar = document.getElementById('userAvatar');
            const userName = document.getElementById('userName');
            const userAvatarLarge = document.getElementById('userAvatarLarge');
            const userFullName = document.getElementById('userFullName');
            const userEmail = document.getElementById('userEmail');
            const userRole = document.getElementById('userRole');

            if (userAvatar) userAvatar.textContent = initials;
            if (userName) userName.textContent = nameForUI;
            if (userAvatarLarge) userAvatarLarge.textContent = initials;
            if (userFullName) userFullName.textContent = nameForUI;
            if (userEmail) userEmail.textContent = currentUser.correo_electronico || currentUser.email || '';
            if (userRole) userRole.textContent = currentUser.rol ? currentUser.rol.charAt(0).toUpperCase() + currentUser.rol.slice(1) : '';
        }

        // Actualizar cualquier otro elemento específico de la página
        this.updatePageSpecificElements(user, true);
    },

    /**
     * Muestra el estado de usuario no logueado
     */
    showLoggedOutState: function() {
        // Mostrar botones de login/registro
        const authButtons = document.getElementById('authButtons');
        if (authButtons) {
            authButtons.style.display = 'block';
        }

        // Ocultar botones de usuario logueado
        const userLoggedButtons = document.getElementById('userLoggedButtons');
        if (userLoggedButtons) {
            userLoggedButtons.style.display = 'none';
        }

        // Limpiar mensaje de bienvenida
        const welcomeElement = document.getElementById('userWelcome');
        if (welcomeElement) {
            welcomeElement.textContent = '';
        }

        // Actualizar cualquier otro elemento específico de la página
        this.updatePageSpecificElements(null, false);
    },

    /**
     * Actualiza elementos específicos de cada página según el estado de login
     */
    updatePageSpecificElements: function(user, isLoggedIn) {
        // Esta función puede ser extendida para manejar elementos específicos de cada página
        
        // Ejemplo: Mostrar/ocultar secciones premium
        const premiumSections = document.querySelectorAll('.premium-content');
        premiumSections.forEach(section => {
            section.style.display = isLoggedIn ? 'block' : 'none';
        });

        // Ocultar/mostrar el botón del carrito
        const cartButton = document.getElementById('cartButton');
        if (cartButton) {
            cartButton.style.display = isLoggedIn ? 'flex' : 'none'; 
        }

        // Ejemplo: Actualizar enlaces de carrito
        const cartLinks = document.querySelectorAll('.cart-link');
        cartLinks.forEach(link => {
            if (isLoggedIn) {
                link.style.opacity = '1';
                link.style.pointerEvents = 'auto';
            } else {
                link.style.opacity = '0.5';
                link.style.pointerEvents = 'none';
            }
        });
    },

    /**
     * Función para cerrar sesión
     */
    logout: function() {
        // Usar SweetAlert2 si está disponible, sino usar confirm nativo
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: '¿Cerrar Sesión?',
                text: '¿Estás seguro de que deseas cerrar sesión?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, cerrar sesión',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.performLogout();
                    
                    Swal.fire({
                        title: '¡Sesión Cerrada!',
                        text: 'Has cerrado sesión exitosamente',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            });
        } else {
            if (confirm('¿Estás seguro de que deseas cerrar sesión?')) {
                this.performLogout();
            }
        }
    },

    /**
     * Ejecuta el proceso de logout
     */
    performLogout: function() {
        // Limpiar datos de sesión
        localStorage.removeItem('userData');
        sessionStorage.removeItem('currentSession');
        
        // Actualizar UI
        this.showLoggedOutState();

        // Notificar a la app que se cerró sesión (para limpiar estados como carrito/favoritos)
        try {
            window.dispatchEvent(new CustomEvent('auth:loggedOut'));
        } catch (e) { /* noop */ }
        
        // Opcional: redirigir a login después de un breve delay
        // setTimeout(() => {
        //     window.location.href = './login/login.html';
        // }, 2000);
    },

    /**
     * Configura los event listeners
     */
    setupEventListeners: function() {
        // Listener para cambios en localStorage (para sincronizar entre pestañas)
        window.addEventListener('storage', (e) => {
            if (e.key === 'userData' || e.key === 'currentSession') {
                this.checkUserSession();
            }
        });

        // Listener para el evento de logout personalizado
        window.addEventListener('userLogout', () => {
            this.performLogout();
        });

        // Listener para el evento de login personalizado
        window.addEventListener('userLogin', (e) => {
            this.showLoggedInState(e.detail.user);
        });
    },

    /**
     * Obtiene los datos del usuario actual
     */
    getCurrentUser: function() {
        const userData = localStorage.getItem('userData');
        const sessionData = sessionStorage.getItem('currentSession');
        
        if (userData || sessionData) {
            const raw = JSON.parse(userData || sessionData);
            const normalized = (raw && raw.user) ? raw.user : raw;
            return (raw && raw.isLoggedIn) ? normalized : null;
        }
        
        return null;
    },

    /**
     * Verifica si el usuario está logueado
     */
    isLoggedIn: function() {
        return this.getCurrentUser() !== null;
    }
};

// Función global para logout (mantener compatibilidad)
function logout() {
    AuthSystem.logout();
}

// Inicializar el sistema cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    AuthSystem.init();
});

// También inicializar en window.onload como respaldo
window.addEventListener('load', function() {
    AuthSystem.init();
});