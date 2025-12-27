<?php
require_once 'config.php';

// Obtener filtros
$busqueda = $_GET['buscar'] ?? '';
$categoria_id = $_GET['categoria'] ?? '';

// Obtener categorías para el filtro (con iconos)
$categorias_query = "SELECT c.*, ci.icono 
                     FROM categorias c 
                     LEFT JOIN categorias_iconos ci ON c.id = ci.categoria_id 
                     WHERE c.estado=1 
                     ORDER BY c.nombre";
$categorias = $conn->query($categorias_query);

// Consulta base
$sql = "SELECT p.*, c.nombre as categoria_nombre 
        FROM productos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        WHERE p.estado = 1";

// Aplicar filtros
$params = [];
$types = '';

if (!empty($busqueda)) {
    $sql .= " AND (p.nombre LIKE ? OR p.descripcion LIKE ?)";
    $busqueda_param = "%$busqueda%";
    $params[] = &$busqueda_param;
    $params[] = &$busqueda_param;
    $types .= 'ss';
}

if (!empty($categoria_id)) {
    $sql .= " AND p.categoria_id = ?";
    $params[] = &$categoria_id;
    $types .= 'i';
}

$sql .= " ORDER BY p.nombre";

// Preparar y ejecutar consulta
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    array_unshift($params, $types);
    call_user_func_array([$stmt, 'bind_param'], $params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de Productos - Sistema de Ventas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style-minimal.css">
    <style>
        .product-card {
            height: 100%;
            transition: transform 0.2s ease;
        }

        .product-card:hover {
            transform: translateY(-2px);
        }

        .product-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-200) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }

        .product-image i {
            font-size: 4rem;
            color: var(--gray-400);
        }

        .product-body {
            padding: 1.5rem;
        }

        .product-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .product-category {
            display: inline-block;
            padding: 0.3rem 0.85rem;
            background-color: var(--gray-100);
            color: var(--text-secondary);
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .product-stock {
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .stock-disponible {
            color: var(--success-color);
            font-weight: 600;
        }

        .stock-bajo {
            color: var(--warning-color);
            font-weight: 600;
        }

        .stock-critico {
            color: var(--danger-color);
            font-weight: 600;
        }

        .filter-section {
            background: var(--bg-primary);
            padding: 2rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }

        .category-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            padding: 0.5rem 0;
        }

        .category-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.65rem 1.25rem;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .category-pill:hover {
            background: var(--gray-50);
            color: var(--primary-color);
            border-color: var(--primary-color);
            transform: translateY(-1px);
        }

        .category-pill.active {
            background: var(--primary-color);
            color: var(--text-white);
            border-color: var(--primary-color);
        }

        /* MODAL COMPACTO Y MODERNO */
        .login-modal .modal-dialog {
            max-width: 400px;
        }

        .login-modal .modal-content {
            border: none;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .modal-header-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.75rem 1.5rem;
            border: none;
            position: relative;
            text-align: center;
        }

        .modal-header-gradient::before {
            content: '';
            position: absolute;
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -30px;
            right: -30px;
        }

        .modal-header-gradient .btn-close {
            filter: brightness(0) invert(1);
            opacity: 0.8;
            position: absolute;
            right: 1rem;
            top: 1rem;
        }

        .modal-header-gradient .icon-badge {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            -webkit-backdrop-filter: blur(10px);
            backdrop-filter: blur(10px);
        }

        .modal-header-gradient .icon-badge i {
            font-size: 1.75rem;
        }

        .modal-header-gradient h5 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .modal-header-gradient p {
            margin: 0.25rem 0 0;
            font-size: 0.875rem;
            opacity: 0.9;
        }

        .login-modal .modal-body {
            padding: 1.75rem 1.5rem;
        }

        .login-modal .form-label {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.4rem;
            font-size: 0.875rem;
        }

        .login-modal .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.65rem 0.85rem;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .login-modal .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .login-modal .input-group-text {
            background: #f7fafc;
            border: 2px solid #e2e8f0;
            border-right: none;
            border-radius: 10px 0 0 10px;
            color: #667eea;
            padding: 0.65rem 0.75rem;
        }

        .login-modal .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            cursor: pointer;
            z-index: 10;
            transition: all 0.2s ease;
        }

        .password-toggle:hover {
            color: #764ba2;
            transform: translateY(-50%) scale(1.1);
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 0.75rem;
            font-weight: 700;
            border-radius: 10px;
            color: white !important;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .alert-modal {
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            display: none;
            animation: slideDown 0.3s ease;
        }

        .alert-modal.show {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-modal.alert-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
        }

        .alert-modal.alert-success {
            background: linear-gradient(135deg, #51cf66 0%, #37b24d 100%);
            color: white;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .divider-modal {
            text-align: center;
            margin: 1.25rem 0;
            position: relative;
        }

        .divider-modal::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e2e8f0;
        }

        .divider-modal span {
            background: white;
            padding: 0 1rem;
            color: #718096;
            font-size: 0.8rem;
            position: relative;
        }

        .modal-backdrop.show {
            -webkit-backdrop-filter: blur(4px);
            backdrop-filter: blur(4px);
            background-color: rgba(0, 0, 0, 0.4);
        }

        .spinner-loading {
            display: none;
        }

        .spinner-loading.show {
            display: inline-block;
        }

        .btn-access {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            transition: all 0.3s ease;
        }

        .btn-access:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-shopping-bag me-2"></i>Catálogo de Productos
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (esta_logueado()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="pages/dashboard.php">
                                <i class="fas fa-tachometer-alt me-1"></i>Panel de Control
                            </a>
                        </li>
                        <li class="nav-item">
                            <span class="nav-link">
                                <i class="fas fa-user me-1"></i><?php echo $_SESSION['usuario_nombre']; ?>
                            </span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="cerrar_sesion.php">
                                <i class="fas fa-sign-out-alt me-1"></i>Cerrar Sesión
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <button class="nav-link btn btn-access px-4" style="color: white; font-weight: 600;" data-bs-toggle="modal" data-bs-target="#loginModal">
                                <i class="fas fa-user-shield me-2"></i>Acceso Personal
                            </button>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Categorías -->
        <div class="row mb-4">
            <div class="col-12">
                <h4 class="mb-3" style="font-weight: 600; color: var(--text-primary);">
                    <i class="fas fa-th-large me-2"></i>Categorías
                </h4>
                <div class="category-pills">
                    <a href="index.php" class="category-pill <?php echo empty($categoria_id) ? 'active' : ''; ?>">
                        <i class="fas fa-border-all"></i>
                        <span>Todos</span>
                    </a>
                    <?php 
                    $categorias_temp = $conn->query($categorias_query);
                    while ($cat = $categorias_temp->fetch_assoc()): 
                        $icono = $cat['icono'] ?? 'fa-cube';
                    ?>
                        <a href="?categoria=<?php echo $cat['id']; ?>" 
                           class="category-pill <?php echo $categoria_id == $cat['id'] ? 'active' : ''; ?>">
                            <i class="fas <?php echo $icono; ?>"></i>
                            <span><?php echo htmlspecialchars($cat['nombre']); ?></span>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filter-section">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label for="buscar-input" class="form-label">
                        <i class="fas fa-search me-2"></i>Buscar productos
                    </label>
                    <input type="text" id="buscar-input" class="form-control search-input" name="buscar"
                        placeholder="Nombre, código o descripción..."
                        aria-label="Buscar productos"
                        value="<?php echo htmlspecialchars($busqueda); ?>">
                </div>
                <div class="col-md-4">
                    <label for="categoria-select" class="form-label">
                        <i class="fas fa-filter me-2"></i>Filtrar por Categoría
                    </label>
                    <select id="categoria-select" class="form-select" name="categoria" aria-label="Filtrar categorías">
                        <option value="">Todas las categorías</option>
                        <?php 
                        $categorias_select = $conn->query($categorias_query);
                        while ($cat = $categorias_select->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $categoria_id == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['nombre']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Filtrar
                    </button>
                </div>
                <?php if (!empty($busqueda) || !empty($categoria_id)): ?>
                    <div class="col-12">
                        <a href="index.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-times me-2"></i>Limpiar filtros
                        </a>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Productos -->
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($producto = $result->fetch_assoc()): ?>
                    <div class="col">
                        <div class="product-card">
                            <div class="product-image">
                                <?php if (!empty($producto['imagen']) && file_exists($producto['imagen'])): ?>
                                    <img src="<?php echo htmlspecialchars($producto['imagen']); ?>" alt="<?php echo htmlspecialchars($producto['nombre'] ?? ''); ?>">
                                <?php else: ?>
                                    <i class="fas fa-box"></i>
                                <?php endif; ?>
                            </div>
                            <div class="product-body">
                                <span class="product-category">
                                    <?php echo htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría'); ?>
                                </span>
                                <h5 class="product-title">
                                    <?php echo htmlspecialchars($producto['nombre'] ?? ''); ?>
                                </h5>
                                <h4 class="text-success fw-bold mb-3">
                                    <?php echo formatear_precio($producto['precio'] ?? 0); ?>
                                </h4>
                                <?php if (esta_logueado() && (es_admin() || es_vendedor())): ?>
                                    <p class="text-muted mb-2" style="font-size: 0.85rem;">
                                        <strong>Código:</strong> <code><?php echo htmlspecialchars($producto['codigo'] ?? 'N/A'); ?></code>
                                    </p>
                                <?php endif; ?>

                                <div class="product-stock">
                                    <?php
                                    $stock_class = 'stock-disponible';
                                    $stock_icon = 'check-circle';
                                    $stock_text = 'Disponible';

                                    $stock = $producto['stock'] ?? 0;
                                    $stock_minimo = $producto['stock_minimo'] ?? 0;

                                    if ($stock_minimo > 0) {
                                        if ($stock <= $stock_minimo) {
                                            $stock_class = 'stock-critico';
                                            $stock_icon = 'exclamation-triangle';
                                            $stock_text = 'Stock crítico';
                                        } elseif ($stock <= $stock_minimo * 2) {
                                            $stock_class = 'stock-bajo';
                                            $stock_icon = 'exclamation-circle';
                                            $stock_text = 'Stock bajo';
                                        }
                                    }
                                    ?>
                                    <i class="fas fa-<?php echo $stock_icon; ?> me-2 <?php echo $stock_class; ?>"></i>
                                    <span class="<?php echo $stock_class; ?>">
                                        <?php echo $stock_text; ?> (<?php echo $stock; ?> unidades)
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle fa-3x mb-3"></i>
                        <h4>No se encontraron productos</h4>
                        <p class="mb-0">Intenta ajustar los filtros de búsqueda.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODAL COMPACTO DE LOGIN -->
    <div class="modal fade login-modal" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header-gradient">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    <div class="icon-badge">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h5 id="loginModalLabel">Acceso Personal</h5>
                    <p>Ingresa tus credenciales</p>
                </div>
                <div class="modal-body">
                    <div class="alert-modal alert-danger" id="loginError">
                        <i class="fas fa-exclamation-circle"></i>
                        <span id="errorMessage"></span>
                    </div>

                    <div class="alert-modal alert-success" id="loginSuccess">
                        <i class="fas fa-check-circle"></i>
                        <span>¡Bienvenido! Redirigiendo...</span>
                    </div>
                    
                    <form id="loginForm" onsubmit="handleLogin(event)">
                        <div class="mb-3">
                            <label class="form-label" for="usuario">
                                <i class="fas fa-envelope me-1"></i>Usuario o Email
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" class="form-control" id="usuario" name="usuario" required placeholder="admin@sistema.local">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="password">
                                <i class="fas fa-lock me-1"></i>Contraseña
                            </label>
                            <div style="position: relative;">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-key"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
                                </div>
                                <span class="password-toggle" onclick="togglePassword()">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </span>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-login w-100">
                            <span class="spinner-loading" id="loadingSpinner">
                                <span class="spinner-border spinner-border-sm me-2"></span>
                            </span>
                            <span id="buttonText">
                                <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                            </span>
                        </button>
                    </form>

                    <div class="divider-modal">
                        <span>¿No tienes cuenta?</span>
                    </div>

                    <p class="text-center text-muted mb-0" style="font-size: 0.85rem;">
                        <i class="fas fa-info-circle me-1"></i>Continúa navegando sin iniciar sesión
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="login.js"></script>
</body>

</html>