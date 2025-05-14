CREATE DATABASE IF NOT EXISTS Metalcnc;
USE Metalcnc;

-- Tabla de usuarios
CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre_usuario VARCHAR(50) NOT NULL,
    contrasena VARCHAR(200) NOT NULL,
    rol ENUM('gerente', 'ventas', 'almacen', 'contabilidad') NOT NULL,
    estado BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO usuarios (nombre_usuario, contrasena, rol, estado) VALUES
('jonathan', '1234', 'gerente', TRUE),
('alfredo', '1234', 'almacen', TRUE),
('calle', '1234', 'ventas', TRUE),
('soliz', '1234', 'contabilidad', TRUE);

-- Tabla de clientes
CREATE TABLE clientes (
    id_cliente INT AUTO_INCREMENT PRIMARY KEY,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    nit_ci VARCHAR(20) NOT NULL,
    telefono VARCHAR(20),
    correo VARCHAR(100),
    direccion VARCHAR(200)
);

-- Tabla de ventas
CREATE TABLE ventas (
    codigo_venta INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL,
    id_usuario INT NOT NULL,
    fecha_venta DATETIME NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE ON UPDATE CASCADE
);

-- Tabla de detalle de ventas
CREATE TABLE detalle_venta (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    codigo_venta INT NOT NULL,
    id_producto INT NOT NULL,
    cantidad INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (codigo_venta) REFERENCES ventas(codigo_venta) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_producto) REFERENCES almacen_productos(id_producto) ON DELETE CASCADE ON UPDATE CASCADE
);

-- Tabla de reportes
CREATE TABLE reportes (
    id_reporte INT AUTO_INCREMENT PRIMARY KEY,
    codigo_venta INT NOT NULL,
    id_usuario INT NOT NULL,
    tipo_reporte ENUM('diario', 'mensual', 'anual') NOT NULL,
    detalle JSON,
    fecha_reporte DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (codigo_venta) REFERENCES ventas(codigo_venta) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE ON UPDATE CASCADE
);

-- Tabla de productos en almacén
CREATE TABLE almacen_productos (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    nombre_producto VARCHAR(100) NOT NULL,
    descripcion TEXT,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    ubicacion VARCHAR(100) NOT NULL
);

-- Tabla de materia prima en almacén
CREATE TABLE almacen_materia_prima (
    id_plancha INT AUTO_INCREMENT PRIMARY KEY,
    tipo_material ENUM('inox', 'aluminio', 'acero') NOT NULL,
    grosor DECIMAL(5,2) NOT NULL CHECK (grosor IN (1.5, 2, 2.5, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15)),
    cantidad INT NOT NULL,
    id_proveedor INT,
    ubicacion VARCHAR(100) NOT NULL,
    FOREIGN KEY (id_proveedor) REFERENCES proveedores(id_proveedor) ON DELETE SET NULL ON UPDATE CASCADE
);

-- Tabla de activos en almacén
CREATE TABLE almacen_activos (
    id_activo INT AUTO_INCREMENT PRIMARY KEY,
    nombre_activo VARCHAR(100) NOT NULL,
    descripcion TEXT,
    cantidad INT NOT NULL,
    tipo_activo ENUM('maquinaria', 'herramienta') NOT NULL,
    ubicacion VARCHAR(100) NOT NULL
);

-- Tabla de proveedores
CREATE TABLE proveedores (
    id_proveedor INT AUTO_INCREMENT PRIMARY KEY,
    razon_social VARCHAR(150) NOT NULL,
    nombres VARCHAR(100),
    apellidos VARCHAR(100),
    descripcion TEXT,
    telefono VARCHAR(20),
    direccion VARCHAR(200)
);

-- Tabla de empleados
CREATE TABLE empleados (
    id_empleado INT AUTO_INCREMENT PRIMARY KEY,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    correo VARCHAR(100),
    cargo VARCHAR(50),
    turno VARCHAR(50),
    salario DECIMAL(10,2) NOT NULL,
    estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo'
);

-- Cotizador

-- Tabla de espesores
CREATE TABLE espesores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL
);

-- Tabla de costos por golpe
CREATE TABLE costos_golpe (
    id INT AUTO_INCREMENT PRIMARY KEY,
    espesor_id INT NOT NULL,
    costo_1 DECIMAL(10,2) NOT NULL,
    costo_2 DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (espesor_id) REFERENCES espesores(id)
);

-- Tabla de materiales
CREATE TABLE materiales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    tipo VARCHAR(50) NOT NULL, 
    precio DECIMAL(10,2) NOT NULL,
    grosor DECIMAL(5,2),
    densidad DECIMAL(10,2),
    descripcion TEXT
);

INSERT INTO espesores (nombre) VALUES
('UNO'), ('DOS'), ('TRES'), ('CUATRO'), ('CINCO'),
('SEIS'), ('OCHO'), ('NUEVE'), ('DIEZ');

INSERT INTO costos_golpe (espesor_id, costo_1, costo_2) VALUES
(1, 6, 8), (2, 8, 10), (3, 10, 12), (4, 12, 14),
(5, 15, 18), (6, 18, 21), (7, 25, 30), (8, 32, 40), (9, 35, 45);

INSERT INTO materiales (nombre, tipo, precio, grosor, densidad, descripcion) VALUES
('Acero al Carbón', 'Acero', 3050.00, 1.5, 7850, 'Acero de bajo carbono, ideal para estructuras.'),
('Acero Inoxidable 304', 'Acero', 8500.00, 2.0, 8000, 'Resistente a la corrosión, uso en ambientes húmedos.'),
('Aluminio 6061', 'Aluminio', 4500.00, 1.2, 2700, 'Aluminio de alta resistencia, uso aeronáutico.'),
('Cobre C110', 'Cobre', 12000.00, 1.0, 8960, 'Excelente conductividad eléctrica y térmica.'),
('Latón C260', 'Latón', 9500.00, 1.5, 8530, 'Aleación de cobre y zinc, uso decorativo.'),
('Zinc', 'Zinc', 7000.00, 1.0, 7140, 'Uso en galvanización y protección contra corrosión.'),
('Bronce', 'Bronce', 11000.00, 2.0, 8800, 'Aleación de cobre y estaño, uso en cojinetes.'),
('Acero Galvanizado', 'Acero', 4000.00, 1.5, 7850, 'Acero recubierto con zinc para mayor durabilidad.');

INSERT INTO clientes (nombres, apellidos, nit_ci, telefono, correo, direccion) VALUES
('Juan', 'Pérez', '12345678 LP', '72000001', 'juan.perez@email.com', 'Calle 1, Zona Central'),
('María', 'Lopez', '87654321 SC', '72000002', 'maria.lopez@email.com', 'Avenida 5, Zona Sur'),
('Carlos', 'González', '11223344 CB', '72000003', 'carlos.gonzalez@email.com', 'Calle 12, Zona Este'),
('Ana', 'Martínez', '99887766 LP', '72000004', 'ana.martinez@email.com', 'Av. Busch, Zona Norte'),
('Pedro', 'Fernández', '55443322 OR', '72000005', 'pedro.fernandez@email.com', 'Calle Bolívar, Centro'),
('Sofía', 'Ramírez', '66778899 PT', '72000006', 'sofia.ramirez@email.com', 'Calle Sucre, Zona Oeste'),
('Luis', 'Herrera', '33445566 CB', '72000007', 'luis.herrera@email.com', 'Calle 3, Zona Sur'),
('Elena', 'Torres', '22113344 SC', '72000008', 'elena.torres@email.com', 'Av. Mariscal, Zona Este'),
('Miguel', 'Vargas', '77889900 LP', '72000009', 'miguel.vargas@email.com', 'Calle Comercio, Centro'),
('Andrea', 'Guzmán', '99001122 CB', '72000010', 'andrea.guzman@email.com', 'Calle 7, Zona Norte');

INSERT INTO proveedores (razon_social, nombres, apellidos, descripcion, telefono, direccion) VALUES
('AceroBol S.A.', 'Roberto', 'Santos', 'Proveedor de acero inoxidable y aluminio', '76000001', 'Av. América #123, Cochabamba'),
('Metalurgia S.R.L.', 'Patricia', 'Vega', 'Especialistas en acero al carbono', '76000002', 'Calle Industrial #456, La Paz'),
('AluTech S.A.', 'Fernando', 'Quispe', 'Distribución de aluminio en láminas', '76000003', 'Zona Sur #789, Santa Cruz'),
('CobreBol Ltda.', 'Claudia', 'Mendoza', 'Proveedor de cobre y bronce', '76000004', 'Av. Sucre #321, Oruro'),
('Industria Metalúrgica Andina', 'Raúl', 'Torrez', 'Venta de metales y galvanizados', '76000005', 'Calle Central #654, Potosí'),
('Hierros y Aceros S.R.L.', 'Isabel', 'Gutiérrez', 'Especialistas en acero estructural', '76000006', 'Av. Bolivia #987, Tarija'),
('Latonera Nacional', 'Javier', 'Peralta', 'Venta de latón y materiales para construcción', '76000007', 'Zona Este #741, Sucre'),
('Metales de Occidente', 'Marina', 'Salazar', 'Proveedora de acero inoxidable', '76000008', 'Zona Norte #852, La Paz'),
('TuboMetal S.A.', 'Ricardo', 'Paredes', 'Venta de tubos metálicos y perfiles', '76000009', 'Av. Santa Cruz #963, Cochabamba'),
('Fundición y Acero Ltda.', 'Teresa', 'Zeballos', 'Distribuidora de acero fundido', '76000010', 'Calle Comercio #159, Oruro');

INSERT INTO empleados (nombres, apellidos, telefono, correo, cargo, turno, salario, estado) VALUES
('Diego', 'Suárez', '78000001', 'diego.suarez@email.com', 'Operador de máquina', 'Mañana', 3500.00, 'activo'),
('Valeria', 'Rojas', '78000002', 'valeria.rojas@email.com', 'Tornero', 'Tarde', 3200.00, 'activo'),
('Gabriel', 'Mamani', '78000003', 'gabriel.mamani@email.com', 'Soldador', 'Mañana', 3600.00, 'activo'),
('Carla', 'Zárate', '78000004', 'carla.zarate@email.com', 'Secretaria', 'Mañana', 2800.00, 'activo'),
('José', 'Vargas', '78000005', 'jose.vargas@email.com', 'Encargado de almacén', 'Tarde', 4000.00, 'activo'),
('Sandra', 'Gonzales', '78000006', 'sandra.gonzales@email.com', 'Supervisora', 'Mañana', 4500.00, 'activo'),
('Daniel', 'Cabrera', '78000007', 'daniel.cabrera@email.com', 'Asistente de contabilidad', 'Mañana', 3300.00, 'activo'),
('Andrea', 'Peñaranda', '78000008', 'andrea.peñaranda@email.com', 'Diseñadora Industrial', 'Tarde', 3800.00, 'activo'),
('Hugo', 'Quintana', '78000009', 'hugo.quintana@email.com', 'Inspector de calidad', 'Mañana', 3700.00, 'activo'),
('Marcela', 'Pacheco', '78000010', 'marcela.pacheco@email.com', 'Recepcionista', 'Tarde', 2700.00, 'activo');

ALTER TABLE clientes ADD COLUMN extension VARCHAR(10) AFTER direccion;
ALTER TABLE usuarios ADD COLUMN foto_perfil VARCHAR(255) AFTER contrasena;

CREATE TABLE cotizaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    espesor_id INT NOT NULL,
    desarrollo DECIMAL(10,2) NOT NULL,
    largo DECIMAL(10,2) NOT NULL,
    golpes_pieza INT NOT NULL,
    perdida_material DECIMAL(5,2) NOT NULL,
    costo_total DECIMAL(12,2) NOT NULL,
    peso_pieza DECIMAL(10,2) NOT NULL,
    numero_piezas INT NOT NULL,
    cliente_id INT,
    notas TEXT,
    costo_golpe DECIMAL(10,2) NOT NULL,
    precio_material DECIMAL(10,2) NOT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (material_id) REFERENCES materiales(id),
    FOREIGN KEY (espesor_id) REFERENCES espesores(id),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id_cliente)
);
-- Agregar campo tipo_material a detalle_venta para identificar ventas de materia prima
ALTER TABLE detalle_venta ADD COLUMN tipo_material VARCHAR(20) AFTER subtotal;

-- Agregar campo id_usuario a la tabla usuarios para identificar al vendedor
ALTER TABLE usuarios ADD COLUMN id_usuario INT AUTO_INCREMENT PRIMARY KEY FIRST;

-- Actualizar los usuarios existentes con IDs
SET @id = 1;
UPDATE usuarios SET id_usuario = @id:=@id+1;
INSERT INTO almacen_materia_prima (tipo_material, grosor, cantidad, id_proveedor, ubicacion) VALUES
('acero', 2.5, 50, 2, 'Zona A, Nivel 1'),
('inox', 1.5, 30, 1, 'Zona B, Nivel 2'),
('aluminio', 3.0, 40, 3, 'Zona C, Nivel 1'),
('acero', 5.0, 25, 2, 'Zona A, Nivel 3'),
('inox', 2.0, 35, 1, 'Zona B, Nivel 1'),
('aluminio', 4.0, 20, 3, 'Zona C, Nivel 2'),
('acero', 3.0, 45, 4, 'Zona A, Nivel 2'),
('inox', 2.5, 28, 5, 'Zona B, Nivel 3'),
('aluminio', 1.5, 38, 6, 'Zona C, Nivel 1'),
('acero', 6.0, 15, 7, 'Zona A, Nivel 4');
INSERT INTO almacen_productos (nombre_producto, descripcion, cantidad, precio_unitario, ubicacion) VALUES
('Soporte metálico para estante', 'Soporte de acero para estantes de 50cm', 25, 45.00, 'Estantería A, Nivel 2'),
('Bandeja de acero inoxidable', 'Bandeja rectangular 30x40cm para cocina', 18, 120.00, 'Estantería B, Nivel 1'),
('Estructura para puerta corrediza', 'Marco metálico para puerta de 2.10m', 8, 320.00, 'Zona de estructuras'),
('Reja de seguridad', 'Reja de protección 1.5x2m con cerradura', 12, 280.00, 'Área de seguridad'),
('Estantería industrial', 'Estante metálico de 5 niveles 2x1m', 6, 550.00, 'Sección de estanterías'),
('Escalera telescópica', 'Escalera de aluminio extensible hasta 3m', 10, 420.00, 'Área de herramientas'),
('Perfil angular de acero', 'Ángulo de 1.5m x 2" x 2"', 35, 38.00, 'Zona de perfiles'),
('Cajón de herramientas', 'Organizador metálico con 5 cajones', 15, 180.00, 'Área de herramientas'),
('Barrera de protección', 'Barrera metálica móvil 1.8m de largo', 9, 210.00, 'Área de seguridad'),
('Mesa de trabajo industrial', 'Mesa de acero 1.5x0.8m con superficie antideslizante', 7, 390.00, 'Sección de muebles');

-- Mejorar la tabla de ventas
ALTER TABLE ventas 
ADD COLUMN subtotal DECIMAL(10,2) NOT NULL AFTER fecha_venta,
ADD COLUMN descuento DECIMAL(5,2) DEFAULT 0 AFTER subtotal,
ADD COLUMN metodo_pago ENUM('efectivo', 'transferencia', 'tarjeta', 'qr') NOT NULL AFTER descuento,
ADD COLUMN monto_pagado DECIMAL(10,2) NOT NULL AFTER metodo_pago,
ADD COLUMN cambio DECIMAL(10,2) DEFAULT 0 AFTER monto_pagado,
ADD COLUMN observaciones TEXT AFTER cambio;

-- Crear tabla de pagos si no existe
CREATE TABLE IF NOT EXISTS pagos (
    id_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_venta INT NOT NULL,
    metodo_pago ENUM('efectivo', 'transferencia', 'tarjeta', 'qr') NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    fecha_pago DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_venta) REFERENCES ventas(codigo_venta) ON DELETE CASCADE ON UPDATE CASCADE
);

-- Mejorar la tabla detalle_venta
ALTER TABLE detalle_venta 
ADD COLUMN precio_unitario DECIMAL(10,2) NOT NULL AFTER cantidad,
MODIFY COLUMN tipo_material VARCHAR(20) DEFAULT 'producto';
-- Recrear la tabla ventas exactamente como se solicita
-- Eliminar primero las tablas que referencian a ventas
DROP TABLE IF EXISTS detalle_venta;
DROP TABLE IF EXISTS pagos;
DROP TABLE IF EXISTS reportes;  -- Si existe esta tabla
DROP TABLE IF EXISTS ventas;
CREATE TABLE `ventas` (
  `codigo_venta` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_venta` datetime NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `descuento` decimal(5,2) DEFAULT 0,
  `total` decimal(10,2) NOT NULL,
  `metodo_pago` enum('efectivo','transferencia','tarjeta','qr') NOT NULL,
  `monto_pagado` decimal(10,2) NOT NULL,
  `cambio` decimal(10,2) DEFAULT 0,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`codigo_venta`),
  FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`),
  FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Asegurar que detalle_venta tenga exactamente esta estructura
DROP TABLE IF EXISTS detalle_venta;
CREATE TABLE `detalle_venta` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_venta` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `tipo_material` varchar(20) DEFAULT 'producto',
  PRIMARY KEY (`id_detalle`),
  FOREIGN KEY (`codigo_venta`) REFERENCES `ventas` (`codigo_venta`),
  FOREIGN KEY (`id_producto`) REFERENCES `almacen_productos` (`id_producto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Recrear detalle_venta
CREATE TABLE `detalle_venta` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_venta` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `tipo_material` varchar(20) DEFAULT 'producto',
  PRIMARY KEY (`id_detalle`),
  FOREIGN KEY (`codigo_venta`) REFERENCES `ventas` (`codigo_venta`),
  FOREIGN KEY (`id_producto`) REFERENCES `almacen_productos` (`id_producto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Recrear pagos
CREATE TABLE `pagos` (
  `id_pago` int(11) NOT NULL AUTO_INCREMENT,
  `id_venta` int(11) NOT NULL,
  `metodo_pago` enum('efectivo','transferencia','tarjeta','qr') NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha_pago` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_pago`),
  FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`codigo_venta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Modificar la tabla existente para ajustarla a los requerimientos
ALTER TABLE detalle_venta 
MODIFY COLUMN precio_unitario decimal(10,2) NOT NULL,
MODIFY COLUMN subtotal decimal(10,2) NOT NULL,
MODIFY COLUMN tipo_material varchar(20) DEFAULT 'producto';

-- Verificar que las claves foráneas estén correctas
ALTER TABLE detalle_venta
ADD FOREIGN KEY (`codigo_venta`) REFERENCES `ventas` (`codigo_venta`),
ADD FOREIGN KEY (`id_producto`) REFERENCES `almacen_productos` (`id_producto`);

SHOW GRANTS FOR 'root'@'localhost';
-- Otorgar todos los privilegios en todas las bases de datos
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;

-- Otorgar privilegios para conexiones desde cualquier host (solo desarrollo)
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;

START TRANSACTION;
INSERT INTO ventas (id_cliente, id_usuario, fecha_venta, subtotal, descuento, total, metodo_pago, monto_pagado, cambio, observaciones)
VALUES (1, 1, NOW(), 100, 0, 100, 'efectivo', 100, 0, 'Prueba');
COMMIT;