// Utilidad para cargar el SDK de PayPal desde el frontend
// Obtiene el client_id de forma segura desde el backend (sin exponer el secret)

async function fetchPayPalConfig() {
  const res = await fetch('../backend/php/get_paypal_client.php');
  const conf = await res.json();
  return conf;
}

async function loadPayPalSdk() {
  if (window.paypal) return true;
  try {
    const conf = await fetchPayPalConfig();
    const clientId = conf.client_id;
    const currency = conf.currency || 'USD';
    const components = conf.components || 'buttons';
    const funding = conf.enable_funding || 'card,venmo,paylater';
    const params = new URLSearchParams({
      'client-id': clientId,
      currency,
      components,
      'enable-funding': funding,
      intent: 'capture'
    });
    const script = document.createElement('script');
    script.src = `https://www.paypal.com/sdk/js?${params.toString()}`;
    script.async = true;
    script.setAttribute('data-namespace', 'paypal');
    document.head.appendChild(script);
    await new Promise((resolve, reject) => {
      script.onload = () => resolve(true);
      script.onerror = () => reject(new Error('SDK PayPal no cargó'));
    });
    return !!window.paypal;
  } catch (e) {
    return false;
  }
}

window.PayPalLoader = { fetchPayPalConfig, loadPayPalSdk };