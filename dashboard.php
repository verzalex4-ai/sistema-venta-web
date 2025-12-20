<?php
require_once 'config.php';

// Verificar sesión
if (!esta_logueado()) {
    redirigir('login.php');
}

// Redirigir al catálogo si es usuario tipo cliente
if ($_SESSION['rol'] === 'cliente') {
    redirigir('index.php');
}

// 🔍 NUEVA FUNCIONALIDAD DE BÚSQUEDA
$busqueda = isset($_GET['buscar']) ? limpiar_entrada($_GET['buscar']) : '';
$resultados_busqueda = [];
$hay_busqueda = !empty($busqueda);

if ($hay_busqueda) {
    // Buscar en múltiples tablas

    // 1. Buscar productos
    $sql_productos_busqueda = "SELECT 'producto' as tipo, id, nombre as titulo, precio, stock, 
                                CONCAT('Stock: ', stock, ' | Precio: ', precio) as detalle
                                FROM productos 
                                WHERE estado=1 AND (nombre LIKE ? OR descripcion LIKE ?)
                                LIMIT 5";
    $stmt = $conn->prepare($sql_productos_busqueda);
    $busqueda_param = "%$busqueda%";
    $stmt->bind_param("ss", $busqueda_param, $busqueda_param);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $resultados_busqueda[] = $row;
    }

    // 2. Buscar clientes
    $sql_clientes_busqueda = "SELECT 'cliente' as tipo, id, CONCAT(nombre, ' ', apellido) as titulo, 
                               CONCAT('DNI: ', dni, ' | Tel: ', telefono) as detalle, 0 as precio, 0 as stock
                               FROM clientes 
                               WHERE estado=1 AND (nombre LIKE ? OR apellido LIKE ? OR dni LIKE ? OR email LIKE ?)
                               LIMIT 5";
    $stmt = $conn->prepare($sql_clientes_busqueda);
    $stmt->bind_param("ssss", $busqueda_param, $busqueda_param, $busqueda_param, $busqueda_param);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $resultados_busqueda[] = $row;
    }

    // 3. Buscar ventas
    $sql_ventas_busqueda = "SELECT 'venta' as tipo, v.id, 
                            CONCAT('Venta #', LPAD(v.id, 6, '0')) as titulo,
                            CONCAT('Cliente: ', c.nombre, ' ', c.apellido, ' | Total: $', v.total) as detalle,
                            v.total as precio, 0 as stock
                            FROM ventas v
                            INNER JOIN clientes c ON v.cliente_id = c.id
                            WHERE v.id LIKE ? OR c.nombre LIKE ? OR c.apellido LIKE ?
                            ORDER BY v.fecha_creacion DESC
                            LIMIT 5";
    $stmt = $conn->prepare($sql_ventas_busqueda);
    $stmt->bind_param("sss", $busqueda_param, $busqueda_param, $busqueda_param);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $resultados_busqueda[] = $row;
    }

    // 4. Buscar facturas
    $sql_facturas_busqueda = "SELECT 'factura' as tipo, f.id, 
                              CONCAT('Factura ', f.numero_factura) as titulo,
                              CONCAT('Cliente: ', c.nombre, ' ', c.apellido, ' | Total: $', f.total) as detalle,
                              f.total as precio, 0 as stock
                              FROM facturas f
                              INNER JOIN clientes c ON f.cliente_id = c.id
                              WHERE f.numero_factura LIKE ? OR c.nombre LIKE ? OR c.apellido LIKE ?
                              ORDER BY f.fecha_emision DESC
                              LIMIT 5";
    $stmt = $conn->prepare($sql_facturas_busqueda);
    $stmt->bind_param("sss", $busqueda_param, $busqueda_param, $busqueda_param);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $resultados_busqueda[] = $row;
    }
}

// Obtener métricas del mes actual
$mes_actual = date('Y-m');
$sql_ventas_mes = "SELECT SUM(total) as total FROM ventas WHERE DATE_FORMAT(fecha_creacion, '%Y-%m') = ? AND estado='completada'";
$stmt = $conn->prepare($sql_ventas_mes);
$stmt->bind_param("s", $mes_actual);
$stmt->execute();
$ventas_mensuales = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Ventas anuales
$anio_actual = date('Y');
$sql_ventas_anual = "SELECT SUM(total) as total FROM ventas WHERE YEAR(fecha_creacion) = ? AND estado='completada'";
$stmt = $conn->prepare($sql_ventas_anual);
$stmt->bind_param("s", $anio_actual);
$stmt->execute();
$ventas_anuales = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Total productos
$sql_productos = "SELECT COUNT(*) as total FROM productos WHERE estado=1";
$total_productos = $conn->query($sql_productos)->fetch_assoc()['total'];

// Total clientes
$sql_clientes = "SELECT COUNT(*) as total FROM clientes WHERE estado=1";
$total_clientes = $conn->query($sql_clientes)->fetch_assoc()['total'];

// 🔔 ALERTAS DE STOCK
$sql_stock_critico = "SELECT COUNT(*) as total FROM productos WHERE stock <= 5 AND estado=1";
$productos_criticos = $conn->query($sql_stock_critico)->fetch_assoc()['total'];

$sql_stock_bajo = "SELECT COUNT(*) as total FROM productos WHERE stock > 5 AND stock <= 10 AND estado=1";
$productos_bajo = $conn->query($sql_stock_bajo)->fetch_assoc()['total'];

$total_alertas = $productos_criticos + $productos_bajo;

// Productos con stock crítico (detalles)
$sql_productos_alerta = "SELECT p.id, p.nombre, p.stock, p.precio, c.nombre as categoria
                          FROM productos p
                          LEFT JOIN categorias c ON p.categoria_id = c.id
                          WHERE p.estado=1 AND p.stock <= 10
                          ORDER BY p.stock ASC
                          LIMIT 10";
$result_alertas = $conn->query($sql_productos_alerta);
$productos_alerta = [];
while ($row = $result_alertas->fetch_assoc()) {
    $productos_alerta[] = $row;
}

// Ventas de los últimos 12 meses
$sql_grafico_ventas = "SELECT DATE_FORMAT(fecha_creacion, '%Y-%m') as mes, SUM(total) as total 
                       FROM ventas WHERE estado='completada' 
                       GROUP BY DATE_FORMAT(fecha_creacion, '%Y-%m') 
                       ORDER BY mes ASC LIMIT 12";
$result_grafico = $conn->query($sql_grafico_ventas);
$datos_grafico = [];
while ($row = $result_grafico->fetch_assoc()) {
    $datos_grafico[] = $row;
}

// Ventas por categoría
$sql_categorias = "SELECT c.nombre, SUM(dv.subtotal) as total
                   FROM detalle_ventas dv
                   INNER JOIN productos p ON dv.producto_id = p.id
                   INNER JOIN categorias c ON p.categoria_id = c.id
                   INNER JOIN ventas v ON dv.venta_id = v.id
                   WHERE v.estado='completada'
                   GROUP BY c.id, c.nombre
                   LIMIT 5";
$result_categorias = $conn->query($sql_categorias);
$datos_categorias = [];
while ($row = $result_categorias->fetch_assoc()) {
    $datos_categorias[] = $row;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Ventas</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style-minimal.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.3.0/chart.umd.min.js"></script>
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
            color: var(--gray-300);
            text-decoration: none;
            transition: all 0.2s;
        }

        .nav-link:hover,
        .nav-link.active {
            color: var(--text-white);
            background-color: var(--primary-light);
        }

        .nav-link i {
            width: 2rem;
            font-size: 0.85rem;
        }

        #content-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            height: 4.375rem;
            background-color: var(--bg-primary);
            box-shadow: var(--shadow-sm);
        }

        .border-left-primary {
            border-left: 0.25rem solid var(--primary-color) !important;
        }

        .border-left-success {
            border-left: 0.25rem solid var(--success-color) !important;
        }

        .border-left-info {
            border-left: 0.25rem solid var(--info-color) !important;
        }

        .border-left-warning {
            border-left: 0.25rem solid var(--warning-color) !important;
        }

        .text-xs {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .h5 {
            font-size: 1.25rem;
            font-weight: 700;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        /* 🔔 ESTILOS PARA ALERTAS DE STOCK */
        .alert-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            background-color: var(--danger-color);
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

        .stock-critico {
            background-color: #fef2f2;
            border-left: 4px solid var(--danger-color) !important;
        }

        .stock-bajo {
            background-color: #fffbeb;
            border-left: 4px solid var(--warning-color) !important;
        }

        .stock-ok {
            border-left: 4px solid var(--success-color) !important;
        }

        .stock-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-weight: 600;
        }

        .stock-badge.critico {
            background-color: var(--danger-color);
            color: white;
        }

        .stock-badge.bajo {
            background-color: var(--warning-color);
            color: white;
        }

        .stock-badge.ok {
            background-color: var(--success-color);
            color: white;
        }

        /* 🔍 ESTILOS PARA LA BÚSQUEDA */
        .search-container {
            position: relative;
            width: 100%;
            max-width: 500px;
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            margin-top: 0.5rem;
            display: none;
            border: 1px solid var(--border-color);
        }

        .search-results.show {
            display: block;
        }

        .search-result-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .search-result-item:hover {
            background-color: #f8f9fc;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .search-icon.producto {
            background-color: #e7f3ff;
            color: var(--info);
        }

        .search-icon.cliente {
            background-color: #e8f5e9;
            color: var(--success);
        }

        .search-icon.venta {
            background-color: #fff3e0;
            color: var(--warning);
        }

        .search-icon.factura {
            background-color: #f3e5f5;
            color: #9c27b0;
        }

        .search-result-content {
            flex: 1;
        }

        .search-result-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
        }

        .search-result-detail {
            font-size: 0.875rem;
            color: #666;
        }

        .no-results {
            padding: 2rem;
            text-align: center;
            color: #999;
        }

        .search-input-wrapper {
            position: relative;
        }

        .search-clear {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0.25rem 0.5rem;
            display: none;
        }

        .search-clear.show {
            display: block;
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
                <a class="nav-link active" href="dashboard.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span>
                </a>
            </li>
            <hr class="sidebar-divider" style="border-color: rgba(255,255,255,.2)">
            <div class="sidebar-heading" style="color: rgba(255,255,255,.5); padding: 0 1rem; font-size: 0.65rem; text-transform: uppercase; margin-top: 0.5rem;">Gestión</div>
            
            <?php if (tiene_permiso('productos')): ?>
            <li class="nav-item">
                <a class="nav-link" href="productos.php" style="position: relative;">
                    <i class="fas fa-fw fa-box"></i><span>Productos</span>
                    <?php if ($total_alertas > 0): ?>
                        <span class="alert-badge"><?php echo $total_alertas; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="categorias.php">
                    <i class="fas fa-fw fa-tags"></i><span>Categorías</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (tiene_permiso('clientes')): ?>
            <li class="nav-item">
                <a class="nav-link" href="clientes.php">
                    <i class="fas fa-fw fa-users"></i><span>Clientes</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (tiene_permiso('proveedores')): ?>
            <li class="nav-item">
                <a class="nav-link" href="proveedores.php">
                    <i class="fas fa-fw fa-truck"></i><span>Proveedores</span>
                </a>
            </li>
            <?php endif; ?>
            
            <hr class="sidebar-divider" style="border-color: rgba(255,255,255,.2)">
            <div class="sidebar-heading" style="color: rgba(255,255,255,.5); padding: 0 1rem; font-size: 0.65rem; text-transform: uppercase; margin-top: 0.5rem;">Operaciones</div>
            
            <?php if (tiene_permiso('ventas')): ?>
            <li class="nav-item">
                <a class="nav-link" href="ventas.php">
                    <i class="fas fa-fw fa-cash-register"></i><span>Ventas</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (tiene_permiso('facturas')): ?>
            <li class="nav-item">
                <a class="nav-link" href="facturas.php">
                    <i class="fas fa-fw fa-file-invoice"></i><span>Facturas</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (tiene_permiso('caja')): ?>
            <li class="nav-item">
                <a class="nav-link" href="caja.php">
                    <i class="fas fa-fw fa-money-bill-wave"></i><span>Caja</span>
                </a>
            </li>
            <?php endif; ?>
            
            <li class="nav-item">
                <a class="nav-link" href="cuentas_corrientes.php">
                    <i class="fas fa-fw fa-file-invoice-dollar"></i><span>Cuentas Corrientes</span>
                </a>
            </li>
            <hr class="sidebar-divider" style="border-color: rgba(255,255,255,.2)">
            <li class="nav-item">
                <a class="nav-link" href="index.php" target="_blank">
                    <i class="fas fa-fw fa-store"></i><span>Ver Catálogo Público</span>
                </a>
            </li>
            <hr class="sidebar-divider" style="border-color: rgba(255,255,255,.2)">
            <li class="nav-item">
                <a class="nav-link" href="reportes.php">
                    <i class="fas fa-fw fa-chart-area"></i><span>Reportes</span>
                </a>
            </li>
            <?php if (es_admin()): ?>
                <hr class="sidebar-divider" style="border-color: rgba(255,255,255,.2)">
                <div class="sidebar-heading" style="color: rgba(255,255,255,.5); font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.05rem; padding: 0 1rem; margin-top: 0.5rem;">
                    Administración
                </div>
                <li class="nav-item">
                    <a class="nav-link" href="usuarios.php">
                        <i class="fas fa-fw fa-users-cog"></i><span>Gestión de Usuarios</span>
                    </a>
                </li>
            <?php endif; ?>
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
                <!-- 🔍 BÚSQUEDA MEJORADA -->
                <div class="search-container">
                    <form class="d-none d-sm-inline-block form-inline mr-auto ml-md-3 my-2 my-md-0 mw-100" method="GET" id="searchForm">
                        <div class="input-group search-input-wrapper">
                            <input type="text"
                                class="form-control bg-light border-0 small"
                                placeholder="Buscar productos, clientes, ventas..."
                                name="buscar"
                                id="searchInput"
                                value="<?php echo htmlspecialchars($busqueda); ?>"
                                style="border-radius: 10rem; padding-right: 40px;">
                            <button type="button" class="search-clear <?php echo $hay_busqueda ? 'show' : ''; ?>" id="clearSearch">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </form>

                    <!-- Resultados de búsqueda -->
                    <div class="search-results <?php echo $hay_busqueda ? 'show' : ''; ?>" id="searchResults">
                        <?php if ($hay_busqueda): ?>
                            <?php if (count($resultados_busqueda) > 0): ?>
                                <?php foreach ($resultados_busqueda as $resultado): ?>
                                    <?php
                                    $iconos = [
                                        'producto' => 'fa-box',
                                        'cliente' => 'fa-user',
                                        'venta' => 'fa-shopping-cart',
                                        'factura' => 'fa-file-invoice'
                                    ];
                                    $urls = [
                                        'producto' => 'productos.php',
                                        'cliente' => 'clientes.php',
                                        'venta' => 'detalle_venta.php?id=' . $resultado['id'],
                                        'factura' => 'facturas.php'
                                    ];
                                    ?>
                                    <div class="search-result-item" onclick="window.location.href='<?php echo $urls[$resultado['tipo']]; ?>'">
                                        <div class="search-icon <?php echo $resultado['tipo']; ?>">
                                            <i class="fas <?php echo $iconos[$resultado['tipo']]; ?>"></i>
                                        </div>
                                        <div class="search-result-content">
                                            <div class="search-result-title"><?php echo $resultado['titulo']; ?></div>
                                            <div class="search-result-detail"><?php echo $resultado['detalle']; ?></div>
                                        </div>
                                        <div>
                                            <span class="badge bg-secondary"><?php echo ucfirst($resultado['tipo']); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-results">
                                    <i class="fas fa-search fa-3x mb-3" style="color: #ddd;"></i>
                                    <p>No se encontraron resultados para "<strong><?php echo htmlspecialchars($busqueda); ?></strong>"</p>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="fas fa-user-circle fa-2x" style="color: #858796;"></i>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="container-fluid">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
                    <a href="reportes.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-download fa-sm text-white-50"></i> Generar Reporte
                    </a>
                </div>

                <!-- Cards de Métricas -->
                <div class="row">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Ventas (Mensual)</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo formatear_precio($ventas_mensuales); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar fa-2x text-gray-300"></i>
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
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Ventas (Anual)</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo formatear_precio($ventas_anuales); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
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
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Productos</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_productos; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Clientes</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_clientes; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 🔔 Widget de Alertas de Stock -->
                <?php if (count($productos_alerta) > 0): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="card shadow mb-4 border-left-danger">
                                <div class="card-header py-3 d-flex justify-content-between align-items-center" style="background-color: #fff5f5;">
                                    <h6 class="m-0 font-weight-bold text-danger">
                                        <i class="fas fa-exclamation-triangle"></i> Alertas de Stock
                                        <span class="badge bg-danger ms-2"><?php echo $total_alertas; ?></span>
                                    </h6>
                                    <a href="productos.php" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-arrow-right"></i> Ver Todos
                                    </a>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <thead style="background-color: #f8f9fc;">
                                                <tr>
                                                    <th>Producto</th>
                                                    <th>Categoría</th>
                                                    <th class="text-center">Stock Actual</th>
                                                    <th class="text-center">Estado</th>
                                                    <th class="text-end">Precio</th>
                                                    <th class="text-center">Acción</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($productos_alerta as $producto): ?>
                                                    <?php
                                                    $clase_stock = '';
                                                    $badge_stock = '';
                                                    $badge_text = '';
                                                    if ($producto['stock'] <= 5) {
                                                        $clase_stock = 'stock-critico';
                                                        $badge_stock = 'critico';
                                                        $badge_text = '🔴 CRÍTICO';
                                                    } elseif ($producto['stock'] <= 10) {
                                                        $clase_stock = 'stock-bajo';
                                                        $badge_stock = 'bajo';
                                                        $badge_text = '⚠️ BAJO';
                                                    }
                                                    ?>
                                                    <tr class="<?php echo $clase_stock; ?>">
                                                        <td class="fw-bold"><?php echo $producto['nombre']; ?></td>
                                                        <td><?php echo $producto['categoria'] ?? '-'; ?></td>
                                                        <td class="text-center">
                                                            <span class="badge bg-dark"><?php echo $producto['stock']; ?></span>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="stock-badge <?php echo $badge_stock; ?>"><?php echo $badge_text; ?></span>
                                                        </td>
                                                        <td class="text-end"><?php echo formatear_precio($producto['precio']); ?></td>
                                                        <td class="text-center">
                                                            <a href="productos.php#producto-<?php echo $producto['id']; ?>" class="btn btn-sm btn-primary">
                                                                <i class="fas fa-plus"></i> Reabastecer
                                                            </a>
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
                <?php endif; ?>

                <!-- Gráficos -->
                <div class="row">
                    <div class="col-xl-8 col-lg-7">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold" style="color: var(--primary);">Resumen de Ventas</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="ventasChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-lg-5">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold" style="color: var(--primary);">Ventas por Categoría</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="categoriasChart"></canvas>
                                </div>
                                <div class="mt-4 text-center small">
                                    <?php
                                    $colores = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'];
                                    foreach ($datos_categorias as $index => $cat):
                                    ?>
                                        <span class="mr-2">
                                            <i class="fas fa-circle" style="color: <?php echo $colores[$index % 5]; ?>;"></i>
                                            <?php echo $cat['nombre']; ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <footer class="bg-white py-4">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; Sistema de Ventas 2025</span>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // 🔍 FUNCIONALIDAD DE BÚSQUEDA
        const searchInput = document.getElementById('searchInput');
        const searchResults = document.getElementById('searchResults');
        const clearSearch = document.getElementById('clearSearch');
        const searchForm = document.getElementById('searchForm');

        // Mostrar/ocultar botón de limpiar
        searchInput.addEventListener('input', function() {
            if (this.value.length > 0) {
                clearSearch.classList.add('show');
            } else {
                clearSearch.classList.remove('show');
                searchResults.classList.remove('show');
            }
        });

        // Limpiar búsqueda
        clearSearch.addEventListener('click', function() {
            searchInput.value = '';
            clearSearch.classList.remove('show');
            searchResults.classList.remove('show');
            window.location.href = 'index.php';
        });

        // Buscar al presionar Enter
        searchForm.addEventListener('submit', function(e) {
            if (searchInput.value.trim().length === 0) {
                e.preventDefault();
            }
        });

        // Cerrar resultados al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-container')) {
                searchResults.classList.remove('show');
            }
        });

        // Mostrar resultados al hacer clic en el input si ya hay búsqueda
        searchInput.addEventListener('focus', function() {
            if (this.value.length > 0 && searchResults.children.length > 0) {
                searchResults.classList.add('show');
            }
        });

        // GRÁFICOS
        console.log('Inicializando gráficos...');

        const datosVentas = <?php echo json_encode($datos_grafico); ?>;
        const datosCategorias = <?php echo json_encode($datos_categorias); ?>;

        console.log('Datos de ventas:', datosVentas);
        console.log('Datos de categorías:', datosCategorias);

        // Gráfico de Ventas
        if (datosVentas && datosVentas.length > 0) {
            const ctxVentas = document.getElementById('ventasChart');
            if (ctxVentas) {
                new Chart(ctxVentas, {
                    type: 'line',
                    data: {
                        labels: datosVentas.map(item => item.mes),
                        datasets: [{
                            label: 'Ventas',
                            data: datosVentas.map(item => parseFloat(item.total)),
                            borderColor: '#4e73df',
                            backgroundColor: 'rgba(78, 115, 223, 0.05)',
                            tension: 0.4,
                            fill: true,
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return '$' + context.parsed.y.toLocaleString('es-AR', {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2
                                        });
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value.toLocaleString('es-AR');
                                    }
                                }
                            }
                        }
                    }
                });
                console.log('Gráfico de ventas creado exitosamente');
            } else {
                console.error('No se encontró el canvas ventasChart');
            }
        } else {
            console.warn('No hay datos de ventas para mostrar');
            document.getElementById('ventasChart').parentElement.innerHTML = '<p class="text-center text-muted p-5">No hay datos de ventas disponibles</p>';
        }

        // Gráfico de Categorías
        if (datosCategorias && datosCategorias.length > 0) {
            const ctxCategorias = document.getElementById('categoriasChart');
            if (ctxCategorias) {
                new Chart(ctxCategorias, {
                    type: 'doughnut',
                    data: {
                        labels: datosCategorias.map(item => item.nombre),
                        datasets: [{
                            data: datosCategorias.map(item => parseFloat(item.total)),
                            backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'],
                            hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf', '#dda20a', '#be2617'],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((value / total) * 100).toFixed(1);
                                        return label + ': $' + value.toLocaleString('es-AR', {
                                            minimumFractionDigits: 2
                                        }) + ' (' + percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
                console.log('Gráfico de categorías creado exitosamente');
            } else {
                console.error('No se encontró el canvas categoriasChart');
            }
        } else {
            console.warn('No hay datos de categorías para mostrar');
            document.getElementById('categoriasChart').parentElement.innerHTML = '<p class="text-center text-muted p-5">No hay datos de categorías disponibles</p>';
        }
    </script>
</body>

</html>