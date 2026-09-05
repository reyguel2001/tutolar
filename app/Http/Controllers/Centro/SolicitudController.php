<?php

namespace App\Http\Controllers\Centro;

use App\Models\Alumno;
use App\Models\Auditoria;
use App\Models\CodigoVinculacion;
use App\Models\SolicitudVinculacion;
use App\Models\Tutela;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * La bandeja donde el centro resuelve las peticiones de código.
 *
 * Aquí es donde una declaración sin verificar se convierte —o no— en una
 * autorización. La decisión la toma una persona que conoce a las familias del
 * centro; la aplicación solo le pone delante los candidatos que se parecen a lo
 * que la familia escribió y le deja elegir.
 *
 * El paso de aprobar exige **señalar a qué alumno**, no se deduce solo. Aunque
 * haya un único candidato evidente, confirmarlo es lo que convierte el clic en
 * una decisión con nombre y apellidos detrás, que es lo que queda en
 * `auditorias`.
 */
class SolicitudController extends BaseCentroController
{
    private const POR_PAGINA = 20;

    public function index(Request $peticion): View
    {
        $estado = $peticion->query('estado', SolicitudVinculacion::PENDIENTE);

        $solicitudes = SolicitudVinculacion::where('centro_id', $this->centroId())
            ->when(in_array($estado, [
                SolicitudVinculacion::PENDIENTE,
                SolicitudVinculacion::APROBADA,
                SolicitudVinculacion::RECHAZADA,
            ], true), fn ($c) => $c->where('estado', $estado))
            ->with('resolutor')
            // Las pendientes, la más vieja primero: quien lleva más esperando
            // es a quien hay que atender antes.
            ->orderBy('created_at', $estado === SolicitudVinculacion::PENDIENTE ? 'asc' : 'desc')
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        return view('centro.solicitudes', [
            'solicitudes' => $solicitudes,
            'estado'      => $estado,
            'pendientes'  => SolicitudVinculacion::where('centro_id', $this->centroId())->pendientes()->count(),
        ]);
    }

    public function aprobar(Request $peticion, string $solicitud): RedirectResponse
    {
        $modelo = $this->buscar($solicitud);

        $datos = $peticion->validate(
            ['alumno_id' => ['required', 'integer']],
            ['alumno_id.required' => 'Señala a qué alumno corresponde antes de aprobar.'],
        );

        // El alumno se busca dentro del centro, como todo en esta zona: un id
        // colado no puede emitir un código de otro instituto.
        $alumno = Alumno::where('centro_id', $this->centroId())->find($datos['alumno_id']);
        abort_unless($alumno, 403, 'Ese alumno no es de tu centro.');

        if (! Tutela::hayHueco($alumno->id)) {
            return back()->with('error',
                "{$alumno->nombre_completo} ya tiene " . Tutela::MAX_POR_ALUMNO
                . ' familias vinculadas. Retira una antes de emitir otro código.');
        }

        $codigo = CodigoVinculacion::generarPara($alumno);

        $modelo->update([
            'estado'         => SolicitudVinculacion::APROBADA,
            'resuelta_por'   => $peticion->user()->id,
            'resuelta_en'    => now(),
            'codigo_emitido' => $codigo->codigo,
        ]);

        $this->auditar($peticion, 'solicitud_vinculacion', $modelo->id, Auditoria::MODIFICAR,
            ['estado' => SolicitudVinculacion::PENDIENTE],
            ['estado' => SolicitudVinculacion::APROBADA, 'alumno_id' => $alumno->id],
        );

        return back()->with('exito', "Código {$codigo->bonito()} emitido para {$alumno->nombre_completo}. "
            . "Hazlo llegar a {$modelo->nombre_completo} por el medio de contacto que tengas de esa familia, "
            . 'no por el correo que ha escrito en la solicitud.');
    }

    public function rechazar(Request $peticion, string $solicitud): RedirectResponse
    {
        $modelo = $this->buscar($solicitud);

        $modelo->update([
            'estado'       => SolicitudVinculacion::RECHAZADA,
            'resuelta_por' => $peticion->user()->id,
            'resuelta_en'  => now(),
        ]);

        $this->auditar($peticion, 'solicitud_vinculacion', $modelo->id, Auditoria::MODIFICAR,
            ['estado' => SolicitudVinculacion::PENDIENTE],
            ['estado' => SolicitudVinculacion::RECHAZADA],
        );

        // No se avisa a quien la envió. Un «rechazada» automático le diría que
        // ese alumno no está aquí, o que no cuela, y las dos cosas son
        // información que no se le debe a alguien que no ha demostrado ser nadie.
        return back()->with('exito', 'Solicitud descartada.');
    }

    private function buscar(string $id): SolicitudVinculacion
    {
        return SolicitudVinculacion::where('centro_id', $this->centroId())->findOrFail($id);
    }
}
