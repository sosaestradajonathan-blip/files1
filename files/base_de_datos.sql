-- ============================================
-- BASE DE DATOS: Sistema de Conversión USD->GTQ
-- Para copiar y pegar en phpMyAdmin
-- ============================================

CREATE DATABASE IF NOT EXISTS banco_conversion
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE banco_conversion;

-- Tabla de tipos de cambio
CREATE TABLE IF NOT EXISTS tipo_cambio (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tasa DECIMAL(10,4) NOT NULL COMMENT 'Quetzales por 1 dólar',
  fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insertar tasa inicial (ajusta según el día)
INSERT INTO tipo_cambio (tasa) VALUES (7.7500);

-- Tabla principal de conversiones
CREATE TABLE IF NOT EXISTS conversiones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  monto_usd DECIMAL(15,2) NOT NULL COMMENT 'Monto en dólares',
  monto_gtq DECIMAL(15,2) NOT NULL COMMENT 'Monto en quetzales',
  tasa_usada DECIMAL(10,4) NOT NULL COMMENT 'Tasa aplicada',
  tipo VARCHAR(20) NOT NULL COMMENT 'fisica o consulta',
  nombre_cliente VARCHAR(100) DEFAULT NULL,
  fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fecha (fecha_hora),
  INDEX idx_tipo (tipo)
);

-- Vista: conversiones por hora del día actual
CREATE OR REPLACE VIEW v_conversiones_por_hora AS
SELECT
  HOUR(fecha_hora) AS hora,
  COUNT(*) AS total_operaciones,
  SUM(monto_usd) AS total_usd,
  SUM(monto_gtq) AS total_gtq,
  tipo
FROM conversiones
WHERE DATE(fecha_hora) = CURDATE()
GROUP BY HOUR(fecha_hora), tipo
ORDER BY hora;

-- Vista: total por día
CREATE OR REPLACE VIEW v_total_por_dia AS
SELECT
  DATE(fecha_hora) AS dia,
  COUNT(*) AS total_operaciones,
  SUM(monto_usd) AS total_usd,
  SUM(monto_gtq) AS total_gtq,
  SUM(CASE WHEN tipo='fisica' THEN 1 ELSE 0 END) AS cambios_fisicos,
  SUM(CASE WHEN tipo='consulta' THEN 1 ELSE 0 END) AS solo_consultas
FROM conversiones
GROUP BY DATE(fecha_hora)
ORDER BY dia DESC;

