<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Verificar permisos (admin y vendedor pueden acceder)
requiere_permiso('facturas');

// Procesar nueva factura
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'crear_factura') {
        $numero_factura = limpiar_entrada($_POST['numero_factura']);
        $tipo = limpiar_entrada($_POST['tipo']);
        $tipo_comprobante = limpiar_entrada($_POST['tipo_comprobante']);
        $cliente_id = intval($_POST['cliente_id']);
        $fecha_emision = limpiar_entrada($_POST['fecha_emision']);
        $fecha_vencimiento = limpiar_entrada($_POST['fecha_vencimiento']);

        // VALIDACIÓN: Verificar que productos no esté vacío
        $productos_json = $_POST['productos'];
        if (empty($productos_json)) {
            $mensaje = "Error: No hay productos en la factura";
            $tipo_mensaje = "danger";
        } else {
            $productos = json_decode($productos_json, true);

            // VALIDACIÓN: Verificar que se decodificó correctamente
            if ($productos === null || !is_array($productos) || count($productos) === 0) {
                $mensaje = "Error: No se pudieron procesar los productos de la factura";
                $tipo_mensaje = "danger";
            } else {
                $subtotal = floatval($_POST['subtotal']);
                $iva = floatval($_POST['iva']);
                $total = floatval($_POST['total']);
                $observaciones = limpiar_entrada($_POST['observaciones']);

                $conn->begin_transaction();

                try {
                    // Insertar factura
                    $sql_factura = "INSERT INTO facturas (numero_factura, tipo, tipo_comprobante, cliente_id, fecha_emision, fecha_vencimiento, subtotal, iva, total, observaciones, usuario_id) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
                    $stmt_factura = $conn->prepare($sql_factura);
                    $stmt_factura->bind_param("sssissddds", $numero_factura, $tipo, $tipo_comprobante, $cliente_id, $fecha_emision, $fecha_vencimiento, $subtotal, $iva, $total, $observaciones);
                    $stmt_factura->execute();
                    $factura_id = $conn->insert_id;

                    // Insertar detalles
                    foreach ($productos as $prod) {
                        $producto_id = isset($prod['id']) ? intval($prod['id']) : null;
                        $descripcion = limpiar_entrada($prod['descripcion']);
                        $cantidad = intval($prod['cantidad']);
                        $precio_unitario = floatval($prod['precio']);
                        $subtotal_item = $cantidad * $precio_unitario;

                        $sql_detalle = "INSERT INTO detalle_facturas (factura_id, producto_id, descripcion, cantidad, precio_unitario, subtotal) 
                                   VALUES (?, ?, ?, ?, ?, ?)";
                        $stmt_detalle = $conn->prepare($sql_detalle);
                        $stmt_detalle->bind_param("iisidd", $factura_id, $producto_id, $descripcion, $cantidad, $precio_unitario, $subtotal_item);
                        $stmt_detalle->execute();
                    }

                    // Registrar en cuenta corriente si es factura
                    if ($tipo_comprobante === 'factura') {
                        // Obtener saldo actual del cliente
                        $sql_saldo_actual = "SELECT COALESCE(SUM(CASE WHEN tipo_movimiento = 'debe' THEN importe ELSE -importe END), 0) as saldo
                                         FROM cuentas_corrientes WHERE cliente_id = ?";
                        $stmt_saldo = $conn->prepare($sql_saldo_actual);
                        $stmt_saldo->bind_param("i", $cliente_id);
                        $stmt_saldo->execute();
                        $saldo_actual = $stmt_saldo->get_result()->fetch_assoc()['saldo'];
                        $nuevo_saldo = $saldo_actual + $total;

                        $sql_cc = "INSERT INTO cuentas_corrientes (cliente_id, tipo_movimiento, concepto, factura_id, importe, saldo, fecha_movimiento, usuario_id) 
                              VALUES (?, 'debe', ?, ?, ?, ?, ?, 1)";
                        $stmt_cc = $conn->prepare($sql_cc);
                        $concepto = "Factura " . $numero_factura;
                        $stmt_cc->bind_param("isidds", $cliente_id, $concepto, $factura_id, $total, $nuevo_saldo, $fecha_emision);
                        $stmt_cc->execute();
                    }

                    $conn->commit();
                    $mensaje = "✅ Factura #" . $factura_id . " creada exitosamente";
                    $tipo_mensaje = "success";
                } catch (Exception $e) {
                    $conn->rollback();
                    $mensaje = "Error al crear factura: " . $e->getMessage();
                    $tipo_mensaje = "danger";
                }
            }
        }
    }
}
// Obtener facturas
$sql_facturas = "SELECT f.*, c.nombre as cliente_nombre, c.apellido as cliente_apellido 
                 FROM facturas f 
                 INNER JOIN clientes c ON f.cliente_id = c.id 
                 ORDER BY f.id DESC";
$result_facturas = $conn->query($sql_facturas);

// Obtener clientes activos
$sql_clientes = "SELECT * FROM clientes WHERE estado=1 ORDER BY nombre";
$result_clientes = $conn->query($sql_clientes);

// Obtener productos activos
$sql_productos = "SELECT * FROM productos WHERE estado=1 ORDER BY nombre";
$result_productos = $conn->query($sql_productos);

// Generar siguiente número de factura
$sql_ultimo = "SELECT numero_factura FROM facturas ORDER BY id DESC LIMIT 1";
$result_ultimo = $conn->query($sql_ultimo);
$siguiente_numero = "0001-00000001";
if ($result_ultimo->num_rows > 0) {
    $ultimo = $result_ultimo->fetch_assoc()['numero_factura'];
    $partes = explode('-', $ultimo);
    $numero = intval($partes[1]) + 1;
    $siguiente_numero = $partes[0] . '-' . str_pad($numero, 8, '0', STR_PAD_LEFT);
}
?>

$page_title = 'Facturas - Sistema de Ventas';
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
                    <h1 class="h3 mb-0 text-gray-800">Gestión de Facturas</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalFactura">
                        <i class="fas fa-plus"></i> Nueva Factura
                    </button>
                </div>

                <div class="card">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold" style="color: var(--primary);">Historial de Facturas</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Número</th>
                                        <th>Tipo</th>
                                        <th>Comprobante</th>
                                        <th>Cliente</th>
                                        <th>Fecha Emisión</th>
                                        <th>Vencimiento</th>
                                        <th>Total</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result_facturas->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $row['numero_factura']; ?></td>
                                            <td><span class="badge bg-info"><?php echo $row['tipo']; ?></span></td>
                                            <td><?php echo ucfirst(str_replace('_', ' ', $row['tipo_comprobante'])); ?></td>
                                            <td><?php echo $row['cliente_nombre'] . ' ' . $row['cliente_apellido']; ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($row['fecha_emision'])); ?></td>
                                            <td><?php echo $row['fecha_vencimiento'] ? date('d/m/Y', strtotime($row['fecha_vencimiento'])) : '-'; ?></td>
                                            <td class="text-success fw-bold"><?php echo formatear_precio($row['total']); ?></td>
                                            <td>
                                                <?php
                                                $badge_color = 'secondary';
                                                switch ($row['estado']) {
                                                    case 'pendiente':
                                                        $badge_color = 'warning';
                                                        break;
                                                    case 'pagada':
                                                        $badge_color = 'success';
                                                        break;
                                                    case 'vencida':
                                                        $badge_color = 'danger';
                                                        break;
                                                    case 'anulada':
                                                        $badge_color = 'secondary';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $badge_color; ?>">
                                                    <?php echo ucfirst($row['estado']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-info btn-sm" onclick="verFactura(<?php echo $row['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-success btn-sm" onclick="descargarPDF(<?php echo $row['id']; ?>)">
                                                    <i class="fas fa-file-pdf"></i>
                                                </button>
                                            </td>
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
                                        <label class="form-label">Número Factura</label>
                                        <input type="text" class="form-control" name="numero_factura" value="<?php echo $siguiente_numero; ?>" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Tipo</label>
                                        <select class="form-select" name="tipo" required>
                                            <option value="A">A</option>
                                            <option value="B" selected>B</option>
                                            <option value="C">C</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Comprobante</label>
                                        <select class="form-select" name="tipo_comprobante" required>
                                            <option value="factura" selected>Factura</option>
                                            <option value="nota_credito">Nota de Crédito</option>
                                            <option value="nota_debito">Nota de Débito</option>
                                            <option value="recibo">Recibo</option>
                                            <option value="presupuesto">Presupuesto</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Cliente</label>
                                        <select class="form-select" name="cliente_id" required>
                                            <option value="">Seleccionar...</option>
                                            <?php
                                            while ($cli = $result_clientes->fetch_assoc()):
                                            ?>
                                                <option value="<?php echo $cli['id']; ?>">
                                                    <?php echo $cli['nombre'] . ' ' . $cli['apellido'] . ' - DNI: ' . $cli['dni']; ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Fecha Emisión</label>
                                        <input type="date" class="form-control" name="fecha_emision" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Fecha Vencimiento</label>
                                        <input type="date" class="form-control" name="fecha_vencimiento">
                                    </div>
                                </div>

                                <h6 class="mb-3">Agregar Items</h6>
                                <div class="mb-3">
                                    <select class="form-select" id="producto_select">
                                        <option value="">Seleccionar producto...</option>
                                        <?php
                                        while ($prod = $result_productos->fetch_assoc()):
                                        ?>
                                            <option value='<?php echo json_encode($prod); ?>'>
                                                <?php echo $prod['nombre'] . ' - ' . formatear_precio($prod['precio']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div id="productos-factura">
                                    <p class="text-muted">No hay productos agregados</p>
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
                        <button type="submit" class="btn btn-success" id="btnGuardarFactura">
                            <i class="fas fa-check"></i> Generar Factura
                        </button>
                    </div>
                </form>
</div></div>

<?php
$inline_script = <<<'JS'
let items = [];

        // Función para formatear precio
        function formatearPrecio(precio) {
            return '$' + parseFloat(precio).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        // Event listener para seleccionar producto
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Inicializando facturas...');

            const productoSelect = document.getElementById('producto_select');
            if (!productoSelect) {
                console.error('❌ No se encontró el select de productos');
                return;
            }

            console.log('✅ Select de productos encontrado');

            productoSelect.addEventListener('change', function() {
                if (this.value) {
                    try {
                        const producto = JSON.parse(this.value);
                        console.log('Producto seleccionado:', producto);
                        agregarItem(producto);
                        this.value = '';
                    } catch (error) {
                        console.error('Error al parsear producto:', error);
                        alert('Error al agregar producto');
                    }
                }
            });

            // Resetear modal al cerrar
            const modalFactura = document.getElementById('modalFactura');
            if (modalFactura) {
                modalFactura.addEventListener('hidden.bs.modal', function() {
                    items = [];
                    actualizarItems();
                    document.getElementById('formFactura').reset();
                    console.log('Modal cerrado, items reseteados');
                });
            }

            // Validar formulario antes de enviar
            const formFactura = document.getElementById('formFactura');
            if (formFactura) {
                formFactura.addEventListener('submit', function(e) {
                    console.log('=== INTENTANDO ENVIAR FACTURA ===');
                    console.log('Items actuales:', items);
                    console.log('productos_json value:', document.getElementById('productos_json').value);

                    if (items.length === 0) {
                        e.preventDefault();
                        alert('⚠️ Debe agregar al menos un item a la factura');
                        return false;
                    }

                    const clienteId = document.querySelector('select[name="cliente_id"]').value;
                    if (!clienteId) {
                        e.preventDefault();
                        alert('⚠️ Debe seleccionar un cliente');
                        return false;
                    }

                    // Verificar que productos_json tenga contenido
                    const productosJson = document.getElementById('productos_json').value;
                    if (!productosJson || productosJson === '' || productosJson === '[]') {
                        e.preventDefault();
                        alert('⚠️ Error: No hay items en la factura');
                        console.error('productos_json está vacío!');
                        return false;
                    }

                    console.log('Enviando factura correctamente');

                    // Mostrar loading
                    const btn = document.getElementById('btnGuardarFactura');
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

                    return true;
                });
            }
        });

        // Función para agregar item (GLOBAL)
        function agregarItem(producto) {
            console.log('Agregando item:', producto);

            const existe = items.find(p => p.id === producto.id);

            if (existe) {
                console.log('Producto ya existe, aumentando cantidad');
                existe.cantidad++;
            } else {
                console.log('Producto nuevo, agregando');
                items.push({
                    id: producto.id,
                    descripcion: producto.nombre,
                    precio: parseFloat(producto.precio),
                    cantidad: 1
                });
            }

            console.log('Items actuales:', items);
            actualizarItems();
        }

        // Función para actualizar items (GLOBAL)
        function actualizarItems() {
            console.log('=== ACTUALIZANDO ITEMS ===');
            console.log('Cantidad de items:', items.length);

            const container = document.getElementById('productos-factura');

            if (!container) {
                console.error('ERROR: No se encontró el contenedor de productos');
                return;
            }

            if (items.length === 0) {
                container.innerHTML = '<p class="text-muted">No hay productos agregados</p>';
                document.getElementById('subtotal_display').innerText = '$0.00';
                document.getElementById('iva_display').innerText = '$0.00';
                document.getElementById('total_display').innerText = '$0.00';
                document.getElementById('btnGuardarFactura').disabled = true;
                return;
            }

            let html = '';
            let subtotal = 0;

            items.forEach((item, index) => {
                const subtotal_item = item.precio * item.cantidad;
                subtotal += subtotal_item;

                html += `
            <div class="producto-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${item.descripcion}</strong><br>
                        <small>${formatearPrecio(item.precio)} x ${item.cantidad} = ${formatearPrecio(subtotal_item)}</small>
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="cambiarCantidad(${index}, -1)">-</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled>${item.cantidad}</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="cambiarCantidad(${index}, 1)">+</button>
                        <button type="button" class="btn btn-sm btn-danger" onclick="eliminarItem(${index})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
            });

            container.innerHTML = html;

            const iva = subtotal * 0.21;
            const total = subtotal + iva;

            console.log('Subtotal:', subtotal, 'IVA:', iva, 'Total:', total);

            document.getElementById('subtotal_display').innerText = formatearPrecio(subtotal);
            document.getElementById('iva_display').innerText = formatearPrecio(iva);
            document.getElementById('total_display').innerText = formatearPrecio(total);

            document.getElementById('subtotal_hidden').value = subtotal.toFixed(2);
            document.getElementById('iva_hidden').value = iva.toFixed(2);
            document.getElementById('total_hidden').value = total.toFixed(2);

            const productosJson = JSON.stringify(items);
            document.getElementById('productos_json').value = productosJson;
            console.log('JSON guardado:', productosJson);

            document.getElementById('btnGuardarFactura').disabled = false;

            console.log('=== FIN ACTUALIZACIÓN ===');
        }

        // Cambiar cantidad (GLOBAL)
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

        // Eliminar item (GLOBAL)
        function eliminarItem(index) {
            if (confirm('¿Eliminar este item?')) {
                items.splice(index, 1);
                actualizarItems();
            }
        }

        // Ver factura (GLOBAL)
        function verFactura(id) {
            window.location.href = 'detalle_factura.php?id=' + id;
        }

        // Descargar PDF (GLOBAL)
        function descargarPDF(id) {
            window.open('generar_pdf_factura.php?id=' + id, '_blank');
        }

        console.log('Script de facturas cargado correctamente');
JS;
?>

<?php include "../includes/footer.php"; ?>