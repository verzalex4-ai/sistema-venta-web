<?php
/**
 * LOGIN AJAX - Versión simplificada y segura
 */

// Desactivar output de errores para JSON limpio
error_reporting(0);
ini_set('display_errors', 0);

// Headers para JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

require_once 'config.php';

// Función para responder
function responder($success, $message, $data = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responder(false, 'Método no permitido');
    }

    // Obtener y validar datos
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($usuario) || empty($password)) {
        responder(false, 'Por favor completa todos los campos');
    }

    // Buscar usuario por email
    $sql = "SELECT id, nombre, email, password, rol, estado 
            FROM usuarios 
            WHERE email = ? 
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Error en la consulta');
    }

    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();

    // Usuario no encontrado
    if ($result->num_rows === 0) {
        responder(false, 'Usuario o contraseña incorrectos');
    }

    $user = $result->fetch_assoc();

    // Verificar contraseña
    if (!password_verify($password, $user['password'])) {
        responder(false, 'Usuario o contraseña incorrectos');
    }

    // Verificar estado
    if ((int)$user['estado'] !== 1) {
        responder(false, 'Tu cuenta está desactivada. Contacta al administrador.');
    }

    // Regenerar ID de sesión por seguridad
    session_regenerate_id(true);

    // Establecer variables de sesión
    $_SESSION['usuario_id']     = $user['id'];
    $_SESSION['usuario_nombre'] = $user['nombre'];
    $_SESSION['usuario_email']  = $user['email'];
    $_SESSION['rol']            = $user['rol'];

    // Actualizar última sesión
    $conn->query("UPDATE usuarios SET fecha_ultima_sesion = NOW() WHERE id = " . $user['id']);

    // Determinar redirección según rol
    $redirect = 'pages/dashboard.php';
    
    if ($user['rol'] === 'cliente') {
        $redirect = 'index.php'; // Clientes van al catálogo
    }

    // Respuesta exitosa
    responder(true, '¡Bienvenido!', [
        'redirect' => $redirect,
        'nombre' => $user['nombre'],
        'rol' => $user['rol']
    ]);

} catch (Exception $e) {
    // Log del error (en producción)
    error_log('Error en login: ' . $e->getMessage());
    
    // Respuesta genérica al usuario
    responder(false, 'Error en el servidor. Intenta nuevamente.');
}