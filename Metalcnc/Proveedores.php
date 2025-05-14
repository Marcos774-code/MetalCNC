<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require 'db_connection.php';

// Función para validar teléfono
function validarTelefono($telefono) {
    if (empty($telefono)) {
        return true; // Teléfono no es obligatorio
    }
    $telefono = str_replace(' ', '', $telefono);
    if (!preg_match('/^(\+?\d{1,3}?[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{2}[-.\s]?\d{2}$/', $telefono)) {
        return "Formato de teléfono inválido. Ejemplos válidos: +591 71234567, 71234567, (591)71234567";
    }
    return true;
}

// Función para validar nombres y apellidos
function validarNombreApellido($valor, $campo) {
    if (!empty($valor)) {
        if (strlen($valor) < 2) {
            return "El $campo debe tener al menos 2 caracteres";
        }
        if (preg_match('/[0-9!@#$%^&*()_+=\[\]{};\'":\\\\|,.<>\/?]+/', $valor)) {
            return "El $campo no puede contener números ni caracteres especiales";
        }
        if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\'\-]+$/', $valor)) {
            return "El $campo solo puede contener letras, espacios y apóstrofes válidos";
        }
    }
    return true;
}

// Función para validar razón social
function validarRazonSocial($razon_social) {
    if (empty($razon_social)) {
        return "La razón social es obligatoria";
    }
    if (strlen($razon_social) < 3) {
        return "La razón social debe tener al menos 3 caracteres";
    }
    return true;
}

// Procesar formulario
if (isset($_POST['agregar_proveedor']) || isset($_POST['editar_proveedor'])) {
    $razon_social = trim(preg_replace('/\s+/', ' ', $_POST['razon_social']));
    $nombres = trim(preg_replace('/\s+/', ' ', $_POST['nombres']));
    $apellidos = trim(preg_replace('/\s+/', ' ', $_POST['apellidos']));
    $descripcion = trim($_POST['descripcion']);
    $telefono = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);
    $id_proveedor = isset($_POST['id_proveedor']) ? intval($_POST['id_proveedor']) : null;

    $errores = [];
    
    $validacionRazonSocial = validarRazonSocial($razon_social);
    if ($validacionRazonSocial !== true) $errores['razon_social'] = $validacionRazonSocial;
    
    $validacionNombres = validarNombreApellido($nombres, "nombre");
    if ($validacionNombres !== true) $errores['nombres'] = $validacionNombres;
    
    $validacionApellidos = validarNombreApellido($apellidos, "apellido");
    if ($validacionApellidos !== true) $errores['apellidos'] = $validacionApellidos;
    
    $validacionTelefono = validarTelefono($telefono);
    if ($validacionTelefono !== true) $errores['telefono'] = $validacionTelefono;
    
    if (empty($errores)) {
        try {
            if (isset($_POST['agregar_proveedor'])) {
                $query = "INSERT INTO proveedores (razon_social, nombres, apellidos, descripcion, telefono, direccion) 
                          VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $mysqli->prepare($query);
                $stmt->bind_param("ssssss", $razon_social, $nombres, $apellidos, $descripcion, $telefono, $direccion);
            } else {
                $query = "UPDATE proveedores SET razon_social=?, nombres=?, apellidos=?, descripcion=?, telefono=?, direccion=? 
                          WHERE id_proveedor=?";
                $stmt = $mysqli->prepare($query);
                $stmt->bind_param("ssssssi", $razon_social, $nombres, $apellidos, $descripcion, $telefono, $direccion, $id_proveedor);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Error al guardar los datos: " . $stmt->error);
            }
            
            $_SESSION['exito'] = isset($_POST['agregar_proveedor']) ? "✅ Proveedor agregado correctamente" : "✅ Proveedor actualizado correctamente";
            header("Location: principal.php?vista=proveedores");
            exit();
            
        } catch (Exception $e) {
            $_SESSION['error'] = "❌ Error: " . $e->getMessage();
            header("Location: principal.php?vista=proveedores");
            exit();
        }
    } else {
        $_SESSION['errores_validacion'] = $errores;
        $_SESSION['datos_formulario'] = [
            'razon_social' => $razon_social,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'descripcion' => $descripcion,
            'telefono' => $telefono,
            'direccion' => $direccion
        ];
        header("Location: principal.php?vista=proveedores");
        exit();
    }
}

// Eliminar proveedor
if (isset($_GET['eliminar'])) {
    $id_proveedor = intval($_GET['eliminar']);
    $stmt = $mysqli->prepare("DELETE FROM proveedores WHERE id_proveedor = ?");
    $stmt->bind_param("i", $id_proveedor);
    $stmt->execute();
    $stmt->close();
    $_SESSION['exito'] = "✅ Proveedor eliminado correctamente";
    header("Location: principal.php?vista=proveedores");
    exit();
}

// Obtener proveedor para editar
$proveedor = null;
if (isset($_GET['editar'])) {
    $id_proveedor = intval($_GET['editar']);
    $stmt = $mysqli->prepare("SELECT * FROM proveedores WHERE id_proveedor = ?");
    $stmt->bind_param("i", $id_proveedor);
    $stmt->execute();
    $result = $stmt->get_result();
    $proveedor = $result->fetch_assoc();
    $stmt->close();
}

// Obtener lista de proveedores
$resultado = $mysqli->query("SELECT * FROM proveedores ORDER BY id_proveedor DESC");

// Búsqueda de proveedores
$proveedoresEncontrados = [];
if (isset($_POST['buscar_proveedor'])) {
    $busqueda = "%" . trim($_POST['busqueda']) . "%";
    $stmt = $mysqli->prepare("SELECT * FROM proveedores WHERE razon_social LIKE ? OR nombres LIKE ? OR apellidos LIKE ? OR telefono LIKE ?");
    $stmt->bind_param("ssss", $busqueda, $busqueda, $busqueda, $busqueda);
    $stmt->execute();
    $resultadoBusqueda = $stmt->get_result();
    while ($row = $resultadoBusqueda->fetch_assoc()) {
        $proveedoresEncontrados[] = $row;
    }
    $stmt->close();
}

// Recuperar datos del formulario si hay errores
$formData = isset($_SESSION['datos_formulario']) ? $_SESSION['datos_formulario'] : [];
$errores = isset($_SESSION['errores_validacion']) ? $_SESSION['errores_validacion'] : [];
unset($_SESSION['datos_formulario']);
unset($_SESSION['errores_validacion']);

// Si estamos editando, usar los datos del proveedor
if ($proveedor && empty($formData)) {
    $formData = [
        'razon_social' => $proveedor['razon_social'],
        'nombres' => $proveedor['nombres'],
        'apellidos' => $proveedor['apellidos'],
        'descripcion' => $proveedor['descripcion'],
        'telefono' => $proveedor['telefono'],
        'direccion' => $proveedor['direccion']
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Proveedores | Sistema</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
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
    border-radius: var(--border-radius);
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
    resize: vertical;
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
    gap: 8px;
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
}

.button-group {
    display: flex;
    gap: 10px;
    margin-top: 25px;
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
    gap: 8px;
}

/* Buscador - Estilos corregidos */
.search-form {
    margin-bottom: 25px;
}

.search-form .input-group {
    display: flex;
    gap: 10px;
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
    padding: 0 20px;
}

/* Mensajes flotantes */
#floating-messages {
    position: fixed;
    top: 25px;
    right: 25px;
    z-index: 1000;
    max-width: 400px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.message {
    padding: 12px 16px;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    display: flex;
    align-items: center;
    gap: 12px;
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
    height: 4px;
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
    margin-top: 5px;
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
            <h2><i class="fas fa-truck"></i> Lista de Proveedores</h2>
            
            <!-- Formulario de búsqueda -->
            <form method="POST" class="search-form">
                <div class="input-group">
                    <div class="form-group">
                        <input type="text" name="busqueda" placeholder="Buscar proveedores..." 
                               value="<?php echo isset($_POST['busqueda']) ? htmlspecialchars($_POST['busqueda']) : ''; ?>">
                        <i class="fas fa-search"></i>
                    </div>
                    <button type="submit" name="buscar_proveedor" class="btn btn-search">
                        <i class="fas fa-search"></i> <span class="btn-text">Buscar</span>
                    </button>
                    <button type="submit" name="mostrar_todos" class="btn btn-reset">
                        <i class="fas fa-sync-alt"></i> <span class="btn-text">Todos</span>
                    </button>
                </div>
            </form>
            
            <!-- Tabla de proveedores -->
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Razón Social</th>
                            <th>Nombres</th>
                            <th>Apellidos</th>
                            <th>Teléfono</th>
                            <th>Dirección</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($proveedoresEncontrados)): ?>
                            <?php foreach ($proveedoresEncontrados as $prov): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($prov['id_proveedor']); ?></td>
                                    <td><?php echo htmlspecialchars($prov['razon_social']); ?></td>
                                    <td><?php echo htmlspecialchars($prov['nombres']); ?></td>
                                    <td><?php echo htmlspecialchars($prov['apellidos']); ?></td>
                                    <td><?php echo htmlspecialchars($prov['telefono']); ?></td>
                                    <td><?php echo htmlspecialchars($prov['direccion']); ?></td>
                                    <td class="actions-cell">
                                        <a href="principal.php?vista=proveedores&editar=<?php echo $prov['id_proveedor']; ?>" class="btn btn-edit">
                                            <i class="fas fa-edit"></i> <span class="btn-text">Editar</span>
                                        </a>
                                        <a href="principal.php?vista=proveedores&eliminar=<?php echo $prov['id_proveedor']; ?>" class="btn btn-delete" onclick="return confirm('¿Estás seguro de eliminar este proveedor?')">
                                            <i class="fas fa-trash-alt"></i> <span class="btn-text">Eliminar</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php while ($row = $resultado->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['id_proveedor']); ?></td>
                                    <td><?php echo htmlspecialchars($row['razon_social']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nombres']); ?></td>
                                    <td><?php echo htmlspecialchars($row['apellidos']); ?></td>
                                    <td><?php echo htmlspecialchars($row['telefono']); ?></td>
                                    <td><?php echo htmlspecialchars($row['direccion']); ?></td>
                                    <td class="actions-cell">
                                        <a href="principal.php?vista=proveedores&editar=<?php echo $row['id_proveedor']; ?>" class="btn btn-edit">
                                            <i class="fas fa-edit"></i> <span class="btn-text">Editar</span>
                                        </a>
                                        <a href="principal.php?vista=proveedores&eliminar=<?php echo $row['id_proveedor']; ?>" class="btn btn-delete" onclick="return confirm('¿Estás seguro de eliminar este proveedor?')">
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
            <h2><i class="fas fa-plus-circle"></i> <?php echo $proveedor ? "Editar Proveedor" : "Agregar Proveedor"; ?></h2>
            
            <!-- Formulario de agregar/editar proveedor -->
            <form method="POST" id="formProveedor">
                <?php if ($proveedor): ?>
                    <input type="hidden" name="id_proveedor" value="<?php echo htmlspecialchars($proveedor['id_proveedor']); ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="razon_social"><i class="fas fa-building"></i> Razón Social *</label>
                    <input type="text" name="razon_social" id="razon_social"
                           value="<?php echo htmlspecialchars($formData['razon_social'] ?? ''); ?>"
                           placeholder="Ej: Distribuidora ABC S.A."
                           required
                           class="<?php echo isset($errores['razon_social']) ? 'is-invalid' : ''; ?>">
                    <i class="fas fa-building"></i>
                    <?php if (isset($errores['razon_social'])): ?>
                        <span class="invalid-feedback"><?php echo $errores['razon_social']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="nombres"><i class="fas fa-user"></i> Nombres</label>
                    <input type="text" name="nombres" id="nombres"
                           value="<?php echo htmlspecialchars($formData['nombres'] ?? ''); ?>"
                           placeholder="Nombres del contacto"
                           class="<?php echo isset($errores['nombres']) ? 'is-invalid' : ''; ?>">
                    <i class="fas fa-user"></i>
                    <?php if (isset($errores['nombres'])): ?>
                        <span class="invalid-feedback"><?php echo $errores['nombres']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="apellidos"><i class="fas fa-user-tag"></i> Apellidos</label>
                    <input type="text" name="apellidos" id="apellidos"
                           value="<?php echo htmlspecialchars($formData['apellidos'] ?? ''); ?>"
                           placeholder="Apellidos del contacto"
                           class="<?php echo isset($errores['apellidos']) ? 'is-invalid' : ''; ?>">
                    <i class="fas fa-user-tag"></i>
                    <?php if (isset($errores['apellidos'])): ?>
                        <span class="invalid-feedback"><?php echo $errores['apellidos']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="descripcion"><i class="fas fa-info-circle"></i> Descripción</label>
                    <textarea name="descripcion" id="descripcion"
                              placeholder="Descripción del proveedor"><?php echo htmlspecialchars($formData['descripcion'] ?? ''); ?></textarea>
                    <i class="fas fa-info-circle"></i>
                </div>
                
                <div class="form-group">
                    <label for="telefono"><i class="fas fa-phone"></i> Teléfono</label>
                    <input type="tel" name="telefono" id="telefono"
                           value="<?php echo htmlspecialchars($formData['telefono'] ?? ''); ?>"
                           placeholder="Ej: +59171234567"
                           class="<?php echo isset($errores['telefono']) ? 'is-invalid' : ''; ?>">
                    <i class="fas fa-mobile-alt"></i>
                    <?php if (isset($errores['telefono'])): ?>
                        <span class="invalid-feedback"><?php echo $errores['telefono']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="direccion"><i class="fas fa-map-marker-alt"></i> Dirección</label>
                    <input type="text" name="direccion" id="direccion"
                           value="<?php echo htmlspecialchars($formData['direccion'] ?? ''); ?>"
                           placeholder="Dirección completa">
                    <i class="fas fa-home"></i>
                </div>
                
                <div class="button-group">
                    <button type="submit" name="<?php echo $proveedor ? 'editar_proveedor' : 'agregar_proveedor'; ?>" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo $proveedor ? "Actualizar" : "Guardar"; ?>
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
        // Validación para nombres y apellidos
        const nombreInputs = document.querySelectorAll('#nombres, #apellidos');
        nombreInputs.forEach(input => {
            input.addEventListener('input', function() {
                this.value = this.value.replace(/[0-9!@#$%^&*()_+=\[\]{};':"\\|,.<>\/?]/g, '');
            });
        });

        // Validación para teléfono
        const telefonoInput = document.getElementById('telefono');
        if (telefonoInput) {
            telefonoInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9+()\- ]/g, '');
            });
        }

        // Auto-eliminar mensajes después de 5 segundos
        const messages = document.querySelectorAll('#floating-messages .message');
        messages.forEach(msg => {
            setTimeout(() => {
                msg.style.animation = 'fadeOut 0.5s forwards';
                setTimeout(() => msg.remove(), 500);
            }, 5000);
        });
    });
    </script>
</body>
</html>
