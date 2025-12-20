<?php
require_once 'config.php';

// Verificar permisos (admin y repositor pueden acceder)
requiere_permiso('productos');

$producto_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($producto_id === 0) {
    redirigir('productos.php');
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
    redirigir('productos.php');
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Producto - <?php echo htmlspecialchars($producto['nombre']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style-minimal.css">
    <style>
        .product-image-large {
            width: 100%;
            max-height: 500px;
            background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-200) 100%);
            border-radius: var(--border-radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .product-image-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-image-large i {
            font-size: 10rem;
            color: var(--gray-400);
        }

        .info-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 0 20px rgba(0, 0, 0, .05);
            margin-bottom: 2rem;
        }

        .badge-custom {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            border-radius: 20px;
        }

        .stock-indicator {
            display: inline-flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
        }

        .stock-critico {
            background-color: #fee;
            color: #dc3545;
        }

        .stock-bajo {
            background-color: #fff3cd;
            color: #856404;
        }

        .stock-disponible {
            background-color: #d4edda;
            color: #155724;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container-fluid">
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
                        <a href="index.php" target="_blank" class="btn btn-info flex-fill">
                            <i class="fas fa-store me-2"></i>Ver en Catálogo Público
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
