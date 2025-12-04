/* Utilidades del carrito con persistencia por usuario */
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
    if(!key) return; // Bloquear guardado si no hay sesión
    localStorage.setItem(key, JSON.stringify(items));
    window.dispatchEvent(new CustomEvent('cart:updated',{detail:{items}}));
  }
  function safeParsePrice(str){
    if(typeof str !== 'string') return 0;
    const num = Number(str.replace(/[^0-9.,-]/g,'').replace(',','.'));
    return isNaN(num) ? 0 : num;
  }
  function addItem({ id, title, price, image, qty=1 }){
    const key = getCartKey();
    if(!key){
      // Sin sesión: no agregamos y devolvemos false
      return false;
    }
    const items = getCart();
    const uniq = id || `${title}-${price}`;
    const found = items.find(i=> (i.id||`${i.title}-${i.price}`) === uniq);
    if(found){ found.qty = (found.qty||1) + qty; }
    else{ items.push({ id, title: title||'Producto', price: Number(price)||0, image: image||'', qty }); }
    saveCart(items);
    return true;
  }

  function bindButtons(){
    // Botones genéricos en promos/productos
    document.querySelectorAll('.add-to-cart-btn, .btn.btn-add, .boton-item').forEach((btn)=>{
      btn.addEventListener('click', ()=>{
        const card = btn.closest('.product-card') || btn.closest('.item') || document.body;
        const title = btn.dataset.title || card.querySelector('.product-name, .titulo-item, .card-title')?.textContent?.trim() || 'Producto';
        const priceText = btn.dataset.price || card.querySelector('.product-price, .precio-item')?.textContent;
        const price = priceText ? safeParsePrice(priceText) : Number(btn.dataset.price||0);
        const image = btn.dataset.image || card.querySelector('img.product-image, img.img-item, img')?.src || '';
        const qty = Number(btn.dataset.qty||1);
        const ok = addItem({ title, price, image, qty });
        if(window.Swal){
          if(ok){
            Swal.fire({ icon:'success', title:'Agregado al carrito', timer:1200, showConfirmButton:false });
          } else {
            Swal.fire({
              icon:'warning',
              title:'Debes iniciar sesión para continuar',
              text:'Inicia sesión para agregar productos al carrito.',
              showCancelButton:true,
              confirmButtonText:'Iniciar sesión',
              cancelButtonText:'Cerrar'
            }).then((r)=>{ if(r.isConfirmed){ window.location.href = './login/login.html'; } });
          }
        }
      });
    });

    // Botón de detalle específico
    const detailBtn = document.querySelector('.btn-add-cart, .add-to-cart');
    if(detailBtn){
      detailBtn.addEventListener('click', ()=>{
        const root = document.querySelector('.product-detail') || document.body;
        const title = detailBtn.dataset.title || document.querySelector('.product-meta p strong, .product-title')?.textContent || 'Producto';
        const priceText = detailBtn.dataset.price || root.querySelector('.product-price-detail')?.textContent;
        const price = priceText ? safeParsePrice(priceText) : Number(detailBtn.dataset.price||0);
        const image = detailBtn.dataset.image || document.querySelector('.product-image-large img, .product-image')?.src || '';
        const qtyInput = document.querySelector('.quantity-input');
        const qty = qtyInput ? Number(qtyInput.value||1) : Number(detailBtn.dataset.qty||1);
        const ok = addItem({ title, price, image, qty });
        if(window.Swal){
          if(ok){
            Swal.fire({ icon:'success', title:'Agregado al carrito', timer:1200, showConfirmButton:false });
          } else {
            Swal.fire({
              icon:'warning',
              title:'Debes iniciar sesión para continuar',
              text:'Inicia sesión para agregar productos al carrito.',
              showCancelButton:true,
              confirmButtonText:'Iniciar sesión',
              cancelButtonText:'Cerrar'
            }).then((r)=>{ if(r.isConfirmed){ window.location.href = '../login/login.html'; } });
          }
        }
      });
    }
  }

  window.CartUtils = { getCart, saveCart, addItem, safeParsePrice };
  window.addEventListener('DOMContentLoaded', bindButtons);
  // Al cerrar sesión, notificar limpieza visual (persistencia queda atada al usuario)
  window.addEventListener('auth:loggedOut', ()=>{
    window.dispatchEvent(new CustomEvent('cart:updated',{detail:{items:[]}}));
  });
})();