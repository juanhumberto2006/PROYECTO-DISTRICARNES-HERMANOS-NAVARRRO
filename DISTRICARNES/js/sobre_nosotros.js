// ===== ULTRA MODERN SOBRE NOSOTROS JAVASCRIPT =====

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all ultra-modern features
    initHeroAnimations();
    initScrollAnimations();
    initCounterAnimations();
    initParallaxEffects();
    initVideoControls();
    initFloatingElements();
    initProgressBars();
    initInteractiveElements();
    initSmoothScrolling();
    initWordRevealAnimation();
});

// ===== HERO SECTION ANIMATIONS =====
function initHeroAnimations() {
    // Animate hero elements on load
    const heroElements = document.querySelectorAll('.hero-content-ultra > *');
    
    heroElements.forEach((element, index) => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(50px)';
        
        setTimeout(() => {
            element.style.transition = 'all 0.8s ease-out';
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        }, index * 200);
    });

    // Floating stats animation
    animateFloatingStats();

    // Ensure hero counters start immediately
    const heroStatNumbers = document.querySelectorAll('.floating-stats .stat-number');
    heroStatNumbers.forEach(el => {
        if (!el.classList.contains('animated')) {
            el.classList.add('animated');
            animateCounter(el);
        }
    });
    
    // Hero video controls
    initHeroVideoControls();
}

function animateFloatingStats() {
    const statCards = document.querySelectorAll('.stat-card');
    
    statCards.forEach((card, index) => {
        // Add floating animation
        card.style.animation = `float ${6 + index}s ease-in-out infinite`;
        card.style.animationDelay = `${index * 0.5}s`;
        
        // Add hover effects
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-15px) scale(1.05)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(-10px) scale(1)';
        });
    });
}

function initHeroVideoControls() {
    const heroVideo = document.querySelector('.hero-video');
    if (heroVideo) {
        // Auto-play with muted sound
        heroVideo.muted = true;
        heroVideo.play().catch(e => console.log('Video autoplay failed:', e));
        
        // Add video overlay interaction
        const overlay = document.querySelector('.hero-video-overlay');
        if (overlay) {
            overlay.addEventListener('click', function() {
                if (heroVideo.paused) {
                    heroVideo.play();
                } else {
                    heroVideo.pause();
                }
            });
        }
    }
}

// ===== SCROLL ANIMATIONS =====
function initScrollAnimations() {
    // Create intersection observer for scroll animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('aos-animate');
                
                // Trigger specific animations based on element type
                if (entry.target.classList.contains('stat-card-ultra')) {
                    animateStatCard(entry.target);
                }
                
                if (entry.target.classList.contains('floating-card')) {
                    animateFloatingCard(entry.target);
                }
            }
        });
    }, observerOptions);

    // Observe all animated elements
    const animatedElements = document.querySelectorAll('[data-aos], .stat-card-ultra, .floating-card, .story-image-container, .video-container-ultra');
    animatedElements.forEach(el => observer.observe(el));
}

function animateStatCard(card) {
    const number = card.querySelector('.stat-number-animated');
    const progressBar = card.querySelector('.progress-bar');
    
    if (number) {
        animateCounter(number);
    }
    
    if (progressBar) {
        const progress = progressBar.getAttribute('data-progress') || '85';
        setTimeout(() => {
            progressBar.style.width = progress + '%';
            progressBar.parentElement.classList.add('progress-animated');
        }, 500);
    }
}

function animateFloatingCard(card) {
    const number = card.querySelector('.card-number');
    if (number) {
        animateCounter(number);
    }
    
    // Add staggered animation
    card.style.transform = 'translateY(50px)';
    card.style.opacity = '0';
    
    setTimeout(() => {
        card.style.transition = 'all 0.8s ease-out';
        card.style.transform = 'translateY(0)';
        card.style.opacity = '1';
    }, Math.random() * 500);
}

// ===== COUNTER ANIMATIONS =====
function initCounterAnimations() {
    // Inicializar contadores para elementos con clase stat-number
    const statNumbers = document.querySelectorAll('.stat-number, .stat-number-animated');
    
    // Configurar observer para animar cuando entren en vista
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && !entry.target.classList.contains('animated')) {
                entry.target.classList.add('animated');
                animateCounter(entry.target);
            }
        });
    }, { threshold: 0.5 });
    
    // Observar todos los elementos de estadísticas
    statNumbers.forEach(element => {
        observer.observe(element);
    });
}

function animateCounter(element) {
    // Obtener el valor target del atributo data-target o del contenido del elemento
    let target = element.getAttribute('data-target');
    if (!target) {
        // Extraer solo números eliminando separadores de miles y decimales
        target = element.textContent.replace(/[\d.,]/g, match => (/[\d]/.test(match) ? match : ''));
    }
    
    // Convertir a número y validar
    target = parseInt(target);
    if (isNaN(target) || target < 0) {
        target = 0;
    }
    
    const duration = 2000;
    const increment = target / (duration / 16);
    let current = 0;
    
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            current = target;
            clearInterval(timer);
        }
        
        // Format number with original suffix
        const originalText = element.textContent;
        const suffix = originalText.replace(/[\d.,]/g, '');
        const locale = document.documentElement.lang || navigator.language || 'es-ES';
        element.textContent = Math.floor(current).toLocaleString(locale) + suffix;
        
        // Add pulse effect during counting
        element.classList.add('counting');
        setTimeout(() => element.classList.remove('counting'), 100);
    }, 16);
}

// ===== PARALLAX EFFECTS =====
function initParallaxEffects() {
    const parallaxElements = document.querySelectorAll('.parallax-layer');
    
    window.addEventListener('scroll', () => {
        const scrolled = window.pageYOffset;
        const rate = scrolled * -0.5;
        
        parallaxElements.forEach((element, index) => {
            const speed = (index + 1) * 0.3;
            element.style.transform = `translateY(${rate * speed}px)`;
        });
        
        // Animate floating shapes
        animateFloatingShapes(scrolled);
    });
}

function animateFloatingShapes(scrolled) {
    const shapes = document.querySelectorAll('.shape');
    
    shapes.forEach((shape, index) => {
        const speed = (index + 1) * 0.2;
        const rotation = scrolled * 0.1;
        shape.style.transform = `translateY(${scrolled * speed}px) rotate(${rotation}deg)`;
    });
}

// ===== VIDEO CONTROLS =====
function initVideoControls() {
    // Story section video
    initStoryVideo();
    
    // Who we are section video
    initWhoWeAreVideo();
}

function initStoryVideo() {
    const playButton = document.querySelector('.play-button-ultra');
    const videoContainer = document.querySelector('.story-image-container');
    
    if (playButton && videoContainer) {
        playButton.addEventListener('click', function() {
            // Create video modal or inline player
            showVideoModal('https://www.youtube.com/embed/dQw4w9WgXcQ');
        });
    }
}

function initWhoWeAreVideo() {
    const videoElement = document.querySelector('.who-video');
    const playBtn = document.querySelector('.video-play-btn');
    const controls = document.querySelectorAll('.control-item');
    
    if (videoElement && playBtn) {
        playBtn.addEventListener('click', function() {
            if (videoElement.paused) {
                videoElement.play();
                this.innerHTML = '<i class="fas fa-pause"></i>';
            } else {
                videoElement.pause();
                this.innerHTML = '<i class="fas fa-play"></i>';
            }
        });
    }
    
    // Custom video controls
    controls.forEach(control => {
        control.addEventListener('click', function() {
            const action = this.getAttribute('data-action');
            handleVideoControl(videoElement, action);
        });
    });
}

function handleVideoControl(video, action) {
    if (!video) return;
    
    switch(action) {
        case 'play':
            video.paused ? video.play() : video.pause();
            break;
        case 'mute':
            video.muted = !video.muted;
            break;
        case 'fullscreen':
            if (video.requestFullscreen) {
                video.requestFullscreen();
            }
            break;
        case 'volume-up':
            video.volume = Math.min(1, video.volume + 0.1);
            break;
        case 'volume-down':
            video.volume = Math.max(0, video.volume - 0.1);
            break;
    }
}

function showVideoModal(videoUrl) {
    // Create modal overlay
    const modal = document.createElement('div');
    modal.className = 'video-modal-overlay';
    modal.innerHTML = `
        <div class="video-modal-content">
            <button class="video-modal-close">&times;</button>
            <iframe src="${videoUrl}" frameborder="0" allowfullscreen></iframe>
        </div>
    `;
    
    // Add modal styles
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.9);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        opacity: 0;
        transition: opacity 0.3s ease;
    `;
    
    const content = modal.querySelector('.video-modal-content');
    content.style.cssText = `
        position: relative;
        width: 90%;
        max-width: 800px;
        aspect-ratio: 16/9;
        background: #000;
        border-radius: 10px;
        overflow: hidden;
        transform: scale(0.8);
        transition: transform 0.3s ease;
    `;
    
    const iframe = modal.querySelector('iframe');
    iframe.style.cssText = `
        width: 100%;
        height: 100%;
    `;
    
    const closeBtn = modal.querySelector('.video-modal-close');
    closeBtn.style.cssText = `
        position: absolute;
        top: -40px;
        right: 0;
        background: none;
        border: none;
        color: white;
        font-size: 30px;
        cursor: pointer;
        z-index: 1;
    `;
    
    document.body.appendChild(modal);
    
    // Animate in
    setTimeout(() => {
        modal.style.opacity = '1';
        content.style.transform = 'scale(1)';
    }, 10);
    
    // Close modal
    const closeModal = () => {
        modal.style.opacity = '0';
        content.style.transform = 'scale(0.8)';
        setTimeout(() => document.body.removeChild(modal), 300);
    };
    
    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
}

// ===== FLOATING ELEMENTS =====
function initFloatingElements() {
    // Add mouse parallax effect to floating cards
    const floatingCards = document.querySelectorAll('.floating-card, .stat-card');
    
    document.addEventListener('mousemove', (e) => {
        const mouseX = e.clientX / window.innerWidth;
        const mouseY = e.clientY / window.innerHeight;
        
        floatingCards.forEach((card, index) => {
            const speed = (index % 3 + 1) * 2;
            const x = (mouseX - 0.5) * speed;
            const y = (mouseY - 0.5) * speed;
            
            card.style.transform += ` translate(${x}px, ${y}px)`;
        });
    });
    
    // Add random floating animation
    floatingCards.forEach((card, index) => {
        const delay = index * 200;
        const duration = 3000 + (index * 500);
        
        setTimeout(() => {
            card.style.animation += `, floatRandom ${duration}ms ease-in-out infinite`;
        }, delay);
    });
}

// ===== PROGRESS BARS =====
function initProgressBars() {
    const progressBars = document.querySelectorAll('.progress-bar');
    
    progressBars.forEach(bar => {
        const progress = Math.floor(Math.random() * 40) + 60; // Random between 60-100
        bar.setAttribute('data-progress', progress);
        bar.style.setProperty('--progress-width', progress + '%');
    });
}

// ===== INTERACTIVE ELEMENTS =====
function initInteractiveElements() {
    // Add ripple effect to buttons
    const buttons = document.querySelectorAll('.btn-primary-ultra, .btn-secondary-ultra, .btn-experience-ultra');
    
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            createRippleEffect(e, this);
        });
    });
    
    // Add magnetic effect to cards
    const cards = document.querySelectorAll('.stat-card-ultra, .floating-card');
    
    cards.forEach(card => {
        card.addEventListener('mousemove', function(e) {
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left - rect.width / 2;
            const y = e.clientY - rect.top - rect.height / 2;
            
            this.style.transform = `translate(${x * 0.1}px, ${y * 0.1}px) rotateX(${y * 0.05}deg) rotateY(${x * 0.05}deg)`;
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translate(0, 0) rotateX(0) rotateY(0)';
        });
    });
}

function createRippleEffect(event, element) {
    const ripple = document.createElement('span');
    const rect = element.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = event.clientX - rect.left - size / 2;
    const y = event.clientY - rect.top - size / 2;
    
    ripple.style.cssText = `
        position: absolute;
        width: ${size}px;
        height: ${size}px;
        left: ${x}px;
        top: ${y}px;
        background: rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        transform: scale(0);
        animation: ripple 0.6s ease-out;
        pointer-events: none;
    `;
    
    element.style.position = 'relative';
    element.style.overflow = 'hidden';
    element.appendChild(ripple);
    
    setTimeout(() => ripple.remove(), 600);
}

// ===== SMOOTH SCROLLING =====
function initSmoothScrolling() {
    // Add smooth scrolling to anchor links
    const links = document.querySelectorAll('a[href^="#"]');
    
    links.forEach(link => {
        link.addEventListener('click', function(e) {
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
    
    // Add scroll indicator functionality
    const scrollIndicator = document.querySelector('.scroll-indicator');
    if (scrollIndicator) {
        scrollIndicator.addEventListener('click', function() {
            window.scrollTo({
                top: window.innerHeight,
                behavior: 'smooth'
            });
        });
    }
}

// ===== WORD REVEAL ANIMATION =====
function initWordRevealAnimation() {
    const titleElements = document.querySelectorAll('.story-title-ultra, .who-title-ultra, .experience-title');
    
    titleElements.forEach(title => {
        const words = title.textContent.split(' ');
        title.innerHTML = '';
        
        words.forEach((word, index) => {
            const span = document.createElement('span');
            span.className = 'title-word';
            span.textContent = word;
            span.style.animationDelay = `${index * 0.1}s`;
            
            // Add highlight to certain words
            if (word.toLowerCase().includes('ultra') || word.toLowerCase().includes('modern') || word.toLowerCase().includes('experience')) {
                span.classList.add('highlight-word');
            }
            
            title.appendChild(span);
            if (index < words.length - 1) {
                title.appendChild(document.createTextNode(' '));
            }
        });
    });
}

// ===== UTILITY FUNCTIONS =====
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    }
}

// ===== PERFORMANCE OPTIMIZATIONS =====
// Use throttled scroll events for better performance
window.addEventListener('scroll', throttle(() => {
    // Update scroll-based animations
    updateScrollAnimations();
}, 16));

function updateScrollAnimations() {
    const scrolled = window.pageYOffset;
    const windowHeight = window.innerHeight;
    
    // Update parallax elements
    const parallaxElements = document.querySelectorAll('.parallax-layer');
    parallaxElements.forEach((element, index) => {
        const speed = (index + 1) * 0.3;
        element.style.transform = `translateY(${scrolled * speed * -0.5}px)`;
    });
    
    // Update floating shapes
    const shapes = document.querySelectorAll('.shape');
    shapes.forEach((shape, index) => {
        const speed = (index + 1) * 0.2;
        const rotation = scrolled * 0.1;
        shape.style.transform = `translateY(${scrolled * speed}px) rotate(${rotation}deg)`;
    });
}

// ===== ADDITIONAL CSS ANIMATIONS =====
// Add dynamic CSS for ripple effect
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to {
            transform: scale(2);
            opacity: 0;
        }
    }
    
    @keyframes floatRandom {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        25% { transform: translateY(-10px) rotate(1deg); }
        50% { transform: translateY(-5px) rotate(-1deg); }
        75% { transform: translateY(-15px) rotate(0.5deg); }
    }
    
    .title-word {
        animation-fill-mode: both;
    }
    
    .magnetic-hover {
        transition: transform 0.3s ease;
    }
`;
document.head.appendChild(style);

// ===== INITIALIZATION COMPLETE =====
console.log('🚀 Ultra Modern Sobre Nosotros JavaScript Initialized Successfully!');