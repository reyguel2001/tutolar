# TUTOLAR Web

Plataforma de seguimiento del rendimiento escolar. Vincula a las familias con el
centro educativo: el profesorado avisa, la familia registra los resultados y el
sistema calcula un índice de rendimiento que se muestra como un nivel de tres
segmentos.

- **Instalar** → [INSTALAR.md](INSTALAR.md)
- **Arquitectura y reparto del trabajo** → [ARQUITECTURA.md](ARQUITECTURA.md)

```bash
composer install
copy .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve                 # http://localhost:8000
```

> **Si ya tenías la base de datos creada**, usa `migrate:fresh --seed` y no
> `migrate`. El esquema se reorganizó por dominios y las migraciones antiguas ya
> no existen; el seeder vuelve a dejar el instituto de demostración completo.
> Si no quieres perder los datos, `php artisan migrate` aplica la migración
> `2026_09_04_000100_renombrar_semaforo_a_nivel`, que renombra las columnas del
> semáforo sin tocar nada más.

O, si prefieres Docker:

```bash
docker compose up -d --build      # http://localhost:8080
```

**¿Sin MySQL?** SQLite no necesita servidor: dos líneas en el `.env` y funciona
igual. Está explicado en [INSTALAR.md, opción C](INSTALAR.md#c--sin-base-de-datos-mysql-sqlite).

---

## Lenguajes y librerías

| Capa | Tecnología | Por qué |
|---|---|---|
| Lenguaje | **PHP 8.2+** | Es lo que sirve XAMPP. Tipado estricto, enums y propiedades promocionadas |
| Framework | **Laravel 12** | Rutas, ORM, migraciones, validación y autenticación resueltos |
| Plantillas | **Blade** | Viene con Laravel; herencia de plantillas y componentes sin compilación |
| ORM | **Eloquent** | Incluido en Laravel |
| Base de datos | **MariaDB / MySQL** | La de XAMPP; en Docker, MariaDB 11.4 |
| Estilos | **Bootstrap 5.3** + capa propia | Retícula y formularios de Bootstrap, colores y componentes de TUTOLAR |
| JavaScript | **Vanilla, sin framework** | La web es formularios y tablas: React aquí sobraría |
| Gráficos | **SVG generado en Blade** | Sin librería de gráficos: el dial son dos círculos |
| Pruebas | **PHPUnit** (incluido) | 22 del motor + 18 de la zona de familia + 38 de dirección + 13 de avisos + 13 de integridad del esquema + 15 del registro de familias + 13 de solicitudes de código |
| Diseño | **Comprobadores propios** | `tests/diseno/` verifica contraste, daltonismo y plantillas sin levantar Laravel |
| Infraestructura | **Docker Compose** | PHP-FPM + Nginx + MariaDB + phpMyAdmin, opcional |

**Sin paso de compilación en el frontend.** No hay Node, ni Vite, ni npm. Fue
una decisión deliberada: en un proyecto que se defiende y se entrega, cada
herramienta añadida es una cosa más que puede fallar el día de la demostración.

---

## Estructura

Árbol estándar de Laravel 12, sin redirecciones de rutas:

```
app/                lógica de negocio y HTTP
├── Models/             21 modelos con sus relaciones
├── Services/
│   └── RendimientoService.php   ⭐ el motor: IRA, IRG, tendencia, ISF, alertas
├── Support/
│   ├── Nivel.php                ⭐ único sitio donde un índice se vuelve nivel
│   └── Avatar.php               identidad visual de las personas
└── Http/
    ├── Controllers/    Auth + Registro + Centro (10), Profesor y Familia
    └── Middleware/
        └── EnsureRol.php        separa las tres zonas de la aplicación

routes/             web.php · console.php
resources/views/    layouts, partials, auth, centro, profesor, familia
public/             raíz web: index.php, .htaccess, css/tutolar.css, avatares/

database/
├── migrations/      3 base de Laravel + 8 de TUTOLAR, una por dominio
├── seeders/         TutolarSeeder — un instituto ficticio completo
└── factories/

tests/
├── Unit/  Feature/  las pruebas de PHPUnit
└── diseno/          paleta.js · blade.py · muestrario.html

docker/             php/ · nginx/ · mariadb/
config/  bootstrap/  storage/  docs/
```

**Esto era una estructura por capas** (`backend/`, `frontend/`) que obligaba a
sobrescribir cinco rutas de Laravel repartidas por tres ficheros. Se deshizo
porque no aportaba nada que `app/` y `resources/` no dieran ya, y porque cinco
líneas de pegamento son cinco sitios donde algo puede dejar de encontrarse.
`bootstrap/app.php` ya solo declara el alias del middleware `rol`.

---

## El sistema de diseño

La paleta es la de la marca, y cada color tiene un papel único:

| Color | Papel |
|---|---|
| `#053F5C` marino | tinta principal, barra lateral, serie de exámenes |
| `#429EBD` azul | serie de ejercicios, acentos secundarios |
| `#9FE7F5` cian | tinta sobre marino, fondos suaves |
| `#F7AD19` ámbar | **acción**: lo que se pulsa |

Y de ahí salen las dos reglas que ninguna vista se salta:

**El ámbar es acción, nunca estado.** Relleno sólido en botones, en la entrada
activa del menú y en el anillo de foco, siempre con tinta marino encima. Nunca
es texto sobre fondo claro —da 1.92:1 sobre blanco— y nunca dice cómo va un
alumno.

**El nivel no depende solo del color.** Como el ámbar ocupa la franja cálida que
antes usaba el semáforo, cada nivel se lee por tres señales a la vez:

```
BAJO  ▮▯▯     MEDIO ▮▮▯     ALTO ▮▮▮     SIN DATOS ▯▯▯
```

Por eso el semáforo dejó de llamarse así: `App\Support\Nivel` sustituye a
`Semaforo` y las constantes pasaron de `ROJO/NARANJA/VERDE/GRIS` a
`BAJO/MEDIO/ALTO/SIN_DATOS`. Un estado llamado «naranja» que se pinta de ocre es
una mentira que envejece mal.

Nada de esto es cuestión de gusto, y hay dos programas que lo comprueban sin
necesidad de levantar Laravel:

```bash
node tests/diseno/paleta.js      # 72 comprobaciones: contraste WCAG y ΔE2000,
                                 # también en las tres formas de daltonismo
python3 tests/diseno/blade.py    # directivas, @include, bloques @php y clases
```

`tests/diseno/muestrario.html` enseña todas las piezas juntas en los dos temas;
se abre en el navegador sin servidor.

---

## Las tres reglas que el código hace cumplir

**1. Sin datos suficientes, sin juicio.** Con menos de dos resultados,
`RendimientoService::calcularIra()` devuelve `valor: null` y nivel `SIN_DATOS`.
La interfaz no puede inventar un cero: `Nivel::cifra(null)` devuelve un guion.
Sin esta regla, la aplicación marcaría como flojo a un alumno del que no sabe
nada.

**2. El color nunca viaja solo.** El parcial `partials/nivel.blade.php` renderiza
siempre barra + tres segmentos + palabra, y la cifra va al lado. La web sigue
siendo legible impresa en blanco y negro, que es como acaba en más de una junta
de evaluación.

**3. El profesor no introduce notas.** No existe ningún controlador ni ruta que
permita a un usuario con rol `PROFESOR` crear un `Resultado`. Es una restricción
de diseño, no una carencia.

---

## El motor de rendimiento

Toda la lógica de negocio vive en `app/Services/RendimientoService.php`, que no
depende de Laravel ni toca la base de datos. Eso permite probarlo sin levantar
nada:

```bash
php artisan test --filter=RendimientoTest    # el motor
php artisan test                             # todo, incluido el registro de notas
```

```
IRA = (M_ex × 0,60 + M_ta × 0,40) / (0,60 + 0,40)
IRG = Σ(IRA × horas_semanales) / Σ(horas_semanales)
peso_recencia = 0,5 ^ (días / 90)
```

Los pesos y los umbrales son configurables por centro y se leen de la tabla
`centros`, así que un centro puede decidir que el nivel bajo acaba en 40 y no en
50.

---

## Qué falta

La base de datos ya contempla notificaciones, códigos de vinculación,
discrepancias entre nota declarada y verificada, y auditoría. Lo que falta es la
interfaz de esas partes:

1. Importación CSV de alumnos.
2. Alta de centro.
3. Exportación e informes.
4. Aviso al centro cuando llega una solicitud de vinculación: hoy hay que entrar
   a mirar la bandeja. Necesitaría configuración de correo, que el proyecto no
   usa.

**El ciclo del producto está cerrado**: el profesor programa un examen desde su
pantalla de avisos, lo corrige y notifica que hay nota, la familia lo ve en su
bandeja y la apunta, y el motor recalcula el nivel. Además, la zona de dirección
permite dar de alta, editar y dar de baja alumnos, tutores, profesores,
asignaturas, grupos, grupos de asignatura, matrículas y cuentas, y resolver las
solicitudes de código que llegan desde la web pública.

El desglose por capas, con las tareas concretas de cada bloque, está en
[ARQUITECTURA.md](ARQUITECTURA.md#3-reparto-del-trabajo).

---

*Todos los nombres de alumnos, familias, profesores y centros que genera el
seeder son ficticios.*
