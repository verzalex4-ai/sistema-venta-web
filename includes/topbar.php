<!-- Topbar -->
<nav class="navbar navbar-expand topbar mb-4 static-top">
    <ul class="navbar-nav ml-auto">
        <li class="nav-item dropdown no-arrow">
            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="mr-2 d-none d-lg-inline text-gray-600 small" style="margin-right: 0.5rem;">
                    <?php echo isset($_SESSION['usuario_nombre']) ? $_SESSION['usuario_nombre'] : 'Usuario'; ?>
                </span>
                <i class="fas fa-user-circle fa-2x" style="color: var(--text-secondary);"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                <li><a class="dropdown-item" href="index.php" target="_blank">
                    <i class="fas fa-store fa-sm fa-fw mr-2 text-gray-400"></i>
                    Ver Catálogo
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="cerrar_sesion.php">
                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                    Cerrar Sesión
                </a></li>
            </ul>
        </li>
    </ul>
</nav>
