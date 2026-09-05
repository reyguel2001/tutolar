{{--
    Formulario de registro de resultados por la familia.

    Es el ciclo central de TUTOLAR: aquí es donde entra el dato que mueve el
    nivel. Todo lo demás de esta pantalla lo consulta.

    Parámetros:
      $alumno      · el alumno seleccionado
      $pendientes  · Collection<Evaluable> que todavía no tienen nota suya
--}}
@php
    $pendientes  = $pendientes ?? collect();
    $porMateria  = $pendientes->groupBy(fn ($e) => $e->imparticion?->asignatura?->denominacion ?? 'Sin asignatura');
    $hayErrores  = $errors->any();
@endphp

<div class="card mt-3" id="registrar">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Registrar una nota</span>
        <span class="fw-normal text-body-tertiary" style="font-size:.75rem">
            {{ $pendientes->count() }} {{ $pendientes->count() === 1 ? 'prueba pendiente' : 'pruebas pendientes' }}
        </span>
    </div>

    <div class="card-body">

        @if ($hayErrores)
            <div class="tl-banner tl-bajo mb-3" role="alert">
                <span aria-hidden="true">⚠️</span>
                <div>
                    <b>No se ha guardado.</b>
                    <ul class="mb-0 mt-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @if ($pendientes->isEmpty())
            <div class="tl-empty">
                <p class="mb-1"><b>No hay nada pendiente de apuntar.</b></p>
                <p class="mb-0 text-body-secondary" style="font-size:.87rem">
                    Cuando el profesorado programe un examen o una tarea nueva, aparecerá aquí.
                </p>
            </div>
        @else

            <form method="POST" action="{{ route('familia.resultados.store') }}" class="row g-3">
                @csrf
                <input type="hidden" name="alumno_id" value="{{ $alumno->id }}">

                <div class="col-md-7">
                    <label for="evaluable_id" class="form-label fw-semibold" style="font-size:.85rem">
                        Examen o tarea
                    </label>
                    <select class="form-control" id="evaluable_id" name="evaluable_id" required
                            aria-describedby="ayuda-evaluable"
                            @if ($errors->has('evaluable_id')) aria-invalid="true" @endif>
                        <option value="" disabled @selected(! old('evaluable_id'))>Elige una prueba…</option>
                        @foreach ($porMateria as $materia => $lista)
                            <optgroup label="{{ $materia }}">
                                @foreach ($lista as $evaluable)
                                    <option value="{{ $evaluable->id }}"
                                            data-maxima="{{ (float) $evaluable->puntuacion_maxima }}"
                                            @selected((int) old('evaluable_id') === $evaluable->id)>
                                        {{ $evaluable->tipo === 'EXAMEN' ? 'Examen' : 'Tarea' }} ·
                                        {{ $evaluable->titulo }} ·
                                        {{ $evaluable->fecha_prevista?->format('d/m/Y') }} ·
                                        sobre {{ rtrim(rtrim(number_format((float) $evaluable->puntuacion_maxima, 2, ',', ''), '0'), ',') }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <div class="text-body-tertiary mt-1" id="ayuda-evaluable" style="font-size:.75rem">
                        Solo aparecen las pruebas de {{ $alumno->nombre }} que aún no tienen nota.
                    </div>
                </div>

                <div class="col-md-5">
                    <label for="puntuacion" class="form-label fw-semibold" style="font-size:.85rem">
                        Puntuación obtenida
                    </label>
                    <input type="number" class="form-control" id="puntuacion" name="puntuacion"
                           value="{{ old('puntuacion') }}"
                           step="0.01" min="0" inputmode="decimal" required
                           placeholder="7.5"
                           aria-describedby="ayuda-puntuacion"
                           @if ($errors->has('puntuacion')) aria-invalid="true" @endif>
                    <div class="text-body-tertiary mt-1" id="ayuda-puntuacion" style="font-size:.75rem">
                        <span id="hint-maxima">Elige primero la prueba para ver el máximo.</span>
                    </div>
                </div>

                <div class="col-12 d-flex flex-wrap align-items-center gap-3">
                    <button type="submit" class="btn btn-primary">Registrar resultado</button>
                    <span class="text-body-tertiary" style="font-size:.75rem">
                        Queda registrado a tu nombre y con la fecha de hoy. El centro puede verificarlo después.
                    </span>
                </div>
            </form>
        @endif
    </div>
</div>

@push('scripts')
<script>
    // Muestra el máximo de la prueba elegida y limita el campo a ese valor.
    // El servidor lo vuelve a comprobar: esto es comodidad, no seguridad.
    (function () {
        var sel  = document.getElementById('evaluable_id');
        var nota = document.getElementById('puntuacion');
        var hint = document.getElementById('hint-maxima');
        if (!sel || !nota || !hint) return;

        function actualizar() {
            var op = sel.options[sel.selectedIndex];
            var max = op && op.dataset ? op.dataset.maxima : null;
            if (max) {
                nota.max = max;
                hint.textContent = 'Máximo de esta prueba: ' + String(max).replace('.', ',') + ' puntos.';
            } else {
                nota.removeAttribute('max');
                hint.textContent = 'Elige primero la prueba para ver el máximo.';
            }
        }

        sel.addEventListener('change', actualizar);
        actualizar();
    })();
</script>
@endpush
