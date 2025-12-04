// Loader global para todo el sitio
// - Muestra un overlay en la carga inicial
// - Muestra overlay en navegación (clicks, submits, beforeunload)
(function () {
  const LOADER_ID = 'global-loader';
  const STYLE_ID = 'global-loader-style';
  const DURATION_MS = 1000; // Mostrar siempre 3 segundos
  let initialized = false;
  let hideTimer = null;

  function injectCSS() {
    if (document.getElementById(STYLE_ID)) return;
    const style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = `
      #${LOADER_ID} {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.85);
        backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: 14px;
        z-index: 99999;
        opacity: 0;
        visibility: hidden;
        transition: opacity 200ms ease, visibility 200ms ease;
      }
      #${LOADER_ID}.visible { opacity: 1; visibility: visible; }
      #${LOADER_ID} .spinner {
        width: 56px; height: 56px;
        border: 4px solid rgba(255, 255, 255, 0.15);
        border-top-color: #ff0000;
        border-radius: 50%;
        animation: globalSpin 0.9s linear infinite;
      }
      #${LOADER_ID} .text {
        color: #fff;
        font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, 'Helvetica Neue', Arial;
        font-weight: 600;
        letter-spacing: .4px;
      }
      @keyframes globalSpin { to { transform: rotate(360deg); } }
    `;
    document.head.appendChild(style);
  }

  function createOverlay() {
    if (document.getElementById(LOADER_ID)) return;
    const overlay = document.createElement('div');
    overlay.id = LOADER_ID;
    overlay.setAttribute('aria-hidden', 'true');
    overlay.innerHTML = `
      <div class="spinner"></div>
      <div class="text">Cargando…</div>
    `;
    document.body.appendChild(overlay);
  }

  function showLoader() {
    const el = document.getElementById(LOADER_ID);
    if (!el) return;
    el.classList.add('visible');
    if (hideTimer) clearTimeout(hideTimer);
    hideTimer = setTimeout(hideLoader, DURATION_MS);
  }

  function hideLoader() {
    const el = document.getElementById(LOADER_ID);
    if (!el) return;
    el.classList.remove('visible');
    if (hideTimer) {
      clearTimeout(hideTimer);
      hideTimer = null;
    }
  }

  function init() {
    if (initialized) return;
    initialized = true;
    injectCSS();
    createOverlay();

    // Simulación en la carga inicial (siempre 3 segundos)
    requestAnimationFrame(() => {
      showLoader();
    });

    // Mostrar antes de navegación por clicks (sin cambiar comportamiento nativo)
    // Mostrar en clicks de enlaces y botones (siempre 3s)
    document.addEventListener(
      'click',
      (e) => {
        const elTarget = e.target && (e.target.closest ? e.target.closest('a, button, [role="button"], .btn') : null);
        if (!elTarget) return;
        if (e.defaultPrevented) return;
        if (e.button !== 0) return; // solo click izquierdo
        showLoader();
      },
      { capture: true }
    );

    // Mostrar en envío de formularios
    document.addEventListener(
      'submit',
      () => {
        showLoader();
      },
      { capture: true }
    );

    // Fallback al abandonar la página
    window.addEventListener('beforeunload', () => {
      showLoader();
    });

    // También mostrar cuando se abren modales (Bootstrap)
    document.addEventListener('show.bs.modal', () => showLoader());
    document.addEventListener('shown.bs.modal', () => showLoader());

    // Exponer contro­les manuales si se necesitan en otras partes
    window.GlobalLoader = { show: showLoader, hide: hideLoader };
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();