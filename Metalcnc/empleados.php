<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require 'db_connection.php';

// Función para validar teléfono (la misma que en clientes)
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

// Función para validar nombres y apellidos (la misma que en clientes)
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

// Función para validar correo (la misma que en clientes)
function validarCorreo($correo) {
    if (empty($correo)) {
        return "El correo electrónico es obligatorio";
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        return "Formato de correo inválido. Debe ser como: ejemplo@dominio.com";
    }
    return true;
}

// Función para validar salario
function validarSalario($salario) {
    if (empty($salario)) {
        return "El salario es obligatorio";
    }
    if (!is_numeric($salario) || $salario <= 0) {
        return "El salario debe ser un número positivo";
    }
    return true;
}

// Procesar formulario
if (isset($_POST['agregar_empleado']) || isset($_POST['editar_empleado'])) {
    $nombres = trim(preg_replace('/\s+/', ' ', $_POST['nombres']));
    $apellidos = trim(preg_replace('/\s+/', ' ', $_POST['apellidos']));
    $telefono = trim($_POST['telefono']);
    $correo = trim($_POST['correo']);
    $cargo = trim($_POST['cargo']);
    $turno = trim($_POST['turno']);
    $salario = floatval($_POST['salario']);
    $estado = $_POST['estado'] ?? 'activo';
    $id_empleado = isset($_POST['id_empleado']) ? intval($_POST['id_empleado']) : null;

    $errores = [];
    
    $validacionNombres = validarNombreApellido($nombres, "nombre");
    if ($validacionNombres !== true) $errores['nombres'] = $validacionNombres;
    
    $validacionApellidos = validarNombreApellido($apellidos, "apellido");
    if ($validacionApellidos !== true) $errores['apellidos'] = $validacionApellidos;
    
    $validacionTelefono = validarTelefono($telefono);
    if ($validacionTelefono !== true) $errores['telefono'] = $validacionTelefono;
    
    $validacionCorreo = validarCorreo($correo);
    if ($validacionCorreo !== true) $errores['correo'] = $validacionCorreo;
    
    $validacionSalario = validarSalario($salario);
    if ($validacionSalario !== true) $errores['salario'] = $validacionSalario;
    
    if (empty($errores)) {
        try {
            if (isset($_POST['agregar_empleado'])) {
                $query = "INSERT INTO empleados (nombres, apellidos, telefono, correo, cargo, turno, salario, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $mysqli->prepare($query);
                $stmt->bind_param("ssssssds", $nombres, $apellidos, $telefono, $correo, $cargo, $turno, $salario, $estado);
            } else {
                $query = "UPDATE empleados SET nombres=?, apellidos=?, telefono=?, correo=?, cargo=?, turno=?, salario=?, estado=? WHERE id_empleado=?";
                $stmt = $mysqli->prepare($query);
                $stmt->bind_param("ssssssdsi", $nombres, $apellidos, $telefono, $correo, $cargo, $turno, $salario, $estado, $id_empleado);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Error al guardar los datos: " . $stmt->error);
            }
            
            $_SESSION['exito'] = isset($_POST['agregar_empleado']) ? "✅ Empleado agregado correctamente" : "✅ Empleado actualizado correctamente";
            header("Location: principal.php?vista=empleados");
            exit();
            
        } catch (Exception $e) {
            $_SESSION['error'] = "❌ Error: " . $e->getMessage();
            header("Location: principal.php?vista=empleados");
            exit();
        }
    } else {
        $_SESSION['errores_validacion'] = $errores;
        $_SESSION['datos_formulario'] = [
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'telefono' => $telefono,
            'correo' => $correo,
            'cargo' => $cargo,
            'turno' => $turno,
            'salario' => $salario,
            'estado' => $estado
        ];
        header("Location: principal.php?vista=empleados");
        exit();
    }
}

// Eliminar empleado
if (isset($_GET['eliminar'])) {
    $id_empleado = intval($_GET['eliminar']);
    $stmt = $mysqli->prepare("DELETE FROM empleados WHERE id_empleado = ?");
    $stmt->bind_param("i", $id_empleado);
    $stmt->execute();
    $stmt->close();
    $_SESSION['exito'] = "Empleado eliminado correctamente";
    header("Location: principal.php?vista=empleados");
    exit();
}

// Obtener empleado para editar
$empleado = null;
if (isset($_GET['editar'])) {
    $id_empleado = intval($_GET['editar']);
    $stmt = $mysqli->prepare("SELECT * FROM empleados WHERE id_empleado = ?");
    $stmt->bind_param("i", $id_empleado);
    $stmt->execute();
    $result = $stmt->get_result();
    $empleado = $result->fetch_assoc();
    $stmt->close();
}

// Obtener lista de empleados
$resultado = $mysqli->query("SELECT * FROM empleados ORDER BY id_empleado DESC");

// Búsqueda de empleados
$empleadosEncontrados = [];
if (isset($_POST['buscar_empleado'])) {
    $busqueda = "%" . trim($_POST['busqueda']) . "%";
    $stmt = $mysqli->prepare("SELECT * FROM empleados WHERE nombres LIKE ? OR apellidos LIKE ? OR telefono LIKE ? OR correo LIKE ? OR cargo LIKE ?");
    $stmt->bind_param("sssss", $busqueda, $busqueda, $busqueda, $busqueda, $busqueda);
    $stmt->execute();
    $resultadoBusqueda = $stmt->get_result();
    while ($row = $resultadoBusqueda->fetch_assoc()) {
        $empleadosEncontrados[] = $row;
    }
    $stmt->close();
}

// Recuperar datos del formulario si hay errores
$formData = isset($_SESSION['datos_formulario']) ? $_SESSION['datos_formulario'] : [];
$errores = isset($_SESSION['errores_validacion']) ? $_SESSION['errores_validacion'] : [];
unset($_SESSION['datos_formulario']);
unset($_SESSION['errores_validacion']);

// Si estamos editando, usar los datos del empleado
if ($empleado && empty($formData)) {
    $formData = [
        'nombres' => $empleado['nombres'],
        'apellidos' => $empleado['apellidos'],
        'telefono' => $empleado['telefono'],
        'correo' => $empleado['correo'],
        'cargo' => $empleado['cargo'],
        'turno' => $empleado['turno'],
        'salario' => $empleado['salario'],
        'estado' => $empleado['estado']
    ];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Empleados</title>
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
    padding: 20px;
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
    margin-bottom: 15px;
    position: relative;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: var(--dark);
    font-size: 13px;
}

.form-group i {
    position: absolute;
    left: 15px;
    top: 38px;
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
    to { transform: translateY(0); opacity: 1; }
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
            <h2><i class="fas fa-users"></i> Lista de Empleados</h2>
            
            <!-- Formulario de búsqueda -->
            <form method="POST" class="search-form">
                <div class="input-group">
                    <div class="form-group">
                        <input type="text" name="busqueda" placeholder="Buscar empleados..." 
                               value="<?php echo isset($_POST['busqueda']) ? htmlspecialchars($_POST['busqueda']) : ''; ?>">
                        <i class="fas fa-search"></i>
                    </div>
                    <button type="submit" name="buscar_empleado" class="btn btn-search">
                        <i class="fas fa-search"></i> <span class="btn-text">Buscar</span>
                    </button>
                    <button type="submit" name="mostrar_todos" class="btn btn-reset">
                        <i class="fas fa-sync-alt"></i> <span class="btn-text">Todos</span>
                    </button>
                </div>
            </form>
            
            <!-- Tabla de empleados -->
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Apellidos</th>
                            <th>Teléfono</th>
                            <th>Cargo</th>
                            <th>Turno</th>
                            <th>Salario</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($empleadosEncontrados)): ?>
                            <?php foreach ($empleadosEncontrados as $emp): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($emp['id_empleado']); ?></td>
                                    <td><?php echo htmlspecialchars($emp['nombres']); ?></td>
                                    <td><?php echo htmlspecialchars($emp['apellidos']); ?></td>
                                    <td><?php echo htmlspecialchars($emp['telefono']); ?></td>
                                    <td><?php echo htmlspecialchars($emp['cargo']); ?></td>
                                    <td><?php echo htmlspecialchars($emp['turno']); ?></td>
                                    <td><?php echo number_format($emp['salario'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($emp['estado']); ?></td>
                                    <td class="actions-cell">
                                        <a href="principal.php?vista=empleados&editar=<?php echo $emp['id_empleado']; ?>" class="btn btn-edit">
                                            <i class="fas fa-edit"></i> <span class="btn-text">Editar</span>
                                        </a>
                                        <a href="principal.php?vista=empleados&eliminar=<?php echo $emp['id_empleado']; ?>" class="btn btn-delete" onclick="return confirm('¿Estás seguro de eliminar este empleado?')">
                                            <i class="fas fa-trash-alt"></i> <span class="btn-text">Eliminar</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php while ($row = $resultado->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['id_empleado']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nombres']); ?></td>
                                    <td><?php echo htmlspecialchars($row['apellidos']); ?></td>
                                    <td><?php echo htmlspecialchars($row['telefono']); ?></td>
                                    <td><?php echo htmlspecialchars($row['cargo']); ?></td>
                                    <td><?php echo htmlspecialchars($row['turno']); ?></td>
                                    <td><?php echo number_format($row['salario'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($row['estado']); ?></td>
                                    <td class="actions-cell">
                                        <a href="principal.php?vista=empleados&editar=<?php echo $row['id_empleado']; ?>" class="btn btn-edit">
                                            <i class="fas fa-edit"></i> <span class="btn-text">Editar</span>
                                        </a>
                                        <a href="principal.php?vista=empleados&eliminar=<?php echo $row['id_empleado']; ?>" class="btn btn-delete" onclick="return confirm('¿Estás seguro de eliminar este empleado?')">
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
            <h2><i class="fas fa-user-plus"></i> <?php echo $empleado ? "Editar Empleado" : "Agregar Empleado"; ?></h2>
            
            <!-- Formulario de agregar/editar empleado -->
            <form method="POST" id="formEmpleado">
                <?php if ($empleado): ?>
                    <input type="hidden" name="id_empleado" value="<?php echo htmlspecialchars($empleado['id_empleado']); ?>">
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
                
                <div class="form-group">
                    <label for="cargo"><i class="fas fa-briefcase"></i> Cargo *</label>
                    <input type="text" name="cargo" id="cargo"
                           value="<?php echo htmlspecialchars($formData['cargo'] ?? ''); ?>"
                           placeholder="Ej: Gerente, Asistente, etc."
                           required>
                    <i class="fas fa-user-tie"></i>
                </div>
                
                <div class="form-group">
                    <label for="turno"><i class="fas fa-clock"></i> Turno *</label>
                    <select name="turno" id="turno" required>
                        <option value="Mañana" <?php echo ($formData['turno'] ?? '') === 'Mañana' ? 'selected' : ''; ?>>Mañana</option>
                        <option value="Tarde" <?php echo ($formData['turno'] ?? '') === 'Tarde' ? 'selected' : ''; ?>>Tarde</option>
                        <option value="Noche" <?php echo ($formData['turno'] ?? '') === 'Noche' ? 'selected' : ''; ?>>Noche</option>
                        <option value="Completo" <?php echo ($formData['turno'] ?? '') === 'Completo' ? 'selected' : ''; ?>>Completo</option>
                    </select>
                    <i class="fas fa-calendar-alt"></i>
                </div>
                
                <div class="form-group">
                    <label for="salario"><i class="fas fa-money-bill-wave"></i> Salario *</label>
                    <input type="number" step="0.01" name="salario" id="salario"
                           value="<?php echo htmlspecialchars($formData['salario'] ?? ''); ?>"
                           placeholder="Ej: 5000.00"
                           required
                           class="<?php echo isset($errores['salario']) ? 'is-invalid' : ''; ?>">
                    <i class="fas fa-dollar-sign"></i>
                    <?php if (isset($errores['salario'])): ?>
                        <span class="invalid-feedback"><?php echo $errores['salario']; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="estado"><i class="fas fa-check-circle"></i> Estado *</label>
                    <select name="estado" id="estado" required>
                        <option value="activo" <?php echo ($formData['estado'] ?? '') === 'activo' ? 'selected' : ''; ?>>Activo</option>
                        <option value="inactivo" <?php echo ($formData['estado'] ?? '') === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                    </select>
                    <i class="fas fa-info-circle"></i>
                </div>
                
                <div class="button-group">
                    <button type="submit" name="<?php echo $empleado ? 'editar_empleado' : 'agregar_empleado'; ?>" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo $empleado ? "Actualizar" : "Guardar"; ?>
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