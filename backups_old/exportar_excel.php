<?php
require_once 'config.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');

// Configurar headers para descarga de Excel
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="reporte_ventas_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Obtener tipo de reporte
$tipo_reporte = isset($_GET['tipo']) ? $_GET['tipo'] : 'general';

?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #4e73df;
            color: white;
            font-weight: bold;
        }

        .total-row {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        .titulo {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .fecha {
            color: #666;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="titulo">REPORTE DE VENTAS - SISTEMA DE VENTAS</div>
    <div class="fecha">Generado el: <?php echo date('d/m/Y H:i:s'); ?></div>
    <br>

    <?php if ($tipo_reporte === 'general' || $tipo_reporte === 'ventas'): ?>
        <!-- Reporte de Ventas -->
        <h2>Historial de Ventas</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Fecha</th>
                    <th>Método de Pago</th>
                    <th>Total</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql_ventas = "SELECT v.*, CONCAT(c.nombre, ' ', c.apellido) as cliente 
                               FROM ventas v 
                               INNER JOIN clientes c ON v.cliente_id = c.id 
                               ORDER BY v.fecha_venta DESC";
                $result_ventas = $conn->query($sql_ventas);
                $total_general = 0;

                while ($venta = $result_ventas->fetch_assoc()):
                    $total_general += $venta['total'];
                ?>
                    <tr>
                        <td><?php echo $venta['id']; ?></td>
                        <td><?php echo $venta['cliente']; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($venta['fecha_venta'])); ?></td>
                        <td><?php echo ucfirst($venta['metodo_pago']); ?></td>
                        <td><?php echo number_format($venta['total'], 2, ',', '.'); ?></td>
                        <td><?php echo ucfirst($venta['estado']); ?></td>
                    </tr>
                <?php endwhile; ?>
                <tr class="total-row">
                    <td colspan="4" style="text-align: right;">TOTAL GENERAL:</td>
                    <td><?php echo number_format($total_general, 2, ',', '.'); ?></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
        <br><br>
    <?php endif; ?>

    <?php if ($tipo_reporte === 'general' || $tipo_reporte === 'productos'): ?>
        <!-- Productos Más Vendidos -->
        <h2>Productos Más Vendidos</h2>
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Cantidad Vendida</th>
                    <th>Ingresos Generados</th>
                    <th>Stock Actual</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql_productos = "SELECT p.nombre, p.stock,
                                  SUM(dv.cantidad) as vendido, 
                                  SUM(dv.subtotal) as ingresos
                                  FROM detalle_ventas dv
                                  INNER JOIN productos p ON dv.producto_id = p.id
                                  INNER JOIN ventas v ON dv.venta_id = v.id
                                  WHERE v.estado='completada'
                                  GROUP BY p.id
                                  ORDER BY vendido DESC";
                $result_productos = $conn->query($sql_productos);

                while ($prod = $result_productos->fetch_assoc()):
                ?>
                    <tr>
                        <td><?php echo $prod['nombre']; ?></td>
                        <td><?php echo $prod['vendido']; ?></td>
                        <td><?php echo number_format($prod['ingresos'], 2, ',', '.'); ?></td>
                        <td><?php echo $prod['stock']; ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <br><br>
    <?php endif; ?>

    <?php if ($tipo_reporte === 'general' || $tipo_reporte === 'categorias'): ?>
        <!-- Ventas por Categoría -->
        <h2>Ventas por Categoría</h2>
        <table>
            <thead>
                <tr>
                    <th>Categoría</th>
                    <th>Total Ventas</th>
                    <th>Porcentaje</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql_cat = "SELECT c.nombre, SUM(dv.subtotal) as total
                            FROM detalle_ventas dv
                            INNER JOIN productos p ON dv.producto_id = p.id
                            INNER JOIN categorias c ON p.categoria_id = c.id
                            INNER JOIN ventas v ON dv.venta_id = v.id
                            WHERE v.estado='completada'
                            GROUP BY c.id
                            ORDER BY total DESC";
                $result_cat = $conn->query($sql_cat);

                // Calcular total
                $total_cat = 0;
                $categorias = [];
                while ($row = $result_cat->fetch_assoc()) {
                    $categorias[] = $row;
                    $total_cat += $row['total'];
                }

                // Mostrar con porcentajes
                foreach ($categorias as $cat):
                    $porcentaje = ($total_cat > 0) ? ($cat['total'] / $total_cat * 100) : 0;
                ?>
                    <tr>
                        <td><?php echo $cat['nombre']; ?></td>
                        <td><?php echo number_format($cat['total'], 2, ',', '.'); ?></td>
                        <td><?php echo number_format($porcentaje, 1); ?>%</td>
                    </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td>TOTAL:</td>
                    <td><?php echo number_format($total_cat, 2, ',', '.'); ?></td>
                    <td>100%</td>
                </tr>
            </tbody>
        </table>
        <br><br>
    <?php endif; ?>

    <?php if ($tipo_reporte === 'general' || $tipo_reporte === 'clientes'): ?>
        <!-- Top Clientes -->
        <h2>Top 10 Clientes</h2>
        <table>
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>DNI</th>
                    <th>Total Compras</th>
                    <th>Cantidad de Compras</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql_clientes = "SELECT CONCAT(c.nombre, ' ', c.apellido) as cliente,
                                 c.dni,
                                 COUNT(v.id) as cantidad_compras,
                                 SUM(v.total) as total_compras
                                 FROM ventas v
                                 INNER JOIN clientes c ON v.cliente_id = c.id
                                 WHERE v.estado='completada'
                                 GROUP BY c.id
                                 ORDER BY total_compras DESC
                                 LIMIT 10";
                $result_clientes = $conn->query($sql_clientes);

                while ($cliente = $result_clientes->fetch_assoc()):
                ?>
                    <tr>
                        <td><?php echo $cliente['cliente']; ?></td>
                        <td><?php echo $cliente['dni']; ?></td>
                        <td><?php echo number_format($cliente['total_compras'], 2, ',', '.'); ?></td>
                        <td><?php echo $cliente['cantidad_compras']; ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <br><br>
    <?php endif; ?>

    <?php if ($tipo_reporte === 'general' || $tipo_reporte === 'caja'): ?>
        <!-- Movimientos de Caja -->
        <h2>Resumen de Caja (Últimos 30 días)</h2>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Ingresos</th>
                    <th>Egresos</th>
                    <th>Saldo del Día</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql_caja = "SELECT fecha_movimiento,
                             SUM(CASE WHEN tipo='ingreso' THEN importe ELSE 0 END) as ingresos,
                             SUM(CASE WHEN tipo='egreso' THEN importe ELSE 0 END) as egresos,
                             SUM(CASE WHEN tipo='ingreso' THEN importe ELSE -importe END) as saldo
                             FROM movimientos_caja
                             WHERE fecha_movimiento >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                             GROUP BY fecha_movimiento
                             ORDER BY fecha_movimiento DESC";
                $result_caja = $conn->query($sql_caja);

                $total_ingresos = 0;
                $total_egresos = 0;

                while ($caja = $result_caja->fetch_assoc()):
                    $total_ingresos += $caja['ingresos'];
                    $total_egresos += $caja['egresos'];
                ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($caja['fecha_movimiento'])); ?></td>
                        <td><?php echo number_format($caja['ingresos'], 2, ',', '.'); ?></td>
                        <td><?php echo number_format($caja['egresos'], 2, ',', '.'); ?></td>
                        <td><?php echo number_format($caja['saldo'], 2, ',', '.'); ?></td>
                    </tr>
                <?php endwhile; ?>
                <tr class="total-row">
                    <td>TOTALES:</td>
                    <td><?php echo number_format($total_ingresos, 2, ',', '.'); ?></td>
                    <td><?php echo number_format($total_egresos, 2, ',', '.'); ?></td>
                    <td><?php echo number_format($total_ingresos - $total_egresos, 2, ',', '.'); ?></td>
                </tr>
            </tbody>
        </table>
    <?php endif; ?>

    <br><br>
    <p style="color: #666; font-size: 12px;">
        <strong>Generado por:</strong> Sistema de Ventas<br>
        <strong>Fecha y Hora:</strong> <?php echo date('d/m/Y H:i:s'); ?>
    </p>
</body>

</html>
<?php
$conn->close();
?>