@extends('layouts.app')
@section('titulo', 'Nuevo aviso · TUTOLAR')
@section('migas', '<span>Docencia</span> › <a href="' . route('profesor.notificaciones.index') . '">Notificar</a> › <b>Nuevo aviso</b>')

@section('contenido')
@php
    use App\Models\Notificacion;
    // tipo → «crear:EXAMEN» | «elegir:TAREA». Se calcula aquí y no dentro de
    // @json() para no meter una función flecha en una directiva de Blade.
    $modosPorTipo = collect(Notificacion::EMITIBLES)->map(fn ($i) => $i['evaluable'])->all();
@endphp
@include('centro.partials.flash')

<div class="mb-4">
    <h1 class="tl-titulo">Nuevo aviso</h1>
    <div class="text-body-secondary" style="font-size:.87rem">
        Llega a las familias de los alumnos del grupo que cursan esa materia.
    </div>
</div>

@if (empty($catalogo))
    <div class="tl-banner tl-medio">
        <span aria-hidden="true">⚠️</span>
        <div>No tienes ninguna materia asignada todavía. Habla con dirección para que
            te asigne asignaturas y grupos antes de poder avisar a nadie.</div>
    </div>
@else

<form method="POST" action="{{ route('profesor.notificaciones.store') }}" id="form-aviso">
    @csrf

    {{-- ---------- 1. Qué se notifica ---------- --}}
    <div class="card mb-3">
        <div class="card-header">1 · Qué quieres notificar</div>
        <div class="card-body row g-2">
            @foreach (Notificacion::EMITIBLES as $clave => $info)
                <div class="col-md-6">
                    <label class="tl-tipo d-flex gap-2 align-items-start p-3 h-100">
                        <input type="radio" name="tipo" value="{{ $clave }}" required
                               @checked(old('tipo') === $clave)>
                        <span>
                            <b style="font-size:.9rem">{{ $info['icono'] }} {{ $info['etiqueta'] }}</b><br>
                            <span class="text-body-secondary" style="font-size:.78rem">{{ $info['ayuda'] }}</span>
                        </span>
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ---------- 2. A quién ---------- --}}
    <div class="card mb-3">
        <div class="card-header">2 · Curso, grupo y materia</div>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="curso">Curso</label>
                <select class="form-control" id="curso" required>
                    <option value="">Elige un curso…</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="grupo">Grupo</label>
                <select class="form-control" id="grupo" required disabled>
                    <option value="">Elige primero el curso</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="materia">Materia</label>
                <select class="form-control" id="materia" required disabled>
                    <option value="">Elige primero el grupo</option>
                </select>
            </div>
            <input type="hidden" name="imparticion_id" id="imparticion_id" value="{{ old('imparticion_id') }}">
            <div class="col-12 text-body-tertiary" style="font-size:.75rem">
                Solo salen los cursos, grupos y materias que impartes tú.
            </div>
        </div>
    </div>

    {{-- ---------- 3a. Programar algo nuevo ---------- --}}
    <div class="card mb-3" id="bloque-crear" hidden>
        <div class="card-header">3 · Datos de la prueba</div>
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="titulo">Título</label>
                <input class="form-control" id="titulo" name="titulo" maxlength="200"
                       placeholder="Examen del tema 4" value="{{ old('titulo') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="fecha_prevista">
                    <span id="etiqueta-fecha">Fecha</span>
                </label>
                <input class="form-control" id="fecha_prevista" name="fecha_prevista" type="date"
                       value="{{ old('fecha_prevista') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="puntuacion_maxima">Puntúa sobre</label>
                <input class="form-control" id="puntuacion_maxima" name="puntuacion_maxima" type="number"
                       min="1" max="999" step="0.5" value="{{ old('puntuacion_maxima', 10) }}">
            </div>
            <div class="col-12 text-body-tertiary" style="font-size:.75rem">
                Al enviar el aviso se crea la prueba en el sistema. Cuando la corrijas,
                vuelve aquí y envía el aviso de notas para que las familias puedan apuntarlas.
            </div>
        </div>
    </div>

    {{-- ---------- 3b. Elegir algo ya programado ---------- --}}
    <div class="card mb-3" id="bloque-elegir" hidden>
        <div class="card-header">3 · De qué prueba son las notas</div>
        <div class="card-body">
            <label class="form-label fw-semibold" style="font-size:.85rem" for="evaluable_id">Examen o tarea</label>
            <select class="form-control" id="evaluable_id" name="evaluable_id">
                <option value="">Elige primero la materia</option>
            </select>
            <div class="text-body-tertiary mt-1" style="font-size:.75rem" id="ayuda-evaluable">
                Solo aparecen las pruebas que ya has programado en esa materia y ese grupo.
            </div>
        </div>
    </div>

    {{-- ---------- 4. Mensaje ---------- --}}
    <div class="card mb-3">
        <div class="card-header">4 · Mensaje para las familias <span class="fw-normal text-body-tertiary">(opcional)</span></div>
        <div class="card-body">
            <textarea class="form-control" name="mensaje" id="mensaje" rows="3" maxlength="1000"
                      placeholder="Entra el tema 4 entero. Conviene repasar los ejercicios de la página 87."
            >{{ old('mensaje') }}</textarea>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit">Enviar aviso</button>
        <a class="btn btn-outline-secondary" href="{{ route('profesor.notificaciones.index') }}">Cancelar</a>
    </div>
</form>

@push('scripts')
<script>
    // Curso → grupo → materia. Los tres selects se alimentan del catálogo de
    // imparticiones del profesor, así que solo puede elegir combinaciones que
    // existen. El servidor lo vuelve a comprobar: esto es comodidad.
    (function () {
        var CATALOGO   = @json($catalogo);
        var EVALUABLES = @json($evaluables);
        var TIPOS      = @json($modosPorTipo);

        var selCurso   = document.getElementById('curso');
        var selGrupo   = document.getElementById('grupo');
        var selMateria = document.getElementById('materia');
        var oculto     = document.getElementById('imparticion_id');
        var selPrueba  = document.getElementById('evaluable_id');
        var bloqueCrear  = document.getElementById('bloque-crear');
        var bloqueElegir = document.getElementById('bloque-elegir');
        var etiquetaFecha = document.getElementById('etiqueta-fecha');

        // Si el formulario no está en la página, no hay nada que hacer.
        if (!selCurso || !selGrupo || !selMateria || !oculto) return;

        var seleccionPrevia = @json(old('evaluable_id'));

        function unicos(lista, clave, etiqueta) {
            var vistos = {}, salida = [];
            lista.forEach(function (f) {
                if (f[clave] === null || vistos[f[clave]]) return;
                vistos[f[clave]] = true;
                salida.push({ id: f[clave], texto: f[etiqueta] });
            });
            return salida;
        }

        function pintar(select, opciones, textoVacio) {
            select.innerHTML = '';
            var vacio = document.createElement('option');
            vacio.value = '';
            vacio.textContent = textoVacio;
            select.appendChild(vacio);
            opciones.forEach(function (o) {
                var op = document.createElement('option');
                op.value = o.id;
                op.textContent = o.texto;
                select.appendChild(op);
            });
            select.disabled = opciones.length === 0;
        }

        function filasDe(cursoId, grupoId) {
            return CATALOGO.filter(function (f) {
                return (!cursoId || String(f.curso_id) === String(cursoId))
                    && (!grupoId || String(f.grupo_id) === String(grupoId));
            });
        }

        function tipoElegido() {
            var marcado = document.querySelector('input[name="tipo"]:checked');
            return marcado ? marcado.value : null;
        }

        function modo() {
            var t = tipoElegido();
            return t ? String(TIPOS[t]).split(':') : null;   // ['crear'|'elegir', 'EXAMEN'|'TAREA']
        }

        function refrescarBloques() {
            var m = modo();
            bloqueCrear.hidden  = !m || m[0] !== 'crear';
            bloqueElegir.hidden = !m || m[0] !== 'elegir';

            if (m && m[0] === 'crear') {
                etiquetaFecha.textContent = m[1] === 'EXAMEN' ? 'Fecha del examen' : 'Fecha de entrega';
            }
            refrescarPruebas();
        }

        function refrescarPruebas() {
            var m = modo();
            if (!m || m[0] !== 'elegir') return;

            var imp = oculto.value;
            var lista = (imp && EVALUABLES[imp] && EVALUABLES[imp][m[1]]) ? EVALUABLES[imp][m[1]] : [];

            pintar(selPrueba, lista.map(function (e) {
                return { id: e.id, texto: e.titulo + ' · ' + e.fecha + ' · sobre ' + String(e.maxima).replace('.', ',') };
            }), lista.length ? 'Elige una prueba…' : 'No hay pruebas programadas en esta materia');

            if (seleccionPrevia) {
                selPrueba.value = seleccionPrevia;
                seleccionPrevia = null;
            }
        }

        selCurso.addEventListener('change', function () {
            pintar(selGrupo, unicos(filasDe(selCurso.value, null), 'grupo_id', 'grupo_completo'), 'Elige un grupo…');
            pintar(selMateria, [], 'Elige primero el grupo');
            oculto.value = '';
            refrescarPruebas();
        });

        selGrupo.addEventListener('change', function () {
            pintar(selMateria, unicos(filasDe(selCurso.value, selGrupo.value), 'asignatura_id', 'asignatura'), 'Elige una materia…');
            oculto.value = '';
            refrescarPruebas();
        });

        selMateria.addEventListener('change', function () {
            var fila = filasDe(selCurso.value, selGrupo.value).find(function (f) {
                return String(f.asignatura_id) === String(selMateria.value);
            });
            oculto.value = fila ? fila.imparticion : '';
            refrescarPruebas();
        });

        Array.prototype.forEach.call(document.querySelectorAll('input[name="tipo"]'), function (r) {
            r.addEventListener('change', refrescarBloques);
        });

        // Arranque, y restauración tras un error de validación.
        pintar(selCurso, unicos(CATALOGO, 'curso_id', 'curso'), 'Elige un curso…');

        var previa = oculto.value;
        if (previa) {
            var fila = CATALOGO.find(function (f) { return String(f.imparticion) === String(previa); });
            if (fila) {
                selCurso.value = fila.curso_id;
                pintar(selGrupo, unicos(filasDe(fila.curso_id, null), 'grupo_id', 'grupo_completo'), 'Elige un grupo…');
                selGrupo.value = fila.grupo_id;
                pintar(selMateria, unicos(filasDe(fila.curso_id, fila.grupo_id), 'asignatura_id', 'asignatura'), 'Elige una materia…');
                selMateria.value = fila.asignatura_id;
            }
        }

        refrescarBloques();
    })();
</script>
@endpush

@endif
@endsection
