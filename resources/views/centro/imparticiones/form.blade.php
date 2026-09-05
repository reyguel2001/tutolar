@extends('layouts.app')
@php $nuevo = ! $imparticion->exists; @endphp
@section('titulo', ($nuevo ? 'Nueva asignación' : 'Editar asignación') . ' · TUTOLAR')
@section('migas', '<span>Actividad académica</span> › <a href="' . route('centro.imparticiones.index') . '">Grupos de asignatura</a> › <b>' . ($nuevo ? 'Nueva' : 'Editar') . '</b>')

@section('contenido')
@include('centro.partials.flash')
@include('centro.partials.cabecera', [
    'titulo' => $nuevo ? 'Nueva asignación' : 'Editar asignación',
    'sub'    => 'Un grupo solo puede tener un profesor por asignatura.',
])

<form method="POST" action="{{ $nuevo ? route('centro.imparticiones.store') : route('centro.imparticiones.update', $imparticion) }}">
    @csrf
    @unless ($nuevo) @method('PUT') @endunless

    <div class="card mb-3" style="max-width:760px">
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="asignatura_id">Asignatura</label>
                <select class="form-control" id="asignatura_id" name="asignatura_id" required>
                    <option value="">Elige…</option>
                    @foreach ($asignaturas as $a)
                        <option value="{{ $a->id }}" @selected((int) old('asignatura_id', $imparticion->asignatura_id) === $a->id)>
                            {{ $a->denominacion }}
                        </option>
                    @endforeach
                </select>
                <div class="text-body-tertiary mt-1" style="font-size:.75rem" id="ayuda-plan">
                    Se filtran por el plan de estudios del curso del grupo.
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="grupo_id">Grupo</label>
                <select class="form-control" id="grupo_id" name="grupo_id" required>
                    <option value="">Elige…</option>
                    @foreach ($grupos as $g)
                        <option value="{{ $g->id }}" @selected((int) old('grupo_id', $imparticion->grupo_id) === $g->id)>
                            {{ $g->nombre_completo }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="profesor_id">Profesor</label>
                <select class="form-control" id="profesor_id" name="profesor_id" required>
                    <option value="">Elige…</option>
                    @foreach ($profesores as $p)
                        <option value="{{ $p->id }}" @selected((int) old('profesor_id', $imparticion->profesor_id) === $p->id)>
                            {{ $p->nombre_completo }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    @if ($nuevo)
    <div class="card mb-3" style="max-width:760px">
        <div class="card-body">
            <label class="d-flex gap-2 align-items-start">
                <input type="checkbox" name="matricular_grupo" value="1" class="mt-1"
                       @checked(old('matricular_grupo', true))>
                <span>
                    <b>Matricular a los alumnos del grupo en esta asignatura.</b>
                    <span class="d-block text-body-tertiary" style="font-size:.8rem">
                        La asignación dice quién da la clase; la matrícula, quién la recibe.
                        Sin matrícula el alumno no ve los exámenes y su nivel no se mueve.
                        Desmárcalo si la matrícula la vas a hacer alumno por alumno.
                    </span>
                </span>
            </label>
        </div>
    </div>
    @endif

    <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit">{{ $nuevo ? 'Crear asignación' : 'Guardar cambios' }}</button>
        <a class="btn btn-outline-secondary" href="{{ route('centro.imparticiones.index') }}">Cancelar</a>
    </div>
</form>

@push('scripts')
<script>
    // Al elegir el grupo se ocultan las materias que no entran en el plan de
    // estudios de su curso. El servidor lo vuelve a comprobar: esto solo evita
    // que el usuario elija algo que va a ser rechazado.
    (function () {
        var CURSO_POR_GRUPO = @json($cursoPorGrupo);
        var PLAN            = @json($planEstudios);   // asignatura → cursos (vacío = todos)

        var selGrupo = document.getElementById('grupo_id');
        var selAsig  = document.getElementById('asignatura_id');
        var ayuda    = document.getElementById('ayuda-plan');
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
