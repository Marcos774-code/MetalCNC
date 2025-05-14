<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require 'db_connection.php';

// Consultas reales a tu base de datos
$total_ventas = $mysqli->query("SELECT COUNT(*) as total FROM ventas WHERE DATE(fecha_venta) = CURDATE()")->fetch_assoc()['total'];
$total_clientes = $mysqli->query("SELECT COUNT(*) as total FROM clientes")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas - Metalcnc</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --color-primario:rgb(49, 161, 241);
            --color-secundario: #3498db;
            --color-fondo: #f8f9fa;
            --color-texto: #333;
            --color-borde: #ddd;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--color-fondo);
            margin: 0;
            padding: 20px;
            color: var(--color-texto);
        }
        
        .contenedor-principal {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .encabezado {
            background: var(--color-primario);
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .encabezado i {
            font-size: 2rem;
        }
        
        .encabezado h1 {
            margin: 0;
            font-size: 1.8rem;
        }
        
        .estadisticas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 20px;
            background: #f1f5f9;
        }
        
        .tarjeta-estadistica {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .tarjeta-estadistica .valor {
            font-size: 2rem;
            font-weight: bold;
            color: var(--color-primario);
            margin: 10px 0;
        }
        
        .tarjeta-estadistica .etiqueta {
            color: #666;
            font-size: 0.9rem;
        }
        
        .icono-estadistica {
            width: 40px;
            height: 40px;
            background: var(--color-secundario);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            color: white;
        }
        
        .modulos {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        
        .modulo {
            border: 1px solid var(--color-borde);
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .modulo:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .modulo-cabecera {
            background: var(--color-secundario);
            color: white;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modulo-cabecera i {
            font-size: 1.5rem;
        }
        
        .modulo-cuerpo {
            padding: 20px;
        }
        
        .modulo-titulo {
            margin: 0;
            font-size: 1.2rem;
        }
        
        .modulo-desc {
            color: #666;
            margin: 10px 0 20px;
            font-size: 0.9rem;
        }
        
        .boton {
            display: inline-block;
            padding: 8px 15px;
            background: var(--color-primario);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.9rem;
            transition: background 0.3s;
        }
        
        .boton:hover {
            background: #1a252f;
        }
        
        @media (max-width: 768px) {
            .estadisticas {
                grid-template-columns: 1fr 1fr;
            }
            
            .modulos {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="contenedor-principal">
        <div class="encabezado">
            <i class="fas fa-cash-register"></i>
            <h1>Módulo de Ventas</h1>
        </div>
        
        <div class="estadisticas">
            <div class="tarjeta-estadistica">
                <div class="icono-estadistica">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="valor"><?php echo $total_ventas; ?></div>
                <div class="etiqueta">Ventas Hoy</div>
            </div>
            
            <div class="tarjeta-estadistica">
                <div class="icono-estadistica">
                    <i class="fas fa-users"></i>
                </div>
                <div class="valor"><?php echo $total_clientes; ?></div>
                <div class="etiqueta">Clientes</div>
            </div>
            
            <div class="tarjeta-estadistica">
                <div class="icono-estadistica">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="valor"><?php 
                    $productos = $mysqli->query("SELECT COUNT(*) as total FROM almacen_productos")->fetch_assoc()['total'];
                    echo $productos;
                ?></div>
                <div class="etiqueta">Productos</div>
            </div>
        </div>
        
        <div class="modulos">
    <div class="modulo">
        <div class="modulo-cabecera">
            <i class="fas fa-calculator"></i>
            <h3 class="modulo-titulo">Cotizador</h3>
        </div>
        <div class="modulo-cuerpo">
            <p class="modulo-desc">Cotizador de plegados x golpes</p>
            <a href="principal.php?vista=cotizador" class="boton">Acceder</a>
        </div>
    </div>
    
    <div class="modulo">
        <div class="modulo-cabecera">
            <i class="fas fa-box"></i>
            <h3 class="modulo-titulo">Productos</h3>
        </div>
        <div class="modulo-cuerpo">
            <p class="modulo-desc">Venta de productos terminados</p>
            <a href="principal.php?vista=ventas_productos" class="boton">Acceder</a>
        </div>
    </div>
    
    <div class="modulo">
        <div class="modulo-cabecera">
            <i class="fas fa-cubes"></i>
            <h3 class="modulo-titulo">Materia Prima</h3>
        </div>
        <div class="modulo-cuerpo">
            <p class="modulo-desc">Venta de planchas y materia prima</p>
            <a href="principal.php?vista=ventas_materia_prima" class="boton">Acceder</a>
        </div>
    </div>
</div>
    </div>
</body>
</html>