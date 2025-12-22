<?php
/**
 * DASHBOARD - Versión optimizada y limpia
 */
require_once '../config.php';

// Verificar autenticación
requiere_login();

// Redirigir clientes al catálogo
if ($_SESSION['rol'] === 'cliente') {
    header('Location: ../index.php');
    exit;
}

// ============================================
// OBTENER DATOS DEL DASHBOARD
// ============================================

// Obtener métricas del mes actual
$mes_actual = date('Y-m');
$anio_actual = date('Y');

// Ventas mensuales
$sql = "SELECT COALESCE(SUM(total), 0) as total 
        FROM ventas 
        WHERE DATE_FORMAT(fecha_creacion, '%Y-%m') = ? AND estado='completada'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $mes_actual);
$stmt->execute();
$ventas_mensuales = $stmt->get_result()->fetch_assoc()['total'];

// Ventas anuales
$sql = "SELECT COALESCE(SUM(total), 0) as total 
        FROM ventas 
        WHERE YEAR(fecha_creacion) = ? AND estado='completada'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $anio_actual);
$stmt->execute();
$ventas_anuales = $stmt->get_result()->fetch_assoc()['total'];

// Total productos
$total_productos = $conn->query("SELECT COUNT(*) as total FROM productos WHERE estado=1")->fetch_assoc()['total'];

// Total clientes
$total_clientes = $conn->query("SELECT COUNT(*) as total FROM clientes WHERE estado=1")->fetch_assoc()['total'];

// Alertas de stock
$productos_criticos = $conn->query("SELECT COUNT(*) as total FROM productos WHERE stock <= 5 AND estado=1")->fetch_assoc()['total'];
$productos_bajo = $conn->query("SELECT COUNT(*) as total FROM productos WHERE stock > 5 AND stock <= 10 AND estado=1")->fetch_assoc()['total'];
$total_alertas = $productos_criticos + $productos_bajo;

// Productos con stock crítico (detalles)
$sql = "SELECT p.id, p.nombre, p.stock, p.precio, c.nombre as categoria
        FROM productos p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        WHERE p.estado=1 AND p.stock <= 10
        ORDER BY p.stock ASC
        LIMIT 10";
$productos_alerta = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Ventas últimos 12 meses (para gráfico)
$sql = "SELECT DATE_FORMAT(fecha_creacion, '%Y-%m') as mes, 
               DATE_FORMAT(fecha_creacion, '%b %Y') as mes_nombre,
               COALESCE(SUM(total), 0) as total 
        FROM ventas 
        WHERE estado='completada' 
        GROUP BY DATE_FORMAT(fecha_creacion, '%Y-%m'), DATE_FORMAT(fecha_creacion, '%b %Y')
        ORDER BY mes DESC 
        LIMIT 12";
$datos_grafico = array_reverse($conn->query($sql)->fetch_all(MYSQLI_ASSOC));

// Ventas por categoría (top 5)
$sql = "SELECT c.nombre, COALESCE(SUM(dv.subtotal), 0) as total
        FROM detalle_ventas dv
        INNER JOIN productos p ON dv.producto_id = p.id
        INNER JOIN categorias c ON p.categoria_id = c.id
        INNER JOIN ventas v ON dv.venta_id = v.id
        WHERE v.estado='completada'
        GROUP BY c.id, c.nombre
        ORDER BY total DESC
        LIMIT 5";
$datos_categorias = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// ============================================
// CONFIGURAR PÁGINA
// ============================================
$page_title = 'Dashboard - Sistema de Ventas';
$chart_js = true;

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div id="content-wrapper" class="d-flex flex-column">
    <?php include '../includes/topbar.php'; ?>
    
    <div class="container-fluid px-4 py-4">
        
        <!-- Encabezado -->
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
            </h1>
            <a href="reportes.php" class="btn btn-primary">
                <i class="fas fa-download me-2"></i>Generar Reporte
            </a>
        </div>
        
        <!-- Tarjetas de Métricas -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Ventas (Mensual)
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo formatear_precio($ventas_mensuales); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Ventas (Anual)
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo formatear_precio($ventas_anuales); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Productos
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo $total_productos; ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Clientes
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo $total_clientes; ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-users fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Alertas de Stock -->
        <?php if ($total_alertas > 0): ?>
        <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                    <div>
                        <h5 class="mb-1">⚠️ Productos con Stock Bajo</h5>
                        <p class="mb-0">
                            <?php if ($productos_criticos > 0): ?>
                                <span class="badge bg-danger me-2"><?php echo $productos_criticos; ?> críticos</span>
                            <?php endif; ?>
                            <?php if ($productos_bajo > 0): ?>
                                <span class="badge bg-warning text-dark"><?php echo $productos_bajo; ?> bajos</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-warning" 
                        data-bs-toggle="collapse" data-bs-target="#alertaStockDetalle">
                    <i class="fas fa-eye"></i> Ver Detalles
                </button>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        
        <!-- Detalle de Stock Bajo -->
        <div class="collapse mb-4" id="alertaStockDetalle">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0"><i class="fas fa-boxes"></i> Productos que Requieren Atención</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Categoría</th>
                                    <th class="text-center">Stock</th>
                                    <th class="text-end">Precio</th>
                                    <th class="text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productos_alerta as $prod): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($prod['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($prod['categoria'] ?? 'Sin categoría'); ?></td>
                                    <td class="text-center">
                                        <span class="badge <?php echo $prod['stock'] <= 5 ? 'bg-danger' : 'bg-warning text-dark'; ?>">
                                            <?php echo $prod['stock']; ?>
                                        </span>
                                    </td>
                                    <td class="text-end"><?php echo formatear_precio($prod['precio']); ?></td>
                                    <td class="text-center">
                                        <a href="productos.php#producto-<?php echo $prod['id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i> Actualizar
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Gráficos -->
        <div class="row">
            <div class="col-xl-8 col-lg-7 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Ventas Mensuales</h6>
                    </div>
                    <div class="card-body">
                        <?php if (count($datos_grafico) > 0): ?>
                            <div class="chart-container">
                                <canvas id="ventasChart"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-chart-line fa-3x text-gray-300 mb-3"></i>
                                <p class="text-muted">No hay datos de ventas disponibles</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-4 col-lg-5 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Ventas por Categoría</h6>
                    </div>
                    <div class="card-body">
                        <?php if (count($datos_categorias) > 0): ?>
                            <div class="chart-container">
                                <canvas id="categoriasChart"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-chart-pie fa-3x text-gray-300 mb-3"></i>
                                <p class="text-muted">No hay datos de categorías disponibles</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>

<?php
// Script para los gráficos
if (count($datos_grafico) > 0 || count($datos_categorias) > 0) {
    $inline_script = "
    // Gráfico de Ventas
    " . (count($datos_grafico) > 0 ? "
    const datosVentas = " . json_encode($datos_grafico) . ";
    const ctxVentas = document.getElementById('ventasChart');
    if (ctxVentas) {
        new Chart(ctxVentas, {
            type: 'line',
            data: {
                labels: datosVentas.map(item => item.mes_nombre || item.mes),
                datasets: [{
                    label: 'Ventas',
                    data: datosVentas.map(item => parseFloat(item.total)),
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.05)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: value => '$' + value.toLocaleString('es-AR')
                        }
                    }
                }
            }
        });
    }" : "") . "
    
    // Gráfico de Categorías
    " . (count($datos_categorias) > 0 ? "
    const datosCategorias = " . json_encode($datos_categorias) . ";
    const ctxCategorias = document.getElementById('categoriasChart');
    if (ctxCategorias) {
        new Chart(ctxCategorias, {
            type: 'doughnut',
            data: {
                labels: datosCategorias.map(item => item.nombre),
                datasets: [{
                    data: datosCategorias.map(item => parseFloat(item.total)),
                    backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }" : "") . "
    ";
}

include '../includes/footer.php';
?>