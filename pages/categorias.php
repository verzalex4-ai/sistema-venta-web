<?php
/**
 * CATEGORÍAS - Versión optimizada
 */
require_once '../config.php';

requiere_permiso('productos');

// ============================================
// PROCESAR ACCIONES
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    
    try {
        if ($accion === 'crear' || $accion === 'editar') {
            $id = $accion === 'editar' ? intval($_POST['id']) : 0;
            $nombre = limpiar_entrada($_POST['nombre']);
            $descripcion = limpiar_entrada($_POST['descripcion']);
            $icono = limpiar_entrada($_POST['icono']);
            
            if ($accion === 'crear') {
                $sql = "INSERT INTO categorias (nombre, descripcion) VALUES (?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $nombre, $descripcion);
                $stmt->execute();
                $cat_id = $stmt->insert_id;
                
                // Guardar ícono
                $conn->query("INSERT INTO categorias_iconos (categoria_id, icono) VALUES ($cat_id, '$icono')");
                
                set_mensaje('Categoría creada exitosamente', 'success');
            } else {
                $sql = "UPDATE categorias SET nombre=?, descripcion=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $nombre, $descripcion, $id);
                $stmt->execute();
                
                // Actualizar ícono
                $conn->query("INSERT INTO categorias_iconos (categoria_id, icono) VALUES ($id, '$icono')
                             ON DUPLICATE KEY UPDATE icono='$icono'");
                
                set_mensaje('Categoría actualizada exitosamente', 'success');
            }
        } elseif ($accion === 'eliminar') {
            $id = intval($_POST['id']);
            
            // Verificar si tiene productos
            $check = $conn->query("SELECT COUNT(*) as total FROM productos WHERE categoria_id=$id");
            $count = $check->fetch_assoc()['total'];
            
            if ($count > 0) {
                set_mensaje("No se puede eliminar. La categoría tiene $count productos asignados.", 'warning');
            } else {
                $conn->query("DELETE FROM categorias WHERE id=$id");
                set_mensaje('Categoría eliminada exitosamente', 'success');
            }
        } elseif ($accion === 'toggle_estado') {
            $id = intval($_POST['id']);
            $conn->query("UPDATE categorias SET estado = NOT estado WHERE id=$id");
            set_mensaje('Estado actualizado', 'success');
        }
        
        // Redirigir para limpiar POST
        header('Location: categorias.php');
        exit;
        
    } catch (Exception $e) {
        set_mensaje('Error: ' . $e->getMessage(), 'danger');
    }
}

// ============================================
// OBTENER CATEGORÍAS
// ============================================
$categorias = $conn->query("
    SELECT c.*, ci.icono, COUNT(p.id) as total_productos
    FROM categorias c
    LEFT JOIN categorias_iconos ci ON c.id = ci.categoria_id
    LEFT JOIN productos p ON c.id = p.categoria_id
    GROUP BY c.id
    ORDER BY c.nombre
")->fetch_all(MYSQLI_ASSOC);

// Iconos disponibles
$iconos = [
    'fa-box' => 'Caja',
    'fa-broom' => 'Escoba',
    'fa-sink' => 'Vajilla',
    'fa-shirt' => 'Ropa',
    'fa-toilet' => 'Baño',
    'fa-spray-can' => 'Spray',
    'fa-pump-soap' => 'Jabón',
    'fa-toolbox' => 'Herramientas',
    'fa-pump-medical' => 'Desinfectante',
    'fa-toilet-paper' => 'Papel',
    'fa-bottle-droplet' => 'Líquidos',
    'fa-hand-sparkles' => 'Limpieza',
    'fa-jug-detergent' => 'Detergente',
    'fa-bucket' => 'Balde',
    'fa-sponge' => 'Esponja',
    'fa-brush' => 'Cepillo',
    'fa-cube' => 'Genérico'
];

$page_title = 'Categorías - Sistema de Ventas';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div id="content-wrapper" class="d-flex flex-column">
    <?php include '../includes/topbar.php'; ?>
    
    <div class="container-fluid px-4 py-4">
        
        <?php mostrar_mensaje(); ?>
        
        <!-- Encabezado -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-tags me-2"></i>Gestión de Categorías
            </h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCategoria">
                <i class="fas fa-plus me-2"></i>Nueva Categoría
            </button>
        </div>
        
        <!-- Tabla de Categorías -->
        <div class="card shadow">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="60">Ícono</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th width="120" class="text-center">Productos</th>
                                <th width="100" class="text-center">Estado</th>
                                <th width="150" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($categorias) > 0): ?>
                                <?php foreach ($categorias as $cat): ?>
                                <tr>
                                    <td class="text-center">
                                        <i class="fas <?php echo $cat['icono'] ?? 'fa-cube'; ?> fa-2x text-primary"></i>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($cat['nombre']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($cat['descripcion'] ?? ''); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-primary"><?php echo $cat['total_productos']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="accion" value="toggle_estado">
                                            <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                            <button type="submit" class="btn btn-sm <?php echo $cat['estado'] ? 'btn-success' : 'btn-secondary'; ?>">
                                                <?php echo $cat['estado'] ? 'Activa' : 'Inactiva'; ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-info" 
                                                onclick='editarCategoria(<?php echo json_encode($cat); ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" style="display:inline;" 
                                              onsubmit="return confirm('¿Eliminar categoría?');">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" 
                                                    <?php echo $cat['total_productos'] > 0 ? 'disabled title="No se puede eliminar: tiene productos"' : ''; ?>>
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-tags fa-3x mb-3"></i><br>
                                        No hay categorías registradas
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

<!-- Modal Categoría -->
<div class="modal fade" id="modalCategoria" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Nueva Categoría</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formCategoria">
                <div class="modal-body">
                    <input type="hidden" name="accion" id="accion" value="crear">
                    <input type="hidden" name="id" id="categoria_id">
                    <input type="hidden" name="icono" id="icono_seleccionado" value="fa-cube">
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre *</label>
                        <input type="text" class="form-control" name="nombre" id="nombre" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" id="descripcion" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ícono *</label>
                        <div class="row g-2">
                            <?php foreach ($iconos as $clase => $nombre): ?>
                                <div class="col-3">
                                    <div class="icon-option" data-icon="<?php echo $clase; ?>" 
                                         onclick="seleccionarIcono('<?php echo $clase; ?>')">
                                        <i class="fas <?php echo $clase; ?>"></i>
                                        <span><?php echo $nombre; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
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

<style>
.icon-option {
    padding: 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}

.icon-option:hover {
    border-color: var(--primary-color);
    background-color: #f8fafc;
}

.icon-option.selected {
    border-color: var(--primary-color);
    background-color: #e0e7ff;
}

.icon-option i {
    display: block;
    font-size: 2rem;
    margin-bottom: 0.5rem;
    color: var(--primary-color);
}

.icon-option span {
    display: block;
    font-size: 0.75rem;
    color: var(--text-secondary);
}
</style>

<?php
$inline_script = <<<'JAVASCRIPT'
function seleccionarIcono(icono) {
    document.getElementById('icono_seleccionado').value = icono;
    document.querySelectorAll('.icon-option').forEach(el => el.classList.remove('selected'));
    document.querySelector(`[data-icon="${icono}"]`).classList.add('selected');
}

function editarCategoria(cat) {
    document.getElementById('modalTitle').textContent = 'Editar Categoría';
    document.getElementById('accion').value = 'editar';
    document.getElementById('categoria_id').value = cat.id;
    document.getElementById('nombre').value = cat.nombre;
    document.getElementById('descripcion').value = cat.descripcion || '';
    
    const icono = cat.icono || 'fa-cube';
    seleccionarIcono(icono);
    
    new bootstrap.Modal(document.getElementById('modalCategoria')).show();
}

// Resetear modal al cerrar
document.getElementById('modalCategoria').addEventListener('hidden.bs.modal', function() {
    document.getElementById('formCategoria').reset();
    document.getElementById('modalTitle').textContent = 'Nueva Categoría';
    document.getElementById('accion').value = 'crear';
    seleccionarIcono('fa-cube');
});

// Seleccionar ícono por defecto
document.addEventListener('DOMContentLoaded', function() {
    seleccionarIcono('fa-cube');
});
JAVASCRIPT;

include '../includes/footer.php';
?>