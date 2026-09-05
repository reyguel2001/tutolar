<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'TUTOLAR')</title>

    {{-- Bootstrap desde CDN. Si trabajas sin conexión, descarga el archivo a
         public/css/bootstrap.min.css y cambia esta línea. --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/tutolar.css') }}" rel="stylesheet">

    {{-- El tema se aplica antes de pintar para que no haya un parpadeo blanco. --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('tutolar-tema') || 'light';
                document.documentElement.setAttribute('data-bs-theme', t);
            } catch (e) {}
        })();
    </script>
</head>
<body>
<a class="tl-skip" href="#contenido">Saltar al contenido principal</a>

<div class="tl-app">

    <aside class="tl-side">
      <div class="interior">
        <div class="tl-brand">
            <div class="tl-mark">T</div>
            <div>
                <div class="nm">TUTOLAR</div>
                <div class="rl">{{ auth()->user()->etiquetaRol() }}</div>
            </div>
        </div>

        <div class="tl-org">
            <div class="oi">@yield('org-icono', '🏫')</div>
            <div class="flex-grow-1 min-w-0">
                <b>@yield('org-titulo', auth()->user()->centro?->denominacion ?? 'TUTOLAR')</b>
                <small>@yield('org-sub', 'Curso 2026/2027')</small>
            </div>
        </div>

        <nav class="px-3" aria-label="Navegación principal">
            @php $rol = auth()->user()->rol; @endphp

            @if ($rol === \App\Models\User::ROL_CENTRO)
                <a class="tl-nav {{ request()->routeIs('centro.panel') ? 'on' : '' }}" href="{{ route('centro.panel') }}">
                    <span class="ic" aria-hidden="true">📈</span> Panel
                </a>

                <div class="tl-navlbl">Gestión</div>
                <a class="tl-nav {{ request()->routeIs('centro.alumno*') ? 'on' : '' }}" href="{{ route('centro.alumnos') }}">
                    <span class="ic" aria-hidden="true">🎒</span> Alumnos
                </a>
                <a class="tl-nav {{ request()->routeIs('centro.tutores.*') ? 'on' : '' }}" href="{{ route('centro.tutores.index') }}">
                    <span class="ic" aria-hidden="true">👨‍👩‍👧</span> Tutores
                </a>
                <a class="tl-nav {{ request()->routeIs('centro.profesores.*') ? 'on' : '' }}" href="{{ route('centro.profesores.index') }}">
                    <span class="ic" aria-hidden="true">🧑‍🏫</span> Profesores
                </a>
                <a class="tl-nav {{ request()->routeIs('centro.asignaturas.*') ? 'on' : '' }}" href="{{ route('centro.asignaturas.index') }}">
                    <span class="ic" aria-hidden="true">📚</span> Asignaturas
                </a>
                <a class="tl-nav {{ request()->routeIs('centro.solicitudes.*') ? 'on' : '' }}" href="{{ route('centro.solicitudes.index') }}">
                    <span class="ic" aria-hidden="true">📨</span> Solicitudes
                    @if (($solicitudesPendientes ?? 0) > 0)
                        <span class="tl-chip tl-medio ms-auto">{{ $solicitudesPendientes }}</span>
                    @endif
                </a>

                <div class="tl-navlbl">Actividad académica</div>
                <a class="tl-nav {{ request()->routeIs('centro.grupos.*') ? 'on' : '' }}" href="{{ route('centro.grupos.index') }}">
                    <span class="ic" aria-hidden="true">🏫</span> Grupos
                </a>
                <a class="tl-nav {{ request()->routeIs('centro.imparticiones.*') ? 'on' : '' }}" href="{{ route('centro.imparticiones.index') }}">
                    <span class="ic" aria-hidden="true">🧩</span> Grupos de asignatura
                </a>
                <a class="tl-nav {{ request()->routeIs('centro.matriculas.*') ? 'on' : '' }}" href="{{ route('centro.matriculas.index') }}">
                    <span class="ic" aria-hidden="true">📋</span> Matrículas
                </a>

                <div class="tl-navlbl">Administración</div>
                <a class="tl-nav {{ request()->routeIs('centro.cuentas.*') ? 'on' : '' }}" href="{{ route('centro.cuentas.index') }}">
                    <span class="ic" aria-hidden="true">🔑</span> Cuentas
                </a>

            @elseif ($rol === \App\Models\User::ROL_PROFESOR)
                <div class="tl-navlbl">Docencia</div>
                <a class="tl-nav {{ request()->routeIs('profesor.grupos') ? 'on' : '' }}" href="{{ route('profesor.grupos') }}">
                    <span class="ic" aria-hidden="true">🏫</span> Mis grupos
                </a>
                <a class="tl-nav {{ request()->routeIs('profesor.notificaciones.*') ? 'on' : '' }}"
                   href="{{ route('profesor.notificaciones.index') }}">
                    <span class="ic" aria-hidden="true">📣</span> Notificar
                </a>

            @else
                <div class="tl-navlbl">Avisos</div>
                <a class="tl-nav {{ request()->routeIs('familia.avisos.*') ? 'on' : '' }}" href="{{ route('familia.avisos.index') }}">
                    <span class="ic" aria-hidden="true">📣</span> Avisos del centro
                    @if (($avisosSinLeer ?? 0) > 0)
                        <span class="tl-chip tl-bajo ms-auto">{{ $avisosSinLeer }}</span>
                    @endif
                </a>

                <div class="tl-navlbl">Mis hijos</div>
                @foreach (($hijos ?? collect()) as $hijo)
                    @php $esteHijo = isset($alumno) && $alumno->id === $hijo->id; @endphp
                    <a class="tl-nav {{ $esteHijo && request()->routeIs('familia.inicio') ? 'on' : '' }}"
                       href="{{ route('familia.inicio', ['alumno' => $hijo->id]) }}">
                        <span class="ic" aria-hidden="true">🎒</span> {{ $hijo->nombre }}
                    </a>
                    {{-- Los gráficos cuelgan del hijo al que pertenecen, no de un
                         menú aparte: así se ve de quién son sin tener que leerlo. --}}
                    @if ($esteHijo)
                        <a class="tl-nav tl-nav-sub {{ request()->routeIs('familia.rendimiento') ? 'on' : '' }}"
                           href="{{ route('familia.rendimiento', ['alumno' => $hijo->id]) }}">
                            <span class="ic" aria-hidden="true">📊</span> Gráficos
                        </a>
                    @endif
                @endforeach
            @endif
        </nav>

        <div class="foot">
            <form method="POST" action="{{ route('logout') }}" class="d-flex align-items-center gap-2">
                @csrf
                <a href="{{ route('perfil') }}" class="d-flex align-items-center gap-2 flex-grow-1 min-w-0 text-decoration-none"
                   title="Mi perfil">
                    @include('partials.avatar', ['persona' => auth()->user(), 'tam' => 'sm'])
                    <span class="flex-grow-1 min-w-0">
                        <b class="d-block" style="font-size:.8rem;color:#fff">{{ auth()->user()->name }}</b>
                        <small style="font-size:.7rem;color:var(--tl-sidebar-text-2)">{{ auth()->user()->etiquetaRol() }}</small>
                    </span>
                </a>
                <button class="btn btn-sm tl-salir" type="submit" title="Cerrar sesión">Salir</button>
            </form>
        </div>
      </div>
    </aside>

    <div>
        <header class="tl-top">
            <div class="tl-crumb">@yield('migas')</div>
            <div class="ms-auto d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" type="button"
                        onclick="cambiarTema()" id="btn-tema"
                        aria-label="Cambiar entre tema claro y oscuro">◐</button>
            </div>
        </header>

        <main class="tl-main" id="contenido">
            @yield('contenido')
        </main>
    </div>
</div>

<script>
    // Bajas en dos pulsaciones. Sin confirm(): un dialogo nativo bloquea la
    // pagina y en secretaria es justo donde se pulsa Aceptar sin leer.
    document.addEventListener('submit', function (ev) {
        var form = ev.target.closest('.tl-confirmar');
        if (!form || form.dataset.confirmado === 'si') return;

        ev.preventDefault();
        var boton = form.querySelector('button');
        var original = boton.textContent;
        form.dataset.confirmado = 'si';
        boton.textContent = form.dataset.confirmacion || 'Pulsa otra vez para confirmar';
        boton.classList.add('tl-peligro');

        setTimeout(function () {
            if (form.dataset.confirmado !== 'si') return;
            form.dataset.confirmado = 'no';
            boton.textContent = original;
            boton.classList.remove('tl-peligro');
        }, 4000);
    });

    function cambiarTema() {
        var html = document.documentElement;
        var nuevo = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-bs-theme', nuevo);
        try { localStorage.setItem('tutolar-tema', nuevo); } catch (e) {}
    }
</script>
@stack('scripts')
</body>
</html>
