# TUTOLAR · arquitectura y reparto del trabajo

Este documento explica **cómo está organizado el proyecto** y reparte **qué
queda por hacer**. Si buscas cómo instalarlo, ve a [INSTALAR.md](INSTALAR.md).

---

## 1. El árbol

El proyecto usa el **árbol estándar de Laravel 12**, sin ninguna redirección de
rutas. Cualquiera que conozca Laravel sabe dónde está cada cosa sin que se lo
expliquen, y `php artisan` funciona tal cual sale de la caja.

```
TUTOLAR/
│
├── app/                    lógica de negocio y HTTP
│   ├── Models/             21 modelos Eloquent
│   ├── Services/           RendimientoService — el motor
│   ├── Support/            Nivel (índice → nivel) · Avatar
│   ├── Providers/          AppServiceProvider
│   └── Http/
│       ├── Controllers/
│       │   ├── Centro/     10 controladores + BaseCentroController
│       │   ├── Profesor/   Familia/   AuthController   RegistroController
│       └── Middleware/     EnsureRol — separa las tres zonas
│
├── routes/
│   ├── web.php             las tres zonas por rol
│   └── console.php
│
├── resources/views/        plantillas Blade
│   ├── layouts/            app (dentro) · acceso (pantallas públicas)
│   ├── partials/           avatar · nivel · segmentos · dial · barras-tipo
│   ├── auth/  centro/  profesor/  familia/
│
├── public/                 raíz web · index.php · css/tutolar.css · avatares/
│
├── database/
│   ├── migrations/         3 base de Laravel + 8 de TUTOLAR
│   ├── seeders/            TutolarSeeder — un instituto ficticio
│   └── factories/          UserFactory
│
├── docker/                 php/ · nginx/ · mariadb/
│
├── config/  bootstrap/  storage/
│
├── tests/
│   ├── Unit/               el motor de rendimiento
│   ├── Feature/            registro de notas, zona de dirección, vinculación
│   └── diseno/             paleta.js · blade.py · muestrario.html
│
└── docs/                   análisis, diseño, prototipos
```

**Antes esto estaba organizado en capas** (`backend/`, `frontend/`) con cinco
sobrescrituras de rutas repartidas entre `bootstrap/app.php`, `config/view.php`
y `composer.json`. Se deshizo: la separación por capas no daba nada que Laravel
no diera ya con `app/` y `resources/`, y a cambio obligaba a explicar cinco
líneas de pegamento antes de poder explicar el proyecto. Ahora `bootstrap/app.php`
solo declara una cosa propia: el alias del middleware `rol`.

> Si `bootstrap/app.php` vuelve a crecer, que sea por una razón del dominio, no
> por mover carpetas.

---

## 1 bis. El sistema de diseño

Toda la apariencia sale de `public/css/tutolar.css`, que define los tokens y
reescribe con ellos las variables de Bootstrap. La paleta es la de la marca:

| Color | Papel |
|---|---|
| `#053F5C` marino | tinta principal, barra lateral, serie de exámenes |
| `#429EBD` azul | serie de ejercicios, acentos secundarios |
| `#9FE7F5` cian | tinta sobre marino, fondos suaves |
| `#F7AD19` ámbar | **acción**: lo que se pulsa |

Y hay **dos reglas que ninguna vista se salta**:

**1. El ámbar es acción, nunca estado.** Aparece como relleno sólido en botones,
en la entrada activa del menú y en el anillo de foco, siempre con tinta marino
encima. Nunca es texto sobre fondo claro —da 1.92:1 sobre blanco— y nunca
describe cómo va un alumno.

**2. El nivel de rendimiento no depende solo del color.** Como el ámbar ocupa la
franja cálida que antes usaba el semáforo, los cuatro niveles se leen por tres
señales a la vez: **segmentos llenos**, **palabra** y **color**.

```
BAJO  ▮▯▯     MEDIO ▮▮▯     ALTO ▮▮▮     SIN DATOS ▯▯▯
```

Quítale el color a la pantalla —daltonismo, impresión en blanco y negro, un
proyector malo en una tutoría— y el nivel se sigue leyendo. Esa es la razón por
la que el semáforo dejó de llamarse semáforo: `App\Support\Nivel` sustituye a
`Semaforo`, y las constantes pasaron de `ROJO/NARANJA/VERDE/GRIS` a
`BAJO/MEDIO/ALTO/SIN_DATOS`. Un estado que se llama «naranja» y se pinta de ocre
es una mentira que envejece mal.

**Cómo se comprueba.** Nada de esto es cuestión de gusto, y hay dos programas
que lo verifican:

```bash
node tests/diseno/paleta.js     # contraste WCAG y ΔE2000, también en daltonismo
python3 tests/diseno/blade.py   # directivas, @include, bloques @php y clases de nivel
```

`paleta.js` comprueba 72 condiciones: contraste de cada tinta sobre cada
superficie en los dos temas, separación entre los cuatro niveles en visión
normal y en protanopia, deuteranopia y tritanopia, distancia del ámbar de acción
a cada nivel, separación de las dos series del gráfico entre sí y de los
niveles, y los colores de identidad de los avatares. **Antes de tocar un color,
ejecútalo.**

`tests/diseno/muestrario.html` enseña todas las piezas juntas en los dos temas.
Se abre en el navegador sin Laravel: solo depende de `tutolar.css`.

---

## 2. Estado real, capa por capa

### Backend — el más maduro

| Pieza | Estado |
|---|---|
| `RendimientoService` | ✅ completo · 18 pruebas, sin dependencias de Laravel |
| `Nivel` | ✅ completo · único sitio donde un índice se vuelve nivel y color |
| `EnsureRol` | ✅ separa las tres zonas |
| `AuthController` | ✅ acceso, sesión y salida |
| Panel del centro, alumnos, ficha | ✅ funcionan sobre datos reales |
| Zona de dirección completa | ✅ alta, edición y baja en tutores, profesores, asignaturas, grupos, grupos de asignatura, matrículas, cuentas y alumnos |
| Aislamiento entre centros | ✅ `BaseCentroController` + 17 pruebas de integración |
| Vista de profesorado y de familia | ✅ solo lectura |
| Registro de notas por la familia | ✅ `Familia\ResultadoController` · 15 pruebas de integración |
| Auditoría de escrituras | ✅ modelo `Auditoria`, se escribe al registrar una nota |
| Notificaciones del profesorado | ✅ 4 tipos · `Profesor\NotificacionController` + bandeja de la familia |
| Códigos de vinculación | ✅ el centro los genera, la familia se registra con ellos |
| Sesiones, asistencia y cobros | ❌ **sin tabla**: es dominio nuevo, no está en el análisis |
| Informes y exportación | ❌ |

**Deuda técnica identificada:**

1. `AlumnoController::index()` y `PanelController::index()` calculan el
   rendimiento **en PHP para todos los alumnos del centro** en cada petición.
   Con ~110 alumnos y unos miles de resultados es asumible; el propio código lo
   dice. A partir de unos miles de alumnos hay que leer la tabla
   `indices_rendimiento`, que ya existe y **nadie escribe todavía**.
2. La paginación de `AlumnoController` es manual (`slice`), no un `Paginator`.
   Funciona, pero no da enlaces ni cuenta con la URL.
3. El listado de alumnos sigue paginando a mano (`slice`) en vez de con un
   `Paginator`, porque ordena por un índice que se calcula en PHP. El resto de
   listados de la zona de dirección ya usan paginación real de Eloquent.

### Frontend — completo para lo que hay, sin nada para lo que falta

| Pieza | Estado |
|---|---|
| `layouts/app.blade.php` | ✅ barra lateral por rol, tema claro/oscuro |
| `partials/semaforo`, `partials/dial` | ✅ el color nunca viaja solo |
| Login, panel, listado, ficha, grupos, familia | ✅ 7 vistas |
| `public/css/tutolar.css` | ✅ tokens propios sobre variables de Bootstrap |
| Formulario de registro de notas | ✅ `familia/registro.blade.php`, dentro de la pantalla principal |
| Barra lateral de dirección | ✅ cuatro secciones: gestión, actividad académica, cobros y administración |
| Pantallas de gestión del centro | ✅ 24 vistas Blade con partials compartidos |
| Bandeja de notificaciones | ✅ `familia/notificaciones/` con confirmación de lectura |
| Bootstrap servido desde CDN | ⚠️ sin internet, la web se ve sin estilos |

**Decisión de diseño que conviene mantener:** no hay Node, ni Vite, ni npm. El
frontend es Blade + un CSS + tres funciones de JavaScript. Todo lo que falta
puede construirse sin añadir un paso de compilación.

### Database — la capa más adelantada respecto al resto

Las 6 migraciones de TUTOLAR ya modelan **cosas que la aplicación todavía no
usa**: `indices_rendimiento`. No es un descuido: el modelo se diseñó entero y
la interfaz ha ido llegando después.

**El esquema está organizado por dominios, uno por migración**, y el orden es el
de las dependencias: cada tabla se crea cuando ya existe todo aquello a lo que
apunta. No hay ninguna clave foránea añadida a posteriori con un `Schema::table`
suelto, así que la migración se lee de arriba abajo como se lee el modelo.

| # | Migración | Dominio | Tablas |
|---|---|---|---|
| 1 | `000100_create_centros_table` | El centro | `centros` |
| 2 | `000200_create_identidad_tables` | Identidad | `users` (ampliada), `profesores`, `tutores_legales` |
| 3 | `000300_create_estructura_academica_tables` | Estructura académica | `cursos`, `grupos`, `asignaturas`, `asignatura_curso` |
| 4 | `000400_create_alumnado_tables` | Alumnado | `alumnos`, `imparticiones`, `matriculas`, `tutelas`, `codigos_vinculacion` |
| 5 | `000500_create_evaluacion_tables` | Evaluación | `evaluables`, `resultados`, `indices_rendimiento` |
| 6 | `000600_create_comunicacion_tables` | Comunicación | `notificaciones`, `notificacion_destinatarios`, `auditorias` |

| Tabla | ¿La usa el código? |
|---|---|
| `centros`, `cursos`, `grupos`, `asignaturas` | ✅ |
| `users`, `profesores`, `tutores_legales` | ✅ |
| `alumnos`, `matriculas`, `tutelas`, `imparticiones` | ✅ |
| `evaluables`, `resultados` | ✅ lectura y escritura |
| `indices_rendimiento` | ❌ nadie escribe en ella |
| `notificaciones`, `notificacion_destinatarios` | ✅ emisión, reparto, lectura y confirmación |
| `codigos_vinculacion` | ✅ registro de familias |
| `cursos` | ✅ se gestionan desde la pantalla de Grupos |
| `asignatura_curso` | ✅ plan de estudios |
| `auditorias` | ✅ toda nota registrada deja su fila |

El esquema es portable: las 9 migraciones corren igual sobre MySQL/MariaDB y
sobre SQLite, y el seeder no usa una sola sentencia SQL específica de un motor.
Eso permite desarrollar sin servidor de base de datos (opción C de INSTALAR.md)
sin tocar ni una línea de código.

#### La política de borrado

Es la decisión de esquema que más conviene poder defender, porque aquí se
guardan calificaciones de menores. La regla es una línea:

> **El historial académico no desaparece en cascada. La configuración sí.**

| Se borra en cascada (se puede reconstruir) | Exige desvincular antes (`restrict`) |
|---|---|
| `asignatura_curso` — plan de estudios | `alumnos` → su grupo y su centro |
| `matriculas` — al borrar el alumno | `imparticiones` → profesor, asignatura y grupo |
| `notificacion_destinatarios` — el reparto de un aviso | `evaluables` → su impartición y quien lo creó |
| `indices_rendimiento` — caché del motor | `resultados` → su evaluable, su alumno y quien lo registró |
| `codigos_vinculacion` — token de un solo uso | `tutelas` → la familia y el alumno, **incluso desactivadas** |
| `profesores` y `tutores_legales` → su cuenta (misma persona) | `notificaciones` → el grupo al que se envió el aviso |

Dos matices que explican el reparto:

- **`notificaciones` es el hecho; `notificacion_destinatarios` es su proyección.**
  Que alguien avisara de algo un martes no se borra; a quién le llegó se puede
  regenerar. Si el examen se anula, `notificaciones.evaluable_id` pasa a `null`
  y el aviso sobrevive con su título y su fecha.
- **Una tutela no se borra: se desactiva.** Es la traza de quién pudo ver a qué
  menor y hasta cuándo. Por eso es la única N:M con `restrict` en los dos
  extremos, y por eso `TutorController` y `AlumnoController` cuentan *todas* las
  tutelas antes de permitir un borrado, no solo las activas.

`BaseCentroController::bloqueoPorDependencias()` se adelanta a todo esto y
explica en castellano qué hay que deshacer primero, de modo que el usuario ve un
aviso y no un error de clave foránea. Las restricciones del esquema son la red
de debajo: valen también para un seeder, una importación CSV o alguien con
phpMyAdmin abierto. `tests/Feature/IntegridadEsquemaTest.php` comprueba las dos
capas por separado.

#### Otros detalles del esquema

- **Unicidad garantizada, no solo validada.** `cursos(centro_id, denominacion)` y
  `asignaturas(centro_id, denominacion)` son índices únicos. La aplicación ya lo
  validaba, pero esa regla vivía únicamente en el controlador.
- **`tutelas.parentesco` es un ENUM**, no un `string(30)` libre donde convivían
  «MADRE», «Madre» y «madre». La lista canónica está en `App\Models\Tutela`,
  que es el modelo nuevo de esa tabla, y las vistas la leen de ahí.
- `resultados` tiene índice único `(evaluable_id, alumno_id, origen)`: un mismo
  examen puede tener a la vez el valor declarado por la familia y el verificado
  por el centro. Esa es la base de la regla RN-01.
- **`alumnos.centro_id` es redundante a propósito.** Se podría deducir por grupo
  → curso → centro; está en la fila para que el aislamiento entre centros sea un
  `where` directo y no un JOIN de tres saltos que alguien pueda olvidar.
- **Los índices siguen a las consultas reales**, no al azar: `alumnos` por
  `(centro_id, activo)` y `(centro_id, apellidos)`, que son el filtro y la
  ordenación del listado; `tutelas` por `(tutor_legal_id, activa)`, que es
  «¿qué hijos tengo?» y se ejecuta en cada pantalla de la zona de familia;
  `evaluables` por `(imparticion_id, estado)`, que es lo que busca el formulario
  de registro de notas.
- Los umbrales del nivel y los pesos viven en `centros`, no en el código.
- **`auditorias` no se toca nunca.** No tiene `updated_at`, no la borra ninguna
  cascada, y si la cuenta del autor desaparece se queda con `autor_id` a null:
  se pierde el enlace, no el hecho, porque el nombre está dentro del JSON.

### La zona de dirección, pantalla por pantalla

La barra lateral tiene cuatro secciones. Ocho entradas funcionan y una está a la
vista pero desactivada, porque **no tiene tabla en el modelo de datos**.
*Sesiones* y *Asistencia* estaban ahí en gris y se han retirado: una entrada de
menú que no lleva a ninguna parte promete una función que no existe, y en una
defensa eso es una pregunta que uno mismo se ha buscado.

| Sección | Entrada | Tabla | Estado |
|---|---|---|---|
| — | Panel | — | ✅ |
| Gestión | Alumnos | `alumnos` | ✅ listado, ficha, alta, edición, baja |
| Gestión | Tutores | `tutores_legales` | ✅ CRUD + vincular y desvincular alumnos |
| Gestión | Profesores | `profesores` | ✅ CRUD |
| Gestión | Asignaturas | `asignaturas` | ✅ CRUD + plan de estudios |
| Gestión | Plan de estudios | `asignatura_curso` | ✅ en qué cursos entra cada materia, desde la ficha de la asignatura |
| Actividad académica | Grupos | `grupos` + `cursos` | ✅ CRUD (los cursos se gestionan aquí) |
| Actividad académica | Grupos de asignatura | `imparticiones` | ✅ CRUD |
| Actividad académica | Matrículas | `matriculas` | ✅ alta individual, alta por grupo y baja |
| Cobros | Pagos | — | ❌ **sin modelo de datos** |
| Administración | Cuentas | `users` | ✅ alta de dirección, edición, activar y desactivar |

**El plan de estudios.** `asignatura_curso` dice en qué cursos entra cada
materia. Hasta esa migración la relación solo existía de rebote, a través de
`imparticiones`, lo que obligaba a asignar un docente para poder afirmar que
Física y Química no se da en 1º ESO — cuando eso es una decisión del plan de
estudios, no de la plantilla. Es N:M porque una materia entra en varios cursos.

De ahí salen tres restricciones, todas comprobadas en el servidor y sugeridas
en el navegador:

| Pantalla | Qué impide |
|---|---|
| Grupos de asignatura | asignar un docente a un grupo cuyo curso no contempla esa materia |
| Matrículas | matricular a un alumno en una materia que su curso no cursa |
| Alta y edición de alumno | marcar materias fuera del plan de estudios de su grupo |

#### El triángulo: asignatura · docencia · matrícula

Tres pantallas, tres tablas, y hasta ahora ninguna sabía de las otras dos:

| Tabla | Qué decide | Si falta |
|---|---|---|
| `asignaturas` + `asignatura_curso` | qué materias existen y en qué cursos entran | no hay nada que impartir |
| `imparticiones` | **quién da la clase** a qué grupo | no se pueden crear exámenes |
| `matriculas` | **quién la recibe** | el alumno no ve nada y su nivel no se mueve |

Las dos últimas son independientes por diseño —un profesor puede tener asignada
una clase antes de que se cierren las matrículas— pero crear una sin la otra
dejaba un hueco que nadie veía: una asignación sin matrículas permite programar
exámenes que ningún alumno recibirá, y una matrícula sin asignación deja al
alumno en gris para siempre.

Lo que cierra el triángulo:

- **El alta de un grupo de asignatura ofrece matricular al grupo** en el mismo
  paso, marcado por defecto. Si se desmarca y nadie cursa esa materia, el
  mensaje de confirmación lo dice en vez de felicitar.
- **El listado de grupos de asignatura tiene columna «Alumnos»**: verde si el
  grupo entero la cursa, naranja si va a medias, y rojo con un botón
  «Matricular al grupo» si no la cursa nadie. Son dos consultas agregadas por
  página, no dos por fila.
- **El listado de asignaturas cuenta y enlaza sus dos dependencias** — grupos de
  asignatura y alumnos matriculados. Son exactamente las que bloquean el
  borrado: antes se descubrían al pulsar Eliminar.
- **El alta masiva de matrículas filtra por plan de estudios** con el mismo
  JavaScript que ya usaba la pantalla de grupos de asignatura, así que ya no se
  puede elegir una combinación que el servidor va a rechazar.

**Una asignatura sin cursos marcados se considera disponible en todos.** Es lo
que mantiene el campo opcional: un centro que aún no ha rellenado su plan sigue
pudiendo asignar docentes y matricular. Y la migración **deduce el plan de las
imparticiones que ya existan**, así que una base de datos anterior no se queda
de golpe sin materias asignables.

Tres decisiones más que conviene poder defender:

**Nada se borra si algo cuelga de ello.** Lo cortan dos capas: el esquema, con
`restrict` en todo lo que es historial académico, y
`BaseCentroController::bloqueoPorDependencias()`, que se adelanta y explica qué
hay que deshacer primero. Está desarrollado en «La política de borrado».

**Las cuentas se desactivan, no se borran.** Borrar una cuenta arrastra su perfil
y con él la trazabilidad de quién registró cada nota. Y el rol de una cuenta no
se cambia: un usuario tiene exactamente un rol y su perfil cuelga de él.

**Cada escritura deja su fila en `auditorias`.** Alta, cambio y baja, con el
antes y el después. Se manejan datos de menores.

---

### Registro de familias con código de vinculación

Una familia puede crearse la cuenta sola desde la pantalla de acceso, pero no
del todo sola: hace falta un **código de vinculación** que el centro genera
desde la ficha del alumno.

Por qué, y esto es lo que hay que poder defender: un formulario de registro
abierto tendría que preguntar de qué alumno eres familia, y para eso tendría que
ofrecer una lista o un buscador de menores a un desconocido. Cualquiera con un
navegador podría vincularse a cualquier niño y ver sus notas. El código traslada
esa decisión a quien debe tomarla —el centro— y deja el formulario reducido a lo
que es: alguien demostrando que ya le autorizaron. **El código dice a la vez
quién es el alumno y de qué centro es**, así que el formulario no pregunta
ninguna de las dos cosas ni las acepta aunque lleguen en la petición.

Tres defensas, y las tres hacen falta:

| | |
|---|---|
| **Un solo uso** | al gastarse queda `usado_en` y deja de valer |
| **Caduca** | 14 días; un papel olvidado en una mochila deja de servir |
| **No se adivina** | 8 caracteres de un alfabeto de 31 → 8,5·10¹¹ combinaciones, de `random_int`, y 10 intentos por minuto |

El alfabeto excluye a propósito 0/O y 1/I/L: un código que la familia teclea mal
es una llamada al centro. Y se acepta escrito como venga —minúsculas, guiones,
espacios— porque quien lo copia de un papel escribe «kq7d-2xb9» tan a menudo
como «KQ7D2XB9».

El mensaje de error es **el mismo** para «no existe», «ya se usó» y «ha
caducado». Decir cuál de las tres es le diría a quien prueba códigos al azar
cuándo ha acertado uno.

#### ¿Y si la familia no tiene código?

La pregunta obvia de esa pantalla. Antes la respuesta era «pídeselo al centro»,
que fuera de la aplicación significa llamar en horario de secretaría. Ahora hay
un formulario público —**Pedir un código**— que deja arrancar el trámite a las
once de la noche y que el centro lo resuelva cuando abra.

Lo que ese formulario **no** hace es lo que lo hace defendible:

- **No comprueba si el alumno existe.** Los datos del alumno se guardan como
  texto suelto en `solicitudes_vinculacion`, sin clave foránea a `alumnos`. Si
  la solicitud tuviera que apuntar a un alumno real, el formulario tendría que
  buscarlo y decir si lo encuentra: una pantalla pública convertida en
  comprobador de matrículas, «¿está Lucía Ramos en este instituto?» respondido a
  cualquiera.
- **No da acceso a nada.** No crea cuenta, no crea tutela, no emite código. Deja
  una fila en la bandeja del centro.
- **No dice si acertaste.** El mensaje de después es idéntico se parezca lo
  escrito a un alumno real o no, y las dos solicitudes se guardan igual.
  Descartar automáticamente la que no cuadra sería decidir sin mirar los
  registros de verdad — y delataría el resultado.

El cruce lo hace una persona en **Gestión → Solicitudes**, que ve los candidatos
que se parecen a lo declarado y tiene que **señalar a cuál corresponde** antes de
aprobar. Aunque solo haya un candidato evidente, confirmarlo es lo que convierte
el clic en una decisión con nombre detrás, que es lo que queda en `auditorias`.
Al aprobar se emite el código; el mensaje recuerda entregarlo por un medio que el
centro ya tenga de esa familia, **no por el correo escrito en la solicitud**.

Descartar no avisa a quien la envió: un «rechazada» automático le diría que ese
alumno no está ahí, o que no ha colado, y las dos cosas son información que no se
le debe a alguien que no ha demostrado ser nadie.

El ciclo completo queda: **solicitar → el centro aprueba → código → registro →
cuenta vinculada**, y hay una prueba que lo recorre entero.

### Avatares y fotos de perfil

Toda persona de la aplicación tiene cara aunque no suba nada. Sin foto se
dibuja una silueta en SVG: **adulto** para dirección, profesorado y familias;
**niño** para el alumnado, con la cabeza proporcionalmente mayor y los hombros
más estrechos. La distinción no es decorativa: en la pantalla de una familia
conviven la madre y el hijo, y dos juegos de iniciales sobre dos círculos de
color no dicen cuál es cuál.

El color sale del nombre (`crc32`, no `rand`), así que la misma persona es
siempre del mismo color en cualquier pantalla y entre sesiones — que es lo que
convierte un adorno en una ayuda para reconocer. La paleta de seis tonos es
fría salvo el magenta y **evita a propósito el rojo, el naranja y el verde**:
esos tres significan estado del alumno y un avatar rojo se leería como alarma.

| Pieza | Dónde |
|---|---|
| Paleta, tamaños y elección de color | `App\Support\Avatar` |
| Qué sabe una persona de su avatar | `App\Models\Concerns\TieneAvatar` |
| El dibujo | `partials/avatar.blade.php` |
| Subir la foto propia | `PerfilController` · `/perfil` |
| Subir la del alumnado | el formulario de alumno, porque un menor no tiene cuenta |

Las fotos van al disco `avatares`, con raíz en `public/avatares`, y no
a `storage/app/public`: así no hacen falta ni `php artisan storage:link` ni los
permisos de administrador que ese enlace pide en Windows. **El nombre del
archivo lo pone la aplicación**, nunca el de la subida — un `virus.php`
renombrado a `.jpg` se guarda con nombre nuestro y extensión de imagen — y en
la base de datos se guarda solo ese nombre, no una ruta ni una URL.

### Dos familias por alumno, y no más

`Tutela::MAX_POR_ALUMNO = 2`. Es una decisión de producto —el modelo es el de
dos progenitores o tutores legales—, no una limitación técnica, y por eso vive
en una constante del modelo y no repetida en cada controlador. Cuentan solo las
tutelas **activas**: retirar una libera el sitio sin borrar la traza de quién
pudo ver a ese menor. En el alta de un tutor con varios alumnos, el que ya está
completo se salta y se avisa **por su nombre**, en vez de guardar a medias y
callarse.

### La zona de familia, pantalla por pantalla

| Pantalla | Ruta | Qué contesta |
|---|---|---|
| Avisos del centro | `familia.avisos.index` | qué me han comunicado |
| Resumen del hijo | `familia.inicio` | **cómo va** · índice, nivel por asignatura y registro de notas |
| Gráficos | `familia.rendimiento` | **por qué** · exámenes frente a ejercicios |

Son dos pantallas y no una tarjeta más en el inicio porque responden a preguntas
distintas y se miran de forma distinta: el resumen se consulta de pasada —«¿hay
algo en rojo?»— y los gráficos se miran una vez al trimestre, con tiempo, antes
de hablar con el tutor. Meterlo todo en una pantalla obliga a bajar por delante
del formulario de registro cada vez.

La elección del hijo y la comprobación de que es suyo viven en el trait
`Familia\Concerns\EligeAlumno`, compartido por las dos: el alumno se busca
**dentro de los tutelados**, así que un id ajeno en `?alumno=` no abre nada.
Está donde está para que saltárselo cueste.

### Los gráficos: exámenes frente a ejercicios

El motor ya separaba las dos medias —`calcularIra()` calcula
`media_examenes` y `media_tareas` y las pondera 60/40—, pero solo las usaba por
dentro. `RendimientoService::desglosePorTipo()` las publica, y el parcial
`partials/barras-tipo.blade.php` las dibuja: dos titulares con la media del curso
y un gráfico de barras agrupadas, una pareja por asignatura.

Tres decisiones que conviene poder defender:

**El color del gráfico no es el del nivel.** Las barras llevan marino `#053F5C`
(exámenes) y azul `#429EBD` (ejercicios): color de **identidad**, no de estado.
Los cuatro tonos de nivel significan una sola cosa en toda la aplicación;
usarlos aquí para decir «tipo de prueba» destruiría esa lectura. El par se
eligió comprobando —no a ojo, y el programa que lo comprueba está en
`tests/diseno/paleta.js`— que separa por encima del umbral en visión normal y en
las tres formas de daltonismo, y que ninguno de los dos se confunde con los
cuatro tonos de nivel ni con el ámbar de acción. El tema oscuro tiene sus
propios pasos, no un volteo de los claros.

**El desglose no aplica el mínimo de dos resultados.** El índice sí: sin datos
suficientes no se emite juicio. Pero esto no es un juicio, es una media
descriptiva que se enseña con cuántas notas la sostienen —«77,8 · 11 notas»—
para que la familia vea de dónde sale el número. Con una sola nota el rótulo lo
dice: *una sola nota da poca idea*.

**La cifra va al lado de cada barra y hay tabla equivalente.** Misma regla que el
nivel: el color nunca viaja solo. El gráfico se lee impreso en blanco y negro.

---

### Docker — nuevo

`docker compose up -d --build` levanta PHP-FPM 8.3, Nginx, MariaDB 11.4 y
phpMyAdmin. Los puertos están elegidos para **no chocar con XAMPP**:

| Servicio | Puerto | XAMPP usa |
|---|---|---|
| Web (Nginx) | 8080 | 80 |
| phpMyAdmin | 8081 | 80/phpmyadmin |
| MariaDB | 3307 | 3306 |

`vendor/` se deja en el disco del host a propósito, no en un volumen de Docker:
así una sola instalación de dependencias sirve para las dos formas de arrancar.

---

## 3. Reparto del trabajo

El orden importa: **1 es el que convierte esto en un producto**, el resto son
mejoras sobre algo que ya funciona.

### Bloque 1 · Registro de notas por la familia — ✅ hecho

Era el ciclo central: sin esto, la base de datos nunca se llenaba sola.

Lo que se construyó, y dónde:

| Capa | Archivo |
|---|---|
| backend | `app/Http/Controllers/Familia/ResultadoController.php` |
| backend | `app/Models/Auditoria.php` |
| backend | `routes/web.php` → `POST /familia/resultados` |
| backend | `InicioController::evaluablesPendientes()` |
| frontend | `resources/views/familia/registro.blade.php` |
| tests | `tests/Feature/RegistroResultadoTest.php` — 15 pruebas |

**Las cuatro comprobaciones antes de escribir**, en este orden: tutela sobre el
alumno, pertenencia del evaluable al grupo y a una asignatura matriculada, rango
frente a `puntuacion_maxima`, y unicidad de la nota declarada. El middleware
`rol:TUTOR_LEGAL` solo dice «eres una familia»; que seas *la* familia de ese
alumno lo comprueba el controlador contra la relación de tutela.

**RN-01 implementada**: si el centro ya había verificado un valor distinto, la
nota declarada entra como `DISCREPANCIA` y conviven las dos filas. No se pisa
el dato del centro ni se rechaza el de la familia.

Lo que quedó fuera a propósito: el estado del `evaluable` no cambia a
`REGISTRADO`, porque ese estado es de la prueba entera del grupo y una sola
familia no puede decidirlo. Y no se dispara alerta de rendimiento al cruzar a
rojo: eso depende del bloque 3, que es quien persiste los índices.

<details>
<summary>Plan original</summary>

| Capa | Tarea |
|---|---|
| database | Ninguna. `resultados` y `evaluables` ya están. |
| backend | `Familia\ResultadoController@create/store`. Validar contra `evaluables.puntuacion_maxima`. Comprobar la tutela antes de nada. Escribir en `auditorias`. |
| backend | Ruta `POST /familia/resultados` con `rol:TUTOR_LEGAL`. |
| frontend | Formulario en `familia/`: evaluable, puntuación, comentario. |
| tests | Que un tutor **no** pueda registrar una nota de un alumno que no tutela. |

</details>

### Bloque 2 · Notificaciones del profesorado — ✅ hecho

Cuatro tipos, y no son cuatro variantes del mismo mensaje: **dos crean algo y
dos cierran algo**.

| Lo que notifica el profesor | `tipo` | Qué hace con el evaluable |
|---|---|---|
| Hay examen | `EXAMEN_PROGRAMADO` | lo **crea**, en estado `NOTIFICADO` |
| Hay tarea | `TAREA_ASIGNADA` | lo **crea**, en estado `NOTIFICADO` |
| Notas del examen entregadas | `RESULTADOS_EXAMEN` | lo **elige** y lo pasa a `PENDIENTE_REGISTRO` |
| Ejercicios resueltos y corregidos | `RESULTADOS_TAREA` | lo **elige** y lo pasa a `PENDIENTE_REGISTRO` |

Con esto **se cierra el ciclo del producto**: el profesor programa el examen →
lo corrige y avisa → la familia lo ve en su bandeja → apunta la nota → el motor
recalcula el nivel. Hasta ahora los `evaluables` solo los creaba el seeder.

| Capa | Archivo |
|---|---|
| backend | `app/Models/Notificacion.php`, `NotificacionDestinatario.php` |
| backend | `app/Http/Controllers/Profesor/NotificacionController.php` |
| backend | `app/Http/Controllers/Familia/NotificacionController.php` |
| frontend | `profesor/notificaciones/{index,form}.blade.php` |
| frontend | `familia/notificaciones/index.blade.php` |
| tests | `tests/Feature/NotificacionTest.php` — 13 pruebas |

**El destinatario no es «el grupo».** Es cada familia de cada alumno del grupo
que además curse esa asignatura: un aviso de Matemáticas no le llega a quien no
la cursa. El reparto (fan-out) materializa una fila por pareja (familia,
alumno), que es lo que permite responder «¿quién lo ha confirmado?» con una
consulta trivial. Los alumnos sin familia vinculada se cuentan aparte y el
profesor lo ve en el acuse: «a esos no les llega».

**Leída y confirmada son cosas distintas.** Leída la marca el sistema al abrir
la bandeja; confirmada la marca la familia a mano, y es la que vale como «me he
enterado».

El selector es curso → grupo → materia, en cascada, alimentado solo con las
imparticiones del profesor. El servidor lo vuelve a comprobar: un
`imparticion_id` manipulado da 403.

### Bloque 3 · Índices persistidos

Quita la carga de calcular todo en cada petición y habilita la tendencia real.

| Capa | Tarea |
|---|---|
| backend | Comando `tutolar:recalcular-indices` que escriba en `indices_rendimiento`. |
| backend | Que `PanelController` y `AlumnoController` lean la instantánea en vez de recalcular. |
| database | Ninguna. |
| docker | Un servicio `scheduler` con `php artisan schedule:work`. |

### Bloque 4 · Gestión: grupos, códigos, importación CSV

| Capa | Tarea |
|---|---|
| backend | `Centro\GrupoController`. Los `codigos_vinculacion` ya están hechos. |
| backend | Importación CSV de alumnos con validación por filas. |
| frontend | Las dos entradas del menú que ahora están en gris. |

### Bloque 5 · Alta de centro y registro de familias

| Capa | Tarea |
|---|---|
| backend | Registro con código de vinculación: canjea el código y crea la tutela. |
| frontend | Pantallas de registro y de canje. |

### Bloque 6 · Calidad, transversal

| Capa | Tarea |
|---|---|
| tests | Pruebas de integración de aislamiento: centro↔centro, tutor↔tutor. |
| backend | Sustituir la paginación manual por `Paginator`. |
| frontend | Descargar Bootstrap a `public/css/` para no depender del CDN. |
| docker | Etapa de producción en el Dockerfile: `--no-dev`, `config:cache`, `route:cache`. |

---

## 4. Las tres reglas que el código hace cumplir

Se documentan aquí porque cualquier cosa que se añada tiene que respetarlas.

**1. Sin datos suficientes, sin juicio.** Con menos de dos resultados,
`RendimientoService::calcularIra()` devuelve `valor: null` y nivel `SIN_DATOS`, y
`Nivel::cifra(null)` devuelve un guion. Cero y «no sabemos» son cosas distintas,
y confundirlas alarma a una familia sin motivo.

**2. El color nunca viaja solo.** `partials/nivel.blade.php` renderiza siempre
barra + tres segmentos + palabra, y la cifra al lado. La web sigue siendo
legible impresa en blanco y negro, que es como acaba en más de una tutoría.

**3. El profesor no introduce notas.** No existe ninguna ruta que permita a un
usuario con rol `PROFESOR` crear un `Resultado`. Es una restricción de diseño.

---

*Todos los nombres de alumnos, familias, profesores y centros que genera el
seeder son ficticios.*
