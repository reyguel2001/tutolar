<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Mi perfil: la foto de quien ha iniciado sesión.
 *
 * Está fuera de las tres zonas porque la tienen las tres. Y solo deja tocar la
 * foto **propia**: no recibe ningún id, trabaja siempre sobre `auth()->user()`,
 * así que no hay forma de cambiarle la cara a otro ni equivocándose de URL.
 * La foto del alumnado la sube el centro desde su ficha, porque un menor no
 * tiene cuenta.
 */
class PerfilController extends Controller
{
    /** Tamaño máximo, en kilobytes. Una foto de perfil no necesita más. */
    private const MAX_KB = 2048;

    public function edit(): View
    {
        return view('perfil', ['usuario' => auth()->user()]);
    }

    public function guardarFoto(Request $peticion): RedirectResponse
    {
        $peticion->validate([
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:' . self::MAX_KB],
        ], [
            'foto.image' => 'El archivo tiene que ser una imagen.',
            'foto.mimes' => 'Formatos admitidos: JPG, PNG o WEBP.',
            'foto.max'   => 'La imagen no puede pasar de 2 MB.',
        ], ['foto' => 'foto']);

        $usuario = $peticion->user();
        $anterior = $usuario->avatar;

        // Nombre propio y extensión de la imagen real, no la que traiga el
        // nombre del archivo: subir «virus.php» renombrado a «.jpg» no sirve de
        // nada si el nombre con el que se guarda lo ponemos nosotros.
        $nombre = 'u' . $usuario->id . '-' . Str::random(16) . '.' . $peticion->file('foto')->extension();

        $peticion->file('foto')->storeAs('', $nombre, 'avatares');

        $usuario->update(['avatar' => $nombre]);

        $this->borrar($anterior);

        Auditoria::registrar(
            entidad: 'usuario', entidadId: $usuario->id, accion: Auditoria::MODIFICAR,
            autorId: $usuario->id, anterior: ['avatar' => $anterior], nuevo: ['avatar' => $nombre],
            ip: $peticion->ip(),
        );

        return back()->with('exito', 'Foto de perfil actualizada.');
    }

    public function quitarFoto(Request $peticion): RedirectResponse
    {
        $usuario  = $peticion->user();
        $anterior = $usuario->avatar;

        if (! $anterior) {
            return back()->with('aviso', 'No tenías ninguna foto puesta.');
        }

        $usuario->update(['avatar' => null]);
        $this->borrar($anterior);

        Auditoria::registrar(
            entidad: 'usuario', entidadId: $usuario->id, accion: Auditoria::MODIFICAR,
            autorId: $usuario->id, anterior: ['avatar' => $anterior], nuevo: ['avatar' => null],
            ip: $peticion->ip(),
        );

        return back()->with('exito', 'Foto quitada. Vuelves a tener el avatar de siempre.');
    }

    /**
     * Borra el archivo anterior para no dejar huérfanos en el disco.
     *
     * Solo el nombre, nunca una ruta: si lo guardado era una URL o venía con
     * barras, no se toca nada del sistema de archivos.
     */
    private function borrar(?string $archivo): void
    {
        if ($archivo && ! str_contains($archivo, '/') && ! str_contains($archivo, '\\')) {
            Storage::disk('avatares')->delete($archivo);
        }
    }
}
