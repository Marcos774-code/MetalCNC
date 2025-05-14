<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require 'db_connection.php';

// Verificar permisos
if ($_SESSION['rol'] != 'gerente' && $_SESSION['rol'] != 'ventas') {
    header('Location: principal.php');
    exit();
}

// Procesar venta de materia prima
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['finalizar_venta'])) {
    $id_cliente = intval($_POST['id_cliente']);
    $materiales = json_decode($_POST['materiales_json'], true);
    $total_venta = floatval($_POST['total_venta']);
    $metodo_pago = $_POST['metodo_pago'];
    $observaciones = trim($_POST['observaciones']);
    
    // Validaciones
    if ($id_cliente <= 0) {
        $_SESSION['error'] = "Debe seleccionar un cliente válido";
        header("Location: principal.php?vista=ventas_materia_prima");
        exit();
    }
    
    if (empty($materiales)) {
        $_SESSION['error'] = "Debe agregar al menos un material";
        header("Location: principal.php?vista=ventas_materia_prima");
        exit();
    }
    
    // Iniciar transacción
    $mysqli->begin_transaction();
    
    try {
        // 1. Registrar la venta
        $stmt = $mysqli->prepare("INSERT INTO ventas (id_cliente, id_usuario, fecha_venta, total, metodo_pago, observaciones) 
                                 VALUES (?, ?, NOW(), ?, ?, ?)");
        $stmt->bind_param("iidss", $id_cliente, $_SESSION['id_usuario'], $total_venta, $metodo_pago, $observaciones);
        $stmt->execute();
        $id_venta = $mysqli->insert_id;
        $stmt->close();
        
        // 2. Registrar detalles y actualizar inventario
        foreach ($materiales as $material) {
            // Registrar detalle (usamos id_producto = 0 para materia prima)
            $stmt = $mysqli->prepare("INSERT INTO detalle_venta (codigo_venta, id_producto, cantidad, subtotal, tipo_material) 
                                     VALUES (?, 0, ?, ?, ?)");
            $subtotal = $material['cantidad'] * $material['precio'];
            $stmt->bind_param("iids", $id_venta, $material['cantidad'], $subtotal, $material['tipo']);
            $stmt->execute();
            $stmt->close();
            
            // Actualizar inventario
            $stmt = $mysqli->prepare("UPDATE almacen_materia_prima SET cantidad = cantidad - ? WHERE id_plancha = ?");
            $stmt->bind_param("ii", $material['cantidad'], $material['id']);
            $stmt->execute();
            $stmt->close();
        }
        
        // Confirmar transacción
        $mysqli->commit();
        
        // Guardar datos para la factura
        $_SESSION['factura_data'] = [
            'id_venta' => $id_venta,
            'fecha' => date('d/m/Y H:i:s'),
            'cliente' => obtenerNombreCliente($id_cliente),
            'materiales' => $materiales,
            'total' => $total_venta,
            'vendedor' => $_SESSION['nombre_usuario'],
            'metodo_pago' => $metodo_pago
        ];
        
        $_SESSION['exito'] = "Venta de materia prima registrada correctamente. Total: Bs. " . number_format($total_venta, 2);
        header("Location: principal.php?vista=ventas_materia_prima&factura=".$id_venta);
        exit();
        
    } catch (Exception $e) {
        $mysqli->rollback();
        $_SESSION['error'] = "Error al procesar la venta: " . $e->getMessage();
        header("Location: principal.php?vista=ventas_materia_prima");
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
    return $row['nombre'];
}

// Obtener lista de materiales para el autocompletado
$materiales = [];
$result = $mysqli->query("SELECT id_plancha, tipo_material, grosor, cantidad, 
                         CASE 
                             WHEN tipo_material = 'inox' THEN 8500 
                             WHEN tipo_material = 'aluminio' THEN 4500 
                             ELSE 3050 
                         END as precio
                         FROM almacen_materia_prima WHERE cantidad > 0");
while ($row = $result->fetch_assoc()) {
    $row['nombre'] = "Plancha de " . ucfirst($row['tipo_material']) . " (" . $row['grosor'] . "mm)";
    $materiales[] = $row;
}

// Obtener lista de clientes
$clientes = [];
$result = $mysqli->query("SELECT id_cliente, CONCAT(nombres, ' ', apellidos) as nombre, nit_ci FROM clientes ORDER BY nombre");
while ($row = $result->fetch_assoc()) {
    $clientes[] = $row;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Venta de Materia Prima | Metal CNC</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        /* Estilos similares al módulo de productos (puedes reutilizar los mismos) */
        :root {
            --primary: #7209b7;
            --primary-hover: #5a098f;
            --secondary: #4361ee;
            --accent: #4895ef;
            --danger: #e63946;
            --success: #4cc9f0;
            --warning: #f8961e;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --gray-light: #e9ecef;
            --border-radius: 8px;
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
            gap: 20px;
            padding: 20px;
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
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }

        input, select, textarea {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 14px;
            width: 100%;
            transition: var(--transition);
            font-family: 'Poppins', sans-serif;
        }

        input:focus, select:focus, textarea:focus {
            border-color: var(--accent);
            outline: none;
            box-shadow: 0 0 0 3px rgba(72, 149, 239, 0.2);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 14px;
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
        }

        .btn-success {
            background-color: var(--success);
            color: white;
        }

        .btn-success:hover {
            background-color: #3ab7de;
        }

        .btn-danger {
            background-color: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background-color: #d62839;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
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
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        table th {
            background-color: var(--primary);
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
        }

        table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--gray-light);
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

        /* Autocompletado */
        .autocomplete {
            position: relative;
        }

        .autocomplete-items {
            position: absolute;
            border: 1px solid #d4d4d4;
            border-bottom: none;
            border-top: none;
            z-index: 99;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 200px;
            overflow-y: auto;
        }

        .autocomplete-items div {
            padding: 10px;
            cursor: pointer;
            background-color: #fff;
            border-bottom: 1px solid #d4d4d4;
        }

        .autocomplete-items div:hover {
            background-color: #e9e9e9;
        }

        .autocomplete-active {
            background-color: var(--primary) !important;
            color: #ffffff;
        }

        /* Resumen de venta */
        .resumen-venta {
            background: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 15px;
            margin-top: 20px;
        }

        .resumen-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .resumen-total {
            font-weight: bold;
            font-size: 1.2rem;
            color: var(--primary);
            border-top: 1px solid #ddd;
            padding-top: 10px;
            margin-top: 10px;
        }

        /* Factura */
        .factura-container {
            display: none;
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-top: 20px;
        }

        .factura-header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 20px;
        }

        .factura-body table {
            width: 100%;
            margin-bottom: 20px;
        }

        .factura-footer {
            margin-top: 30px;
            text-align: right;
            border-top: 2px solid var(--primary);
            padding-top: 20px;
        }

        @media print {
            body * {
                visibility: hidden;
            }
            .factura-container, .factura-container * {
                visibility: visible;
            }
            .factura-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none;
            }
        }

        /* Estilos específicos para materia prima */
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-inox {
            background-color: #a8dadc;
            color: #1d3557;
        }

        .badge-aluminio {
            background-color: #f1faee;
            color: #1b4332;
        }

        .badge-acero {
            background-color: #ffd166;
            color: #6a040f;
        }

        @media (max-width: 992px) {
            .container {
                flex-direction: column;
            }
            
            .panel {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="panel left-panel">
            <h2><i class="fas fa-cubes"></i> Venta de Materia Prima</h2>
            
            <form id="form-venta" method="POST">
                <div class="form-group">
                    <label for="buscar-cliente">Buscar Cliente</label>
                    <input type="text" id="buscar-cliente" placeholder="Nombre, apellido o NIT/CI">
                    <input type="hidden" id="id_cliente" name="id_cliente">
                </div>
                
                <div class="form-group">
                    <label for="buscar-material">Buscar Material</label>
                    <div class="autocomplete">
                        <input type="text" id="buscar-material" placeholder="Tipo de material o grosor">
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table id="tabla-materiales">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Grosor</th>
                                <th>Precio Unit.</th>
                                <th>Cantidad</th>
                                <th>Subtotal</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="materiales-seleccionados">
                            <!-- Materiales agregados aparecerán aquí -->
                        </tbody>
                    </table>
                </div>
                
                <div class="resumen-venta">
                    <div class="resumen-item">
                        <span>Subtotal:</span>
                        <span id="subtotal">Bs. 0.00</span>
                    </div>
                    <div class="resumen-total">
                        <span>TOTAL:</span>
                        <span id="total">Bs. 0.00</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="metodo_pago">Método de Pago</label>
                    <select id="metodo_pago" name="metodo_pago" required>
                        <option value="efectivo">Efectivo</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="tarjeta">Tarjeta</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="observaciones">Observaciones</label>
                    <textarea id="observaciones" name="observaciones" rows="2"></textarea>
                </div>
                
                <div class="button-group">
                    <button type="button" id="limpiar-venta" class="btn btn-danger">
                        <i class="fas fa-trash-alt"></i> Limpiar
                    </button>
                    <button type="submit" name="finalizar_venta" class="btn btn-success">
                        <i class="fas fa-check-circle"></i> Finalizar Venta
                    </button>
                </div>
                
                <input type="hidden" id="materiales_json" name="materiales_json">
                <input type="hidden" id="total_venta" name="total_venta">
            </form>
            
            <?php if (isset($_GET['factura'])): ?>
                <div class="factura-container" id="factura">
                    <div class="factura-header">
                        <h2>METAL CNC</h2>
                        <p>Calle Principal #123, Ciudad</p>
                        <p>Teléfono: 12345678 | NIT: 123456789</p>
                        <h3>FACTURA #<?php echo $_GET['factura']; ?></h3>
                    </div>
                    
                    <div class="factura-body">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                            <div>
                                <p><strong>Cliente:</strong> <?php echo $_SESSION['factura_data']['cliente']; ?></p>
                            </div>
                            <div>
                                <p><strong>Fecha:</strong> <?php echo $_SESSION['factura_data']['fecha']; ?></p>
                                <p><strong>Vendedor:</strong> <?php echo $_SESSION['factura_data']['vendedor']; ?></p>
                            </div>
                        </div>
                        
                        <table>
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th>Tipo</th>
                                    <th>Precio Unit.</th>
                                    <th>Cantidad</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_SESSION['factura_data']['materiales'] as $material): ?>
                                    <tr>
                                        <td>Plancha <?php echo ucfirst($material['tipo']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $material['tipo']; ?>">
                                                <?php echo strtoupper($material['tipo']); ?>
                                            </span>
                                        </td>
                                        <td>Bs. <?php echo number_format($material['precio'], 2); ?></td>
                                        <td><?php echo $material['cantidad']; ?></td>
                                        <td>Bs. <?php echo number_format($material['cantidad'] * $material['precio'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="factura-footer">
                        <p><strong>Método de Pago:</strong> <?php echo ucfirst($_SESSION['factura_data']['metodo_pago']); ?></p>
                        <h3>TOTAL: Bs. <?php echo number_format($_SESSION['factura_data']['total'], 2); ?></h3>
                        <p>¡Gracias por su compra!</p>
                    </div>
                    
                    <div class="button-group no-print">
                        <button onclick="window.print()" class="btn btn-primary">
                            <i class="fas fa-print"></i> Imprimir Factura
                        </button>
                        <button onclick="document.getElementById('factura').style.display='none'" class="btn btn-danger">
                            <i class="fas fa-times"></i> Cerrar
                        </button>
                    </div>
                </div>
                
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        document.getElementById('factura').style.display = 'block';
                    });
                </script>
                
                <?php unset($_SESSION['factura_data']); ?>
            <?php endif; ?>
        </div>
        
        <div class="panel right-panel">
            <h2><i class="fas fa-info-circle"></i> Información</h2>
            <p>Seleccione un cliente y agregue materiales para realizar una venta.</p>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Material</th>
                            <th>Grosor</th>
                            <th>Stock</th>
                            <th>Precio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materiales as $material): ?>
                            <tr>
                                <td><?php echo ucfirst($material['tipo_material']); ?></td>
                                <td><?php echo $material['grosor']; ?> mm</td>
                                <td><?php echo $material['cantidad']; ?></td>
                                <td>Bs. <?php echo number_format($material['precio'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Datos para autocompletado
        const clientes = <?php echo json_encode($clientes); ?>;
        const materiales = <?php echo json_encode($materiales); ?>;
        
        // Variables globales
        let materialesSeleccionados = [];
        
        // Autocompletado para clientes
        document.getElementById('buscar-cliente').addEventListener('input', function() {
            const input = this.value.toLowerCase();
            const dropdown = document.createElement('div');
            dropdown.className = 'autocomplete-items';
            
            clientes.forEach(cliente => {
                const displayText = `${cliente.nombre} - ${cliente.nit_ci}`;
                if (displayText.toLowerCase().includes(input)) {
                    const item = document.createElement('div');
                    item.innerHTML = `<strong>${cliente.nombre}</strong> <small>${cliente.nit_ci}</small>`;
                    item.addEventListener('click', function() {
                        document.getElementById('buscar-cliente').value = displayText;
                        document.getElementById('id_cliente').value = cliente.id_cliente;
                        dropdown.remove();
                    });
                    dropdown.appendChild(item);
                }
            });
            
            // Limpiar dropdowns anteriores
            const oldDropdown = document.querySelector('.autocomplete-items');
            if (oldDropdown) oldDropdown.remove();
            
            if (dropdown.children.length > 0) {
                this.parentNode.appendChild(dropdown);
            }
        });
        
        // Autocompletado para materiales
        document.getElementById('buscar-material').addEventListener('input', function() {
            const input = this.value.toLowerCase();
            const dropdown = document.querySelector('.autocomplete .autocomplete-items') || 
                            document.createElement('div');
            dropdown.className = 'autocomplete-items';
            dropdown.innerHTML = '';
            
            materiales.forEach(material => {
                const displayText = `${material.nombre}`;
                if (displayText.toLowerCase().includes(input)) {
                    const item = document.createElement('div');
                    item.innerHTML = `
                        <strong>${material.nombre}</strong> 
                        <small>Bs. ${material.precio.toFixed(2)} (Stock: ${material.cantidad})</small>
                    `;
                    item.addEventListener('click', function() {
                        agregarMaterial(material);
                        document.getElementById('buscar-material').value = '';
                        dropdown.remove();
                    });
                    dropdown.appendChild(item);
                }
            });
            
            // Limpiar dropdowns anteriores
            const oldDropdown = document.querySelector('.autocomplete .autocomplete-items');
            if (oldDropdown) oldDropdown.remove();
            
            if (dropdown.children.length > 0) {
                document.querySelector('.autocomplete').appendChild(dropdown);
            }
        });
        
        // Agregar material a la lista
        function agregarMaterial(material) {
            // Verificar si ya está agregado
            const index = materialesSeleccionados.findIndex(m => m.id === material.id_plancha);
            
            if (index >= 0) {
                // Si ya existe, aumentar cantidad si hay stock
                if (materialesSeleccionados[index].cantidad < material.cantidad) {
                    materialesSeleccionados[index].cantidad += 1;
                } else {
                    alert('No hay suficiente stock disponible');
                    return;
                }
            } else {
                // Agregar nuevo material
                materialesSeleccionados.push({
                    id: material.id_plancha,
                    nombre: material.nombre,
                    tipo: material.tipo_material,
                    grosor: material.grosor,
                    precio: material.precio,
                    cantidad: 1,
                    stock: material.cantidad
                });
            }
            
            actualizarTablaMateriales();
        }
        
        // Actualizar tabla de materiales
        function actualizarTablaMateriales() {
            const tbody = document.getElementById('materiales-seleccionados');
            tbody.innerHTML = '';
            
            let subtotal = 0;
            
            materialesSeleccionados.forEach((material, index) => {
                const row = document.createElement('tr');
                const materialTotal = material.precio * material.cantidad;
                subtotal += materialTotal;
                
                row.innerHTML = `
                    <td>${material.nombre}</td>
                    <td>${material.grosor} mm</td>
                    <td>Bs. ${material.precio.toFixed(2)}</td>
                    <td>
                        <input type="number" min="1" max="${material.stock}" value="${material.cantidad}" 
                               onchange="actualizarCantidad(${index}, this.value)" style="width: 60px;">
                    </td>
                    <td>Bs. ${materialTotal.toFixed(2)}</td>
                    <td class="actions-cell">
                        <button onclick="eliminarMaterial(${index})" class="btn btn-danger btn-sm">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </td>
                `;
                
                tbody.appendChild(row);
            });
            
            // Actualizar totales
            document.getElementById('subtotal').textContent = `Bs. ${subtotal.toFixed(2)}`;
            document.getElementById('total').textContent = `Bs. ${subtotal.toFixed(2)}`;
            
            // Actualizar campos ocultos para el formulario
            document.getElementById('materiales_json').value = JSON.stringify(materialesSeleccionados);
            document.getElementById('total_venta').value = subtotal;
        }
        
        // Actualizar cantidad de un material
        function actualizarCantidad(index, nuevaCantidad) {
            nuevaCantidad = parseInt(nuevaCantidad);
            if (nuevaCantidad > 0 && nuevaCantidad <= materialesSeleccionados[index].stock) {
                materialesSeleccionados[index].cantidad = nuevaCantidad;
                actualizarTablaMateriales();
            } else {
                alert('Cantidad no válida o excede el stock disponible');
                // Restaurar valor anterior
                document.querySelector(`#materiales-seleccionados tr:nth-child(${index+1}) input`).value = materialesSeleccionados[index].cantidad;
            }
        }
        
        // Eliminar material de la lista
        function eliminarMaterial(index) {
            materialesSeleccionados.splice(index, 1);
            actualizarTablaMateriales();
        }
        
        // Limpiar toda la venta
        document.getElementById('limpiar-venta').addEventListener('click', function() {
            if (confirm('¿Está seguro de limpiar toda la venta?')) {
                materialesSeleccionados = [];
                document.getElementById('buscar-cliente').value = '';
                document.getElementById('id_cliente').value = '';
                document.getElementById('metodo_pago').value = 'efectivo';
                document.getElementById('observaciones').value = '';
                actualizarTablaMateriales();
            }
        });
        
        // Validar formulario antes de enviar
        document.getElementById('form-venta').addEventListener('submit', function(e) {
            if (materialesSeleccionados.length === 0) {
                e.preventDefault();
                alert('Debe agregar al menos un material');
                return;
            }
            
            if (!document.getElementById('id_cliente').value) {
                e.preventDefault();
                alert('Debe seleccionar un cliente');
                return;
            }
        });
    </script>
</body>
</html>