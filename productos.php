<?php
require_once 'config.php';

// Verificar permisos (admin y repositor pueden acceder)
requiere_permiso('productos');

// Procesar acciones (Crear, Editar, Eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        $accion = $_POST['accion'];

        if ($accion === 'crear') {
            $nombre = limpiar_entrada($_POST['nombre']);
            $descripcion = limpiar_entrada($_POST['descripcion']);
            $precio = floatval($_POST['precio']);
            $stock = intval($_POST['stock']);
            $categoria_id = intval($_POST['categoria_id']);
            
            // Procesar imagen
            $imagen = null;
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['imagen']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed) && $_FILES['imagen']['size'] <= 2097152) { // 2MB
                    $new_filename = uniqid() . '.' . $ext;
                    $upload_dir = 'uploads/productos/';
                    
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    if (move_uploaded_file($_FILES['imagen']['tmp_name'], $upload_dir . $new_filename)) {
                        $imagen = $upload_dir . $new_filename;
                    }
                }
            }

            $sql = "INSERT INTO productos (nombre, descripcion, imagen, precio, stock, categoria_id) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssdii", $nombre, $descripcion, $imagen, $precio, $stock, $categoria_id);

            if ($stmt->execute()) {
                $mensaje = "Producto creado exitosamente";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al crear producto";
                $tipo_mensaje = "danger";
            }
        }

        if ($accion === 'editar') {
            $id = intval($_POST['id']);
            $nombre = limpiar_entrada($_POST['nombre']);
            $descripcion = limpiar_entrada($_POST['descripcion']);
            $precio = floatval($_POST['precio']);
            $stock = intval($_POST['stock']);
            $categoria_id = intval($_POST['categoria_id']);
            
            // Procesar nueva imagen si se subió
            $imagen_sql = "";
            $imagen_param = null;
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
                        $imagen_param = $upload_dir . $new_filename;
                        $imagen_sql = ", imagen=?";
                    }
                }
            }

            if ($imagen_param) {
                $sql = "UPDATE productos SET nombre=?, descripcion=?, precio=?, stock=?, categoria_id=?" . $imagen_sql . " WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssdisi", $nombre, $descripcion, $precio, $stock, $categoria_id, $imagen_param, $id);
            } else {
                $sql = "UPDATE productos SET nombre=?, descripcion=?, precio=?, stock=?, categoria_id=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssdiii", $nombre, $descripcion, $precio, $stock, $categoria_id, $id);
            }

            if ($stmt->execute()) {
                $mensaje = "Producto actualizado exitosamente";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al actualizar producto";
                $tipo_mensaje = "danger";
            }
        }

        if ($accion === 'eliminar') {
            $id = intval($_POST['id']);
            $sql = "UPDATE productos SET estado=0 WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                $mensaje = "Producto desactivado exitosamente";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al desactivar producto";
                $tipo_mensaje = "danger";
            }
        }
    }
}

// Obtener productos con stock bajo
$sql_stock_bajo = "SELECT COUNT(*) as total_critico FROM productos WHERE stock <= 5 AND estado=1";
$stock_critico = $conn->query($sql_stock_bajo)->fetch_assoc()['total_critico'];

$sql_stock_bajo2 = "SELECT COUNT(*) as total_bajo FROM productos WHERE stock > 5 AND stock <= 10 AND estado=1";
$stock_bajo = $conn->query($sql_stock_bajo2)->fetch_assoc()['total_bajo'];

// Obtener productos
$sql_productos = "SELECT p.*, c.nombre as categoria_nombre FROM productos p 
                  LEFT JOIN categorias c ON p.categoria_id = c.id 
                  WHERE p.estado=1
                  ORDER BY p.stock ASC, p.id DESC";
$result_productos = $conn->query($sql_productos);

// Obtener categorías para el select (con iconos)
$sql_categorias = "SELECT c.*, ci.icono 
                   FROM categorias c 
                   LEFT JOIN categorias_iconos ci ON c.id = ci.categoria_id 
                   WHERE c.estado=1 
                   ORDER BY c.nombre";
$result_categorias = $conn->query($sql_categorias);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - Sistema de Ventas</title>
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

        /* Estilos para alertas de stock */
        .alert-stock {
            border-left: 4px solid #f6c23e;
            background: linear-gradient(90deg, #fff3cd 0%, #ffffff 100%);
            animation: pulseWarning 2s ease-in-out infinite;
        }

        .stock-critico {
            background-color: #fee !important;
            border-left: 4px solid var(--danger) !important;
        }

        .stock-bajo {
            background-color: #fff9e6 !important;
            border-left: 4px solid var(--warning) !important;
        }

        .alert-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            background-color: var(--danger);
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: bold;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        @keyframes pulseWarning {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(246, 194, 62, 0.4);
            }

            50% {
                box-shadow: 0 0 0 10px rgba(246, 194, 62, 0);
            }
        }

        .stock-critico {
            background-color: #f8d7da !important;
        }

        .stock-bajo {
            background-color: #fff3cd !important;
        }

        .stock-card {
            transition: all 0.3s;
            cursor: pointer;
        }

        .stock-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
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
                <a class="nav-link active" href="productos.php" style="position: relative;">
                    <i class="fas fa-fw fa-box"></i><span>Productos</span>
                    <?php if ($stock_critico + $stock_bajo > 0): ?>
                        <span class="alert-badge"><?php echo $stock_critico + $stock_bajo; ?></span>
                    <?php endif; ?>
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

                <!-- ALERTA DE STOCK BAJO - NUEVA SECCIÓN -->
                <?php if ($stock_critico > 0 || $stock_bajo > 0): ?>
                    <div class="alert alert-stock alert-dismissible fade show mb-4" role="alert">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-exclamation-triangle fa-2x me-3 text-warning"></i>
                                <div>
                                    <h5 class="mb-1">⚠️ Atención: Productos con Stock Bajo</h5>
                                    <p class="mb-0">
                                        <?php if ($stock_critico > 0): ?>
                                            <span class="badge bg-danger me-2"><?php echo $stock_critico; ?> productos en stock crítico</span>
                                        <?php endif; ?>
                                        <?php if ($stock_bajo > 0): ?>
                                            <span class="badge bg-warning text-dark"><?php echo $stock_bajo; ?> productos en stock bajo</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="collapse" data-bs-target="#alertaStockDetalle">
                                <i class="fas fa-eye"></i> Ver Detalles
                            </button>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>

                    <!-- Detalle de productos con stock bajo -->
                    <div class="collapse mb-4" id="alertaStockDetalle">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h6 class="mb-0"><i class="fas fa-boxes"></i> Productos que Requieren Atención</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <?php
                                    $sql_productos_bajos = "SELECT p.*, c.nombre as categoria_nombre 
                                                        FROM productos p 
                                                        LEFT JOIN categorias c ON p.categoria_id = c.id 
                                                        WHERE p.stock <= 10 AND p.estado=1 
                                                        ORDER BY p.stock ASC";
                                    $result_bajos = $conn->query($sql_productos_bajos);

                                    while ($prod_bajo = $result_bajos->fetch_assoc()):
                                        $es_critico = $prod_bajo['stock'] <= 5;
                                    ?>
                                        <div class="col-md-4">
                                            <div class="card stock-card <?php echo $es_critico ? 'border-danger' : 'border-warning'; ?>">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <h6 class="card-title mb-1"><?php echo $prod_bajo['nombre']; ?></h6>
                                                            <small class="text-muted"><?php echo $prod_bajo['categoria_nombre']; ?></small>
                                                        </div>
                                                        <span class="badge <?php echo $es_critico ? 'bg-danger' : 'bg-warning text-dark'; ?>" style="font-size: 1rem;">
                                                            <?php echo $prod_bajo['stock']; ?>
                                                        </span>
                                                    </div>
                                                    <hr>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small class="<?php echo $es_critico ? 'text-danger' : 'text-warning'; ?>">
                                                            <i class="fas fa-arrow-down"></i>
                                                            <?php echo $es_critico ? 'Stock crítico' : 'Stock bajo'; ?>
                                                        </small>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="editarProducto(<?php echo htmlspecialchars(json_encode($prod_bajo)); ?>)">
                                                            <i class="fas fa-edit"></i> Actualizar
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <!-- FIN DE ALERTA DE STOCK BAJO -->

                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Gestión de Productos</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProducto">
                        <i class="fas fa-plus"></i> Nuevo Producto
                    </button>
                </div>

                <div class="card">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold" style="color: var(--primary);">Lista de Productos</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Descripción</th>
                                        <th>Categoría</th>
                                        <th>Precio</th>
                                        <th>Stock</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result_productos->fetch_assoc()): ?>
                                        <tr class="<?php echo $row['stock'] <= 5 ? 'stock-critico' : ($row['stock'] <= 10 ? 'stock-bajo' : ''); ?>">
                                            <td><?php echo $row['id']; ?></td>
                                            <td>
                                                <?php if ($row['stock'] <= 5): ?>
                                                    <i class="fas fa-exclamation-circle text-danger me-1" title="Stock crítico"></i>
                                                <?php elseif ($row['stock'] <= 10): ?>
                                                    <i class="fas fa-exclamation-triangle text-warning me-1" title="Stock bajo"></i>
                                                <?php endif; ?>
                                                <strong><?php echo $row['nombre']; ?></strong>
                                            </td>
                                            <td><?php echo substr($row['descripcion'], 0, 50); ?>...</td>
                                            <td><span class="badge bg-info"><?php echo $row['categoria_nombre']; ?></span></td>
                                            <td><?php echo formatear_precio($row['precio']); ?></td>
                                            <td>
                                                <?php
                                                $badge_class = 'bg-success';
                                                if ($row['stock'] <= 5) {
                                                    $badge_class = 'bg-danger';
                                                } elseif ($row['stock'] <= 10) {
                                                    $badge_class = 'bg-warning text-dark';
                                                }
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php echo $row['stock']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $row['estado'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo $row['estado'] ? 'Activo' : 'Inactivo'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-primary btn-sm me-1" onclick="verDetalleProducto(<?php echo $row['id']; ?>)" title="Ver detalle">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-info btn-sm me-1" onclick="editarProducto(<?php echo htmlspecialchars(json_encode($row)); ?>)" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm" onclick="eliminarProducto(<?php echo $row['id']; ?>)" title="Eliminar">
                                                    <i class="fas fa-trash"></i>
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

    <!-- Modal Producto -->
    <div class="modal fade" id="modalProducto" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nuevo Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formProducto" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="accion" id="accion" value="crear">
                        <input type="hidden" name="id" id="producto_id">

                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" class="form-control" name="nombre" id="nombre" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" name="descripcion" id="descripcion" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Imagen del Producto</label>
                            <input type="file" class="form-control" name="imagen" id="imagen" accept="image/*">
                            <small class="text-muted">Formatos: JPG, PNG, GIF (máx. 2MB)</small>
                            <div id="preview_imagen" class="mt-2"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Categoría</label>
                            <select class="form-select" name="categoria_id" id="categoria_id" required>
                                <option value="">Seleccionar...</option>
                                <?php
                                $result_categorias->data_seek(0);
                                while ($cat = $result_categorias->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo $cat['nombre']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Precio</label>
                                <input type="number" step="0.01" class="form-control" name="precio" id="precio" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stock</label>
                                <input type="number" class="form-control" name="stock" id="stock" required>
                                <small class="text-muted">Alerta: menos de 10 unidades</small>
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function editarProducto(producto) {
            document.getElementById('modalTitle').innerText = 'Editar Producto';
            document.getElementById('accion').value = 'editar';
            document.getElementById('producto_id').value = producto.id;
            document.getElementById('nombre').value = producto.nombre;
            document.getElementById('descripcion').value = producto.descripcion;
            document.getElementById('categoria_id').value = producto.categoria_id;
            document.getElementById('precio').value = producto.precio;
            document.getElementById('stock').value = producto.stock;

            new bootstrap.Modal(document.getElementById('modalProducto')).show();
        }

        function verDetalleProducto(id) {
            // Redirigir a página de detalle
            window.location.href = 'detalle_producto.php?id=' + id;
        }

        function eliminarProducto(id) {
            if (confirm('¿Está seguro de eliminar este producto?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        document.getElementById('modalProducto').addEventListener('hidden.bs.modal', function() {
            document.getElementById('formProducto').reset();
            document.getElementById('modalTitle').innerText = 'Nuevo Producto';
            document.getElementById('accion').value = 'crear';
        });
    </script>
</body>

</html>