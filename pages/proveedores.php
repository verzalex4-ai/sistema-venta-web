<?php
/**
 * PROVEEDORES - Versión optimizada
 */
require_once '../config.php';

requiere_permiso('proveedores');

// ============================================
// PROCESAR ACCIONES
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    
    try {
        if ($accion === 'crear' || $accion === 'editar') {
            $id = $accion === 'editar' ? intval($_POST['id']) : 0;
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
            
            if ($accion === 'crear') {
                $sql = "INSERT INTO proveedores (nombre, razon_social, cuit, email, telefono, direccion, 
                        ciudad, provincia, codigo_postal, contacto_nombre, contacto_telefono) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssssssss", $nombre, $razon_social, $cuit, $email, $telefono, 
                                 $direccion, $ciudad, $provincia, $codigo_postal, $contacto_nombre, $contacto_telefono);
                $stmt->execute();
                
                set_mensaje('Proveedor creado exitosamente', 'success');
            } else {
                $sql = "UPDATE proveedores SET nombre=?, razon_social=?, cuit=?, email=?, telefono=?, 
                        direccion=?, ciudad=?, provincia=?, codigo_postal=?, contacto_nombre=?, contacto_telefono=? 
                        WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssssssssi", $nombre, $razon_social, $cuit, $email, $telefono, 
                                 $direccion, $ciudad, $provincia, $codigo_postal, $contacto_nombre, $contacto_telefono, $id);
                $stmt->execute();
                
                set_mensaje('Proveedor actualizado exitosamente', 'success');
            }
        } elseif ($accion === 'eliminar') {
            $id = intval($_POST['id']);
            $conn->query("UPDATE proveedores SET estado=0 WHERE id=$id");
            set_mensaje('Proveedor desactivado exitosamente', 'success');
        }
        
        // Redirigir para limpiar POST
        header('Location: proveedores.php');
        exit;
        
    } catch (Exception $e) {
        set_mensaje('Error: ' . $e->getMessage(), 'danger');
    }
}

// ============================================
// OBTENER PROVEEDORES
// ============================================
$proveedores = $conn->query("
    SELECT * FROM proveedores 
    WHERE estado=1 
    ORDER BY nombre
")->fetch_all(MYSQLI_ASSOC);

$page_title = 'Proveedores - Sistema de Ventas';
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
                <i class="fas fa-truck me-2"></i>Gestión de Proveedores
            </h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProveedor">
                <i class="fas fa-plus me-2"></i>Nuevo Proveedor
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
                                    Total Proveedores
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo count($proveedores); ?>
                                </div>
                            </div>
                            <i class="fas fa-truck fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabla de Proveedores -->
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Lista de Proveedores</h6>
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
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($proveedores) > 0): ?>
                                <?php foreach ($proveedores as $prov): ?>
                                <tr>
                                    <td><?php echo $prov['id']; ?></td>
                                    <td>
                                        <i class="fas fa-building text-primary me-2"></i>
                                        <strong><?php echo htmlspecialchars($prov['nombre']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($prov['cuit'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($prov['email'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($prov['telefono'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($prov['ciudad'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($prov['contacto_nombre'] ?? '-'); ?></td>
                                    <td>
                                        <button class="btn btn-info btn-sm" 
                                                onclick='editarProveedor(<?php echo json_encode($prov); ?>)' 
                                                title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" style="display:inline;" 
                                              onsubmit="return confirm('¿Desactivar este proveedor?');">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo $prov['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Desactivar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="fas fa-truck fa-3x mb-3"></i><br>
                                        No hay proveedores registrados
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
                    
                    <h6 class="mb-3">Información General</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre Comercial *</label>
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
                            <input type="text" class="form-control" name="cuit" id="cuit" 
                                   placeholder="20-12345678-9">
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
                    <h6 class="mb-3">Datos de Contacto</h6>
                    
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

<?php
$inline_script = <<<'JAVASCRIPT'
function editarProveedor(prov) {
    document.getElementById('modalTitle').textContent = 'Editar Proveedor';
    document.getElementById('accion').value = 'editar';
    document.getElementById('proveedor_id').value = prov.id;
    document.getElementById('nombre').value = prov.nombre;
    document.getElementById('razon_social').value = prov.razon_social || '';
    document.getElementById('cuit').value = prov.cuit || '';
    document.getElementById('email').value = prov.email || '';
    document.getElementById('telefono').value = prov.telefono || '';
    document.getElementById('direccion').value = prov.direccion || '';
    document.getElementById('ciudad').value = prov.ciudad || '';
    document.getElementById('provincia').value = prov.provincia || '';
    document.getElementById('codigo_postal').value = prov.codigo_postal || '';
    document.getElementById('contacto_nombre').value = prov.contacto_nombre || '';
    document.getElementById('contacto_telefono').value = prov.contacto_telefono || '';
    
    new bootstrap.Modal(document.getElementById('modalProveedor')).show();
}

// Resetear modal al cerrar
document.getElementById('modalProveedor').addEventListener('hidden.bs.modal', function() {
    document.getElementById('formProveedor').reset();
    document.getElementById('modalTitle').textContent = 'Nuevo Proveedor';
    document.getElementById('accion').value = 'crear';
});
JAVASCRIPT;

include '../includes/footer.php';
?>