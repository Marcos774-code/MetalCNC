<?php
require_once('db_connection.php');

// 1. Manejar Solicitudes AJAX
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'obtener_precio_material':
            $material_id = intval($_GET['id']);
            $sql = "SELECT precio FROM materiales WHERE id = $material_id";
            $resultado = $mysqli->query($sql);
            if ($resultado && $resultado->num_rows > 0) {
                $fila = $resultado->fetch_assoc();
                echo json_encode(['precio' => $fila['precio']]);
            } else {
                echo json_encode(['precio' => 0]);
            }
            exit;
            
        case 'obtener_costos_golpe':
            $espesor_id = intval($_GET['id']);
            $sql = "SELECT id, costo_1, costo_2 FROM costos_golpe WHERE espesor_id = $espesor_id";
            $resultado = $mysqli->query($sql);
            if ($resultado && $resultado->num_rows > 0) {
                $fila = $resultado->fetch_assoc();
                echo json_encode([
                    'id' => $fila['id'],
                    'costo_1' => $fila['costo_1'],
                    'costo_2' => $fila['costo_2']
                ]);
            } else {
                echo json_encode([]);
            }
            exit;
            
        case 'guardar_precio_material':
            $data = json_decode(file_get_contents('php://input'), true);
            $material_id = intval($data['id']);
            $nuevo_precio = floatval($data['precio']);
            $sql = "UPDATE materiales SET precio = $nuevo_precio WHERE id = $material_id";
            echo json_encode(['success' => $mysqli->query($sql)]);
            exit;
            
        case 'guardar_costos_golpe':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = intval($data['id']);
            $espesor_id = intval($data['espesor_id']);
            $costo_1 = floatval($data['costo_1']);
            $costo_2 = floatval($data['costo_2']);
            
            if ($id) {
                // Actualizar registro existente
                $sql = "UPDATE costos_golpe SET costo_1 = $costo_1, costo_2 = $costo_2 WHERE id = $id";
            } else {
                // Crear nuevo registro
                $sql = "INSERT INTO costos_golpe (espesor_id, costo_1, costo_2) VALUES ($espesor_id, $costo_1, $costo_2)";
            }
            
            if ($mysqli->query($sql)) {
                $nuevo_id = $id ? $id : $mysqli->insert_id;
                echo json_encode(['success' => true, 'id' => $nuevo_id]);
            } else {
                echo json_encode(['success' => false]);
            }
            exit;
            
        case 'buscar_cotizaciones':
            $termino = $mysqli->real_escape_string($_GET['q']);
            $sql = "SELECT c.id, c.fecha_creacion, c.numero_piezas, c.costo_total, c.peso_pieza, c.notas,
                    m.nombre as material, 
                    CONCAT(cl.nombres, ' ', cl.apellidos) as cliente_nombre
                    FROM cotizaciones c 
                    JOIN materiales m ON c.material_id = m.id 
                    LEFT JOIN clientes cl ON c.cliente_id = cl.id_cliente
                    WHERE cl.nombres LIKE '%$termino%' OR cl.apellidos LIKE '%$termino%' OR m.nombre LIKE '%$termino%'
                    ORDER BY c.fecha_creacion DESC 
                    LIMIT 50";
            $resultado = $mysqli->query($sql);
            $cotizaciones = [];
            
            if ($resultado && $resultado->num_rows > 0) {
                while ($fila = $resultado->fetch_assoc()) {
                    $cotizaciones[] = $fila;
                }
            }
            
            echo json_encode($cotizaciones);
            exit;
            
        case 'obtener_detalle_cotizacion':
            $cotizacion_id = intval($_GET['id']);
            $sql = "SELECT c.*, m.nombre as material_nombre, e.nombre as espesor_nombre, 
                    cl.nombres as cliente_nombre, cl.apellidos as cliente_apellido
                    FROM cotizaciones c
                    JOIN materiales m ON c.material_id = m.id
                    JOIN espesores e ON c.espesor_id = e.id
                    LEFT JOIN clientes cl ON c.cliente_id = cl.id_cliente
                    WHERE c.id = $cotizacion_id";
            $resultado = $mysqli->query($sql);
            if ($resultado && $resultado->num_rows > 0) {
                $cotizacion = $resultado->fetch_assoc();
                
                // Calcular valores
                $precio_mat_pieza = ($cotizacion['desarrollo'] / 1000) * ($cotizacion['largo'] / 1000) * ($cotizacion['precio_material'] / 3.6);
                $costo_perdida = $precio_mat_pieza * ($cotizacion['perdida_material'] / 100);
                $total_mat_pieza = $precio_mat_pieza + $costo_perdida;
                $costo_trabajo_pieza = $cotizacion['golpes_pieza'] * $cotizacion['costo_golpe'];
                
                echo json_encode([
                    'success' => true,
                    'cotizacion' => $cotizacion,
                    'detalle_calculo' => [
                        'precio_material_pieza' => $precio_mat_pieza,
                        'costo_perdida' => $costo_perdida,
                        'total_material_pieza' => $total_mat_pieza,
                        'costo_trabajo_pieza' => $costo_trabajo_pieza
                    ]
                ]);
            } else {
                echo json_encode(['success' => false]);
            }
            exit;
    }
}

// 2. Procesar el Formulario Cuando se Envía
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Obtener datos del formulario
    $material_id = intval($_POST['material']);
    $espesor_id = intval($_POST['espesor']);
    $costo_golpe_opcion = intval($_POST['costo_golpe']); // 1 o 2
    $precio_material = floatval($_POST['precio_material']);
    $desarrollo = floatval($_POST['desarrollo']);
    $largo = floatval($_POST['largo']);
    $golpes_pieza = intval($_POST['golpes_pieza']);
    $perdida_material = floatval($_POST['perdida_material']);
    $numero_piezas = intval($_POST['numero_piezas']);
    $cliente_id = !empty($_POST['cliente']) ? intval($_POST['cliente']) : null;
    $notas = $mysqli->real_escape_string($_POST['notas'] ?? '');

    // Obtener el costo real según la opción seleccionada
    $sql_costos = "SELECT costo_1, costo_2 FROM costos_golpe WHERE espesor_id = $espesor_id";
    $resultado_costos = $mysqli->query($sql_costos);
    if ($resultado_costos && $resultado_costos->num_rows > 0) {
        $fila_costos = $resultado_costos->fetch_assoc();
        $costo_golpe = ($costo_golpe_opcion == 1) ? $fila_costos['costo_1'] : $fila_costos['costo_2'];
    } else {
        $error = "No se encontraron costos para el espesor seleccionado.";
    }

    // Validar campos requeridos
    if ($material_id && $espesor_id && isset($costo_golpe) && $precio_material) {
        // Obtener detalles del material
        $sql_material = "SELECT grosor, densidad FROM materiales WHERE id = $material_id";
        $resultado_material = $mysqli->query($sql_material);
        $material = $resultado_material->fetch_assoc();

        // Calcular el costo total
        $precio_mat_pieza = ($desarrollo / 1000) * ($largo / 1000) * ($precio_material / 3.6);
        $costo_perdida = $precio_mat_pieza * ($perdida_material / 100);
        $total_mat_pieza = $precio_mat_pieza + $costo_perdida;
        $costo_trabajo_pieza = $golpes_pieza * $costo_golpe;
        $costo_total_pieza = $total_mat_pieza + $costo_trabajo_pieza;
        $costo_total = $costo_total_pieza * $numero_piezas;

        // Calcular el peso de la pieza
        $volumen = ($desarrollo / 1000) * ($largo / 1000) * ($material['grosor'] / 1000);
        $peso_pieza = $volumen * $material['densidad'];

        // Guardar la cotización en la base de datos
        $sql = "INSERT INTO cotizaciones (
                material_id, espesor_id, desarrollo, largo, golpes_pieza, 
                perdida_material, costo_total, peso_pieza, numero_piezas, 
                cliente_id, notas, costo_golpe, precio_material
            ) VALUES (
                $material_id, $espesor_id, $desarrollo, $largo, $golpes_pieza, 
                $perdida_material, $costo_total, $peso_pieza, $numero_piezas, 
                " . ($cliente_id ? "$cliente_id" : "NULL") . ", '$notas', $costo_golpe, $precio_material
            )";
            
        if ($mysqli->query($sql)) {
            $cotizacion_id = $mysqli->insert_id;
            $mensaje = "Cotización guardada correctamente.";
            
            $resultados = [
                'total_material' => $total_mat_pieza * $numero_piezas,
                'total_mano_obra' => $costo_trabajo_pieza * $numero_piezas,
                'costo_total_pieza' => $costo_total,
                'peso_pieza' => $peso_pieza * $numero_piezas,
                'cotizacion_id' => $cotizacion_id
            ];
        } else {
            $error = "Error al guardar la cotización: " . $mysqli->error;
        }
    } else {
        $error = "Por favor, complete todos los campos requeridos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotizador de Trabajos - MetalCNC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
        }
        .header {
            margin-bottom: 30px;
            text-align: center;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .result-card {
            margin: 20px auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            max-width: 600px;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .result-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
        }
        .result-label {
            font-weight: 600;
            color: #495057;
        }
        .result-value {
            font-weight: 500;
            color: #212529;
        }
        .total-value {
            font-weight: 700;
            color: #dc3545;
        }
        .action-buttons {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .tab-content {
            padding: 20px;
            background-color: white;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .nav-tabs {
            border-bottom: none;
        }
        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            color: #0d6efd;
            border-bottom: 3px solid #0d6efd;
            background-color: transparent;
        }
        .card-header {
            background-color: #0d6efd;
            color: white;
            font-weight: 500;
        }
        .costos-golpe-container {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
        }
        .costo-input-group {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .costo-input-group label {
            min-width: 120px;
            margin-right: 10px;
            margin-bottom: 0;
        }
        .costo-input-group input, .costo-input-group select {
            flex: 1;
            max-width: 200px;
        }
        table {
            font-size: 14px;
        }
        table th {
            font-weight: 600;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-calculator me-2"></i>Cotizador MetalCNC</h1>
            <p class="mb-4">Sistema de cotización para trabajos de corte y doblado</p>
            
            <?php if (isset($resultados)): ?>
                <div class="card result-card">
                    <div class="card-header">
                        <i class="fas fa-file-invoice-dollar me-2"></i>Resultado de la Cotización #<?= $resultados['cotizacion_id'] ?>
                    </div>
                    <div class="card-body">
                        <div class="result-item">
                            <span class="result-label">Número de Piezas:</span>
                            <span class="result-value"><?= $numero_piezas ?></span>
                        </div>
                        <div class="result-item">
                            <span class="result-label">Total Material:</span>
                            <span class="result-value">Bs <?= number_format($resultados['total_material'], 2) ?></span>
                        </div>
                        <div class="result-item">
                            <span class="result-label">Total Mano de Obra:</span>
                            <span class="result-value">Bs <?= number_format($resultados['total_mano_obra'], 2) ?></span>
                        </div>
                        <div class="result-item">
                            <span class="result-label">Peso Total:</span>
                            <span class="result-value"><?= number_format($resultados['peso_pieza'], 2) ?> kg</span>
                        </div>
                        <div class="result-item">
                            <span class="result-label">Costo Total:</span>
                            <span class="result-value total-value">Bs <?= number_format($resultados['costo_total_pieza'], 2) ?></span>
                        </div>

                        <div class="action-buttons">
                            <button class="btn btn-primary" onclick="nuevaCotizacion()">
                                <i class="fas fa-plus me-2"></i>Nueva Cotización
                            </button>
                            <a href="principal.php?vista=ventas" class="btn btn-danger">
                                <i class="fas fa-sign-out-alt me-2"></i>Salir a Ventas
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="new-tab" data-bs-toggle="tab" data-bs-target="#new" type="button" role="tab">Nueva Cotización</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab">Historial</button>
            </li>
        </ul>

        <div class="tab-content" id="myTabContent">
            <div class="tab-pane fade show active" id="new" role="tabpanel">
                <form action="Cotizador.php" method="post" id="cotizacionForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-box-open me-2"></i>Información del Material
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="material" class="form-label">Material:</label>
                                        <select class="form-select" name="material" id="material" onchange="actualizarPrecio()" required>
                                            <option value="">Seleccione un material</option>
                                            <?php
                                            $sql = "SELECT id, nombre, tipo, precio FROM materiales";
                                            $resultado = $mysqli->query($sql);

                                            if ($resultado && $resultado->num_rows > 0) {
                                                while ($fila = $resultado->fetch_assoc()) {
                                                    echo "<option value='".$fila["id"]."'>".$fila["nombre"]." (".$fila["tipo"].")</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="precio_material" class="form-label">Precio del Material (Bs):</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="precio_material" id="precio_material" step="0.01" required>
                                            <button type="button" class="btn btn-success" onclick="guardarPrecio()">
                                                <i class="fas fa-save me-1"></i>Guardar
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="espesor" class="form-label">Espesor:</label>
                                        <select class="form-select" name="espesor" id="espesor" onchange="actualizarCostoGolpe()" required>
                                            <option value="">Seleccione un espesor</option>
                                            <?php
                                            $sql = "SELECT id, nombre FROM espesores";
                                            $resultado = $mysqli->query($sql);

                                            if ($resultado && $resultado->num_rows > 0) {
                                                while ($fila = $resultado->fetch_assoc()) {
                                                    echo "<option value='".$fila["id"]."'>".$fila["nombre"]."</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Costos por Golpe (Bs):</label>
                                        <div class="costos-golpe-container">
                                            <div class="costo-input-group">
                                                <label for="costo_golpe">Costo a utilizar:</label>
                                                <select class="form-control" name="costo_golpe" id="costo_golpe" required>
                                                    <option value="">Seleccione una opción</option>
                                                    <option value="1" id="opcion1">Opción 1: Bs <span id="valor1">0.00</span></option>
                                                    <option value="2" id="opcion2">Opción 2: Bs <span id="valor2">0.00</span></option>
                                                </select>
                                            </div>
                                            <div class="costo-input-group mt-3">
                                                <label for="costo_golpe_1">Editar Opción 1:</label>
                                                <input type="number" class="form-control" id="costo_golpe_1" step="0.01" placeholder="Costo 1">
                                            </div>
                                            <div class="costo-input-group">
                                                <label for="costo_golpe_2">Editar Opción 2:</label>
                                                <input type="number" class="form-control" id="costo_golpe_2" step="0.01" placeholder="Costo 2">
                                            </div>
                                            <button type="button" class="btn btn-warning mt-2" onclick="guardarCostosGolpe()">
                                                <i class="fas fa-save me-1"></i>Guardar Costos
                                            </button>
                                            <input type="hidden" id="costoGolpeId">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-ruler-combined me-2"></i>Medidas y Cantidades
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="desarrollo" class="form-label">Desarrollo (mm):</label>
                                        <input type="number" class="form-control" name="desarrollo" id="desarrollo" step="0.01" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="largo" class="form-label">Largo (mm):</label>
                                        <input type="number" class="form-control" name="largo" id="largo" step="0.01" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="golpes_pieza" class="form-label">Golpes de Pieza:</label>
                                        <input type="number" class="form-control" name="golpes_pieza" id="golpes_pieza" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="perdida_material" class="form-label">% Pérdida de Material:</label>
                                        <input type="number" class="form-control" name="perdida_material" id="perdida_material" step="0.01" value="5" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="numero_piezas" class="form-label">Número de Piezas:</label>
                                        <input type="number" class="form-control" name="numero_piezas" id="numero_piezas" min="1" value="1" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-info-circle me-2"></i>Información Adicional
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="cliente" class="form-label">Cliente (Opcional):</label>
                                        <select class="form-select" name="cliente" id="cliente">
                                            <option value="">Seleccione un cliente</option>
                                            <?php
                                            $sql = "SELECT id_cliente, nombres, apellidos FROM clientes ORDER BY apellidos, nombres";
                                            $resultado = $mysqli->query($sql);
                                            
                                            if ($resultado && $resultado->num_rows > 0) {
                                                while ($fila = $resultado->fetch_assoc()) {
                                                    echo "<option value='".$fila["id_cliente"]."'>".$fila["apellidos"].", ".$fila["nombres"]."</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="notas" class="form-label">Notas (Opcional):</label>
                                        <textarea class="form-control" name="notas" id="notas" rows="2" placeholder="Notas adicionales"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-calculator me-2"></i>Calcular Cotización
                        </button>
                    </div>
                </form>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger mt-4">
                        <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tab-pane fade" id="history" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-history me-2"></i>Historial de Cotizaciones
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Cliente</th>
                                        <th>Material</th>
                                        <th>Piezas</th>
                                        <th>Total</th>
                                        <th>Descripción</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaHistorial">
                                    <?php
                                    $sql = "SELECT c.id, c.fecha_creacion, c.numero_piezas, c.costo_total, c.notas,
                                            m.nombre as material, 
                                            CONCAT(cl.nombres, ' ', cl.apellidos) as cliente_nombre
                                            FROM cotizaciones c 
                                            JOIN materiales m ON c.material_id = m.id 
                                            LEFT JOIN clientes cl ON c.cliente_id = cl.id_cliente
                                            ORDER BY c.fecha_creacion DESC 
                                            LIMIT 20";
                                    $resultado = $mysqli->query($sql);

                                    if ($resultado && $resultado->num_rows > 0) {
                                        while ($fila = $resultado->fetch_assoc()) {
                                            echo "<tr>
                                                    <td>".date('d/m/Y', strtotime($fila['fecha_creacion']))."</td>
                                                    <td>".($fila['cliente_nombre'] ? $fila['cliente_nombre'] : '-')."</td>
                                                    <td>".$fila['material']."</td>
                                                    <td>".$fila['numero_piezas']."</td>
                                                    <td>Bs ".number_format($fila['costo_total'], 2)."</td>
                                                    <td>".($fila['notas'] ? $fila['notas'] : '-')."</td>
                                                  </tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='6' class='text-center'>No hay cotizaciones recientes</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para actualizar el precio del material
        function actualizarPrecio() {
            var materialId = document.getElementById('material').value;
            if (materialId) {
                fetch('Cotizador.php?action=obtener_precio_material&id=' + materialId)
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('precio_material').value = data.precio;
                    });
            } else {
                document.getElementById('precio_material').value = '';
            }
        }

        // Función para actualizar los costos por golpe
        function actualizarCostoGolpe() {
            var espesorId = document.getElementById('espesor').value;
            if (espesorId) {
                fetch('Cotizador.php?action=obtener_costos_golpe&id=' + espesorId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.id) {
                            // Actualizar campos de edición
                            document.getElementById('costo_golpe_1').value = data.costo_1;
                            document.getElementById('costo_golpe_2').value = data.costo_2;
                            
                            // Actualizar opciones del select
                            document.getElementById('valor1').textContent = data.costo_1;
                            document.getElementById('valor2').textContent = data.costo_2;
                            document.getElementById('opcion1').textContent = 'Opción 1: Bs ' + data.costo_1;
                            document.getElementById('opcion2').textContent = 'Opción 2: Bs ' + data.costo_2;
                            
                            // Guardar ID para futuras actualizaciones
                            document.getElementById('costoGolpeId').value = data.id;
                            
                            // Seleccionar la primera opción por defecto
                            document.getElementById('costo_golpe').value = "1";
                        } else {
                            // Si no hay costos, limpiar campos
                            document.getElementById('costo_golpe_1').value = '';
                            document.getElementById('costo_golpe_2').value = '';
                            document.getElementById('costoGolpeId').value = '';
                            document.getElementById('costo_golpe').innerHTML = `
                                <option value="">Seleccione una opción</option>
                                <option value="1" id="opcion1">Opción 1: Bs <span id="valor1">0.00</span></option>
                                <option value="2" id="opcion2">Opción 2: Bs <span id="valor2">0.00</span></option>
                            `;
                        }
                    });
            } else {
                document.getElementById('costo_golpe_1').value = '';
                document.getElementById('costo_golpe_2').value = '';
                document.getElementById('costoGolpeId').value = '';
                document.getElementById('costo_golpe').innerHTML = `
                    <option value="">Seleccione una opción</option>
                    <option value="1" id="opcion1">Opción 1: Bs <span id="valor1">0.00</span></option>
                    <option value="2" id="opcion2">Opción 2: Bs <span id="valor2">0.00</span></option>
                `;
            }
        }

        // Función para guardar el nuevo precio del material
        function guardarPrecio() {
            var materialId = document.getElementById('material').value;
            var nuevoPrecio = document.getElementById('precio_material').value;

            if (materialId && nuevoPrecio) {
                if (confirm('¿Está seguro de actualizar el precio de este material?')) {
                    fetch('Cotizador.php?action=guardar_precio_material', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id: materialId,
                            precio: nuevoPrecio
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Precio actualizado correctamente.');
                        } else {
                            alert('Error al actualizar el precio.');
                        }
                    });
                }
            } else {
                alert('Seleccione un material e ingrese un precio válido.');
            }
        }

        // Función para guardar los costos por golpe
        function guardarCostosGolpe() {
            var id = document.getElementById('costoGolpeId').value;
            var costo1 = document.getElementById('costo_golpe_1').value;
            var costo2 = document.getElementById('costo_golpe_2').value;
            var espesorId = document.getElementById('espesor').value;

            if (!espesorId) {
                alert('Por favor seleccione un espesor primero.');
                return;
            }

            if (!costo1 || !costo2) {
                alert('Por favor complete ambos costos.');
                return;
            }

            if (confirm('¿Está seguro de actualizar los costos por golpe?')) {
                fetch('Cotizador.php?action=guardar_costos_golpe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: id,
                        espesor_id: espesorId,
                        costo_1: costo1,
                        costo_2: costo2
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Costos actualizados correctamente.');
                        // Actualizar la visualización
                        document.getElementById('valor1').textContent = costo1;
                        document.getElementById('valor2').textContent = costo2;
                        document.getElementById('opcion1').textContent = 'Opción 1: Bs ' + costo1;
                        document.getElementById('opcion2').textContent = 'Opción 2: Bs ' + costo2;
                        
                        if (data.id) {
                            document.getElementById('costoGolpeId').value = data.id;
                        }
                    } else {
                        alert('Error al actualizar los costos.');
                    }
                });
            }
        }

        // Función para crear nueva cotización
        function nuevaCotizacion() {
            document.getElementById('cotizacionForm').reset();
            window.location.href = 'Cotizador.php';
        }

        // Validación del formulario antes de enviar
        document.getElementById('cotizacionForm').addEventListener('submit', function(e) {
            const material = document.getElementById('material').value;
            const espesor = document.getElementById('espesor').value;
            const costoSeleccionado = document.getElementById('costo_golpe').value;
            
            if (!material || !espesor || !costoSeleccionado) {
                e.preventDefault();
                alert('Por favor complete todos los campos requeridos.');
            }
        });

        // Cargar costos cuando se selecciona un espesor
        document.getElementById('espesor').addEventListener('change', actualizarCostoGolpe);
    </script>
</body>
</html>

