<?php
header('Content-Type: application/json');
session_start();

// Cargar el autoloader de Composer para usar la librería de Twilio
require_once __DIR__ . '/../../vendor/autoload.php';

use Twilio\Rest\Client;
use Twilio\Exceptions\TwilioException;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';

if (empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'El número de teléfono es requerido.']);
    exit;
}

// Twilio requiere el formato E.164 (+CODIGO_PAISNUMERO)
$e164_phone = '+57' . $phone; // Asumiendo que los usuarios son de Colombia

// --- Credenciales de Twilio ---
$sid    = "ACc847435e87340c353d4a3da828e471fc";
$token  = "0a6451a948369db18db7a47cdb555859";
$verify_sid = "VA4cfef02b48cee4f72b26ce704fb0ed3f"; // Tu Service SID de Twilio Verify

try {
    $twilio = new Client($sid, $token);

    // Iniciar la verificación usando la API de Twilio Verify
    $verification = $twilio->verify->v2->services($verify_sid)
                                       ->verifications
                                       ->create($e164_phone, "sms");

    // Guardar el número de teléfono en la sesión para usarlo en el siguiente paso
    $_SESSION['verification_phone_e164'] = $e164_phone;

    // Si la solicitud a Twilio fue exitosa, devuelve un mensaje de éxito.
    // Twilio se encarga de generar y enviar el código.
    echo json_encode([
        'success' => true,
        'message' => 'Hemos enviado un código de verificación a tu teléfono.'
    ]);

} catch (TwilioException $e) {
    // Si Twilio falla, devuelve el error específico para depuración.
    error_log("Error de Twilio Verify: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de Twilio: ' . $e->getMessage()
    ]);
}
?>
