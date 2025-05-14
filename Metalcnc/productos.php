<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require 'db_connection.php';

// Validaciones
function validarDatosProducto($datos) {
    $errores = [];
    
    if (empty($datos['nombre_producto'])) {
        $errores['nombre_producto'] = "El nombre del producto es obligatorio";
    } elseif (strlen($datos['nombre_producto']) < 3) {
        $errores['nombre_producto'] = "El nombre debe tener al menos 3 caracteres";
    }
    
    if (!isset($datos['cantidad']) || !is_numeric($datos['cantidad']) || $datos['cantidad'] < 0) {
        $errores['cantidad'] = "La cantidad debe ser un número positivo";
    }
    
    if (!isset($datos['precio_unitario']) || !is_numeric($datos['precio_unitario']) || $datos['precio_unitario'] <= 0) {
        $errores['precio_unitario'] = "El precio debe ser un número mayor a 0";
    }
    
    if (empty($datos['ubicacion'])) {
        $errores['ubicacion'] = "La ubicación es obligatoria";
    }
    
    return $errores;
}

// Procesar formulario
if (isset($_POST['agregar_producto']) || isset($_POST['editar_producto'])) {
    $datos = [
        'nombre_producto' => trim($_POST['nombre_producto']),
        'descripcion' => trim($_POST['descripcion']),
        'cantidad' => intval($_POST['cantidad']),
        'precio_unitario' => floatval($_POST['precio_unitario']),
        'ubicacion' => trim($_POST['ubicacion'])
    ];
    
    if (isset($_POST['editar_producto'])) {
        $datos['id_producto'] = intval($_POST['id_producto']);
    }
    
    $errores = validarDatosProducto($datos);
    
    if (empty($errores)) {
        try {
            if (isset($_POST['agregar_producto'])) {
                $stmt = $mysqli->prepare("INSERT INTO almacen_productos (nombre_producto, descripcion, cantidad, precio_unitario, ubicacion) 
                                         VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("ssids", $datos['nombre_producto'], $datos['descripcion'], $datos['cantidad'], 
                                 $datos['precio_unitario'], $datos['ubicacion']);
            } else {
                $stmt = $mysqli->prepare("UPDATE almacen_productos SET nombre_producto=?, descripcion=?, cantidad=?, 
                                         precio_unitario=?, ubicacion=? WHERE id_producto=?");
                $stmt->bind_param("ssidsi", $datos['nombre_producto'], $datos['descripcion'], $datos['cantidad'], 
                                 $datos['precio_unitario'], $datos['ubicacion'], $datos['id_producto']);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Error al guardar el producto: " . $stmt->error);
            }
            
            $_SESSION['exito'] = isset($_POST['agregar_producto']) ? "✅ Producto agregado correctamente" : "✅ Producto actualizado correctamente";
            header("Location: principal.php?vista=productos");
            exit();
            
        } catch (Exception $e) {
            $_SESSION['error'] = "❌ Error: " . $e->getMessage();
            header("Location: principal.php?vista=productos");
            exit();
        }
    } else {
        $_SESSION['errores_validacion'] = $errores;
        $_SESSION['datos_formulario'] = $datos;
        header("Location: principal.php?vista=productos");
        exit();
    }
}

// Eliminar producto
if (isset($_GET['eliminar_producto'])) {
    $id_producto = intval($_GET['eliminar_producto']);
    $stmt = $mysqli->prepare("DELETE FROM almacen_productos WHERE id_producto = ?");
    $stmt->bind_param("i", $id_producto);
    $stmt->execute();
    $stmt->close();
    $_SESSION['exito'] = "Producto eliminado correctamente";
    header("Location: principal.php?vista=productos");
    exit();
}

// Obtener producto para editar
$producto = null;
if (isset($_GET['editar_producto'])) {
    $id_producto = intval($_GET['editar_producto']);
    $stmt = $mysqli->prepare("SELECT * FROM almacen_productos WHERE id_producto = ?");
    $stmt->bind_param("i", $id_producto);
    $stmt->execute();
    $result = $stmt->get_result();
    $producto = $result->fetch_assoc();
    $stmt->close();
}

// Obtener lista de productos
$resultado = $mysqli->query("SELECT * FROM almacen_productos ORDER BY nombre_producto");

// Búsqueda de productos
$productosEncontrados = [];
if (isset($_POST['buscar_producto'])) {
    $busqueda = "%" . trim($_POST['busqueda']) . "%";
    $stmt = $mysqli->prepare("SELECT * FROM almacen_productos WHERE nombre_producto LIKE ? OR descripcion LIKE ? OR ubicacion LIKE ?");
    $stmt->bind_param("sss", $busqueda, $busqueda, $busqueda);
    $stmt->execute();
    $resultadoBusqueda = $stmt->get_result();
    while ($row = $resultadoBusqueda->fetch_assoc()) {
        $productosEncontrados[] = $row;
    }
    $stmt->close();
}

// Recuperar datos del formulario si hay errores
$formData = isset($_SESSION['datos_formulario']) ? $_SESSION['datos_formulario'] : [];
$errores = isset($_SESSION['errores_validacion']) ? $_SESSION['errores_validacion'] : [];
unset($_SESSION['datos_formulario']);
unset($_SESSION['errores_validacion']);

// Si estamos editando, usar los datos del producto
if ($producto && empty($formData)) {
    $formData = [
        'nombre_producto' => $producto['nombre_producto'],
        'descripcion' => $producto['descripcion'],
        'cantidad' => $producto['cantidad'],
        'precio_unitario' => $producto['precio_unitario'],
        'ubicacion' => $producto['ubicacion']
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos | Sistema</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
    /* Estilos iguales a los de agregarCliente.php */
    :root {
        --primary: #4361ee;
        --primary-hover: #3a56d4;
        --secondary: #3f37c9;
        --accent: #4895ef;
        --danger: #e63946;
        --danger-hover: #d62839;
        --success: #4cc9f0;
        --success-hover: #3ab7de;
        --warning: #f8961e;
        --edit: #38b000;
        --edit-hover: #32a000;
        --light: #f8f9fa;
        --dark: #212529;
        --gray: #6c757d;
        --gray-light: #e9ecef;
        --border-radius: 20px;
        --box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        --transition: all 0.3s ease;
    }

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
        font-family: 'Poppins', sans-serif;
        background-color: #f5f7fa;
        color: var(--dark);
        line-height: 1.6;
        font-size: 14px;
    }

    .container {
        display: flex;
        flex-wrap: wrap;
        gap: 25px;
        padding: 25px;
        max-width: 1400px;
        margin: 0 auto;
    }

    .panel {
        background: white;
        border-radius: 12px;
        box-shadow: var(--box-shadow);
        padding: 25px;
        flex: 1;
        min-width: 300px;
        transition: var(--transition);
    }

    .panel:hover {
        box-shadow: 0 6px 24px rgba(0, 0, 0, 0.12);
    }

    .left-panel {
        flex: 2;
    }

    .right-panel {
        flex: 1;
    }

    h2 {
        color: var(--primary);
        margin-bottom: 20px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 18px;
    }

    .form-group {
        margin-bottom: 20px;
        position: relative;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--dark);
        font-size: 13px;
    }

    .form-group i {
        position: absolute;
        left: 15px;
        top: 40px;
        color: var(--gray);
        font-size: 14px;
    }

    input, select, textarea {
        padding: 10px 15px 10px 40px;
        border: 1px solid #ddd;
        border-radius: var(--border-radius);
        font-size: 13px;
        width: 100%;
        transition: var(--transition);
        font-family: 'Poppins', sans-serif;
    }

    textarea {
        min-height: 100px;
        padding-left: 15px;
    }

    input:focus, select:focus, textarea:focus {
        border-color: var(--accent);
        outline: none;
        box-shadow: 0 0 0 3px rgba(72, 149, 239, 0.2);
    }

    /* Estilos para botones */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 8px 16px;
        border: none;
        border-radius: var(--border-radius);
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: var(--transition);
        text-decoration: none;
        gap: 6px;
    }

    .btn-primary {
        background-color: var(--primary);
        color: white;
    }

    .btn-primary:hover {
        background-color: var(--primary-hover);
        transform: translateY(-2px);
    }

    .btn-reset {
        background-color: var(--gray);
        color: white;
    }

    .btn-reset:hover {
        background-color: #5a6268;
        transform: translateY(-2px);
    }

    .btn-search {
        background-color: var(--success);
        color: white;
    }

    .btn-search:hover {
        background-color: var(--success-hover);
        transform: translateY(-2px);
    }

    .btn-edit {
        background-color: var(--edit);
        color: white;
    }

    .btn-edit:hover {
        background-color: var(--edit-hover);
        transform: translateY(-2px);
    }

    .btn-delete {
        background-color: var(--danger);
        color: white;
    }

    .btn-delete:hover {
        background-color: var(--danger-hover);
        transform: translateY(-2px);
    }

    .btn-sm {
        padding: 6px 12px;
        font-size: 12px;
    }

    .btn-icon {
        padding: 8px;
        width: 36px;
        height: 36px;
        font-size: 14px;
    }

    .button-group {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }

    /* Tabla */
    .table-responsive {
        overflow-x: auto;
        margin-top: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: white;
        border-radius: 12px;
        overflow: hidden;
    }

    table th {
        background-color: var(--primary);
        color: white;
        padding: 12px 15px;
        text-align: left;
        position: sticky;
        top: 0;
        font-weight: 500;
        font-size: 13px;
    }

    table td {
        padding: 10px 12px;
        border-bottom: 1px solid var(--gray-light);
        font-size: 13px;
    }

    table tr:last-child td {
        border-bottom: none;
    }

    table tr:nth-child(even) {
        background-color: #f8f9fa;
    }

    table tr:hover {
        background-color: #f1f3f5;
    }

    .actions-cell {
        display: flex;
        gap: 6px;
    }

    /* Buscador */
    .search-form {
        margin-bottom: 20px;
    }

    .search-form .input-group {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .search-form .form-group {
        flex: 1;
        position: relative;
        margin-bottom: 0;
    }

    .search-form input {
        padding-left: 40px;
        height: 40px;
        border-radius: 30px;
        font-size: 13px;
    }

    .search-form .form-group i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray);
        font-size: 14px;
        z-index: 2;
    }

    .search-form .btn {
        border-radius: 30px;
        height: 40px;
        width: auto;
        padding: 0 16px;
        font-size: 13px;
    }

    /* Mensajes flotantes */
    #floating-messages {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1000;
        max-width: 350px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .message {
        padding: 12px 16px;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        display: flex;
        align-items: center;
        gap: 10px;
        position: relative;
        overflow: hidden;
        animation: slideIn 0.5s forwards;
        border-left: 4px solid transparent;
        font-size: 13px;
    }

    .message::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 3px;
        background: rgba(0,0,0,0.1);
    }

    .message.error-message {
        background-color: #fff5f5;
        color: #d32f2f;
        border-left-color: #d32f2f;
    }

    .message.exito-message {
        background-color: #f1f8e9;
        color: #2e7d32;
        border-left-color: #2e7d32;
    }

    .message button {
        margin-left: auto;
        background: none;
        color: inherit;
        border: none;
        cursor: pointer;
        font-size: 16px;
        padding: 0;
        width: auto;
    }

    /* Validación */
    .is-invalid {
        border-color: #d32f2f !important;
    }

    .invalid-feedback {
        color: #d32f2f;
        font-size: 12px;
        margin-top: 4px;
        display: block;
    }

    /* Animaciones */
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }

    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }

    /* Responsive */
    @media (max-width: 992px) {
        .container {
            flex-direction: column;
        }
        
        .panel {
            width: 100%;
        }
    }

    @media (max-width: 576px) {
        .actions-cell {
            flex-direction: column;
        }
        
        .search-form .input-group {
            flex-direction: column;
        }
        
        .search-form .btn {
            width: 100%;
        }
        
        .button-group {
            flex-direction: column;
        }
        
        body {
            font-size: 13px;
        }
        
        h2 {
            font-size: 16px;
        }
    }

    /* Estilos específicos para productos */
    .stock-bajo {
        color: var(--danger);
        font-weight: bold;
    }

    .stock-medio {
        color: var(--warning);
    }

    .stock-alto {
        color: var(--success);
    }
    </style>
</head>
<body>
    <!-- Mensajes flotantes -->
    <div id="floating-messages">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error-message">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                <button onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['exito'])): ?>
            <div class="message exito-message">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $_SESSION['exito']; unset($_SESSION['exito']); ?></span>
                <button onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>
    </div>

    <div class="container">
        <div class="panel left-panel">
            <h2><i class="fas fa-boxes"></i> Inventario de Productos</h2>
            
            <!-- Formulario de búsqueda -->
            <form method="POST" class="search-form">
                <div class="input-group">
                    <div class="form-group">
                        <input type="text" name="busqueda" placeholder="Buscar productos..." 
                               value="<?php echo isset($_POST['busqueda']) ? htmlspecialchars($_POST['busqueda']) : ''; ?>">
                        <i class="fas fa-search"></i>
                    </div>
                    <button type="submit" name="buscar_producto" class="btn btn-search">
                        <i class="fas fa-search"></i> <span class="btn-text">Buscar</span>
                    </button>
                    <button type="submit" name="mostrar_todos" class="btn btn-reset">
                        <i class="fas fa-sync-alt"></i> <span class="btn-text">Todos</span>
                    </button>
                </div>
            </form>
            
            <!-- Tabla de productos -->
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>Ubicación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($productosEncontrados)): ?>
                            <?php foreach ($productosEncontrados as $prod): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($prod['id_producto']); ?></td>
                                    <td><?php echo htmlspecialchars($prod['nombre_producto']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($prod['descripcion'], 0, 50)); ?>...</td>
                                    <td class="<?php echo $prod['cantidad'] < 5 ? 'stock-bajo' : ($prod['cantidad'] < 15 ? 'stock-medio' : 'stock-alto'); ?>">
                                        <?php echo htmlspecialchars($prod['cantidad']); ?>
                                    </td>
                                    <td><?php echo number_format($prod['precio_unitario'], 2); ?> Bs.</td>
                                    <td><?php echo htmlspecialchars($prod['ubicacion']); ?></td>
                                    <td class="actions-cell">
                                        <a href="principal.php?vista=productos&editar_producto=<?php echo $prod['id_producto']; ?>" class="btn btn-edit">
                                            <i class="fas fa-edit"></i> <span class="btn-text">Editar</span>
                                        </a>
                                        <a href="principal.php?vista=productos&eliminar_producto=<?php echo $prod['id_producto']; ?>" class="btn btn-delete" onclick="return confirm('¿Estás seguro de eliminar este producto?')">
                                            <i class="fas fa-trash-alt"></i> <span class="btn-text">Eliminar</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php while ($row = $resultado->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['id_producto']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nombre_producto']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($row['descripcion'], 0, 50)); ?>...</td>
                                    <td class="<?php echo $row['cantidad'] < 5 ? 'stock-bajo' : ($row['cantidad'] < 15 ? 'stock-medio' : 'stock-alto'); ?>">
                                        <?php echo htmlspecialchars($row['cantidad']); ?>
                                    </td>
                                    <td><?php echo number_format($row['precio_unitario'], 2); ?> Bs.</td>
                                    <td><?php echo htmlspecialchars($row['ubicacion']); ?></td>
                                    <td class="actions-cell">
                                        <a href="principal.php?vista=productos&editar_producto=<?php echo $row['id_producto']; ?>" class="btn btn-edit">
                                            <i class="fas fa-edit"></i> <span class="btn-text">Editar</span>
                                        </a>
                                        <a href="principal.php?vista=productos&eliminar_producto=<?php echo $row['id_producto']; ?>" class="btn btn-delete" onclick="return confirm('¿Estás seguro de eliminar este producto?')">
                                            <i class="fas fa-trash-alt"></i> <span class="btn-text">Eliminar</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel right-panel">
            <h2><i class="fas fa-box-open"></i> <?php echo $producto ? "Editar Producto" : "Agregar Producto"; ?></h2>
            
            <!-- Formulario de agregar/editar producto -->
            <form method="POST" id="formProducto">
                <?php if ($producto): ?>
                    <input type="hidden" name="id_producto" value="<?php echo htmlspecialchars($producto['id_producto']); ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="nombre_producto"><i class="fas fa-tag"></i> Nombre del Producto *</label>
                    <input type="text" name="nombre_producto" id="nombre_producto"
                           value="<?php echo htmlspecialchars($formData['nombre_producto'] ?? ''); ?>"
                           placeholder="Ej: Soporte metálico para estante"
                           required
                           class="<?php echo isset($errores['nombre_producto']) ? 'is-invalid' : ''; ?>">
                    <i class="fas fa-tag"></i>
                    <?php if (isset($errores['nombre_producto'])): ?>
                        <span class="invalid-feedback"><?php echo $errores['nombre_producto']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="descripcion"><i class="fas fa-align-left"></i> Descripción</label>
                    <textarea name="descripcion" id="descripcion"
                              placeholder="Descripción detallada del producto..."><?php echo htmlspecialchars($formData['descripcion'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="cantidad"><i class="fas fa-layer-group"></i> Cantidad en Stock *</label>
                    <input type="number" name="cantidad" id="cantidad"
                           value="<?php echo htmlspecialchars($formData['cantidad'] ?? '0'); ?>"
                           min="0"
                           step="1"
                           required
                           class="<?php echo isset($errores['cantidad']) ? 'is-invalid' : ''; ?>">
                    <i class="fas fa-boxes"></i>
                    <?php if (isset($errores['cantidad'])): ?>
                        <span class="invalid-feedback"><?php echo $errores['cantidad']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="precio_unitario"><i class="fas fa-dollar-sign"></i> Precio Unitario (Bs.) *</label>
                    <input type="number" name="precio_unitario" id="precio_unitario"
                           value="<?php echo htmlspecialchars($formData['precio_unitario'] ?? '0'); ?>"
                           min="0"
                           step="0.01"
                           required
                           class="<?php echo isset($errores['precio_unitario']) ? 'is-invalid' : ''; ?>">
                    <i class="fas fa-money-bill-wave"></i>
                    <?php if (isset($errores['precio_unitario'])): ?>
                        <span class="invalid-feedback"><?php echo $errores['precio_unitario']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="ubicacion"><i class="fas fa-map-marker-alt"></i> Ubicación en Almacén *</label>
                    <input type="text" name="ubicacion" id="ubicacion"
                           value="<?php echo htmlspecialchars($formData['ubicacion'] ?? ''); ?>"
                           placeholder="Ej: Estantería A, Nivel 3"
                           required
                           class="<?php echo isset($errores['ubicacion']) ? 'is-invalid' : ''; ?>">
                    <i class="fas fa-warehouse"></i>
                    <?php if (isset($errores['ubicacion'])): ?>
                        <span class="invalid-feedback"><?php echo $errores['ubicacion']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="button-group">
                    <button type="submit" name="<?php echo $producto ? 'editar_producto' : 'agregar_producto'; ?>" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo $producto ? "Actualizar" : "Guardar"; ?>
                    </button>
                    <button type="reset" class="btn btn-reset">
                        <i class="fas fa-eraser"></i> Limpiar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Validación en tiempo real
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-eliminar mensajes después de 5 segundos
        const messages = document.querySelectorAll('#floating-messages .message');
        messages.forEach(msg => {
            setTimeout(() => {
                msg.style.animation = 'fadeOut 0.5s forwards';
                setTimeout(() => msg.remove(), 500);
            }, 5000);
        });

        // Validar números positivos
        const numberInputs = document.querySelectorAll('input[type="number"]');
        numberInputs.forEach(input => {
            input.addEventListener('change', function() {
                if (this.value < 0) {
                    this.value = 0;
                }
            });
        });
    });
    </script>
</body>
</html>