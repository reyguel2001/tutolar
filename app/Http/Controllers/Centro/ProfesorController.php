<?php

namespace App\Http\Controllers\Centro;

use App\Http\Controllers\Centro\Concerns\GestionaCuenta;
use App\Models\Auditoria;
use App\Models\Evaluable;
use App\Models\Grupo;
use App\Models\Profesor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProfesorController extends BaseCentroController
{
    use GestionaCuenta;

    private const POR_PAGINA = 20;

    private function consulta(): Builder
    {
        return Profesor::query()->where('centro_id', $this->centroId());
    }

    private function buscar(string $id): Profesor
    {
        return $this->consulta()->findOrFail($id);
    }

    public function index(Request $peticion): View
    {
        $q = trim((string) $peticion->query('q'));

        $profesores = $this->consulta()
            ->with('user')
            ->withCount('imparticiones')
            ->when($q !== '', fn ($c) => $c->where(function (Builder $b) use ($q) {
                $b->where('nombre', 'like', "%{$q}%")
                  ->orWhere('apellidos', 'like', "%{$q}%")
                  ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%"));
            }))
            ->orderBy('apellidos')
            ->orderBy('nombre')
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        return view('centro.profesores.index', ['profesores' => $profesores, 'q' => $q]);
    }

    public function create(): View
    {
        return view('centro.profesores.form', ['profesor' => new Profesor()]);
    }

    public function store(Request $peticion): RedirectResponse
    {
        $datos = $peticion->validate($this->reglas());

        $profesor = DB::transaction(function () use ($peticion, $datos) {
            $usuario = $this->crearCuenta(
                $datos,
                User::ROL_PROFESOR,
                "{$datos['nombre']} {$datos['apellidos']}",
                $this->centroId(),
            );

            $profesor = Profesor::create([
                'user_id'   => $usuario->id,
                'centro_id' => $this->centroId(),
                'nombre'    => $datos['nombre'],
                'apellidos' => $datos['apellidos'],
            ]);

            $this->auditar($peticion, 'profesor', $profesor->id, Auditoria::CREAR, null, $this->instantanea($profesor));

            return $profesor;
        });

        return redirect()
            ->route('centro.profesores.show', $profesor)
            ->with('exito', "Profesor «{$profesor->nombre_completo}» dado de alta.");
    }

    public function show(string $profesor): View
    {
        $modelo = $this->buscar($profesor);
        $modelo->load(['user', 'imparticiones.asignatura', 'imparticiones.grupo.curso']);

        return view('centro.profesores.show', [
            'profesor' => $modelo,
            'tutorias' => Grupo::where('tutor_grupo_id', $modelo->id)->with('curso')->get(),
        ]);
    }

    public function edit(string $profesor): View
    {
        return view('centro.profesores.form', ['profesor' => $this->buscar($profesor)->load('user')]);
    }

    public function update(Request $peticion, string $profesor): RedirectResponse
    {
        $modelo = $this->buscar($profesor)->load('user');
        $antes  = $this->instantanea($modelo);

        $datos = $peticion->validate($this->reglas($modelo->user));

        DB::transaction(function () use ($peticion, $modelo, $datos, $antes) {
            $modelo->update(['nombre' => $datos['nombre'], 'apellidos' => $datos['apellidos']]);
            $this->actualizarCuenta($modelo->user, $datos, "{$datos['nombre']} {$datos['apellidos']}");
            $this->auditar($peticion, 'profesor', $modelo->id, Auditoria::MODIFICAR, $antes, $this->instantanea($modelo));
        });

        return redirect()
            ->route('centro.profesores.show', $modelo)
            ->with('exito', 'Datos del profesor actualizados.');
    }

    public function destroy(Request $peticion, string $profesor): RedirectResponse
    {
        $modelo = $this->buscar($profesor)->load('user');

        // `evaluables.creado_por` apunta aquí con `restrictOnDelete`: un examen
        // guarda quién lo puso, y esa firma no se puede quedar en el aire. Se
        // cuenta aparte porque un profesor puede haber creado exámenes en una
        // impartición que ya se le retiró.
        $bloqueo = $this->bloqueoPorDependencias([
            'asignaturas asignadas' => $modelo->imparticiones()->count(),
            'grupos de los que es tutor' => Grupo::where('tutor_grupo_id', $modelo->id)->count(),
            'exámenes y tareas creados por él' => Evaluable::where('creado_por', $modelo->id)->count(),
        ]);

        if ($bloqueo) {
            return back()->with('error', $bloqueo);
        }

        $antes = $this->instantanea($modelo);

        DB::transaction(function () use ($peticion, $modelo, $antes) {
            $this->auditar($peticion, 'profesor', $modelo->id, Auditoria::ANULAR, $antes, null);
            $modelo->user?->delete();
        });

        return redirect()
            ->route('centro.profesores.index')
            ->with('exito', 'Profesor eliminado junto con su cuenta.');
    }

    /** @return array<string, array<int, mixed>> */
    private function reglas(?User $usuario = null): array
    {
        return array_merge([
            'nombre'    => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:150'],
            'telefono'  => ['nullable', 'string', 'max:20'],
        ], $this->reglasDeCuenta($usuario));
    }
}
