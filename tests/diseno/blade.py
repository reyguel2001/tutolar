#!/usr/bin/env python3
"""
Comprobación estática de las plantillas Blade.

No sustituye a ejecutar la aplicación, pero pilla lo que rompe una pantalla
entera y no se ve leyendo: una directiva sin cerrar, un @include a una vista
que no existe, una clase de nivel que el CSS no define, o PHP mal escrito
dentro de un bloque @php.

    python3 tests/diseno/blade.py
"""
import re, sys, subprocess, pathlib

RAIZ   = pathlib.Path(__file__).resolve().parents[2]
VISTAS = RAIZ / 'resources' / 'views'
CSS    = RAIZ / 'public' / 'css' / 'tutolar.css'

PARES = {
    'if': 'endif', 'foreach': 'endforeach', 'forelse': 'endforelse',
    'for': 'endfor', 'while': 'endwhile', 'section': 'endsection',
    'php': 'endphp', 'unless': 'endunless', 'isset': 'endisset',
    'empty': 'endempty', 'push': 'endpush', 'auth': 'endauth',
    'guest': 'endguest', 'verbatim': 'endverbatim', 'once': 'endonce',
}
# @section('x', 'y') y @empty dentro de @forelse no abren bloque.
AUTOCIERRE = re.compile(r"@section\s*\([^)]*,")

fallos = []


def apunta(v, msg):
    fallos.append(f'{v.relative_to(RAIZ)}: {msg}')


def directivas(txt):
    """Directivas fuera de comentarios {{-- --}} y de cadenas de PHP."""
    txt = re.sub(r'\{\{--.*?--\}\}', '', txt, flags=re.S)
    for m in re.finditer(r'@(\w+)', txt):
        yield m.group(1), m.start(), txt


def revisar_equilibrio(v, txt):
    pila = []
    limpio = re.sub(r'\{\{--.*?--\}\}', '', txt, flags=re.S)
    dentro_forelse = 0
    for m in re.finditer(r'@(\w+)', limpio):
        d, pos = m.group(1), m.start()
        if d == 'section' and AUTOCIERRE.match(limpio[pos:pos + 200] or ''):
            continue
        if d == 'forelse':
            dentro_forelse += 1
        if d == 'endforelse':
            dentro_forelse = max(0, dentro_forelse - 1)
        if d == 'empty' and (dentro_forelse or pila and pila[-1][0] == 'forelse'):
            continue                      # el @empty de un @forelse no abre bloque
        if d in PARES:
            pila.append((d, limpio[:pos].count('\n') + 1))
        elif d in PARES.values():
            esperado = [k for k, val in PARES.items() if val == d][0]
            if not pila:
                apunta(v, f'línea {limpio[:pos].count(chr(10)) + 1}: @{d} sin su @{esperado}')
            elif pila[-1][0] != esperado:
                abierto, ln = pila[-1]
                apunta(v, f'línea {limpio[:pos].count(chr(10)) + 1}: @{d} cierra un @{abierto} abierto en la {ln}')
                pila.pop()
            else:
                pila.pop()
    for abierto, ln in pila:
        apunta(v, f'@{abierto} de la línea {ln} se queda sin @{PARES[abierto]}')


def revisar_includes(v, txt):
    for m in re.finditer(r"@(?:include|extends)\s*\(\s*'([^']+)'", txt):
        destino = VISTAS / (m.group(1).replace('.', '/') + '.blade.php')
        if not destino.exists():
            apunta(v, f"@include/@extends a «{m.group(1)}», que no existe")


def revisar_php(v, txt):
    """Cada bloque @php ... @endphp tiene que ser PHP válido."""
    for i, m in enumerate(re.finditer(r'@php\b(.*?)@endphp', txt, flags=re.S)):
        codigo = '<?php ' + m.group(1)
        r = subprocess.run(['php', '-l'], input=codigo, capture_output=True, text=True)
        if r.returncode != 0:
            apunta(v, f'bloque @php nº{i + 1}: {r.stdout.strip().splitlines()[0]}')


def revisar_clases(v, txt, definidas):
    """
    El relleno de nivel es lo único que se compone en tiempo de render con un
    valor del dominio, así que es lo único que puede acabar apuntando a una
    clase inexistente. Se exige que el sufijo salga de Nivel::token(): un
    strtolower() sobre la constante da `tl-fill-sin_datos`, que no existe, y el
    fallo es silencioso —la barra se queda transparente— así que no se ve
    leyendo ni probando con datos normales.
    """
    for m in re.finditer(r'\btl-fill-', txt):
        resto = txt[m.end():]
        if resto.startswith('{{'):
            # Vale tanto la llamada en el sitio como una variable calculada
            # antes en la misma vista, que es como se escribe cuando el token
            # se usa varias veces.
            enLinea  = 'Nivel::token' in resto[:120]
            deVariable = (re.match(r'\{\{\s*\$token\s*\}\}', resto) is not None
                          and 'Nivel::token' in txt)
            if not (enLinea or deVariable):
                apunta(v, 'un tl-fill-{{ … }} se compone sin Nivel::token(): '
                          'el sufijo puede no existir en el CSS')
            continue
        sufijo = re.match(r'[a-z0-9-]+', resto)
        if sufijo and ('tl-fill-' + sufijo.group(0)) not in definidas:
            apunta(v, f'clase «tl-fill-{sufijo.group(0)}» sin definir en tutolar.css')


def main():
    css = CSS.read_text(encoding='utf-8')
    definidas = set(re.findall(r'\.(tl-[a-z0-9-]+)', css))
    variables = set(re.findall(r'(--tl-[a-z0-9-]+)\s*:', css))

    vistas = sorted(VISTAS.rglob('*.blade.php'))
    for v in vistas:
        txt = v.read_text(encoding='utf-8')
        revisar_equilibrio(v, txt)
        revisar_includes(v, txt)
        revisar_php(v, txt)
        revisar_clases(v, txt, definidas)
        # var(--tl-…) literal: las construidas con Blade se comprueban abajo.
        for m in re.finditer(r'var\((--tl-[a-z0-9-]+)\)', txt):
            if m.group(1) not in variables:
                apunta(v, f'variable CSS «{m.group(1)}» sin definir')

    # Los cuatro niveles tienen que tener token, clase, relleno y variable.
    for token in ('bajo', 'medio', 'alto', 'sin'):
        for exigida in (f'--tl-{token}', f'--tl-{token}-fill', f'--tl-{token}-soft', f'--tl-{token}-line'):
            if exigida not in variables:
                fallos.append(f'tutolar.css: falta {exigida}')
        for clase in (f'tl-{token}', f'tl-fill-{token}'):
            if clase not in definidas:
                fallos.append(f'tutolar.css: falta la clase .{clase}')

    print(f'{len(vistas)} vistas revisadas')
    if fallos:
        print(f'\n{len(fallos)} problema(s):')
        for f in fallos:
            print('  ✗ ' + f)
        sys.exit(1)
    print('Sin problemas.')


if __name__ == '__main__':
    main()
