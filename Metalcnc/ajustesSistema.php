<?php
ob_start();

// Verificar si la sesión no está iniciada antes de iniciarla
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar permisos
if (!isset($_SESSION['nombre_usuario']) || $_SESSION['rol'] != 'gerente') {
    header('Location: principal.php');
    exit();
}
require 'db_connection.php';

$error = '';
$exito = '';

// Procesar cambio de estado
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cambiar_estado'])) {
    $id_usuario = $_POST['id_usuario'];
    $nuevo_estado = $_POST['nuevo_estado'] == '1' ? 1 : 0;
    
    $stmt = $mysqli->prepare("UPDATE usuarios SET estado = ? WHERE id_usuario = ?");
    $stmt->bind_param("ii", $nuevo_estado, $id_usuario);
    
    if ($stmt->execute()) {
        $exito = 'Estado del usuario actualizado correctamente';
    } else {
        $error = 'Error al actualizar el estado: ' . $stmt->error;
    }
    $stmt->close();
}

// Procesar edición de usuario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar_usuario'])) {
    $id_usuario = $_POST['id_usuario'];
    $nombre_usuario = trim($_POST['nombre_usuario']);
    $rol = $_POST['rol'];
    $contrasena_actual = $_POST['contrasena_actual'] ?? '';
    $nueva_contrasena = $_POST['nueva_contrasena'] ?? '';
    $contrasena_maestra = 'metalcnc';

    if (empty($nombre_usuario) || empty($rol)) {
        $error = 'Nombre de usuario y rol son obligatorios';
    } else {
        // Verificar si el nombre de usuario ya existe
        $stmt = $mysqli->prepare("SELECT id_usuario, contrasena, foto_perfil FROM usuarios WHERE nombre_usuario = ? AND id_usuario != ?");
        $stmt->bind_param("si", $nombre_usuario, $id_usuario);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error = 'El nombre de usuario ya existe';
        } else {
            // Procesar foto de perfil
            $foto_perfil = null;
            if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] == 0) {
                // Crear directorio si no existe
                if (!file_exists('uploads/perfiles')) {
                    mkdir('uploads/perfiles', 0777, true);
                }

                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                if (in_array($_FILES['foto_perfil']['type'], $allowed_types)) {
                    $extension = pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION);
                    $nombre_archivo = uniqid() . '.' . $extension;
                    $ruta_destino = 'uploads/perfiles/' . $nombre_archivo;
                    
                    if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $ruta_destino)) {
                        $foto_perfil = $nombre_archivo;
                        
                        // Eliminar foto anterior si existe
                        $stmt_foto = $mysqli->prepare("SELECT foto_perfil FROM usuarios WHERE id_usuario = ?");
                        $stmt_foto->bind_param("i", $id_usuario);
                        $stmt_foto->execute();
                        $result_foto = $stmt_foto->get_result();
                        $foto_antigua = $result_foto->fetch_assoc()['foto_perfil'];
                        
                        if ($foto_antigua && file_exists('uploads/perfiles/' . $foto_antigua)) {
                            unlink('uploads/perfiles/' . $foto_antigua);
                        }
                    } else {
                        $error = 'Error al subir la imagen';
                    }
                } else {
                    $error = 'Formato de imagen no válido. Use JPG, PNG o GIF';
                }
            }

            // Procesar cambio de contraseña
            $update_password = false;
            if (!empty($nueva_contrasena)) {
                // Obtener contraseña actual
                $stmt_pass = $mysqli->prepare("SELECT contrasena FROM usuarios WHERE id_usuario = ?");
                $stmt_pass->bind_param("i", $id_usuario);
                $stmt_pass->execute();
                $result_pass = $stmt_pass->get_result();
                $user_pass = $result_pass->fetch_assoc();
                
                if ($contrasena_actual == $contrasena_maestra || $contrasena_actual == $user_pass['contrasena']) {
                    $update_password = true;
                } else {
                    $error = 'Contraseña actual incorrecta';
                }
            }

            if (empty($error)) {
                // Construir consulta SQL según los campos a actualizar
                if ($update_password && $foto_perfil) {
                    $stmt = $mysqli->prepare("UPDATE usuarios SET nombre_usuario = ?, rol = ?, contrasena = ?, foto_perfil = ? WHERE id_usuario = ?");
                    $stmt->bind_param("ssssi", $nombre_usuario, $rol, $nueva_contrasena, $foto_perfil, $id_usuario);
                } elseif ($update_password) {
                    $stmt = $mysqli->prepare("UPDATE usuarios SET nombre_usuario = ?, rol = ?, contrasena = ? WHERE id_usuario = ?");
                    $stmt->bind_param("sssi", $nombre_usuario, $rol, $nueva_contrasena, $id_usuario);
                } elseif ($foto_perfil) {
                    $stmt = $mysqli->prepare("UPDATE usuarios SET nombre_usuario = ?, rol = ?, foto_perfil = ? WHERE id_usuario = ?");
                    $stmt->bind_param("sssi", $nombre_usuario, $rol, $foto_perfil, $id_usuario);
                } else {
                    $stmt = $mysqli->prepare("UPDATE usuarios SET nombre_usuario = ?, rol = ? WHERE id_usuario = ?");
                    $stmt->bind_param("ssi", $nombre_usuario, $rol, $id_usuario);
                }
                
                if ($stmt->execute()) {
                    $exito = 'Usuario actualizado correctamente';
                    
                    // Actualizar sesión si es el usuario actual
                    if ($_SESSION['nombre_usuario'] == $nombre_usuario) {
                        $_SESSION['nombre_usuario'] = $nombre_usuario;
                        $_SESSION['rol'] = $rol;
                        if ($foto_perfil) {
                            $_SESSION['foto_perfil'] = $foto_perfil;
                        }
                    }
                } else {
                    $error = 'Error al actualizar el usuario: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// Obtener todos los usuarios
$usuarios = [];
$result = $mysqli->query("SELECT * FROM usuarios ORDER BY id_usuario DESC");
if ($result) {
    $usuarios = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .user-management {
            max-width: 1200px;
            margin: 30px auto;
            padding: 25px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background-color: #ffebee;
            color: #c62828;
            border-left: 4px solid #c62828;
        }
        
        .alert-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .user-table th, .user-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .user-table th {
            background-color: #2c3e50;
            color: white;
        }
        
        .user-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-sm {
            padding: 6px 10px;
            font-size: 12px;
        }
        
        .btn-primary {
            background-color: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            width: 80%;
            max-width: 500px;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: black;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .text-muted {
            color: #6c757d;
            font-size: 0.8em;
        }
        
        .photo-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin: 10px auto;
            display: block;
            border: 3px solid #3498db;
        }
    </style>
</head>
<body>
    <div class="user-management">
        <h2><i class="fas fa-users-cog"></i> Gestión de Usuarios</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($exito): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $exito; ?>
            </div>
        <?php endif; ?>
        
        <div class="table-container">
            <table class="user-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Fecha Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($usuario['id_usuario']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($usuario['nombre_usuario']); ?>
                            <?php if ($usuario['foto_perfil']): ?>
                                <img src="uploads/perfiles/<?php echo htmlspecialchars($usuario['foto_perfil']); ?>" 
                                     alt="Foto perfil" style="width:30px;height:30px;border-radius:50%;margin-left:10px;">
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($usuario['rol']); ?></td>
                        <td>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="id_usuario" value="<?php echo $usuario['id_usuario']; ?>">
                                <input type="hidden" name="nuevo_estado" value="<?php echo $usuario['estado'] ? '0' : '1'; ?>">
                                <button type="submit" name="cambiar_estado" class="btn btn-sm <?php echo $usuario['estado'] ? 'btn-success' : 'btn-secondary'; ?>">
                                    <?php echo $usuario['estado'] ? 'Activo' : 'Inactivo'; ?>
                                </button>
                            </form>
                        </td>
                        <td><?php echo htmlspecialchars($usuario['created_at']); ?></td>
                        <td>
                            <button class="btn btn-primary btn-sm btn-editar" 
                                    data-id="<?php echo $usuario['id_usuario']; ?>"
                                    data-nombre="<?php echo htmlspecialchars($usuario['nombre_usuario']); ?>"
                                    data-rol="<?php echo htmlspecialchars($usuario['rol']); ?>"
                                    data-foto="<?php echo htmlspecialchars($usuario['foto_perfil'] ?? ''); ?>">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal para editar usuario -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3><i class="fas fa-user-edit"></i> Editar Usuario</h3>
            <form id="editUserForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id_usuario" id="editIdUsuario">
                
                <div class="form-group">
                    <label for="editNombreUsuario">Nombre de Usuario</label>
                    <input type="text" id="editNombreUsuario" name="nombre_usuario" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="editRol">Rol</label>
                    <select id="editRol" name="rol" class="form-control" required>
                        <option value="gerente">Gerente</option>
                        <option value="ventas">Ventas</option>
                        <option value="almacen">Almacén</option>
                        <option value="contabilidad">Contabilidad</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="foto_perfil">Foto de Perfil</label>
                    <img id="photoPreview" src="" class="photo-preview" style="display:none;">
                    <input type="file" id="foto_perfil" name="foto_perfil" class="form-control" accept="image/*">
                    <small class="text-muted">Dejar en blanco para mantener la actual</small>
                </div>
                
                <div class="form-group">
                    <label for="contrasena_actual">Contraseña Actual</label>
                    <input type="password" id="contrasena_actual" name="contrasena_actual" class="form-control">
                    <small class="text-muted">Requerida para cambiar contraseña (o usar contraseña maestra)</small>
                </div>
                
                <div class="form-group">
                    <label for="nueva_contrasena">Nueva Contraseña</label>
                    <input type="password" id="nueva_contrasena" name="nueva_contrasena" class="form-control">
                    <small class="text-muted">Dejar en blanco para mantener la actual</small>
                </div>
                
                <div class="form-group">
                    <button type="submit" name="editar_usuario" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        // Manejar el modal de edición
        const modal = document.getElementById("editUserModal");
        const span = document.getElementsByClassName("close")[0];
        const editButtons = document.querySelectorAll(".btn-editar");
        
        // Abrir modal al hacer clic en editar
        editButtons.forEach(button => {
            button.addEventListener("click", function() {
                document.getElementById("editIdUsuario").value = this.dataset.id;
                document.getElementById("editNombreUsuario").value = this.dataset.nombre;
                document.getElementById("editRol").value = this.dataset.rol;
                
                // Mostrar foto actual si existe
                const photoPreview = document.getElementById("photoPreview");
                if (this.dataset.foto) {
                    photoPreview.src = "uploads/perfiles/" + this.dataset.foto;
                    photoPreview.style.display = "block";
                } else {
                    photoPreview.style.display = "none";
                }
                
                modal.style.display = "block";
            });
        });
        
        // Cerrar modal al hacer clic en la X
        span.onclick = function() {
            modal.style.display = "none";
        }
        
        // Cerrar modal al hacer clic fuera del contenido
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
        
        // Vista previa de la nueva foto
        document.getElementById('foto_perfil').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const photoPreview = document.getElementById("photoPreview");
                    photoPreview.src = event.target.result;
                    photoPreview.style.display = "block";
                };
                reader.readAsDataURL(file);
            }
        });
    });
    </script>
</body>
</html>
<?php
ob_end_flush();
?>