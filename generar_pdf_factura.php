<?php
require_once 'config.php';

$factura_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Obtener datos de la factura
$sql_factura = "SELECT f.*, c.nombre as cliente_nombre, c.apellido as cliente_apellido,
                c.dni, c.email, c.telefono, c.direccion,
                u.nombre as usuario_nombre
                FROM facturas f
                INNER JOIN clientes c ON f.cliente_id = c.id
                INNER JOIN usuarios u ON f.usuario_id = u.id
                WHERE f.id = ?";
$stmt = $conn->prepare($sql_factura);
$stmt->bind_param("i", $factura_id);
$stmt->execute();
$factura = $stmt->get_result()->fetch_assoc();

if (!$factura) {
    die('Factura no encontrada');
}

// Obtener detalles
$sql_detalles = "SELECT df.*, p.nombre as producto_nombre
                 FROM detalle_facturas df
                 LEFT JOIN productos p ON df.producto_id = p.id
                 WHERE df.factura_id = ?";
$stmt_det = $conn->prepare($sql_detalles);
$stmt_det->bind_param("i", $factura_id);
$stmt_det->execute();
$detalles = $stmt_det->get_result();

// Configurar headers para PDF (usando HTML como alternativa)
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura <?php echo $factura['numero_factura']; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
        }

        .header {
            background: linear-gradient(135deg, #4e73df, #224abe);
            color: white;
            padding: 20px;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .header p {
            margin: 5px 0;
        }

        .factura-tipo {
            font-size: 48px;
            font-weight: bold;
            border: 3px solid white;
            width: 80px;
            height: 80px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            float: right;
        }

        .info-box {
            background: #f8f9fc;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #4e73df;
        }

        .info-box h3 {
            color: #4e73df;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .info-box p {
            margin: 5px 0;
            line-height: 1.6;
        }

        .row {
            display: flex;
            margin-bottom: 15px;
        }

        .col-6 {
            flex: 0 0 50%;
            padding: 0 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        table th {
            background: #f8f9fc;
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
            font-weight: bold;
        }

        table td {
            padding: 10px;
            border: 1px solid #ddd;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .totales {
            float: right;
            width: 300px;
            background: #f8f9fc;
            padding: 15px;
            margin-top: 20px;
        }

        .totales .row-total {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .total-final {
            border-top: 2px solid #4e73df;
            padding-top: 10px;
            margin-top: 10px;
        }

        .total-final .amount {
            font-size: 24px;
            font-weight: bold;
            color: #1cc88a;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e3e6f0;
            color: #999;
            font-size: 11px;
        }

        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }

        .badge-success {
            background: #1cc88a;
            color: white;
        }

        .badge-warning {
            background: #f6c23e;
            color: #333;
        }

        .badge-danger {
            background: #e74a3b;
            color: white;
        }

        @media print {
            body {
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }

        @page {
            margin: 1cm;
        }
    </style>
</head>
<body onload="window.print();">
    <div class="container">
        <!-- Encabezado -->
        <div class="header">
            <div class="factura-tipo"><?php echo $factura['tipo']; ?></div>
            <h1>
                <?php echo strtoupper($factura['tipo_comprobante']); ?>
            </h1>
            <p><strong><?php echo $factura['numero_factura']; ?></strong></p>
            <p>Fecha de Emisión: <?php echo date('d/m/Y', strtotime($factura['fecha_emision'])); ?></p>
        </div>

        <!-- Información del emisor y cliente -->
        <div class="row">
            <div class="col-6">
                <div class="info-box">
                    <h3>EMISOR</h3>
                    <p><strong>Sistema de Ventas</strong></p>
                    <p>CUIT: 20-12345678-9</p>
                    <p>Dirección: Av. Principal 123</p>
                    <p>Teléfono: (387) 555-1234</p>
                    <p>Email: ventas@sistema.com</p>
                </div>
            </div>
            <div class="col-6">
                <div class="info-box">
                    <h3>CLIENTE</h3>
                    <p><strong><?php echo $factura['cliente_nombre'] . ' ' . $factura['cliente_apellido']; ?></strong></p>
                    <p>DNI: <?php echo $factura['dni']; ?></p>
                    <?php if ($factura['email']): ?>
                        <p>Email: <?php echo $factura['email']; ?></p>
                    <?php endif; ?>
                    <?php if ($factura['telefono']): ?>
                        <p>Tel: <?php echo $factura['telefono']; ?></p>
                    <?php endif; ?>
                    <?php if ($factura['direccion']): ?>
                        <p>Dirección: <?php echo $factura['direccion']; ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Información adicional -->
        <div class="row">
            <div class="col-6">
                <p><strong>Fecha de Vencimiento:</strong> 
                    <?php echo $factura['fecha_vencimiento'] ? date('d/m/Y', strtotime($factura['fecha_vencimiento'])) : 'N/A'; ?>
                </p>
            </div>
            <div class="col-6">
                <p><strong>Estado:</strong> 
                    <span class="badge badge-<?php 
                        echo $factura['estado'] == 'pagada' ? 'success' : 
                            ($factura['estado'] == 'pendiente' ? 'warning' : 'danger'); 
                    ?>">
                        <?php echo strtoupper($factura['estado']); ?>
                    </span>
                </p>
            </div>
        </div>

        <!-- Tabla de productos -->
        <table>
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th class="text-center" width="80">Cantidad</th>
                    <th class="text-right" width="120">Precio Unit.</th>
                    <th class="text-right" width="120">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($detalle = $detalles->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $detalle['descripcion']; ?></td>
                        <td class="text-center"><?php echo $detalle['cantidad']; ?></td>
                        <td class="text-right"><?php echo formatear_precio($detalle['precio_unitario']); ?></td>
                        <td class="text-right"><?php echo formatear_precio($detalle['subtotal']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- Observaciones y totales -->
        <div style="clear: both;">
            <?php if ($factura['observaciones']): ?>
                <div class="info-box" style="width: 400px; float: left;">
                    <h3>OBSERVACIONES</h3>
                    <p><?php echo $factura['observaciones']; ?></p>
                </div>
            <?php endif; ?>

            <div class="totales">
                <div class="row-total">
                    <span>Subtotal:</span>
                    <strong><?php echo formatear_precio($factura['subtotal']); ?></strong>
                </div>
                <div class="row-total">
                    <span>IVA (21%):</span>
                    <strong><?php echo formatear_precio($factura['iva']); ?></strong>
                </div>
                <div class="row-total total-final">
                    <span style="font-size: 18px;"><strong>TOTAL:</strong></span>
                    <span class="amount"><?php echo formatear_precio($factura['total']); ?></span>
                </div>
            </div>
        </div>

        <!-- Pie de página -->
        <div class="footer" style="clear: both;">
            <p>Documento no válido como factura fiscal - Sistema de gestión interno</p>
            <p>Usuario: <?php echo $factura['usuario_nombre']; ?> | 
            Generado: <?php echo date('d/m/Y H:i'); ?></p>
        </div>
    </div>
</body>
</html>