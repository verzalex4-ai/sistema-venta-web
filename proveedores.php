<?php
require_once 'config.php';

// Verificar permisos (admin y repositor pueden acceder)
requiere_permiso('proveedores');

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        $accion = $_POST['accion'];

        if ($accion === 'crear') {
            $nombre = limpiar_entrada($_POST['nombre']);
            $razon_social = limpiar_entrada($_POST['razon_social']);
            $cuit = limpiar_entrada($_POST['cuit']);
            $email = limpiar_entrada($_POST['email']);
            $telefono = limpiar_entrada($_POST['telefono']);
            $direccion = limpiar_entrada($_POST['direccion']);
            $ciudad = limpiar_entrada($_POST['ciudad']);
            $provincia = limpiar_entrada($_POST['provincia']);
            $codigo_postal = limpiar_entrada($_POST['codigo_postal']);
            $contacto_nombre = limpiar_entrada($_POST['contacto_nombre']);
            $contacto_telefono = limpiar_entrada($_POST['contacto_telefono']);

            $sql = "INSERT INTO proveedores (nombre, razon_social, cuit, email, telefono, direccion, ciudad, provincia, codigo_postal, contacto_nombre, contacto_telefono) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssssss", $nombre, $razon_social, $cuit, $email, $telefono, $direccion, $ciudad, $provincia, $codigo_postal, $contacto_nombre, $contacto_telefono);

            if ($stmt->execute()) {
                $mensaje = "Proveedor creado exitosamente";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al crear proveedor";
                $tipo_mensaje = "danger";
            }
        }

        if ($accion === 'editar') {
            $id = intval($_POST['id']);
            $nombre = limpiar_entrada($_POST['nombre']);
            $razon_social = limpiar_entrada($_POST['razon_social']);
            $cuit = limpiar_entrada($_POST['cuit']);
            $email = limpiar_entrada($_POST['email']);
            $telefono = limpiar_entrada($_POST['telefono']);
            $direccion = limpiar_entrada($_POST['direccion']);
            $ciudad = limpiar_entrada($_POST['ciudad']);
            $provincia = limpiar_entrada($_POST['provincia']);
            $codigo_postal = limpiar_entrada($_POST['codigo_postal']);
            $contacto_nombre = limpiar_entrada($_POST['contacto_nombre']);
            $contacto_telefono = limpiar_entrada($_POST['contacto_telefono']);

            $sql = "UPDATE proveedores SET nombre=?, razon_social=?, cuit=?, email=?, telefono=?, direccion=?, ciudad=?, provincia=?, codigo_postal=?, contacto_nombre=?, contacto_telefono=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssssssi", $nombre, $razon_social, $cuit, $email, $telefono, $direccion, $ciudad, $provincia, $codigo_postal, $contacto_nombre, $contacto_telefono, $id);

            if ($stmt->execute()) {
                $mensaje = "Proveedor actualizado exitosamente";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al actualizar proveedor";
                $tipo_mensaje = "danger";
            }
        }

        if ($accion === 'eliminar') {
            $id = intval($_POST['id']);
            $sql = "UPDATE proveedores SET estado=0 WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                $mensaje = "Proveedor desactivado exitosamente";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al desactivar proveedor";
                $tipo_mensaje = "danger";
            }
        }
    }
}

// Obtener proveedores
$sql_proveedores = "SELECT * FROM proveedores WHERE estado=1 ORDER BY id DESC";
$result_proveedores = $conn->query($sql_proveedores);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proveedores - Sistema de Ventas</title>
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
                <a class="nav-link" href="index.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span>
                </a>
            </li>
            <hr class="sidebar-divider" style="border-color: rgba(255,255,255,.2)">
            <div class="sidebar-heading" style="color: rgba(255,255,255,.5); padding: 0 1rem; font-size: 0.65rem; text-transform: uppercase;">Gestión</div>
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
                <a class="nav-link active" href="proveedores.php">
                    <i class="fas fa-fw fa-truck"></i><span>Proveedores</span>
                </a>
            </li>
            <hr class="sidebar-divider" style="border-color: rgba(255,255,255,.2)">
            <div class="sidebar-heading" style="color: rgba(255,255,255,.5); padding: 0 1rem; font-size: 0.65rem; text-transform: uppercase;">Operaciones</div>
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
                    <i class="fas fa-fw fa-cash-register"></i><span>Caja</span>
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
                    <h1 class="h3 mb-0 text-gray-800">Gestión de Proveedores</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProveedor">
                        <i class="fas fa-plus"></i> Nuevo Proveedor
                    </button>
                </div>

                <div class="card">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold" style="color: var(--primary);">Lista de Proveedores</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>CUIT</th>
                                        <th>Email</th>
                                        <th>Teléfono</th>
                                        <th>Ciudad</th>
                                        <th>Contacto</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result_proveedores->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $row['id']; ?></td>
                                            <td><?php echo $row['nombre']; ?></td>
                                            <td><?php echo $row['cuit']; ?></td>
                                            <td><?php echo $row['email']; ?></td>
                                            <td><?php echo $row['telefono']; ?></td>
                                            <td><?php echo $row['ciudad']; ?></td>
                                            <td><?php echo $row['contacto_nombre']; ?></td>
                                            <td>
                                                <span class="badge <?php echo $row['estado'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo $row['estado'] ? 'Activo' : 'Inactivo'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-info btn-sm" onclick="editarProveedor(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm" onclick="eliminarProveedor(<?php echo $row['id']; ?>)">
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

    <!-- Modal Proveedor -->
    <div class="modal fade" id="modalProveedor" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nuevo Proveedor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formProveedor">
                    <div class="modal-body">
                        <input type="hidden" name="accion" id="accion" value="crear">
                        <input type="hidden" name="id" id="proveedor_id">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre Comercial</label>
                                <input type="text" class="form-control" name="nombre" id="nombre" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Razón Social</label>
                                <input type="text" class="form-control" name="razon_social" id="razon_social">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">CUIT</label>
                                <input type="text" class="form-control" name="cuit" id="cuit" placeholder="20-12345678-9" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="email">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="text" class="form-control" name="telefono" id="telefono">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Dirección</label>
                                <input type="text" class="form-control" name="direccion" id="direccion">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Ciudad</label>
                                <input type="text" class="form-control" name="ciudad" id="ciudad">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Provincia</label>
                                <input type="text" class="form-control" name="provincia" id="provincia">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Código Postal</label>
                                <input type="text" class="form-control" name="codigo_postal" id="codigo_postal">
                            </div>
                        </div>

                        <hr>
                        <h6>Datos de Contacto</h6>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre del Contacto</label>
                                <input type="text" class="form-control" name="contacto_nombre" id="contacto_nombre">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teléfono del Contacto</label>
                                <input type="text" class="form-control" name="contacto_telefono" id="contacto_telefono">
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
        function editarProveedor(proveedor) {
            document.getElementById('modalTitle').innerText = 'Editar Proveedor';
            document.getElementById('accion').value = 'editar';
            document.getElementById('proveedor_id').value = proveedor.id;
            document.getElementById('nombre').value = proveedor.nombre;
            document.getElementById('razon_social').value = proveedor.razon_social;
            document.getElementById('cuit').value = proveedor.cuit;
            document.getElementById('email').value = proveedor.email;
            document.getElementById('telefono').value = proveedor.telefono;
            document.getElementById('direccion').value = proveedor.direccion;
            document.getElementById('ciudad').value = proveedor.ciudad;
            document.getElementById('provincia').value = proveedor.provincia;
            document.getElementById('codigo_postal').value = proveedor.codigo_postal;
            document.getElementById('contacto_nombre').value = proveedor.contacto_nombre;
            document.getElementById('contacto_telefono').value = proveedor.contacto_telefono;

            new bootstrap.Modal(document.getElementById('modalProveedor')).show();
        }

        function eliminarProveedor(id) {
            if (confirm('¿Está seguro de desactivar este proveedor?')) {
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

        document.getElementById('modalProveedor').addEventListener('hidden.bs.modal', function() {
            document.getElementById('formProveedor').reset();
            document.getElementById('modalTitle').innerText = 'Nuevo Proveedor';
            document.getElementById('accion').value = 'crear';
        });
    </script>
</body>

</html>