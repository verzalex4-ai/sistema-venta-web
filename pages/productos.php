<?php
/**
 * PRODUCTOS - Versión optimizada
 */
require_once '../config.php';

requiere_permiso('productos');

// ============================================
// PROCESAR ACCIONES
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    
    try {
        if ($accion === 'crear' || $accion === 'editar') {
            $id = $accion === 'editar' ? intval($_POST['id']) : 0;
            $codigo = limpiar_entrada($_POST['codigo']);
            $nombre = limpiar_entrada($_POST['nombre']);
            $descripcion = limpiar_entrada($_POST['descripcion']);
            $precio = floatval($_POST['precio']);
            $stock = intval($_POST['stock']);
            $categoria_id = intval($_POST['categoria_id']);
            
            // Procesar imagen
            $imagen = null;
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === 0) {
                $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
                
                if (in_array($ext, ALLOWED_EXTENSIONS) && $_FILES['imagen']['size'] <= MAX_FILE_SIZE) {
                    $new_filename = uniqid() . '.' . $ext;
                    $upload_dir = UPLOAD_DIR . 'productos/';
                    
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    if (move_uploaded_file($_FILES['imagen']['tmp_name'], $upload_dir . $new_filename)) {
                        $imagen = 'uploads/productos/' . $new_filename;
                    }
                }
            }
            
            if ($accion === 'crear') {
                $sql = "INSERT INTO productos (codigo, nombre, descripcion, imagen, precio, stock, categoria_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssdii", $codigo, $nombre, $descripcion, $imagen, $precio, $stock, $categoria_id);
                $stmt->execute();
                
                set_mensaje('Producto creado exitosamente', 'success');
            } else {

                if ($imagen) {
                    $sql = "UPDATE productos SET codigo=?, nombre=?, descripcion=?, imagen=?, precio=?, stock=?, categoria_id=? WHERE id=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssdiiii", $codigo, $nombre, $descripcion, $imagen, $precio, $stock, $categoria_id, $id);
                } else {
                    $sql = "UPDATE productos SET codigo=? nombre=?, descripcion=?, precio=?, stock=?, categoria_id=? WHERE id=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssdiii", $codigo, $nombre, $descripcion, $precio, $stock, $categoria_id, $id);
                }
                $stmt->execute();
                
                set_mensaje('Producto actualizado exitosamente', 'success');
            }
        } elseif ($accion === 'eliminar') {
            $id = intval($_POST['id']);
            $conn->query("UPDATE productos SET estado=0 WHERE id=$id");
            set_mensaje('Producto desactivado exitosamente', 'success');
        }
        
        // Redirigir para limpiar POST
        header('Location: productos.php');
        exit;
        
    } catch (Exception $e) {
        set_mensaje('Error: ' . $e->getMessage(), 'danger');
    }
}

// ============================================
// OBTENER DATOS
// ============================================

// Alertas de stock
$productos_criticos = $conn->query("SELECT COUNT(*) as total FROM productos WHERE stock <= 5 AND estado=1")->fetch_assoc()['total'];
$productos_bajo = $conn->query("SELECT COUNT(*) as total FROM productos WHERE stock > 5 AND stock <= 10 AND estado=1")->fetch_assoc()['total'];
$total_alertas = $productos_criticos + $productos_bajo;

// Productos con stock bajo
$productos_alerta = $conn->query("
    SELECT p.*, c.nombre as categoria_nombre 
    FROM productos p 
    LEFT JOIN categorias c ON p.categoria_id = c.id 
    WHERE p.stock <= 10 AND p.estado=1 
    ORDER BY p.stock ASC
")->fetch_all(MYSQLI_ASSOC);

// Todos los productos
$productos = $conn->query("
    SELECT p.*, c.nombre as categoria_nombre 
    FROM productos p 
    LEFT JOIN categorias c ON p.categoria_id = c.id 
    WHERE p.estado=1
    ORDER BY p.id DESC
")->fetch_all(MYSQLI_ASSOC);

// Categorías
$categorias = $conn->query("SELECT * FROM categorias WHERE estado=1 ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);

$page_title = 'Productos - Sistema de Ventas';
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
                <i class="fas fa-box me-2"></i>Gestión de Productos
            </h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProducto">
                <i class="fas fa-plus me-2"></i>Nuevo Producto
            </button>
        </div>
        
        <!-- Alertas de Stock -->
        <?php if ($total_alertas > 0): ?>
        <div class="alert alert-warning alert-dismissible fade show mb-4">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                    <div>
                        <h5 class="mb-1">⚠️ Productos con Stock Bajo</h5>
                        <p class="mb-0">
                            <?php if ($productos_criticos > 0): ?>
                                <span class="badge bg-danger me-2"><?php echo $productos_criticos; ?> críticos</span>
                            <?php endif; ?>
                            <?php if ($productos_bajo > 0): ?>
                                <span class="badge bg-warning text-dark"><?php echo $productos_bajo; ?> bajos</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-warning" 
                        data-bs-toggle="collapse" data-bs-target="#alertaStockDetalle">
                    <i class="fas fa-eye"></i> Ver Detalles
                </button>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        
        <div class="collapse mb-4" id="alertaStockDetalle">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0"><i class="fas fa-boxes"></i> Productos que Requieren Atención</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Categoría</th>
                                    <th class="text-center">Stock</th>
                                    <th class="text-end">Precio</th>
                                    <th class="text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productos_alerta as $prod): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($prod['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($prod['categoria_nombre'] ?? 'Sin categoría'); ?></td>
                                    <td class="text-center">
                                        <span class="badge <?php echo $prod['stock'] <= 5 ? 'bg-danger' : 'bg-warning text-dark'; ?>">
                                            <?php echo $prod['stock']; ?>
                                        </span>
                                    </td>
                                    <td class="text-end"><?php echo formatear_precio($prod['precio']); ?></td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-primary" 
                                                onclick='editarProducto(<?php echo json_encode($prod); ?>)'>
                                            <i class="fas fa-edit"></i> Actualizar
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Tabla de Productos -->
        <div class="card">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Lista de Productos</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Categoría</th>
                                <th>Precio</th>
                                <th>Stock</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $prod): ?>
                            <tr>
                                <td><?php echo $prod['id']; ?></td>
                                <td><code><?php echo htmlspecialchars($prod['codigo']); ?></code></td>
                                <td>
                                    <?php if ($prod['stock'] <= 5): ?>
                                        <i class="fas fa-exclamation-circle text-danger me-1"></i>
                                    <?php elseif ($prod['stock'] <= 10): ?>
                                        <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                                    <?php endif; ?>
                                    <strong><?php echo htmlspecialchars($prod['nombre']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo htmlspecialchars($prod['categoria_nombre'] ?? 'Sin categoría'); ?>
                                    </span>
                                </td>
                                <td><?php echo formatear_precio($prod['precio']); ?></td>
                                <td>
                                    <span class="badge <?php 
                                        echo $prod['stock'] <= 5 ? 'bg-danger' : 
                                            ($prod['stock'] <= 10 ? 'bg-warning text-dark' : 'bg-success'); 
                                    ?>">
                                        <?php echo $prod['stock']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $prod['estado'] ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo $prod['estado'] ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="detalle_producto.php?id=<?php echo $prod['id']; ?>" 
                                       class="btn btn-primary btn-sm" title="Ver detalle">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button class="btn btn-info btn-sm" 
                                            onclick='editarProducto(<?php echo json_encode($prod); ?>)' 
                                            title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display:inline;" 
                                          onsubmit="return confirm('¿Desactivar este producto?');">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id" value="<?php echo $prod['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
    </div>
</div>

<!-- Modal Producto -->
<div class="modal fade" id="modalProducto" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Nuevo Producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="formProducto">
                <div class="modal-body">
                    <input type="hidden" name="accion" id="accion" value="crear">
                    <input type="hidden" name="id" id="producto_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Código *</label>
                        <input type="text" class="form-control" name="codigo" id="codigo" required 
                            placeholder="Ej: ELEC-001, PROD-123">
                        <small class="text-muted">Código único del producto (letras, números, guiones)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" id="descripcion" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Imagen</label>
                        <input type="file" class="form-control" name="imagen" accept="image/*">
                        <small class="text-muted">JPG, PNG, GIF (máx. 2MB)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Categoría *</label>
                        <select class="form-select" name="categoria_id" id="categoria_id" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>">
                                    <?php echo htmlspecialchars($cat['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Precio *</label>
                            <input type="number" step="0.01" class="form-control" 
                                   name="precio" id="precio" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stock *</label>
                            <input type="number" class="form-control" name="stock" id="stock" required>
                        </div>
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

<?php
$inline_script = <<<'JAVASCRIPT'
function editarProducto(producto) {
    document.getElementById('modalTitle').textContent = 'Editar Producto';
    document.getElementById('accion').value = 'editar';
    document.getElementById('producto_id').value = producto.id;
    document.getElementById('codigo').value = producto.codigo || '';
    document.getElementById('nombre').value = producto.nombre;
    document.getElementById('descripcion').value = producto.descripcion || '';
    document.getElementById('categoria_id').value = producto.categoria_id;
    document.getElementById('precio').value = producto.precio;
    document.getElementById('stock').value = producto.stock;
    
    new bootstrap.Modal(document.getElementById('modalProducto')).show();
}

// Resetear modal al cerrar
document.getElementById('modalProducto').addEventListener('hidden.bs.modal', function() {
    document.getElementById('formProducto').reset();
    document.getElementById('modalTitle').textContent = 'Nuevo Producto';
    document.getElementById('accion').value = 'crear';
});
JAVASCRIPT;

include '../includes/footer.php';
?>