<?php
// Script para generar el hash correcto de la contraseña
$password = "admin123";
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Hash para 'admin123': " . $hash . "\n";
echo "\nUsa este SQL para actualizar:\n";
echo "UPDATE usuarios SET password = '" . $hash . "' WHERE email = 'admin@sistema.local';\n";
echo "UPDATE usuarios SET password = '" . $hash . "' WHERE email = 'vendedor@sistema.local';\n";
echo "UPDATE usuarios SET password = '" . $hash . "' WHERE email = 'repositor@sistema.local';\n";
?>
