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
    header("Location: facturas.php");
    exit();
}

// Obtener detalles de productos
$sql_detalles = "SELECT df.*, p.nombre as producto_nombre
                 FROM detalle_facturas df
                 LEFT JOIN productos p ON df.producto_id = p.id
                 WHERE df.factura_id = ?";
$stmt_det = $conn->prepare($sql_detalles);
$stmt_det->bind_param("i", $factura_id);
$stmt_det->execute();
$detalles = $stmt_det->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura <?php echo $factura['numero_factura']; ?> - Sistema de Ventas</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4e73df;
            --success: #1cc88a;
        }

        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f8f9fc;
        }

        .factura-container {
            max-width: 900px;
            margin: 2rem auto;
            background: white;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .factura-header {
            background: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
            color: white;
            padding: 2rem;
            border-radius: 0.35rem;
            margin-bottom: 2rem;
        }

        .factura-tipo {
            font-size: 3rem;
            font-weight: bold;
            border: 3px solid white;
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.5rem;
        }

        .info-section {
            background-color: #f8f9fc;
            padding: 1rem;
            border-radius: 0.35rem;
            margin-bottom: 1rem;
        }

        .total-box {
            background-color: #f8f9fc;
            padding: 1.5rem;
            border-radius: 0.35rem;
            text-align: right;
        }

        .total-amount {
            font-size: 2rem;
            font-weight: bold;
            color: var(--success);
        }

        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white;
            }
            .factura-container {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="factura-container">
        <!-- Botones de acción -->
        <div class="d-flex justify-content-between mb-3 no-print">
            <a href="facturas.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
            <div class="btn-group">
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print"></i> Imprimir
                </button>
                <button onclick="window.location.href='generar_pdf_factura.php?id=<?php echo $factura_id; ?>'" class="btn btn-danger">
                    <i class="fas fa-file-pdf"></i> Descargar PDF
                </button>
            </div>
        </div>

        <!-- Encabezado de la factura -->
        <div class="factura-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-0">
                        <i class="fas fa-file-invoice"></i> 
                        <?php echo strtoupper($factura['tipo_comprobante']); ?>
                    </h1>
                    <h4><?php echo $factura['numero_factura']; ?></h4>
                    <p class="mb-0">
                        <i class="fas fa-calendar"></i> 
                        Fecha de Emisión: <?php echo date('d/m/Y', strtotime($factura['fecha_emision'])); ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="factura-tipo">
                        <?php echo $factura['tipo']; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información del emisor y cliente -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="info-section">
                    <h6 class="text-primary mb-3"><i class="fas fa-building"></i> EMISOR</h6>
                    <p class="mb-1"><strong>Sistema de Ventas</strong></p>
                    <p class="mb-1">CUIT: 20-12345678-9</p>
                    <p class="mb-1">Dirección: Av. Principal 123</p>
                    <p class="mb-1">Tel: (387) 555-1234</p>
                    <p class="mb-0">Email: ventas@sistema.com</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="info-section">
                    <h6 class="text-primary mb-3"><i class="fas fa-user"></i> CLIENTE</h6>
                    <p class="mb-1">
                        <strong><?php echo $factura['cliente_nombre'] . ' ' . $factura['cliente_apellido']; ?></strong>
                    </p>
                    <p class="mb-1">DNI: <?php echo $factura['dni']; ?></p>
                    <?php if ($factura['email']): ?>
                        <p class="mb-1">Email: <?php echo $factura['email']; ?></p>
                    <?php endif; ?>
                    <?php if ($factura['telefono']): ?>
                        <p class="mb-1">Tel: <?php echo $factura['telefono']; ?></p>
                    <?php endif; ?>
                    <?php if ($factura['direccion']): ?>
                        <p class="mb-0">Dir: <?php echo $factura['direccion']; ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Información adicional -->
        <div class="row mb-4">
            <div class="col-md-6">
                <p class="mb-1"><strong>Fecha de Vencimiento:</strong> 
                    <?php echo $factura['fecha_vencimiento'] ? date('d/m/Y', strtotime($factura['fecha_vencimiento'])) : 'N/A'; ?>
                </p>
            </div>
            <div class="col-md-6">
                <p class="mb-1"><strong>Estado:</strong> 
                    <span class="badge bg-<?php 
                        echo $factura['estado'] == 'pagada' ? 'success' : 
                            ($factura['estado'] == 'pendiente' ? 'warning' : 'danger'); 
                    ?>">
                        <?php echo ucfirst($factura['estado']); ?>
                    </span>
                </p>
            </div>
        </div>

        <!-- Detalle de items -->
        <h6 class="text-primary mb-3"><i class="fas fa-list"></i> DETALLE</h6>
        <div class="table-responsive mb-4">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Descripción</th>
                        <th class="text-center" width="100">Cantidad</th>
                        <th class="text-end" width="150">Precio Unit.</th>
                        <th class="text-end" width="150">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $subtotal_total = 0;
                    while ($detalle = $detalles->fetch_assoc()): 
                        $subtotal_total += $detalle['subtotal'];
                    ?>
                        <tr>
                            <td><?php echo $detalle['descripcion']; ?></td>
                            <td class="text-center"><?php echo $detalle['cantidad']; ?></td>
                            <td class="text-end"><?php echo formatear_precio($detalle['precio_unitario']); ?></td>
                            <td class="text-end"><?php echo formatear_precio($detalle['subtotal']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Totales -->
        <div class="row">
            <div class="col-md-6">
                <?php if ($factura['observaciones']): ?>
                    <div class="info-section">
                        <h6 class="text-primary mb-2"><i class="fas fa-comment"></i> OBSERVACIONES</h6>
                        <p class="mb-0"><?php echo $factura['observaciones']; ?></p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <div class="total-box">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <strong><?php echo formatear_precio($factura['subtotal']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>IVA (21%):</span>
                        <strong><?php echo formatear_precio($factura['iva']); ?></strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <h5>TOTAL:</h5>
                        <h3 class="total-amount"><?php echo formatear_precio($factura['total']); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pie de factura -->
        <div class="text-center mt-5 pt-4" style="border-top: 2px solid #e3e6f0;">
            <p class="text-muted mb-1">
                <small>Documento no válido como factura fiscal - Sistema de gestión interno</small>
            </p>
            <p class="text-muted mb-0">
                <small>Usuario: <?php echo $factura['usuario_nombre']; ?> | 
                Fecha de emisión: <?php echo date('d/m/Y H:i', strtotime($factura['fecha_creacion'])); ?></small>
            </p>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>