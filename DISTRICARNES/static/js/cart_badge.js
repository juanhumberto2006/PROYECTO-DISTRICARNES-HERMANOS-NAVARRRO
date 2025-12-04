// Actualiza el badge del carrito leyendo por usuario
(function(){
  function getUserKey(){
    try{
      const rawStr = localStorage.getItem('userData') || sessionStorage.getItem('currentSession');
      if(!rawStr) return null;
      const raw = JSON.parse(rawStr);
      const user = raw && raw.user ? raw.user : raw;
      const email = (user && (user.correo_electronico || user.email)) || '';
      const id = (user && (user.id_usuario || user.id)) || '';
      const key = email || String(id||'').trim();
      return key || null;
    }catch(e){ return null; }
  }

  function getCartKey(){
    const userKey = getUserKey();
    return userKey ? ('cart_items:' + userKey) : null;
  }

  function readCart(){
    const key = getCartKey();
    if(!key) return [];
    try{
      const raw = localStorage.getItem(key);
      return raw ? (JSON.parse(raw) || []) : [];
    }catch(e){ return []; }
  }

  function countItems(items){
    let total = 0;
    for (const it of items){
      if (it && typeof it === 'object'){
        if ('qty' in it) total += Number(it.qty) || 0;
        else if ('quantity' in it) total += Number(it.quantity) || 0;
        else total += 1;
      } else {
        total += 1;
      }
    }
    return total;
  }

  function updateBadge(){
    const el = document.getElementById('cartCount');
    if (!el) return;
    const items = readCart();
    el.textContent = String(countItems(items));
  }

  window.CartBadge = { update: updateBadge };
  document.addEventListener('DOMContentLoaded', updateBadge);
  window.addEventListener('cart:updated', updateBadge);
  window.addEventListener('auth:loggedOut', updateBadge);
})();