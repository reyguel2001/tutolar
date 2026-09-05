<?php

namespace App\Http\Controllers\Familia;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\NotificacionDestinatario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bandeja de avisos de la familia.
 *
 * El otro extremo del aviso que emite el profesorado. Existe porque una
 * notificación que nadie puede leer no es una notificación, y porque la
 * columna «confirmadas» de la pantalla del profesor necesita que alguien
 * confirme de verdad.
 *
 * Leída y confirmada son cosas distintas: leída la marca el sistema al abrir
 * la bandeja; confirmada la marca la familia a mano, y es la que vale como
 * «me he enterado».
 */
class NotificacionController extends Controller
{
    private const POR_PAGINA = 25;

    public function index(Request $peticion): View
    {
        $tutor = $peticion->user()->tutorLegal;
        abort_unless($tutor, 403, 'Esta cuenta no tiene perfil de tutor legal.');

        $avisos = NotificacionDestinatario::where('tutor_legal_id', $tutor->id)
            ->with(['notificacion.evaluable.imparticion.asignatura', 'notificacion.emisor', 'alumno'])
            ->join('notificaciones', 'notificaciones.id', '=', 'notificacion_destinatarios.notificacion_id')
            ->orderByDesc('notificaciones.emitida_en')
            ->select('notificacion_destinatarios.*')
            ->paginate(self::POR_PAGINA);

        // Abrir la bandeja cuenta como leer. Confirmar sigue siendo un acto
        // deliberado: son dos columnas distintas por algo.
        NotificacionDestinatario::where('tutor_legal_id', $tutor->id)
            ->whereNull('leida_en')
            ->update(['leida_en' => now()]);

        return view('familia.notificaciones.index', [
            'tutor'  => $tutor,
            'hijos'  => $tutor->alumnos()->with('grupo.curso')->get(),
            'avisos' => $avisos,
        ]);
    }

    public function confirmar(Request $peticion, string $aviso): RedirectResponse
    {
        $tutor = $peticion->user()->tutorLegal;
        abort_unless($tutor, 403, 'Esta cuenta no tiene perfil de tutor legal.');

        // La consulta parte del tutor: un id ajeno sencillamente no aparece.
        $destinatario = NotificacionDestinatario::where('tutor_legal_id', $tutor->id)->findOrFail($aviso);

        if ($destinatario->estaConfirmada()) {
            return back()->with('aviso', 'Ese aviso ya estaba confirmado.');
        }

        $destinatario->update([
            'leida_en'      => $destinatario->leida_en ?? now(),
            'confirmada_en' => now(),
        ]);

        Auditoria::registrar(
            entidad: 'notificacion_destinatario',
            entidadId: $destinatario->id,
            accion: Auditoria::MODIFICAR,
            autorId: $peticion->user()->id,
            anterior: ['confirmada_en' => null],
            nuevo: ['confirmada_en' => $destinatario->confirmada_en?->toDateTimeString()],
            ip: $peticion->ip(),
        );

        return back()->with('exito', 'Aviso confirmado. El profesorado lo verá en su pantalla.');
    }
}
