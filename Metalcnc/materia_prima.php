<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require 'db_connection.php';

// Validaciones
function validarDatosMateriaPrima($datos) {
    $errores = [];
    
    if (empty($datos['tipo_material'])) {
        $errores['tipo_material'] = "El tipo de material es obligatorio";
    }
    
    if (!isset($datos['grosor']) || !is_numeric($datos['grosor']) || $datos['grosor'] <= 0) {
        $errores['grosor'] = "El grosor debe ser un número positivo";
    }
    
    if (!isset($datos['cantidad']) || !is_numeric($datos['cantidad']) || $datos['cantidad'] < 0) {
        $errores['cantidad'] = "La cantidad debe ser un número positivo";
    }
    
    if (empty($datos['ubicacion'])) {
        $errores['ubicacion'] = "La ubicación es obligatoria";
    }
    
    return $errores;
}

// Procesar formulario
if (isset($_POST['agregar_materia_prima']) || isset($_POST['editar_materia_prima'])) {
    $datos = [
        'tipo_material' => trim($_POST['tipo_material']),
        'grosor' => floatval($_POST['grosor']),
        'cantidad' => intval($_POST['cantidad']),
        'id_proveedor' => !empty($_POST['id_proveedor']) ? intval($_POST['id_proveedor']) : null,
        'ubicacion' => trim($_POST['ubicacion'])
    ];
    
    if (isset($_POST['editar_materia_prima'])) {
        $datos['id_plancha'] = intval($_POST['id_plancha']);
    }
    
    $errores = validarDatosMateriaPrima($datos);
    
    if (empty($errores)) {
        try {
            if (isset($_POST['agregar_materia_prima'])) {
                $stmt = $mysqli->prepare("INSERT INTO almacen_materia_prima (tipo_material, grosor, cantidad, id_proveedor, ubicacion) 
                                         VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sdiss", $datos['tipo_material'], $datos['grosor'], $datos['cantidad'], 
                                 $datos['id_proveedor'], $datos['ubicacion']);
            } else {
                $stmt = $mysqli->prepare("UPDATE almacen_materia_prima SET tipo_material=?, grosor=?, cantidad=?, 
                                         id_proveedor=?, ubicacion=? WHERE id_plancha=?");
                $stmt->bind_param("sdisssi", $datos['tipo_material'], $datos['grosor'], $datos['cantidad'], 
                                 $datos['id_proveedor'], $datos['ubicacion'], $datos['id_plancha']);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Error al guardar la materia prima: " . $stmt->error);
            }
            
            $_SESSION['exito'] = isset($_POST['agregar_materia_prima']) ? "✅ Materia prima agregada correctamente" : "✅ Materia prima actualizada correctamente";
            header("Location: principal.php?vista=materia_prima");
            exit();
            
        } catch (Exception $e) {
            $_SESSION['error'] = "❌ Error: " . $e->getMessage();
            header("Location: principal.php?vista=materia_prima");
            exit();
        }
    } else {
        $_SESSION['errores_validacion'] = $errores;
        $_SESSION['datos_formulario'] = $datos;
        header("Location: principal.php?vista=materia_prima");
        exit();
    }
}

// Eliminar materia prima
if (isset($_GET['eliminar_materia_prima'])) {
    $id_plancha = intval($_GET['eliminar_materia_prima']);
    $stmt = $mysqli->prepare("DELETE FROM almacen_materia_prima WHERE id_plancha = ?");
    $stmt->bind_param("i", $id_plancha);
    $stmt->execute();
    $stmt->close();
    $_SESSION['exito'] = "Materia prima eliminada correctamente";
    header("Location: principal.php?vista=materia_prima");
    exit();
}

// Obtener materia prima para editar
$materiaPrima = null;
if (isset($_GET['editar_materia_prima'])) {
    $id_plancha = intval($_GET['editar_materia_prima']);
    $stmt = $mysqli->prepare("SELECT * FROM almacen_materia_prima WHERE id_plancha = ?");
    $stmt->bind_param("i", $id_plancha);
    $stmt->execute();
    $result = $stmt->get_result();
    $materiaPrima = $result->fetch_assoc();
    $stmt->close();
}

// Obtener lista de materia prima con información de proveedores
$resultado = $mysqli->query("
    SELECT mp.*, p.razon_social as nombre_proveedor 
    FROM almacen_materia_prima mp
    LEFT JOIN proveedores p ON mp.id_proveedor = p.id_proveedor
    ORDER BY mp.tipo_material, mp.grosor
");

// Obtener lista de proveedores para el select
$proveedores = $mysqli->query("SELECT id_proveedor, razon_social FROM proveedores ORDER BY razon_social");

// Búsqueda de materia prima
$materiasPrimasEncontradas = [];
if (isset($_POST['buscar_materia_prima'])) {
    $busqueda = "%" . trim($_POST['busqueda']) . "%";
    $stmt = $mysqli->prepare("
        SELECT mp.*, p.razon_social as nombre_proveedor 
        FROM almacen_materia_prima mp
        LEFT JOIN proveedores p ON mp.id_proveedor = p.id_proveedor
        WHERE mp.tipo_material LIKE ? OR mp.ubicacion LIKE ? OR p.razon_social LIKE ?
    ");
    $stmt->bind_param("sss", $busqueda, $busqueda, $busqueda);
    $stmt->execute();
    $resultadoBusqueda = $stmt->get_result();
    while ($row = $resultadoBusqueda->fetch_assoc()) {
        $materiasPrimasEncontradas[] = $row;
    }
    $stmt->close();
}

// Recuperar datos del formulario si hay errores
$formData = isset($_SESSION['datos_formulario']) ? $_SESSION['datos_formulario'] : [];
$errores = isset($_SESSION['errores_validacion']) ? $_SESSION['errores_validacion'] : [];
unset($_SESSION['datos_formulario']);
unset($_SESSION['errores_validacion']);

// Si estamos editando, usar los datos de la materia prima
if ($materiaPrima && empty($formData)) {
    $formData = [
        'tipo_material' => $materiaPrima['tipo_material'],
        'grosor' => $materiaPrima['grosor'],
        'cantidad' => $materiaPrima['cantidad'],
        'id_proveedor' => $materiaPrima['id_proveedor'],
        'ubicacion' => $materiaPrima['ubicacion']
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Materia Prima | Sistema</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
    /* Estilos iguales a los de productos.php */
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

    input, select {
        padding: 10px 15px 10px 40px;
        border: 1px solid #ddd;
        border-radius: var(--border-radius);
        font-size: 13px;
        width: 100%;
        transition: var(--transition);
        font-family: 'Poppins', sans-serif;
    }

    input:focus, select:focus {
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

    /* Estilos específicos para materia prima */
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

    .material-inox {
        background-color: #e6f7ff;
    }

    .material-aluminio {
        background-color: #f6ffed;
    }

    .material-acero {
        background-color: #fff7e6;
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
            <h2><i class="fas fa-cubes"></i> Inventario de Materia Prima</h2>
            
            <!-- Formulario de búsqueda -->
            <form method="POST" class="search-form">
                <div class="input-group">
                    <div class="form-group">
                        <input type="text" name="busqueda" placeholder="Buscar materia prima..." 
                               value="<?php echo isset($_POST['busqueda']) ? htmlspecialchars($_POST['busqueda']) : ''; ?>">
                        <i class="fas fa-search"></i>
                    </div>
                    <button type="submit" name="buscar_materia_prima" class="btn btn-search">
                        <i class="fas fa-search"></i> <span class="btn-text">Buscar</span>
                    </button>
                    <button type="submit" name="mostrar_todos" class="btn btn-reset">
                        <i class="fas fa-sync-alt"></i> <span class="btn-text">Todos</span>
                    </button>
                </div>
            </form>
            
            <!-- Tabla de materia prima -->
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tipo</th>
                            <th>Grosor (mm)</th>
                            <th>Cantidad</th>
                            <th>Proveedor</th>
                            <th>Ubicación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($materiasPrimasEncontradas)): ?>
                            <?php foreach ($materiasPrimasEncontradas as $mp): ?>
                                <tr class="material-<?php echo strtolower($mp['tipo_material']); ?>">
                                    <td><?php echo htmlspecialchars($mp['id_plancha']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($mp['tipo_material'])); ?></td>
                                    <td><?php echo number_format($mp['grosor'], 2); ?></td>
                                    <td class="<?php echo $mp['cantidad'] < 5 ? 'stock-bajo' : ($mp['cantidad'] < 15 ? 'stock-medio' : 'stock-alto'); ?>">
                                        <?php echo htmlspecialchars($mp['cantidad']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($mp['nombre_proveedor'] ?? 'Sin proveedor'); ?></td>
                                    <td><?php echo htmlspecialchars($mp['ubicacion']); ?></td>
                                    <td class="actions-cell">
                                        <a href="principal.php?vista=materia_prima&editar_materia_prima=<?php echo $mp['id_plancha']; ?>" class="btn btn-edit">
                                            <i class="fas fa-edit"></i> <span class="btn-text">Editar</span>
                                        </a>
                                        <a href="principal.php?vista=materia_prima&eliminar_materia_prima=<?php echo $mp['id_plancha']; ?>" class="btn btn-delete" onclick="return confirm('¿Estás seguro de eliminar este material?')">
                                            <i class="fas fa-trash-alt"></i> <span class="btn-text">Eliminar</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php while ($row = $resultado->fetch_assoc()): ?>
                                <tr class="material-<?php echo strtolower($row['tipo_material']); ?>">
                                    <td><?php echo htmlspecialchars($row['id_plancha']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($row['tipo_material'])); ?></td>
                                    <td><?php echo number_format($row['grosor'], 2); ?></td>
                                    <td class="<?php echo $row['cantidad'] < 5 ? 'stock-bajo' : ($row['cantidad'] < 15 ? 'stock-medio' : 'stock-alto'); ?>">
                                        <?php echo htmlspecialchars($row['cantidad']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['nombre_proveedor'] ?? 'Sin proveedor'); ?></td>
                                    <td><?php echo htmlspecialchars($row['ubicacion']); ?></td>
                                    <td class="actions-cell">
                                        <a href="principal.php?vista=materia_prima&editar_materia_prima=<?php echo $row['id_plancha']; ?>" class="btn btn-edit">
                                            <i class="fas fa-edit"></i> <span class="btn-text">Editar</span>
                                        </a>
                                        <a href="principal.php?vista=materia_prima&eliminar_materia_prima=<?php echo $row['id_plancha']; ?>" class="btn btn-delete" onclick="return confirm('¿Estás seguro de eliminar este material?')">
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
            <h2><i class="fas fa-cube"></i> <?php echo $materiaPrima ? "Editar Materia Prima" : "Agregar Materia Prima"; ?></h2>
            
            <!-- Formulario de agregar/editar materia prima -->
            <form method="POST" id="formMateriaPrima">
                <?php if ($materiaPrima): ?>
                    <input type="hidden" name="id_plancha" value="<?php echo htmlspecialchars($materiaPrima['id_plancha']); ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="tipo_material"><i class="fas fa-layer-group"></i> Tipo de Material *</label>
                    <select name="tipo_material" id="tipo_material" required
                            class="<?php echo isset($errores['tipo_material']) ? 'is-invalid' : ''; ?>">
                        <option value="">Seleccione un material</option>
                        <option value="inox" <?php echo (isset($formData['tipo_material']) && $formData['tipo_material'] == 'inox') ? 'selected' : ''; ?>>Acero Inoxidable</option>
                        <option value="aluminio" <?php echo (isset($formData['tipo_material']) && $formData['tipo_material'] == 'aluminio') ? 'selected' : ''; ?>>Aluminio</option>
                        <option value="acero" <?php echo (isset($formData['tipo_material']) && $formData['tipo_material'] == 'acero') ? 'selected' : ''; ?>>Acero al Carbón</option>
                    </select>
                    <i class="fas fa-tags"></i>
                    <?php if (isset($errores['tipo_material'])): ?>
                        <span class="invalid-feedback"><?php echo $errores['tipo_material']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="grosor"><i class="fas fa-ruler-vertical"></i> Grosor (mm) *</label>
                    <input type="number" name="grosor" id="grosor"
                           value="<?php echo htmlspecialchars($formData['grosor'] ?? '0'); ?>"
                           min="0"
                           step="0.5"
                           required
                           class="<?php echo isset($errores['grosor']) ? 'is-invalid' : ''; ?>">
                    <i class="fas fa-ruler-combined"></i>
                    <?php if (isset($errores['grosor'])): ?>
                        <span class="invalid-feedback"><?php echo $errores['grosor']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="cantidad"><i class="fas fa-layer-group"></i> Cantidad *</label>
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
                    <label for="id_proveedor"><i class="fas fa-truck"></i> Proveedor</label>
                    <select name="id_proveedor" id="id_proveedor">
                        <option value="">Seleccione un proveedor</option>
                        <?php while ($proveedor = $proveedores->fetch_assoc()): ?>
                            <option value="<?php echo $proveedor['id_proveedor']; ?>" 
                                <?php echo (isset($formData['id_proveedor']) && $formData['id_proveedor'] == $proveedor['id_proveedor']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($proveedor['razon_social']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <i class="fas fa-parachute-box"></i>
                </div>
                
                <div class="form-group">
                    <label for="ubicacion"><i class="fas fa-map-marker-alt"></i> Ubicación en Almacén *</label>
                    <input type="text" name="ubicacion" id="ubicacion"
                           value="<?php echo htmlspecialchars($formData['ubicacion'] ?? ''); ?>"
                           placeholder="Ej: Estantería B, Nivel 2"
                           required
                           class="<?php echo isset($errores['ubicacion']) ? 'is-invalid' : ''; ?>">
                    <i class="fas fa-warehouse"></i>
                    <?php if (isset($errores['ubicacion'])): ?>
                        <span class="invalid-feedback"><?php echo $errores['ubicacion']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="button-group">
                    <button type="submit" name="<?php echo $materiaPrima ? 'editar_materia_prima' : 'agregar_materia_prima'; ?>" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo $materiaPrima ? "Actualizar" : "Guardar"; ?>
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