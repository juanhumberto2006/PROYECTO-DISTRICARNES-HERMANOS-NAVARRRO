<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

include_once __DIR__ . '/conexion.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$action = $_POST['action'] ?? '';
$userId = $_SESSION['user_id'];

switch ($action) {
    case 'update_profile':
        updateProfile($conexion, $userId);
        break;
    case 'change_password':
        changePassword($conexion, $userId);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
        break;
}

function updateProfile($conexion, $userId) {
    $fullName = $_POST['fullName'] ?? '';
    $email = $_POST['email'] ?? '';

    if (empty($fullName) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Por favor, completa todos los campos.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'El formato del correo electrónico no es válido.']);
        exit;
    }

    // Check if email is already in use by another user
    $sql_check = "SELECT id_usuario FROM usuario WHERE correo_electronico = ? AND id_usuario != ?";
    $stmt_check = $conexion->prepare($sql_check);
    $stmt_check->bind_param("si", $email, $userId);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'El correo electrónico ya está en uso por otro usuario.']);
        $stmt_check->close();
        return;
    }
    $stmt_check->close();

    // Update user profile
    $sql = "UPDATE usuario SET nombres_completos = ?, correo_electronico = ? WHERE id_usuario = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ssi", $fullName, $email, $userId);

    if ($stmt->execute()) {
        // Update session variables
        $_SESSION['user_name'] = $fullName;
        $_SESSION['user_email'] = $email;
        echo json_encode(['success' => true, 'message' => 'Perfil actualizado exitosamente.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el perfil.']);
    }
    $stmt->close();
}

function changePassword($conexion, $userId) {
    $currentPassword = $_POST['currentPassword'] ?? '';
    $newPassword = $_POST['newPassword'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        echo json_encode(['success' => false, 'message' => 'Por favor, completa todos los campos de contraseña.']);
        exit;
    }

    if (strlen($newPassword) < 8) {
        echo json_encode(['success' => false, 'message' => 'La nueva contraseña debe tener al menos 8 caracteres.']);
        exit;
    }

    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Las nuevas contraseñas no coinciden.']);
        exit;
    }

    // Get current password hash
    $sql_get = "SELECT contrasena FROM usuario WHERE id_usuario = ?";
    $stmt_get = $conexion->prepare($sql_get);
    $stmt_get->bind_param("i", $userId);
    $stmt_get->execute();
    $result = $stmt_get->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $hashedPassword = $user['contrasena'];

        if (password_verify($currentPassword, $hashedPassword)) {
            // Hash new password
            $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            // Update password in DB
            $sql_update = "UPDATE usuario SET contrasena = ? WHERE id_usuario = ?";
            $stmt_update = $conexion->prepare($sql_update);
            $stmt_update->bind_param("si", $newHashedPassword, $userId);

            if ($stmt_update->execute()) {
                echo json_encode(['success' => true, 'message' => 'Contraseña actualizada exitosamente.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar la contraseña.']);
            }
            $stmt_update->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'La contraseña actual es incorrecta.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo encontrar el usuario.']);
    }
    $stmt_get->close();
}

$conexion->close();
?>
