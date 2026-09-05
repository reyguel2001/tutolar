<?php

namespace App\Http\Controllers\Centro;

use App\Http\Controllers\Centro\Concerns\GestionaCuenta;
use App\Models\Alumno;
use App\Models\Auditoria;
use App\Models\Tutela;
use App\Models\TutorLegal;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Tutores legales: las familias.
 *
 * Un tutor pertenece al centro por dos vías: porque su cuenta se creó aquí
 * (`users.centro_id`) o porque tutela a algún alumno del centro. La consulta
 * cubre las dos, si no los tutores recién dados de alta —que todavía no
 * tutelan a nadie— desaparecerían del listado nada más crearlos.
 */
class TutorController extends BaseCentroController
{
    use GestionaCuenta;

    private const POR_PAGINA = 20;

    private function consulta(): Builder
    {
        $centroId = $this->centroId();

        return TutorLegal::query()->where(function (Builder $q) use ($centroId) {
            $q->whereHas('user', fn ($u) => $u->where('centro_id', $centroId))
              ->orWhereHas('alumnos', fn ($a) => $a->where('centro_id', $centroId));
        });
    }

    private function buscar(string $id): TutorLegal
    {
        return $this->consulta()->findOrFail($id);
    }

    public function index(Request $peticion): View
    {
        $q = trim((string) $peticion->query('q'));

        $tutores = $this->consulta()
            ->with('user')
            ->withCount('alumnos')
            ->when($q !== '', fn ($c) => $c->where(function (Builder $b) use ($q) {
                $b->where('nombre', 'like', "%{$q}%")
                  ->orWhere('apellidos', 'like', "%{$q}%")
                  ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%"));
            }))
            ->orderBy('apellidos')
            ->orderBy('nombre')
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        return view('centro.tutores.index', ['tutores' => $tutores, 'q' => $q]);
    }

    public function create(): View
    {
        return view('centro.tutores.form', [
            'tutor'   => new TutorLegal(),
            'alumnos' => $this->alumnosDelCentro(),
        ]);
    }

    public function store(Request $peticion): RedirectResponse
    {
        $datos = $peticion->validate($this->reglas());

        $tutor = DB::transaction(function () use ($peticion, $datos) {
            $usuario = $this->crearCuenta(
                $datos,
                User::ROL_TUTOR_LEGAL,
                "{$datos['nombre']} {$datos['apellidos']}",
                $this->centroId(),
            );

            $tutor = TutorLegal::create([
                'user_id'   => $usuario->id,
                'nombre'    => $datos['nombre'],
                'apellidos' => $datos['apellidos'],
                'telefono'  => $datos['telefono'],
            ]);

            $llenos = $this->vincular($tutor, $datos['alumnos'] ?? [], $datos['parentesco'] ?? Tutela::POR_DEFECTO);

            $this->auditar($peticion, 'tutor_legal', $tutor->id, Auditoria::CREAR, null, $this->instantanea($tutor));

            return [$tutor, $llenos];
        });

        [$tutor, $llenos] = $tutor;

        $respuesta = redirect()
            ->route('centro.tutores.show', $tutor)
            ->with('exito', "Tutor «{$tutor->nombre_completo}» dado de alta.");

        // La cuenta se crea igual; lo que no se ha podido hacer es la
        // vinculación, y eso se dice en vez de dejarlo pasar.
        return $llenos === [] ? $respuesta : $respuesta->with('aviso', $this->avisoDeCupo($llenos));
    }

    public function show(string $tutor): View
    {
        $modelo = $this->buscar($tutor);
        $modelo->load(['user', 'alumnos.grupo.curso']);

        return view('centro.tutores.show', [
            'tutor'   => $modelo,
            'alumnos' => $this->alumnosDelCentro(),
        ]);
    }

    public function edit(string $tutor): View
    {
        return view('centro.tutores.form', [
            'tutor'   => $this->buscar($tutor)->load('user'),
            'alumnos' => $this->alumnosDelCentro(),
        ]);
    }

    public function update(Request $peticion, string $tutor): RedirectResponse
    {
        $modelo = $this->buscar($tutor)->load('user');
        $antes  = $this->instantanea($modelo);

        $datos = $peticion->validate($this->reglas($modelo->user));

        DB::transaction(function () use ($peticion, $modelo, $datos, $antes) {
            $modelo->update([
                'nombre'    => $datos['nombre'],
                'apellidos' => $datos['apellidos'],
                'telefono'  => $datos['telefono'],
            ]);

            $this->actualizarCuenta($modelo->user, $datos, "{$datos['nombre']} {$datos['apellidos']}");

            $this->auditar($peticion, 'tutor_legal', $modelo->id, Auditoria::MODIFICAR, $antes, $this->instantanea($modelo));
        });

        return redirect()
            ->route('centro.tutores.show', $modelo)
            ->with('exito', 'Datos del tutor actualizados.');
    }

    public function destroy(Request $peticion, string $tutor): RedirectResponse
    {
        $modelo = $this->buscar($tutor)->load('user');

        // Todas las tutelas, no solo las activas: una tutela desactivada sigue
        // siendo la traza de que esta familia pudo ver a ese menor, y el
        // esquema la protege con `restrictOnDelete`. Si aquí solo contáramos
        // las activas, la aplicación dejaría pasar un borrado que la base de
        // datos rechazaría con un error de clave foránea en bruto.
        $bloqueo = $this->bloqueoPorDependencias([
            'alumnos tutelados (incluidas las vinculaciones ya retiradas)' =>
                DB::table('tutelas')->where('tutor_legal_id', $modelo->id)->count(),
        ]);

        if ($bloqueo) {
            return back()->with('error', $bloqueo);
        }

        $antes = $this->instantanea($modelo);

        DB::transaction(function () use ($peticion, $modelo, $antes) {
            $this->auditar($peticion, 'tutor_legal', $modelo->id, Auditoria::ANULAR, $antes, null);
            $modelo->user?->delete();   // el perfil cuelga en cascada de la cuenta
        });

        return redirect()
            ->route('centro.tutores.index')
            ->with('exito', 'Tutor eliminado junto con su cuenta.');
    }

    // ------------------------------------------------------------- tutelas

    public function vincularAlumno(Request $peticion, string $tutor): RedirectResponse
    {
        $modelo = $this->buscar($tutor);

        $datos = $peticion->validate([
            'alumno_id'  => ['required', 'integer'],
            'parentesco' => ['required', Rule::in(Tutela::PARENTESCOS)],
        ]);

        $alumno = Alumno::where('centro_id', $this->centroId())->find($datos['alumno_id']);
        abort_unless($alumno, 403, 'Ese alumno no es de tu centro.');

        if ($modelo->alumnos()->where('alumnos.id', $alumno->id)->exists()) {
            return back()->with('aviso', 'Ese alumno ya estaba vinculado a este tutor.');
        }

        if (! Tutela::hayHueco($alumno->id)) {
            return back()->with('error', $this->avisoDeCupo([$alumno->nombre_completo]));
        }

        $this->vincular($modelo, [$alumno->id], $datos['parentesco']);
        $this->auditar($peticion, 'tutela', $modelo->id, Auditoria::CREAR, null, [
            'tutor_legal_id' => $modelo->id,
            'alumno_id'      => $alumno->id,
            'parentesco'     => $datos['parentesco'],
        ]);

        return back()->with('exito', "{$alumno->nombre_completo} vinculado a este tutor.");
    }

    public function desvincularAlumno(Request $peticion, string $tutor, string $alumno): RedirectResponse
    {
        $modelo = $this->buscar($tutor);

        DB::table('tutelas')
            ->where('tutor_legal_id', $modelo->id)
            ->where('alumno_id', (int) $alumno)
            ->update(['activa' => false]);

        // La tutela no se borra, se desactiva: es la traza de quién pudo ver
        // qué y hasta cuándo, y eso no se tira.
        $this->auditar($peticion, 'tutela', $modelo->id, Auditoria::ANULAR, [
            'tutor_legal_id' => $modelo->id,
            'alumno_id'      => (int) $alumno,
        ], null);

        return back()->with('exito', 'Alumno desvinculado de este tutor.');
    }

    // ------------------------------------------------------------- apoyo

    /**
     * @return array<string, array<int, mixed>>
     */
    private function reglas(?User $usuario = null): array
    {
        return array_merge([
            'nombre'       => ['required', 'string', 'max:100'],
            'apellidos'    => ['required', 'string', 'max:150'],
            'telefono'     => ['required', 'string', 'max:20'],
            'alumnos'      => ['nullable', 'array'],
            'alumnos.*'    => ['integer'],
            'parentesco'   => ['nullable', Rule::in(Tutela::PARENTESCOS)],
        ], $this->reglasDeCuenta($usuario));
    }

    /**
     * Vincula al tutor con los alumnos que pueda.
     *
     * Un alumno admite como mucho `Tutela::MAX_POR_ALUMNO` familias activas. El
     * que ya esté lleno se salta y se devuelve su nombre, para poder decirle al
     * usuario exactamente cuál no ha entrado en vez de guardar a medias y
     * callarse. Reactivar una tutela retirada del mismo tutor no cuenta como
     * nueva: ya ocupaba ese sitio.
     *
     * @param  array<int, int|string> $alumnoIds
     * @return array<int, string>     los que se quedaron fuera por estar completos
     */
    private function vincular(TutorLegal $tutor, array $alumnoIds, string $parentesco): array
    {
        $llenos = [];

        foreach ($alumnoIds as $id) {
            $alumno = Alumno::where('centro_id', $this->centroId())->find($id);

            if (! $alumno) {
                continue;   // aislamiento entre centros: se ignora en silencio
            }

            $yaVinculado = Tutela::where('tutor_legal_id', $tutor->id)
                ->where('alumno_id', $alumno->id)
                ->where('activa', true)
                ->exists();

            if (! $yaVinculado && ! Tutela::hayHueco($alumno->id)) {
                $llenos[] = $alumno->nombre_completo;
                continue;
            }

            DB::table('tutelas')->updateOrInsert(
                ['tutor_legal_id' => $tutor->id, 'alumno_id' => $alumno->id],
                ['parentesco' => $parentesco, 'activa' => true, 'vinculado_en' => now()],
            );
        }

        return $llenos;
    }

    /** El mensaje de «no cabe», con los nombres de quienes están completos. */
    private function avisoDeCupo(array $llenos): string
    {
        $cuantos = Tutela::MAX_POR_ALUMNO;
        $lista   = collect($llenos)->join(', ', ' y ');

        return count($llenos) === 1
            ? "{$lista} ya tiene {$cuantos} familias vinculadas. Retira una desde su ficha antes de añadir otra."
            : "{$lista} ya tienen {$cuantos} familias vinculadas cada uno. Retira una desde su ficha antes de añadir otra.";
    }

    /** @return LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection */
    private function alumnosDelCentro()
    {
        return Alumno::where('centro_id', $this->centroId())
            ->where('activo', true)
            ->with('grupo.curso')
            ->orderBy('apellidos')
            ->get();
    }
}
