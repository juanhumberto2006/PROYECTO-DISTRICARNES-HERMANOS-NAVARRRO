// auth_utils.js
// Centralized functions for authentication state management and UI updates.

(function() {
  const LOGOUT_FLAG_KEY = 'logoutFlag'; // Defined in session_guard.js, keep consistent

  // Function to check user session and update header UI
  function checkUserSession() {
    const userData = localStorage.getItem('userData');
    const sessionData = sessionStorage.getItem('currentSession');

    const authButtons = document.getElementById('authButtons');
    const userLoggedButtons = document.getElementById('userLoggedButtons');
    const heroButtons = document.getElementById('userLoggedButtonsHero'); // Assuming this exists on some pages

    if (userData || sessionData) {
      const raw = JSON.parse(userData || sessionData);
      if (raw && raw.isLoggedIn) {
        const currentUser = raw.user ? raw.user : raw;

        // Show logged-in elements, hide auth buttons
        if (authButtons) authButtons.style.display = 'none';
        if (userLoggedButtons) userLoggedButtons.style.display = 'block';
        if (heroButtons) heroButtons.style.display = 'block'; // Show if exists

        // Populate user data in header (if elements exist)
        const displayName = currentUser.nombres_completos || currentUser.nombre || currentUser.correo_electronico || currentUser.email || 'Usuario';
        const displayEmail = currentUser.correo_electronico || currentUser.email || '';
        const displayRole = currentUser.rol || '';
        const initials = (displayName.charAt(0) || 'U').toUpperCase();

        const userAvatar = document.getElementById('userAvatar');
        const userName = document.getElementById('userName');
        const userAvatarLarge = document.getElementById('userAvatarLarge');
        const userFullName = document.getElementById('userFullName');
        const userEmail = document.getElementById('userEmail');
        const userRole = document.getElementById('userRole');

        if (userAvatar) userAvatar.textContent = initials;
        if (userName) userName.textContent = displayName;
        if (userAvatarLarge) userAvatarLarge.textContent = initials;
        if (userFullName) userFullName.textContent = displayName;
        if (userEmail) userEmail.textContent = displayEmail;
        if (userRole) userRole.textContent = displayRole ? displayRole.charAt(0).toUpperCase() + displayRole.slice(1) : '';

        // Welcome message (if element exists)
        const welcomeElement = document.getElementById('userWelcome');
        if (welcomeElement) {
          welcomeElement.textContent = `¡Bienvenido, ${displayName}!`;
        }
        return; // User is logged in, no need to proceed further
      }
    }

    // If not logged in, ensure auth buttons are visible and user elements are hidden
    if (authButtons) authButtons.style.display = 'flex'; // Use flex as it was originally
    if (userLoggedButtons) userLoggedButtons.style.display = 'none';
    if (heroButtons) heroButtons.style.display = 'none';
  }

  // Function to handle logout
  function logout() {
    // Clear session data
    localStorage.removeItem('userData');
    sessionStorage.removeItem('currentSession');
    sessionStorage.setItem(LOGOUT_FLAG_KEY, '1'); // Mark as logged out for session_guard

    // Dispatch a custom event to notify other parts of the application
    window.dispatchEvent(new CustomEvent('auth:loggedOut'));

    // Optional: Redirect to login page after logout
    // window.location.href = './login/login.html';
  }

  // Listen for custom events to update UI
  window.addEventListener('auth:loggedOut', () => {
    checkUserSession(); // Update UI after logout
    // Optionally redirect to login page after a short delay
    // setTimeout(() => { window.location.href = './login/login.html'; }, 500);
  });

  window.addEventListener('auth:loggedIn', () => {
    sessionStorage.removeItem(LOGOUT_FLAG_KEY); // Clear logout flag on successful login
    checkUserSession(); // Update UI after login
  });

  // Expose functions globally if needed (e.g., for onclick attributes)
  window.logout = logout;
  window.checkUserSession = checkUserSession;

  // Initial check when the DOM is ready
  document.addEventListener('DOMContentLoaded', checkUserSession);
})();