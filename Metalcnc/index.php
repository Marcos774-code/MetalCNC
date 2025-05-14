<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'db_connection.php';

    $rol = $_POST['rol'];
    $usuario = $_POST['usuario'];
    $password = $_POST['password'];

    // Consulta modificada para verificar estado del usuario
    $stmt = $mysqli->prepare("SELECT * FROM usuarios WHERE nombre_usuario = ? AND rol = ? AND contrasena = ? AND estado = 1");
    
    if ($stmt) {
        $stmt->bind_param("sss", $usuario, $rol, $password);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $_SESSION['nombre_usuario'] = $row['nombre_usuario'];
            $_SESSION['rol'] = $row['rol'];
            $_SESSION['foto_perfil'] = $row['foto_perfil'];

            header('Location: principal.php');
            exit();
        } else {
            // Verificar si el usuario existe pero está inactivo
            $stmt2 = $mysqli->prepare("SELECT * FROM usuarios WHERE nombre_usuario = ? AND rol = ? AND contrasena = ? AND estado = 0");
            $stmt2->bind_param("sss", $usuario, $rol, $password);
            $stmt2->execute();
            
            if ($stmt2->get_result()->num_rows === 1) {
                $error = "Tu cuenta está deshabilitada. Contacta al administrador.";
            } else {
                $error = "Usuario, rol o contraseña incorrectos.";
            }
            $stmt2->close();
        }
    } else {
        $error = "Error en la consulta SQL: " . $mysqli->error;
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Metalcnc</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: url('img/login\ one.jpg') no-repeat center center fixed;
            background-size: cover;
            font-family: 'Arial', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .card {
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            background: rgba(0, 0, 0, 0.7);
            border-radius: 10px;
            padding: 30px;
            color: #fff;
            max-width: 500px; /* Máximo ancho para la tarjeta en pantallas grandes */
            width: 100%; /* Ancho 100% para ajustarse a pantallas pequeñas */
        }

        .card h2 {
            font-size: 2rem;
            margin-bottom: 20px;
        }

        .alert {
            margin-bottom: 20px;
        }

        .form-label {
            font-size: 1rem;
        }

        .form-control {
            border-radius: 10px;
            padding: 10px;
            font-size: 1rem;
        }

        .btn-primary {
            background-color: #3498db;
            border-color: #3498db;
            font-size: 1.1rem;
            padding: 12px;
            border-radius: 10px;
            transition: background-color 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }

        .form-check-label {
            font-size: 0.9rem;
        }

        .container {
            max-width: 100%;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* Efecto al cargar el formulario */
        .card {
            animation: fadeIn 1s ease-out;
        }

        @keyframes fadeIn {
            0% { opacity: 0; }
            100% { opacity: 1; }
        }

        /* Estilos para la adaptabilidad */
        @media (max-width: 768px) {
            .card {
                width: 90%; /* Se adapta al tamaño de la pantalla */
                padding: 20px;
            }

            .card h2 {
                font-size: 1.5rem;
            }

            .btn-primary {
                font-size: 1rem;
                padding: 10px;
            }
        }

        @media (max-width: 480px) {
            .card {
                width: 95%; /* Ajusta más en pantallas más pequeñas */
                padding: 15px;
            }

            .card h2 {
                font-size: 1.2rem;
            }

            .btn-primary {
                font-size: 0.9rem;
                padding: 8px;
            }

            .form-control {
                font-size: 0.9rem;
                padding: 8px;
            }
        }

    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h2 class="text-center mb-4">Login</h2>
            
            <!-- Muestra un error si ocurre -->
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Formulario -->
            <form action="" method="POST">
                <div class="mb-3">
                    <label for="rol" class="form-label">Rol</label>
                    <select class="form-select" name="rol" id="rol" required>
                        <option value="">Seleccionar</option>
                        <option value="gerente">Gerente</option>
                        <option value="almacen">Almacen</option>
                        <option value="ventas">Ventas</option>
                        <option value="contabilidad">Contabilidad</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="usuario" class="form-label">Usuario</label>
                    <input type="text" class="form-control" name="usuario" id="usuario" placeholder="Ingresar usuario..." required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" name="password" id="password" placeholder="Ingresar contraseña..." required>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="mostrar" onclick="mostrarPassword()">
                    <label class="form-check-label" for="mostrar">Mostrar contraseña</label>
                </div>

                <button type="submit" class="btn btn-primary w-100">Ingresar</button>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function mostrarPassword() {
            var input = document.getElementById("password");
            input.type = (input.type === "password") ? "text" : "password";
        }
    </script>
</body>
</html>

