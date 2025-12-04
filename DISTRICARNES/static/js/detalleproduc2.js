// Simulación de producto (en un caso real, esto vendría de una API o base de datos)
const productData = {
    id: 1,
    name: "Aceite de Coco Prensado en Frío",
    price: 14.00,
    image: "https://via.placeholder.com/300x300?text=Aceite+Coco",
    description: "Aceite de COCO, la presión en frío. Ecológico. 450ml. Producto crudo.",
    stock: 3,
    code: "ACEITE DE COCO PRENSADO EN FRÍO",
    category: "Alimentos > Coco",
    tags: ["ACEITE DE COCO PRENSADO EN FRÍO", "Alimentos"],
    weight: "450 ml.",
    brand: "MundoDecoris",
    origin: "Indonesia",
    composition: "100% Aceite de coco ecológico",
    usage: "Puedes utilizarlo de manera externa como aceite de masaje para nutrir tu piel, como interno para tomar en crudo (Siempre mejor), dar sabor a tus platos una vez cocinados como arroces, pastas, quinoa... en tus postres, pastas,"
};

// Actualizar datos dinámicamente (si quisieras conectarlo a un backend)
document.addEventListener('DOMContentLoaded', function() {
    // En este ejemplo, usamos los datos estáticos, pero podrías usar URLSearchParams para obtener el ID
    const urlParams = new URLSearchParams(window.location.search);
    const productId = urlParams.get('id');

    if (productId) {
        // Aquí podrías hacer una petición a una API para obtener el producto por ID
        // Por ahora, solo actualizamos la interfaz con los datos simulados
        updateProductPage(productData);
    }
});

function updateProductPage(product) {
    // Actualizar título de la página
    document.title = `${product.name} | Puro Alimento`;
    document.querySelector('.page-title h1').textContent = product.name;

    // Actualizar breadcrumb
    const breadcrumb = document.querySelector('.breadcrumb');
    breadcrumb.innerHTML = `
        <a href="index.html">Inicio</a> <span>›</span> 
        <a href="#">Alimentos</a> <span>›</span> 
        <a href="#">Coco</a> <span>›</span> 
        <span>${product.name}</span>
      `;

    // Actualizar imagen
    document.querySelector('.product-image-large img').src = product.image;

    // Actualizar precio
    document.querySelector('.product-price-detail').textContent = `${product.price.toFixed(2)} €`;

    // Actualizar descripción
    document.querySelector('.product-description').textContent = product.description;

    // Actualizar stock
    document.querySelector('.stock-info').textContent = `${product.stock} en stock`;

    // Actualizar meta info
    document.querySelector('.product-meta p:nth-child(1)').innerHTML = `<strong>Código:</strong> ${product.code}`;
    document.querySelector('.product-meta p:nth-child(2)').innerHTML = `<strong>Categoría:</strong> ${product.category}`;
    document.querySelector('.product-meta p:nth-child(3)').innerHTML = `<strong>Etiquetas:</strong> ${product.tags.join(', ')}`;

    // Actualizar descripción detallada
    const descList = document.querySelector('.description-list');
    descList.innerHTML = `
        <li><strong>Peso neto:</strong> ${product.weight}</li>
        <li><strong>Marca:</strong> ${product.brand}</li>
        <li><strong>Origen:</strong> ${product.origin}</li>
        <li><strong>Composición:</strong> ${product.composition}</li>
        <li><strong>Uso:</strong> ${product.usage}</li>
      `;
}

// Eventos para el selector de cantidad
document.querySelectorAll('.quantity-selector button').forEach(btn => {
    btn.addEventListener('click', function() {
        const input = this.parentElement.querySelector('.quantity-input');
        let value = parseInt(input.value);
        if (this.textContent === '+') {
            value++;
        } else if (this.textContent === '-' && value > 1) {
            value--;
        }
        input.value = value;
    });
});

// Evento para el botón "Añadir al carrito"
document.querySelector('.btn-add-cart').addEventListener('click', function() {
    const quantity = parseInt(document.querySelector('.quantity-input').value);
    // Intentar usar utilidades del carrito si están disponibles
    let added = false;
    if (window.CartUtils && typeof CartUtils.addItem === 'function') {
        added = CartUtils.addItem({
            id: productData.id,
            title: productData.name,
            price: productData.price,
            image: productData.image,
            qty: quantity
        });
    }

    if (added) {
        if (window.Swal) {
            Swal.fire({ icon:'success', title:'Agregado al carrito', timer:1200, showConfirmButton:false });
        } else {
            showToast(`Se han añadido ${quantity} unidades de "${productData.name}" al carrito`);
        }
    } else {
        if (window.Swal) {
            Swal.fire({
                icon:'warning',
                title:'Debes iniciar sesión para continuar',
                text:'Inicia sesión para agregar productos al carrito.',
                showCancelButton:true,
                confirmButtonText:'Iniciar sesión',
                cancelButtonText:'Cerrar'
            }).then((r)=>{ if(r.isConfirmed){ window.location.href = './login/login.html'; } });
        } else {
            showToast('Debes iniciar sesión para continuar');
        }
    }
});

function showToast(message) {
    const toast = document.createElement('div');
    toast.style.position = 'fixed';
    toast.style.top = '20px';
    toast.style.right = '20px';
    toast.style.background = '#7cb342';
    toast.style.color = 'white';
    toast.style.padding = '1rem 1.5rem';
    toast.style.borderRadius = '4px';
    toast.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
    toast.style.zIndex = '1000';
    toast.style.transform = 'translateX(120%)';
    toast.style.transition = 'transform 0.3s ease';
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.transform = 'translateX(0)';
    }, 10);

    setTimeout(() => {
        toast.style.transform = 'translateX(120%)';
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 3000);
}