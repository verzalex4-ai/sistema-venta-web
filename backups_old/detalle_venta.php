<?php
require_once 'config.php';

$venta_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Obtener datos de la venta
$sql_venta = "SELECT v.*, CONCAT(c.nombre, ' ', c.apellido) as cliente_nombre,
              c.dni, c.email, c.telefono, c.direccion,
              u.nombre as vendedor
              FROM ventas v
              INNER JOIN clientes c ON v.cliente_id = c.id
              INNER JOIN usuarios u ON v.usuario_id = u.id
              WHERE v.id = ?";
$stmt = $conn->prepare($sql_venta);
$stmt->bind_param("i", $venta_id);
$stmt->execute();
$venta = $stmt->get_result()->fetch_assoc();

if (!$venta) {
    header("Location: ventas.php");
    exit();
}

// Obtener detalles de productos
$sql_detalles = "SELECT dv.*, p.nombre as producto_nombre
                 FROM detalle_ventas dv
                 INNER JOIN productos p ON dv.producto_id = p.id
                 WHERE dv.venta_id = ?";
$stmt_det = $conn->prepare($sql_detalles);
$stmt_det->bind_param("i", $venta_id);
$stmt_det->execute();
$detalles = $stmt_det->get_result();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Venta #<?php echo $venta_id; ?> - Sistema de Ventas</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4e73df;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
        }

        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f8f9fc;
        }

        #wrapper {
            display: flex;
        }

        #sidebar-wrapper {
            min-height: 100vh;
            width: 224px;
            background: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
        }

        .sidebar-brand {
            height: 4.375rem;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 800;
            padding: 1.5rem 1rem;
            text-align: center;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 1rem;
            color: rgba(255, 255, 255, .8);
            text-decoration: none;
            transition: all 0.3s;
        }

        .nav-link:hover,
        .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, .1);
        }

        .nav-link i {
            width: 2rem;
            font-size: 0.85rem;
        }

        .sidebar-heading {
            color: rgba(255, 255, 255, .5);
            padding: 0 1rem;
            font-size: 0.65rem;
            text-transform: uppercase;
            margin-top: 0.5rem;
        }

        #content-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            height: 4.375rem;
            background-color: #fff;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .card {
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }

        .invoice-header {
            background: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
            color: white;
            padding: 2rem;
            border-radius: 0.35rem 0.35rem 0 0;
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

            #sidebar-wrapper,
            .topbar,
            .no-print {
                display: none !important;
            }

            #content-wrapper {
                margin: 0 !important;
            }

            .card {
                box-shadow: none !important;
            }
        }
    </style>
</head>

<body>
    <div id="wrapper">
        <!-- Sidebar -->
        <ul class="navbar-nav" id="sidebar-wrapper">
            <a class="sidebar-brand" href="index.php">
                <div class="sidebar-brand-icon"><i class="fas fa-shopping-cart"></i></div>
                <div class="sidebar-brand-text mx-3">VENTAS</div>
            </a>
            <hr class="sidebar-divider my-0" style="border-color: rgba(255,255,255,.2)">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span>
                </a>
            </li>
            <hr class="sidebar-divider" style="border-color: rgba(255,255,255,.2)">
            <div class="sidebar-heading">Gestión</div>
            <li class="nav-item">
                <a class="nav-link" href="productos.php">
                    <i class="fas fa-fw fa-box"></i><span>Productos</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="clientes.php">
                    <i class="fas fa-fw fa-users"></i><span>Clientes</span>
                </a>
            </li>
            
            <hr class="sidebar-divider" style="border-color: rgba(255,255,255,.2)">
            <div class="sidebar-heading">Operaciones</div>
            <li class="nav-item">
                <a class="nav-link active" href="ventas.php">
                    <i class="fas fa-fw fa-cash-register"></i><span>Ventas</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="facturas.php">
                    <i class="fas fa-fw fa-file-invoice"></i><span>Facturas</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="caja.php">
                    <i class="fas fa-fw fa-money-bill-wave"></i><span>Caja</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="cuentas_corrientes.php">
                    <i class="fas fa-fw fa-file-invoice-dollar"></i><span>Cuentas Corrientes</span>
                </a>
            </li>
            <hr class="sidebar-divider" style="border-color: rgba(255,255,255,.2)">
            <li class="nav-item">
                <a class="nav-link" href="reportes.php">
                    <i class="fas fa-fw fa-chart-area"></i><span>Reportes</span>
                </a>
            </li>
            <hr class="sidebar-divider" style="border-color: rgba(255,255,255,.2)">
            <li class="nav-item">
                <a class="nav-link" href="cerrar_sesion.php">
                    <i class="fas fa-fw fa-sign-out-alt"></i><span>Cerrar Sesión</span>
                </a>
            </li>
        </ul>

        <!-- Content -->
        <div id="content-wrapper">
            <nav class="navbar navbar-expand topbar mb-4 static-top">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="fas fa-user-circle fa-2x" style="color: #858796;"></i>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="container-fluid">
                <div class="d-sm-flex align-items-center justify-content-between mb-4 no-print">
                    <h1 class="h3 mb-0 text-gray-800">Detalle de Venta</h1>
                    <div class="btn-group">
                        <a href="ventas.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                        <button onclick="window.print()" class="btn btn-primary">
                            <i class="fas fa-print"></i> Imprimir
                        </button>
                    </div>
                </div>

                <div class="card">
                    <div class="invoice-header">
                        <div class="row">
                            <div class="col-md-6">
                                <h2><i class="fas fa-shopping-cart"></i> VENTA #<?php echo str_pad($venta_id, 6, '0', STR_PAD_LEFT); ?></h2>
                                <p class="mb-0">
                                    <i class="fas fa-calendar"></i> <?php echo formatear_fecha($venta['fecha_creacion']); ?>
                                </p>
                            </div>
                            <div class="col-md-6 text-end">
                                <h4>SISTEMA DE VENTAS</h4>
                                <p class="mb-0">
                                    <small>Vendedor: <?php echo $venta['vendedor']; ?></small>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- Información del Cliente -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5 class="text-primary"><i class="fas fa-user"></i> Información del Cliente</h5>
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Nombre:</strong></td>
                                        <td><?php echo $venta['cliente_nombre']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>DNI:</strong></td>
                                        <td><?php echo $venta['dni']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Email:</strong></td>
                                        <td><?php echo $venta['email'] ?? '-'; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Teléfono:</strong></td>
                                        <td><?php echo $venta['telefono'] ?? '-'; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Dirección:</strong></td>
                                        <td><?php echo $venta['direccion'] ?? '-'; ?></td>
                                    </tr>
                                </table>
                            </div>

                            <div class="col-md-6">
                                <h5 class="text-primary"><i class="fas fa-info-circle"></i> Información de la Venta</h5>
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Método de Pago:</strong></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php
                                                $iconos_pago = [
                                                    'efectivo' => 'fa-money-bill-wave',
                                                    'tarjeta' => 'fa-credit-card',
                                                    'transferencia' => 'fa-exchange-alt'
                                                ];
                                                echo '<i class="fas ' . ($iconos_pago[$venta['metodo_pago']] ?? 'fa-wallet') . '"></i> ';
                                                echo ucfirst($venta['metodo_pago']);
                                                ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Estado:</strong></td>
                                        <td>
                                            <?php
                                            $badge_color = 'success';
                                            switch ($venta['estado']) {
                                                case 'pendiente':
                                                    $badge_color = 'warning';
                                                    break;
                                                case 'cancelada':
                                                    $badge_color = 'danger';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $badge_color; ?>">
                                                <?php echo ucfirst($venta['estado']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Detalle de Productos -->
                        <h5 class="text-primary mb-3"><i class="fas fa-box"></i> Productos</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Producto</th>
                                        <th class="text-center">Cantidad</th>
                                        <th class="text-end">Precio Unitario</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $subtotal_total = 0;
                                    while ($detalle = $detalles->fetch_assoc()):
                                        $subtotal_total += $detalle['subtotal'];
                                    ?>
                                        <tr>
                                            <td><?php echo $detalle['producto_nombre']; ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-primary"><?php echo $detalle['cantidad']; ?></span>
                                            </td>
                                            <td class="text-end"><?php echo formatear_precio($detalle['precio_unitario']); ?></td>
                                            <td class="text-end fw-bold text-success">
                                                <?php echo formatear_precio($detalle['subtotal']); ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Total -->
                        <div class="row mt-4">
                            <div class="col-md-8"></div>
                            <div class="col-md-4">
                                <div class="total-box">
                                    <h6 class="text-muted">TOTAL A PAGAR</h6>
                                    <div class="total-amount">
                                        <?php echo formatear_precio($venta['total']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notas adicionales -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Nota:</strong> Esta es una copia digital de la venta realizada.
                                    Para cualquier consulta o reclamo, comunicarse con el área de ventas.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>

</html>