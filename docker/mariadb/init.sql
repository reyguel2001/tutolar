-- TUTOLAR · inicializacion de la base de datos.
-- MariaDB solo ejecuta esto la primera vez que se crea el volumen.
CREATE DATABASE IF NOT EXISTS tutolar
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Base de datos aparte para las pruebas de integracion.
CREATE DATABASE IF NOT EXISTS tutolar_test
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON tutolar.*      TO 'tutolar'@'%';
GRANT ALL PRIVILEGES ON tutolar_test.* TO 'tutolar'@'%';
FLUSH PRIVILEGES;
