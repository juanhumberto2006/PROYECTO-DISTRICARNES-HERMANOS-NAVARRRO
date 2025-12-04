// Guardián de conexión en tiempo real
// - Muestra alerta persistente si no hay Internet (SweetAlert2 o overlay nativo)
// - Bloquea clics y envíos de formularios mientras esté offline
// - Detecta cambios sin recargar la página usando eventos y heartbeat (ping)
(function(){
  const SWAL_CDN = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
  const PING_URL = 'https://www.gstatic.com/generate_204';
  const HEARTBEAT_MS = 5000; // Intervalo para verificar conectividad
  const TIMEOUT_MS = 3000;   // Tiempo máximo por verificación
  const OVERLAY_ID = 'offline-guard-overlay';
  const OVERLAY_STYLE_ID = 'offline-guard-style';

  let activated = false;
  let isOffline = false;
  let heartbeat = null;

  function ensureSwal(cb){
    if (window.Swal) { cb && cb(); return; }
    const s = document.createElement('script');
    s.src = SWAL_CDN;
    s.async = true;
    if (cb) s.onload = cb;
    document.head.appendChild(s);
  }

  function ensureOverlay(){
    if (!document.getElementById(OVERLAY_STYLE_ID)){
      const style = document.createElement('style');
      style.id = OVERLAY_STYLE_ID;
      style.textContent = `
        #${OVERLAY_ID} { position: fixed; inset: 0; background: rgba(0,0,0,.85); color: #fff;
          display: none; align-items: center; justify-content: center; z-index: 999999; }
        #${OVERLAY_ID}.visible { display: flex; }
        #${OVERLAY_ID} .box { text-align: center; font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Arial;
          font-weight: 600; letter-spacing: .2px; }
        #${OVERLAY_ID} .sub { margin-top: 8px; font-weight: 500; opacity: .9; }
      `;
      document.head.appendChild(style);
    }
    if (!document.getElementById(OVERLAY_ID)){
      const div = document.createElement('div');
      div.id = OVERLAY_ID;
      div.innerHTML = `<div class="box">Sin conexión a Internet<div class="sub">Verifica tu red y reconecta.</div></div>`;
      document.body.appendChild(div);
    }
  }

  function showOfflineUI(){
    if (window.Swal){
      Swal.fire({
        title: 'Sin conexión a Internet',
        html: '<p>Verifica tu red y vuelve a intentarlo.</p>',
        icon: 'warning',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        backdrop: true,
        didOpen: () => { Swal.showLoading(); }
      });
      return;
    }
    ensureOverlay();
    const ov = document.getElementById(OVERLAY_ID);
    ov && ov.classList.add('visible');
  }

  function hideOfflineUI(){
    if (window.Swal){ try { Swal.close(); } catch(e){} return; }
    const ov = document.getElementById(OVERLAY_ID);
    ov && ov.classList.remove('visible');
  }

  function showReconnectedUI(){
    // Aviso en la esquina superior derecha (top-end) como toast
    // Inyecta estilos mínimos para asegurar apariencia y z-index
    const STYLE_ID = 'reconnected-toast-styles';
    if (!document.getElementById(STYLE_ID)){
      const style = document.createElement('style');
      style.id = STYLE_ID;
      style.textContent = `
        .swal2-container { z-index: 2147483647 !important; }
        .swal2-popup.reconnected-toast {
          border-radius: 10px !important;
          padding: 10px 12px !important;
          box-shadow: 0 4px 14px rgba(0,0,0,0.15) !important;
          width: auto !important;
          min-width: 280px;
        }
        .swal2-title.reconnected-title {
          font-size: 14px !important;
          font-weight: 600 !important;
          margin: 0 !important;
          color: #1f2937 !important;
        }
      `;
      document.head.appendChild(style);
    }

    const MESSAGE = 'Conexión restablecida';

    if (window.Swal && typeof window.Swal.fire === 'function'){
      try {
        Swal.fire({
          toast: true,
          position: 'top-end',
          icon: 'info',
          title: MESSAGE,
          background: '#d9d9d9',
          color: '#1f2937',
          showConfirmButton: false,
          timer: 2000,
          timerProgressBar: true,
          backdrop: false,
          customClass: { popup: 'reconnected-toast', title: 'reconnected-title' }
        });
      } catch (e) {}
      return;
    }

    // Fallback nativo si SweetAlert2 no está disponible
    const existing = document.getElementById('reconnected-toast-fallback');
    if (existing) existing.remove();
    const toast = document.createElement('div');
    toast.id = 'reconnected-toast-fallback';
    Object.assign(toast.style, {
      position: 'fixed',
      top: '12px',
      right: '12px',
      background: '#d9d9d9',
      color: '#1f2937',
      padding: '10px 14px',
      borderRadius: '10px',
      boxShadow: '0 4px 14px rgba(0,0,0,0.15)',
      zIndex: '2147483647',
      display: 'flex',
      alignItems: 'center',
      gap: '8px',
      fontWeight: '600'
    });
    const icon = document.createElement('span');
    icon.textContent = 'ℹ️';
    const text = document.createElement('span');
    text.textContent = MESSAGE;
    toast.append(icon, text);
    document.body.appendChild(toast);
    setTimeout(()=>{ toast.remove(); }, 2000);
  }

  async function checkConnectivity(){
    try {
      const ctrl = new AbortController();
      const tid = setTimeout(() => ctrl.abort(), TIMEOUT_MS);
      // no-cors: si la red está caída, fetch rechazará; si hay conexión, resolverá
      await fetch(PING_URL, { method: 'GET', cache: 'no-store', mode: 'no-cors', signal: ctrl.signal });
      clearTimeout(tid);
      const wasOffline = isOffline;
      if (wasOffline){
        isOffline = false;
        hideOfflineUI();
        showReconnectedUI();
      } else {
        isOffline = false;
      }
      return true;
    } catch (e) {
      if (!isOffline){ isOffline = true; showOfflineUI(); }
      return false;
    }
  }

  function guardInteractions(){
    document.addEventListener('click', (e) => {
      if (!activated) return;
      if (isOffline){
        const t = e.target && (e.target.closest ? e.target.closest('a, button, [role="button"], .btn') : null);
        if (t){ e.preventDefault(); e.stopPropagation(); showOfflineUI(); }
      }
    }, { capture: true });

    document.addEventListener('submit', (e) => {
      if (isOffline){ e.preventDefault(); e.stopPropagation(); showOfflineUI(); }
    }, { capture: true });
  }

  function startHeartbeat(){
    if (heartbeat) clearInterval(heartbeat);
    heartbeat = setInterval(checkConnectivity, HEARTBEAT_MS);
    checkConnectivity(); // verificación inmediata
  }

  function init(){
    if (activated) return;
    activated = true;
    guardInteractions();

    // Estado inicial
    if (!navigator.onLine){ isOffline = true; showOfflineUI(); }
    startHeartbeat();
    ensureSwal(); // carga perezosa

    // Cambios en tiempo real del navegador
    window.addEventListener('offline', () => { isOffline = true; showOfflineUI(); });
    window.addEventListener('online', () => { checkConnectivity(); });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) checkConnectivity(); });
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); }
  else { init(); }
})();