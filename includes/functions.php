<?php
/**
 * FUNCIONES AUXILIARES GLOBALES
 */

function mostrar_alertas() {
    if (isset($_SESSION['mensaje'])) {
        $tipo = $_SESSION['tipo_mensaje'] ?? 'info';
        echo '<div class="alert alert-' . $tipo . ' alert-dismissible fade show">';
        echo htmlspecialchars($_SESSION['mensaje']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
        unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']);
    }
}

function set_mensaje($mensaje, $tipo = 'success') {
    $_SESSION['mensaje'] = $mensaje;
    $_SESSION['tipo_mensaje'] = $tipo;
}