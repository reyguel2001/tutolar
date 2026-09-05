# TUTOLAR · instalación

Hay tres formas de levantar el proyecto y **no son excluyentes**:

- **[A · XAMPP](#a--xampp)** — con el MySQL que ya trae XAMPP.
- **[B · Docker](#b--docker)** — un comando; trae su propia MariaDB.
- **[C · Sin base de datos MySQL](#c--sin-base-de-datos-mysql-sqlite)** — SQLite,
  la base de datos es un archivo. Si no tienes MySQL o no te arranca, empieza aquí.

El proyecto vive en `C:\xampp\htdocs\SORA\TUTOLAR` y ya es un proyecto Laravel
completo: **no hay que ejecutar `composer create-project`**. Solo instalar las
dependencias.

> **La raíz web es `public/`, no la raíz del proyecto.** Por encima
> están el `.env` con las credenciales y todo el código. Esto vale para
> Apache, para Nginx y para cualquier otra cosa que sirva el proyecto.

---

## A · XAMPP

### 0. Requisitos

| Requisito | Cómo comprobarlo | Si falla |
|---|---|---|
| **PHP 8.2 o superior** | `php -v` | Laravel 12 no arranca con PHP 8.1. Actualiza XAMPP |
| **PHP en el PATH** | `php -v` responde desde cualquier carpeta | Añade `C:\xampp\php` al PATH de Windows |
| **Composer** | `composer -V` | Instálalo desde getcomposer.org |
| **MySQL arrancado** | Panel de XAMPP → Start en MySQL | — |

Extensiones de PHP. En XAMPP suelen venir activas menos `fileinfo`. Si algo
falla, abre `C:\xampp\php\php.ini` y quita el `;` de estas líneas:

```ini
extension=pdo_mysql
extension=mbstring
extension=fileinfo
extension=openssl
extension=zip
```

### 1. Instalar dependencias

Abre la consola en `C:\xampp\htdocs\SORA\TUTOLAR`:

```bash
composer install
```

Descarga Laravel 12 y sus dependencias en `vendor/`. Tarda uno o dos minutos y
solo hace falta la primera vez.

### 2. Configurar el entorno

```bash
copy .env.example .env
php artisan key:generate
```

El `.env.example` ya viene apuntando a XAMPP: `root` sin contraseña y base de
datos `tutolar`. Si tu MySQL tiene contraseña, cámbiala en `.env`.

### 3. Crear la base de datos

Entra en <http://localhost/phpmyadmin>, pulsa **Nueva** y crea una base de datos
llamada `tutolar` con cotejamiento `utf8mb4_unicode_ci`.

### 4. Crear las tablas y cargar los datos

```bash
php artisan migrate:fresh --seed
```

> `migrate:fresh` y no `migrate`: el esquema está organizado en seis migraciones
> por dominio y, si vienes de una versión anterior del proyecto, las antiguas ya
> no existen. `fresh` vacía y recrea; el seeder deja el instituto completo otra vez.

El seeder crea un instituto completo: 8 grupos, 6 asignaturas, 6 profesores,
alrededor de 110 alumnos con sus familias y varios miles de resultados. Tarda
entre 30 y 60 segundos.

Para empezar de cero en cualquier momento:

```bash
php artisan migrate:fresh --seed
```

### 5. Arrancar

**Opción A1 · servidor de Laravel (recomendada)**

```bash
php artisan serve
```

Y abre <http://localhost:8000>. Sin configurar Apache y sin problemas de rutas.

**Opción A2 · Apache de XAMPP**

Como el proyecto está dentro de `htdocs`, Apache lo sirve en
<http://localhost/SORA/TUTOLAR/public>. Funciona, pero la URL arrastra
la ruta y hay que ajustar `.env`:

```env
APP_URL=http://localhost/SORA/TUTOLAR/public
```

**Opción A3 · host virtual (lo más parecido a producción)**

En `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:

```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/SORA/TUTOLAR/public"
    ServerName tutolar.test
    <Directory "C:/xampp/htdocs/SORA/TUTOLAR/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Y en `C:\Windows\System32\drivers\etc\hosts` (como administrador):

```
127.0.0.1  tutolar.test
```

Reinicia Apache y entra en <http://tutolar.test>.

---

## B · Docker

Necesitas Docker Desktop. No hace falta PHP, ni Composer, ni MySQL en tu
máquina: todo va dentro.

```bash
docker compose up -d --build
```

No hace falta preparar ningún `.env`: el contenedor lo crea a partir de
`.env.example`. Y como Laravel da prioridad a las variables de entorno del
proceso sobre las del archivo, **el mismo `.env` vale para XAMPP y para
Docker**: dentro del contenedor las `DB_*` que manda son las de
`docker-compose.yml`.

La primera vez tarda unos minutos: construye la imagen de PHP, instala las
dependencias, migra y siembra la base de datos. Puedes seguirlo con:

```bash
docker compose logs -f app
```

Cuando veas `TUTOLAR listo`:

| | |
|---|---|
| Web | <http://localhost:8080> |
| phpMyAdmin | <http://localhost:8081> · servidor `db`, usuario `tutolar`, contraseña `tutolar` |
| MariaDB desde el host | `localhost:3307` |

**Los puertos están elegidos para no chocar con XAMPP** (que ocupa el 80, el 443
y el 3306). Puedes tener XAMPP y Docker arrancados a la vez.

Comandos útiles:

```bash
docker compose exec app php artisan migrate:fresh --seed   # empezar de cero
docker compose exec app php artisan test                   # pruebas
docker compose exec app bash                               # una consola dentro
docker compose down                                        # parar
docker compose down -v                                     # parar y borrar la base de datos
```

---

## C · Sin base de datos MySQL (SQLite)

Si no tienes MySQL, no te arranca el de XAMPP o simplemente quieres probar la
aplicación cuanto antes: **SQLite no necesita ningún servidor**. La base de
datos entera es un archivo dentro del proyecto, y Laravel la trata igual que a
MySQL — mismas migraciones, mismo seeder, mismo código.

```bash
composer install
copy .env.example .env
php artisan key:generate
```

Abre el `.env` y deja el bloque de base de datos así — **comenta las líneas de
MySQL** y descomenta las de SQLite:

```env
DB_CONNECTION=sqlite
DB_DATABASE=C:\xampp\htdocs\SORA\TUTOLAR\database\database.sqlite

# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_USERNAME=root
# DB_PASSWORD=
```

La ruta tiene que ser **absoluta**. Si moviste el proyecto, ajústala.

Y ya:

```bash
php artisan migrate:fresh --seed
php artisan serve
```

El archivo `database/database.sqlite` ya viene creado y vacío. Si lo borras,
créalo de nuevo (basta con un archivo vacío con ese nombre) o deja que
`php artisan migrate` te pregunte si quiere crearlo.

Para empezar de cero: `php artisan migrate:fresh --seed`, o borra el archivo y
vuelve a migrar.

**Qué cambia respecto a MySQL:**

| | |
|---|---|
| Migraciones y seeder | Idénticos. Laravel traduce el esquema a SQLite |
| Sesiones, caché y colas | Funcionan igual: las tres usan tablas |
| Búsqueda de alumnos (`LIKE`) | Distingue mayúsculas en caracteres acentuados |
| Concurrencia | SQLite escribe de uno en uno. Irrelevante en desarrollo |
| phpMyAdmin | No aplica. Usa *DB Browser for SQLite* si quieres mirar los datos |

Para la entrega, la memoria y la defensa, MySQL/MariaDB sigue siendo la base de
datos del proyecto — es lo que dice el documento de diseño. SQLite es para poder
trabajar sin tenerla delante. Cambiar de una a otra son dos líneas del `.env`.

---

## Entrar

| Rol | Correo | Contraseña |
|---|---|---|
| Centro educativo | `direccion@iescervantes.edu.es` | `tutolar2026` |
| Profesorado | `javier.ortega@iescervantes.edu.es` | `tutolar2026` |
| Familia | `familia@iescervantes.edu.es` | `tutolar2026` |

La plataforma deduce el rol de la cuenta y lleva a cada usuario a su espacio: no
hay selector de rol en el formulario de acceso. La cuenta de familia aterriza en
`/familia/inicio`, con el rendimiento del alumno que tutela.

El propio seeder te recuerda las tres cuentas por consola al terminar.

**Las demás familias.** El seeder crea una cuenta por cada alumno con familia
vinculada, alrededor de cien, con el correo generado a partir del nombre de la
tutora, el primer apellido y el id del alumno — del estilo
`lucia.martin7@email.com`. La de arriba es simplemente la primera, a la que se
le fija un correo estable para poder documentarla. Si quieres otras:

```bash
php artisan tinker --execute="\App\Models\User::where('rol','TUTOR_LEGAL')->take(5)->get()->each(fn(\$u) => print(\$u->email.PHP_EOL));"
```

O en la base de datos:

```sql
SELECT id, name, email FROM users WHERE rol = 'TUTOR_LEGAL' LIMIT 5;
```

---

## Comprobar que todo está bien

```bash
php artisan test --filter=RendimientoTest
```

Deben pasar las **18 pruebas** del motor de rendimiento: normalización,
ponderación por recencia, umbrales del nivel, estado sin datos, IRG, tendencia,
ISF y disparo de alertas.

---

## Problemas frecuentes

| Síntoma | Causa | Solución |
|---|---|---|
| `Could not open input file: artisan` | Estás en la carpeta equivocada | `cd C:\xampp\htdocs\SORA\TUTOLAR` |
| `Failed to open stream: vendor/autoload.php` | Falta el paso 1 | `composer install` |
| `No application encryption key has been specified` | Falta el paso 2 | `php artisan key:generate` |
| `SQLSTATE[HY000] [1049] Unknown database 'tutolar'` | No existe la base de datos | Créala en phpMyAdmin (paso 3) |
| `could not find driver` | `pdo_mysql` desactivado | Descoméntalo en `php.ini` y reinicia Apache |
| `SQLSTATE[HY000] [2002] ... no se pudo conectar` | MySQL no está arrancado | Arráncalo en XAMPP, o usa SQLite (opción C) |
| SQLite: `Database file at path [tutolar] does not exist` | Dejaste `DB_DATABASE=tutolar` | Pon la **ruta absoluta** al archivo `.sqlite` |
| SQLite: `unable to open database file` | La carpeta `database/` no deja escribir | Comprueba permisos, o crea el archivo a mano |
| `Target class [rol] does not exist` | Han tocado `bootstrap/app.php` | El alias `rol` tiene que estar en `withMiddleware` |
| `View [layouts.app] not found` | Han tocado `config/view.php` | `paths` debe apuntar a `resource_path('views')` |
| `Class "App\Models\User" not found` | Autoload desincronizado | `composer dump-autoload` |
| 404 en todo salvo la portada | Apache sin `mod_rewrite` o sin `AllowOverride All` | Actívalos y reinicia |
| La web se ve sin estilos | Bootstrap viene de un CDN | Conéctate a internet o baja Bootstrap a `public/css/` |
| `SQLSTATE[42S02] ... table 'sessions'` | Falta migrar | `php artisan migrate` |
| Página en blanco | Error de PHP oculto | `APP_DEBUG=true` y mira `storage/logs/laravel.log` |
| El seeder tarda muchísimo | Normal la primera vez | Espera; crea miles de filas |
| Docker: `port is already allocated` | Otro proceso ocupa 8080/8081/3307 | Cambia el puerto de la izquierda en `docker-compose.yml` |
| Docker: `Permission denied` en `storage` | Permisos del bind mount | `docker compose exec app chmod -R ug+rw storage bootstrap/cache` |

---

## Qué hay construido y qué no

**Funciona de verdad:**

- Acceso con contraseña, sesión y separación por rol.
- Panel del centro con reparto real del rendimiento por curso.
- Listado de alumnos con búsqueda, filtros, orden y paginación.
- Ficha del alumno con índice global, desglose por asignatura e historial.
- Vista del profesorado con el agregado de sus grupos.
- Vista de familia con el rendimiento de sus hijos.
- Motor de rendimiento completo, con sus 18 pruebas.
- Tema claro y oscuro con los tokens del sistema de diseño.

**Todavía no:**

- Registro de notas desde la web (el formulario del tutor).
- Emisión de notificaciones por el profesorado.
- Gestión de grupos, códigos de vinculación e importación CSV.
- Alta de centro y registro de familias.
- Auditoría y exportación de informes.

Las tablas de todo eso **ya existen** en la base de datos: falta la interfaz, no
el modelo. El reparto del trabajo, bloque a bloque, está en
[ARQUITECTURA.md](ARQUITECTURA.md).
