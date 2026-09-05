#!/bin/sh
set -e

cd /var/www/html

echo "› TUTOLAR · preparando el contenedor"

# 1. Dependencias. Si no hay vendor/, se instalan.
if [ ! -f vendor/autoload.php ]; then
    echo "› composer install"
    composer install --no-interaction --prefer-dist --no-progress
fi

# 2. Entorno.
#    Laravel carga el .env de forma "inmutable": las variables que ya existen
#    en el entorno del proceso GANAN sobre las del archivo. Por eso el .env
#    puede seguir apuntando a XAMPP (127.0.0.1) sin romper nada aquí: las
#    variables DB_* que define docker-compose son las que se aplican dentro
#    del contenedor. Un mismo .env sirve para las dos formas de arrancar.
if [ ! -f .env ]; then
    echo "› creando .env a partir de .env.example"
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    echo "› generando APP_KEY"
    php artisan key:generate --force
fi

# 3. Permisos de escritura de Laravel. En un bind mount de Windows el chown
#    no aplica y falla sin consecuencias: por eso el `|| true`.
mkdir -p storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rw storage bootstrap/cache 2>/dev/null || true

# La configuración cacheada congelaría el .env y anularía lo anterior.
rm -f bootstrap/cache/config.php bootstrap/cache/routes-*.php

# 4. Esperar a la base de datos.
echo "› esperando a la base de datos en ${DB_HOST:-db}:${DB_PORT:-3306}"
intentos=0
until php -r '
    try {
        new PDO(
            sprintf("mysql:host=%s;port=%s;dbname=%s",
                getenv("DB_HOST") ?: "db",
                getenv("DB_PORT") ?: "3306",
                getenv("DB_DATABASE") ?: "tutolar"),
            getenv("DB_USERNAME") ?: "tutolar",
            getenv("DB_PASSWORD") ?: "tutolar"
        );
        exit(0);
    } catch (Throwable $e) { exit(1); }
' 2>/dev/null; do
    intentos=$((intentos + 1))
    if [ "$intentos" -gt 60 ]; then
        echo "✗ la base de datos no responde tras 60 intentos"
        exit 1
    fi
    sleep 2
done
echo "› base de datos lista"

# 5. Migrar siempre; sembrar solo si el centro no existe todavía.
#
#    Si esto falla, `set -e` mata el contenedor y Docker lo reinicia, así que
#    el error se repite cada pocos segundos y el motivo real queda enterrado
#    bajo copias idénticas. El caso que de verdad pasa tiene nombre: el volumen
#    de MariaDB conserva el esquema de un juego de migraciones anterior, su
#    tabla `migrations` nombra archivos que ya no existen, y por eso Laravel
#    cree que las de ahora están pendientes y trata de crear tablas que ya
#    están. Se detecta y se explica antes de salir.
if ! php artisan migrate --force; then
    echo ""
    echo "✗ La migración ha fallado."
    echo ""
    echo "  Si el error dice «Base table or view already exists», la base de"
    echo "  datos de este volumen se creó con las migraciones anteriores y su"
    echo "  tabla \`migrations\` nombra archivos que ya no existen. No hay"
    echo "  forma de continuar desde ahí: hay que partir de una base limpia."
    echo ""
    echo "      docker compose down -v      # -v borra el volumen de MariaDB"
    echo "      docker compose up -d --build"
    echo ""
    echo "  Los datos de desarrollo los vuelve a crear el seeder. Si en esa"
    echo "  base había algo que no quieres perder, sácalo antes desde"
    echo "  phpMyAdmin (http://localhost:8081)."
    echo ""
    exit 1
fi

if [ "${TUTOLAR_SEED:-1}" = "1" ]; then
    if php -r '
        try {
            $pdo = new PDO(
                sprintf("mysql:host=%s;port=%s;dbname=%s",
                    getenv("DB_HOST") ?: "db",
                    getenv("DB_PORT") ?: "3306",
                    getenv("DB_DATABASE") ?: "tutolar"),
                getenv("DB_USERNAME") ?: "tutolar",
                getenv("DB_PASSWORD") ?: "tutolar"
            );
            $n = (int) $pdo->query("SELECT COUNT(*) FROM centros")->fetchColumn();
            exit($n === 0 ? 0 : 1);   // 0 = hay que sembrar
        } catch (Throwable $e) { exit(1); }
    '; then
        echo "› base de datos vacía: cargando los datos de ejemplo (30-60 s)"
        php artisan db:seed --force
    else
        echo "› ya hay datos: no se vuelve a sembrar"
    fi
fi

php artisan view:clear >/dev/null 2>&1 || true

echo "› TUTOLAR listo → http://localhost:8080"

exec "$@"
