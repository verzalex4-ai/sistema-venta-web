<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Verificar sesión
if (!esta_logueado()) {
    header('Location: ../login.php'); exit();;
}

// Obtener estadísticas generales
$sql_total_ventas = "SELECT COUNT(*) as total FROM ventas WHERE estado='completada'";
$total_ventas = $conn->query($sql_total_ventas)->fetch_assoc()['total'];

$sql_total_ingresos = "SELECT SUM(total) as ingresos FROM ventas WHERE estado='completada'";
$total_ingresos = $conn->query($sql_total_ingresos)->fetch_assoc()['ingresos'] ?? 0;

$sql_total_productos = "SELECT COUNT(*) as total FROM productos WHERE estado=1";
$total_productos = $conn->query($sql_total_productos)->fetch_assoc()['total'];

$sql_total_clientes = "SELECT COUNT(*) as total FROM clientes WHERE estado=1";
$total_clientes = $conn->query($sql_total_clientes)->fetch_assoc()['total'];

// Ventas por mes (últimos 12 meses)
$sql_ventas_mes = "SELECT DATE_FORMAT(fecha_creacion, '%Y-%m') as mes, 
                   DATE_FORMAT(fecha_creacion, '%b %Y') as mes_nombre,
                   SUM(total) as total 
                   FROM ventas WHERE estado='completada' 
                   GROUP BY DATE_FORMAT(fecha_creacion, '%Y-%m'), DATE_FORMAT(fecha_creacion, '%b %Y')
                   ORDER BY mes DESC LIMIT 12";
$result_ventas_mes = $conn->query($sql_ventas_mes);
$ventas_mes = [];
while ($row = $result_ventas_mes->fetch_assoc()) {
    $ventas_mes[] = $row;
}
$ventas_mes = array_reverse($ventas_mes);

// Productos más vendidos
$sql_productos_top = "SELECT p.nombre, SUM(dv.cantidad) as vendido, SUM(dv.subtotal) as ingresos
                      FROM detalle_ventas dv
                      INNER JOIN productos p ON dv.producto_id = p.id
                      INNER JOIN ventas v ON dv.venta_id = v.id
                      WHERE v.estado='completada'
                      GROUP BY p.id
                      ORDER BY vendido DESC
                      LIMIT 10";
$result_productos_top = $conn->query($sql_productos_top);

// Ventas por categoría
$sql_ventas_categoria = "SELECT c.nombre, SUM(dv.subtotal) as total
                         FROM detalle_ventas dv
                         INNER JOIN productos p ON dv.producto_id = p.id
                         INNER JOIN categorias c ON p.categoria_id = c.id
                         INNER JOIN ventas v ON dv.venta_id = v.id
                         WHERE v.estado='completada'
                         GROUP BY c.id
                         ORDER BY total DESC";
$result_ventas_categoria = $conn->query($sql_ventas_categoria);
$ventas_categoria = [];
while ($row = $result_ventas_categoria->fetch_assoc()) {
    $ventas_categoria[] = $row;
}

// Métodos de pago
$sql_metodos_pago = "SELECT metodo_pago, COUNT(*) as cantidad, SUM(total) as monto
                     FROM ventas WHERE estado='completada'
                     GROUP BY metodo_pago";
$result_metodos_pago = $conn->query($sql_metodos_pago);
$metodos_pago = [];
while ($row = $result_metodos_pago->fetch_assoc()) {
    $metodos_pago[] = $row;
}
?>

$page_title = 'Reportes - Sistema de Ventas';
$chart_js = true;
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div id="content-wrapper" class="flex-fill">
<?php include "../includes/topbar.php"; ?>
<div class="container-fluid px-4 py-4">
<div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Reportes y Estadísticas</h1>
                    <div class="no-print">
                        <div class="btn-group">
                            <button class="btn btn-success" onclick="window.location.href='exportar_excel.php?tipo=general'">
                                <i class="fas fa-file-excel"></i> Exportar Excel
                            </button>
                            <button class="btn btn-primary" onclick="window.print()">
                                <i class="fas fa-print"></i> Imprimir
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Cards de Métricas -->
                <div class="row">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Ventas</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_ventas; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
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
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Ingresos Totales</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatear_precio($total_ingresos); ?></div>
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
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Productos</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_productos; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-box fa-2x text-gray-300"></i>
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
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Clientes</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_clientes; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos -->
                <div class="row">
                    <div class="col-xl-8">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold" style="color: var(--primary);">Ventas por Mes</h6>
                            </div>
                            <div class="card-body">
                                <?php if (count($ventas_mes) > 0): ?>
                                    <div class="chart-container">
                                        <canvas id="ventasMesChart"></canvas>
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

                    <div class="col-xl-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold" style="color: var(--primary);">Ventas por Categoría</h6>
                            </div>
                            <div class="card-body">
                                <?php if (count($ventas_categoria) > 0): ?>
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

                <div class="row">
                    <div class="col-xl-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold" style="color: var(--primary);">Productos Más Vendidos</h6>
                                <button class="btn btn-sm btn-outline-primary no-print" onclick="window.location.href='exportar_excel.php?tipo=productos'">
                                    <i class="fas fa-download"></i> Excel
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Producto</th>
                                                <th>Cantidad</th>
                                                <th>Ingresos</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if ($result_productos_top->num_rows > 0):
                                                $posicion = 1;
                                                while ($row = $result_productos_top->fetch_assoc()):
                                            ?>
                                                    <tr>
                                                        <td>
                                                            <?php if ($posicion <= 3): ?>
                                                                <span class="badge bg-<?php echo $posicion == 1 ? 'warning' : ($posicion == 2 ? 'secondary' : 'info'); ?>">
                                                                    <?php echo $posicion; ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <?php echo $posicion; ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo $row['nombre']; ?></td>
                                                        <td><span class="badge bg-primary"><?php echo $row['vendido']; ?></span></td>
                                                        <td class="text-success fw-bold"><?php echo formatear_precio($row['ingresos']); ?></td>
                                                    </tr>
                                                <?php
                                                    $posicion++;
                                                endwhile;
                                            else:
                                                ?>
                                                <tr>
                                                    <td colspan="4" class="text-center text-muted py-4">
                                                        <i class="fas fa-box-open fa-2x mb-2"></i><br>
                                                        No hay datos disponibles
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold" style="color: var(--primary);">Métodos de Pago</h6>
                            </div>
                            <div class="card-body">
                                <?php if (count($metodos_pago) > 0): ?>
                                    <div class="chart-container mb-3">
                                        <canvas id="metodosPagoChart"></canvas>
                                    </div>
                                    <div class="mt-4">
                                        <?php
                                        $iconos = [
                                            'efectivo' => ['icon' => 'fa-money-bill-wave', 'color' => '#1cc88a'],
                                            'tarjeta' => ['icon' => 'fa-credit-card', 'color' => '#4e73df'],
                                            'transferencia' => ['icon' => 'fa-exchange-alt', 'color' => '#36b9cc']
                                        ];
                                        foreach ($metodos_pago as $metodo):
                                            $info = $iconos[$metodo['metodo_pago']] ?? ['icon' => 'fa-wallet', 'color' => '#858796'];
                                        ?>
                                            <div class="metodo-pago-item">
                                                <div class="metodo-icon" style="color: <?php echo $info['color']; ?>">
                                                    <i class="fas <?php echo $info['icon']; ?>"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between">
                                                        <span class="text-capitalize fw-bold"><?php echo $metodo['metodo_pago']; ?></span>
                                                        <span class="fw-bold" style="color: <?php echo $info['color']; ?>"><?php echo formatear_precio($metodo['monto']); ?></span>
                                                    </div>
                                                    <small class="text-muted"><?php echo $metodo['cantidad']; ?> transacciones</small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-wallet fa-3x text-gray-300 mb-3"></i>
                                        <p class="text-muted">No hay datos de métodos de pago disponibles</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
</div></div>

<?php
$inline_script = <<<'JS'
console.log('Inicializando reportes...');

        // Datos desde PHP
        const datosVentas = <?php echo json_encode($ventas_mes); ?>;
        const datosCategorias = <?php echo json_encode($ventas_categoria); ?>;
        const datosMetodos = <?php echo json_encode($metodos_pago); ?>;

        console.log('Datos ventas:', datosVentas);
        console.log('Datos categorías:', datosCategorias);
        console.log('Datos métodos:', datosMetodos);

        // Gráfico de Ventas por Mes
        if (datosVentas && datosVentas.length > 0) {
            const ctxVentasMes = document.getElementById('ventasMesChart');
            if (ctxVentasMes) {
                new Chart(ctxVentasMes, {
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
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return '$' + context.parsed.y.toLocaleString('es-AR', {
                                            minimumFractionDigits: 2
                                        });
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value.toLocaleString('es-AR');
                                    }
                                }
                            }
                        }
                    }
                });
                console.log('✓ Gráfico de ventas por mes creado');
            }
        }

        // Gráfico de Categorías
        if (datosCategorias && datosCategorias.length > 0) {
            const ctxCategorias = document.getElementById('categoriasChart');
            if (ctxCategorias) {
                new Chart(ctxCategorias, {
                    type: 'doughnut',
                    data: {
                        labels: datosCategorias.map(item => item.nombre),
                        datasets: [{
                            data: datosCategorias.map(item => parseFloat(item.total)),
                            backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'],
                            hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf', '#dda20a', '#be2617'],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((value / total) * 100).toFixed(1);
                                        return label + ': $' + value.toLocaleString('es-AR', {
                                            minimumFractionDigits: 2
                                        }) + ' (' + percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
                console.log('✓ Gráfico de categorías creado');
            }
        }

        // Gráfico de Métodos de Pago
        if (datosMetodos && datosMetodos.length > 0) {
            const ctxMetodos = document.getElementById('metodosPagoChart');
            if (ctxMetodos) {
                const colores = {
                    'efectivo': '#1cc88a',
                    'tarjeta': '#4e73df',
                    'transferencia': '#36b9cc'
                };

                new Chart(ctxMetodos, {
                    type: 'bar',
                    data: {
                        labels: datosMetodos.map(item => item.metodo_pago.charAt(0).toUpperCase() + item.metodo_pago.slice(1)),
                        datasets: [{
                            label: 'Monto Total',
                            data: datosMetodos.map(item => parseFloat(item.monto)),
                            backgroundColor: datosMetodos.map(item => colores[item.metodo_pago] || '#858796'),
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return '$' + context.parsed.y.toLocaleString('es-AR', {
                                            minimumFractionDigits: 2
                                        });
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value.toLocaleString('es-AR');
                                    }
                                }
                            }
                        }
                    }
                });
                console.log('✓ Gráfico de métodos de pago creado');
            }
        }

        console.log('✓ Todos los gráficos inicializados');
JS;
?>

<?php include "../includes/footer.php"; ?>