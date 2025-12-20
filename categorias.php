<?php
require_once 'config.php';
requiere_permiso('productos'); // Solo admin y repositor

// Procesar acciones
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        switch ($_POST['accion']) {
            case 'crear':
                $nombre = limpiar_entrada($_POST['nombre']);
                $descripcion = limpiar_entrada($_POST['descripcion']);
                $icono = limpiar_entrada($_POST['icono']);
                
                $stmt = $conn->prepare("INSERT INTO categorias (nombre, descripcion, estado) VALUES (?, ?, 1)");
                $stmt->bind_param("ss", $nombre, $descripcion);
                
                if ($stmt->execute()) {
                    $cat_id = $stmt->insert_id;
                    
                    // Guardar ícono
                    $conn->query("INSERT INTO categorias_iconos (categoria_id, icono) VALUES ($cat_id, '$icono')");
                    
                    $mensaje = 'Categoría creada exitosamente';
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = 'Error al crear categoría';
                    $tipo_mensaje = 'danger';
                }
                $stmt->close();
                break;
                
            case 'editar':
                $id = intval($_POST['id']);
                $nombre = limpiar_entrada($_POST['nombre']);
                $descripcion = limpiar_entrada($_POST['descripcion']);
                $icono = limpiar_entrada($_POST['icono']);
                
                $stmt = $conn->prepare("UPDATE categorias SET nombre=?, descripcion=? WHERE id=?");
                $stmt->bind_param("ssi", $nombre, $descripcion, $id);
                
                if ($stmt->execute()) {
                    // Actualizar ícono
                    $conn->query("INSERT INTO categorias_iconos (categoria_id, icono) VALUES ($id, '$icono')
                                 ON DUPLICATE KEY UPDATE icono='$icono'");
                    
                    $mensaje = 'Categoría actualizada exitosamente';
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = 'Error al actualizar categoría';
                    $tipo_mensaje = 'danger';
                }
                $stmt->close();
                break;
                
            case 'eliminar':
                $id = intval($_POST['id']);
                
                // Verificar si tiene productos
                $check = $conn->query("SELECT COUNT(*) as total FROM productos WHERE categoria_id=$id");
                $count = $check->fetch_assoc()['total'];
                
                if ($count > 0) {
                    $mensaje = "No se puede eliminar. La categoría tiene $count productos asignados.";
                    $tipo_mensaje = 'warning';
                } else {
                    $stmt = $conn->prepare("DELETE FROM categorias WHERE id=?");
                    $stmt->bind_param("i", $id);
                    
                    if ($stmt->execute()) {
                        $mensaje = 'Categoría eliminada exitosamente';
                        $tipo_mensaje = 'success';
                    } else {
                        $mensaje = 'Error al eliminar categoría';
                        $tipo_mensaje = 'danger';
                    }
                    $stmt->close();
                }
                break;
                
            case 'toggle_estado':
                $id = intval($_POST['id']);
                $conn->query("UPDATE categorias SET estado = NOT estado WHERE id=$id");
                $mensaje = 'Estado actualizado';
                $tipo_mensaje = 'success';
                break;
        }
    }
}

// Obtener todas las categorías con conteo de productos
$sql = "SELECT c.*, ci.icono, COUNT(p.id) as total_productos
        FROM categorias c
        LEFT JOIN categorias_iconos ci ON c.id = ci.categoria_id
        LEFT JOIN productos p ON c.id = p.categoria_id
        GROUP BY c.id
        ORDER BY c.nombre";
$result = $conn->query($sql);

// Iconos disponibles para seleccionar
$iconos_disponibles = [
    'fa-broom' => 'Escoba',
    'fa-sink' => 'Lavabo/Vajilla',
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Categorías - Sistema de Ventas</title>
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

        .icon-selector {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 0.5rem;
            max-height: 300px;
            overflow-y: auto;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: var(--border-radius);
        }

        .icon-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0.75rem;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.2s;
            background: white;
        }

        .icon-option:hover {
            border-color: var(--primary-color);
            background: var(--gray-50);
        }

        .icon-option.selected {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
        }

        .icon-option i {
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }

        .icon-option span {
            font-size: 0.7rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div id="wrapper">
        <?php include 'sidebar.php'; ?>

        <div id="content-wrapper" class="flex-fill">
            <?php include 'topbar.php'; ?>

            <div class="container-fluid px-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0"><i class="fas fa-tags me-2"></i>Gestión de Categorías</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCategoria">
                        <i class="fas fa-plus me-2"></i>Nueva Categoría
                    </button>
                </div>

                <?php if ($mensaje): ?>
                    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show">
                        <?php echo $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
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
                                    <?php while ($cat = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td class="text-center">
                                                <i class="fas <?php echo $cat['icono'] ?? 'fa-cube'; ?> fa-2x" 
                                                   style="color: var(--primary-color);"></i>
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
                                                <button class="btn btn-sm btn-primary" onclick="editarCategoria(<?php echo htmlspecialchars(json_encode($cat)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar categoría?');">
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" <?php echo $cat['total_productos'] > 0 ? 'disabled' : ''; ?>>
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
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

    <!-- Modal Categoría -->
    <div class="modal fade" id="modalCategoria" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Nueva Categoría</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="accion" id="accion" value="crear">
                        <input type="hidden" name="id" id="categoria_id">
                        
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
                            <input type="hidden" name="icono" id="icono_seleccionado" value="fa-cube" required>
                            <div class="icon-selector">
                                <?php foreach ($iconos_disponibles as $icono => $nombre): ?>
                                    <div class="icon-option" data-icon="<?php echo $icono; ?>" onclick="seleccionarIcono('<?php echo $icono; ?>')">
                                        <i class="fas <?php echo $icono; ?>"></i>
                                        <span><?php echo $nombre; ?></span>
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
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

        // Resetear formulario al cerrar modal
        document.getElementById('modalCategoria').addEventListener('hidden.bs.modal', function () {
            document.getElementById('modalTitle').textContent = 'Nueva Categoría';
            document.getElementById('accion').value = 'crear';
            document.getElementById('categoria_id').value = '';
            document.getElementById('nombre').value = '';
            document.getElementById('descripcion').value = '';
            seleccionarIcono('fa-cube');
        });

        // Seleccionar ícono por defecto al cargar
        seleccionarIcono('fa-cube');
    </script>
</body>
</html>
