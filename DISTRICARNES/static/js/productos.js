// Carga de productos desde la BD (tabla producto)
let products = [];

function normalizeRow(row){
  return {
    id: row.id_producto || row.id || row.ID || row.codigo,
    name: row.nombre || row.name || 'Producto',
    price: Number(row.precio_venta || row.precio || row.precio_base || row.price || 0),
    image: row.imagen || row.image || '',
    categoria: (row.categoria || row.category || '').toLowerCase(),
    subcategoria: (row.subcategoria || row.subcategory || '').toLowerCase(),
    stock: Number(row.stock || row.existencias || 1),
    descripcion: row.descripcion || row.description || '',
    codigo: row.codigo || row.code || '',
    marca: row.marca || row.brand || '',
    origen: row.origen || row.origin || '',
    peso: row.peso || row.peso_neto || row.weight || '',
    unidad: row.unidad || row.unit || '',
    composicion: row.composicion || row.ingredientes || row.composition || '',
    presentacion: row.presentacion || row.presentation || '',
    etiquetas: row.etiquetas || row.tags || ''
  };
}

function getParam(name){
  const url = new URL(window.location.href);
  return url.searchParams.get(name);
}

async function fetchProducts(){
  try{
    const params = new URLSearchParams();
    const q = getParam('q');
    const categoria = getParam('categoria');
    const subcategoria = getParam('subcategoria');
    if(q) params.set('q', q);
    if(categoria) params.set('categoria', categoria);
    if(subcategoria) params.set('subcategoria', subcategoria);

    const res = await fetch('backend/php/get_products.php' + (params.toString()?`?${params.toString()}`:''));
    const data = await res.json();
    products = (data && data.products ? data.products : []).map(normalizeRow);
  }catch(e){
    console.error('Error al cargar productos', e);
    products = [];
  }
}

// FUNCIONES PRINCIPALES
let currentFilters = {
    search: '',
    minPrice: 2,
    maxPrice: 50,
    categories: [],
    tags: []
};

function renderProducts() {
    const container = document.getElementById('products-container');
    // Si el contenedor fue renderizado por PHP, no duplicar tarjetas
    if (container && container.dataset && container.dataset.serverRender === '1') {
        // Solo vincular eventos de + Info para abrir modal
        document.querySelectorAll('.product-card .btn-info').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if(e){ e.preventDefault(); e.stopPropagation(); }
                const card = this.closest('.product-card');
                const productId = card.querySelector('.btn-add').dataset.id;
                const imageSrc = card.querySelector('.product-image img')?.src
                  || card.querySelector('.favorite-btn')?.dataset?.image
                  || '';
                openProductInfoModal(productId, imageSrc);
            });
        });
        return;
    }
    container.innerHTML = '';

    const filteredProducts = products.filter(product => {
        // Filtrar por búsqueda
        if (currentFilters.search && !product.name.toLowerCase().includes(currentFilters.search.toLowerCase())) {
            return false;
        }

        // Filtrar por precio
        if (product.price < currentFilters.minPrice || product.price > currentFilters.maxPrice) {
            return false;
        }

        // Filtrar por categorías (comparación por texto de categoria)
        if (currentFilters.categories.length > 0) {
            const catText = (product.categoria || '').toLowerCase();
            const hasCategory = currentFilters.categories.some(cat => catText.includes(cat.toLowerCase()));
            if (!hasCategory) return false;
        }

        // Filtrar por etiquetas (omitido: la BD puede no tener tags)

        return true;
    });

    // Mostrar conteo
    document.querySelector('.products-count').textContent = `Mostrando 1-${filteredProducts.length} de ${filteredProducts.length} resultados`;

    // Renderizar productos
    filteredProducts.forEach(product => {
        const card = document.createElement('div');
        card.className = 'product-card';
        card.innerHTML = `
          <div class="product-image">
            <img src="${product.image}" alt="${product.name}">
          </div>
          <div class="product-info">
            <h3 class="product-name">${product.name}</h3>
            <div class="product-price">$${product.price.toFixed(2)}</div>
            <div class="product-actions">
              <button class="btn btn-add" data-id="${product.id}">Añadir al carrito</button>
              <button class="btn btn-info">+ Info</button>
            </div>
          </div>
        `;
        container.appendChild(card);
    });

    // Añadir eventos a los botones + Info para abrir modal
    document.querySelectorAll('.btn-info').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if(e){ e.preventDefault(); e.stopPropagation(); }
            const card = this.closest('.product-card');
            const productId = card.querySelector('.btn-add').dataset.id;
            const imageSrc = card.querySelector('.product-image img')?.src || '';
            openProductInfoModal(productId, imageSrc);
        });
    });
}

function showToast(message) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.classList.add('show');

    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// EVENT LISTENERS
// El input de búsqueda puede ser el del header o el del sidebar
const searchInputEl = document.getElementById('search-input')
    || document.getElementById('site-search')
    || document.getElementById('site-search-sidebar');
if (searchInputEl) {
    searchInputEl.addEventListener('input', function() {
        currentFilters.search = this.value || '';
        renderProducts();
    });
}

const searchBtnEl = document.getElementById('search-btn');
if (searchBtnEl && searchInputEl) {
    searchBtnEl.addEventListener('click', function() {
        currentFilters.search = searchInputEl.value || '';
        renderProducts();
    });
}

// Sliders de precio
const minSlider = document.getElementById('price-min');
const maxSlider = document.getElementById('price-max');
const minDisplay = document.getElementById('min-display');
const maxDisplay = document.getElementById('max-display');
const priceValue = document.getElementById('price-value');

function updatePriceRange() {
    let min = parseInt(minSlider.value);
    let max = parseInt(maxSlider.value);

    if (min >= max) {
        minSlider.value = max - 1;
        min = max - 1;
    }

    if (max <= min) {
        maxSlider.value = min + 1;
        max = min + 1;
    }

    minDisplay.textContent = `${min} €`;
    maxDisplay.textContent = `${max} €`;
    priceValue.textContent = `${min} € — ${max} €`;

    currentFilters.minPrice = min;
    currentFilters.maxPrice = max;
    renderProducts();
}

if (minSlider && maxSlider) {
    minSlider.addEventListener('input', updatePriceRange);
    maxSlider.addEventListener('input', updatePriceRange);
}

// Filtros por categoría
document.querySelectorAll('input[data-category]').forEach(input => {
    input.addEventListener('change', function() {
        const category = this.dataset.category;
        if (this.checked) {
            currentFilters.categories.push(category);
        } else {
            const index = currentFilters.categories.indexOf(category);
            if (index > -1) {
                currentFilters.categories.splice(index, 1);
            }
        }
        renderProducts();
    });
});

// Filtros por etiqueta
document.querySelectorAll('input[data-tag]').forEach(input => {
    input.addEventListener('change', function() {
        const tag = this.dataset.tag;
        if (this.checked) {
            currentFilters.tags.push(tag);
        } else {
            const index = currentFilters.tags.indexOf(tag);
            if (index > -1) {
                currentFilters.tags.splice(index, 1);
            }
        }
        renderProducts();
    });
});

// Botón de filtrar
const filterBtnEl = document.querySelector('.filter-btn');
if (filterBtnEl) {
    filterBtnEl.addEventListener('click', function() {
        renderProducts();
    });
}

// Inicializar
// ====== Secciones por categorías dinámicas desde BD ======
let categories = [];

function buildCategoriesFromProducts(){
  const set = new Map();
  products.forEach(p=>{
    const name = (p.categoria||'').trim().toLowerCase();
    if(!name) return;
    if(!set.has(name)){
      set.set(name, { name, display: name.toUpperCase() });
    }
  });
  categories = Array.from(set.values()).sort((a,b)=> a.name.localeCompare(b.name));
}

function renderCards(container, list){
  list.forEach(product=>{
    const card = document.createElement('div');
    card.className = 'product-card';
    card.innerHTML = `
      <div class="product-image">
        <img src="${product.image}" alt="${product.name}">
      </div>
      <div class="product-info">
        <h3 class="product-name">${product.name}</h3>
        <div class="product-price">$${(Number(product.price)||0).toFixed(2)}</div>
        <div class="product-actions">
          <button class="btn btn-add" data-id="${product.id}">Añadir al carrito</button>
          <button class="btn btn-info">+ Info</button>
        </div>
      </div>
    `;
    container.appendChild(card);
  });
}

function renderCategorySections(){
  const root = document.getElementById('category-sections');
  if(!root) return;
  root.innerHTML = '';

  categories.forEach(cat=>{
    const section = document.createElement('section');
    section.className = 'category-block';
    section.innerHTML = `<h2 class="category-title">${cat.display}</h2><div class="category-grid"></div>`;
    root.appendChild(section);

    const grid = section.querySelector('.category-grid');
    const items = products.filter(p=>{
      const catText = (p.categoria||'').toLowerCase();
      return catText.includes((cat.name||'').toLowerCase());
    });
    renderCards(grid, items);
  });

  // enlazar eventos de info en secciones también
  document.querySelectorAll('.category-block .btn-info').forEach(btn=>{
    btn.addEventListener('click', function(e){
      if(e){ e.preventDefault(); e.stopPropagation(); }
      const card = this.closest('.product-card');
      const productId = card.querySelector('.btn-add').dataset.id;
      const imageSrc = card.querySelector('.product-image img')?.src
        || card.querySelector('.favorite-btn')?.dataset?.image
        || '';
      openProductInfoModal(productId, imageSrc);
    });
  });
}

// Modal de información de producto usando SweetAlert2
function openProductInfoModal(productId, imageOverride){
  try{
    const product = (products || []).find(p=> String(p.id) === String(productId));
    if(!product){
      // Fallback simple: informar que no se encontró
      if(window.Swal){
        Swal.fire({ icon:'error', title:'Producto no encontrado', text:'No pudimos cargar la información completa.' });
      } else {
        alert('Producto no encontrado');
      }
      return;
    }

    const priceText = `$${(Number(product.price)||0).toFixed(2)}`;
    const categoriaText = (product.categoria||'').toUpperCase();
    const subcategoriaText = (product.subcategoria||'').toUpperCase();
    const stockText = Number(product.stock||0) > 0 ? product.stock : 'Consultar';
    const descripcionText = product.descripcion || 'Sin descripción disponible';

    // Imagen prioritaria: la de la tarjeta (override), luego BD, luego DOM
    let modalImage = imageOverride || product.image || '';
    if(!modalImage){
      const btn = document.querySelector(`.btn-add[data-id="${productId}"]`);
      const card = btn ? btn.closest('.product-card') : null;
      const cardImg = card?.querySelector('.product-image img')?.src || '';
      const favDataImg = card?.querySelector('.favorite-btn')?.dataset?.image || '';
      modalImage = cardImg || favDataImg || '';
    }
    // Normalizar y ajustar rutas
    modalImage = String(modalImage || '').replace(/\\+/g,'/');
    if(/^http:\/\/static\//i.test(modalImage)){
      modalImage = modalImage.replace(/^http:\/\/static\//i, '/static/');
    }
    if(modalImage && modalImage.startsWith('static/')){ modalImage = '/' + modalImage; }
    if(!modalImage){ modalImage = '/static/images/image.png'; }

    // Especificaciones dinámicas según disponibilidad
    const specs = [];
    specs.push(`<div style=\"color:#000\">Precio</div><div style=\"color:#000\">${priceText}</div>`);
    specs.push(`<div style=\"color:#000\">Stock</div><div style=\"color:#000\">${stockText}</div>`);
    if(categoriaText) specs.push(`<div style=\"color:#000\">Categoría</div><div style=\"color:#000\">${categoriaText}</div>`);
    if(subcategoriaText) specs.push(`<div style=\"color:#000\">Subcategoría</div><div style=\"color:#000\">${subcategoriaText}</div>`);
    if(product.codigo) specs.push(`<div style=\"color:#000\">Código</div><div style=\"color:#000\">${product.codigo}</div>`);
    if(product.marca) specs.push(`<div style=\"color:#000\">Marca</div><div style=\"color:#000\">${product.marca}</div>`);
    if(product.origen) specs.push(`<div style=\"color:#000\">Origen</div><div style=\"color:#000\">${product.origen}</div>`);
    if(product.peso) specs.push(`<div style=\"color:#000\">Peso</div><div style=\"color:#000\">${product.peso}${product.unidad?` ${product.unidad}`:''}</div>`);
    if(product.presentacion) specs.push(`<div style=\"color:#000\">Presentación</div><div style=\"color:#000\">${product.presentacion}</div>`);
    if(product.composicion) specs.push(`<div style=\"color:#000\">Composición</div><div style=\"color:#000\">${product.composicion}</div>`);
    if(product.etiquetas) specs.push(`<div style=\"color:#000\">Etiquetas</div><div style=\"color:#000\">${String(product.etiquetas)}</div>`);

    const html = `
      <div style=\"display:flex; gap:18px; align-items:flex-start; flex-wrap:wrap; text-align:left; color:#000;\">\n        <div style=\"flex:0 0 240px; max-width:240px;\">\n          ${modalImage ? `<img src=\"${modalImage}\" alt=\"${product.name}\" style=\"width:100%; height:auto; border-radius:8px; border:1px solid #ddd;\"/>` : ''}\n        </div>\n        <div style=\"flex:1; min-width:260px; color:#000;\">\n          <div style=\"margin-bottom:12px; font-size:1rem; color:#000;\">${categoriaText}${subcategoriaText?` • ${subcategoriaText}`:''}</div>\n          <div style=\"font-size:1.1rem; margin-bottom:12px; color:#000;\">${descripcionText}</div>\n          <div style=\"display:grid; grid-template-columns: 150px 1fr; gap:10px 14px; color:#000;\">\n            ${specs.join('')}\n          </div>\n        </div>\n      </div>\n    `;

    if(window.Swal){
      Swal.fire({
        title: product.name,
        html,
        width: 720,
        background: '#fff',
        color: '#000',
        showCloseButton: true,
        confirmButtonText: 'Cerrar',
        didOpen: (popup)=>{
          const imgEl = popup.querySelector('img');
          if(imgEl){
            imgEl.onerror = ()=>{
              const btn = document.querySelector(`.btn-add[data-id="${productId}"]`);
              const card = btn ? btn.closest('.product-card') : null;
              const altImg = card?.querySelector('.favorite-btn')?.dataset?.image || '/static/images/image.png';
              imgEl.src = String(altImg || '').replace(/\\/g,'/').replace(/^static\//,'/static/');
            };
          }
        },
        focusConfirm: false
      });
    }else{
      // Fallback nativo
      alert(`${product.name}\n\n${descripcionText}\nPrecio: ${priceText}\nStock: ${stockText}`);
    }
  }catch(err){
    console.error('Error al abrir modal de producto', err);
    if(window.Swal){
      Swal.fire({ icon:'error', title:'Error', text:'Ocurrió un problema al mostrar la información.' });
    } else {
      alert('Error al mostrar la información del producto');
    }
  }
}

// Inicializar con carga desde API
(async function init(){
  await fetchProducts();
  buildCategoriesFromProducts();
  renderProducts();
  renderCategorySections();
})();