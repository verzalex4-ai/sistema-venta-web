<?php
require_once '../config.php';
require_once '../includes/functions.php';

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

$page_title = 'Proveedores - Sistema de Ventas';
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
</div></div>

<?php
$inline_script = <<<'JS'
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
JS;
?>

<?php include "../includes/footer.php"; ?>