@extends('layouts.app')

@section('titulo', 'Gráficos · ' . $alumno->nombre_completo . ' · TUTOLAR')
@section('org-icono', '👨‍👩‍👧')
@section('org-titulo', $tutor->nombre_completo)
@section('org-sub', $hijos->count() . ' alumnos vinculados')
@section('migas', '<span>Mis hijos</span> › <a href="' . route('familia.inicio', ['alumno' => $alumno->id]) . '">' . e($alumno->nombre) . '</a> › <b>Gráficos</b>')

@section('contenido')
@include('familia.cabecera', ['alumno' => $alumno, 'activa' => 'rendimiento'])

<div class="tl-banner tl-sin mb-3">
    <span aria-hidden="true">🔍</span>
    <div>El resumen dice <b>cómo va</b>. Esta pantalla dice <b>por qué</b>: si el
        rendimiento flojea por los exámenes o por no entregar los ejercicios, la
        conversación con el tutor es distinta.</div>
</div>

@include('partials.barras-tipo', ['rendimiento' => $rendimiento])
@endsection
