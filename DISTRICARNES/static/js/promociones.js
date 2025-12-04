document.addEventListener('DOMContentLoaded', () => {
  const offersGrid = document.getElementById('offersGrid');

  // --- UTILS ---
  function fmtCurrency(n) {
    try {
      return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }).format(n);
    } catch (e) {
      return `$${Number(n || 0).toFixed(0)}`;
    }
  }

  function discountLabel(type, value) {
    const t = (type || '').toLowerCase();
    if (t === 'percentage') return `${Number(value || 0)}%`;
    if (t === 'fixed') return fmtCurrency(value);
    if (t === 'bogo') return '2x1';
    return String(value || 0);
  }

  function normalizeProductRow(row) {
    return {
      id: row.id_producto || row.id,
      name: row.nombre || 'Producto',
      price: Number(row.precio_venta || row.precio || 0),
      image: (row.imagen || '').trim() ? row.imagen : (row.image_url || ''),
    };
  }

  function normalizeOfferImage(raw) {
    let s = (raw || '').trim();
    if (!s) return '';
    s = s.replace(/\\/g, '/');
    if (/^https?:\/\//i.test(s)) return s;
    const idx = s.indexOf('static/images');
    if (idx !== -1) return '/' + s.slice(idx);
    return s.startsWith('/') ? s : '/' + s;
  }

  function computeDiscountedPrice(price, type, value) {
    const p = Number(price || 0);
    const v = Number(value || 0);
    const t = (type || '').toLowerCase();
    if (t === 'percentage') return Math.max(0, p * (1 - v / 100));
    if (t === 'fixed') return Math.max(0, p - v);
    return p;
  }

  function ensureArray(x) {
    if (Array.isArray(x)) return x;
    if (typeof x === 'string') {
      try {
        const j = JSON.parse(x);
        if (Array.isArray(j)) return j;
      } catch (e) {}
      return x.split(',').map(s => s.trim()).filter(Boolean);
    }
    return [];
  }

  // --- SKELETON LOADER ---
  function renderSkeletons() {
    if (!offersGrid) return;
    const skeletonHtml = Array(6).fill('').map(() => `
      <div class="offer-card-skeleton">
        <div class="skeleton-image"></div>
        <div class="skeleton-info">
          <div class="skeleton-line" style="width: 80%;"></div>
          <div class="skeleton-line" style="width: 60%;"></div>
          <div class="skeleton-line" style="width: 40%; height: 24px; margin-top: 10px;"></div>
        </div>
      </div>
    `).join('');
    offersGrid.innerHTML = skeletonHtml;
  }

  // --- API CALLS ---
  let ALL_PRODUCTS_CACHE = [];
  async function fetchAllProducts() {
    if (ALL_PRODUCTS_CACHE.length) return;
    try {
      const res = await fetch('/backend/php/get_products.php');
      const data = await res.json();
      ALL_PRODUCTS_CACHE = (data?.products?.map(normalizeProductRow) || []);
    } catch (e) {
      console.error('Error loading all products', e);
    }
  }

  async function fetchOffers() {
    try {
      const res = await fetch('/backend/php/get_offers.php?only_active=1');
      const data = await res.json();
      if (data?.ok) {
        renderOffersWithProducts(data.offers || []);
      } else {
        showEmptyState();
      }
    } catch (err) {
      console.error('Error loading offers', err);
      showErrorState();
    }
  }

  // --- RENDERING ---
  function showEmptyState() {
    if (!offersGrid) return;
    offersGrid.innerHTML = '<p class="empty-state">No hay ofertas disponibles en este momento. ¡Vuelve pronto!</p>';
  }
  
  function showErrorState() {
      if (!offersGrid) return;
      offersGrid.innerHTML = '<p class="empty-state error-state">No se pudieron cargar las ofertas. Por favor, intenta de nuevo más tarde.</p>';
  }

  async function renderOffersWithProducts(offers) {
    if (!offersGrid) return;
    if (!offers.length) {
      showEmptyState();
      return;
    }

    const productIds = new Set();
    offers.forEach(o => {
      const pids = ensureArray(o.products ?? o.productos_json);
      pids.forEach(id => {
        if (String(id).trim()) productIds.add(String(id));
      });
    });

    let products = [];
    try {
      if (productIds.size) {
        const res = await fetch(`/backend/php/get_products.php?ids=${encodeURIComponent(Array.from(productIds).join(','))}`);
        const data = await res.json();
        products = data?.products || [];
      }
    } catch (e) {
      console.error('Error loading products for offers', e);
    }

    const productIndex = {};
    products.forEach(r => {
      const p = normalizeProductRow(r);
      if (p.id != null) productIndex[String(p.id)] = p;
    });

    const cardsHtml = offers.flatMap(offer => {
      const productIdsForOffer = ensureArray(offer.products ?? offer.productos_json);
      const discountText = discountLabel(offer.type, offer.discount_value);
      const offerImage = normalizeOfferImage(offer.image);

      return productIdsForOffer.map(pid => {
        const product = productIndex[String(pid)];
        if (!product) return '';

        const finalPrice = computeDiscountedPrice(product.price, offer.type, offer.discount_value);
        const imageUrl = offerImage || product.image || '/static/images/image.png';
        const countdownHtml = offer.end_date ? `<div class="offer-countdown" data-end-date="${offer.end_date}"></div>` : '';

        return `
          <article class="offer-card">
            <div class="offer-image">
              <img src="${imageUrl}" alt="${product.name}" class="offer-image-tag">
              <div class="offer-badge">${offer.type === 'bogo' ? '2x1' : `-${discountText}`}</div>
              ${countdownHtml}
            </div>
            <div class="offer-info">
              <h3 class="offer-name">${product.name}</h3>
              <p class="offer-desc">${offer.title || ''}</p>
              <div class="offer-prices">
                <span class="offer-price-old">${fmtCurrency(product.price)}</span>
                <span class="offer-price-new">${fmtCurrency(finalPrice)}</span>
              </div>
              <div class="offer-actions">
                <button class="offer-btn offer-btn-add" data-id="${product.id}" data-title="${product.name}" data-price="${finalPrice}" data-image="${imageUrl}">Añadir al carrito</button>
                <button class="offer-btn offer-btn-info" data-id="${product.id}">+ Info</button>
              </div>
            </div>
          </article>
        `;
      }).join('');
    }).join('');

    offersGrid.innerHTML = cardsHtml || '<p class="empty-state">No hay productos en oferta en este momento.</p>';
    updateCountdownTimers(); // Initial call to set timers
  }

  // --- COUNTDOWN TIMER ---
  function updateCountdownTimers() {
    const countdownElements = document.querySelectorAll('.offer-countdown');
    countdownElements.forEach(el => {
      const endDate = new Date(el.dataset.endDate).getTime();
      const now = new Date().getTime();
      const distance = endDate - now;

      if (distance < 0) {
        el.innerHTML = '<span>Expirado</span>';
        el.closest('.offer-card').classList.add('expired');
        return;
      }

      const days = Math.floor(distance / (1000 * 60 * 60 * 24));
      const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
      const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
      const seconds = Math.floor((distance % (1000 * 60)) / 1000);

      el.innerHTML = `
        <span>${days}d</span>
        <span>${hours}h</span>
        <span>${minutes}m</span>
        <span>${seconds}s</span>
      `;
    });
  }

  // --- EVENT HANDLERS ---
  function handleAddToCart(btn) {
    const id = btn.dataset.id;
    const title = btn.dataset.title;
    const price = Number(btn.dataset.price);
    const image = btn.dataset.image;

    try {
      const ok = window.CartUtils?.addItem({ id, title, price, image, qty: 1 });
      if (ok) {
        Swal.fire({ icon: 'success', title: 'Agregado al carrito', timer: 1200, showConfirmButton: false });
        btn.textContent = 'Agregado';
        btn.disabled = true;
        setTimeout(() => { btn.textContent = 'Añadir al carrito'; btn.disabled = false; }, 1500);
      } else {
         Swal.fire({ 
             icon: 'warning', 
             title: 'Debes iniciar sesión',
             text: 'Para agregar productos al carrito, necesitas iniciar sesión.',
             showCancelButton: true, 
             confirmButtonText: 'Iniciar sesión' 
            }).then(r => { 
                if (r.isConfirmed) window.location.href = '/login/login.html';
            });
      }
    } catch (e) {
      console.error('Failed to add to cart', e);
      Swal.fire({ icon: 'error', title: 'Oops...', text: 'No se pudo agregar el producto al carrito.' });
    }
  }

  function handleShowInfo(btn) {
      const productId = btn.dataset.id;
      // Redirect to product detail page
      window.location.href = `/productos.php?id=${productId}`;
  }

  offersGrid.addEventListener('click', (ev) => {
    const addBtn = ev.target.closest('.offer-btn-add');
    if (addBtn) {
      handleAddToCart(addBtn);
      return;
    }
    const infoBtn = ev.target.closest('.offer-btn-info');
    if (infoBtn) {
      handleShowInfo(infoBtn);
      return;
    }
  });

  // --- INITIALIZATION ---
  function init() {
    renderSkeletons();
    fetchAllProducts(); // Pre-cache all products
    fetchOffers();

    // Set up polling
    setInterval(fetchOffers, 30000); // Refresh every 30 seconds
    setInterval(updateCountdownTimers, 1000); // Update timers every second
  }

  init();
});
