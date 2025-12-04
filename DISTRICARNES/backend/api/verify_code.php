<?php
header('Content-Type: application/json');
session_start();

// Cargar dependencias
require_once __DIR__ . '/../../vendor/autoload.php';
require_once '../php/conexion.php';

use Twilio\Rest\Client;
use Twilio\Exceptions\TwilioException;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$code = isset($_POST['code']) ? trim($_POST['code']) : '';

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'El código de verificación es requerido.']);
    exit;
}

if (!isset($_SESSION['verification_phone_e164'])) {
    echo json_encode(['success' => false, 'message' => 'No se ha iniciado un proceso de verificación o la sesión ha expirado.']);
    exit;
}

$phone_to_verify = $_SESSION['verification_phone_e164'];

// --- Credenciales de Twilio ---
$sid    = "ACc847435e87340c353d4a3da828e471fc";
$token  = "0a6451a948369db18db7a47cdb555859";
$verify_sid = "VA4cfef02b48cee4f72b26ce704fb0ed3f";

try {
    $twilio = new Client($sid, $token);

    $verification_check = $twilio->verify->v2->services($verify_sid)
                                             ->verificationChecks
                                             ->create([
                                                 "to" => $phone_to_verify,
                                                 "code" => $code
                                             ]);

    if ($verification_check->status === 'approved') {
        // El código es correcto.
        $phone_for_db = substr($phone_to_verify, 3); // Extraer número sin +57

        // CORRECCIÓN: Buscar en la columna 'celular' en lugar de 'telefono'
        $sql = "SELECT id_usuario, nombres_completos, correo_electronico, rol FROM usuario WHERE celular = ? LIMIT 1";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("s", $phone_for_db);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = null;

        if ($result && $result->num_rows === 1) {
            // --- USUARIO ENCONTRADO: Iniciar sesión ---
            $user_data = $result->fetch_assoc();
        } else {
            // --- USUARIO NO ENCONTRADO: Registrarlo automáticamente ---
            $placeholder_name = "Usuario " . $phone_for_db;
            $placeholder_email = $phone_for_db . "@districarnes.com"; // Email único provisional
            $placeholder_cedula = $phone_for_db; // Cédula única provisional
            $placeholder_address = "No especificada";
            $random_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
            $default_role = 'trabajo';

            $insert_sql = 'INSERT INTO usuario (nombres_completos, cedula, direccion, celular, correo_electronico, contrasena, rol) VALUES (?,?,?,?,?,?,?)';
            $insert_stmt = $conexion->prepare($insert_sql);
            $insert_stmt->bind_param('sssssss', $placeholder_name, $placeholder_cedula, $placeholder_address, $phone_for_db, $placeholder_email, $random_password, $default_role);
            
            if ($insert_stmt->execute()) {
                $new_user_id = $insert_stmt->insert_id;
                $user_data = [
                    'id_usuario' => $new_user_id,
                    'nombres_completos' => $placeholder_name,
                    'correo_electronico' => $placeholder_email,
                    'rol' => $default_role
                ];
            }
            $insert_stmt->close();
        }
        $stmt->close();

        if ($user_data) {
            // --- Iniciar sesión del usuario (existente o nuevo) ---
            $_SESSION['user_id'] = $user_data['id_usuario'];
            $_SESSION['user_email'] = $user_data['correo_electronico'];
            $_SESSION['user_name'] = $user_data['nombres_completos'];
            $_SESSION['rol'] = $user_data['rol'];
            $_SESSION['logged_in'] = true;

            unset($_SESSION['verification_phone_e164']);

            echo json_encode([
                'success' => true,
                'message' => 'Inicio de sesión exitoso.',
                'user' => [
                    'id' => $user_data['id_usuario'],
                    'nombre' => $user_data['nombres_completos'],
                    'email' => $user_data['correo_electronico'],
                    'rol' => $user_data['rol']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No se pudo registrar al nuevo usuario.']);
        }

    } else {
        echo json_encode(['success' => false, 'message' => 'El código de verificación es incorrecto.']);
    }
    $conexion->close();

} catch (Throwable $e) {
    error_log("Error al verificar código: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al verificar el código: ' . $e->getMessage()
    ]);
}
?>
