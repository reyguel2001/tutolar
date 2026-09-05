@extends('layouts.app')

@section('titulo', $alumno->nombre_completo . ' · TUTOLAR')
@section('org-icono', '👨‍👩‍👧')
@section('org-titulo', $tutor->nombre_completo)
@section('org-sub', $hijos->count() . ' alumnos vinculados')
@section('migas', '<span>Mis hijos</span> › <b>' . e($alumno->nombre) . '</b>')

@section('contenido')
@php use App\Support\Nivel; $irg = $rendimiento['global']; @endphp

@if (session('exito'))
    <div class="tl-banner tl-alto mb-3" role="status" aria-live="polite">
        <span aria-hidden="true">✅</span>
        <div>{{ session('exito') }}</div>
    </div>
@endif

@if (session('aviso'))
    <div class="tl-banner tl-medio mb-3" role="status" aria-live="polite">
        <span aria-hidden="true">⚠️</span>
        <div>{{ session('aviso') }}</div>
    </div>
@endif

@include('familia.cabecera', ['alumno' => $alumno, 'activa' => 'inicio'])

@php
    $enBajo = collect($rendimiento['asignaturas'])
        ->filter(fn ($a) => $a['ira']['nivel'] === Nivel::BAJO);
@endphp

@if ($enBajo->isNotEmpty())
    <div class="tl-banner tl-bajo mb-3">
        <span aria-hidden="true">⚠️</span>
        <div>
            <b>{{ $enBajo->pluck('asignatura.denominacion')->join(', ', ' y ') }}
                {{ $enBajo->count() === 1 ? 'está' : 'están' }} en rendimiento bajo.</b><br>
            Conviene hablar con
            {{ $enBajo->count() === 1 ? 'el profesor' : 'los profesores' }} esta semana.
        </div>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body text-center py-4">
                @include('partials.dial', [
                    'valor'    => $irg['valor'],
                    'nivel' => $irg['nivel'],
                    'etiqueta' => $irg['etiqueta'],
                    'tam'      => 180,
                ])
                <div class="text-body-tertiary mt-3" style="font-size:.75rem">
                    Índice de Rendimiento Global<br>
                    Actualizado {{ now()->format('d/m/Y H:i') }}
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Asignaturas</span>
                <a class="fw-normal" style="font-size:.75rem"
                   href="{{ route('familia.rendimiento', ['alumno' => $alumno->id]) }}">
                    Ver exámenes y ejercicios por separado →
                </a>
            </div>
            <div class="table-responsive">
                <table class="tl-table">
                    <caption class="visually-hidden">Rendimiento por asignatura</caption>
                    <thead>
                        <tr>
                            <th scope="col">Asignatura</th>
                            <th scope="col">Profesor</th>
                            <th scope="col" class="num">Resultados</th>
                            <th scope="col" class="num">Índice</th>
                            <th scope="col" style="width:200px">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($rendimiento['asignaturas'] as $fila)
                        @php $ira = $fila['ira']; @endphp
                        <tr>
                            <td class="fw-semibold">{{ $fila['asignatura']->denominacion }}</td>
                            <td class="text-body-secondary">{{ $fila['profesor']?->nombre_completo ?? '—' }}</td>
                            <td class="num text-body-secondary">{{ $ira['num_resultados'] }}</td>
                            <td class="num tl-cifra {{ Nivel::clase($ira['nivel']) }}">
                                {{ Nivel::cifra($ira['valor']) }}
                            </td>
                            <td>@include('partials.nivel', ['ira' => $ira])</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('familia.registro', ['alumno' => $alumno, 'pendientes' => $pendientes ?? collect()])
@endsection
