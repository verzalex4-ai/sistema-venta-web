/**
 * MAIN.JS - JavaScript Global
 */

function formatearPrecio(precio) {
    return '$' + parseFloat(precio).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function confirmarEliminacion(mensaje = '¿Eliminar este elemento?') {
    return confirm(mensaje);
}

// Auto-hide alerts
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 5000);
    });
});

window.SistemaVentas = {
    formatearPrecio,
    confirmarEliminacion
};