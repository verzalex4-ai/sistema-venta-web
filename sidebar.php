<!-- Sidebar -->
<ul class="navbar-nav" id="sidebar-wrapper">
    <a class="sidebar-brand" href="index.php">
        <div class="sidebar-brand-icon"><i class="fas fa-shopping-cart"></i></div>
        <div class="sidebar-brand-text mx-3">VENTAS</div>
    </a>
    <hr class="sidebar-divider my-0" style="border-color: rgba(255,255,255,.2)">
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
            <i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span>
        </a>
    </li>
    <hr class="sidebar-divider" style="border-color: rgba(255,255,255,.2)">
    <div class="sidebar-heading" style="color: rgba(255,255,255,.5); padding: 0 1rem; font-size: 0.65rem; text-transform: uppercase; margin-top: 0.5rem;">Gestión</div>
    
    <?php if (tiene_permiso('productos')): ?>
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'productos.php' ? 'active' : ''; ?>" href="productos.php" style="position: relative;">
            <i class="fas fa-fw fa-box"></i><span>Productos</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'categorias.php' ? 'active' : ''; ?>" href="categorias.php">
            <i class="fas fa-fw fa-tags"></i><span>Categorías</span>
        </a>
    </li>
    <?php endif; ?>
    
    <?php if (tiene_permiso('clientes')): ?>
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'clientes.php' ? 'active' : ''; ?>" href="clientes.php">
            <i class="fas fa-fw fa-users"></i><span>Clientes</span>
        </a>
    </li>
    <?php endif; ?>
    
    <?php if (tiene_permiso('proveedores')): ?>
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'proveedores.php' ? 'active' : ''; ?>" href="proveedores.php">
            <i class="fas fa-fw fa-truck"></i><span>Proveedores</span>
        </a>
    </li>
    <?php endif; ?>
    
    <hr class="sidebar-divider" style="border-color: rgba(255,255,255,.2)">
    <div class="sidebar-heading" style="color: rgba(255,255,255,.5); padding: 0 1rem; font-size: 0.65rem; text-transform: uppercase; margin-top: 0.5rem;">Operaciones</div>
    
    <?php if (tiene_permiso('ventas')): ?>
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'ventas.php' ? 'active' : ''; ?>" href="ventas.php">
            <i class="fas fa-fw fa-cash-register"></i><span>Ventas</span>
        </a>
    </li>
    <?php endif; ?>
    
    <?php if (tiene_permiso('facturas')): ?>
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'facturas.php' ? 'active' : ''; ?>" href="facturas.php">
            <i class="fas fa-fw fa-file-invoice"></i><span>Facturas</span>
        </a>
    </li>
    <?php endif; ?>
    
    <?php if (tiene_permiso('caja')): ?>
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'caja.php' ? 'active' : ''; ?>" href="caja.php">
            <i class="fas fa-fw fa-money-bill-wave"></i><span>Caja</span>
        </a>
    </li>
    <?php endif; ?>
    
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'cuentas_corrientes.php' ? 'active' : ''; ?>" href="cuentas_corrientes.php">
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
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reportes.php' ? 'active' : ''; ?>" href="reportes.php">
            <i class="fas fa-fw fa-chart-area"></i><span>Reportes</span>
        </a>
    </li>
    <?php if (es_admin()): ?>
        <hr class="sidebar-divider" style="border-color: rgba(255,255,255,.2)">
        <div class="sidebar-heading" style="color: rgba(255,255,255,.5); font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.05rem; padding: 0 1rem; margin-top: 0.5rem;">
            Administración
        </div>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'usuarios.php' ? 'active' : ''; ?>" href="usuarios.php">
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
