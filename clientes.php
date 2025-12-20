<?php
require_once 'config.php';

// Verificar permisos (admin y vendedor pueden acceder)
requiere_permiso('clientes');

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        $accion = $_POST['accion'];

        if ($accion === 'crear') {
            $nombre = limpiar_entrada($_POST['nombre']);
            $apellido = limpiar_entrada($_POST['apellido']);
            $email = limpiar_entrada($_POST['email']);
            $telefono = limpiar_entrada($_POST['telefono']);
            $dni = limpiar_entrada($_POST['dni']);
            $direccion = limpiar_entrada($_POST['direccion']);

            $sql = "INSERT INTO clientes (nombre, apellido, email, telefono, dni, direccion) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssss", $nombre, $apellido, $email, $telefono, $dni, $direccion);

            if ($stmt->execute()) {
                $mensaje = "Cliente creado exitosamente";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al crear cliente";
                $tipo_mensaje = "danger";
            }
        }

        if ($accion === 'editar') {
            $id = intval($_POST['id']);
            $nombre = limpiar_entrada($_POST['nombre']);
            $apellido = limpiar_entrada($_POST['apellido']);
            $email = limpiar_entrada($_POST['email']);
            $telefono = limpiar_entrada($_POST['telefono']);
            $dni = limpiar_entrada($_POST['dni']);
            $direccion = limpiar_entrada($_POST['direccion']);

            $sql = "UPDATE clientes SET nombre=?, apellido=?, email=?, telefono=?, dni=?, direccion=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssi", $nombre, $apellido, $email, $telefono, $dni, $direccion, $id);

            if ($stmt->execute()) {
                $mensaje = "Cliente actualizado exitosamente";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al actualizar cliente";
                $tipo_mensaje = "danger";
            }
        }

        if ($accion === 'eliminar') {
            $id = intval($_POST['id']);
            $sql = "UPDATE clientes SET estado=0 WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                $mensaje = "Cliente desactivado exitosamente";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al desactivar cliente";
                $tipo_mensaje = "danger";
            }
        }
    }
}

// Obtener clientes
$sql_clientes = "SELECT * FROM clientes WHERE estado=1 ORDER BY id DESC";
$result_clientes = $conn->query($sql_clientes);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - Sistema de Ventas</title>
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
                <a class="nav-link active" href="index.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span>
                </a>
            </li>
            <hr class="sidebar-divider" style="border-color: rgba(255,255,255,.2)">
            <div class="sidebar-heading" style="color: rgba(255,255,255,.5); padding: 0 1rem; font-size: 0.65rem; text-transform: uppercase; margin-top: 0.5rem;">Gestión</div>
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
            <li class="nav-item">
                <a class="nav-link" href="proveedores.php">
                    <i class="fas fa-fw fa-truck"></i><span>Proveedores</span>
                </a>
            </li>
            <hr class="sidebar-divider" style="border-color: rgba(255,255,255,.2)">
            <div class="sidebar-heading" style="color: rgba(255,255,255,.5); padding: 0 1rem; font-size: 0.65rem; text-transform: uppercase; margin-top: 0.5rem;">Operaciones</div>
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

                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Gestión de Clientes</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCliente">
                        <i class="fas fa-plus"></i> Nuevo Cliente
                    </button>
                </div>

                <div class="card">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold" style="color: var(--primary);">Lista de Clientes</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre Completo</th>
                                        <th>DNI</th>
                                        <th>Email</th>
                                        <th>Teléfono</th>
                                        <th>Dirección</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result_clientes->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $row['id']; ?></td>
                                            <td><?php echo $row['nombre'] . ' ' . $row['apellido']; ?></td>
                                            <td><?php echo $row['dni']; ?></td>
                                            <td><?php echo $row['email']; ?></td>
                                            <td><?php echo $row['telefono']; ?></td>
                                            <td><?php echo substr($row['direccion'], 0, 30); ?>...</td>
                                            <td>
                                                <span class="badge <?php echo $row['estado'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo $row['estado'] ? 'Activo' : 'Inactivo'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-info btn-sm" onclick="editarCliente(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm" onclick="eliminarCliente(<?php echo $row['id']; ?>)">
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

    <!-- Modal Cliente -->
    <div class="modal fade" id="modalCliente" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nuevo Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formCliente">
                    <div class="modal-body">
                        <input type="hidden" name="accion" id="accion" value="crear">
                        <input type="hidden" name="id" id="cliente_id">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre</label>
                                <input type="text" class="form-control" name="nombre" id="nombre" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Apellido</label>
                                <input type="text" class="form-control" name="apellido" id="apellido" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">DNI</label>
                                <input type="text" class="form-control" name="dni" id="dni" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="text" class="form-control" name="telefono" id="telefono" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="email">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Dirección</label>
                            <textarea class="form-control" name="direccion" id="direccion" rows="2"></textarea>
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
        function editarCliente(cliente) {
            document.getElementById('modalTitle').innerText = 'Editar Cliente';
            document.getElementById('accion').value = 'editar';
            document.getElementById('cliente_id').value = cliente.id;
            document.getElementById('nombre').value = cliente.nombre;
            document.getElementById('apellido').value = cliente.apellido;
            document.getElementById('dni').value = cliente.dni;
            document.getElementById('telefono').value = cliente.telefono;
            document.getElementById('email').value = cliente.email;
            document.getElementById('direccion').value = cliente.direccion;

            new bootstrap.Modal(document.getElementById('modalCliente')).show();
        }

        function eliminarCliente(id) {
            if (confirm('¿Está seguro de desactivar este cliente?')) {
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

        document.getElementById('modalCliente').addEventListener('hidden.bs.modal', function() {
            document.getElementById('formCliente').reset();
            document.getElementById('modalTitle').innerText = 'Nuevo Cliente';
            document.getElementById('accion').value = 'crear';
        });
    </script>
</body>

</html>