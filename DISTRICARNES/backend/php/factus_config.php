<?php
// Configuración de integración con Factus (Sandbox)
// Ajusta estos valores según tus credenciales y entorno

// Endpoint base (Sandbox)
define('FACTUS_BASE_URL', getenv('FACTUS_BASE_URL') ?: 'https://api-sandbox.factus.com.co');

// Credenciales OAuth (cliente)
define('FACTUS_CLIENT_ID', getenv('FACTUS_CLIENT_ID') ?: '9fc833b1-63d6-48a6-a529-9f1a17314144');
define('FACTUS_CLIENT_SECRET', getenv('FACTUS_CLIENT_SECRET') ?: '5MH7IpTieMD8Hm86yyuKTOEuXpc9sSj2RiCFmkKh');

// Usuario sandbox (si el flujo de autenticación lo requiere)
define('FACTUS_USERNAME', getenv('FACTUS_USERNAME') ?: 'sandbox@factus.com.co');
define('FACTUS_PASSWORD', getenv('FACTUS_PASSWORD') ?: 'sandbox2024%');

// Rutas de API (ajusta conforme a la documentación oficial)
define('FACTUS_OAUTH_TOKEN_PATH', getenv('FACTUS_OAUTH_TOKEN_PATH') ?: '/oauth/token');
define('FACTUS_INVOICE_CREATE_PATH', getenv('FACTUS_INVOICE_CREATE_PATH') ?: '/v1/invoices');

// Datos de la empresa emisora (deben coincidir con la configuración de tu cuenta Factus)
define('FACTUS_COMPANY_NAME', 'DistriCarnes Hermanos Navarro');
define('FACTUS_COMPANY_NIT', '900000000-0');
define('FACTUS_COMPANY_EMAIL', 'soporte@districarnes.local');
define('FACTUS_COMPANY_PHONE', '+57 300 000 0000');
define('FACTUS_COMPANY_ADDRESS', 'Calle Principal #123, Cartagena');

// Moneda por defecto
define('FACTUS_CURRENCY_DEFAULT', 'USD');

?>