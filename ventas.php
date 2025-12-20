<?php
require_once 'config.php';

// Verificar permisos (admin y vendedor pueden acceder)
requiere_permiso('ventas');

// Procesar nueva venta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'crear_venta') {
        $cliente_id = intval($_POST['cliente_id']);
        $metodo_pago = limpiar_entrada($_POST['metodo_pago']);
        $productos_json = $_POST['productos'];
        $total = floatval($_POST['total']);

        // VALIDACIÓN: Verificar que productos_json no esté vacío
        if (empty($productos_json)) {
            $mensaje = "Error: No hay productos en el carrito";
            $tipo_mensaje = "danger";
        } else {
            $productos = json_decode($productos_json, true);

            // VALIDACIÓN: Verificar que se decodificó correctamente
            if ($productos === null || !is_array($productos) || count($productos) === 0) {
                $mensaje = "Error: No se pudieron procesar los productos del carrito";
                $tipo_mensaje = "danger";
            } else {
                // Iniciar transacción
                $conn->begin_transaction();

                try {
                    // Insertar venta
                    $sql_venta = "INSERT INTO ventas (cliente_id, usuario_id, total, metodo_pago) VALUES (?, 1, ?, ?)";
                    $stmt_venta = $conn->prepare($sql_venta);
                    $stmt_venta->bind_param("ids", $cliente_id, $total, $metodo_pago);
                    $stmt_venta->execute();
                    $venta_id = $conn->insert_id;

                    // Insertar detalles y actualizar stock
                    foreach ($productos as $prod) {
                        $producto_id = intval($prod['id']);
                        $cantidad = intval($prod['cantidad']);
                        $precio = floatval($prod['precio']);
                        $subtotal = $cantidad * $precio;

                        // Insertar detalle
                        $sql_detalle = "INSERT INTO detalle_ventas (venta_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)";
                        $stmt_detalle = $conn->prepare($sql_detalle);
                        $stmt_detalle->bind_param("iiidd", $venta_id, $producto_id, $cantidad, $precio, $subtotal);
                        $stmt_detalle->execute();

                        // Actualizar stock
                        $sql_stock = "UPDATE productos SET stock = stock - ? WHERE id = ?";
                        $stmt_stock = $conn->prepare($sql_stock);
                        $stmt_stock->bind_param("ii", $cantidad, $producto_id);
                        $stmt_stock->execute();
                    }

                    $conn->commit();
                    $mensaje = "✅ Venta #" . $venta_id . " registrada exitosamente";
                    $tipo_mensaje = "success";
                } catch (Exception $e) {
                    $conn->rollback();
                    $mensaje = "Error al registrar venta: " . $e->getMessage();
                    $tipo_mensaje = "danger";
                }
            }
        }
    }
}

// Filtros
$filtro_cliente = isset($_GET['cliente']) ? intval($_GET['cliente']) : 0;
$filtro_metodo = isset($_GET['metodo']) ? limpiar_entrada($_GET['metodo']) : '';
$filtro_fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$filtro_fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';
$busqueda = isset($_GET['buscar']) ? limpiar_entrada($_GET['buscar']) : '';

// Construir query con filtros
$sql_ventas = "SELECT v.*, c.nombre as cliente_nombre, c.apellido as cliente_apellido 
               FROM ventas v 
               INNER JOIN clientes c ON v.cliente_id = c.id 
               WHERE 1=1";

if ($filtro_cliente > 0) {
    $sql_ventas .= " AND v.cliente_id = " . $filtro_cliente;
}

if ($filtro_metodo) {
    $sql_ventas .= " AND v.metodo_pago = '" . $filtro_metodo . "'";
}

if ($filtro_fecha_desde) {
    $sql_ventas .= " AND DATE(v.fecha_creacion) >= '" . $filtro_fecha_desde . "'";
}

if ($filtro_fecha_hasta) {
    $sql_ventas .= " AND DATE(v.fecha_creacion) <= '" . $filtro_fecha_hasta . "'";
}

if ($busqueda) {
    $sql_ventas .= " AND (c.nombre LIKE '%" . $busqueda . "%' OR c.apellido LIKE '%" . $busqueda . "%' OR v.id LIKE '%" . $busqueda . "%')";
}

$sql_ventas .= " ORDER BY v.id DESC LIMIT 100";
$result_ventas = $conn->query($sql_ventas);

// Calcular totales con filtros
$sql_totales = "SELECT COUNT(*) as cantidad, SUM(v.total) as total_ingresos
                FROM ventas v 
                INNER JOIN clientes c ON v.cliente_id = c.id 
                WHERE v.estado='completada'";

if ($filtro_cliente > 0) $sql_totales .= " AND v.cliente_id = " . $filtro_cliente;
if ($filtro_metodo) $sql_totales .= " AND v.metodo_pago = '" . $filtro_metodo . "'";
if ($filtro_fecha_desde) $sql_totales .= " AND DATE(v.fecha_creacion) >= '" . $filtro_fecha_desde . "'";
if ($filtro_fecha_hasta) $sql_totales .= " AND DATE(v.fecha_creacion) <= '" . $filtro_fecha_hasta . "'";
if ($busqueda) $sql_totales .= " AND (c.nombre LIKE '%" . $busqueda . "%' OR c.apellido LIKE '%" . $busqueda . "%' OR v.id LIKE '%" . $busqueda . "%')";

$totales = $conn->query($sql_totales)->fetch_assoc();

// Obtener clientes activos
$sql_clientes = "SELECT * FROM clientes WHERE estado=1 ORDER BY nombre";
$result_clientes = $conn->query($sql_clientes);

// Obtener productos activos
$sql_productos = "SELECT * FROM productos WHERE estado=1 AND stock > 0 ORDER BY nombre";
$result_productos = $conn->query($sql_productos);

// Obtener clientes para filtro
$sql_clientes_filtro = "SELECT * FROM clientes WHERE estado=1 ORDER BY nombre";
$result_clientes_filtro = $conn->query($sql_clientes_filtro);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas - Sistema de Ventas</title>
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

        #carrito-productos {
            max-height: 300px;
            overflow-y: auto;
        }

        .producto-item {
            border-bottom: 1px solid #e3e6f0;
            padding: 10px 0;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .total-venta {
            font-size: 2rem;
            font-weight: bold;
            color: var(--success);
            animation: pulse 0.5s ease;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
        }

        .filtros-card {
            background-color: #f8f9fc;
            border: 1px solid #e3e6f0;
            border-radius: 0.35rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .stat-box {
            background: white;
            padding: 1rem;
            border-radius: 0.35rem;
            border-left: 3px solid var(--primary);
        }

        .empty-cart {
            text-align: center;
            padding: 2rem;
            color: #999;
        }

        .empty-cart i {
            font-size: 3rem;
            margin-bottom: 1rem;
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
                <a class="nav-link" href="dashboard.php">
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
            
            <hr class="sidebar-divider" style="border-color: rgba(255,255,255,.2)">
            <div class="sidebar-heading">Operaciones</div>
            <li class="nav-item">
                <a class="nav-link active" href="ventas.php">
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

                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Gestión de Ventas</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalVenta">
                        <i class="fas fa-plus"></i> Nueva Venta
                    </button>
                </div>

                <!-- Estadísticas rápidas -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="stat-box">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">Ventas Filtradas</small>
                                    <h4 class="mb-0"><?php echo $totales['cantidad'] ?? 0; ?></h4>
                                </div>
                                <i class="fas fa-shopping-cart fa-2x text-primary"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stat-box" style="border-left-color: var(--success)">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">Ingresos Totales</small>
                                    <h4 class="mb-0 text-success"><?php echo formatear_precio($totales['total_ingresos'] ?? 0); ?></h4>
                                </div>
                                <i class="fas fa-dollar-sign fa-2x text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="filtros-card">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label"><i class="fas fa-search"></i> Buscar</label>
                            <input type="text" class="form-control" name="buscar" value="<?php echo $busqueda; ?>" placeholder="ID, Cliente...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><i class="fas fa-user"></i> Cliente</label>
                            <select class="form-select" name="cliente">
                                <option value="">Todos</option>
                                <?php while ($cli = $result_clientes_filtro->fetch_assoc()): ?>
                                    <option value="<?php echo $cli['id']; ?>" <?php echo $filtro_cliente == $cli['id'] ? 'selected' : ''; ?>>
                                        <?php echo $cli['nombre'] . ' ' . $cli['apellido']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><i class="fas fa-credit-card"></i> Método</label>
                            <select class="form-select" name="metodo">
                                <option value="">Todos</option>
                                <option value="efectivo" <?php echo $filtro_metodo == 'efectivo' ? 'selected' : ''; ?>>Efectivo</option>
                                <option value="tarjeta" <?php echo $filtro_metodo == 'tarjeta' ? 'selected' : ''; ?>>Tarjeta</option>
                                <option value="transferencia" <?php echo $filtro_metodo == 'transferencia' ? 'selected' : ''; ?>>Transferencia</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><i class="fas fa-calendar"></i> Desde</label>
                            <input type="date" class="form-control" name="fecha_desde" value="<?php echo $filtro_fecha_desde; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><i class="fas fa-calendar"></i> Hasta</label>
                            <input type="date" class="form-control" name="fecha_hasta" value="<?php echo $filtro_fecha_hasta; ?>">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filtrar
                            </button>
                            <a href="ventas.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Limpiar
                            </a>
                            <button type="button" class="btn btn-success" onclick="window.location.href='exportar_excel.php?tipo=ventas'">
                                <i class="fas fa-file-excel"></i> Exportar
                            </button>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold" style="color: var(--primary);">Historial de Ventas</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Cliente</th>
                                        <th>Total</th>
                                        <th>Método de Pago</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($result_ventas->num_rows > 0):
                                        while ($row = $result_ventas->fetch_assoc()):
                                    ?>
                                            <tr>
                                                <td><strong>#<?php echo str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                                                <td>
                                                    <i class="fas fa-user-circle text-primary"></i>
                                                    <?php echo $row['cliente_nombre'] . ' ' . $row['cliente_apellido']; ?>
                                                </td>
                                                <td class="text-success fw-bold"><?php echo formatear_precio($row['total']); ?></td>
                                                <td>
                                                    <?php
                                                    $iconos = [
                                                        'efectivo' => 'fa-money-bill-wave',
                                                        'tarjeta' => 'fa-credit-card',
                                                        'transferencia' => 'fa-exchange-alt'
                                                    ];
                                                    ?>
                                                    <span class="badge bg-info">
                                                        <i class="fas <?php echo $iconos[$row['metodo_pago']] ?? 'fa-wallet'; ?>"></i>
                                                        <?php echo ucfirst($row['metodo_pago']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success">
                                                        <?php echo ucfirst($row['estado']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatear_fecha($row['fecha_creacion']); ?></td>
                                                <td>
                                                    <button class="btn btn-info btn-sm" onclick="verDetalle(<?php echo $row['id']; ?>)" title="Ver Detalle">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-primary btn-sm" onclick="imprimirTicket(<?php echo $row['id']; ?>)" title="Imprimir">
                                                        <i class="fas fa-print"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php
                                        endwhile;
                                    else:
                                        ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-3x mb-3"></i><br>
                                                No se encontraron ventas con los filtros aplicados
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
                                <h6 class="mb-3"><i class="fas fa-box"></i> Seleccionar Productos</h6>
                                <div class="mb-3">
                                    <select class="form-select" id="producto_select">
                                        <option value="">Seleccionar producto...</option>
                                        <?php
                                        while ($prod = $result_productos->fetch_assoc()):
                                            // Crear array limpio solo con datos necesarios
                                            $producto_datos = [
                                                'id' => $prod['id'],
                                                'nombre' => $prod['nombre'],
                                                'precio' => $prod['precio'],
                                                'stock' => $prod['stock']
                                            ];
                                        ?>
                                            <option value='<?php echo htmlspecialchars(json_encode($producto_datos), ENT_QUOTES, 'UTF-8'); ?>'>
                                                <?php echo $prod['nombre'] . ' - ' . formatear_precio($prod['precio']) . ' (Stock: ' . $prod['stock'] . ')'; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>


                                <div id="carrito-productos">
                                    <div class="empty-cart">
                                        <i class="fas fa-shopping-cart"></i>
                                        <p>No hay productos agregados al carrito</p>
                                        <small class="text-muted">Selecciona productos del menú desplegable</small>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <h6 class="mb-3"><i class="fas fa-info-circle"></i> Información de Venta</h6>

                                <div class="mb-3">
                                    <label class="form-label">Cliente *</label>
                                    <select class="form-select" name="cliente_id" required>
                                        <option value="">Seleccionar...</option>
                                        <?php
                                        $result_clientes->data_seek(0);
                                        while ($cli = $result_clientes->fetch_assoc()):
                                        ?>
                                            <option value="<?php echo $cli['id']; ?>">
                                                <?php echo $cli['nombre'] . ' ' . $cli['apellido']; ?>
                                            </option>
                                        <?php endwhile; ?>
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
                                        <div class="total-venta" id="total_display">$0.00</div>
                                        <small class="text-muted" id="items_count">0 productos</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-success" id="btnGuardarVenta" disabled>
                            <i class="fas fa-check"></i> Procesar Venta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>


        // Variables GLOBALES
        var carrito = [];

        // Función para formatear precio (CORREGIDA)
        function formatearPrecio(precio) {
            return '$' + parseFloat(precio).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        // Función para agregar al carrito (GLOBAL)
        function agregarAlCarrito(producto) {
            console.log('Agregando al carrito:', producto);

            // Convertir id a número
            const productoId = parseInt(producto.id);
            const existe = carrito.find(p => p.id === productoId);

            if (existe) {
                console.log('Producto ya existe, aumentando cantidad');
                if (existe.cantidad < parseInt(producto.stock)) {
                    existe.cantidad++;
                } else {
                    alert('⚠️ Stock insuficiente. Disponible: ' + producto.stock);
                    return;
                }
            } else {
                console.log('Producto nuevo, agregando al carrito');
                const nuevoProducto = {
                    id: productoId,
                    nombre: producto.nombre,
                    precio: parseFloat(producto.precio),
                    cantidad: 1,
                    stock: parseInt(producto.stock)
                };
                carrito.push(nuevoProducto);
                console.log('Producto agregado:', nuevoProducto);
            }

            console.log('Carrito actual:', carrito);
            actualizarCarrito();
        }

        // Función para actualizar el carrito (GLOBAL)
        function actualizarCarrito() {
            console.log('=== ACTUALIZANDO CARRITO ===');
            console.log('Cantidad de productos en carrito:', carrito.length);
            console.log('Contenido del carrito:', carrito);

            const container = document.getElementById('carrito-productos');

            if (!container) {
                console.error('ERROR: No se encontró el contenedor del carrito');
                return;
            }

            if (carrito.length === 0) {
                console.log('Carrito vacío, mostrando mensaje');
                container.innerHTML = `
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <p>No hay productos agregados al carrito</p>
                <small class="text-muted">Selecciona productos del menú desplegable</small>
            </div>
        `;
                document.getElementById('total_display').innerText = '$0.00';
                document.getElementById('items_count').innerText = '0 productos';
                document.getElementById('btnGuardarVenta').disabled = true;
                document.getElementById('productos_json').value = '';
                document.getElementById('total_venta').value = '0';
                return;
            }

            console.log('Generando HTML del carrito...');
            let html = '';
            let total = 0;

            carrito.forEach((prod, index) => {
                console.log(`Procesando producto ${index}:`, prod);
                const subtotal = prod.precio * prod.cantidad;
                total += subtotal;

                html += `
            <div class="producto-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="flex-grow-1">
                        <strong>${prod.nombre}</strong><br>
                        <small class="text-muted">
                            ${formatearPrecio(prod.precio)} × ${prod.cantidad} = 
                            <span class="text-success fw-bold">${formatearPrecio(subtotal)}</span>
                        </small>
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="cambiarCantidad(${index}, -1)" title="Disminuir">
                            <i class="fas fa-minus"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled style="min-width: 50px;">
                            <strong>${prod.cantidad}</strong>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="cambiarCantidad(${index}, 1)" title="Aumentar" ${prod.cantidad >= prod.stock ? 'disabled' : ''}>
                            <i class="fas fa-plus"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" onclick="eliminarDelCarrito(${index})" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
            });

            console.log('Insertando HTML en el contenedor');
            container.innerHTML = html;

            console.log('Total calculado:', total);

            // Actualizar total con animación
            const totalDisplay = document.getElementById('total_display');
            const itemsCount = document.getElementById('items_count');
            const totalVenta = document.getElementById('total_venta');
            const productosJson = document.getElementById('productos_json');
            const btnGuardar = document.getElementById('btnGuardarVenta');

            if (totalDisplay) totalDisplay.innerText = formatearPrecio(total);
            if (itemsCount) itemsCount.innerText = carrito.length + ' producto' + (carrito.length !== 1 ? 's' : '');
            if (totalVenta) totalVenta.value = total.toFixed(2);
            if (productosJson) {
                const carritoJson = JSON.stringify(carrito);
                productosJson.value = carritoJson;
                console.log('JSON guardado:', carritoJson);
            }
            if (btnGuardar) btnGuardar.disabled = false;

            console.log('Carrito actualizado exitosamente');
            console.log('=== FIN ACTUALIZACIÓN ===');
        }

        // Cambiar cantidad (GLOBAL)
        function cambiarCantidad(index, cambio) {
            const producto = carrito[index];
            const nuevaCantidad = producto.cantidad + cambio;

            if (nuevaCantidad <= 0) {
                eliminarDelCarrito(index);
                return;
            }

            if (nuevaCantidad > producto.stock) {
                alert('⚠️ Stock insuficiente. Disponible: ' + producto.stock);
                return;
            }

            producto.cantidad = nuevaCantidad;
            actualizarCarrito();
        }

        // Eliminar del carrito (GLOBAL)
        function eliminarDelCarrito(index) {
            if (confirm('¿Eliminar este producto del carrito?')) {
                carrito.splice(index, 1);
                actualizarCarrito();
            }
        }



        // Ver detalle de venta (GLOBAL)
        function verDetalle(id) {
            window.location.href = 'detalle_venta.php?id=' + id;
        }

        // Imprimir ticket (GLOBAL)
        function imprimirTicket(id) {
            window.open('detalle_venta.php?id=' + id, '_blank');
        }


        // INICIALIZACIÓN AL CARGAR EL DOM

        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM cargado, inicializando...');

            const productoSelect = document.getElementById('producto_select');
            if (!productoSelect) {
                console.error('❌ No se encontró el select de productos');
                return;
            }

            console.log('✅ Select de productos encontrado');
            console.log('Opciones disponibles:', productoSelect.options.length);

            // Event listener para seleccionar producto
            productoSelect.addEventListener('change', function() {
                if (this.value) {
                    try {
                        const producto = JSON.parse(this.value);
                        console.log('Producto seleccionado:', producto);
                        agregarAlCarrito(producto);
                        this.value = '';
                    } catch (error) {
                        console.error('Error al parsear producto:', error);
                        alert('Error al agregar producto');
                    }
                }
            });

            // ⭐ Resetear modal al cerrar
            const modalVenta = document.getElementById('modalVenta');
            if (modalVenta) {
                modalVenta.addEventListener('hidden.bs.modal', function() {
                    carrito = [];
                    actualizarCarrito();
                    document.getElementById('formVenta').reset();
                    console.log('Modal cerrado, carrito reseteado');
                });
            }

            // ⭐ Validar formulario antes de enviar
            const formVenta = document.getElementById('formVenta');
            if (formVenta) {
                formVenta.addEventListener('submit', function(e) {
                    console.log('=== INTENTANDO ENVIAR FORMULARIO ===');
                    console.log('Carrito actual:', carrito);
                    console.log('productos_json value:', document.getElementById('productos_json').value);

                    if (carrito.length === 0) {
                        e.preventDefault();
                        alert('⚠️ Debe agregar al menos un producto al carrito');
                        return false;
                    }

                    const clienteId = document.querySelector('select[name="cliente_id"]').value;
                    if (!clienteId) {
                        e.preventDefault();
                        alert('⚠️ Debe seleccionar un cliente');
                        return false;
                    }

                    const metodoPago = document.querySelector('select[name="metodo_pago"]').value;
                    if (!metodoPago) {
                        e.preventDefault();
                        alert('⚠️ Debe seleccionar un método de pago');
                        return false;
                    }

                    // Verificar que productos_json tenga contenido
                    const productosJson = document.getElementById('productos_json').value;
                    if (!productosJson || productosJson === '' || productosJson === '[]') {
                        e.preventDefault();
                        alert('⚠️ Error: El carrito está vacío');
                        console.error('productos_json está vacío!');
                        return false;
                    }

                    console.log('Enviando venta:', {
                        carrito: carrito,
                        total: document.getElementById('total_venta').value,
                        productos_json: productosJson
                    });

                    // Mostrar loading
                    const btn = document.getElementById('btnGuardarVenta');
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

                    return true;
                });
            }

            // Log inicial
            console.log('Script de ventas cargado correctamente');
            console.log('Carrito inicial:', carrito);
        });
    </script>
</body>

</html>