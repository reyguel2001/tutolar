@extends('layouts.app')
@php $nuevo = ! $alumno->exists; @endphp
@section('titulo', ($nuevo ? 'Nuevo alumno' : 'Editar alumno') . ' · TUTOLAR')
@section('migas', '<span>Gestión</span> › <a href="' . route('centro.alumnos') . '">Alumnos</a> › <b>' . ($nuevo ? 'Nuevo' : e($alumno->nombre_completo)) . '</b>')

@section('contenido')
@include('centro.partials.flash')
@include('centro.partials.cabecera', [
    'titulo' => $nuevo ? 'Nuevo alumno' : 'Editar ' . $alumno->nombre_completo,
    'sub'    => 'Las asignaturas marcadas son su matrícula: solo esas cuentan en el índice.',
])

<form method="POST" enctype="multipart/form-data"
      action="{{ $nuevo ? route('centro.alumnos.store') : route('centro.alumnos.update', $alumno) }}">
    @csrf
    @unless ($nuevo) @method('PUT') @endunless

    <div class="card mb-3">
        <div class="card-header">Datos del alumno</div>
        <div class="card-body row g-3">
            {{-- La foto la pone el centro: un menor no tiene cuenta con la que
                 subirla él mismo. Sin foto se dibuja la silueta de un niño. --}}
            <div class="col-12">
                <div class="tl-foto">
                    @include('partials.avatar', ['persona' => $alumno, 'tam' => 'lg'])
                    <div style="flex:1 1 260px">
                        <label class="form-label fw-semibold" style="font-size:.85rem" for="foto">
                            Foto <span class="fw-normal text-body-tertiary">(opcional · JPG, PNG o WEBP, hasta 2 MB)</span>
                        </label>
                        <input class="form-control" type="file" id="foto" name="foto"
                               accept="image/jpeg,image/png,image/webp">
                        @if ($alumno->tieneFoto())
                            <label class="d-flex gap-2 align-items-center mt-2" style="font-size:.8rem">
                                <input type="checkbox" name="quitar_foto" value="1">
                                Quitar la foto actual
                            </label>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="nombre">Nombre</label>
                <input class="form-control" id="nombre" name="nombre" required maxlength="100"
                       value="{{ old('nombre', $alumno->nombre) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="apellidos">Apellidos</label>
                <input class="form-control" id="apellidos" name="apellidos" required maxlength="150"
                       value="{{ old('apellidos', $alumno->apellidos) }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="fecha_nacimiento">Nacimiento</label>
                <input class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" type="date" required
                       value="{{ old('fecha_nacimiento', $alumno->fecha_nacimiento?->format('Y-m-d')) }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="grupo_id">Grupo</label>
                <select class="form-control" id="grupo_id" name="grupo_id" required>
                    <option value="">Elige…</option>
                    @foreach ($grupos as $g)
                        <option value="{{ $g->id }}" @selected((int) old('grupo_id', $alumno->grupo_id) === $g->id)>
                            {{ $g->nombre_completo }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Matrícula</div>
        <div class="card-body">
            @if ($asignaturas->isEmpty())
                <p class="text-body-secondary mb-0" style="font-size:.85rem">
                    No hay asignaturas en el catálogo del centro todavía.
                </p>
            @else
                @php $marcadas = old('asignaturas', $alumno->exists ? $alumno->asignaturas->pluck('id')->all() : []); @endphp
                <div class="row g-2" id="lista-asignaturas">
                    @foreach ($asignaturas as $a)
                        <div class="col-md-4" data-asignatura="{{ $a->id }}">
                            <label class="d-flex gap-2 align-items-center" style="font-size:.85rem">
                                <input type="checkbox" name="asignaturas[]" value="{{ $a->id }}"
                                       @checked(in_array($a->id, $marcadas, false))>
                                <span>{{ $a->denominacion }}
                                    <span class="text-body-tertiary">· {{ $a->horas_semanales }} h</span></span>
                            </label>
                        </div>
                    @endforeach
                </div>
                <div class="text-body-tertiary mt-3" style="font-size:.75rem" id="ayuda-plan">
                    Solo se muestran las materias del plan de estudios del curso elegido.
                </div>
            @endif
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit">{{ $nuevo ? 'Crear alumno' : 'Guardar cambios' }}</button>
        <a class="btn btn-outline-secondary" href="{{ $nuevo ? route('centro.alumnos') : route('centro.alumno', $alumno) }}">Cancelar</a>
    </div>
</form>

@push('scripts')
<script>
    // Las materias que no entran en el plan de estudios del curso del grupo se
    // ocultan y se desmarcan. El servidor rechaza el envío si alguna se cuela.
    (function () {
        var CURSO_POR_GRUPO = @json($cursoPorGrupo ?? []);
        var PLAN            = @json($planEstudios ?? []);

        var selGrupo = document.getElementById('grupo_id');
        var lista    = document.getElementById('lista-asignaturas');
        var ayuda    = document.getElementById('ayuda-plan');
        if (!selGrupo || !lista) return;

        function permitida(asignaturaId, cursoId) {
            var cursos = PLAN[asignaturaId];
            if (!cursos || cursos.length === 0) return true;
            return cursos.some(function (c) { return String(c) === String(cursoId); });
        }

        function filtrar() {
            var cursoId = CURSO_POR_GRUPO[selGrupo.value];
            var visibles = 0;

            Array.prototype.forEach.call(lista.children, function (celda) {
                var id = celda.getAttribute('data-asignatura');
                var ok = !selGrupo.value || permitida(id, cursoId);
                celda.hidden = !ok;
                var casilla = celda.querySelector('input');
                if (!ok && casilla) { casilla.checked = false; }
                if (ok) visibles++;
            });

            if (ayuda) {
                ayuda.textContent = !selGrupo.value
                    ? 'Elige el grupo para ver las materias que le corresponden.'
                    : visibles + (visibles === 1 ? ' materia disponible' : ' materias disponibles') + ' en este curso.';
            }
        }

        selGrupo.addEventListener('change', filtrar);
        filtrar();
    })();
</script>
@endpush
@endsection
