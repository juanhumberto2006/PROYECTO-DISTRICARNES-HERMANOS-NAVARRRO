// Gestión de favoritos e historial de compras con localStorage
(function(){
  const HIST_KEY = 'purchaseHistory';

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

  function getFavKey(){
    const userKey = getUserKey();
    // Si no hay sesión de usuario, no usamos almacenamiento de invitado
    // para cumplir el requisito: al cerrar sesión, todo vuelve a cero.
    return userKey ? ('favorites:' + userKey) : null;
  }

  function read(key){
    try { return JSON.parse(localStorage.getItem(key)) || []; } catch(e){ return []; }
  }
  function write(key, data){
    localStorage.setItem(key, JSON.stringify(data));
  }

  const FavoritesStore = {
    add(item){
      const key = getFavKey();
      if(!key) return; // Bloquear acciones si no hay sesión
      const list = read(key);
      const now = new Date().toISOString();
      const idx = list.findIndex(i => i && String(i.id) === String(item.id));
      if (idx === -1) list.push({ ...item, addedAt: now });
      else list[idx] = { ...list[idx], ...item, addedAt: now };
      write(key, list);
      window.dispatchEvent(new Event('favorites:updated'));
    },
    remove(id){
      const key = getFavKey();
      if(!key) return; // Bloquear acciones si no hay sesión
      const list = read(key).filter(i => i && String(i.id) !== String(id));
      write(key, list);
      window.dispatchEvent(new Event('favorites:updated'));
    },
    all(){
      const key = getFavKey();
      return key ? read(key) : [];
    }
  };

  const PurchaseHistoryStore = {
    record(order){
      const list = read(HIST_KEY);
      const now = new Date().toISOString();
      list.push({ ...order, date: now });
      write(HIST_KEY, list);
      window.dispatchEvent(new Event('history:updated'));
    },
    all(){ return read(HIST_KEY); }
  };

  // Exponer funciones globales para integraciones
  window.addToFavorites = function(item){ FavoritesStore.add(item); };
  window.recordPurchase = function(order){ PurchaseHistoryStore.record(order); };
  window.FavoritesStore = FavoritesStore;
  window.PurchaseHistoryStore = PurchaseHistoryStore;
})();