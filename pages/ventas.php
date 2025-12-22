<?php
/**
 * VENTAS - Versión optimizada
 */
require_once '../config.php';

requiere_permiso('ventas');

// ============================================
// PROCESAR NUEVA VENTA
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear_venta') {
    
    $cliente_id = intval($_POST['cliente_id']);
    $metodo_pago = limpiar_entrada($_POST['metodo_pago']);
    $productos_json = $_POST['productos'] ?? '';
    $total = floatval($_POST['total']);
    
    // Validar datos
    if (empty($productos_json)) {
        set_mensaje('Error: No hay productos en el carrito', 'danger');
    } else {
        $productos = json_decode($productos_json, true);
        
        if (!is_array($productos) || count($productos) === 0) {
            set_mensaje('Error: No se pudieron procesar los productos', 'danger');
        } else {
            // Iniciar transacción
            $conn->begin_transaction();
            
            try {
                // Insertar venta
                $sql = "INSERT INTO ventas (cliente_id, usuario_id, total, metodo_pago, estado) 
                        VALUES (?, ?, ?, ?, 'completada')";
                $stmt = $conn->prepare($sql);
                $usuario_id = $_SESSION['usuario_id'];
                $stmt->bind_param("iids", $cliente_id, $usuario_id, $total, $metodo_pago);
                $stmt->execute();
                $venta_id = $conn->insert_id();
                
                // Insertar detalles y actualizar stock
                foreach ($productos as $prod) {
                    $producto_id = intval($prod['id']);
                    $cantidad = intval($prod['cantidad']);
                    $precio = floatval($prod['precio']);
                    $subtotal = $cantidad * $precio;
                    
                    // Verificar stock disponible
                    $check = $conn->query("SELECT stock FROM productos WHERE id = $producto_id");
                    $stock_actual = $check->fetch_assoc()['stock'];
                    
                    if ($stock_actual < $cantidad) {
                        throw new Exception("Stock insuficiente para producto ID: $producto_id");
                    }
                    
                    // Insertar detalle
                    $sql = "INSERT INTO detalle_ventas (venta_id, producto_id, cantidad, precio_unitario, subtotal) 
                            VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("iiidd", $venta_id, $producto_id, $cantidad, $precio, $subtotal);
                    $stmt->execute();
                    
                    // Actualizar stock
                    $conn->query("UPDATE productos SET stock = stock - $cantidad WHERE id = $producto_id");
                }
                
                $conn->commit();
                set_mensaje("✅ Venta #$venta_id registrada exitosamente", 'success');
                
                // Redirigir para limpiar POST
                header('Location: ventas.php');
                exit;
                
            } catch (Exception $e) {
                $conn->rollback();
                set_mensaje('Error al registrar venta: ' . $e->getMessage(), 'danger');
            }
        }
    }
}

// ============================================
// OBTENER VENTAS CON FILTROS
// ============================================
$filtro_cliente = isset($_GET['cliente']) ? intval($_GET['cliente']) : 0;
$filtro_metodo = isset($_GET['metodo']) ? limpiar_entrada($_GET['metodo']) : '';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? '';
$busqueda = $_GET['buscar'] ?? '';

$where_conditions = ["1=1"];
$params = [];
$types = '';

if ($filtro_cliente > 0) {
    $where_conditions[] = "v.cliente_id = ?";
    $params[] = $filtro_cliente;
    $types .= 'i';
}

if ($filtro_metodo) {
    $where_conditions[] = "v.metodo_pago = ?";
    $params[] = $filtro_metodo;
    $types .= 's';
}

if ($filtro_fecha_desde) {
    $where_conditions[] = "DATE(v.fecha_creacion) >= ?";
    $params[] = $filtro_fecha_desde;
    $types .= 's';
}

if ($filtro_fecha_hasta) {
    $where_conditions[] = "DATE(v.fecha_creacion) <= ?";
    $params[] = $filtro_fecha_hasta;
    $types .= 's';
}

if ($busqueda) {
    $where_conditions[] = "(c.nombre LIKE ? OR c.apellido LIKE ? OR v.id LIKE ?)";
    $search_param = "%$busqueda%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$sql = "SELECT v.*, CONCAT(c.nombre, ' ', c.apellido) as cliente_nombre
        FROM ventas v 
        INNER JOIN clientes c ON v.cliente_id = c.id 
        WHERE " . implode(' AND ', $where_conditions) . "
        ORDER BY v.id DESC LIMIT 100";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$ventas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calcular totales
$sql_totales = "SELECT COUNT(*) as cantidad, COALESCE(SUM(v.total), 0) as total_ingresos
                FROM ventas v 
                INNER JOIN clientes c ON v.cliente_id = c.id 
                WHERE v.estado='completada' AND " . implode(' AND ', $where_conditions);
$stmt = $conn->prepare($sql_totales);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totales = $stmt->get_result()->fetch_assoc();

// Obtener clientes y productos
$clientes = $conn->query("SELECT * FROM clientes WHERE estado=1 ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
$productos = $conn->query("SELECT * FROM productos WHERE estado=1 AND stock > 0 ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);

$page_title = 'Ventas - Sistema de Ventas';
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
                <i class="fas fa-cash-register me-2"></i>Gestión de Ventas
            </h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalVenta">
                <i class="fas fa-plus me-2"></i>Nueva Venta
            </button>
        </div>
        
        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Ventas Filtradas
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo $totales['cantidad']; ?>
                                </div>
                            </div>
                            <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Ingresos Totales
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo formatear_precio($totales['total_ingresos']); ?>
                                </div>
                            </div>
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Buscar</label>
                        <input type="text" class="form-control" name="buscar" 
                               value="<?php echo htmlspecialchars($busqueda); ?>" 
                               placeholder="ID, Cliente...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Cliente</label>
                        <select class="form-select" name="cliente">
                            <option value="">Todos</option>
                            <?php foreach ($clientes as $cli): ?>
                                <option value="<?php echo $cli['id']; ?>" 
                                        <?php echo $filtro_cliente == $cli['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cli['nombre'] . ' ' . $cli['apellido']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Método de Pago</label>
                        <select class="form-select" name="metodo">
                            <option value="">Todos</option>
                            <option value="efectivo" <?php echo $filtro_metodo == 'efectivo' ? 'selected' : ''; ?>>Efectivo</option>
                            <option value="tarjeta" <?php echo $filtro_metodo == 'tarjeta' ? 'selected' : ''; ?>>Tarjeta</option>
                            <option value="transferencia" <?php echo $filtro_metodo == 'transferencia' ? 'selected' : ''; ?>>Transferencia</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Desde</label>
                        <input type="date" class="form-control" name="fecha_desde" 
                               value="<?php echo htmlspecialchars($filtro_fecha_desde); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Hasta</label>
                        <input type="date" class="form-control" name="fecha_hasta" 
                               value="<?php echo htmlspecialchars($filtro_fecha_hasta); ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i>Filtrar
                        </button>
                        <a href="ventas.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Tabla de Ventas -->
        <div class="card">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Historial de Ventas</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Total</th>
                                <th>Método</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($ventas) > 0): ?>
                                <?php foreach ($ventas as $venta): ?>
                                <tr>
                                    <td><strong>#<?php echo str_pad($venta['id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td><?php echo htmlspecialchars($venta['cliente_nombre']); ?></td>
                                    <td class="text-success fw-bold"><?php echo formatear_precio($venta['total']); ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo ucfirst($venta['metodo_pago']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success">
                                            <?php echo ucfirst($venta['estado']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatear_fecha($venta['fecha_creacion']); ?></td>
                                    <td>
                                        <a href="detalle_venta.php?id=<?php echo $venta['id']; ?>" 
                                           class="btn btn-info btn-sm" title="Ver Detalle">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-3x mb-3"></i><br>
                                        No se encontraron ventas
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

<!-- Modal Nueva Venta -->
<div class="modal fade" id="modalVenta" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nueva Venta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formVenta">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="crear_venta">
                    <input type="hidden" name="productos" id="productos_json">
                    <input type="hidden" name="total" id="total_venta">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="mb-3">Seleccionar Productos</h6>
                            <select class="form-select mb-3" id="producto_select">
                                <option value="">Seleccionar producto...</option>
                                <?php foreach ($productos as $prod): ?>
                                    <option value='<?php echo htmlspecialchars(json_encode([
                                        'id' => $prod['id'],
                                        'nombre' => $prod['nombre'],
                                        'precio' => $prod['precio'],
                                        'stock' => $prod['stock']
                                    ]), ENT_QUOTES); ?>'>
                                        <?php echo htmlspecialchars($prod['nombre']); ?> - 
                                        <?php echo formatear_precio($prod['precio']); ?> 
                                        (Stock: <?php echo $prod['stock']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <div id="carrito-productos">
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                                    <p>No hay productos en el carrito</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <h6 class="mb-3">Información de Venta</h6>
                            
                            <div class="mb-3">
                                <label class="form-label">Cliente *</label>
                                <select class="form-select" name="cliente_id" required>
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($clientes as $cli): ?>
                                        <option value="<?php echo $cli['id']; ?>">
                                            <?php echo htmlspecialchars($cli['nombre'] . ' ' . $cli['apellido']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Método de Pago *</label>
                                <select class="form-select" name="metodo_pago" required>
                                    <option value="efectivo">💵 Efectivo</option>
                                    <option value="tarjeta">💳 Tarjeta</option>
                                    <option value="transferencia">🔄 Transferencia</option>
                                </select>
                            </div>
                            
                            <hr>
                            
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="text-muted mb-2">TOTAL A PAGAR</h6>
                                    <h2 class="mb-0 text-success" id="total_display">$0.00</h2>
                                    <small class="text-muted" id="items_count">0 productos</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="btnGuardarVenta" disabled>
                        <i class="fas fa-check me-2"></i>Procesar Venta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$inline_script = <<<'JAVASCRIPT'
let carrito = [];

function formatearPrecio(precio) {
    return '$' + parseFloat(precio).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function agregarAlCarrito(producto) {
    const existe = carrito.find(p => p.id === parseInt(producto.id));
    
    if (existe) {
        if (existe.cantidad < producto.stock) {
            existe.cantidad++;
        } else {
            alert('Stock insuficiente');
            return;
        }
    } else {
        carrito.push({
            id: parseInt(producto.id),
            nombre: producto.nombre,
            precio: parseFloat(producto.precio),
            cantidad: 1,
            stock: parseInt(producto.stock)
        });
    }
    
    actualizarCarrito();
}

function actualizarCarrito() {
    const container = document.getElementById('carrito-productos');
    
    if (carrito.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                <p>No hay productos en el carrito</p>
            </div>
        `;
        document.getElementById('total_display').textContent = '$0.00';
        document.getElementById('items_count').textContent = '0 productos';
        document.getElementById('btnGuardarVenta').disabled = true;
        return;
    }
    
    let html = '<div class="list-group">';
    let total = 0;
    
    carrito.forEach((prod, index) => {
        const subtotal = prod.precio * prod.cantidad;
        total += subtotal;
        
        html += `
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="flex-grow-1">
                        <strong>${prod.nombre}</strong><br>
                        <small class="text-muted">
                            ${formatearPrecio(prod.precio)} × ${prod.cantidad} = 
                            <span class="text-success fw-bold">${formatearPrecio(subtotal)}</span>
                        </small>
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="cambiarCantidad(${index}, -1)">
                            <i class="fas fa-minus"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                            ${prod.cantidad}
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success" 
                                onclick="cambiarCantidad(${index}, 1)" 
                                ${prod.cantidad >= prod.stock ? 'disabled' : ''}>
                            <i class="fas fa-plus"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" onclick="eliminarDelCarrito(${index})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    
    container.innerHTML = html;
    document.getElementById('total_display').textContent = formatearPrecio(total);
    document.getElementById('items_count').textContent = carrito.length + ' producto' + (carrito.length !== 1 ? 's' : '');
    document.getElementById('total_venta').value = total.toFixed(2);
    document.getElementById('productos_json').value = JSON.stringify(carrito);
    document.getElementById('btnGuardarVenta').disabled = false;
}

function cambiarCantidad(index, cambio) {
    const producto = carrito[index];
    const nuevaCantidad = producto.cantidad + cambio;
    
    if (nuevaCantidad <= 0) {
        eliminarDelCarrito(index);
        return;
    }
    
    if (nuevaCantidad > producto.stock) {
        alert('Stock insuficiente');
        return;
    }
    
    producto.cantidad = nuevaCantidad;
    actualizarCarrito();
}

function eliminarDelCarrito(index) {
    if (confirm('¿Eliminar este producto?')) {
        carrito.splice(index, 1);
        actualizarCarrito();
    }
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    const productoSelect = document.getElementById('producto_select');
    
    productoSelect.addEventListener('change', function() {
        if (this.value) {
            try {
                const producto = JSON.parse(this.value);
                agregarAlCarrito(producto);
                this.value = '';
            } catch(e) {
                alert('Error al agregar producto');
            }
        }
    });
    
    // Resetear modal al cerrar
    document.getElementById('modalVenta').addEventListener('hidden.bs.modal', function() {
        carrito = [];
        actualizarCarrito();
        document.getElementById('formVenta').reset();
    });
    
    // Validar antes de enviar
    document.getElementById('formVenta').addEventListener('submit', function(e) {
        if (carrito.length === 0) {
            e.preventDefault();
            alert('Debe agregar al menos un producto');
            return false;
        }
        
        if (!document.querySelector('[name="cliente_id"]').value) {
            e.preventDefault();
            alert('Debe seleccionar un cliente');
            return false;
        }
    });
});
JAVASCRIPT;

include '../includes/footer.php';
?>