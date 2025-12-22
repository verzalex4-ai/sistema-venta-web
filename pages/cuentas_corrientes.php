<?php
/**
 * CUENTAS CORRIENTES - Versión optimizada
 * PROBLEMA RESUELTO: Eliminado HTML duplicado
 */
require_once '../config.php';

requiere_login();

// ============================================
// PROCESAR PAGOS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'registrar_pago') {
    try {
        $cliente_id = intval($_POST['cliente_id']);
        $importe = floatval($_POST['importe']);
        $concepto = limpiar_entrada($_POST['concepto']);
        $fecha_movimiento = limpiar_entrada($_POST['fecha_movimiento']);
        $observaciones = limpiar_entrada($_POST['observaciones']);
        
        // Obtener saldo actual
        $sql = "SELECT COALESCE(SUM(CASE WHEN tipo_movimiento = 'debe' THEN importe ELSE -importe END), 0) as saldo
                FROM cuentas_corrientes WHERE cliente_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $cliente_id);
        $stmt->execute();
        $saldo_actual = $stmt->get_result()->fetch_assoc()['saldo'];
        $nuevo_saldo = $saldo_actual - $importe;
        
        // Registrar pago
        $sql = "INSERT INTO cuentas_corrientes (cliente_id, tipo_movimiento, concepto, importe, saldo, fecha_movimiento, observaciones, usuario_id) 
                VALUES (?, 'haber', ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $usuario_id = $_SESSION['usuario_id'];
        $stmt->bind_param("isddsi", $cliente_id, $concepto, $importe, $nuevo_saldo, $fecha_movimiento, $observaciones, $usuario_id);
        $stmt->execute();
        
        set_mensaje('Pago registrado exitosamente', 'success');
        header("Location: cuentas_corrientes.php?cliente_id=$cliente_id");
        exit;
        
    } catch (Exception $e) {
        set_mensaje('Error al registrar pago: ' . $e->getMessage(), 'danger');
    }
}

// ============================================
// OBTENER DATOS
// ============================================

// Cliente seleccionado
$cliente_id = isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : 0;
$cliente_seleccionado = null;
$movimientos = [];
$saldo_total = 0;

if ($cliente_id > 0) {
    // Datos del cliente
    $sql = "SELECT * FROM clientes WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $cliente_id);
    $stmt->execute();
    $cliente_seleccionado = $stmt->get_result()->fetch_assoc();
    
    if ($cliente_seleccionado) {
        // Movimientos del cliente
        $sql = "SELECT cc.*, f.numero_factura, f.tipo as factura_tipo
                FROM cuentas_corrientes cc
                LEFT JOIN facturas f ON cc.factura_id = f.id
                WHERE cc.cliente_id = ?
                ORDER BY cc.fecha_movimiento DESC, cc.id DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $cliente_id);
        $stmt->execute();
        $movimientos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Último saldo registrado
        $sql = "SELECT saldo FROM cuentas_corrientes 
                WHERE cliente_id = ? 
                ORDER BY fecha_movimiento DESC, id DESC 
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $cliente_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $saldo_total = $result->fetch_assoc()['saldo'];
        }
    }
}

// Clientes con saldo
$clientes = $conn->query("
    SELECT 
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
    ORDER BY saldo_actual DESC
")->fetch_all(MYSQLI_ASSOC);

$page_title = 'Cuentas Corrientes - Sistema de Ventas';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div id="content-wrapper" class="d-flex flex-column">
    <?php include '../includes/topbar.php'; ?>
    
    <div class="container-fluid px-4 py-4">
        
        <?php mostrar_mensaje(); ?>
        
        <!-- Encabezado -->
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-file-invoice-dollar me-2"></i>Cuentas Corrientes
            </h1>
            <?php if ($cliente_seleccionado): ?>
                <a href="cuentas_corrientes.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Volver
                </a>
            <?php endif; ?>
        </div>
        
        <?php if (!$cliente_seleccionado): ?>
            <!-- VISTA: Lista de Clientes -->
            <div class="row">
                <?php if (count($clientes) > 0): ?>
                    <?php foreach ($clientes as $cliente): ?>
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card shadow h-100 cursor-pointer" 
                                 onclick="window.location.href='cuentas_corrientes.php?cliente_id=<?php echo $cliente['id']; ?>'"
                                 style="cursor: pointer; transition: transform 0.2s;">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-user-circle text-primary me-2"></i>
                                        <?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']); ?>
                                    </h5>
                                    <p class="card-text">
                                        <small class="text-muted">DNI: <?php echo htmlspecialchars($cliente['dni']); ?></small><br>
                                        <small class="text-muted">Tel: <?php echo htmlspecialchars($cliente['telefono']); ?></small>
                                    </p>
                                    <hr>
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <small class="text-muted d-block">Debe</small>
                                            <strong class="text-danger"><?php echo formatear_precio($cliente['total_debe']); ?></strong>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted d-block">Haber</small>
                                            <strong class="text-success"><?php echo formatear_precio($cliente['total_haber']); ?></strong>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted d-block">Saldo</small>
                                            <strong class="<?php echo $cliente['saldo_actual'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                                <?php echo formatear_precio($cliente['saldo_actual']); ?>
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-file-invoice-dollar fa-4x text-muted mb-3"></i>
                                <h5>No hay movimientos de cuenta corriente</h5>
                                <p class="text-muted">Los clientes con movimientos aparecerán aquí</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
        <?php else: ?>
            <!-- VISTA: Detalle de Cliente -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card shadow">
                        <div class="card-body">
                            <h4>
                                <i class="fas fa-user-circle text-primary me-2"></i>
                                <?php echo htmlspecialchars($cliente_seleccionado['nombre'] . ' ' . $cliente_seleccionado['apellido']); ?>
                            </h4>
                            <p class="mb-0 text-muted">
                                <strong>DNI:</strong> <?php echo htmlspecialchars($cliente_seleccionado['dni']); ?> |
                                <strong>Email:</strong> <?php echo htmlspecialchars($cliente_seleccionado['email'] ?? '-'); ?> |
                                <strong>Teléfono:</strong> <?php echo htmlspecialchars($cliente_seleccionado['telefono'] ?? '-'); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow">
                        <div class="card-body text-center">
                            <h6 class="text-muted">SALDO ACTUAL</h6>
                            <h2 class="<?php echo $saldo_total > 0 ? 'text-danger' : 'text-success'; ?>">
                                <?php echo formatear_precio($saldo_total); ?>
                            </h2>
                            <button class="btn btn-success w-100 mt-2" data-bs-toggle="modal" data-bs-target="#modalPago">
                                <i class="fas fa-money-bill-wave me-2"></i>Registrar Pago
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tabla de Movimientos -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Movimientos de Cuenta</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Concepto</th>
                                    <th>Factura</th>
                                    <th class="text-end">Debe</th>
                                    <th class="text-end">Haber</th>
                                    <th class="text-end">Saldo</th>
                                    <th>Observaciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($movimientos) > 0): ?>
                                    <?php foreach ($movimientos as $mov): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($mov['fecha_movimiento'])); ?></td>
                                        <td><?php echo htmlspecialchars($mov['concepto']); ?></td>
                                        <td>
                                            <?php if ($mov['numero_factura']): ?>
                                                <span class="badge bg-info">
                                                    <?php echo htmlspecialchars($mov['factura_tipo'] . ' ' . $mov['numero_factura']); ?>
                                                </span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end text-danger fw-bold">
                                            <?php echo $mov['tipo_movimiento'] === 'debe' ? formatear_precio($mov['importe']) : '-'; ?>
                                        </td>
                                        <td class="text-end text-success fw-bold">
                                            <?php echo $mov['tipo_movimiento'] === 'haber' ? formatear_precio($mov['importe']) : '-'; ?>
                                        </td>
                                        <td class="text-end fw-bold <?php echo $mov['saldo'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                            <?php echo formatear_precio($mov['saldo']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($mov['observaciones'] ?? '-'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            No hay movimientos registrados
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Modal Registrar Pago -->
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
                                    <strong>Cliente:</strong> <?php echo htmlspecialchars($cliente_seleccionado['nombre'] . ' ' . $cliente_seleccionado['apellido']); ?><br>
                                    <strong>Saldo Actual:</strong> <?php echo formatear_precio($saldo_total); ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Importe del Pago *</label>
                                    <input type="number" step="0.01" class="form-control" name="importe" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Concepto</label>
                                    <input type="text" class="form-control" name="concepto" value="Pago recibido" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Fecha de Pago</label>
                                    <input type="date" class="form-control" name="fecha_movimiento" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Observaciones</label>
                                    <textarea class="form-control" name="observaciones" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check me-2"></i>Registrar Pago
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<style>
.card.cursor-pointer:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.15) !important;
}
</style>

<?php
include '../includes/footer.php';
?>