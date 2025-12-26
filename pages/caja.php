<?php
require_once '../config.php';
require_once '../includes/functions.php';

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
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $usuario_id = $_SESSION['usuario_id'];
        $stmt->bind_param("sssdsssi", $tipo, $concepto, $categoria, $importe, $metodo_pago, $referencia, $fecha_movimiento, $observaciones, $usuario_id);

        if ($stmt->execute()) {
            set_mensaje("Movimiento registrado exitosamente", 'success');
        } else {
            set_mensaje("Error al registrar movimiento", 'danger');
        }
        
        header('Location: caja.php');
        exit;
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

$page_title = 'Caja - Sistema de Ventas';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div id="content-wrapper" class="flex-fill">
<?php include "../includes/topbar.php"; ?>
<div class="container-fluid px-4 py-4">
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
</div></div>

<?php
$inline_script = <<<'JS'
function setTipoMovimiento(tipo) {
            document.getElementById('tipo_movimiento').value = tipo;
            document.getElementById('modalTitle').innerText = tipo === 'ingreso' ? 'Nuevo Ingreso' : 'Nuevo Egreso';
        }
JS;
?>

<?php include "../includes/footer.php"; ?>