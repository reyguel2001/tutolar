@extends('layouts.app')
@section('titulo', 'Matrículas · TUTOLAR')
@section('migas', '<span>Actividad académica</span> › <b>Matrículas</b>')

@section('contenido')
@include('centro.partials.flash')
@include('centro.partials.cabecera', [
    'titulo' => 'Matrículas',
    'sub'    => 'Qué asignaturas cursa cada alumno.',
])

@include('centro.partials.ayuda', ['puntos' => [
    ['titulo' => 'Matricular no basta:', 'texto' => 'para que el alumno reciba notas, su grupo tiene
        que tener un <a href="' . route('centro.imparticiones.index') . '">grupo de asignatura</a>
        de esa materia. Allí se ve de un vistazo qué clases no cursa nadie.'],
    ['titulo' => 'Matricular de más pasa factura:', 'texto' => 'una asignatura matriculada sin
        resultados sale sin datos en la ficha del alumno, y eso llena el panel de fichas grises
        que nadie va a resolver.'],
]])

<div class="card mb-3">
    <div class="card-header">Matricular un grupo entero</div>
    <form method="POST" action="{{ route('centro.matriculas.grupo') }}" class="card-body row g-2 align-items-end">
        @csrf
        <div class="col-md-5">
            <label class="form-label fw-semibold" style="font-size:.85rem" for="grupo_masivo">Grupo</label>
            <select class="form-control" id="grupo_masivo" name="grupo_id" required>
                <option value="">Elige un grupo…</option>
                @foreach ($grupos as $g)<option value="{{ $g->id }}">{{ $g->nombre_completo }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label fw-semibold" style="font-size:.85rem" for="asignatura_masiva">Asignatura</label>
            <select class="form-control" id="asignatura_masiva" name="asignatura_id" required>
                <option value="">Elige una asignatura…</option>
                @foreach ($asignaturas as $a)<option value="{{ $a->id }}">{{ $a->denominacion }}</option>@endforeach
            </select>
            <div class="text-body-tertiary mt-1" style="font-size:.75rem" id="ayuda-plan-masiva">
                Se filtran por el plan de estudios del curso del grupo.
            </div>
        </div>
        <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Matricular</button></div>
    </form>
</div>

<div class="card">
    <form method="GET" class="card-header d-flex flex-wrap gap-2 align-items-center">
        <input class="form-control" type="search" name="q" value="{{ $filtros['q'] }}" style="max-width:240px"
               placeholder="Buscar alumno" aria-label="Buscar alumno">
        <select class="form-select" name="curso" style="max-width:190px" aria-label="Filtrar por curso">
            <option value="">Curso: todos</option>
            @foreach ($cursos as $c)
                <option value="{{ $c->id }}" @selected((string) $filtros['curso'] === (string) $c->id)>{{ $c->denominacion }}</option>
            @endforeach
        </select>
        <select class="form-select" name="asignatura" style="max-width:220px" aria-label="Filtrar por asignatura">
            <option value="">Matriculados en: cualquiera</option>
            @foreach ($asignaturas as $a)
                <option value="{{ $a->id }}" @selected((string) $filtros['asignatura'] === (string) $a->id)>{{ $a->denominacion }}</option>
            @endforeach
        </select>
        <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
        @if (array_filter($filtros))
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('centro.matriculas.index') }}">Limpiar</a>
        @endif
    </form>

    @if ($alumnos->isEmpty())
        @include('centro.partials.vacio', ['mensaje' => 'Ningún alumno con esos filtros.'])
    @else
    <div class="table-responsive">
        <table class="tl-table">
            <caption class="visually-hidden">Matrículas por alumno</caption>
            <thead><tr>
                <th scope="col">Alumno</th><th scope="col">Grupo</th>
                <th scope="col">Asignaturas matriculadas</th><th scope="col" style="width:280px">Añadir</th>
            </tr></thead>
            <tbody>
            @foreach ($alumnos as $alumno)
                <tr>
                    <td class="fw-semibold"><a href="{{ route('centro.alumno', $alumno) }}">{{ $alumno->nombre_completo }}</a></td>
                    <td class="text-body-secondary">{{ $alumno->grupo?->nombre_completo ?? '—' }}</td>
                    <td>
                        @forelse ($alumno->asignaturas as $a)
                            <form method="POST" class="d-inline tl-confirmar"
                                  action="{{ route('centro.matriculas.destroy', [$alumno, $a]) }}"
                                  data-confirmacion="Pulsa otra vez para quitarla">
                                @csrf @method('DELETE')
                                <button type="submit" class="tl-chip tl-info border-0"
                                        title="Quitar matrícula de {{ $a->denominacion }}">
                                    {{ $a->denominacion }} ✕
                                </button>
                            </form>
                        @empty
                            <span class="text-body-tertiary" style="font-size:.8rem">Sin matrículas</span>
                        @endforelse
                    </td>
                    <td>
                        @php
                            // Ni las que ya cursa ni las que su curso no contempla.
                            $disponibles = $asignaturas->filter(fn ($a) =>
                                ! $alumno->asignaturas->contains('id', $a->id)
                                && $a->seImparteEn($alumno->grupo?->curso_id));
                        @endphp
                        @if ($disponibles->isEmpty())
                            <span class="text-body-tertiary" style="font-size:.8rem">
                                Ya cursa todo su plan de estudios
                            </span>
                        @else
                        <form method="POST" action="{{ route('centro.matriculas.store') }}" class="d-flex gap-1">
                            @csrf
                            <input type="hidden" name="alumno_id" value="{{ $alumno->id }}">
                            <select class="form-select form-select-sm" name="asignatura_id" required
                                    aria-label="Asignatura para {{ $alumno->nombre_completo }}">
                                <option value="">Añadir…</option>
                                @foreach ($disponibles as $a)
                                    <option value="{{ $a->id }}">{{ $a->denominacion }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-sm btn-outline-secondary" type="submit">+</button>
                        </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $alumnos->links() }}</div>
    @endif
</div>

@push('scripts')
<script>
    // Mismo filtro que en «Grupos de asignatura»: al elegir el grupo del alta
    // masiva se ocultan las materias que no entran en el plan de estudios de su
    // curso. El servidor lo vuelve a comprobar; esto solo evita que el usuario
    // elija algo que va a ser rechazado.
    (function () {
        var CURSO_POR_GRUPO = @json($cursoPorGrupo);
        var PLAN            = @json($planEstudios);   // asignatura → cursos (vacío = todos)

        var selGrupo = document.getElementById('grupo_masivo');
        var selAsig  = document.getElementById('asignatura_masiva');
        var ayuda    = document.getElementById('ayuda-plan-masiva');
        if (!selGrupo || !selAsig) return;

        function permitida(asignaturaId, cursoId) {
            var cursos = PLAN[asignaturaId];
            if (!cursos || cursos.length === 0) return true;   // sin restricción
            return cursos.some(function (c) { return String(c) === String(cursoId); });
        }

        function filtrar() {
            var cursoId = CURSO_POR_GRUPO[selGrupo.value];
            var ocultas = 0;

            Array.prototype.forEach.call(selAsig.options, function (op) {
                if (!op.value) return;
                var ok = !selGrupo.value || permitida(op.value, cursoId);
                op.hidden = !ok;
                op.disabled = !ok;
                if (!ok) {
                    ocultas++;
                    if (op.selected) { selAsig.value = ''; }
                }
            });

            if (ayuda) {
                ayuda.textContent = !selGrupo.value
                    ? 'Se filtran por el plan de estudios del curso del grupo.'
                    : (ocultas === 0
                        ? 'Todas las materias del centro entran en este curso.'
                        : ocultas + (ocultas === 1 ? ' materia no entra' : ' materias no entran') + ' en el plan de estudios de este curso.');
            }
        }

        selGrupo.addEventListener('change', filtrar);
        filtrar();
    })();
</script>
@endpush
@endsection
