<?php
ob_start();

if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION['nombre_usuario']) || $_SESSION['rol'] != 'gerente') {
    header('Location: principal.php');
    exit();
}

require 'db_connection.php';

$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre_usuario = trim($_POST['nombre_usuario']);
    $contrasena = $_POST['contrasena'];
    $rol = $_POST['rol'];
    
    if (empty($nombre_usuario) || empty($contrasena) || empty($rol)) {
        $error = 'Todos los campos son obligatorios';
    } else {
        $stmt = $mysqli->prepare("SELECT id_usuario FROM usuarios WHERE nombre_usuario = ?");
        $stmt->bind_param("s", $nombre_usuario);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error = 'El nombre de usuario ya existe';
        } else {
            $foto_perfil = '';
            if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] == 0) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                if (in_array($_FILES['foto_perfil']['type'], $allowed_types)) {
                    $extension = pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION);
                    $nombre_archivo = uniqid() . '.' . $extension;
                    $ruta_destino = 'uploads/perfiles/' . $nombre_archivo;
                    
                    if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $ruta_destino)) {
                        $foto_perfil = $nombre_archivo;
                    } else {
                        $error = 'Error al subir la imagen';
                    }
                } else {
                    $error = 'Formato de imagen no válido. Use JPG, PNG o GIF';
                }
            }
            
            if (empty($error)) {
                $stmt = $mysqli->prepare("INSERT INTO usuarios (nombre_usuario, contrasena, foto_perfil, rol) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $nombre_usuario, $contrasena, $foto_perfil, $rol);
                
                if ($stmt->execute()) {
                    $exito = 'Usuario registrado exitosamente';
                    $_POST = array();
                } else {
                    $error = 'Error al registrar el usuario: ' . $stmt->error;
                }
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Usuarios</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .user-form {
            max-width: 600px;
            margin: 30px auto;
            padding: 25px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background-color: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
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
        
        .photo-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin: 15px auto;
            display: block;
            border: 3px solid #3498db;
            background-color: #f5f5f5;
        }
        
        .file-input {
            display: none;
        }
        
        .file-label {
            display: block;
            text-align: center;
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .file-label:hover {
            background-color: #e0e0e0;
        }
    </style>
</head>
<body>
    <div class="user-form">
        <h2><i class="fas fa-user-plus"></i> Registrar Nuevo Usuario</h2>
        
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
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="foto_perfil">Foto de Perfil</label>
                <input type="file" id="foto_perfil" name="foto_perfil" class="file-input" accept="image/*">
                <label for="foto_perfil" class="file-label">
                    <i class="fas fa-camera"></i> Seleccionar Foto
                </label>
                <img id="photoPreview" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" class="photo-preview">
            </div>
            
            <div class="form-group">
                <label for="nombre_usuario">Nombre de Usuario</label>
                <input type="text" id="nombre_usuario" name="nombre_usuario" class="form-control" 
                       value="<?php echo htmlspecialchars($_POST['nombre_usuario'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="contrasena">Contraseña</label>
                <input type="password" id="contrasena" name="contrasena" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="rol">Rol</label>
                <select id="rol" name="rol" class="form-control" required>
                    <option value="">Seleccionar Rol</option>
                    <option value="gerente" <?php echo ($_POST['rol'] ?? '') == 'gerente' ? 'selected' : ''; ?>>Gerente</option>
                    <option value="ventas" <?php echo ($_POST['rol'] ?? '') == 'ventas' ? 'selected' : ''; ?>>Ventas</option>
                    <option value="almacen" <?php echo ($_POST['rol'] ?? '') == 'almacen' ? 'selected' : ''; ?>>Almacén</option>
                    <option value="contabilidad" <?php echo ($_POST['rol'] ?? '') == 'contabilidad' ? 'selected' : ''; ?>>Contabilidad</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Registrar Usuario
            </button>
        </form>
    </div>

    <script>
    document.getElementById('foto_perfil').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                document.getElementById('photoPreview').src = event.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
    </script>
</body>
</html>
<?php
ob_end_flush();
?>