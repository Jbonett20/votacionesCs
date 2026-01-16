-- ============================
-- CONVERTIR BASE DE DATOS A UTF-8
-- ============================
USE bd_votaciones;

-- Establecer codificación de la sesión
SET NAMES utf8mb4;
SET character_set_client = utf8mb4;
SET character_set_connection = utf8mb4;
SET character_set_results = utf8mb4;

-- Convertir base de datos
ALTER DATABASE bd_votaciones CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Convertir tabla roles
ALTER TABLE roles CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Convertir tabla estados
ALTER TABLE estados CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Convertir tabla tipos_identificacion
ALTER TABLE tipos_identificacion CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Convertir tabla usuarios
ALTER TABLE usuarios CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Convertir tabla lideres
ALTER TABLE lideres CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Convertir tabla votantes
ALTER TABLE votantes CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Verificar
SHOW VARIABLES LIKE 'character_set%';
SHOW TABLE STATUS WHERE Name IN ('roles', 'estados', 'tipos_identificacion', 'usuarios', 'lideres', 'votantes');
