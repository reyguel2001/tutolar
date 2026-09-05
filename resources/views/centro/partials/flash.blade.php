{{-- Confirmaciones, avisos y errores de validación. Un único sitio para los tres. --}}
@if (session('exito'))
    <div class="tl-banner tl-alto mb-3" role="status" aria-live="polite">
        <span aria-hidden="true">✅</span><div>{{ session('exito') }}</div>
    </div>
@endif

@if (session('aviso'))
    <div class="tl-banner tl-medio mb-3" role="status" aria-live="polite">
        <span aria-hidden="true">⚠️</span><div>{{ session('aviso') }}</div>
    </div>
@endif

@if (session('error'))
    <div class="tl-banner tl-bajo mb-3" role="alert">
        <span aria-hidden="true">⛔</span><div>{{ session('error') }}</div>
    </div>
@endif

@if ($errors->any())
    <div class="tl-banner tl-bajo mb-3" role="alert">
        <span aria-hidden="true">⚠️</span>
        <div>
            <b>Revisa el formulario.</b>
            <ul class="mb-0 mt-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    </div>
@endif
