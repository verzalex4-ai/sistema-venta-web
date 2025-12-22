<?php
/**
 * CLIENTES - Versión optimizada
 */
require_once '../config.php';

requiere_permiso('clientes');

// ============================================
// PROCESAR ACCIONES
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    
    try {
        if ($accion === 'crear' || $accion === 'editar') {
            $id = $accion === 'editar' ? intval($_POST['id']) : 0;
            $nombre = limpiar_entrada($_POST['nombre']);
            $apellido = limpiar_entrada($_POST['apellido']);
            $email = limpiar_entrada($_POST['email']);
            $telefono = limpiar_entrada($_POST['telefono']);
            $dni = limpiar_entrada($_POST['dni']);
            $direccion = limpiar_entrada($_POST['direccion']);
            
            if ($accion === 'crear') {
                $sql = "INSERT INTO clientes (nombre, apellido, email, telefono, dni, direccion) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssss", $nombre, $apellido, $email, $telefono, $dni, $direccion);
                $stmt->execute();
                
                set_mensaje('Cliente creado exitosamente', 'success');
            } else {
                $sql = "UPDATE clientes SET nombre=?, apellido=?, email=?, telefono=?, dni=?, direccion=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssi", $nombre, $apellido, $email, $telefono, $dni, $direccion, $id);
                $stmt->execute();
                
                set_mensaje('Cliente actualizado exitosamente', 'success');
            }
        } elseif ($accion === 'eliminar') {
            $id = intval($_POST['id']);
            $conn->query("UPDATE clientes SET estado=0 WHERE id=$id");
            set_mensaje('Cliente desactivado exitosamente', 'success');
        }
        
        // Redirigir para limpiar POST
        header('Location: clientes.php');
        exit;
        
    } catch (Exception $e) {
        set_mensaje('Error: ' . $e->getMessage(), 'danger');
    }
}

// ============================================
// OBTENER CLIENTES
// ============================================
$clientes = $conn->query("
    SELECT * FROM clientes 
    WHERE estado=1 
    ORDER BY apellido, nombre
")->fetch_all(MYSQLI_ASSOC);

$page_title = 'Clientes - Sistema de Ventas';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div id="content-wrapper" class="d-flex flex-column">
    <?php include '../includes/topbar.php'; ?>
    
    <div class="container-fluid px-4 py-4">
        
        <?php mostrar_mensaje(); ?>
        
        <!-- Encabezado -->
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-users me-2"></i>Gestión de Clientes
            </h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCliente">
                <i class="fas fa-plus me-2"></i>Nuevo Cliente
            </button>
        </div>
        
        <!-- Estadística rápida -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Clientes
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo count($clientes); ?>
                                </div>
                            </div>
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabla de Clientes -->
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Lista de Clientes</h6>
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
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($clientes) > 0): ?>
                                <?php foreach ($clientes as $cliente): ?>
                                <tr>
                                    <td><?php echo $cliente['id']; ?></td>
                                    <td>
                                        <i class="fas fa-user-circle text-primary me-2"></i>
                                        <strong><?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($cliente['dni']); ?></td>
                                    <td><?php echo htmlspecialchars($cliente['email'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($cliente['telefono'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars(substr($cliente['direccion'] ?? '-', 0, 30)); ?></td>
                                    <td>
                                        <button class="btn btn-info btn-sm" 
                                                onclick='editarCliente(<?php echo json_encode($cliente); ?>)' 
                                                title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" style="display:inline;" 
                                              onsubmit="return confirm('¿Desactivar este cliente?');">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Desactivar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="fas fa-users fa-3x mb-3"></i><br>
                                        No hay clientes registrados
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
                            <label class="form-label">Nombre *</label>
                            <input type="text" class="form-control" name="nombre" id="nombre" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Apellido *</label>
                            <input type="text" class="form-control" name="apellido" id="apellido" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">DNI *</label>
                            <input type="text" class="form-control" name="dni" id="dni" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="text" class="form-control" name="telefono" id="telefono">
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

<?php
$inline_script = <<<'JAVASCRIPT'
function editarCliente(cliente) {
    document.getElementById('modalTitle').textContent = 'Editar Cliente';
    document.getElementById('accion').value = 'editar';
    document.getElementById('cliente_id').value = cliente.id;
    document.getElementById('nombre').value = cliente.nombre;
    document.getElementById('apellido').value = cliente.apellido;
    document.getElementById('dni').value = cliente.dni;
    document.getElementById('telefono').value = cliente.telefono || '';
    document.getElementById('email').value = cliente.email || '';
    document.getElementById('direccion').value = cliente.direccion || '';
    
    new bootstrap.Modal(document.getElementById('modalCliente')).show();
}

// Resetear modal al cerrar
document.getElementById('modalCliente').addEventListener('hidden.bs.modal', function() {
    document.getElementById('formCliente').reset();
    document.getElementById('modalTitle').textContent = 'Nuevo Cliente';
    document.getElementById('accion').value = 'crear';
});
JAVASCRIPT;

include '../includes/footer.php';
?>