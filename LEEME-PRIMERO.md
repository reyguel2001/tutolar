# Empieza por aquí

## Arrancar

Doble clic en **`ARRANCAR.bat`**.

Eso es todo. El archivo se encarga de lo demás: busca PHP —en el PATH y, si no,
en `C:\xampp\php\php.exe`—, instala las dependencias si falta `vendor\`, limpia
la caché, aplica las migraciones pendientes y te pregunta cómo quieres servir la
aplicación:

- **[1] Servidor de Laravel** — no necesita XAMPP. Abre `http://localhost:8000`.
- **[2] Apache de XAMPP** — arráncalo en el panel y abre
  `http://localhost/SORA/tutolarV3/public`.

Se puede ejecutar las veces que haga falta: lo que ya está hecho se lo salta.

Entra con `direccion@iescervantes.edu.es`, contraseña `tutolar2026`.

---

## Si prefieres hacerlo a mano

```bash
cd C:\xampp\htdocs\SORA\tutolarV3
composer install
php artisan config:clear
php artisan view:clear
php artisan serve
```

El `composer.lock` que hay aquí es el tuyo de siempre, así que `composer install`
instala exactamente los mismos 76 paquetes: no actualiza ninguna versión. Lo
único que cambió en `composer.json` fue la ruta del autoload, y Composer no la
incluye en el hash del lock.

Si en vez de instalar prefieres **copiar `vendor\`** desde
`C:\xampp\htdocs\SORA\TUTOLAR`, también vale — pero entonces hace falta
`composer dump-autoload`, porque ese `vendor\` todavía busca las clases en
`backend/app`. `ARRANCAR.bat` lo detecta y lo hace solo.

---

## Esta carpeta ya viene montada

No hay que fusionarla con nada ni borrar nada antes. Ese fue el problema las dos
veces anteriores: al copiar encima de la carpeta antigua sobrevivían ficheros que
ya no debían estar, y eran justo los que Laravel acababa cargando.

Aquí dentro ya están:

- el código reorganizado al árbol estándar de Laravel;
- tu **`.env`**, con **tu misma `APP_KEY`** — las sesiones y las contraseñas
  siguen valiendo;
- tu **base de datos** con los 108 alumnos, 647 matrículas y 1815 resultados,
  ya migrada al esquema nuevo;
- `storage/` con todas sus carpetas;
- `composer.lock`.

Falta una sola cosa que no viaja aquí: la carpeta **`docs\`**. Los cinco `.md`
los recuperé del proyecto de Claude y están en la carpeta vieja, pero los
diagramas HTML, el PDF y el DOCX pesan 14 MB y siguen solo allí. Cópiala desde
`C:\xampp\htdocs\SORA\TUTOLAR\docs` cuando quieras.

**No borres `TUTOLAR` ni `tutolarV2` hasta que esto funcione.**

---

## Comprobar que está todo bien

```bash
php artisan test
node tests/diseno/paleta.js      # 72 comprobaciones de color y contraste
python3 tests/diseno/blade.py    # 45 vistas
```

Y abre `tests/diseno/muestrario.html` en el navegador: enseña el sistema de
diseño entero en tema claro y oscuro, sin necesidad de levantar nada.

---

## Qué cambió respecto a la versión de agosto

- **Estructura**: `app/`, `routes/`, `resources/`, `public/` en la raíz.
  Desaparecen `backend/` y `frontend/` y las cinco redirecciones de rutas que
  hacían falta para sostenerlas. `bootstrap/app.php` ya solo declara el alias del
  middleware `rol`.
- **Color**: paleta de marca `#053F5C` · `#429EBD` · `#9FE7F5` · `#F7AD19`.
  El ámbar es acción —lo que se pulsa—, nunca estado.
- **El semáforo es ahora un nivel.** `App\Support\Nivel` sustituye a `Semaforo`,
  y `ROJO/NARANJA/VERDE/GRIS` pasan a `BAJO/MEDIO/ALTO/SIN_DATOS`. Cada nivel se
  lee por tres señales a la vez, no solo por el color:

  ```
  BAJO ▮▯▯    MEDIO ▮▮▯    ALTO ▮▮▮    SIN DATOS ▯▯▯
  ```

- **Base de datos**: `centros.umbral_rojo` → `umbral_bajo`,
  `centros.umbral_verde` → `umbral_alto`, `indices_rendimiento.color` → `nivel`.
  Ya está aplicado en la base de datos que viene aquí, y la migración
  `2026_09_04_000100` está registrada, así que `php artisan migrate` no intentará
  repetirlo.

El detalle está en `README.md` y en `ARQUITECTURA.md`.
