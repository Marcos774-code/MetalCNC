<?php
ob_start();
session_start();

require 'db_connection.php'; // Necesitamos la conexión para verificar el estado

// Verificación de sesión mejorada - CORRECCIÓN AQUÍ
if (!isset($_SESSION['nombre_usuario'])) {
    header('Location: index.php');
    exit();
}

// Verificar si el usuario sigue activo en la base de datos
$stmt = $mysqli->prepare("SELECT estado FROM usuarios WHERE nombre_usuario = ?");
$stmt->bind_param("s", $_SESSION['nombre_usuario']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    if ($row['estado'] != 1) {
        // Usuario deshabilitado - destruir sesión y redirigir
        session_unset();
        session_destroy();
        header('Location: index.php?error=cuenta_deshabilitada');
        exit();
    }
} else {
    // Usuario no existe en la base de datos
    session_unset();
    session_destroy();
    header('Location: index.php?error=usuario_no_encontrado');
    exit();
}

$usuario = $_SESSION['nombre_usuario'];
$rol = $_SESSION['rol'];
$vista = isset($_GET['vista']) ? $_GET['vista'] : 'inicio';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Principal - Metalcnc</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Roboto', sans-serif;
        }

        body {
            display: flex;
            min-height: 100vh;
            background: linear-gradient(to bottom right, #e0e0e0, #f8f8f8);
            flex-direction: column;
            position: relative;
        }

        .watermark {
            position: absolute;
            bottom: 20px;
            right: 20px;
            opacity: 0.1;
            z-index: -1;
        }

        .menu {
            background-color: #2c3e50;
            width: 250px;
            height: 100vh;
            margin: 0;
            padding: 0;
            top: 0;
            left: 0;
            color: #fff;
            position: fixed;
            display: flex;
            flex-direction: column;
            align-items: center;
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
            z-index: 1000;
        }

        .menu.menu-active {
            transform: translateX(0);
        }

        .menu .close-btn {
            display: none;
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 24px;
            color: #fff;
            cursor: pointer;
        }

        .profile-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #3498db;
            margin: 0 auto 15px;
            display: block;
        }

        .profile-default {
            width: 100px;
            height: 100px;
            margin: 0 auto 15px;
            color: #3498db;
            font-size: 80px;
            text-align: center;
        }

        .profile h4 {
            margin: 10px 0 5px;
            color: #fff;
            font-size: 18px;
        }

        .profile span {
            color: #bdc3c7;
            font-size: 14px;
        }

        .menu ul {
            list-style: none;
            width: 100%;
            padding: 0 15px;
        }

        .menu ul li a {
            display: block;
            text-decoration: none;
            color: #fff;
            padding: 15px 20px;
            border-radius: 8px;
            transition: background-color 0.3s ease, transform 0.2s ease;
            margin-bottom: 5px;
        }

        .menu ul li a:hover {
            background-color: rgb(185, 41, 41);
            transform: translateX(5px);
        }

        .submenu {
            display: none;
            background-color: #34495e;
            border-radius: 5px;
            margin: 5px 0;
            padding: 5px 0;
        }

        .submenu.active {
            display: block;
        }

        .submenu li a {
            padding: 10px 30px !important;
            font-size: 0.9em;
        }

        .submenu li a:hover {
            background-color: #3d566e !important;
        }

        .menu .logout {
            margin-top: auto;
            width: 100%;
            text-align: center;
            padding: 15px;
        }

        .menu .logout a {
            display: block;
            text-decoration: none;
            color: #fff;
            background-color: #e74c3c;
            padding: 15px;
            border-radius: 8px;
        }

        .content {
            margin-left: 0px;
            padding: 20px;
            flex-grow: 1;
            transition: margin-left 0.3s ease-in-out;
        }

        .content h1 {
            color: #2c3e50;
            margin-top: 60px;
            text-align: center;
        }

        .content p {
            color: #7f8c8d;
            text-align: center;
        }

        #menu-toggle {
            display: block;
            font-size: 24px;
            cursor: pointer;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
            color: #2c3e50;
        }

        @media (max-width: 769px) {
            .menu .close-btn {
                display: block;
            }
        }

        @media (min-width: 769px) {
            .menu {
                transform: translateX(0);
            }

            .content {
                margin-left: 250px;
            }

            #menu-toggle {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div id="menu-toggle" onclick="toggleMenu()">
        <i class="fas fa-bars"></i>
    </div>

    <div class="menu" id="menu">
        <span class="close-btn" onclick="toggleMenu()">&times;</span>
        
        <div class="profile">
            <?php if(!empty($_SESSION['foto_perfil'])): ?>
                <img src="uploads/perfiles/<?php echo htmlspecialchars($_SESSION['foto_perfil']); ?>" alt="Foto de perfil" class="profile-img">
            <?php else: ?>
                <div class="profile-default">
                    <i class="fas fa-user-circle"></i>
                </div>
            <?php endif; ?>
            <h4><?php echo htmlspecialchars($usuario); ?></h4>
            <span>Rol: <?php echo htmlspecialchars($rol); ?></span>
        </div>

        <ul>
            <?php if ($rol == 'gerente' || $rol == 'ventas'): ?>
                <li>
                    <a href="principal.php?vista=agregarCliente">
                        <i class="fas fa-user-plus"></i> Agregar Clientes
                    </a>
                </li>
                <li>
                    <a href="principal.php?vista=ventas">
                        <i class="fas fa-shopping-cart"></i> Ventas
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($rol == 'gerente' || $rol == 'contabilidad'): ?>
                <li>
                    <a href="#">
                        <i class="fas fa-chart-line"></i> Reportes
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-chart-pie"></i> Gráficos
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($rol == 'gerente' || $rol == 'almacen'): ?>
                <li>
                    <a href="principal.php?vista=almacen">
                        <i class="fas fa-warehouse"></i> Almacén
                    </a>
                </li>
                <li>
                    <a href="principal.php?vista=proveedores">
                        <i class="fas fa-truck"></i> Proveedores
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($rol == 'gerente'): ?>
                <li>
                    <a href="principal.php?vista=empleados">
                        <i class="fas fa-users"></i> Empleados
                    </a>
                </li>
                <li>
                    <a href="#" onclick="toggleSubmenu('config-submenu', this); return false;">
                        <i class="fas fa-cog"></i> Configuración
                        <i class="fas fa-chevron-down" style="float: right; margin-top: 3px;"></i>
                    </a>
                    <ul class="submenu" id="config-submenu">
                        <li>
                            <a href="principal.php?vista=registroUsuario">
                                <i class="fas fa-user-plus"></i> Registrar Usuarios
                            </a>
                        </li>
                        <li>
                            <a href="principal.php?vista=ajustesSistema">
                                <i class="fas fa-tools"></i> Ajustes del Sistema
                            </a>
                        </li>
                    </ul>
                </li>
            <?php endif; ?>
        </ul>

        <div class="logout">
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Salir
            </a>
        </div>
    </div>

    <div class="content">
        <?php
   if ($vista == 'agregarCliente') {
    include 'agregarCliente.php';
} elseif ($vista == 'empleados') {
    include 'empleados.php';
} elseif ($vista == 'proveedores') {
    include 'proveedores.php';
} elseif ($vista == 'almacen') {
    include 'almacen.php';
} elseif ($vista == 'ventas') {
    include 'ventas.php';
} elseif ($vista == 'ventas_productos') {
    include 'productos_venta.php';
} elseif ($vista == 'ventas_materia_prima') {
    include 'materia_prima_venta.php';
} elseif ($vista == 'cotizador') {
    include 'Cotizador.php';
    } elseif ($vista == 'registroUsuario') {
        include 'registroUsuario.php';
    } elseif ($vista == 'ajustesSistema') {
        include 'ajustesSistema.php';
    } elseif ($vista == 'productos') {
        include 'productos.php';
    } elseif ($vista == 'materia_prima') {
        include 'materia_prima.php';
    } elseif ($vista == 'activos') {
        include 'activos.php';
    } else {
        echo "<h1>Bienvenido al Sistema</h1>";
        echo "<p>Seleccione una opción del menú para comenzar.</p>";
    }
        ?>
    </div>

    <div class="watermark">
        <img src="./img/logo completo.png" alt="Metal CNC" width="200px">
    </div>

    <script>
    function toggleMenu() {
        const menu = document.getElementById('menu');
        menu.classList.toggle('menu-active');
    }
    
    function toggleSubmenu(id, element) {
        const submenu = document.getElementById(id);
        submenu.classList.toggle('active');
        
        const icon = element.querySelector('.fa-chevron-down, .fa-chevron-up');
        if (submenu.classList.contains('active')) {
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-up');
        } else {
            icon.classList.remove('fa-chevron-up');
            icon.classList.add('fa-chevron-down');
        }
    }
    </script>
</body>
</html>
<?php
ob_end_flush();
?>

