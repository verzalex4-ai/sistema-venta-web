<?php
require_once 'config.php';

// Verificar sesión
if (!esta_logueado()) {
    redirigir('login.php');
}

// Obtener clientes con saldos
$sql_clientes = "SELECT 
    c.id,
    c.nombre,
    c.apellido,
    c.dni,
    c.telefono,
    COALESCE(SUM(CASE WHEN cc.tipo_movimiento = 'debe' THEN cc.importe ELSE 0 END), 0) as total_debe,
    COALESCE(SUM(CASE WHEN cc.tipo_movimiento = 'haber' THEN cc.importe ELSE 0 END), 0) as total_haber,
    COALESCE(SUM(CASE WHEN cc.tipo_movimiento = 'debe' THEN cc.importe ELSE -cc.importe END), 0) as saldo_actual
FROM clientes c
LEFT JOIN cuentas_corrientes cc ON c.id = cc.cliente_id
WHERE c.estado = 1
GROUP BY c.id, c.nombre, c.apellido, c.dni, c.telefono
HAVING saldo_actual != 0 OR total_debe > 0 OR total_haber > 0
ORDER BY saldo_actual DESC";
$result_clientes = $conn->query($sql_clientes);

// Si se selecciona un cliente específico
$cliente_seleccionado = null;
$movimientos_cliente = [];
$saldo_total = 0;

if (isset($_GET['cliente_id'])) {
    $cliente_id = intval($_GET['cliente_id']);

    // Obtener datos del cliente
    $sql_cliente = "SELECT * FROM clientes WHERE id = ?";
    $stmt = $conn->prepare($sql_cliente);
    $stmt->bind_param("i", $cliente_id);
    $stmt->execute();
    $cliente_seleccionado = $stmt->get_result()->fetch_assoc();

    // Obtener movimientos del cliente
    $sql_movimientos = "SELECT cc.*, f.numero_factura, f.tipo as factura_tipo
                        FROM cuentas_corrientes cc
                        LEFT JOIN facturas f ON cc.factura_id = f.id
                        WHERE cc.cliente_id = ?
                        ORDER BY cc.fecha_movimiento DESC, cc.id DESC";
    $stmt_mov = $conn->prepare($sql_movimientos);
    $stmt_mov->bind_param("i", $cliente_id);
    $stmt_mov->execute();
    $result_movimientos = $stmt_mov->get_result();

    while ($row = $result_movimientos->fetch_assoc()) {
        $movimientos_cliente[] = $row;
    }

    // ⭐ OBTENER EL ÚLTIMO SALDO REGISTRADO
    $sql_ultimo_saldo = "SELECT saldo FROM cuentas_corrientes 
                         WHERE cliente_id = ? 
                         ORDER BY fecha_movimiento DESC, id DESC 
                         LIMIT 1";
    $stmt_saldo = $conn->prepare($sql_ultimo_saldo);
    $stmt_saldo->bind_param("i", $cliente_id);
    $stmt_saldo->execute();
    $result_saldo = $stmt_saldo->get_result();
    if ($result_saldo->num_rows > 0) {
        $saldo_total = $result_saldo->fetch_assoc()['saldo'];
    }
}

// Registrar pago
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'registrar_pago') {
        $cliente_id = intval($_POST['cliente_id']);
        $importe = floatval($_POST['importe']);
        $concepto = limpiar_entrada($_POST['concepto']);
        $fecha_movimiento = limpiar_entrada($_POST['fecha_movimiento']);
        $observaciones = limpiar_entrada($_POST['observaciones']);

        // Obtener saldo actual
        $sql_saldo = "SELECT COALESCE(SUM(CASE WHEN tipo_movimiento = 'debe' THEN importe ELSE -importe END), 0) as saldo
                      FROM cuentas_corrientes WHERE cliente_id = ?";
        $stmt_saldo = $conn->prepare($sql_saldo);
        $stmt_saldo->bind_param("i", $cliente_id);
        $stmt_saldo->execute();
        $saldo_actual = $stmt_saldo->get_result()->fetch_assoc()['saldo'];
        $nuevo_saldo = $saldo_actual - $importe;

        // ⭐ CORRECCIÓN: bind_param con 6 parámetros (isddss)
        $sql_pago = "INSERT INTO cuentas_corrientes (cliente_id, tipo_movimiento, concepto, importe, saldo, fecha_movimiento, observaciones, usuario_id) 
                     VALUES (?, 'haber', ?, ?, ?, ?, ?, 1)";
        $stmt_pago = $conn->prepare($sql_pago);
        $stmt_pago->bind_param("isddss", $cliente_id, $concepto, $importe, $nuevo_saldo, $fecha_movimiento, $observaciones);

        if ($stmt_pago->execute()) {
            $mensaje = "Pago registrado exitosamente";
            $tipo_mensaje = "success";
            header("Location: cuentas_corrientes.php?cliente_id=" . $cliente_id);
            exit();
        } else {
            $mensaje = "Error al registrar pago";
            $tipo_mensaje = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuentas Corrientes - Sistema de Ventas</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style-minimal.css">
    <style>
        body {
            background-color: var(--bg-secondary);
        }

        #wrapper {
            display: flex;
        }

        #sidebar-wrapper {
            min-height: 100vh;
            width: 224px;
            background: var(--primary-color);
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

        .table-responsive {
            max-height: 600px;
            overflow-y: auto;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        .badge {
            padding: 0.5em 0.75em;
        }

        .cliente-card {
            cursor: pointer;
            transition: all 0.3s;
        }

        .cliente-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.25);
        }

        .saldo-positivo {
            color: var(--danger);
        }

        .saldo-negativo {
            color: var(--success);
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
                <a class="nav-link" href="index.php">
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
            <li class="nav-item">
                <a class="nav-link" href="proveedores.php">
                    <i class="fas fa-fw fa-truck"></i><span>Proveedores</span>
                </a>
            </li>
            <hr class="sidebar-divider" style="border-color: rgba(255,255,255,.2)">
            <div class="sidebar-heading">Operaciones</div>
            <li class="nav-item">
                <a class="nav-link" href="ventas.php">
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
                <a class="nav-link active" href="cuentas_corrientes.php">
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
                <?php if (isset($mensaje)): ?>
                    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show">
                        <?php echo $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Cuentas Corrientes</h1>
                    <?php if ($cliente_seleccionado): ?>
                        <a href="cuentas_corrientes.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (!$cliente_seleccionado): ?>
                    <!-- Vista de Lista de Clientes -->
                    <div class="row">
                        <?php while ($cliente = $result_clientes->fetch_assoc()): ?>
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="card cliente-card" onclick="window.location.href='cuentas_corrientes.php?cliente_id=<?php echo $cliente['id']; ?>'">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo $cliente['nombre'] . ' ' . $cliente['apellido']; ?></h5>
                                        <p class="card-text">
                                            <small class="text-muted">DNI: <?php echo $cliente['dni']; ?></small><br>
                                            <small class="text-muted">Tel: <?php echo $cliente['telefono']; ?></small>
                                        </p>
                                        <hr>
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <small class="text-muted">Debe</small><br>
                                                <strong class="text-danger"><?php echo formatear_precio($cliente['total_debe']); ?></strong>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted">Haber</small><br>
                                                <strong class="text-success"><?php echo formatear_precio($cliente['total_haber']); ?></strong>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted">Saldo</small><br>
                                                <strong class="<?php echo $cliente['saldo_actual'] > 0 ? 'saldo-positivo' : 'saldo-negativo'; ?>">
                                                    <?php echo formatear_precio($cliente['saldo_actual']); ?>
                                                </strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>

                <?php else: ?>
                    <!-- Vista de Detalle de Cliente -->
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-body">
                                    <h4><?php echo $cliente_seleccionado['nombre'] . ' ' . $cliente_seleccionado['apellido']; ?></h4>
                                    <p class="mb-0">
                                        <strong>DNI:</strong> <?php echo $cliente_seleccionado['dni']; ?> |
                                        <strong>Email:</strong> <?php echo $cliente_seleccionado['email']; ?> |
                                        <strong>Teléfono:</strong> <?php echo $cliente_seleccionado['telefono']; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">SALDO ACTUAL</h6>
                                    <h2 class="<?php echo $saldo_total > 0 ? 'saldo-positivo' : 'saldo-negativo'; ?>">
                                        <?php echo formatear_precio($saldo_total); ?>
                                    </h2>
                                    <button class="btn btn-success w-100 mt-2" data-bs-toggle="modal" data-bs-target="#modalPago">
                                        <i class="fas fa-money-bill-wave"></i> Registrar Pago
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla de Movimientos -->
                    <div class="card">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold" style="color: var(--primary);">Movimientos de Cuenta</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Concepto</th>
                                            <th>Factura</th>
                                            <th>Debe</th>
                                            <th>Haber</th>
                                            <th>Saldo</th>
                                            <th>Observaciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($movimientos_cliente as $mov): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y', strtotime($mov['fecha_movimiento'])); ?></td>
                                                <td><?php echo $mov['concepto']; ?></td>
                                                <td>
                                                    <?php if ($mov['numero_factura']): ?>
                                                        <span class="badge bg-info">
                                                            <?php echo $mov['factura_tipo'] . ' ' . $mov['numero_factura']; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-danger fw-bold">
                                                    <?php echo $mov['tipo_movimiento'] === 'debe' ? formatear_precio($mov['importe']) : '-'; ?>
                                                </td>
                                                <td class="text-success fw-bold">
                                                    <?php echo $mov['tipo_movimiento'] === 'haber' ? formatear_precio($mov['importe']) : '-'; ?>
                                                </td>
                                                <td class="fw-bold <?php echo $mov['saldo'] > 0 ? 'saldo-positivo' : 'saldo-negativo'; ?>">
                                                    <?php echo formatear_precio($mov['saldo']); ?>
                                                </td>
                                                <td><?php echo $mov['observaciones'] ?? '-'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Registrar Pago -->
    <?php if ($cliente_seleccionado): ?>
        <div class="modal fade" id="modalPago" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Registrar Pago</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="accion" value="registrar_pago">
                            <input type="hidden" name="cliente_id" value="<?php echo $cliente_seleccionado['id']; ?>">

                            <div class="alert alert-info">
                                <strong>Cliente:</strong> <?php echo $cliente_seleccionado['nombre'] . ' ' . $cliente_seleccionado['apellido']; ?><br>
                                <strong>Saldo Actual:</strong> <?php echo formatear_precio($saldo_total); ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Importe del Pago</label>
                                <input type="number" step="0.01" class="form-control" name="importe" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Concepto</label>
                                <input type="text" class="form-control" name="concepto" value="Pago recibido" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Fecha de Pago</label>
                                <input type="date" class="form-control" name="fecha_movimiento" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Observaciones</label>
                                <textarea class="form-control" name="observaciones" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success">Registrar Pago</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>

</html>