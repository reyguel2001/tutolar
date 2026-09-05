@extends('layouts.app')
@section('titulo', $tutor->nombre_completo . ' · TUTOLAR')
@section('migas', '<span>Gestión</span> › <a href="' . route('centro.tutores.index') . '">Tutores</a> › <b>' . e($tutor->nombre_completo) . '</b>')

@section('contenido')
@include('centro.partials.flash')

<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        @include('partials.avatar', ['persona' => $tutor, 'tam' => 'lg'])
        <div>
            <h1 class="tl-titulo">{{ $tutor->nombre_completo }}</h1>
            <div class="text-body-secondary" style="font-size:.87rem">
                {{ $tutor->user?->email }} · {{ $tutor->telefono }}
            </div>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('centro.tutores.edit', $tutor) }}">Editar</a>
        @include('centro.partials.confirmar', [
            'url'          => route('centro.tutores.destroy', $tutor),
            'texto'        => 'Eliminar',
            'confirmacion' => 'Se borrará la cuenta. Pulsa otra vez.',
        ])
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Alumnos tutelados</div>
    @if ($tutor->alumnos->isEmpty())
        @include('centro.partials.vacio', ['mensaje' => 'Este tutor no tutela a ningún alumno todavía.'])
    @else
    <div class="table-responsive">
        <table class="tl-table">
            <caption class="visually-hidden">Alumnos que tutela</caption>
            <thead><tr>
                <th scope="col">Alumno</th><th scope="col">Grupo</th>
                <th scope="col">Parentesco</th><th scope="col"></th>
            </tr></thead>
            <tbody>
            @foreach ($tutor->alumnos as $a)
                <tr>
                    <td class="fw-semibold"><a href="{{ route('centro.alumno', $a) }}">{{ $a->nombre_completo }}</a></td>
                    <td class="text-body-secondary">{{ $a->grupo?->nombre_completo ?? '—' }}</td>
                    <td class="text-body-secondary">{{ \App\Models\Tutela::etiqueta($a->pivot->parentesco) }}</td>
                    <td class="text-end">
                        @include('centro.partials.confirmar', [
                            'url'          => route('centro.tutores.desvincular', [$tutor, $a]),
                            'texto'        => 'Desvincular',
                            'confirmacion' => 'Perderá el acceso a esta ficha. Pulsa otra vez.',
                        ])
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

<div class="card">
    <div class="card-header">Vincular otro alumno</div>
    <form method="POST" action="{{ route('centro.tutores.vincular', $tutor) }}" class="card-body row g-2 align-items-end">
        @csrf
        <div class="col-md-6">
            <label class="form-label fw-semibold" style="font-size:.85rem" for="alumno_id">Alumno</label>
            <select class="form-control" id="alumno_id" name="alumno_id" required>
                <option value="">Elige un alumno…</option>
                @foreach ($alumnos as $a)
                    <option value="{{ $a->id }}">{{ $a->nombre_completo }} · {{ $a->grupo?->nombre_completo }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold" style="font-size:.85rem" for="parentesco">Parentesco</label>
            <select class="form-control" id="parentesco" name="parentesco" required>
                @foreach (\App\Models\Tutela::PARENTESCOS as $p)
                    <option value="{{ $p }}">{{ \App\Models\Tutela::etiqueta($p) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <button class="btn btn-primary w-100" type="submit">Vincular</button>
        </div>
    </form>
</div>
@endsection
