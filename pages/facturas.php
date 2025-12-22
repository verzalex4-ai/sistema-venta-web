<?php
/**
 * FACTURAS - Versión optimizada
 */
require_once '../config.php';

requiere_permiso('facturas');

// ============================================
// PROCESAR NUEVA FACTURA
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear_factura') {
    
    try {
        $numero_factura = limpiar_entrada($_POST['numero_factura']);
        $tipo = limpiar_entrada($_POST['tipo']);
        $tipo_comprobante = limpiar_entrada($_POST['tipo_comprobante']);
        $cliente_id = intval($_POST['cliente_id']);
        $fecha_emision = limpiar_entrada($_POST['fecha_emision']);
        $fecha_vencimiento = limpiar_entrada($_POST['fecha_vencimiento']);
        $productos_json = $_POST['productos'] ?? '';
        $subtotal = floatval($_POST['subtotal']);
        $iva = floatval($_POST['iva']);
        $total = floatval($_POST['total']);
        $observaciones = limpiar_entrada($_POST['observaciones']);
        
        // Validar productos
        if (empty($productos_json)) {
            throw new Exception('No hay productos en la factura');
        }
        
        $productos = json_decode($productos_json, true);
        if (!is_array($productos) || count($productos) === 0) {
            throw new Exception('Error al procesar los productos');
        }
        
        $conn->begin_transaction();
        
        // Insertar factura
        $sql = "INSERT INTO facturas (numero_factura, tipo, tipo_comprobante, cliente_id, fecha_emision, 
                fecha_vencimiento, subtotal, iva, total, observaciones, usuario_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $usuario_id = $_SESSION['usuario_id'];
        $stmt->bind_param("sssissdddsi", $numero_factura, $tipo, $tipo_comprobante, $cliente_id, 
                         $fecha_emision, $fecha_vencimiento, $subtotal, $iva, $total, $observaciones, $usuario_id);
        $stmt->execute();
        $factura_id = $conn->insert_id;
        
        // Insertar detalles
        foreach ($productos as $prod) {
            $producto_id = isset($prod['id']) ? intval($prod['id']) : null;
            $descripcion = limpiar_entrada($prod['descripcion']);
            $cantidad = intval($prod['cantidad']);
            $precio_unitario = floatval($prod['precio']);
            $subtotal_item = $cantidad * $precio_unitario;
            
            $sql = "INSERT INTO detalle_facturas (factura_id, producto_id, descripcion, cantidad, precio_unitario, subtotal) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iisidd", $factura_id, $producto_id, $descripcion, $cantidad, $precio_unitario, $subtotal_item);
            $stmt->execute();
        }
        
        // Registrar en cuenta corriente si es factura
        if ($tipo_comprobante === 'factura') {
            $sql = "SELECT COALESCE(SUM(CASE WHEN tipo_movimiento = 'debe' THEN importe ELSE -importe END), 0) as saldo
                    FROM cuentas_corrientes WHERE cliente_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $cliente_id);
            $stmt->execute();
            $saldo_actual = $stmt->get_result()->fetch_assoc()['saldo'];
            $nuevo_saldo = $saldo_actual + $total;
            
            $sql = "INSERT INTO cuentas_corrientes (cliente_id, tipo_movimiento, concepto, factura_id, importe, saldo, fecha_movimiento, usuario_id) 
                    VALUES (?, 'debe', ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $concepto = "Factura " . $numero_factura;
            $stmt->bind_param("isiddsi", $cliente_id, $concepto, $factura_id, $total, $nuevo_saldo, $fecha_emision, $usuario_id);
            $stmt->execute();
        }
        
        $conn->commit();
        set_mensaje("✅ Factura #$factura_id creada exitosamente", 'success');
        
        header('Location: facturas.php');
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        set_mensaje('Error al crear factura: ' . $e->getMessage(), 'danger');
    }
}

// ============================================
// OBTENER FACTURAS
// ============================================
$facturas = $conn->query("
    SELECT f.*, CONCAT(c.nombre, ' ', c.apellido) as cliente_nombre
    FROM facturas f 
    INNER JOIN clientes c ON f.cliente_id = c.id 
    ORDER BY f.id DESC
    LIMIT 100
")->fetch_all(MYSQLI_ASSOC);

// Obtener clientes y productos
$clientes = $conn->query("SELECT * FROM clientes WHERE estado=1 ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
$productos = $conn->query("SELECT * FROM productos WHERE estado=1 ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);

// Generar siguiente número
$ultimo = $conn->query("SELECT numero_factura FROM facturas ORDER BY id DESC LIMIT 1")->fetch_assoc();
$siguiente_numero = "0001-00000001";
if ($ultimo) {
    $partes = explode('-', $ultimo['numero_factura']);
    $numero = intval($partes[1]) + 1;
    $siguiente_numero = $partes[0] . '-' . str_pad($numero, 8, '0', STR_PAD_LEFT);
}

$page_title = 'Facturas - Sistema de Ventas';
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
                <i class="fas fa-file-invoice me-2"></i>Gestión de Facturas
            </h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalFactura">
                <i class="fas fa-plus me-2"></i>Nueva Factura
            </button>
        </div>
        
        <!-- Tabla de Facturas -->
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Historial de Facturas</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Tipo</th>
                                <th>Cliente</th>
                                <th>Fecha</th>
                                <th>Vencimiento</th>
                                <th>Total</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($facturas) > 0): ?>
                                <?php foreach ($facturas as $f): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($f['numero_factura']); ?></strong></td>
                                    <td><span class="badge bg-info"><?php echo $f['tipo']; ?></span></td>
                                    <td><?php echo htmlspecialchars($f['cliente_nombre']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($f['fecha_emision'])); ?></td>
                                    <td><?php echo $f['fecha_vencimiento'] ? date('d/m/Y', strtotime($f['fecha_vencimiento'])) : '-'; ?></td>
                                    <td class="text-success fw-bold"><?php echo formatear_precio($f['total']); ?></td>
                                    <td>
                                        <?php
                                        $badge_color = [
                                            'pendiente' => 'warning',
                                            'pagada' => 'success',
                                            'vencida' => 'danger',
                                            'anulada' => 'secondary'
                                        ][$f['estado']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $badge_color; ?>">
                                            <?php echo ucfirst($f['estado']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="detalle_factura.php?id=<?php echo $f['id']; ?>" 
                                           class="btn btn-info btn-sm" title="Ver">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="generar_pdf_factura.php?id=<?php echo $f['id']; ?>" 
                                           class="btn btn-success btn-sm" title="PDF" target="_blank">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        No hay facturas registradas
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
    </div>
</div>

<!-- Modal Nueva Factura -->
<div class="modal fade" id="modalFactura" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nueva Factura</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formFactura">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="crear_factura">
                    <input type="hidden" name="productos" id="productos_json">
                    <input type="hidden" name="subtotal" id="subtotal_hidden">
                    <input type="hidden" name="iva" id="iva_hidden">
                    <input type="hidden" name="total" id="total_hidden">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Número Factura *</label>
                                    <input type="text" class="form-control" name="numero_factura" 
                                           value="<?php echo $siguiente_numero; ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Tipo *</label>
                                    <select class="form-select" name="tipo" required>
                                        <option value="A">A</option>
                                        <option value="B" selected>B</option>
                                        <option value="C">C</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Comprobante *</label>
                                    <select class="form-select" name="tipo_comprobante" required>
                                        <option value="factura" selected>Factura</option>
                                        <option value="nota_credito">Nota de Crédito</option>
                                        <option value="nota_debito">Nota de Débito</option>
                                        <option value="recibo">Recibo</option>
                                        <option value="presupuesto">Presupuesto</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Cliente *</label>
                                <select class="form-select" name="cliente_id" required>
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($clientes as $cli): ?>
                                        <option value="<?php echo $cli['id']; ?>">
                                            <?php echo htmlspecialchars($cli['nombre'] . ' ' . $cli['apellido'] . ' - DNI: ' . $cli['dni']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Fecha Emisión *</label>
                                    <input type="date" class="form-control" name="fecha_emision" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Fecha Vencimiento</label>
                                    <input type="date" class="form-control" name="fecha_vencimiento">
                                </div>
                            </div>
                            
                            <h6 class="mb-3">Agregar Items</h6>
                            <select class="form-select mb-3" id="producto_select">
                                <option value="">Seleccionar producto...</option>
                                <?php foreach ($productos as $prod): ?>
                                    <option value='<?php echo htmlspecialchars(json_encode([
                                        'id' => $prod['id'],
                                        'descripcion' => $prod['nombre'],
                                        'precio' => $prod['precio']
                                    ]), ENT_QUOTES); ?>'>
                                        <?php echo htmlspecialchars($prod['nombre']) . ' - ' . formatear_precio($prod['precio']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <div id="items-factura">
                                <p class="text-muted">No hay items agregados</p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Observaciones</label>
                                <textarea class="form-control" name="observaciones" rows="2"></textarea>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">Resumen</h6>
                                    <hr>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Subtotal:</span>
                                        <span id="subtotal_display">$0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>IVA (21%):</span>
                                        <span id="iva_display">$0.00</span>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between">
                                        <strong>TOTAL:</strong>
                                        <strong class="text-success" id="total_display">$0.00</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="btnGuardarFactura" disabled>
                        <i class="fas fa-check me-2"></i>Generar Factura
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Reutilizar lógica del carrito similar a ventas
$inline_script = <<<'JAVASCRIPT'
let items = [];

function formatearPrecio(precio) {
    return '$' + parseFloat(precio).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function agregarItem(producto) {
    const existe = items.find(p => p.id === parseInt(producto.id));
    
    if (existe) {
        existe.cantidad++;
    } else {
        items.push({
            id: parseInt(producto.id),
            descripcion: producto.descripcion,
            precio: parseFloat(producto.precio),
            cantidad: 1
        });
    }
    
    actualizarItems();
}

function actualizarItems() {
    const container = document.getElementById('items-factura');
    
    if (items.length === 0) {
        container.innerHTML = '<p class="text-muted">No hay items agregados</p>';
        document.getElementById('btnGuardarFactura').disabled = true;
        return;
    }
    
    let html = '<div class="list-group">';
    let subtotal = 0;
    
    items.forEach((item, index) => {
        const subtotal_item = item.precio * item.cantidad;
        subtotal += subtotal_item;
        
        html += `
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="flex-grow-1">
                        <strong>${item.descripcion}</strong><br>
                        <small class="text-muted">
                            ${formatearPrecio(item.precio)} × ${item.cantidad} = 
                            <span class="text-success fw-bold">${formatearPrecio(subtotal_item)}</span>
                        </small>
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="cambiarCantidad(${index}, -1)">
                            <i class="fas fa-minus"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled>${item.cantidad}</button>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="cambiarCantidad(${index}, 1)">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" onclick="eliminarItem(${index})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    
    container.innerHTML = html;
    
    const iva = subtotal * 0.21;
    const total = subtotal + iva;
    
    document.getElementById('subtotal_display').textContent = formatearPrecio(subtotal);
    document.getElementById('iva_display').textContent = formatearPrecio(iva);
    document.getElementById('total_display').textContent = formatearPrecio(total);
    
    document.getElementById('subtotal_hidden').value = subtotal.toFixed(2);
    document.getElementById('iva_hidden').value = iva.toFixed(2);
    document.getElementById('total_hidden').value = total.toFixed(2);
    document.getElementById('productos_json').value = JSON.stringify(items);
    
    document.getElementById('btnGuardarFactura').disabled = false;
}

function cambiarCantidad(index, cambio) {
    const item = items[index];
    const nuevaCantidad = item.cantidad + cambio;
    
    if (nuevaCantidad <= 0) {
        eliminarItem(index);
        return;
    }
    
    item.cantidad = nuevaCantidad;
    actualizarItems();
}

function eliminarItem(index) {
    if (confirm('¿Eliminar este item?')) {
        items.splice(index, 1);
        actualizarItems();
    }
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    const productoSelect = document.getElementById('producto_select');
    
    productoSelect.addEventListener('change', function() {
        if (this.value) {
            try {
                const producto = JSON.parse(this.value);
                agregarItem(producto);
                this.value = '';
            } catch(e) {
                alert('Error al agregar item');
            }
        }
    });
    
    // Resetear modal
    document.getElementById('modalFactura').addEventListener('hidden.bs.modal', function() {
        items = [];
        actualizarItems();
        document.getElementById('formFactura').reset();
    });
    
    // Validar antes de enviar
    document.getElementById('formFactura').addEventListener('submit', function(e) {
        if (items.length === 0) {
            e.preventDefault();
            alert('Debe agregar al menos un item');
            return false;
        }
    });
});
JAVASCRIPT;

include '../includes/footer.php';
?>