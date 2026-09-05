<?php

namespace App\Http\Controllers\Centro;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Centro;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Base de toda la zona de dirección.
 *
 * Existe por una sola razón: **el aislamiento entre centros**. Ninguna consulta
 * de esta zona puede partir de `Modelo::all()`; todas arrancan del centro de
 * quien ha iniciado sesión. Tenerlo aquí evita que se olvide en la novena
 * pantalla, que es donde siempre se olvida.
 *
 * Y la contrapartida de poder escribir: cada alta, cambio y baja deja su fila
 * en `auditorias`. Se gestionan datos de menores; no es opcional.
 */
abstract class BaseCentroController extends Controller
{
    protected function centroId(): int
    {
        return (int) auth()->user()->centro_id;
    }

    protected function centro(): Centro
    {
        return Centro::findOrFail($this->centroId());
    }

    /**
     * Deja traza de una escritura.
     *
     * @param array<string, mixed>|null $antes
     * @param array<string, mixed>|null $despues
     */
    protected function auditar(
        Request $peticion,
        string $entidad,
        int $entidadId,
        string $accion,
        ?array $antes = null,
        ?array $despues = null,
    ): void {
        Auditoria::registrar(
            entidad: $entidad,
            entidadId: $entidadId,
            accion: $accion,
            autorId: $peticion->user()?->id,
            anterior: $antes,
            nuevo: $despues,
            ip: $peticion->ip(),
        );
    }

    /**
     * Los campos del modelo que interesa guardar en la auditoría: los que
     * cambian, sin marcas de tiempo ni claves técnicas.
     *
     * @return array<string, mixed>
     */
    protected function instantanea(Model $modelo): array
    {
        return collect($modelo->getAttributes())
            ->except(['created_at', 'updated_at', 'password', 'remember_token'])
            ->all();
    }

    /**
     * Impide borrar algo de lo que cuelgan otras cosas.
     *
     * Las claves foráneas del esquema están en cascada: borrar una asignatura
     * se llevaría por delante sus imparticiones, matrículas y resultados sin
     * avisar. Antes que un borrado silencioso, un mensaje que explique qué hay
     * que deshacer primero.
     *
     * @param array<string, int> $dependencias  etiqueta => cuántas hay
     */
    protected function bloqueoPorDependencias(array $dependencias): ?string
    {
        $conDatos = array_filter($dependencias, fn ($n) => $n > 0);

        if ($conDatos === []) {
            return null;
        }

        $detalle = collect($conDatos)
            ->map(fn ($n, $etiqueta) => "{$n} {$etiqueta}")
            ->values()
            ->join(', ', ' y ');

        return "No se puede eliminar: todavía hay {$detalle}. Desvincula eso primero.";
    }
}
