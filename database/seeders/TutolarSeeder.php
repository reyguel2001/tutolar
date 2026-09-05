<?php

namespace Database\Seeders;

use App\Models\Alumno;
use App\Models\Asignatura;
use App\Models\Centro;
use App\Models\Curso;
use App\Models\Evaluable;
use App\Models\Grupo;
use App\Models\Imparticion;
use App\Models\Profesor;
use App\Models\Resultado;
use App\Models\TutorLegal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Datos ficticios de un instituto completo.
 *
 * Todos los nombres son inventados. La distribución de notas está sesgada a
 * propósito para que el panel enseñe los cuatro estados del semáforo, incluido
 * el gris: hay alumnos sin familia vinculada y por tanto sin resultados.
 */
class TutolarSeeder extends Seeder
{
    private const NOMBRES_H = ['Diego', 'Marcos', 'Iván', 'Pablo', 'Jorge', 'Raúl', 'Hugo', 'Álvaro', 'Nicolás', 'Adrián'];
    private const NOMBRES_M = ['Lucía', 'Alba', 'Nerea', 'Carla', 'Elena', 'Sara', 'Marta', 'Irene', 'Julia', 'Noa'];
    private const APELLIDOS = ['Martín Sáez', 'Bernal Ruiz', 'Cano Prieto', 'Fuentes Gil', 'Gil Peña',
                               'Robles Sanz', 'Torres Vidal', 'Esteban Mora', 'Vega Blanco', 'Lara Nieto',
                               'Serrano Paz', 'Rivas Ortiz', 'Molina Cruz', 'Herrera Lima', 'Pardo Solís'];

    /**
     * Cuenta de familia con correo fijo, para poder documentarla igual que las
     * del centro y el profesorado. Se la queda la primera familia que se crea.
     * El resto siguen con el correo generado.
     */
    public const CORREO_FAMILIA_DEMO = 'familia@iescervantes.edu.es';

    private bool $familiaDemoPendiente = true;

    public function run(): void
    {
        // Determinista: dos ejecuciones producen los mismos datos, lo que hace
        // que las capturas de la memoria no cambien entre una y otra.
        mt_srand(20260821);

        $centro = Centro::create([
            'denominacion' => 'IES Miguel de Cervantes',
            'cif'          => 'Q2868001B',
            'direccion'    => 'Calle Mayor 14, 28013 Madrid',
            'email'        => 'secretaria@iescervantes.edu.es',
            'telefono'     => '910000000',
        ]);

        // ---------- Cuenta de dirección ----------
        User::create([
            'name'      => 'María Delgado',
            'email'     => 'direccion@iescervantes.edu.es',
            'password'  => Hash::make('tutolar2026'),
            'rol'       => User::ROL_CENTRO,
            'centro_id' => $centro->id,
            'telefono'  => '910000001',
        ]);

        // ---------- Asignaturas ----------
        // El tercer elemento es el plan de estudios: en qué cursos entra la
        // materia. `null` significa «en todos», que es lo habitual en la ESO.
        $catalogo = [
            ['Matemáticas',          4, null],
            ['Lengua Castellana',    4, null],
            ['Inglés',               3, null],
            ['Geografía e Historia', 3, null],
            ['Biología y Geología',  3, ['1º ESO', '3º ESO', '4º ESO']],
            ['Física y Química',     3, ['2º ESO', '3º ESO', '4º ESO']],
        ];

        $asignaturas = collect($catalogo)->map(fn ($a) => Asignatura::create([
            'centro_id'       => $centro->id,
            'denominacion'    => $a[0],
            'horas_semanales' => $a[1],
        ]));

        $planEstudios = collect($catalogo)->mapWithKeys(fn ($a) => [$a[0] => $a[2]])->all();

        // ---------- Profesorado ----------
        $docentes = [
            ['Javier', 'Ortega',  'Matemáticas'],
            ['Marta',  'Ruiz',    'Lengua Castellana'],
            ['Sara',   'López',   'Inglés'],
            ['Ana',    'Delgado', 'Biología y Geología'],
            ['Pablo',  'Vega',    'Geografía e Historia'],
            ['Elena',  'Sanz',    'Física y Química'],
        ];

        $profesores = [];
        foreach ($docentes as [$nombre, $apellidos, $materia]) {
            $correo = mb_strtolower($nombre . '.' . explode(' ', $apellidos)[0]) . '@iescervantes.edu.es';
            $correo = $this->sinAcentos($correo);

            $user = User::create([
                'name'      => "$nombre $apellidos",
                'email'     => $correo,
                'password'  => Hash::make('tutolar2026'),
                'rol'       => User::ROL_PROFESOR,
                'centro_id' => $centro->id,
            ]);

            $profesores[$materia] = Profesor::create([
                'user_id'   => $user->id,
                'centro_id' => $centro->id,
                'nombre'    => $nombre,
                'apellidos' => $apellidos,
            ]);
        }

        // ---------- Cursos y grupos ----------
        $estructura = [
            '1º ESO' => ['A', 'B'],
            '2º ESO' => ['A', 'C'],
            '3º ESO' => ['A', 'B'],
            '4º ESO' => ['A', 'B'],
        ];

        $grupos = [];
        $asignaturasPorGrupo = [];

        foreach ($estructura as $denominacion => $letras) {
            $curso = Curso::create([
                'centro_id'      => $centro->id,
                'denominacion'   => $denominacion,
                'anio_academico' => '2026/2027',
            ]);

            // Las materias que entran en este curso: las que no restringen y
            // las que lo incluyen. Física y Química no se da en 1º, y Biología
            // y Geología no se da en 2º.
            $delCurso = $asignaturas->filter(function ($a) use ($planEstudios, $denominacion) {
                $cursos = $planEstudios[$a->denominacion] ?? null;

                return $cursos === null || in_array($denominacion, $cursos, true);
            })->values();

            $curso->asignaturas()->attach($delCurso->pluck('id'));

            foreach ($letras as $letra) {
                $grupo = Grupo::create([
                    'curso_id'       => $curso->id,
                    'denominacion'   => $letra,
                    'tutor_grupo_id' => $profesores['Lengua Castellana']->id,
                ]);

                // Cada grupo recibe las asignaturas de su curso con su docente.
                foreach ($delCurso as $asignatura) {
                    Imparticion::create([
                        'profesor_id'   => $profesores[$asignatura->denominacion]->id,
                        'asignatura_id' => $asignatura->id,
                        'grupo_id'      => $grupo->id,
                    ]);
                }

                $asignaturasPorGrupo[$grupo->id] = $delCurso;
                $grupos[] = $grupo;
            }
        }

        // ---------- Alumnado, familias y resultados ----------
        $usados = [];
        foreach ($grupos as $indiceGrupo => $grupo) {
            $delGrupo = $asignaturasPorGrupo[$grupo->id];
            $cuantos  = mt_rand(12, 16);

            for ($i = 0; $i < $cuantos; $i++) {
                [$nombre, $apellidos] = $this->nombreUnico($usados);

                $alumno = Alumno::create([
                    'centro_id'        => $centro->id,
                    'grupo_id'         => $grupo->id,
                    'nombre'           => $nombre,
                    'apellidos'        => $apellidos,
                    'fecha_nacimiento' => now()->subYears(mt_rand(12, 17))->subDays(mt_rand(0, 364))->toDateString(),
                ]);

                // Solo las materias que se imparten en su curso.
                $alumno->asignaturas()->attach($delGrupo->pluck('id'));

                // Un 10 % de alumnos se queda sin familia vinculada: es el caso
                // que el centro tiene que resolver y debe verse en el panel.
                $sinFamilia = mt_rand(1, 100) <= 10;

                if (! $sinFamilia) {
                    $this->crearFamilia($alumno, $nombre, $apellidos);
                    $this->crearResultados($alumno, $grupo, $delGrupo, $profesores);
                }
            }
        }

        $this->command?->info('Centro creado con ' . Alumno::count() . ' alumnos y ' . Resultado::count() . ' resultados.');
        $this->command?->info('Cuentas de prueba (contraseña tutolar2026):');
        $this->command?->info('  Centro      direccion@iescervantes.edu.es');
        $this->command?->info('  Profesorado javier.ortega@iescervantes.edu.es');
        $this->command?->info('  Familia     ' . self::CORREO_FAMILIA_DEMO);
    }

    private function crearFamilia(Alumno $alumno, string $nombreHijo, string $apellidos): void
    {
        $nombreTutor = self::NOMBRES_M[array_rand(self::NOMBRES_M)];
        $correo = $this->sinAcentos(mb_strtolower($nombreTutor . '.' . explode(' ', $apellidos)[0] . $alumno->id)) . '@email.com';

        // La primera familia se lleva el correo fijo de demostración: es la que
        // aparece documentada en INSTALAR.md. No se toca el generador de
        // aleatorios, así que el resto de los datos siguen siendo idénticos.
        if ($this->familiaDemoPendiente) {
            $correo = self::CORREO_FAMILIA_DEMO;
            $this->familiaDemoPendiente = false;
        }

        $user = User::create([
            'name'     => "$nombreTutor " . $apellidos,
            'email'    => $correo,
            'password' => Hash::make('tutolar2026'),
            'rol'       => User::ROL_TUTOR_LEGAL,
            'centro_id' => $alumno->centro_id,
            'telefono'  => '6' . mt_rand(10000000, 99999999),
        ]);

        $tutor = TutorLegal::create([
            'user_id'   => $user->id,
            'nombre'    => $nombreTutor,
            'apellidos' => $apellidos,
            'telefono'  => $user->telefono,
        ]);

        DB::table('tutelas')->insert([
            'tutor_legal_id' => $tutor->id,
            'alumno_id'      => $alumno->id,
            'parentesco'     => 'MADRE',
            'vinculado_en'   => now(),
            'activa'         => true,
        ]);
    }

    /**
     * Genera exámenes y tareas con notas verosímiles.
     *
     * Cada alumno recibe un «nivel» base y cada asignatura una desviación, de
     * modo que aparezcan perfiles reales: alumnos buenos con una asignatura
     * atragantada, alumnos justos que remontan, y alguno con una sola nota
     * (que el motor dejará en gris).
     */
    private function crearResultados(Alumno $alumno, Grupo $grupo, $asignaturas, array $profesores): void
    {
        $nivelBase = mt_rand(30, 88);

        foreach ($asignaturas as $asignatura) {
            $imparticion = Imparticion::where('grupo_id', $grupo->id)
                ->where('asignatura_id', $asignatura->id)
                ->first();

            if (! $imparticion) {
                continue;
            }

            $desviacion = mt_rand(-22, 18);
            $nivel = max(5, min(98, $nivelBase + $desviacion));

            // Una de cada seis asignaturas se queda con un solo resultado, para
            // que el estado gris aparezca de verdad en la interfaz.
            $cuantos = mt_rand(1, 6) === 1 ? 1 : mt_rand(2, 5);

            for ($k = 0; $k < $cuantos; $k++) {
                $esExamen = $k % 2 === 0;
                $dias = mt_rand(2, 88);

                $evaluable = Evaluable::create([
                    'imparticion_id'    => $imparticion->id,
                    'tipo'              => $esExamen ? 'EXAMEN' : 'TAREA',
                    'titulo'            => $esExamen
                        ? 'Examen · Tema ' . ($k + 1)
                        : 'Ejercicios · Tema ' . ($k + 1),
                    'fecha_prevista'    => now()->subDays($dias)->toDateString(),
                    'puntuacion_maxima' => 10,
                    'estado'            => 'REGISTRADO',
                    'creado_por'        => $imparticion->profesor_id,
                ]);

                $nota = max(0, min(10, round(($nivel + mt_rand(-12, 12)) / 10, 1)));

                $resultado = Resultado::create([
                    'evaluable_id'        => $evaluable->id,
                    'alumno_id'           => $alumno->id,
                    'puntuacion_obtenida' => $nota,
                    'origen'              => mt_rand(1, 5) === 1 ? 'VERIFICADO' : 'DECLARADO',
                    'registrado_por'      => $alumno->tutores->first()?->user_id
                                             ?? $imparticion->profesor->user_id,
                ]);

                // El peso por recencia necesita fechas reales, no la de hoy.
                $resultado->forceFill([
                    'created_at' => now()->subDays($dias),
                    'updated_at' => now()->subDays($dias),
                ])->save();
            }
        }
    }

    /** @return array{0: string, 1: string} */
    private function nombreUnico(array &$usados): array
    {
        do {
            $nombre = mt_rand(0, 1)
                ? self::NOMBRES_H[array_rand(self::NOMBRES_H)]
                : self::NOMBRES_M[array_rand(self::NOMBRES_M)];
            $apellidos = self::APELLIDOS[array_rand(self::APELLIDOS)];
            $clave = "$nombre $apellidos";
        } while (isset($usados[$clave]));

        $usados[$clave] = true;

        return [$nombre, $apellidos];
    }

    private function sinAcentos(string $texto): string
    {
        return strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
        ]);
    }
}
