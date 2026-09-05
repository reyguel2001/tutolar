@extends('layouts.app')

@section('titulo', 'Mis grupos · TUTOLAR')
@section('org-icono', '📐')
@section('org-titulo', $profesor->nombre_completo)
@section('org-sub', 'Profesorado')
@section('migas', '<span>Docencia</span> › <b>Mis grupos</b>')

@section('contenido')
@php use App\Support\Nivel; @endphp

<div class="tl-cab">
    <div>
        <h1>Mis grupos</h1>
        <p>{{ $profesor->nombre_completo }} · {{ $tarjetas->count() }}
           {{ $tarjetas->count() === 1 ? 'grupo' : 'grupos' }}</p>
    </div>
</div>

<div class="tl-banner tl-sin mb-4">
    <span aria-hidden="true">🔒</span>
    <div>Ves el agregado de tus grupos en tu asignatura. Tu rol nunca introduce
        calificaciones: eso lo hace la familia cuando el examen llega a casa.</div>
</div>

<div class="row g-3">
@forelse ($tarjetas as $t)
    @php
        $imp = $t['imparticion'];
        $r = $t['reparto'];
        $sinVincular = $t['alumnos'] - $t['vinculados'];
    @endphp
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>{{ $imp->grupo->nombre_completo }} · {{ $imp->asignatura->denominacion }}</span>
                @if ($sinVincular > 0)
                    <span class="tl-chip tl-bajo">{{ $sinVincular }} SIN VINCULAR</span>
                @else
                    <span class="tl-chip tl-alto">TODAS VINCULADAS</span>
                @endif
            </div>
            <div class="card-body">
                <div class="text-body-secondary mb-3" style="font-size:.82rem">
                    {{ $t['alumnos'] }} alumnos · {{ $t['vinculados'] }} familias vinculadas
                </div>

                <div class="tl-stack" style="height:12px">
                    @foreach ([Nivel::BAJO, Nivel::MEDIO, Nivel::ALTO, Nivel::SIN_DATOS] as $c)
                        @if (($r[$c] ?? 0) > 0)
                            <i class="tl-fill-{{ Nivel::token($c) }}" style="flex: {{ $r[$c] }}"></i>
                        @endif
                    @endforeach
                </div>

                <div class="text-body-secondary mt-2" style="font-size:.78rem">
                    {{ $r[Nivel::BAJO] }} bajo ·
                    {{ $r[Nivel::MEDIO] }} medio ·
                    {{ $r[Nivel::ALTO] }} alto ·
                    {{ $r[Nivel::SIN_DATOS] }} sin datos
                </div>
            </div>
        </div>
    </div>
@empty
    <div class="col-12">
        <div class="card"><div class="tl-empty">
            <div class="em">🏫</div>
            <b>Todavía no tienes grupos asignados</b>
            <p class="mb-0">El centro debe asignarte al menos una asignatura en un grupo.</p>
        </div></div>
    </div>
@endforelse
</div>
@endsection
