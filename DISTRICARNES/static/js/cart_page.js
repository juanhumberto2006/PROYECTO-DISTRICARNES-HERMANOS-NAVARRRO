/* Página del carrito: CRUD con persistencia por usuario */
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

  function getCart(){
    const key = getCartKey();
    if(!key) return [];
    try{ return JSON.parse(localStorage.getItem(key) || '[]'); }catch(e){ return []; }
  }
  function saveCart(items){
    const key = getCartKey();
    if(!key) return; // sin sesión no persistimos
    localStorage.setItem(key, JSON.stringify(items));
    window.dispatchEvent(new CustomEvent('cart:updated',{detail:{items}}));
  }

  function formatCurrency(n){
    const v = Number(n||0);
    return v.toLocaleString('es-ES',{style:'currency',currency:'USD'});
  }

  function render(){
    const rowsEl = document.getElementById('cartRows');
    const emptyEl = document.getElementById('emptyCart');
    const items = getCart();
    rowsEl.innerHTML = '';
    if(!items.length){
      emptyEl.style.display = 'block';
    } else {
      emptyEl.style.display = 'none';
      items.forEach((item, idx)=>{
        const row = document.createElement('div');
        row.className = 'cart-row';
        row.innerHTML = `
          <img class="cart-img" src="${item.image||''}" alt="${item.title||'Producto'}"/>
          <div class="cart-title">${item.title||'Producto'}</div>
          <div class="qty">
            <button class="btn-dec" aria-label="Disminuir">-</button>
            <input class="qty-input" type="number" min="1" value="${Number(item.qty||1)}" />
            <button class="btn-inc" aria-label="Aumentar">+</button>
          </div>
          <div class="cart-price">${formatCurrency(item.price * (item.qty||1))}</div>
          <button class="remove-btn" aria-label="Eliminar">Eliminar</button>
        `;
        // Eventos
        row.querySelector('.btn-dec').addEventListener('click', ()=> updateQty(idx, (Number(item.qty||1)-1)) );
        row.querySelector('.btn-inc').addEventListener('click', ()=> updateQty(idx, (Number(item.qty||1)+1)) );
        row.querySelector('.qty-input').addEventListener('change', (e)=> updateQty(idx, Number(e.target.value||1)) );
        row.querySelector('.remove-btn').addEventListener('click', ()=> removeItem(idx) );
        rowsEl.appendChild(row);
      });
    }
    recalcTotals();
  }

  function updateQty(index, qty){
    const items = getCart();
    if(!items[index]) return;
    const newQty = Math.max(1, Number(qty||1));
    items[index].qty = newQty;
    saveCart(items);
    render();
  }

  function removeItem(index){
    const items = getCart();
    items.splice(index,1);
    saveCart(items);
    render();
  }

  function clearCart(){
    const key = getCartKey();
    if(!key) return;
    localStorage.setItem(key, JSON.stringify([]));
    window.dispatchEvent(new CustomEvent('cart:updated',{detail:{items:[]}}));
    render();
  }

  function recalcTotals(){
    const items = getCart();
    const subtotal = items.reduce((sum,i)=> sum + (Number(i.price||0) * Number(i.qty||1)), 0);
    const shipping = subtotal >= 100 ? 0 : (items.length ? 10 : 0);
    const total = subtotal + shipping;
    document.getElementById('subtotal').textContent = formatCurrency(subtotal);
    document.getElementById('shipping').textContent = formatCurrency(shipping);
    document.getElementById('total').textContent = formatCurrency(total);
  }

  function setSessionInfo(){
    const el = document.getElementById('sessionInfo');
    if(!el) return;
    // Si ya existe clave de usuario para el carrito, ocultar el mensaje
    const hasUserKey = !!getUserKey();
    if (hasUserKey) {
      el.style.display = 'none';
      return;
    }
    let user = null;
    let logged = false;
    // Preferir el sistema global de auth si está presente
    try{
      if (window.AuthSystem) {
        if (typeof window.AuthSystem.getCurrentUser === 'function') {
          user = window.AuthSystem.getCurrentUser();
        }
        if (typeof window.AuthSystem.isLoggedIn === 'function') {
          logged = window.AuthSystem.isLoggedIn();
        }
      }
    }catch(e){ /* noop */ }
    // Fallback: leer de userData / currentSession (formato { isLoggedIn, user })
    if(!user){
      try{
        const rawStr = localStorage.getItem('userData') || sessionStorage.getItem('currentSession');
        if(rawStr){
          const raw = JSON.parse(rawStr);
          user = raw && raw.user ? raw.user : raw;
          logged = Boolean(raw && (raw.isLoggedIn || raw.user || raw.email || raw.correo_electronico));
        }
      }catch(e){ /* noop */ }
    }
    if(logged && user && typeof user === 'object'){
      const name = user.nombres_completos || user.nombre || user.name || '';
      const email = user.correo_electronico || user.email || '';
      const display = name || email || 'Usuario';
      el.textContent = `Sesión iniciada: ${display}. Continúa con tu compra.`;
      el.classList && el.classList.remove('hidden');
      el.style.display = 'block';
    } else {
      el.textContent = 'No has iniciado sesión. Inicia sesión para continuar.';
      el.classList && el.classList.remove('hidden');
      el.style.display = 'block';
    }
  }

  function goCheckout(){
    const items = getCart();
    if(!items.length){
      if(window.Swal){ Swal.fire({ icon:'info', title:'Tu carrito está vacío', timer:1400, showConfirmButton:false }); }
      return;
    }
    if(window.Swal){
      Swal.fire({ title:'Cargando checkout…', timer:800, didOpen:()=>Swal.showLoading(), willClose:()=>{ window.location.href = '../checkout/direccion.html'; } });
    } else {
      window.location.href = '../checkout/direccion.html';
    }
  }

  // Pago gestionado únicamente en la página de checkout

  function init(){
    setSessionInfo();
    render();
    const btnCheckout = document.getElementById('btnCheckout');
    const btnClear = document.getElementById('btnClear');
    if(btnCheckout) btnCheckout.addEventListener('click', goCheckout);
    if(btnClear) btnClear.addEventListener('click', ()=>{
      if(window.Swal){
        Swal.fire({
          title:'¿Vaciar carrito?',
          text:'Se eliminarán todos los productos.',
          icon:'warning',
          showCancelButton:true,
          confirmButtonColor:'#e50914',
          cancelButtonColor:'#333',
          confirmButtonText:'Sí, vaciar'
        }).then((r)=>{ if(r.isConfirmed) clearCart(); });
      } else { clearCart(); }
    });

    window.addEventListener('cart:updated', ()=>{ render(); });
    window.addEventListener('auth:loggedOut', ()=>{ setSessionInfo(); render(); });
    window.addEventListener('userLogin', ()=>{ setSessionInfo(); render(); });
    window.addEventListener('storage', (e)=>{
      if (e.key === 'userData' || e.key === 'currentSession') { setSessionInfo(); }
    });
  }

  window.addEventListener('DOMContentLoaded', init);
})();