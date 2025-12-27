<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Verificar que esté logueado y sea admin
if (!esta_logueado()) {
    header('Location: ../login.php');
    exit();
}

if (!es_admin()) {
    header('HTTP/1.0 403 Forbidden');
    die('Acceso denegado. Solo administradores pueden gestionar usuarios.');
}

// Procesar acciones
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $nombre = limpiar_entrada($_POST['nombre']);
        $email = limpiar_entrada($_POST['email']);
        $password = $_POST['password'];
        $rol = limpiar_entrada($_POST['rol']);

        // Validar email único
        $check = $conn->query("SELECT id FROM usuarios WHERE email = '$email'");
        if ($check->num_rows > 0) {
            set_mensaje('El email ya está registrado', 'danger');
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $nombre, $email, $password_hash, $rol);

            if ($stmt->execute()) {
                set_mensaje('Usuario creado exitosamente', 'success');
            } else {
                set_mensaje('Error al crear usuario: ' . $conn->error, 'danger');
            }
        }
    } elseif ($accion === 'editar') {
        $id = intval($_POST['id']);
        $nombre = limpiar_entrada($_POST['nombre']);
        $email = limpiar_entrada($_POST['email']);
        $rol = limpiar_entrada($_POST['rol']);
        $password = $_POST['password'] ?? '';

        // Validar email único (excepto el mismo usuario)
        $check = $conn->query("SELECT id FROM usuarios WHERE email = '$email' AND id != $id");
        if ($check->num_rows > 0) {
            set_mensaje('El email ya está registrado por otro usuario', 'danger');
        } else {
            if (!empty($password)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE usuarios SET nombre = ?, email = ?, password = ?, rol = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssi", $nombre, $email, $password_hash, $rol, $id);
            } else {
                $sql = "UPDATE usuarios SET nombre = ?, email = ?, rol = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssi", $nombre, $email, $rol, $id);
            }

            if ($stmt->execute()) {
                set_mensaje('Usuario actualizado exitosamente', 'success');
            } else {
                set_mensaje('Error al actualizar usuario: ' . $conn->error, 'danger');
            }
        }
    } elseif ($accion === 'cambiar_estado') {
        $id = intval($_POST['id']);
        $estado = intval($_POST['estado']);

        $sql = "UPDATE usuarios SET estado = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $estado, $id);

        if ($stmt->execute()) {
            set_mensaje($estado ? 'Usuario activado' : 'Usuario desactivado', 'success');
        } else {
            set_mensaje('Error al cambiar estado', 'danger');
        }
    } elseif ($accion === 'eliminar') {
        $id = intval($_POST['id']);

        // No permitir eliminar al admin principal
        if ($id == 1) {
            set_mensaje('No se puede eliminar el usuario administrador principal', 'danger');
        } else {
            $sql = "DELETE FROM usuarios WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                set_mensaje('Usuario eliminado exitosamente', 'success');
            } else {
                set_mensaje('Error al eliminar usuario: ' . $conn->error, 'danger');
            }
        }
    }
}

// Obtener lista de usuarios
$usuarios = $conn->query("SELECT * FROM usuarios ORDER BY id DESC");

$page_title = 'Usuarios - Sistema de Ventas';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div id="content-wrapper" class="d-flex flex-column">
    <?php include '../includes/topbar.php'; ?>
    
    <div class="container-fluid px-4 py-4">
        
        <?php mostrar_mensaje(); ?>

        <style>
            .badge-rol {
                padding: 0.5rem 1rem;
                font-size: 0.85rem;
                font-weight: 600;
                border-radius: 20px;
            }

            .badge-admin {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }

            .badge-vendedor {
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                color: white;
            }

            .badge-repositor {
                background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
                color: white;
            }

            .badge-cliente {
                background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
                color: white;
            }

            .btn-action {
                padding: 0.25rem 0.5rem;
                font-size: 0.875rem;
                margin: 0 0.1rem;
            }

            .estado-activo {
                color: var(--success-color);
            }

            .estado-inactivo {
                color: var(--danger-color);
            }
        </style>

        <!-- Encabezado -->
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-users me-2"></i>Gestión de Usuarios
            </h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrearUsuario">
                <i class="fas fa-user-plus me-2"></i>Nuevo Usuario
            </button>
        </div>

        <!-- Tabla -->
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Lista de Usuarios</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Último Acceso</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($usuario = $usuarios->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $usuario['id']; ?></td>
                                    <td>
                                        <i class="fas fa-user-circle me-2 text-primary"></i>
                                        <?php echo htmlspecialchars($usuario['nombre']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                    <td>
                                        <span class="badge badge-rol badge-<?php echo $usuario['rol']; ?>">
                                            <?php echo ucfirst($usuario['rol']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <i class="fas fa-circle <?php echo $usuario['estado'] ? 'estado-activo' : 'estado-inactivo'; ?>"></i>
                                        <?php echo $usuario['estado'] ? 'Activo' : 'Inactivo'; ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($usuario['fecha_ultima_sesion']) {
                                            echo date('d/m/Y H:i', strtotime($usuario['fecha_ultima_sesion']));
                                        } else {
                                            echo '<span class="text-muted">Nunca</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-primary btn-action"
                                            onclick='editarUsuario(<?php echo htmlspecialchars(json_encode($usuario)); ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>

                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="accion" value="cambiar_estado">
                                            <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                                            <input type="hidden" name="estado" value="<?php echo $usuario['estado'] ? 0 : 1; ?>">
                                            <button type="submit" class="btn btn-sm btn-<?php echo $usuario['estado'] ? 'warning' : 'success'; ?> btn-action"
                                                <?php echo $usuario['id'] == 1 ? 'disabled' : ''; ?>>
                                                <i class="fas fa-<?php echo $usuario['estado'] ? 'ban' : 'check'; ?>"></i>
                                            </button>
                                        </form>

                                        <?php if ($usuario['id'] != 1): ?>
                                            <button class="btn btn-sm btn-danger btn-action"
                                                onclick="confirmarEliminar(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['nombre']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
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

<!-- Modal Crear Usuario -->
<div class="modal fade" id="modalCrearUsuario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus me-2"></i>Crear Nuevo Usuario
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="crear">
                    <div class="mb-3">
                        <label class="form-label">Nombre Completo</label>
                        <input type="text" class="form-control" name="nombre" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña</label>
                        <input type="password" class="form-control" name="password" required minlength="6">
                        <small class="text-muted">Mínimo 6 caracteres</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rol</label>
                        <select class="form-select" name="rol" required>
                            <option value="admin">Administrador - Acceso total</option>
                            <option value="vendedor" selected>Vendedor - Ventas, clientes, caja</option>
                            <option value="repositor">Repositor - Productos, proveedores</option>
                            <option value="cliente">Cliente - Solo visualización catálogo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Crear Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Usuario -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-edit me-2"></i>Editar Usuario
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label">Nombre Completo</label>
                        <input type="text" class="form-control" name="nombre" id="edit_nombre" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="edit_email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nueva Contraseña (dejar vacío para no cambiar)</label>
                        <input type="password" class="form-control" name="password" minlength="6">
                        <small class="text-muted">Solo completar si desea cambiar la contraseña</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rol</label>
                        <select class="form-select" name="rol" id="edit_rol" required>
                            <option value="admin">Administrador - Acceso total</option>
                            <option value="vendedor">Vendedor - Ventas, clientes, caja</option>
                            <option value="repositor">Repositor - Productos, proveedores</option>
                            <option value="cliente">Cliente - Solo visualización catálogo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Form para eliminar (oculto) -->
<form id="formEliminar" method="POST" style="display: none;">
    <input type="hidden" name="accion" value="eliminar">
    <input type="hidden" name="id" id="delete_id">
</form>

<?php
$inline_script = <<<'JAVASCRIPT'
function editarUsuario(usuario) {
    document.getElementById('edit_id').value = usuario.id;
    document.getElementById('edit_nombre').value = usuario.nombre;
    document.getElementById('edit_email').value = usuario.email;
    document.getElementById('edit_rol').value = usuario.rol;

    const modal = new bootstrap.Modal(document.getElementById('modalEditarUsuario'));
    modal.show();
}

function confirmarEliminar(id, nombre) {
    if (confirm(`¿Estás seguro de eliminar al usuario "${nombre}"?\n\nEsta acción no se puede deshacer.`)) {
        document.getElementById('delete_id').value = id;
        document.getElementById('formEliminar').submit();
    }
}
JAVASCRIPT;

include '../includes/footer.php';
?>