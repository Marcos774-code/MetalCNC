-- MySQL dump 10.13  Distrib 8.0.38, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: metalcnc
-- ------------------------------------------------------
-- Server version	5.5.5-10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `almacen_activos`
--

DROP TABLE IF EXISTS `almacen_activos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `almacen_activos` (
  `id_activo` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_activo` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 0,
  `tipo_activo` enum('maquinaria','herramienta') NOT NULL,
  `ubicacion` varchar(100) NOT NULL,
  PRIMARY KEY (`id_activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `almacen_activos`
--

LOCK TABLES `almacen_activos` WRITE;
/*!40000 ALTER TABLE `almacen_activos` DISABLE KEYS */;
/*!40000 ALTER TABLE `almacen_activos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `almacen_materia_prima`
--

DROP TABLE IF EXISTS `almacen_materia_prima`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `almacen_materia_prima` (
  `id_plancha` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_material` enum('inox','aluminio','acero') NOT NULL,
  `grosor` decimal(5,2) NOT NULL CHECK (`grosor` in (1.5,2,2.5,3,4,5,6,7,8,9,10,11,12,13,14,15)),
  `cantidad` int(11) NOT NULL DEFAULT 0,
  `id_proveedor` int(11) DEFAULT NULL,
  `ubicacion` varchar(100) NOT NULL,
  PRIMARY KEY (`id_plancha`),
  KEY `id_proveedor` (`id_proveedor`),
  CONSTRAINT `almacen_materia_prima_ibfk_1` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`id_proveedor`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `almacen_materia_prima`
--

LOCK TABLES `almacen_materia_prima` WRITE;
/*!40000 ALTER TABLE `almacen_materia_prima` DISABLE KEYS */;
INSERT INTO `almacen_materia_prima` VALUES (1,'acero',2.50,50,2,'Zona A, Nivel 1'),(2,'inox',1.50,30,1,'Zona B, Nivel 2'),(3,'aluminio',3.00,40,3,'Zona C, Nivel 1'),(4,'acero',5.00,25,2,'Zona A, Nivel 3'),(5,'inox',2.00,35,1,'Zona B, Nivel 1'),(6,'aluminio',4.00,20,3,'Zona C, Nivel 2'),(7,'acero',3.00,45,4,'Zona A, Nivel 2'),(8,'inox',2.50,28,5,'Zona B, Nivel 3'),(9,'aluminio',1.50,38,6,'Zona C, Nivel 1'),(10,'acero',6.00,15,7,'Zona A, Nivel 4');
/*!40000 ALTER TABLE `almacen_materia_prima` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `almacen_productos`
--

DROP TABLE IF EXISTS `almacen_productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `almacen_productos` (
  `id_producto` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_producto` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 0,
  `precio_unitario` decimal(10,2) NOT NULL,
  `ubicacion` varchar(100) NOT NULL,
  PRIMARY KEY (`id_producto`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `almacen_productos`
--

LOCK TABLES `almacen_productos` WRITE;
/*!40000 ALTER TABLE `almacen_productos` DISABLE KEYS */;
INSERT INTO `almacen_productos` VALUES (1,'Soporte metálico para estante','Soporte de acero para estantes de 50cm',25,45.00,'Estantería A, Nivel 2'),(2,'Bandeja de acero inoxidable','Bandeja rectangular 30x40cm para cocina',18,120.00,'Estantería B, Nivel 1'),(3,'Estructura para puerta corrediza','Marco metálico para puerta de 2.10m',8,320.00,'Zona de estructuras'),(4,'Reja de seguridad','Reja de protección 1.5x2m con cerradura',12,280.00,'Área de seguridad'),(5,'Estantería industrial','Estante metálico de 5 niveles 2x1m',6,550.00,'Sección de estanterías'),(6,'Escalera telescópica','Escalera de aluminio extensible hasta 3m',10,420.00,'Área de herramientas'),(7,'Perfil angular de acero','Ángulo de 1.5m x 2\" x 2\"',35,38.00,'Zona de perfiles'),(8,'Cajón de herramientas','Organizador metálico con 5 cajones',15,180.00,'Área de herramientas'),(9,'Barrera de protección','Barrera metálica móvil 1.8m de largo',9,210.00,'Área de seguridad'),(10,'Mesa de trabajo industrial','Mesa de acero 1.5x0.8m con superficie antideslizante',7,390.00,'Sección de muebles');
/*!40000 ALTER TABLE `almacen_productos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clientes`
--

DROP TABLE IF EXISTS `clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clientes` (
  `id_cliente` int(11) NOT NULL AUTO_INCREMENT,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `nit_ci` varchar(20) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `extension` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id_cliente`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clientes`
--

LOCK TABLES `clientes` WRITE;
/*!40000 ALTER TABLE `clientes` DISABLE KEYS */;
INSERT INTO `clientes` VALUES (1,'Juan','Pérez','12345678 LP','72000001','juan.perez@email.com','Calle 1, Zona Central',NULL),(2,'María','Lopez','87654321 SC','72000002','maria.lopez@email.com','Avenida 5, Zona Sur',NULL),(3,'Carlos','González','11223344 CB','72000003','carlos.gonzalez@email.com','Calle 12, Zona Este',NULL),(4,'Ana','Martínez','99887766 LP','72000004','ana.martinez@email.com','Av. Busch, Zona Norte',NULL),(5,'Pedro','Fernández','55443322 OR','72000005','pedro.fernandez@email.com','Calle Bolívar, Centro',NULL),(6,'Sofía','Ramírez','66778899 PT','72000006','sofia.ramirez@email.com','Calle Sucre, Zona Oeste',NULL),(7,'Luis','Herrera','33445566 CB','72000007','luis.herrera@email.com','Calle 3, Zona Sur',NULL),(8,'Elena','Torres','22113344 SC','72000008','elena.torres@email.com','Av. Mariscal, Zona Este',NULL),(9,'Miguel','Vargas','77889900 LP','72000009','miguel.vargas@email.com','Calle Comercio, Centro',NULL),(10,'Andrea','Guzmán','99001122 CB','72000010','andrea.guzman@email.com','Calle 7, Zona Norte',NULL);
/*!40000 ALTER TABLE `clientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `costos_golpe`
--

DROP TABLE IF EXISTS `costos_golpe`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `costos_golpe` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `espesor_id` int(11) NOT NULL,
  `costo_1` decimal(10,2) NOT NULL,
  `costo_2` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `espesor_id` (`espesor_id`),
  CONSTRAINT `costos_golpe_ibfk_1` FOREIGN KEY (`espesor_id`) REFERENCES `espesores` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `costos_golpe`
--

LOCK TABLES `costos_golpe` WRITE;
/*!40000 ALTER TABLE `costos_golpe` DISABLE KEYS */;
INSERT INTO `costos_golpe` VALUES (1,1,6.00,8.00),(2,2,8.00,10.00),(3,3,10.00,12.00),(4,4,12.00,14.00),(5,5,15.00,18.00),(6,6,18.00,21.00),(7,7,25.00,30.00),(8,8,32.00,40.00),(9,9,35.00,45.00);
/*!40000 ALTER TABLE `costos_golpe` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cotizaciones`
--

DROP TABLE IF EXISTS `cotizaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cotizaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `material_id` int(11) NOT NULL,
  `espesor_id` int(11) NOT NULL,
  `desarrollo` decimal(10,2) NOT NULL,
  `largo` decimal(10,2) NOT NULL,
  `golpes_pieza` int(11) NOT NULL,
  `perdida_material` decimal(5,2) NOT NULL,
  `costo_total` decimal(12,2) NOT NULL,
  `peso_pieza` decimal(10,2) NOT NULL,
  `numero_piezas` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `costo_golpe` decimal(10,2) NOT NULL,
  `precio_material` decimal(10,2) NOT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `material_id` (`material_id`),
  KEY `espesor_id` (`espesor_id`),
  KEY `cliente_id` (`cliente_id`),
  CONSTRAINT `cotizaciones_ibfk_1` FOREIGN KEY (`material_id`) REFERENCES `materiales` (`id`),
  CONSTRAINT `cotizaciones_ibfk_2` FOREIGN KEY (`espesor_id`) REFERENCES `espesores` (`id`),
  CONSTRAINT `cotizaciones_ibfk_3` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id_cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cotizaciones`
--

LOCK TABLES `cotizaciones` WRITE;
/*!40000 ALTER TABLE `cotizaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `cotizaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalle_venta`
--

DROP TABLE IF EXISTS `detalle_venta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalle_venta` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_venta` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `tipo_material` varchar(20) DEFAULT 'producto',
  PRIMARY KEY (`id_detalle`),
  KEY `codigo_venta` (`codigo_venta`),
  KEY `id_producto` (`id_producto`),
  CONSTRAINT `detalle_venta_ibfk_1` FOREIGN KEY (`codigo_venta`) REFERENCES `ventas` (`codigo_venta`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `detalle_venta_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `almacen_productos` (`id_producto`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_venta`
--

LOCK TABLES `detalle_venta` WRITE;
/*!40000 ALTER TABLE `detalle_venta` DISABLE KEYS */;
/*!40000 ALTER TABLE `detalle_venta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `empleados`
--

DROP TABLE IF EXISTS `empleados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `empleados` (
  `id_empleado` int(11) NOT NULL AUTO_INCREMENT,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `cargo` varchar(50) DEFAULT NULL,
  `turno` varchar(50) DEFAULT NULL,
  `salario` decimal(10,2) NOT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  PRIMARY KEY (`id_empleado`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `empleados`
--

LOCK TABLES `empleados` WRITE;
/*!40000 ALTER TABLE `empleados` DISABLE KEYS */;
INSERT INTO `empleados` VALUES (1,'Diego','Suárez','78000001','diego.suarez@email.com','Operador de máquina','Mañana',3500.00,'activo'),(2,'Valeria','Rojas','78000002','valeria.rojas@email.com','Tornero','Tarde',3200.00,'activo'),(3,'Gabriel','Mamani','78000003','gabriel.mamani@email.com','Soldador','Mañana',3600.00,'activo'),(4,'Carla','Zárate','78000004','carla.zarate@email.com','Secretaria','Mañana',2800.00,'activo'),(5,'José','Vargas','78000005','jose.vargas@email.com','Encargado de almacén','Tarde',4000.00,'activo'),(6,'Sandra','Gonzales','78000006','sandra.gonzales@email.com','Supervisora','Mañana',4500.00,'activo'),(7,'Daniel','Cabrera','78000007','daniel.cabrera@email.com','Asistente de contabilidad','Mañana',3300.00,'activo'),(8,'Andrea','Peñaranda','78000008','andrea.peñaranda@email.com','Diseñadora Industrial','Tarde',3800.00,'activo'),(9,'Hugo','Quintana','78000009','hugo.quintana@email.com','Inspector de calidad','Mañana',3700.00,'activo'),(10,'Marcela','Pacheco','78000010','marcela.pacheco@email.com','Recepcionista','Tarde',2700.00,'activo');
/*!40000 ALTER TABLE `empleados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `espesores`
--

DROP TABLE IF EXISTS `espesores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `espesores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `espesores`
--

LOCK TABLES `espesores` WRITE;
/*!40000 ALTER TABLE `espesores` DISABLE KEYS */;
INSERT INTO `espesores` VALUES (1,'UNO'),(2,'DOS'),(3,'TRES'),(4,'CUATRO'),(5,'CINCO'),(6,'SEIS'),(7,'OCHO'),(8,'NUEVE'),(9,'DIEZ');
/*!40000 ALTER TABLE `espesores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `materiales`
--

DROP TABLE IF EXISTS `materiales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `materiales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `grosor` decimal(5,2) DEFAULT NULL,
  `densidad` decimal(10,2) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `materiales`
--

LOCK TABLES `materiales` WRITE;
/*!40000 ALTER TABLE `materiales` DISABLE KEYS */;
INSERT INTO `materiales` VALUES (1,'Acero al Carbón','Acero',3050.00,1.50,7850.00,'Acero de bajo carbono, ideal para estructuras.'),(2,'Acero Inoxidable 304','Acero',8500.00,2.00,8000.00,'Resistente a la corrosión, uso en ambientes húmedos.'),(3,'Aluminio 6061','Aluminio',4500.00,1.20,2700.00,'Aluminio de alta resistencia, uso aeronáutico.'),(4,'Cobre C110','Cobre',12000.00,1.00,8960.00,'Excelente conductividad eléctrica y térmica.'),(5,'Latón C260','Latón',9500.00,1.50,8530.00,'Aleación de cobre y zinc, uso decorativo.'),(6,'Zinc','Zinc',7000.00,1.00,7140.00,'Uso en galvanización y protección contra corrosión.'),(7,'Bronce','Bronce',11000.00,2.00,8800.00,'Aleación de cobre y estaño, uso en cojinetes.'),(8,'Acero Galvanizado','Acero',4000.00,1.50,7850.00,'Acero recubierto con zinc para mayor durabilidad.');
/*!40000 ALTER TABLE `materiales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pagos`
--

DROP TABLE IF EXISTS `pagos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pagos` (
  `id_pago` int(11) NOT NULL AUTO_INCREMENT,
  `id_venta` int(11) NOT NULL,
  `metodo_pago` enum('efectivo','transferencia','tarjeta','qr') NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha_pago` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_pago`),
  KEY `id_venta` (`id_venta`),
  CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`codigo_venta`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pagos`
--

LOCK TABLES `pagos` WRITE;
/*!40000 ALTER TABLE `pagos` DISABLE KEYS */;
/*!40000 ALTER TABLE `pagos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proveedores`
--

DROP TABLE IF EXISTS `proveedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proveedores` (
  `id_proveedor` int(11) NOT NULL AUTO_INCREMENT,
  `razon_social` varchar(150) NOT NULL,
  `nombres` varchar(100) DEFAULT NULL,
  `apellidos` varchar(100) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id_proveedor`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proveedores`
--

LOCK TABLES `proveedores` WRITE;
/*!40000 ALTER TABLE `proveedores` DISABLE KEYS */;
INSERT INTO `proveedores` VALUES (1,'AceroBol S.A.','Roberto','Santos','Proveedor de acero inoxidable y aluminio','76000001','Av. América #123, Cochabamba'),(2,'Metalurgia S.R.L.','Patricia','Vega','Especialistas en acero al carbono','76000002','Calle Industrial #456, La Paz'),(3,'AluTech S.A.','Fernando','Quispe','Distribución de aluminio en láminas','76000003','Zona Sur #789, Santa Cruz'),(4,'CobreBol Ltda.','Claudia','Mendoza','Proveedor de cobre y bronce','76000004','Av. Sucre #321, Oruro'),(5,'Industria Metalúrgica Andina','Raúl','Torrez','Venta de metales y galvanizados','76000005','Calle Central #654, Potosí'),(6,'Hierros y Aceros S.R.L.','Isabel','Gutiérrez','Especialistas en acero estructural','76000006','Av. Bolivia #987, Tarija'),(7,'Latonera Nacional','Javier','Peralta','Venta de latón y materiales para construcción','76000007','Zona Este #741, Sucre'),(8,'Metales de Occidente','Marina','Salazar','Proveedora de acero inoxidable','76000008','Zona Norte #852, La Paz'),(9,'TuboMetal S.A.','Ricardo','Paredes','Venta de tubos metálicos y perfiles','76000009','Av. Santa Cruz #963, Cochabamba'),(10,'Fundición y Acero Ltda.','Teresa','Zeballos','Distribuidora de acero fundido','76000010','Calle Comercio #159, Oruro');
/*!40000 ALTER TABLE `proveedores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reportes`
--

DROP TABLE IF EXISTS `reportes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reportes` (
  `id_reporte` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_venta` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `tipo_reporte` enum('diario','mensual','anual') NOT NULL,
  `detalle` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`detalle`)),
  `fecha_reporte` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_reporte`),
  KEY `codigo_venta` (`codigo_venta`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `reportes_ibfk_1` FOREIGN KEY (`codigo_venta`) REFERENCES `ventas` (`codigo_venta`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `reportes_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reportes`
--

LOCK TABLES `reportes` WRITE;
/*!40000 ALTER TABLE `reportes` DISABLE KEYS */;
/*!40000 ALTER TABLE `reportes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_usuario` varchar(50) NOT NULL,
  `contrasena` varchar(200) NOT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL,
  `rol` enum('gerente','ventas','almacen','contabilidad') NOT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `nombre_usuario` (`nombre_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'jonathan','1234',NULL,'gerente',1,'2025-05-14 19:49:55','2025-05-14 19:49:55'),(2,'alfredo','1234',NULL,'almacen',1,'2025-05-14 19:49:55','2025-05-14 19:49:55'),(3,'calle','1234',NULL,'ventas',1,'2025-05-14 19:49:55','2025-05-14 19:49:55'),(4,'soliz','1234',NULL,'contabilidad',1,'2025-05-14 19:49:55','2025-05-14 19:49:55');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ventas`
--

DROP TABLE IF EXISTS `ventas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ventas` (
  `codigo_venta` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_venta` datetime NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `descuento` decimal(5,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `metodo_pago` enum('efectivo','transferencia','tarjeta','qr') NOT NULL,
  `monto_pagado` decimal(10,2) NOT NULL,
  `cambio` decimal(10,2) DEFAULT 0.00,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`codigo_venta`),
  KEY `id_cliente` (`id_cliente`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `ventas_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ventas_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ventas`
--

LOCK TABLES `ventas` WRITE;
/*!40000 ALTER TABLE `ventas` DISABLE KEYS */;
/*!40000 ALTER TABLE `ventas` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-05-14 19:51:47
