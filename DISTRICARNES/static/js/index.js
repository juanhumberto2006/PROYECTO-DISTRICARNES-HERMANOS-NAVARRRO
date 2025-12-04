document.addEventListener('DOMContentLoaded', function() {
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userMenu = document.getElementById('userMenu');

    if (userMenuBtn && userMenu) {
        userMenuBtn.addEventListener('click', function() {
            userMenu.classList.toggle('user-menu-active');
        });

        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            if (!userMenuBtn.contains(event.target) && !userMenu.contains(event.target)) {
                userMenu.classList.remove('user-menu-active');
            }
        });
    }
});

function handleLogout(e) {
    e.preventDefault();

    // Create and show confirmation modal
    const modal = document.createElement('div');
    modal.innerHTML = `
<div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999; ">
<div style="background: white; padding: 20px; border-radius: 8px; text-align: center; max-width: 400px; width: 90%; ">
<h3 style="margin-bottom: 15px; color: #333; ">¿Estás seguro que deseas cerrar sesión?</h3>
<div style="display: flex; justify-content: center; gap: 10px; ">
<button onclick="confirmLogout() " 
style="background: #dc2626; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; ">
Sí, cerrar sesión
</button>
<button onclick="this.parentElement.parentElement.parentElement.remove() " 
style="background: #gray; color: #333; padding: 8px 16px; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; ">
Cancelar
</button>
</div>
</div>
</div>
`;
    document.body.appendChild(modal);
}

function confirmLogout() {
    // Marcar logout y limpiar estado accesible antes de redirigir
    try {
        sessionStorage.setItem('logoutFlag', '1');
        localStorage.removeItem('userData');
        sessionStorage.removeItem('currentSession');
        window.dispatchEvent(new CustomEvent('auth:loggedOut'));
    } catch (e) {}

    // Show success message
    const message = document.createElement('div');
    message.innerHTML = `
<div style="position: fixed; top: 20px; right: 20px; background: #22c55e; color: white; padding: 15px; border-radius: 6px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); z-index: 9999; animation: slideIn 0.3s ease-out; ">
¡Sesión cerrada exitosamente! Redirigiendo...
</div>
`;
    document.body.appendChild(message);

    // Add animation
    const style = document.createElement('style');
    style.textContent = `
@keyframes slideIn {
from { transform: translateX(100%); }
to { transform: translateX(0); }
}
`;
    document.head.appendChild(style);

    // Redirect after delay
    setTimeout(() => {
        window.location.href = "{% url 'logout' %} ";
    }, 1500);
}
//-----------------------------CARRUSERL DE EQUIPO FUNIONALIDAD-----------------------------

// Funcionalidad del carrusel de equipos
class TeamCarousel {
    constructor() {
        this.currentSlide = 0;
        this.slides = document.querySelectorAll('.team-slide');
        this.dots = document.querySelectorAll('.dot');
        this.prevBtn = document.getElementById('prevBtn');
        this.nextBtn = document.getElementById('nextBtn');
        this.totalSlides = this.slides.length;
        this.autoPlayInterval = null;
        this.isAutoPlaying = true;
        this.autoPlayDelay = 5000; // 5 segudos de retraso entre diapositivas

        this.init();
    }

    init() {
        // Configurar los controladores de eventos
        this.setupEventListeners();

        // Iniciar la reproducción automática cuando la sección sea visible
        this.setupIntersectionObserver();

        // Inicializar la primera diapositiva
        this.showSlide(0);
    }

    setupEventListeners() {
        // Flechas de navegación
        if (this.prevBtn) {
            this.prevBtn.addEventListener('click', () => this.prevSlide());
        }

        if (this.nextBtn) {
            this.nextBtn.addEventListener('click', () => this.nextSlide());
        }

        // Puntos de navegación
        this.dots.forEach((dot, index) => {
            dot.addEventListener('click', () => this.goToSlide(index));
        });

        // Pausa al pasar el ratón por encima
        const carouselContainer = document.querySelector('.team-carousel-container');
        if (carouselContainer) {
            carouselContainer.addEventListener('mouseenter', () => this.pauseAutoPlay());
            carouselContainer.addEventListener('mouseleave', () => this.resumeAutoPlay());
        }

        // Navegación por teclado
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') this.prevSlide();
            if (e.key === 'ArrowRight') this.nextSlide();
        });

        // Compatibilidad con gestos táctiles/de deslizamiento
        this.setupTouchEvents();
    }

    setupIntersectionObserver() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.startAutoPlay();
                } else {
                    this.pauseAutoPlay();
                }
            });
        }, {
            threshold: 0.3
        });

        const teamSection = document.getElementById('team-section');
        if (teamSection) {
            observer.observe(teamSection);
        }
    }

    setupTouchEvents() {
        let startX = 0;
        let endX = 0;

        const carousel = document.querySelector('.team-carousel');
        if (!carousel) return;

        carousel.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
        });

        carousel.addEventListener('touchend', (e) => {
            endX = e.changedTouches[0].clientX;
            this.handleSwipe(startX, endX);
        });
    }

    handleSwipe(startX, endX) {
        const threshold = 50;
        const diff = startX - endX;

        if (Math.abs(diff) > threshold) {
            if (diff > 0) {
                this.nextSlide();
            } else {
                this.prevSlide();
            }
        }
    }

    showSlide(index) {
        // Eliminar la clase "active" de todas las diapositivas y los puntos de navegación.
        this.slides.forEach(slide => {
            slide.classList.remove('active', 'prev', 'next');
        });

        this.dots.forEach(dot => {
            dot.classList.remove('active');
        });

        //Añadir la clase "active" a la diapositiva y al punto de navegación actuales
        if (this.slides[index]) {
            this.slides[index].classList.add('active');
        }

        if (this.dots[index]) {
            this.dots[index].classList.add('active');
        }

        // Establecer las clases prev y next para lograr transiciones suaves
        const prevIndex = (index - 1 + this.totalSlides) % this.totalSlides;
        const nextIndex = (index + 1) % this.totalSlides;

        if (this.slides[prevIndex]) {
            this.slides[prevIndex].classList.add('prev');
        }

        if (this.slides[nextIndex]) {
            this.slides[nextIndex].classList.add('next');
        }

        this.currentSlide = index;

        //Activar la animación de entrada
        this.triggerSlideAnimation(index);
    }

    triggerSlideAnimation(index) {
        const slide = this.slides[index];
        if (!slide) return;

        // Reiniciar animación
        const card = slide.querySelector('.team-card-carousel');
        const image = slide.querySelector('.team-image-carousel');
        const glow = slide.querySelector('.card-glow');

        if (card) {
            card.style.animation = 'none';
            card.offsetHeight; //Activar el reflujo
            card.style.animation = 'cardEntrance 1s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards';
        }

        if (image) {
            image.style.animation = 'none';
            image.offsetHeight; //Activar el reflujo
            image.style.animation = 'imageFloat 4s ease-in-out infinite';
        }

        if (glow) {
            glow.style.animation = 'none';
            glow.offsetHeight; //Activar el reflujo
            glow.style.animation = 'glowPulse 3s ease-in-out infinite';
        }
    }

    nextSlide() {
        const nextIndex = (this.currentSlide + 1) % this.totalSlides;
        this.showSlide(nextIndex);
        this.resetAutoPlay();
    }

    prevSlide() {
        const prevIndex = (this.currentSlide - 1 + this.totalSlides) % this.totalSlides;
        this.showSlide(prevIndex);
        this.resetAutoPlay();
    }

    goToSlide(index) {
        if (index !== this.currentSlide) {
            this.showSlide(index);
            this.resetAutoPlay();
        }
    }

    startAutoPlay() {
        if (this.isAutoPlaying && !this.autoPlayInterval) {
            this.autoPlayInterval = setInterval(() => {
                this.nextSlide();
            }, this.autoPlayDelay);
        }
    }

    pauseAutoPlay() {
        if (this.autoPlayInterval) {
            clearInterval(this.autoPlayInterval);
            this.autoPlayInterval = null;
        }
    }

    resumeAutoPlay() {
        if (this.isAutoPlaying) {
            this.startAutoPlay();
        }
    }

    resetAutoPlay() {
        this.pauseAutoPlay();
        this.resumeAutoPlay();
    }

    toggleAutoPlay() {
        this.isAutoPlaying = !this.isAutoPlaying;
        if (this.isAutoPlaying) {
            this.startAutoPlay();
        } else {
            this.pauseAutoPlay();
        }
    }
}

// Inicializar el carrusel cuando se haya cargado el DOM
document.addEventListener('DOMContentLoaded', function() {
    // Comprueba si existen los elementos del carrusel del equipo
    const teamCarouselContainer = document.querySelector('.team-carousel-container');
    if (teamCarouselContainer) {
        new TeamCarousel();
    }

    // Añadir desplazamiento suave para mejorar la experiencia del usuario
    document.querySelectorAll('a[href^="# "]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
});

// Añadir animación de carga para la sección del equipo
window.addEventListener('load', function() {
    const teamSection = document.getElementById('team-section');
    if (teamSection) {
        teamSection.style.opacity = '0';
        teamSection.style.transform = 'translateY(50px)';
        teamSection.style.transition = 'all 1s ease-out';

        setTimeout(() => {
            teamSection.style.opacity = '1';
            teamSection.style.transform = 'translateY(0)';
        }, 300);
    }
});
//-----------------------------MENU MOVIL-----------------------------
// menú móvil
document.addEventListener('DOMContentLoaded', function() {
    const mobileToggle = document.getElementById('mobileToggle');
    const navMenu = document.getElementById('navMenu');

    if (mobileToggle && navMenu) {
        mobileToggle.addEventListener('click', function() {
            navMenu.classList.toggle('active');
        });
    }
});

//-----------------------------USER DROPDOWN MENU-----------------------------
// Función para alternar el menú desplegable del usuario
function toggleUserDropdown() {
    const dropdown = document.getElementById('userDropdown');
    const profileContainer = dropdown.closest('.user-profile-container');
    
    if (dropdown && profileContainer) {
        dropdown.classList.toggle('show');
        profileContainer.classList.toggle('active');
    }
}

// Cerrar el menú cuando se hace clic fuera de él
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('userDropdown');
    const profileContainer = document.querySelector('.user-profile-container');
    
    if (dropdown && profileContainer) {
        const isClickInsideMenu = profileContainer.contains(event.target);
        
        if (!isClickInsideMenu && dropdown.classList.contains('show')) {
            dropdown.classList.remove('show');
            profileContainer.classList.remove('active');
        }
    }
});

// Función para actualizar la información del usuario en el menú
function updateUserMenuInfo(userData) {
    // Actualizar avatar pequeño
    const userAvatar = document.getElementById('userAvatar');
    const userName = document.getElementById('userName');
    
    // Actualizar avatar grande y detalles en el dropdown
    const userAvatarLarge = document.getElementById('userAvatarLarge');
    const userFullName = document.getElementById('userFullName');
    const userEmail = document.getElementById('userEmail');
    const userRole = document.getElementById('userRole');
    
    if (userData) {
        const initials = userData.nombre ? userData.nombre.charAt(0).toUpperCase() : 'A';
        
        // Actualizar elementos del botón principal
        if (userAvatar) userAvatar.textContent = initials;
        if (userName) userName.textContent = userData.nombre || 'Administrador';
        
        // Actualizar elementos del dropdown
        if (userAvatarLarge) userAvatarLarge.textContent = initials;
        if (userFullName) userFullName.textContent = userData.nombre || 'Administrador';
        if (userEmail) userEmail.textContent = userData.email || 'admin@districarnes.com';
        if (userRole) userRole.textContent = userData.rol || 'Administrador';
    }
}