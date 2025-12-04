document.addEventListener('DOMContentLoaded', () => {
  // Toggle user dropdown safely
  const menuButton = document.querySelector('.menu-button');
  const userDropdown = document.getElementById('userDropdown');
  if (menuButton && userDropdown) {
    menuButton.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = userDropdown.style.display === 'block';
      userDropdown.style.display = isOpen ? 'none' : 'block';
      menuButton.setAttribute('aria-expanded', (!isOpen).toString());
    });

    document.addEventListener('click', (e) => {
      if (userDropdown.style.display === 'block') {
        const within = userDropdown.contains(e.target) || menuButton.contains(e.target);
        if (!within) {
          userDropdown.style.display = 'none';
          menuButton.setAttribute('aria-expanded', 'false');
        }
      }
    });
  }

  // Basic search redirect safety if using header form
  const searchForm = document.querySelector('.ml-search form');
  const searchInput = document.querySelector('.ml-search input[type="search"]');
  if (searchForm && searchInput) {
    searchForm.addEventListener('submit', (e) => {
      const q = (searchInput.value || '').trim();
      // Let native submit happen; optionally can route to productos.html?q=...
      if (!q.length) {
        // Prevent empty submissions from reloading
        e.preventDefault();
      }
    });
  }

  // ====== Lógica de autenticación y visibilidad global (carrito / botones login) ======
  const AuthSystem = {
    getSession(){
      const userData = localStorage.getItem('userData');
      const sessionData = sessionStorage.getItem('currentSession');
      let raw = null;
      try { raw = userData ? JSON.parse(userData) : (sessionData ? JSON.parse(sessionData) : null); } catch(e){ raw = null; }
      return raw && raw.user ? raw.user : raw;
    },
    isLoggedIn(user){
      if(!user) return false;
      // campos posibles: isLoggedIn, estado, bloqueado
      const blocked = String((user.estado||'').toLowerCase()) === 'bloqueado' || Boolean(user.bloqueado);
      return (Boolean(user.isLoggedIn) || 'correo_electronico' in user || 'email' in user) && !blocked;
    },
    isBlocked(user){
      if(!user) return false;
      return String((user.estado||'').toLowerCase()) === 'bloqueado' || Boolean(user.bloqueado);
    },
    checkUserSession(){
      const quickLinks = document.getElementById('quickLinks'); // contiene carrito y enlaces rápidos
      const authButtons = document.getElementById('authButtons');
      const userLoggedButtons = document.getElementById('userLoggedButtons');

      const user = this.getSession();
      const logged = this.isLoggedIn(user);
      const blocked = this.isBlocked(user);

      // Carrito: siempre visible; si se desea ocultar cuando bloqueado, descomentar:
      // if (quickLinks) quickLinks.style.display = blocked ? 'none' : 'flex';
      if (quickLinks) quickLinks.style.display = 'flex';

      if (logged) {
        if (authButtons) authButtons.style.display = 'none';
        if (userLoggedButtons) userLoggedButtons.style.display = 'block';
      } else {
        if (authButtons) authButtons.style.display = 'block';
        if (userLoggedButtons) userLoggedButtons.style.display = 'none';
      }
    }
  };

  // Exponer para que otras páginas puedan invocarlo
  window.AuthSystem = AuthSystem;
  // Ejecutar al cargar
  try { AuthSystem.checkUserSession(); } catch(e) { /* no-op */ }
});