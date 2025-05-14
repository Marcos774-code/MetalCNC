<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require 'db_connection.php';

// Consultas reales a tu base de datos
$total_productos = $mysqli->query("SELECT COUNT(*) as total FROM almacen_productos")->fetch_assoc()['total'];
$total_materiales = $mysqli->query("SELECT COUNT(*) as total FROM almacen_materia_prima")->fetch_assoc()['total'];
$total_activos = $mysqli->query("SELECT COUNT(*) as total FROM almacen_activos")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Almacén - Metalcnc</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --color-primario: #7209b7;
            --color-secundario: #4361ee;
            --color-fondo: #f8f9fa;
            --color-texto: #333;
            --color-borde: #ddd;
            --color-alerta: #f72585;
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
            border-top: 3px solid var(--color-primario);
        }
        
        .tarjeta-estadistica .valor {
            font-size: 2rem;
            font-weight: bold;
            color: var(--color-primario);
            margin: 10px 0;
        }
        
        .tarjeta-estadistica.alerta .valor {
            color: var(--color-alerta);
        }
        
        .tarjeta-estadistica .etiqueta {
            color: #666;
            font-size: 0.9rem;
        }
        
        .icono-estadistica {
            width: 40px;
            height: 40px;
            background: var(--color-primario);
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
            background: var(--color-primario);
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
            background: #4a148c;
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
            <i class="fas fa-warehouse"></i>
            <h1>Gestión de Almacén</h1>
        </div>
        
        <div class="estadisticas">
            <div class="tarjeta-estadistica">
                <div class="icono-estadistica">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="valor"><?php echo $total_productos; ?></div>
                <div class="etiqueta">Productos</div>
            </div>
            
            <div class="tarjeta-estadistica">
                <div class="icono-estadistica">
                    <i class="fas fa-cubes"></i>
                </div>
                <div class="valor"><?php echo $total_materiales; ?></div>
                <div class="etiqueta">Materiales</div>
            </div>
            
            <div class="tarjeta-estadistica">
                <div class="icono-estadistica">
                    <i class="fas fa-tools"></i>
                </div>
                <div class="valor"><?php echo $total_activos; ?></div>
                <div class="etiqueta">Activos</div>
            </div>
            
            <div class="tarjeta-estadistica alerta">
                <div class="icono-estadistica" style="background: var(--color-alerta);">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="valor">
                    <?php 
                        $bajo_stock = $mysqli->query("SELECT COUNT(*) as total FROM almacen_productos WHERE cantidad < 5")->fetch_assoc()['total'];
                        echo $bajo_stock;
                    ?>
                </div>
                <div class="etiqueta">Bajo Stock</div>
            </div>
        </div>
        

<div class="modulos">
    <div class="modulo">
        <div class="modulo-cabecera">
            <i class="fas fa-box-open"></i>
            <h3 class="modulo-titulo">Productos</h3>
        </div>
        <div class="modulo-cuerpo">
            <p class="modulo-desc">Administrar productos terminados en inventario</p>
            <a href="?vista=productos" class="boton">Administrar</a>
        </div>
    </div>
    
    <div class="modulo">
        <div class="modulo-cabecera">
            <i class="fas fa-cube"></i>
            <h3 class="modulo-titulo">Materia Prima</h3>
        </div>
        <div class="modulo-cuerpo">
            <p class="modulo-desc">Gestionar materiales de producción</p>
            <a href="?vista=materia_prima" class="boton">Ver Stock</a>
        </div>
    </div>
    
    <div class="modulo">
        <div class="modulo-cabecera">
            <i class="fas fa-toolbox"></i>
            <h3 class="modulo-titulo">Activos</h3>
        </div>
        <div class="modulo-cuerpo">
            <p class="modulo-desc">Administrar herramientas y equipos</p>
            <a href="?vista=activos" class="boton">Ver Activos</a>
        </div>
    </div>
</div>
    </div>
</body>
</html>