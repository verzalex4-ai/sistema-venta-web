-- =====================================================
-- BASE DE DATOS: SISTEMA DE VENTAS
-- =====================================================

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS sistema_ventas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sistema_ventas;

-- =====================================================
-- 1. TABLA: usuarios
-- =====================================================
CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'vendedor', 'repositor') NOT NULL DEFAULT 'vendedor',
    estado TINYINT(1) DEFAULT 1 COMMENT '1 = activo, 0 = inactivo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_ultima_sesion DATETIME NULL,
    INDEX idx_email (email),
    INDEX idx_rol (rol),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Usuarios del sistema';

-- =====================================================
-- 2. TABLA: categorias
-- =====================================================
CREATE TABLE categorias (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    estado TINYINT(1) DEFAULT 1 COMMENT '1 = activo, 0 = inactivo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nombre (nombre),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categorías de productos';

-- =====================================================
-- 3. TABLA: categorias_iconos
-- =====================================================
CREATE TABLE categorias_iconos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    categoria_id INT NOT NULL UNIQUE,
    icono VARCHAR(100),
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE,
    INDEX idx_categoria_id (categoria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Iconos asociados a categorías';

-- =====================================================
-- 4. TABLA: productos
-- =====================================================
CREATE TABLE productos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    imagen VARCHAR(255),
    precio DECIMAL(10, 2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    categoria_id INT NOT NULL,
    estado TINYINT(1) DEFAULT 1 COMMENT '1 = activo, 0 = inactivo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE RESTRICT,
    INDEX idx_nombre (nombre),
    INDEX idx_categoria_id (categoria_id),
    INDEX idx_estado (estado),
    INDEX idx_stock (stock),
    FULLTEXT idx_busqueda (nombre, descripcion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Productos del catálogo';

-- =====================================================
-- 5. TABLA: proveedores
-- =====================================================
CREATE TABLE proveedores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(150) NOT NULL,
    razon_social VARCHAR(150),
    cuit VARCHAR(20) UNIQUE,
    email VARCHAR(100),
    telefono VARCHAR(20),
    direccion VARCHAR(255),
    ciudad VARCHAR(100),
    provincia VARCHAR(100),
    codigo_postal VARCHAR(10),
    contacto_nombre VARCHAR(100),
    contacto_telefono VARCHAR(20),
    estado TINYINT(1) DEFAULT 1 COMMENT '1 = activo, 0 = inactivo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_nombre (nombre),
    INDEX idx_cuit (cuit),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Proveedores';

-- =====================================================
-- 6. TABLA: clientes
-- =====================================================
CREATE TABLE clientes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    telefono VARCHAR(20),
    dni VARCHAR(20),
    direccion VARCHAR(255),
    estado TINYINT(1) DEFAULT 1 COMMENT '1 = activo, 0 = inactivo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_nombre (nombre),
    INDEX idx_apellido (apellido),
    INDEX idx_email (email),
    INDEX idx_dni (dni),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Clientes';

-- =====================================================
-- 7. TABLA: ventas
-- =====================================================
CREATE TABLE ventas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cliente_id INT NOT NULL,
    usuario_id INT NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    metodo_pago VARCHAR(50) NOT NULL COMMENT 'efectivo, tarjeta, cheque, etc.',
    estado VARCHAR(20) DEFAULT 'completada' COMMENT 'completada, pendiente, cancelada',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_cliente_id (cliente_id),
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_estado (estado),
    INDEX idx_fecha_creacion (fecha_creacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de ventas';

-- =====================================================
-- 8. TABLA: detalle_ventas
-- =====================================================
CREATE TABLE detalle_ventas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    venta_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE RESTRICT,
    INDEX idx_venta_id (venta_id),
    INDEX idx_producto_id (producto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detalles de ventas (líneas)';

-- =====================================================
-- 9. TABLA: facturas
-- =====================================================
CREATE TABLE facturas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero_factura VARCHAR(50) NOT NULL UNIQUE,
    tipo ENUM('venta', 'compra') NOT NULL COMMENT 'venta o compra',
    tipo_comprobante ENUM('factura_a', 'factura_b', 'factura_c', 'ticket') NOT NULL DEFAULT 'factura_b',
    cliente_id INT NOT NULL,
    usuario_id INT NOT NULL,
    fecha_emision DATE NOT NULL,
    fecha_vencimiento DATE,
    subtotal DECIMAL(10, 2) NOT NULL,
    iva DECIMAL(10, 2) DEFAULT 0,
    total DECIMAL(10, 2) NOT NULL,
    observaciones TEXT,
    estado VARCHAR(20) DEFAULT 'pendiente' COMMENT 'pendiente, pagada, cancelada',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_numero_factura (numero_factura),
    INDEX idx_cliente_id (cliente_id),
    INDEX idx_estado (estado),
    INDEX idx_fecha_emision (fecha_emision),
    INDEX idx_tipo (tipo),
    INDEX idx_tipo_comprobante (tipo_comprobante)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Facturas emitidas';

-- =====================================================
-- 10. TABLA: detalle_facturas
-- =====================================================
CREATE TABLE detalle_facturas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    factura_id INT NOT NULL,
    producto_id INT,
    descripcion TEXT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (factura_id) REFERENCES facturas(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL,
    INDEX idx_factura_id (factura_id),
    INDEX idx_producto_id (producto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detalles de facturas (líneas)';

-- =====================================================
-- 11. TABLA: cuentas_corrientes
-- =====================================================
CREATE TABLE cuentas_corrientes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cliente_id INT NOT NULL,
    factura_id INT,
    tipo_movimiento ENUM('debe', 'haber') NOT NULL COMMENT 'debe=deuda, haber=pago',
    importe DECIMAL(10, 2) NOT NULL,
    descripcion VARCHAR(255),
    fecha_movimiento DATE NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
    FOREIGN KEY (factura_id) REFERENCES facturas(id) ON DELETE SET NULL,
    INDEX idx_cliente_id (cliente_id),
    INDEX idx_factura_id (factura_id),
    INDEX idx_fecha_movimiento (fecha_movimiento),
    INDEX idx_tipo_movimiento (tipo_movimiento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Movimientos de cuentas corrientes de clientes';

-- =====================================================
-- 12. TABLA: movimientos_caja
-- =====================================================
CREATE TABLE movimientos_caja (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tipo ENUM('ingreso', 'egreso') NOT NULL,
    concepto VARCHAR(150) NOT NULL,
    categoria VARCHAR(100),
    importe DECIMAL(10, 2) NOT NULL,
    metodo_pago VARCHAR(50),
    referencia VARCHAR(100),
    observaciones TEXT,
    usuario_id INT NOT NULL,
    fecha_movimiento DATE NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_tipo (tipo),
    INDEX idx_categoria (categoria),
    INDEX idx_fecha_movimiento (fecha_movimiento),
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_concepto (concepto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Movimientos de caja (ingresos y egresos)';

-- =====================================================
-- DATOS INICIALES
-- =====================================================

-- Insertar usuario administrador (contraseña: admin123)
INSERT INTO usuarios (nombre, email, password, rol, estado) VALUES
('Administrador', 'admin@sistema.local', '$2y$10$90SWqXDZnONwSy9IsuytyOyrquOWBewKyPgAI90KWeI5UQ8ElCTyW', 'admin', 1),
('Juan Vendedor', 'vendedor@sistema.local', '$2y$10$90SWqXDZnONwSy9IsuytyOyrquOWBewKyPgAI90KWeI5UQ8ElCTyW', 'vendedor', 1),
('Carlos Repositor', 'repositor@sistema.local', '$2y$10$90SWqXDZnONwSy9IsuytyOyrquOWBewKyPgAI90KWeI5UQ8ElCTyW', 'repositor', 1);

-- Insertar categorías de ejemplo
INSERT INTO categorias (nombre, descripcion, estado) VALUES
('Electrónica', 'Productos electrónicos en general', 1),
('Ropa', 'Prendas de vestir', 1),
('Alimentos', 'Productos alimenticios', 1),
('Hogar', 'Artículos para el hogar', 1);

-- Insertar iconos para categorías
INSERT INTO categorias_iconos (categoria_id, icono) VALUES
(1, 'fas fa-laptop'),
(2, 'fas fa-shirt'),
(3, 'fas fa-apple-alt'),
(4, 'fas fa-home');

-- Insertar productos de ejemplo
INSERT INTO productos (nombre, descripcion, precio, stock, categoria_id, estado) VALUES
('Laptop HP', 'Laptop HP 15.6 pulgadas, procesador Intel i5', 899.99, 10, 1, 1),
('Remera básica', 'Remera de algodón para hombre, color blanco', 19.99, 50, 2, 1),
('Arroz integral', 'Arroz integral 1kg', 3.99, 100, 3, 1),
('Almohada memory foam', 'Almohada con espuma viscoelástica', 49.99, 20, 4, 1);

-- Insertar clientes de ejemplo
INSERT INTO clientes (nombre, apellido, email, telefono, dni, direccion, estado) VALUES
('Juan', 'Pérez', 'juan.perez@email.com', '3515551234', '30123456', 'Calle Principal 123', 1),
('María', 'García', 'maria.garcia@email.com', '3515555678', '32654321', 'Av. Secundaria 456', 1),
('Carlos', 'López', 'carlos.lopez@email.com', '3516789012', '28987654', 'Calle Tercera 789', 1);

-- Insertar proveedor de ejemplo
INSERT INTO proveedores (nombre, razon_social, cuit, email, telefono, direccion, ciudad, provincia, codigo_postal, contacto_nombre, contacto_telefono, estado) VALUES
('Distribuidor ABC', 'Distribuidor ABC S.A.', '20-12345678-9', 'contacto@distrib.com', '3514445555', 'Calle Distribuidor 100', 'Córdoba', 'Córdoba', '5000', 'Roberto García', '3514445555', 1);

-- =====================================================
-- CREAR VISTAS ÚTILES
-- =====================================================

-- Vista: Saldo de clientes
CREATE OR REPLACE VIEW v_saldo_clientes AS
SELECT 
    c.id,
    CONCAT(c.nombre, ' ', c.apellido) as cliente_nombre,
    c.email,
    c.telefono,
    c.dni,
    COALESCE(SUM(CASE WHEN cc.tipo_movimiento = 'debe' THEN cc.importe ELSE 0 END), 0) as total_debe,
    COALESCE(SUM(CASE WHEN cc.tipo_movimiento = 'haber' THEN cc.importe ELSE 0 END), 0) as total_haber,
    COALESCE(SUM(CASE WHEN cc.tipo_movimiento = 'debe' THEN cc.importe ELSE -cc.importe END), 0) as saldo_actual
FROM clientes c
LEFT JOIN cuentas_corrientes cc ON c.id = cc.cliente_id
WHERE c.estado = 1
GROUP BY c.id, c.nombre, c.apellido, c.email, c.telefono, c.dni;

-- Vista: Stock de productos
CREATE OR REPLACE VIEW v_stock_productos AS
SELECT 
    p.id,
    p.nombre,
    c.nombre as categoria,
    p.precio,
    p.stock,
    CASE 
        WHEN p.stock = 0 THEN 'Sin stock'
        WHEN p.stock <= 5 THEN 'Stock bajo'
        ELSE 'Disponible'
    END as estado_stock,
    p.fecha_actualizacion
FROM productos p
JOIN categorias c ON p.categoria_id = c.id
WHERE p.estado = 1;

-- Vista: Ventas diarias
CREATE OR REPLACE VIEW v_ventas_diarias AS
SELECT 
    DATE(v.fecha_creacion) as fecha,
    COUNT(v.id) as cantidad_ventas,
    SUM(v.total) as total_vendido,
    COUNT(DISTINCT v.cliente_id) as clientes_atendidos
FROM ventas v
GROUP BY DATE(v.fecha_creacion)
ORDER BY fecha DESC;

-- Vista: Facturas pendientes de pago
CREATE OR REPLACE VIEW v_facturas_pendientes AS
SELECT 
    f.id,
    f.numero_factura,
    CONCAT(c.nombre, ' ', c.apellido) as cliente,
    f.total,
    f.fecha_emision,
    f.fecha_vencimiento,
    DATEDIFF(f.fecha_vencimiento, CURDATE()) as dias_vencimiento,
    f.estado
FROM facturas f
JOIN clientes c ON f.cliente_id = c.id
WHERE f.estado = 'pendiente'
ORDER BY f.fecha_vencimiento ASC;

-- =====================================================
-- FIN DEL SCRIPT
-- =====================================================
