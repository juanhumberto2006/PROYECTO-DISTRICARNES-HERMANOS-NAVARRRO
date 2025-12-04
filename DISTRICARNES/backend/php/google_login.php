<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/conexion.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    if (isset($_POST['credential'])) {
        $id_token = $_POST['credential'];

        $client = new Google_Client(['client_id' => '1089395533199-070ohtiul6msdderh593mlp8m7v7lv3j.apps.googleusercontent.com']);
        $payload = $client->verifyIdToken($id_token);

        if ($payload) {
            $userid = $payload['sub'];
            $email = $payload['email'];
            $name = $payload['name'];
            $picture = $payload['picture'];

            // Check if user exists
            $sql = "SELECT id_usuario, nombres_completos, correo_electronico, rol FROM usuario WHERE correo_electronico = ?";
            $stmt = $conexion->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare statement failed: " . $conexion->error);
            }
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                // User exists, log them in
                $user = $result->fetch_assoc();
                $_SESSION['user_id'] = $user['id_usuario'];
                $_SESSION['user_rol'] = $user['rol'];
                $_SESSION['logged_in'] = true;

                $redirect_url = ($user['rol'] === 'admin') ? '/admin/admin_dashboard.html' : '/index.html';

                $response = [
                    'success' => true,
                    'message' => 'Login successful',
                    'user' => [
                        'id' => $user['id_usuario'],
                        'nombre' => $user['nombres_completos'],
                        'email' => $user['correo_electronico'],
                        'rol' => $user['rol']
                    ],
                    'redirect_url' => $redirect_url
                ];
            } else {
                // User does not exist, create a new user
                $rol = 'trabajo'; // default role
                $cedula = $userid; // Use Google ID as cedula
                $password = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT); // Generate random password
                $direccion_placeholder = ''; // Placeholder for direccion
                $celular_placeholder = ''; // Placeholder for celular

                $sql_insert = "INSERT INTO usuario (nombres_completos, correo_electronico, rol, cedula, contrasena, direccion, celular) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt_insert = $conexion->prepare($sql_insert);
                if (!$stmt_insert) {
                    throw new Exception("Prepare insert statement failed: " . $conexion->error);
                }
                $stmt_insert->bind_param("sssssss", $name, $email, $rol, $cedula, $password, $direccion_placeholder, $celular_placeholder);
                
                if ($stmt_insert->execute()) {
                    $new_user_id = $stmt_insert->insert_id;
                    $_SESSION['user_id'] = $new_user_id;
                    $_SESSION['user_rol'] = $rol;
                    $_SESSION['logged_in'] = true;

                    $redirect_url = ($rol === 'admin') ? '/admin/admin_dashboard.html' : '/index.html';

                    $response = [
                        'success' => true,
                        'message' => 'Registration and login successful',
                        'user' => [
                            'id' => $new_user_id,
                            'nombre' => $name,
                            'email' => $email,
                            'rol' => $rol
                        ],
                        'redirect_url' => $redirect_url
                    ];
                } else {
                    $response['message'] = 'Error creating new user: ' . $stmt_insert->error;
                }
            }
        }
    }
} catch (Exception $e) {
    $response['message'] = 'Server error: ' . $e->getMessage();
    error_log('Google Login Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
} finally {
    if (isset($conexion)) {
        $conexion->close();
    }
}

echo json_encode($response);
exit;
?>