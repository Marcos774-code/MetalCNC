<?php
// Habilitar modo depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'db_connection.php';

// Verificar permisos
if (!isset($_SESSION['nombre_usuario'])) {
    header('Location: index.php');
    exit();
}

if ($_SESSION['rol'] != 'gerente' && $_SESSION['rol'] != 'ventas') {
    header('Location: principal.php');
    exit();
}

// Inicializar carrito si no existe
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Constantes
define('METODOS_PAGO', ['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'tarjeta' => 'Tarjeta', 'qr' => 'Pago QR']);

// Clases de ayuda
class VentaHelper {
    public static function calcularTotales($carrito) {
        $subtotal = 0;
        $total = 0;
        
        foreach ($carrito as $item) {
            $subtotal += $item['precio'] * $item['cantidad'];
        }
        
        $total = $subtotal;
        
        return [
            'subtotal' => $subtotal,
            'total' => $total,
            'items' => count($carrito)
        ];
    }
    
    public static function verificarStock($mysqli, $id_producto, $cantidad) {
        $stmt = $mysqli->prepare("SELECT cantidad, nombre_producto FROM almacen_productos WHERE id_producto = ?");
        $stmt->bind_param("i", $id_producto);
        $stmt->execute();
        $result = $stmt->get_result();
        $producto = $result->fetch_assoc();
        $stmt->close();
        
        if (!$producto) {
            return ['disponible' => false, 'mensaje' => 'Producto no encontrado'];
        }
        
        if ($producto['cantidad'] < $cantidad) {
            return [
                'disponible' => false,
                'mensaje' => "Stock insuficiente para {$producto['nombre_producto']}. Disponible: {$producto['cantidad']}"
            ];
        }
        
        return ['disponible' => true, 'stock' => $producto['cantidad']];
    }
}

// Procesar acciones del carrito
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Depuración: Registrar datos recibidos
        error_log("Datos POST recibidos: " . print_r($_POST, true));
        
        // Agregar producto al carrito
        if (isset($_POST['agregar_producto'])) {
            $id_producto = intval($_POST['id_producto']);
            $cantidad = intval($_POST['cantidad']);
            
            // Verificar stock
            $stockInfo = VentaHelper::verificarStock($mysqli, $id_producto, $cantidad);
            if (!$stockInfo['disponible']) {
                throw new Exception($stockInfo['mensaje']);
            }
            
            $producto = $mysqli->query("SELECT * FROM almacen_productos WHERE id_producto = $id_producto")->fetch_assoc();
            
            if (isset($_SESSION['carrito'][$id_producto])) {
                $nuevaCantidad = $_SESSION['carrito'][$id_producto]['cantidad'] + $cantidad;
                if ($nuevaCantidad > $stockInfo['stock']) {
                    throw new Exception("No hay suficiente stock disponible");
                }
                $_SESSION['carrito'][$id_producto]['cantidad'] = $nuevaCantidad;
                $_SESSION['exito'] = "Producto actualizado en el carrito";
            } else {
                $_SESSION['carrito'][$id_producto] = [
                    'id' => $producto['id_producto'],
                    'nombre' => $producto['nombre_producto'],
                    'precio' => $producto['precio_unitario'],
                    'cantidad' => $cantidad,
                    'stock' => $producto['cantidad']
                ];
                $_SESSION['exito'] = "Producto agregado al carrito";
            }
            
            header("Location: principal.php?vista=ventas_productos");
            exit();
        }
        
        // Actualizar cantidades del carrito
        if (isset($_POST['actualizar_carrito']) && isset($_POST['cantidades'])) {
            foreach ($_POST['cantidades'] as $id => $cantidad) {
                $id = intval($id);
                $cantidad = intval($cantidad);
                
                if (isset($_SESSION['carrito'][$id])) {
                    if ($cantidad <= 0) {
                        unset($_SESSION['carrito'][$id]);
                    } else {
                        // Verificar stock antes de actualizar
                        $stockInfo = VentaHelper::verificarStock($mysqli, $id, $cantidad);
                        if (!$stockInfo['disponible']) {
                            throw new Exception($stockInfo['mensaje']);
                        }
                        $_SESSION['carrito'][$id]['cantidad'] = $cantidad;
                    }
                }
            }
            $_SESSION['exito'] = "Carrito actualizado";
            header("Location: principal.php?vista=ventas_productos");
            exit();
        }
        
        // Eliminar producto del carrito (POST)
        if (isset($_POST['eliminar_producto'])) {
            $id = intval($_POST['eliminar_producto']);
            if (isset($_SESSION['carrito'][$id])) {
                unset($_SESSION['carrito'][$id]);
                $_SESSION['exito'] = "Producto eliminado del carrito";
                header("Location: principal.php?vista=ventas_productos");
                exit();
            }
        }
        
        // Aplicar descuento
        if (isset($_POST['aplicar_descuento'])) {
            $descuento = floatval($_POST['descuento']);
            if ($descuento < 0 || $descuento > 100) {
                throw new Exception("El descuento debe estar entre 0% y 100%");
            }
            $_SESSION['descuento'] = $descuento;
            $_SESSION['exito'] = "Descuento aplicado: {$descuento}%";
            header("Location: principal.php?vista=ventas_productos");
            exit();
        }
        
        // Finalizar venta - CÓDIGO MEJORADO
        if (isset($_POST['finalizar_venta'])) {
            error_log("Iniciando proceso de finalizar venta");
            
            // Validar cliente
            if (!isset($_POST['id_cliente']) || intval($_POST['id_cliente']) <= 0) {
                throw new Exception("Debe seleccionar un cliente válido");
            }
            
            // Validar carrito no vacío
            if (empty($_SESSION['carrito'])) {
                throw new Exception("Debe agregar al menos un producto");
            }
            
            $id_cliente = intval($_POST['id_cliente']);
            $metodo_pago = $_POST['metodo_pago'] ?? 'efectivo';
            $observaciones = $_POST['observaciones'] ?? '';
            $monto_pagado = floatval($_POST['monto_pagado'] ?? 0);
            $descuento = $_SESSION['descuento'] ?? 0;
            
            // Calcular totales
            $totales = VentaHelper::calcularTotales($_SESSION['carrito']);
            $subtotal = $totales['subtotal'];
            $total = $totales['total'];
            
            // Aplicar descuento si existe
            if ($descuento > 0) {
                $total = $subtotal * (1 - ($descuento / 100));
            }
            
            // Validar pago en efectivo
            if ($metodo_pago == 'efectivo' && $monto_pagado < $total) {
                throw new Exception("El monto pagado no puede ser menor al total");
            }
            
            // Iniciar transacción
            $mysqli->begin_transaction();
            error_log("Transacción iniciada");
            
            try {
                // 1. Registrar la venta
                $stmt = $mysqli->prepare("INSERT INTO ventas (
                    id_cliente, id_usuario, fecha_venta, subtotal, descuento, total, 
                    metodo_pago, monto_pagado, cambio, observaciones
                ) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)");
                
                if (!$stmt) {
                    throw new Exception("Error al preparar consulta de venta: " . $mysqli->error);
                }
                
                $cambio = $monto_pagado - $total;
                $stmt->bind_param("iidddssds", 
                    $id_cliente, 
                    $_SESSION['id_usuario'], 
                    $subtotal, 
                    $descuento, 
                    $total, 
                    $metodo_pago, 
                    $monto_pagado, 
                    $cambio, 
                    $observaciones
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("Error al registrar la venta: " . $stmt->error);
                }
                
                $id_venta = $mysqli->insert_id;
                $stmt->close();
                error_log("Venta registrada con ID: $id_venta");
                
                // 2. Registrar detalles y actualizar inventario
                foreach ($_SESSION['carrito'] as $item) {
                    // Registrar detalle
                    $stmt = $mysqli->prepare("INSERT INTO detalle_venta (
                        codigo_venta, id_producto, cantidad, precio_unitario, subtotal, tipo_material
                    ) VALUES (?, ?, ?, ?, ?, 'producto')");
                    
                    if (!$stmt) {
                        throw new Exception("Error al preparar consulta de detalle: " . $mysqli->error);
                    }
                    
                    $subtotal_item = $item['cantidad'] * $item['precio'];
                    $stmt->bind_param("iiidd", $id_venta, $item['id'], $item['cantidad'], $item['precio'], $subtotal_item);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Error al registrar detalle de venta: " . $stmt->error);
                    }
                    $stmt->close();
                    
                    // Actualizar inventario
                    $stmt = $mysqli->prepare("UPDATE almacen_productos SET cantidad = cantidad - ? WHERE id_producto = ?");
                    
                    if (!$stmt) {
                        throw new Exception("Error al preparar consulta de actualización: " . $mysqli->error);
                    }
                    
                    $stmt->bind_param("ii", $item['cantidad'], $item['id']);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Error al actualizar inventario: " . $stmt->error);
                    }
                    $stmt->close();
                }
                error_log("Detalles de venta registrados");
                
                // 3. Registrar pago si es diferente de efectivo
                if ($metodo_pago != 'efectivo') {
                    $stmt = $mysqli->prepare("INSERT INTO pagos (id_venta, metodo_pago, monto, fecha_pago) 
                                           VALUES (?, ?, ?, NOW())");
                    
                    if (!$stmt) {
                        throw new Exception("Error al preparar consulta de pago: " . $mysqli->error);
                    }
                    
                    $stmt->bind_param("isd", $id_venta, $metodo_pago, $total);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Error al registrar pago: " . $stmt->error);
                    }
                    $stmt->close();
                    error_log("Pago registrado");
                }
                
                // Confirmar transacción
                $mysqli->commit();
                error_log("Transacción completada con éxito");
                
                // Guardar datos para la factura
                $_SESSION['factura_data'] = [
                    'id_venta' => $id_venta,
                    'fecha' => date('d/m/Y'),
                    'hora' => date('H:i'),
                    'cliente' => obtenerNombreCliente($id_cliente),
                    'ci_cliente' => obtenerCIcliente($id_cliente),
                    'productos' => $_SESSION['carrito'],
                    'subtotal' => $subtotal,
                    'descuento' => $descuento,
                    'total' => $total,
                    'monto_pagado' => $monto_pagado,
                    'cambio' => $cambio,
                    'vendedor' => $_SESSION['nombre_usuario'],
                    'metodo_pago' => $metodo_pago,
                    'observaciones' => $observaciones
                ];
                
                // Limpiar carrito y descuento
                unset($_SESSION['carrito']);
                unset($_SESSION['descuento']);
                
                $_SESSION['exito'] = "Venta registrada correctamente. Total: Bs. " . number_format($total, 2);
                error_log("Redireccionando a factura con ID: $id_venta");
                header("Location: principal.php?vista=ventas_productos&factura=".$id_venta);
                exit();
                
            } catch (Exception $e) {
                $mysqli->rollback();
                error_log("Error en transacción: " . $e->getMessage());
                throw $e;
            }
        }
        
    } catch (Exception $e) {
        error_log("Error en el proceso: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
        header("Location: principal.php?vista=ventas_productos");
        exit();
    }
}

// Función auxiliar para obtener nombre del cliente
function obtenerNombreCliente($id) {
    global $mysqli;
    $stmt = $mysqli->prepare("SELECT CONCAT(nombres, ' ', apellidos) as nombre FROM clientes WHERE id_cliente = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['nombre'] ?? 'Cliente no encontrado';
}

// Función para obtener CI del cliente
function obtenerCIcliente($id) {
    global $mysqli;
    $stmt = $mysqli->prepare("SELECT nit_ci FROM clientes WHERE id_cliente = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['nit_ci'] ?? 'No registrado';
}

// Obtener lista de productos
$busqueda = isset($_POST['busqueda']) ? trim($_POST['busqueda']) : '';
if (!empty($busqueda)) {
    $stmt = $mysqli->prepare("SELECT * FROM almacen_productos WHERE (nombre_producto LIKE ? OR descripcion LIKE ?) AND cantidad > 0 ORDER BY nombre_producto");
    $busqueda_like = "%$busqueda%";
    $stmt->bind_param("ss", $busqueda_like, $busqueda_like);
    $stmt->execute();
    $productos = $stmt->get_result();
} else {
    $productos = $mysqli->query("SELECT * FROM almacen_productos WHERE cantidad > 0 ORDER BY nombre_producto");
}

// Obtener lista de clientes
$clientes = $mysqli->query("SELECT id_cliente, CONCAT(nombres, ' ', apellidos) as nombre, nit_ci FROM clientes ORDER BY nombre");

// Obtener historial de ventas recientes
$historial = $mysqli->query("
    SELECT v.codigo_venta, v.fecha_venta, c.nombres, c.apellidos, v.total 
    FROM ventas v 
    JOIN clientes c ON v.id_cliente = c.id_cliente 
    ORDER BY v.fecha_venta DESC LIMIT 5
");

// Calcular totales del carrito
$totales = VentaHelper::calcularTotales($_SESSION['carrito'] ?? []);
$subtotal = $totales['subtotal'];
$total = $totales['total'];
$descuento = isset($_SESSION['descuento']) ? $_SESSION['descuento'] : 0;

// Aplicar descuento si existe
if ($descuento > 0) {
    $total = $subtotal * (1 - ($descuento / 100));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas de Productos | Metal CNC</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --success-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --gray-color: #95a5a6;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            display: flex;
            min-height: 100vh;
            gap: 20px;
            padding: 20px;
        }
        
        .panel {
            flex: 1;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            overflow-y: auto;
        }
        
        h2 {
            color: var(--dark-color);
            margin-bottom: 20px;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            align-items: center;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        
        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }
        
        .btn-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #27ae60;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .btn-warning {
            background-color: var(--warning-color);
            color: white;
        }
        
        .btn-warning:hover {
            background-color: #e67e22;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 13px;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .cantidad-input {
            width: 70px;
            text-align: center;
            padding: 5px;
        }
        
        .resumen-venta {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .resumen-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .resumen-total {
            display: flex;
            justify-content: space-between;
            font-weight: 600;
            font-size: 1.1rem;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }
        
        .descuento-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .descuento-input {
            flex: 1;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .historial-ventas {
            margin-top: 20px;
        }
        
        .historial-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .historial-info {
            display: flex;
            flex-direction: column;
        }
        
        .historial-info small {
            color: var(--gray-color);
            font-size: 0.85rem;
        }
        
        .historial-total {
            font-weight: 600;
            color: var(--success-color);
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow: auto;
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 800px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #333;
        }
        
        /* Recibo/Factura */
        .recibo {
            font-family: Arial, sans-serif;
            max-width: 100%;
        }
        
        .recibo-header {
            text-align: center;
            margin-bottom: 15px;
        }
        
        .recibo-header h2 {
            margin-bottom: 5px;
            color: #000;
        }
        
        .recibo-header p {
            margin: 2px 0;
            font-size: 13px;
        }
        
        .recibo-body {
            margin-bottom: 15px;
        }
        
        .recibo-footer {
            text-align: center;
            font-size: 13px;
        }
        
        hr {
            border: none;
            border-top: 1px dashed #ccc;
            margin: 10px 0;
        }
        
        @media print {
            body * {
                visibility: hidden;
            }
            #recibo, #recibo * {
                visibility: visible;
            }
            #recibo {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0;
                padding: 20px;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Panel izquierdo - Productos -->
        <div class="panel">
            <h2><i class="fas fa-boxes"></i> Productos Disponibles</h2>
            
            <?php if (isset($_SESSION['exito'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $_SESSION['exito']; unset($_SESSION['exito']); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="search-form">
                <div class="form-group">
                    <input type="text" name="busqueda" placeholder="Buscar productos..." 
                           value="<?php echo isset($_POST['busqueda']) ? htmlspecialchars($_POST['busqueda']) : ''; ?>">
                </div>
                <button type="submit" name="buscar_producto" class="btn btn-primary">
                    <i class="fas fa-search"></i> Buscar
                </button>
                <?php if (!empty($busqueda)): ?>
                <a href="principal.php?vista=ventas_productos" class="btn btn-danger">
                    <i class="fas fa-times"></i> Limpiar
                </a>
                <?php endif; ?>
            </form>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th>Cantidad</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($productos->num_rows > 0): ?>
                            <?php while ($producto = $productos->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($producto['nombre_producto']); ?></td>
                                    <td>Bs. <?php echo number_format($producto['precio_unitario'], 2); ?></td>
                                    <td><?php echo $producto['cantidad']; ?></td>
                                    <td>
                                        <input type="number" class="cantidad-input" value="1" min="1" max="<?php echo $producto['cantidad']; ?>" 
                                               id="cantidad-<?php echo $producto['id_producto']; ?>">
                                    </td>
                                    <td>
                                        <button onclick="agregarAlCarrito(<?php echo $producto['id_producto']; ?>)" 
                                                class="btn btn-primary btn-sm">
                                            <i class="fas fa-cart-plus"></i> Agregar
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 15px;">
                                    <i class="fas fa-box-open" style="font-size: 20px; color: #6c757d; margin-bottom: 8px;"></i>
                                    <p>No se encontraron productos</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Historial de ventas recientes -->
            <h2 style="margin-top: 20px;"><i class="fas fa-history"></i> Ventas Recientes</h2>
            <div class="historial-ventas">
                <?php if ($historial->num_rows > 0): ?>
                    <?php while ($venta = $historial->fetch_assoc()): ?>
                        <div class="historial-item">
                            <div class="historial-info">
                                <strong>#<?php echo str_pad($venta['codigo_venta'], 6, '0', STR_PAD_LEFT); ?></strong>
                                <span><?php echo htmlspecialchars($venta['nombres'] . ' ' . $venta['apellidos']); ?></span>
                                <small><?php echo date('d/m/Y H:i', strtotime($venta['fecha_venta'])); ?></small>
                            </div>
                            <div class="historial-total">
                                Bs. <?php echo number_format($venta['total'], 2); ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #6c757d;">No hay ventas recientes</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Panel derecho - Carrito de compras -->
        <div class="panel">
            <h2><i class="fas fa-shopping-cart"></i> Carrito de Compras</h2>
            
            <div class="form-group">
                <label for="cliente-seleccionado">Cliente *</label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="cliente-seleccionado" placeholder="Seleccione un cliente..." readonly required
                           value="<?php echo isset($_POST['cliente_nombre']) ? htmlspecialchars($_POST['cliente_nombre']) : ''; ?>">
                    <button type="button" onclick="abrirModalClientes()" class="btn btn-primary">
                        <i class="fas fa-users"></i> Seleccionar
                    </button>
                </div>
                <input type="hidden" id="id_cliente" name="id_cliente" 
                       value="<?php echo isset($_POST['id_cliente']) ? htmlspecialchars($_POST['id_cliente']) : ''; ?>">
            </div>
            
            <form method="POST" id="form-carrito">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Precio</th>
                                <th>Cantidad</th>
                                <th>Subtotal</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="lista-carrito">
                            <?php if (!empty($_SESSION['carrito'])): ?>
                                <?php foreach ($_SESSION['carrito'] as $id => $item): ?>
                                <tr class="carrito-item">
                                    <td><?php echo htmlspecialchars($item['nombre']); ?></td>
                                    <td>Bs. <?php echo number_format($item['precio'], 2); ?></td>
                                    <td>
                                        <input type="number" name="cantidades[<?php echo $id; ?>]" 
                                               value="<?php echo $item['cantidad']; ?>" min="1" max="<?php echo $item['stock']; ?>" 
                                               class="cantidad-input" onchange="actualizarTotales()">
                                    </td>
                                    <td class="subtotal-item">Bs. <?php echo number_format($item['precio'] * $item['cantidad'], 2); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="eliminar_producto" value="<?php echo $id; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Eliminar">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 15px;">
                                        <i class="fas fa-shopping-cart" style="font-size: 20px; color: #6c757d; margin-bottom: 8px;"></i>
                                        <p>El carrito está vacío</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (!empty($_SESSION['carrito'])): ?>
                <div class="resumen-venta">
                    <div class="resumen-item">
                        <span>Subtotal:</span>
                        <span id="subtotal">Bs. <?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    
                    <?php if ($descuento > 0): ?>
                    <div class="resumen-item">
                        <span>Descuento (<?php echo $descuento; ?>%):</span>
                        <span id="descuento">- Bs. <?php echo number_format($subtotal * ($descuento / 100), 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="resumen-total">
                        <span>TOTAL:</span>
                        <span id="total">Bs. <?php echo number_format($total, 2); ?></span>
                    </div>
                </div>
                
                <!-- Formulario de descuento -->
                <form method="POST" class="descuento-container">
                    <div class="form-group descuento-input">
                        <input type="number" name="descuento" placeholder="Descuento %" min="0" max="100" 
                               value="<?php echo $descuento; ?>" class="form-control">
                    </div>
                    <button type="submit" name="aplicar_descuento" class="btn btn-warning">
                        <i class="fas fa-tag"></i> Aplicar
                    </button>
                </form>
                
                <div class="form-group">
                    <label for="metodo_pago">Método de Pago *</label>
                    <select id="metodo_pago" name="metodo_pago" required class="form-control">
                        <?php foreach (METODOS_PAGO as $valor => $texto): ?>
                            <option value="<?php echo $valor; ?>" <?php echo ($metodo_pago ?? 'efectivo') == $valor ? 'selected' : ''; ?>>
                                <?php echo $texto; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" id="monto-pagado-container">
                    <label for="monto_pagado">Monto Recibido (Bs.) *</label>
                    <input type="number" id="monto_pagado" name="monto_pagado" step="0.01" min="<?php echo $total; ?>" 
                           value="<?php echo $total; ?>" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="observaciones">Observaciones</label>
                    <textarea id="observaciones" name="observaciones" rows="2" class="form-control"><?php echo $observaciones ?? ''; ?></textarea>
                </div>
                
                <div class="button-group">
                    <button type="submit" name="actualizar_carrito" class="btn btn-primary">
                        <i class="fas fa-sync-alt"></i> Actualizar Carrito
                    </button>
                    <button type="submit" name="finalizar_venta" class="btn btn-success">
                        <i class="fas fa-check-circle"></i> Finalizar Venta
                    </button>
                </div>
                
                <input type="hidden" id="productos_json" name="productos_json" value="<?php echo htmlspecialchars(json_encode($_SESSION['carrito'])); ?>">
                <input type="hidden" id="total_venta" name="total_venta" value="<?php echo $total; ?>">
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Modal para selección de clientes -->
    <div id="modalClientes" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModalClientes()">&times;</span>
            <h2><i class="fas fa-users"></i> Seleccionar Cliente</h2>
            
            <div class="form-group">
                <input type="text" id="buscar-cliente-modal" placeholder="Buscar cliente..." class="form-control">
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>NIT/CI</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody id="lista-clientes">
                        <?php while ($cliente = $clientes->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($cliente['nit_ci']); ?></td>
                            <td>
                                <button onclick="seleccionarCliente(<?php echo $cliente['id_cliente']; ?>, '<?php echo htmlspecialchars($cliente['nombre']); ?>', '<?php echo htmlspecialchars($cliente['nit_ci']); ?>')" 
                                        class="btn btn-primary btn-sm">
                                    <i class="fas fa-check"></i> Seleccionar
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal para la factura -->
    <?php if (isset($_GET['factura']) && isset($_SESSION['factura_data'])): ?>
    <div id="modal-factura" class="modal" style="display: block;">
        <div class="modal-content" style="max-width: 800px;">
            <span class="close" onclick="document.getElementById('modal-factura').style.display='none'">&times;</span>
            
            <div class="recibo" id="recibo">
                <div class="recibo-header">
                    <h2>*** METAL CNC ***</h2>
                    <p>Av. Siempre Viva #123 - Tel: 78945612</p>
                    <p>NIT: 987654321 - La Paz, Bolivia</p>
                    <hr>
                    <h3>RECIBO DE VENTA N° <?php echo str_pad($_SESSION['factura_data']['id_venta'], 6, '0', STR_PAD_LEFT); ?></h3>
                    <p>
                        Fecha: <?php echo $_SESSION['factura_data']['fecha']; ?> &nbsp;&nbsp;&nbsp; 
                        Hora: <?php echo $_SESSION['factura_data']['hora']; ?>
                    </p>
                    <hr>
                </div>
                
                <div class="recibo-body">
                    <div style="margin-bottom: 10px;">
                        <p style="margin: 2px 0;"><strong>Cliente:</strong> <?php echo $_SESSION['factura_data']['cliente']; ?></p>
                        <p style="margin: 2px 0;"><strong>C.I.:</strong> <?php echo $_SESSION['factura_data']['ci_cliente']; ?></p>
                    </div>
                    <hr>
                    
                    <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                        <thead>
                            <tr>
                                <th style="text-align: left; width: 10%;">CANT</th>
                                <th style="text-align: left; width: 50%;">DESCRIPCIÓN</th>
                                <th style="text-align: right; width: 20%;">P.UNIT</th>
                                <th style="text-align: right; width: 20%;">SUBTOTAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($_SESSION['factura_data']['productos'] as $producto): ?>
                            <tr>
                                <td style="text-align: left;"><?php echo $producto['cantidad']; ?></td>
                                <td style="text-align: left;"><?php echo htmlspecialchars($producto['nombre']); ?></td>
                                <td style="text-align: right;">Bs. <?php echo number_format($producto['precio'], 2); ?></td>
                                <td style="text-align: right;">Bs. <?php echo number_format($producto['cantidad'] * $producto['precio'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <hr>
                    
                    <div style="text-align: right; margin-top: 10px; font-size: 13px;">
                        <p style="margin: 5px 0;"><strong>SUBTOTAL Bs.</strong> <?php echo number_format($_SESSION['factura_data']['subtotal'], 2); ?></p>
                        <?php if ($_SESSION['factura_data']['descuento'] > 0): ?>
                        <p style="margin: 5px 0;"><strong>DESCUENTO (<?php echo $_SESSION['factura_data']['descuento']; ?>%) Bs.</strong> <?php echo number_format($_SESSION['factura_data']['subtotal'] * ($_SESSION['factura_data']['descuento'] / 100), 2); ?></p>
                        <?php endif; ?>
                        <p style="margin: 5px 0;"><strong>TOTAL Bs.</strong> <?php echo number_format($_SESSION['factura_data']['total'], 2); ?></p>
                        <p style="margin: 5px 0;"><strong><?php echo strtoupper($_SESSION['factura_data']['metodo_pago']); ?> Bs.</strong> <?php echo number_format($_SESSION['factura_data']['monto_pagado'], 2); ?></p>
                        <?php if ($_SESSION['factura_data']['metodo_pago'] == 'efectivo'): ?>
                        <p style="margin: 5px 0;"><strong>CAMBIO Bs.</strong> <?php echo number_format($_SESSION['factura_data']['cambio'], 2); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($_SESSION['factura_data']['observaciones'])): ?>
                    <hr>
                    <div style="margin-top: 10px;">
                        <p style="margin: 2px 0;"><strong>Observaciones:</strong></p>
                        <p style="margin: 2px 0;"><?php echo htmlspecialchars($_SESSION['factura_data']['observaciones']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <hr>
                
                <div class="recibo-footer">
                    <p style="margin: 2px 0;">Atendido por: <?php echo $_SESSION['factura_data']['vendedor']; ?></p>
                    <p style="margin: 10px 0 2px 0; font-style: italic;">¡Gracias por su preferencia!</p>
                    <p style="margin: 2px 0; font-size: 11px;">Conserve su recibo, por favor</p>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 15px; justify-content: center;" class="no-print">
                    <button id="btn-imprimir" class="btn btn-primary">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                    <button id="btn-cerrar-factura" class="btn btn-danger">
                        <i class="fas fa-times"></i> Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php unset($_SESSION['factura_data']); ?>
    <?php endif; ?>

    <script>
        // Funciones para el modal de clientes
        function abrirModalClientes() {
            document.getElementById('modalClientes').style.display = 'block';
        }
        
        function cerrarModalClientes() {
            document.getElementById('modalClientes').style.display = 'none';
        }
        
        function seleccionarCliente(id, nombre, ci) {
            document.getElementById('id_cliente').value = id;
            document.getElementById('cliente-seleccionado').value = nombre + ' - ' + ci;
            cerrarModalClientes();
        }
        
        // Función para agregar productos al carrito
        function agregarAlCarrito(idProducto) {
            const inputCantidad = document.getElementById(`cantidad-${idProducto}`);
            if (!inputCantidad) return;
            
            const cantidad = parseInt(inputCantidad.value) || 1;
            const max = parseInt(inputCantidad.max) || 999;
            const cantidadFinal = Math.min(Math.max(cantidad, 1), max);
            
            // Crear formulario dinámico
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const inputId = document.createElement('input');
            inputId.type = 'hidden';
            inputId.name = 'id_producto';
            inputId.value = idProducto;
            
            const inputCant = document.createElement('input');
            inputCant.type = 'hidden';
            inputCant.name = 'cantidad';
            inputCant.value = cantidadFinal;
            
            const inputAction = document.createElement('input');
            inputAction.type = 'hidden';
            inputAction.name = 'agregar_producto';
            inputAction.value = '1';
            
            form.appendChild(inputId);
            form.appendChild(inputCant);
            form.appendChild(inputAction);
            document.body.appendChild(form);
            form.submit();
        }
        
        // Actualizar totales del carrito
        function actualizarTotales() {
            let subtotal = 0;
            const filas = document.querySelectorAll('#lista-carrito tr.carrito-item');
            
            filas.forEach(fila => {
                const precio = parseFloat(fila.cells[1].textContent.replace('Bs. ', ''));
                const cantidad = parseInt(fila.querySelector('input').value);
                const subtotalFila = precio * cantidad;
                
                fila.cells[3].textContent = `Bs. ${subtotalFila.toFixed(2)}`;
                subtotal += subtotalFila;
            });
            
            // Aplicar descuento si existe
            const descuento = parseFloat(document.querySelector('input[name="descuento"]')?.value) || 0;
            let total = subtotal;
            
            if (descuento > 0) {
                total = subtotal * (1 - (descuento / 100));
                document.getElementById('descuento').textContent = `- Bs. ${(subtotal * (descuento / 100)).toFixed(2)}`;
                document.getElementById('descuento').parentElement.style.display = 'flex';
            } else if (document.getElementById('descuento')) {
                document.getElementById('descuento').parentElement.style.display = 'none';
            }
            
            document.getElementById('subtotal').textContent = `Bs. ${subtotal.toFixed(2)}`;
            document.getElementById('total').textContent = `Bs. ${total.toFixed(2)}`;
            document.getElementById('total_venta').value = total;
            
            // Actualizar monto mínimo a pagar
            const montoPagado = document.getElementById('monto_pagado');
            if (montoPagado) {
                montoPagado.min = total;
                if (parseFloat(montoPagado.value) < total) {
                    montoPagado.value = total.toFixed(2);
                }
            }
        }
        
        // Búsqueda de clientes en el modal
        document.getElementById('buscar-cliente-modal')?.addEventListener('input', function() {
            const busqueda = this.value.toLowerCase();
            const filas = document.querySelectorAll('#lista-clientes tr');
            
            filas.forEach(fila => {
                const nombre = fila.cells[0].textContent.toLowerCase();
                const nit = fila.cells[1].textContent.toLowerCase();
                
                if (nombre.includes(busqueda) || nit.includes(busqueda)) {
                    fila.style.display = '';
                } else {
                    fila.style.display = 'none';
                }
            });
        });
        
        // Manejar cambio de método de pago
        function manejarMetodoPago() {
            const metodoPago = document.getElementById('metodo_pago').value;
            const montoPagadoContainer = document.getElementById('monto-pagado-container');
            
            if (metodoPago === 'efectivo') {
                montoPagadoContainer.style.display = 'block';
                document.getElementById('monto_pagado').required = true;
            } else {
                montoPagadoContainer.style.display = 'none';
                document.getElementById('monto_pagado').required = false;
            }
        }
        
        // Validar formulario antes de enviar
        document.getElementById('form-carrito')?.addEventListener('submit', function(e) {
            const btnPresionado = e.submitter.name;
            
            if (btnPresionado === 'finalizar_venta') {
                const idCliente = document.getElementById('id_cliente').value;
                
                if (!idCliente || idCliente <= 0) {
                    e.preventDefault();
                    alert('Debe seleccionar un cliente antes de finalizar la venta');
                    abrirModalClientes();
                    return;
                }
                
                // Validar que el carrito no esté vacío
                const carritoVacio = document.querySelectorAll('#lista-carrito tr.carrito-item').length === 0;
                if (carritoVacio) {
                    e.preventDefault();
                    alert('El carrito está vacío. Agregue productos antes de finalizar la venta');
                    return;
                }
                
                // Validar método de pago
                const metodoPago = document.getElementById('metodo_pago').value;
                if (metodoPago === 'efectivo') {
                    const montoPagado = parseFloat(document.getElementById('monto_pagado').value);
                    const total = parseFloat(document.getElementById('total').textContent.replace('Bs. ', ''));
                    
                    if (montoPagado < total) {
                        e.preventDefault();
                        alert('El monto pagado no puede ser menor al total');
                        return;
                    }
                }
            }
        });
        
        // Inicializar eventos
        document.addEventListener('DOMContentLoaded', function() {
            // Actualizar totales al cargar
            if (document.querySelectorAll('#lista-carrito tr.carrito-item').length > 0) {
                actualizarTotales();
            }
            
            // Cerrar modal al hacer clic fuera
            window.addEventListener('click', function(event) {
                if (event.target == document.getElementById('modalClientes')) {
                    cerrarModalClientes();
                }
                if (event.target == document.getElementById('modal-factura')) {
                    document.getElementById('modal-factura').style.display = 'none';
                }
            });
            
            // Configurar método de pago
            const metodoPagoSelect = document.getElementById('metodo_pago');
            if (metodoPagoSelect) {
                metodoPagoSelect.addEventListener('change', manejarMetodoPago);
                // Ejecutar al cargar para configurar estado inicial
                manejarMetodoPago();
            }
            
            // Configurar botón de imprimir
            const btnImprimir = document.getElementById('btn-imprimir');
            if (btnImprimir) {
                btnImprimir.addEventListener('click', function() {
                    window.print();
                });
            }
            
            // Configurar botón de cerrar factura
            const btnCerrarFactura = document.getElementById('btn-cerrar-factura');
            if (btnCerrarFactura) {
                btnCerrarFactura.addEventListener('click', function() {
                    document.getElementById('modal-factura').style.display = 'none';
                    // Limpiar parámetro de la URL sin recargar
                    history.replaceState(null, null, window.location.pathname);
                });
            }
        });
    </script>
</body>
</html>