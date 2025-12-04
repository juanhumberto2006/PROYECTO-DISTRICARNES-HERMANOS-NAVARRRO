// Admin Reports - Real-time data logic in JavaScript
// This script replaces Django-style templating by fetching live data
// from PHP endpoints and rendering metrics and charts in real time.

(function(){
  const fmtCurrency = (n) => {
    try {
      return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(n || 0);
    } catch(e) {
      return '$' + Number(n || 0).toLocaleString('es-CO');
    }
  };

  const fmtPercent = (n) => `${Math.round((n || 0) * 100) / 100}%`;

  const getRange = () => {
    const sel = document.getElementById('dateRange');
    const startInput = document.getElementById('startDate');
    const endInput = document.getElementById('endDate');

    const today = new Date();
    const end = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    let start = new Date(end);

    const toISODate = (d) => {
      const y = d.getFullYear();
      const m = String(d.getMonth() + 1).padStart(2, '0');
      const da = String(d.getDate()).padStart(2, '0');
      return `${y}-${m}-${da}`;
    };

    switch(sel?.value){
      case 'today':
        // start = end (today)
        break;
      case 'week':
        start.setDate(end.getDate() - 6);
        break;
      case 'month':
        start = new Date(end.getFullYear(), end.getMonth(), 1);
        break;
      case 'quarter':
        {
          const qStartMonth = Math.floor(end.getMonth() / 3) * 3;
          start = new Date(end.getFullYear(), qStartMonth, 1);
        }
        break;
      case 'year':
        start = new Date(end.getFullYear(), 0, 1);
        break;
      case 'custom':
        if (startInput?.value) { start = new Date(startInput.value + 'T00:00:00'); }
        if (endInput?.value) { const e = new Date(endInput.value + 'T00:00:00'); end.setTime(e.getTime()); }
        break;
      default:
        start.setDate(end.getDate() - 6);
    }

    return { from: toISODate(start), to: toISODate(end) };
  };

  async function fetchJSON(url){
    const res = await fetch(url, { credentials: 'same-origin' });
    if(!res.ok) throw new Error('HTTP ' + res.status);
    return res.json();
  }

  function setText(id, text){
    const el = document.getElementById(id);
    if(el) el.textContent = text;
  }

  // ====== Metrics from Orders ======
  function aggregateOrders(orders){
    const totals = {
      totalSales: 0,
      ordersCount: 0,
      avgOrder: 0,
      dayTotals: {},
      monthTotals: {},
      customers: {}
    };
    const dayFmt = new Intl.DateTimeFormat('es-ES', { weekday: 'short' });
    const monthFmt = new Intl.DateTimeFormat('es-ES', { month: 'short' });

    for(const o of (orders || [])){
      const total = Number(o.total || 0);
      totals.totalSales += total;
      totals.ordersCount += 1;

      const d = new Date(o.created_at.replace(' ', 'T'));
      const day = dayFmt.format(d);
      const mon = monthFmt.format(d);
      totals.dayTotals[day] = (totals.dayTotals[day] || 0) + total;
      totals.monthTotals[mon] = (totals.monthTotals[mon] || 0) + total;

      const email = (o.customer_email || '').toLowerCase();
      if(email){
        const c = totals.customers[email] || { count: 0, firstDate: d };
        c.count += 1;
        if(d < c.firstDate) c.firstDate = d;
        totals.customers[email] = c;
      }
    }

    totals.avgOrder = totals.ordersCount ? (totals.totalSales / totals.ordersCount) : 0;

    // Customer analytics
    const distinct = Object.values(totals.customers);
    const newCustomers = distinct.filter(c => c.count === 1).length;
    const returningCustomers = distinct.filter(c => c.count > 1).length;
    const retention = distinct.length ? Math.round((returningCustomers / distinct.length) * 100) : 0;

    return { totals, newCustomers, returningCustomers, retention };
  }

  function buildArrayFromMap(map, orderedKeys){
    return orderedKeys.map(k => Number(map[k] || 0));
  }

  // ====== Metrics from Products/Categories ======
  function aggregateProducts(products){
    let lowStock = 0, totalItems = 0, inventoryValue = 0;
    for(const p of (products || [])){
      const stock = Number(
        p.stock ?? p.min_stock ?? p.stock_min ?? p.stock_minimo ?? 0
      );
      const minStock = Number(p.stock_minimo ?? p.min_stock ?? p.stock_min ?? 5);
      const price = Number(p.precio ?? p.price ?? 0);
      totalItems += stock;
      inventoryValue += stock * price;
      if(stock <= minStock) lowStock += 1;
    }
    return { lowStock, totalItems, inventoryValue };
  }

  function aggregateTopProductSales(orders){
    const map = new Map(); // title -> qty sum
    for(const o of (orders || [])){
      for(const it of (o.items || [])){
        const title = String(it.title || 'Producto').trim();
        const qty = Number(it.qty || 0);
        map.set(title, (map.get(title) || 0) + qty);
      }
    }
    let max = 0;
    for(const v of map.values()) max = Math.max(max, v);
    return max;
  }

  // ====== Chart helpers ======
  function ensureChart(canvasId, type, data, options){
    const ctx = document.getElementById(canvasId)?.getContext('2d');
    if(!ctx) return null;
    const existing = window[canvasId + '_instance'];
    if(existing){
      existing.config.type = type;
      existing.data = data;
      existing.options = options || existing.options;
      existing.update();
      return existing;
    }
    const c = new Chart(ctx, { type, data, options });
    window[canvasId + '_instance'] = c;
    return c;
  }

  function getDefaultChartOptions(){
    return {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { labels: { color: 'rgba(255, 255, 255, 0.8)' } }
      },
      scales: {
        x: {
          ticks: { color: 'rgba(255, 255, 255, 0.6)' },
          grid: { color: 'rgba(255, 255, 255, 0.1)' }
        },
        y: {
          ticks: { color: 'rgba(255, 255, 255, 0.6)' },
          grid: { color: 'rgba(255, 255, 255, 0.1)' }
        }
      }
    };
  }

  async function loadReports(){
    try {
      const { from, to } = getRange();
      const qs = `?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`;
      const ordersRes = await fetchJSON(`../backend/php/orders_list.php${qs}`);
      const orders = ordersRes.ok ? (ordersRes.orders || []) : [];
      const { totals, newCustomers, returningCustomers, retention } = aggregateOrders(orders);

      // Stats: Sales
      setText('statTotalSales', fmtCurrency(totals.totalSales));
      setText('statOrdersCount', String(totals.ordersCount));
      setText('statAvgOrder', fmtCurrency(totals.avgOrder));

      // Stats: Customers
      setText('statNewCustomers', String(newCustomers));
      setText('statReturningCustomers', String(returningCustomers));
      setText('statCustomerRetention', `${retention}%`);

      // Stats: Top product sales quantity
      const topQty = aggregateTopProductSales(orders);
      setText('statTopProductSales', String(topQty));

      // Charts: Sales daily
      const days = ['lun.', 'mar.', 'mié.', 'jue.', 'vie.', 'sáb.', 'dom.'];
      const dayData = buildArrayFromMap(totals.dayTotals, days);
      ensureChart('salesChart', 'line', {
        labels: days.map(d => d.charAt(0).toUpperCase() + d.slice(1)),
        datasets: [{
          label: 'Ventas Diarias',
          data: dayData,
          borderColor: '#48bb78',
          backgroundColor: 'rgba(72, 187, 120, 0.1)',
          fill: true,
          tension: 0.4
        }]
      }, getDefaultChartOptions());

      // Charts: Sales trend monthly (current year)
      const months = ['ene.', 'feb.', 'mar.', 'abr.', 'may.', 'jun.', 'jul.', 'ago.', 'sep.', 'oct.', 'nov.', 'dic.'];
      const monthData = buildArrayFromMap(totals.monthTotals, months);
      ensureChart('salesTrendChart', 'line', {
        labels: months.map(m => m.replace('.', '').toUpperCase()),
        datasets: [{
          label: 'Ventas',
          data: monthData,
          borderColor: '#48bb78',
          backgroundColor: 'rgba(72, 187, 120, 0.1)',
          fill: true,
          tension: 0.4
        }]
      }, getDefaultChartOptions());

      // Categories for Products chart and count
      const catsRes = await fetchJSON('../backend/php/get_categories.php');
      const cats = catsRes.ok ? (catsRes.categories || []) : [];
      setText('statCategoriesCount', String(cats.length));
      ensureChart('productsChart', 'bar', {
        labels: cats.map(c => c.display || c.name || ''),
        datasets: [{
          label: 'Productos por Categoría',
          data: cats.map(c => Number(c.product_count || 0)),
          borderColor: '#4299e1',
          backgroundColor: 'rgba(66, 153, 225, 0.2)'
        }]
      }, getDefaultChartOptions());

      // Products for inventory metrics
      const prodsRes = await fetchJSON('../backend/php/get_products.php');
      const prods = prodsRes.ok ? (prodsRes.products || []) : [];
      const inv = aggregateProducts(prods);
      setText('statLowStock', String(inv.lowStock));
      setText('statTotalItems', String(inv.totalItems));
      setText('statInventoryValue', fmtCurrency(inv.inventoryValue));

      // Inventory radar chart
      let stockHigh=0, stockMid=0, stockLow=0, stockZero=0;
      for(const p of prods){
        const stock = Number(p.stock ?? p.min_stock ?? p.stock_min ?? p.stock_minimo ?? 0);
        const minStock = Number(p.stock_minimo ?? p.min_stock ?? p.stock_min ?? 5);
        if(stock <= 0) stockZero += 1;
        else if(stock <= minStock) stockLow += 1;
        else if(stock <= minStock * 2) stockMid += 1;
        else stockHigh += 1;
      }
      ensureChart('inventoryChart', 'radar', {
        labels: ['Stock Alto', 'Stock Medio', 'Stock Bajo', 'Sin Stock', 'Reabastecido'],
        datasets: [{
          label: 'Estado del Inventario',
          data: [stockHigh, stockMid, stockLow, stockZero, 0],
          borderColor: '#ed8936',
          backgroundColor: 'rgba(237, 137, 54, 0.2)',
          pointBackgroundColor: '#ed8936'
        }]
      }, {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { labels: { color: 'rgba(255, 255, 255, 0.8)' } } },
        scales: { r: { ticks: { color: 'rgba(255, 255, 255, 0.6)' }, grid: { color: 'rgba(255, 255, 255, 0.1)' }, angleLines: { color: 'rgba(255, 255, 255, 0.1)' } } }
      });

    } catch(err){
      console.error('Error cargando reportes:', err);
    }
  }

  // Expose update function matching existing button
  window.updateReports = function(){ loadReports(); };
  window.addEventListener('DOMContentLoaded', loadReports);
})();