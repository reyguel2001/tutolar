<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'TUTOLAR')</title>

    {{-- Bootstrap desde CDN. Si trabajas sin conexión, descarga el archivo a
         public/css/bootstrap.min.css y cambia esta línea. --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/tutolar.css') }}" rel="stylesheet">

    {{-- El tema se aplica antes de pintar para que no haya un parpadeo blanco.
         Antes esto solo estaba en el layout de dentro, así que quien tenía el
         tema oscuro veía las pantallas de acceso en blanco. --}}
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
{{--
    Las tres pantallas públicas —entrar, registrarse y pedir código— comparten
    esta estructura: el formulario a la izquierda y el panel de marca a la
    derecha, que desaparece por debajo de 992px porque en un móvil solo estorba.
--}}
<div class="tl-acceso">
    <div class="tl-acceso-form">
        <div>
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="tl-mark" style="width:44px;height:44px;border-radius:13px;font-size:1.2rem">T</div>
                <div class="fs-4 fw-bold" style="letter-spacing:-.6px">TUTOLAR</div>
            </div>

            @yield('formulario')
        </div>
    </div>

    <div class="tl-acceso-marca">
        <div class="txt">
            @yield('marca')
        </div>
    </div>
</div>
</body>
</html>
