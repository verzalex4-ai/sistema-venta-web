<?php
require_once 'config.php';

// Verificar permisos (admin y vendedor pueden acceder)
requiere_permiso('caja');

// Procesar movimiento de caja
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'nuevo_movimiento') {
        $tipo = limpiar_entrada($_POST['tipo']);
        $concepto = limpiar_entrada($_POST['concepto']);
        $categoria = limpiar_entrada($_POST['categoria']);
        $importe = floatval($_POST['importe']);
        $metodo_pago = limpiar_entrada($_POST['metodo_pago']);
        $referencia = limpiar_entrada($_POST['referencia']);
        $fecha_movimiento = limpiar_entrada($_POST['fecha_movimiento']);
        $observaciones = limpiar_entrada($_POST['observaciones']);

        $sql = "INSERT INTO movimientos_caja (tipo, concepto, categoria, importe, metodo_pago, referencia, fecha_movimiento, observaciones, usuario_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssdssss", $tipo, $concepto, $categoria, $importe, $metodo_pago, $referencia, $fecha_movimiento, $observaciones);

        if ($stmt->execute()) {
            $mensaje = "Movimiento registrado exitosamente";
            $tipo_mensaje = "success";
        } else {
            $mensaje = "Error al registrar movimiento";
            $tipo_mensaje = "danger";
        }
    }
}

// Obtener fecha seleccionada
$fecha_filtro = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

// Obtener movimientos del día
$sql_movimientos = "SELECT * FROM movimientos_caja WHERE fecha_movimiento = ? ORDER BY id DESC";
$stmt = $conn->prepare($sql_movimientos);
$stmt->bind_param("s", $fecha_filtro);
$stmt->execute();
$result_movimientos = $stmt->get_result();

// Calcular totales del día
$sql_totales = "SELECT 
                SUM(CASE WHEN tipo = 'ingreso' THEN importe ELSE 0 END) as total_ingresos,
                SUM(CASE WHEN tipo = 'egreso' THEN importe ELSE 0 END) as total_egresos,
                SUM(CASE WHEN tipo = 'ingreso' THEN importe ELSE -importe END) as saldo
                FROM movimientos_caja WHERE fecha_movimiento = ?";
$stmt_totales = $conn->prepare($sql_totales);
$stmt_totales->bind_param("s", $fecha_filtro);
$stmt_totales->execute();
$totales = $stmt_totales->get_result()->fetch_assoc();

// Obtener saldo acumulado hasta la fecha
$sql_acumulado = "SELECT SUM(CASE WHEN tipo = 'ingreso' THEN importe ELSE -importe END) as saldo_acumulado
                  FROM movimientos_caja WHERE fecha_movimiento <= ?";
$stmt_acum = $conn->prepare($sql_acumulado);
$stmt_acum->bind_param("s", $fecha_filtro);
$stmt_acum->execute();
$saldo_acumulado = $stmt_acum->get_result()->fetch_assoc()['saldo_acumulado'] ?? 0;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caja - Sistema de Ventas</title>
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

        .border-left-success {
            border-left: 0.25rem solid var(--success) !important;
        }

        .border-left-danger {
            border-left: 0.25rem solid var(--danger) !important;
        }

        .border-left-info {
            border-left: 0.25rem solid var(--info) !important;
        }

        .text-xs {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .h5 {
            font-size: 1.25rem;
            font-weight: 700;
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
                <a class="nav-link active" href="caja.php">
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
                <?php if (isset($mensaje)): ?>
                    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show">
                        <?php echo $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Control de Caja</h1>
                    <div>
                        <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#modalMovimiento" onclick="setTipoMovimiento('ingreso')">
                            <i class="fas fa-plus"></i> Ingreso
                        </button>
                        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalMovimiento" onclick="setTipoMovimiento('egreso')">
                            <i class="fas fa-minus"></i> Egreso
                        </button>
                    </div>
                </div>

                <!-- Tarjetas de Resumen -->
                <div class="row">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Ingresos del Día</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo formatear_precio($totales['total_ingresos'] ?? 0); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-arrow-up fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-danger shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Egresos del Día</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo formatear_precio($totales['total_egresos'] ?? 0); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-arrow-down fa-2x text-gray-300"></i>
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
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Saldo del Día</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo formatear_precio($totales['saldo'] ?? 0); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-balance-scale fa-2x text-gray-300"></i>
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
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Saldo Acumulado</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo formatear_precio($saldo_acumulado); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-wallet fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtro de Fecha -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-auto">
                                <label class="form-label">Fecha:</label>
                            </div>
                            <div class="col-auto">
                                <input type="date" class="form-control" name="fecha" value="<?php echo $fecha_filtro; ?>">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary">Filtrar</button>
                                <a href="caja.php" class="btn btn-secondary">Hoy</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabla de Movimientos -->
                <div class="card">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold" style="color: var(--primary);">
                            Movimientos del <?php echo date('d/m/Y', strtotime($fecha_filtro)); ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Concepto</th>
                                        <th>Categoría</th>
                                        <th>Importe</th>
                                        <th>Método</th>
                                        <th>Referencia</th>
                                        <th>Observaciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result_movimientos->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <?php if ($row['tipo'] === 'ingreso'): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-arrow-up"></i> Ingreso
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-arrow-down"></i> Egreso
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $row['concepto']; ?></td>
                                            <td><span class="badge bg-secondary"><?php echo ucfirst(str_replace('_', ' ', $row['categoria'])); ?></span></td>
                                            <td class="fw-bold <?php echo $row['tipo'] === 'ingreso' ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo formatear_precio($row['importe']); ?>
                                            </td>
                                            <td><?php echo ucfirst($row['metodo_pago']); ?></td>
                                            <td><?php echo $row['referencia'] ?? '-'; ?></td>
                                            <td><?php echo $row['observaciones'] ?? '-'; ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Movimiento -->
    <div class="modal fade" id="modalMovimiento" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nuevo Movimiento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formMovimiento">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="nuevo_movimiento">
                        <input type="hidden" name="tipo" id="tipo_movimiento">

                        <div class="mb-3">
                            <label class="form-label">Concepto</label>
                            <input type="text" class="form-control" name="concepto" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Categoría</label>
                            <select class="form-select" name="categoria" required>
                                <option value="venta">Venta</option>
                                <option value="compra">Compra</option>
                                <option value="gasto">Gasto</option>
                                <option value="pago_proveedor">Pago a Proveedor</option>
                                <option value="cobro_cliente">Cobro a Cliente</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Importe</label>
                            <input type="number" step="0.01" class="form-control" name="importe" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Método de Pago</label>
                            <select class="form-select" name="metodo_pago" required>
                                <option value="efectivo">Efectivo</option>
                                <option value="tarjeta">Tarjeta</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Número de Referencia</label>
                            <input type="text" class="form-control" name="referencia" placeholder="Opcional">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Fecha</label>
                            <input type="date" class="form-control" name="fecha_movimiento" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Observaciones</label>
                            <textarea class="form-control" name="observaciones" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function setTipoMovimiento(tipo) {
            document.getElementById('tipo_movimiento').value = tipo;
            document.getElementById('modalTitle').innerText = tipo === 'ingreso' ? 'Nuevo Ingreso' : 'Nuevo Egreso';
        }
    </script>
</body>

</html>