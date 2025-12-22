<?php
/**
 * Login AJAX limpio y estable
 */

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

require_once 'config.php';

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Verificar POST
    if (!isset($_POST['usuario'], $_POST['password'])) {
        throw new Exception('Datos incompletos');
    }

    $usuario = trim($_POST['usuario']);
    $password = $_POST['password'];

    if ($usuario === '' || $password === '') {
        throw new Exception('Completa todos los campos');
    }

    // Buscar usuario (EMAIL)
    $sql = "SELECT id, nombre, email, password, rol, estado 
            FROM usuarios 
            WHERE email = ? 
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Error interno');
    }

    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Usuario o contraseña incorrectos');
    }

    $user = $result->fetch_assoc();

    // Verificar contraseña
    if (!password_verify($password, $user['password'])) {
        throw new Exception('Usuario o contraseña incorrectos');
    }

    // Verificar estado
    if ((int)$user['estado'] !== 1) {
        throw new Exception('Cuenta desactivada');
    }

    // Crear sesión
    session_regenerate_id(true);
    $_SESSION['usuario_id']     = $user['id'];
    $_SESSION['usuario_nombre'] = $user['nombre'];
    $_SESSION['usuario_email']  = $user['email'];
    $_SESSION['rol']            = $user['rol'];

    echo json_encode([
        'success'  => true,
        'message'  => 'Login correcto',
        'redirect' => 'pages/dashboard.php'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

exit;
