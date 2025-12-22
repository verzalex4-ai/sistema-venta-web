<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Verificar permisos (admin y repositor pueden acceder)
requiere_permiso('productos');

$producto_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($producto_id === 0) {
    header('Location: productos.php'); exit();;
}

// Procesar actualización de imagen
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_imagen'])) {
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['imagen']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed) && $_FILES['imagen']['size'] <= 2097152) {
            $new_filename = uniqid() . '.' . $ext;
            $upload_dir = 'uploads/productos/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $upload_dir . $new_filename)) {
                $imagen_path = $upload_dir . $new_filename;
                
                $sql = "UPDATE productos SET imagen = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $imagen_path, $producto_id);
                
                if ($stmt->execute()) {
                    $mensaje = 'Imagen actualizada correctamente';
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = 'Error al actualizar imagen';
                    $tipo_mensaje = 'danger';
                }
            }
        } else {
            $mensaje = 'Formato de imagen no válido o tamaño mayor a 2MB';
            $tipo_mensaje = 'danger';
        }
    }
}

// Obtener datos del producto
$sql = "SELECT p.*, c.nombre as categoria_nombre 
        FROM productos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $producto_id);
$stmt->execute();
$producto = $stmt->get_result()->fetch_assoc();

if (!$producto) {
    header('Location: productos.php'); exit();;
}
?>

$page_title = 'Detalle producto - Sistema de Ventas';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div id="content-wrapper" class="flex-fill">
<?php include "../includes/topbar.php"; ?>
<div class="container-fluid px-4 py-4">
<a class="navbar-brand" href="productos.php">
                <i class="fas fa-arrow-left me-2"></i>Volver a Productos
            </a>
            <span class="navbar-text text-white">
                <i class="fas fa-user me-2"></i><?php echo $_SESSION['usuario_nombre']; ?>
            </span>
        </div>
    </nav>

    <div class="container">
        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show">
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Columna de imagen -->
            <div class="col-lg-5">
                <div class="info-card">
                    <h5 class="mb-3">
                        <i class="fas fa-image me-2 text-primary"></i>Imagen del Producto
                    </h5>
                    <div class="product-image-large mb-3">
                        <?php if (!empty($producto['imagen']) && file_exists($producto['imagen'])): ?>
                            <img src="<?php echo htmlspecialchars($producto['imagen']); ?>" alt="<?php echo htmlspecialchars($producto['nombre']); ?>">
                        <?php else: ?>
                            <i class="fas fa-box"></i>
                        <?php endif; ?>
                    </div>

                    <!-- Formulario para actualizar imagen -->
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-upload me-2"></i>Subir nueva imagen
                            </label>
                            <input type="file" class="form-control" name="imagen" accept="image/*" required>
                            <small class="text-muted">Formatos: JPG, PNG, GIF (máx. 2MB)</small>
                        </div>
                        <button type="submit" name="actualizar_imagen" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Actualizar Imagen
                        </button>
                    </form>
                </div>
            </div>

            <!-- Columna de información -->
            <div class="col-lg-7">
                <div class="info-card">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h2 class="mb-2"><?php echo htmlspecialchars($producto['nombre']); ?></h2>
                            <span class="badge badge-custom bg-info text-white">
                                <i class="fas fa-tag me-2"></i><?php echo htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría'); ?>
                            </span>
                        </div>
                        <a href="productos.php" class="btn btn-outline-primary">
                            <i class="fas fa-edit me-2"></i>Editar Producto
                        </a>
                    </div>

                    <hr>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted mb-1">
                                    <i class="fas fa-barcode me-2"></i>Código
                                </label>
                                <h5><?php echo htmlspecialchars($producto['codigo'] ?? 'N/A'); ?></h5>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted mb-1">
                                    <i class="fas fa-dollar-sign me-2"></i>Precio
                                </label>
                                <h5 class="text-success"><?php echo formatear_precio($producto['precio']); ?></h5>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted mb-1">
                                    <i class="fas fa-boxes me-2"></i>Stock
                                </label>
                                <div>
                                    <?php
                                    $stock = $producto['stock'];
                                    $stock_minimo = $producto['stock_minimo'] ?? 0;
                                    $stock_class = 'stock-disponible';
                                    $stock_text = 'Stock Disponible';

                                    if ($stock_minimo > 0) {
                                        if ($stock <= $stock_minimo) {
                                            $stock_class = 'stock-critico';
                                            $stock_text = 'Stock Crítico';
                                        } elseif ($stock <= $stock_minimo * 2) {
                                            $stock_class = 'stock-bajo';
                                            $stock_text = 'Stock Bajo';
                                        }
                                    }
                                    ?>
                                    <span class="stock-indicator <?php echo $stock_class; ?>">
                                        <i class="fas fa-box me-2"></i>
                                        <?php echo $stock; ?> unidades - <?php echo $stock_text; ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted mb-1">
                                    <i class="fas fa-chart-line me-2"></i>Stock Mínimo
                                </label>
                                <h5><?php echo $producto['stock_minimo'] ?? 0; ?> unidades</h5>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="mb-3">
                                <label class="text-muted mb-1">
                                    <i class="fas fa-align-left me-2"></i>Descripción
                                </label>
                                <p class="lead">
                                    <?php echo nl2br(htmlspecialchars($producto['descripcion'] ?? 'Sin descripción')); ?>
                                </p>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted mb-1">
                                    <i class="fas fa-toggle-on me-2"></i>Estado
                                </label>
                                <div>
                                    <span class="badge badge-custom <?php echo $producto['estado'] ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo $producto['estado'] ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted mb-1">
                                    <i class="fas fa-calendar me-2"></i>Fecha de Creación
                                </label>
                                <p><?php echo date('d/m/Y H:i', strtotime($producto['fecha_creacion'] ?? 'now')); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones de acción -->
                <div class="info-card">
                    <div class="d-flex gap-2">
                        <a href="productos.php" class="btn btn-secondary flex-fill">
                            <i class="fas fa-arrow-left me-2"></i>Volver a la Lista
                        </a>
                        <a href="../index.php" target="_blank" class="btn btn-info flex-fill">
                            <i class="fas fa-store me-2"></i>Ver en Catálogo Público
                        </a>
                    </div>
                </div>
</div></div>

<?php include "../includes/footer.php"; ?>