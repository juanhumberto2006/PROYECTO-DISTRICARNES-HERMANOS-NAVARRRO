<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['rol'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit;
}

$settingsFilePath = __DIR__ . '/settings.json';
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'get_settings':
        getSettings($settingsFilePath);
        break;
    case 'save_settings':
        saveSettings($settingsFilePath);
        break;
    case 'backup_database':
        backupDatabase();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
        break;
}

function getSettings($filePath) {
    if (file_exists($filePath)) {
        $settings = file_get_contents($filePath);
        echo $settings;
    } else {
        // Default settings structure
        $defaults = [
            'siteName' => 'DistriCarnes',
            'contactEmail' => 'admin@districarnes.com',
            'twoFactorAuth' => false,
            'paypalClientId' => '',
            'maintenanceMode' => false
        ];
        echo json_encode(['success' => true, 'settings' => $defaults]);
    }
}

function saveSettings($filePath) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Error en los datos JSON recibidos.']);
        exit;
    }

    // Basic validation
    $settings = [
        'siteName' => filter_var($data['siteName'] ?? 'DistriCarnes', FILTER_SANITIZE_STRING),
        'contactEmail' => filter_var($data['contactEmail'] ?? '', FILTER_VALIDATE_EMAIL) ? $data['contactEmail'] : '',
        'twoFactorAuth' => !empty($data['twoFactorAuth']),
        'paypalClientId' => filter_var($data['paypalClientId'] ?? '', FILTER_SANITIZE_STRING),
        'maintenanceMode' => !empty($data['maintenanceMode'])
    ];

    if (file_put_contents($filePath, json_encode($settings, JSON_PRETTY_PRINT))) {
        echo json_encode(['success' => true, 'message' => 'Configuración guardada exitosamente.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo guardar la configuración. Verifica los permisos del archivo.']);
    }
}

function backupDatabase() {
    // This is a placeholder for a real database backup implementation.
    // A real implementation would be complex and require mysqldump or similar.
    echo json_encode(['success' => true, 'message' => 'El proceso de respaldo se ha iniciado (simulación).']);
}
?>
