<?php
require_once '../config.php';
require_once '../includes/functions.php';

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

$page_title = 'Clientes - Sistema de Ventas';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div id="content-wrapper" class="flex-fill">
<?php include "../includes/topbar.php"; ?>
<div class="container-fluid px-4 py-4">
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
</div></div>

<?php
$inline_script = <<<'JS'
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
JS;
?>

<?php include "../includes/footer.php"; ?>