<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require 'db_connection.php';

// Función para validar teléfono
function validarTelefono($telefono) {
    if (empty($telefono)) {
        return "El teléfono es obligatorio";
    }
    $telefono = str_replace(' ', '', $telefono);
    if (!preg_match('/^(\+?\d{1,3}?[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{2}[-.\s]?\d{2}$/', $telefono)) {
        return "Formato de teléfono inválido. Ejemplos válidos: +591 71234567, 71234567, (591)71234567";
    }
    return true;
}

// Función para validar nombres y apellidos
function validarNombreApellido($valor, $campo) {
    if (empty($valor)) {
        return "El $campo es obligatorio";
    }
    if (strlen($valor) < 2) {
        return "El $campo debe tener al menos 2 caracteres";
    }
    if (preg_match('/[0-9!@#$%^&*()_+=\[\]{};\'":\\\\|,.<>\/?]+/', $valor)) {
        return "El $campo no puede contener números ni caracteres especiales";
    }
    if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\'\-]+$/', $valor)) {
        return "El $campo solo puede contener letras, espacios y apóstrofes válidos";
    }
    return true;
}

// Función para validar correo
function validarCorreo($correo) {
    if (empty($correo)) {
        return "El correo electrónico es obligatorio";
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        return "Formato de correo inválido. Debe ser como: ejemplo@dominio.com";
    }
    return true;
}

// Función para validar CI/NIT
function validarNitCi($nit_ci, $extension) {
    if (empty($nit_ci)) {
        return "El NIT/CI es obligatorio";
    }
    $nit_ci = str_replace([' ', '-'], '', $nit_ci);
    if (!preg_match('/^[0-9]+$/', $nit_ci)) {
        return "El NIT/CI solo puede contener números (sin letras ni caracteres especiales)";
    }
    if (strlen($nit_ci) < 4 || strlen($nit_ci) > 15) {
        return "El NIT/CI debe tener entre 4 y 15 dígitos";
    }
    if (!empty($extension) && !preg_match('/^[A-Za-z]{2,4}$/', $extension)) {
        return "La extensión debe ser de 2 a 4 letras (ej: LP, CBBA)";
    }
    return true;
}

// Verificar si CI/NIT ya existe
function nitCiExiste($nit_ci, $extension, $id_excluir = null) {
    global $mysqli;
    $query = "SELECT id_cliente FROM clientes WHERE nit_ci = ?";
    $params = [$nit_ci];
    $types = "s";
    
    if (!empty($extension)) {
        $query .= " AND extension = ?";
        $params[] = $extension;
        $types .= "s";
    }
    
    if ($id_excluir) {
        $query .= " AND id_cliente != ?";
        $params[] = $id_excluir;
        $types .= "i";
    }
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->store_result();
    return $stmt->num_rows > 0;
}

// Procesar formulario
if (isset($_POST['agregar_cliente']) || isset($_POST['editar_cliente'])) {
    $nombres = trim(preg_replace('/\s+/', ' ', $_POST['nombres']));
    $apellidos = trim(preg_replace('/\s+/', ' ', $_POST['apellidos']));
    $direccion = trim($_POST['direccion']);
    $telefono = trim($_POST['telefono']);
    $correo = trim($_POST['correo']);
    $nit_ci = trim(str_replace([' ', '-'], '', $_POST['nit_ci']));
    $extension = trim($_POST['extension']);
    $id_cliente = isset($_POST['id_cliente']) ? intval($_POST['id_cliente']) : null;

    $errores = [];
    
    $validacionNombres = validarNombreApellido($nombres, "nombre");
    if ($validacionNombres !== true) $errores['nombres'] = $validacionNombres;
    
    $validacionApellidos = validarNombreApellido($apellidos, "apellido");
    if ($validacionApellidos !== true) $errores['apellidos'] = $validacionApellidos;
    
    $validacionTelefono = validarTelefono($telefono);
    if ($validacionTelefono !== true) $errores['telefono'] = $validacionTelefono;
    
    $validacionCorreo = validarCorreo($correo);
    if ($validacionCorreo !== true) $errores['correo'] = $validacionCorreo;
    
    $validacionNitCi = validarNitCi($nit_ci, $extension);
    if ($validacionNitCi !== true) {
        $errores['nit_ci'] = $validacionNitCi;
    } elseif (nitCiExiste($nit_ci, $extension, $id_cliente)) {
        $errores['nit_ci'] = "Este NIT/CI ya está registrado para otro cliente";
    }
    
    if (empty($errores)) {
        try {
            if (isset($_POST['agregar_cliente'])) {
                $query = "INSERT INTO clientes (nombres, apellidos, direccion, telefono, correo, nit_ci, extension) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $mysqli->prepare($query);
                $stmt->bind_param("sssssss", $nombres, $apellidos, $direccion, $telefono, $correo, $nit_ci, $extension);
            } else {
                $query = "UPDATE clientes SET nombres=?, apellidos=?, direccion=?, telefono=?, correo=?, nit_ci=?, extension=? WHERE id_cliente=?";
                $stmt = $mysqli->prepare($query);
                $stmt->bind_param("sssssssi", $nombres, $apellidos, $direccion, $telefono, $correo, $nit_ci, $extension, $id_cliente);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Error al guardar los datos: " . $stmt->error);
            }
            
            $_SESSION['exito'] = isset($_POST['agregar_cliente']) ? "✅ Cliente agregado correctamente" : "✅ Cliente actualizado correctamente";
            header("Location: principal.php?vista=agregarCliente");
            exit();
            
        } catch (Exception $e) {
            $_SESSION['error'] = "❌ Error: " . $e->getMessage();
            header("Location: principal.php?vista=agregarCliente");
            exit();
        }
    } else {
        $_SESSION['errores_validacion'] = $errores;
        $_SESSION['datos_formulario'] = [
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'direccion' => $direccion,
            'telefono' => $telefono,
            'correo' => $correo,
            'nit_ci' => $nit_ci,
            'extension' => $extension
        ];
        header("Location: principal.php?vista=agregarCliente");
        exit();
    }
}

// Eliminar cliente
if (isset($_GET['eliminar'])) {
    $id_cliente = intval($_GET['eliminar']);
    $stmt = $mysqli->prepare("DELETE FROM clientes WHERE id_cliente = ?");
    $stmt->bind_param("i", $id_cliente);
    $stmt->execute();
    $stmt->close();
    $_SESSION['exito'] = "Cliente eliminado correctamente";
    header("Location: principal.php?vista=agregarCliente");
    exit();
}

// Obtener cliente para editar
$cliente = null;
if (isset($_GET['editar'])) {
    $id_cliente = intval($_GET['editar']);
    $stmt = $mysqli->prepare("SELECT * FROM clientes WHERE id_cliente = ?");
    $stmt->bind_param("i", $id_cliente);
    $stmt->execute();
    $result = $stmt->get_result();
    $cliente = $result->fetch_assoc();
    $stmt->close();
}

// Obtener lista de clientes
$resultado = $mysqli->query("SELECT * FROM clientes ORDER BY id_cliente DESC");

// Búsqueda de clientes
$clientesEncontrados = [];
if (isset($_POST['buscar_cliente'])) {
    $busqueda = "%" . trim($_POST['busqueda']) . "%";
    $stmt = $mysqli->prepare("SELECT * FROM clientes WHERE nombres LIKE ? OR apellidos LIKE ? OR telefono LIKE ? OR correo LIKE ? OR nit_ci LIKE ?");
    $stmt->bind_param("sssss", $busqueda, $busqueda, $busqueda, $busqueda, $busqueda);
    $stmt->execute();
    $resultadoBusqueda = $stmt->get_result();
    while ($row = $resultadoBusqueda->fetch_assoc()) {
        $clientesEncontrados[] = $row;
    }
    $stmt->close();
}

// Recuperar datos del formulario si hay errores
$formData = isset($_SESSION['datos_formulario']) ? $_SESSION['datos_formulario'] : [];
$errores = isset($_SESSION['errores_validacion']) ? $_SESSION['errores_validacion'] : [];
unset($_SESSION['datos_formulario']);
unset($_SESSION['errores_validacion']);

// Si estamos editando, usar los datos del cliente
if ($cliente && empty($formData)) {
    $formData = [
        'nombres' => $cliente['nombres'],
        'apellidos' => $cliente['apellidos'],
        'direccion' => $cliente['direccion'],
        'telefono' => $cliente['telefono'],
        'correo' => $cliente['correo'],
        'nit_ci' => $cliente['nit_ci'],
        'extension' => $cliente['extension'] ?? ''
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes | Sistema</title>
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
            <h2><i class="fas fa-users"></i> Lista de Clientes</h2>
            
            <!-- Formulario de búsqueda -->
            <form method="POST" class="search-form">
                <div class="input-group">
                    <div class="form-group">
                        <input type="text" name="busqueda" placeholder="Buscar clientes..." 
                               value="<?php echo isset($_POST['busqueda']) ? htmlspecialchars($_POST['busqueda']) : ''; ?>">
                        <i class="fas fa-search"></i>
                    </div>
                    <button type="submit" name="buscar_cliente" class="btn btn-search">
                        <i class="fas fa-search"></i> <span class="btn-text">Buscar</span>
                    </button>
                    <button type="submit" name="mostrar_todos" class="btn btn-reset">
                        <i class="fas fa-sync-alt"></i> <span class="btn-text">Todos</span>
                    </button>
                </div>
            </form>
            
            <!-- Tabla de clientes -->
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Apellidos</th>
                            <th>Teléfono</th>
                            <th>NIT/CI</th>
                            <th>Ext.</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($clientesEncontrados)): ?>
                            <?php foreach ($clientesEncontrados as $cli): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cli['id_cliente']); ?></td>
                                    <td><?php echo htmlspecialchars($cli['nombres']); ?></td>
                                    <td><?php echo htmlspecialchars($cli['apellidos']); ?></td>
                                    <td><?php echo htmlspecialchars($cli['telefono']); ?></td>
                                    <td><?php echo htmlspecialchars($cli['nit_ci']); ?></td>
                                    <td><?php echo htmlspecialchars($cli['extension'] ?? ''); ?></td>
                                    <td class="actions-cell">
                                        <a href="principal.php?vista=agregarCliente&editar=<?php echo $cli['id_cliente']; ?>" class="btn btn-edit">
                                            <i class="fas fa-edit"></i> <span class="btn-text">Editar</span>
                                        </a>
                                        <a href="principal.php?vista=agregarCliente&eliminar=<?php echo $cli['id_cliente']; ?>" class="btn btn-delete" onclick="return confirm('¿Estás seguro de eliminar este cliente?')">
                                            <i class="fas fa-trash-alt"></i> <span class="btn-text">Eliminar</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php while ($row = $resultado->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['id_cliente']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nombres']); ?></td>
                                    <td><?php echo htmlspecialchars($row['apellidos']); ?></td>
                                    <td><?php echo htmlspecialchars($row['telefono']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nit_ci']); ?></td>
                                    <td><?php echo htmlspecialchars($row['extension'] ?? ''); ?></td>
                                    <td class="actions-cell">
                                        <a href="principal.php?vista=agregarCliente&editar=<?php echo $row['id_cliente']; ?>" class="btn btn-edit">
                                            <i class="fas fa-edit"></i> <span class="btn-text">Editar</span>
                                        </a>
                                        <a href="principal.php?vista=agregarCliente&eliminar=<?php echo $row['id_cliente']; ?>" class="btn btn-delete" onclick="return confirm('¿Estás seguro de eliminar este cliente?')">
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
            <h2><i class="fas fa-user-plus"></i> <?php echo $cliente ? "Editar Cliente" : "Agregar Cliente"; ?></h2>
            
            <!-- Formulario de agregar/editar cliente -->
            <form method="POST" id="formCliente">
                <?php if ($cliente): ?>
                    <input type="hidden" name="id_cliente" value="<?php echo htmlspecialchars($cliente['id_cliente']); ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="nombres"><i class="fas fa-user"></i> Nombres *</label>
                    <input type="text" name="nombres" id="nombres"
                           value="<?php echo htmlspecialchars($formData['nombres'] ?? ''); ?>"
                           placeholder="Ej: Juan Carlos"
                           required
                           class="<?php echo isset($errores['nombres']) ? 'is-invalid' : ''; ?>">
                    <i class="fas fa-user"></i>
                    <?php if (isset($errores['nombres'])): ?>
                        <span class="invalid-feedback"><?php echo $errores['nombres']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="apellidos"><i class="fas fa-user-tag"></i> Apellidos *</label>
                    <input type="text" name="apellidos" id="apellidos"
                           value="<?php echo htmlspecialchars($formData['apellidos'] ?? ''); ?>"
                           placeholder="Ej: Pérez López"
                           required
                           class="<?php echo isset($errores['apellidos']) ? 'is-invalid' : ''; ?>">
                    <i class="fas fa-user-tag"></i>
                    <?php if (isset($errores['apellidos'])): ?>
                        <span class="invalid-feedback"><?php echo $errores['apellidos']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="direccion"><i class="fas fa-map-marked-alt"></i> Dirección</label>
                    <input type="text" name="direccion" id="direccion"
                           value="<?php echo htmlspecialchars($formData['direccion'] ?? ''); ?>"
                           placeholder="Ej: Av. Siempre Viva 123"
                           maxlength="255">
                    <i class="fas fa-home"></i>
                </div>
                
                <div class="form-group">
                    <label for="telefono"><i class="fas fa-phone"></i> Teléfono *</label>
                    <input type="tel" name="telefono" id="telefono"
                           value="<?php echo htmlspecialchars($formData['telefono'] ?? ''); ?>"
                           placeholder="Ej: +59171234567"
                           required
                           class="<?php echo isset($errores['telefono']) ? 'is-invalid' : ''; ?>">
                    <i class="fas fa-mobile-alt"></i>
                    <?php if (isset($errores['telefono'])): ?>
                        <span class="invalid-feedback"><?php echo $errores['telefono']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="correo"><i class="fas fa-envelope"></i> Correo electrónico *</label>
                    <input type="email" name="correo" id="correo"
                           value="<?php echo htmlspecialchars($formData['correo'] ?? ''); ?>"
                           placeholder="Ej: ejemplo@dominio.com"
                           required
                           class="<?php echo isset($errores['correo']) ? 'is-invalid' : ''; ?>">
                    <i class="fas fa-at"></i>
                    <?php if (isset($errores['correo'])): ?>
                        <span class="invalid-feedback"><?php echo $errores['correo']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="input-group">
                    <div class="form-group" style="flex: 1;">
                        <label for="nit_ci"><i class="fas fa-id-card"></i> NIT/CI *</label>
                        <input type="text" name="nit_ci" id="nit_ci"
                               value="<?php echo htmlspecialchars($formData['nit_ci'] ?? ''); ?>"
                               placeholder="Ej: 12345678"
                               required
                               class="<?php echo isset($errores['nit_ci']) ? 'is-invalid' : ''; ?>"
                               maxlength="15">
                        <i class="fas fa-address-card"></i>
                        <?php if (isset($errores['nit_ci'])): ?>
                            <span class="invalid-feedback"><?php echo $errores['nit_ci']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group" style="width: 100px;">
                        <label for="extension"><i class="fas fa-map-marker-alt"></i> Ext.</label>
                        <select name="extension" id="extension">
                            <option value="">Ext.</option>
                            <?php
                            $departamentos = [
                                'LP' => 'LP', 
                                'CBBA' => 'CBBA',
                                'SCZ' => 'SCZ',
                                'OR' => 'OR',
                                'PT' => 'PT',
                                'TJ' => 'TJ',
                                'CH' => 'CH',
                                'BN' => 'BN',
                                'PA' => 'PA'
                            ];
                            
                            $currentExt = $formData['extension'] ?? '';
                            foreach ($departamentos as $value => $label) {
                                $selected = ($currentExt == $value) ? 'selected' : '';
                                echo "<option value=\"$value\" $selected>$label</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" name="<?php echo $cliente ? 'editar_cliente' : 'agregar_cliente'; ?>" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo $cliente ? "Actualizar" : "Guardar"; ?>
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