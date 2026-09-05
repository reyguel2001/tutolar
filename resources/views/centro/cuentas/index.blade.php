@extends('layouts.app')
@section('titulo', 'Cuentas · TUTOLAR')
@section('migas', '<span>Administración</span> › <b>Cuentas</b>')

@section('contenido')
@php use App\Models\User; @endphp
@include('centro.partials.flash')
@include('centro.partials.cabecera', [
    'titulo'      => 'Cuentas',
    'sub'         => 'Quién puede entrar en TUTOLAR y con qué rol.',
    'accionUrl'   => route('centro.cuentas.create'),
    'accionTexto' => 'Nueva cuenta de dirección',
])

<div class="row g-3 mb-3">
    @foreach ([User::ROL_CENTRO => 'Dirección', User::ROL_PROFESOR => 'Profesorado', User::ROL_TUTOR_LEGAL => 'Familias'] as $rol => $etiqueta)
        <div class="col-md-4">
            <div class="tl-kpi card"><div class="card-body">
                <div class="k">{{ $etiqueta }}</div>
                <div class="v">{{ $resumen[$rol] }}</div>
                <div class="d">cuentas</div>
            </div></div>
        </div>
    @endforeach
</div>

<div class="tl-banner tl-sin mb-3">
    <span aria-hidden="true">🔒</span>
    <div>Las cuentas no se borran, se <b>desactivan</b>: borrar una se llevaría por
        delante su perfil y con él la trazabilidad de quién registró cada nota.
        El rol tampoco se cambia — si alguien cambia de puesto, se le crea otra cuenta.</div>
</div>

<div class="card">
    <form method="GET" class="card-header d-flex flex-wrap gap-2 align-items-center">
        <input class="form-control" type="search" name="q" value="{{ $filtros['q'] }}" style="max-width:260px"
               placeholder="Buscar por nombre o correo" aria-label="Buscar cuenta">
        <select class="form-select" name="rol" style="max-width:190px" aria-label="Filtrar por rol">
            <option value="">Rol: todos</option>
            <option value="{{ User::ROL_CENTRO }}"      @selected($filtros['rol'] === User::ROL_CENTRO)>Dirección</option>
            <option value="{{ User::ROL_PROFESOR }}"    @selected($filtros['rol'] === User::ROL_PROFESOR)>Profesorado</option>
            <option value="{{ User::ROL_TUTOR_LEGAL }}" @selected($filtros['rol'] === User::ROL_TUTOR_LEGAL)>Familia</option>
        </select>
        <select class="form-select" name="estado" style="max-width:170px" aria-label="Filtrar por estado">
            <option value="">Estado: todos</option>
            <option value="activas"   @selected($filtros['estado'] === 'activas')>Activas</option>
            <option value="inactivas" @selected($filtros['estado'] === 'inactivas')>Inactivas</option>
        </select>
        <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
        @if (array_filter($filtros))
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('centro.cuentas.index') }}">Limpiar</a>
        @endif
    </form>

    @if ($cuentas->isEmpty())
        @include('centro.partials.vacio', ['mensaje' => 'Ninguna cuenta con esos filtros.'])
    @else
    <div class="table-responsive">
        <table class="tl-table">
            <caption class="visually-hidden">Cuentas de acceso</caption>
            <thead><tr>
                <th scope="col">Nombre</th><th scope="col">Correo</th><th scope="col">Rol</th>
                <th scope="col">Último acceso</th><th scope="col">Estado</th><th scope="col"></th>
            </tr></thead>
            <tbody>
            @foreach ($cuentas as $c)
                <tr>
                    <td class="fw-semibold">{{ $c->name }}</td>
                    <td class="text-body-secondary">{{ $c->email }}</td>
                    <td><span class="tl-chip tl-sin">{{ $c->etiquetaRol() }}</span></td>
                    <td class="text-body-secondary">{{ $c->ultimo_acceso_en?->format('d/m/Y H:i') ?? 'Nunca' }}</td>
                    <td><span class="tl-chip {{ $c->activo ? 'tl-alto' : 'tl-bajo' }}">
                        {{ $c->activo ? 'ACTIVA' : 'INACTIVA' }}</span></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('centro.cuentas.edit', $c) }}">Editar</a>
                        <form method="POST" action="{{ route('centro.cuentas.estado', $c) }}" class="d-inline">
                            @csrf @method('PATCH')
                            <button class="btn btn-sm btn-outline-secondary" type="submit">
                                {{ $c->activo ? 'Desactivar' : 'Activar' }}
                            </button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $cuentas->links() }}</div>
    @endif
</div>
@endsection
