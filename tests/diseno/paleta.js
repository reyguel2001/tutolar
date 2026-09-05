// Validador de la paleta de TUTOLAR.
// Comprueba contraste WCAG y separación cromática (ΔE2000) en visión normal
// y en las tres formas de daltonismo.

const hex = h => { h = h.replace('#',''); return [0,2,4].map(i => parseInt(h.slice(i,i+2),16)); };
const srgb = c => { c /= 255; return c <= 0.04045 ? c/12.92 : Math.pow((c+0.055)/1.055, 2.4); };
const lum = h => { const [r,g,b] = hex(h).map(srgb); return 0.2126*r + 0.7152*g + 0.0722*b; };
const ratio = (a,b) => { const l1 = lum(a), l2 = lum(b); return (Math.max(l1,l2)+0.05)/(Math.min(l1,l2)+0.05); };

// sRGB -> Lab (D65)
function lab(h) {
  let [r,g,b] = hex(h).map(srgb);
  let x = (0.4124*r + 0.3576*g + 0.1805*b) / 0.95047;
  let y = (0.2126*r + 0.7152*g + 0.0722*b) / 1.00000;
  let z = (0.0193*r + 0.1192*g + 0.9505*b) / 1.08883;
  const f = t => t > 0.008856 ? Math.cbrt(t) : (7.787*t + 16/116);
  [x,y,z] = [f(x),f(y),f(z)];
  return [116*y - 16, 500*(x-y), 200*(y-z)];
}

function deltaE(h1, h2) {
  const [L1,a1,b1] = lab(h1), [L2,a2,b2] = lab(h2);
  const C1 = Math.hypot(a1,b1), C2 = Math.hypot(a2,b2), Cb = (C1+C2)/2;
  const G = 0.5*(1 - Math.sqrt(Math.pow(Cb,7)/(Math.pow(Cb,7)+Math.pow(25,7))));
  const A1 = (1+G)*a1, A2 = (1+G)*a2;
  const Cp1 = Math.hypot(A1,b1), Cp2 = Math.hypot(A2,b2);
  const hp = (a,b) => { let h = Math.atan2(b,a)*180/Math.PI; return h < 0 ? h+360 : h; };
  const h1p = Cp1 === 0 ? 0 : hp(A1,b1), h2p = Cp2 === 0 ? 0 : hp(A2,b2);
  const dL = L2-L1, dC = Cp2-Cp1;
  let dh = 0;
  if (Cp1*Cp2 !== 0) { dh = h2p-h1p; if (dh > 180) dh -= 360; else if (dh < -180) dh += 360; }
  const dH = 2*Math.sqrt(Cp1*Cp2)*Math.sin(dh*Math.PI/360);
  const Lb = (L1+L2)/2, Cpb = (Cp1+Cp2)/2;
  let hb;
  if (Cp1*Cp2 === 0) hb = h1p+h2p;
  else { hb = (h1p+h2p)/2; if (Math.abs(h1p-h2p) > 180) hb += (h1p+h2p < 360) ? 180 : -180; }
  const T = 1 - 0.17*Math.cos((hb-30)*Math.PI/180) + 0.24*Math.cos(2*hb*Math.PI/180)
              + 0.32*Math.cos((3*hb+6)*Math.PI/180) - 0.20*Math.cos((4*hb-63)*Math.PI/180);
  const Sl = 1 + (0.015*Math.pow(Lb-50,2))/Math.sqrt(20+Math.pow(Lb-50,2));
  const Sc = 1 + 0.045*Cpb, Sh = 1 + 0.015*Cpb*T;
  const Rt = -2*Math.sqrt(Math.pow(Cpb,7)/(Math.pow(Cpb,7)+Math.pow(25,7)))
             * Math.sin((60*Math.exp(-Math.pow((hb-275)/25,2)))*Math.PI/180);
  return Math.sqrt(Math.pow(dL/Sl,2) + Math.pow(dC/Sc,2) + Math.pow(dH/Sh,2) + Rt*(dC/Sc)*(dH/Sh));
}

// Simulación de daltonismo (Brettel/Viénot, matrices sobre lineal)
const CVD = {
  protan: [[0.1121,0.8853,-0.0005],[0.1127,0.8897,-0.0001],[0.0045,0.0085,1.0000]],
  deutan: [[0.2920,0.7054,-0.0003],[0.2934,0.7089,0.0000],[-0.0195,0.0333,1.0000]],
  tritan: [[1.2537,-0.0777,-0.1758],[-0.0212,0.9052,0.1156],[0.0883,-0.5259,1.4376]],
};
function simular(h, tipo) {
  const [r,g,b] = hex(h).map(srgb);
  const m = CVD[tipo];
  const out = m.map(row => row[0]*r + row[1]*g + row[2]*b);
  const back = c => { c = Math.max(0, Math.min(1, c)); return Math.round(255*(c <= 0.0031308 ? 12.92*c : 1.055*Math.pow(c, 1/2.4) - 0.055)); };
  return '#' + out.map(back).map(v => v.toString(16).padStart(2,'0')).join('');
}

// ------------------------------------------------------------ la paleta
const CLARO = {
  fondo:'#F2F6F8', superficie:'#FFFFFF', superficie2:'#E8F0F5',
  texto:'#053F5C', texto2:'#48697C', texto3:'#7B95A3',
  accion:'#F7AD19', accionPress:'#DE9A0C', enlace:'#0A6E8F', enlacePress:'#08596F',
  marino:'#053F5C', azul:'#429EBD', cian:'#9FE7F5',
  estadoAlto:'#0E7C5A', estadoMedio:'#B25A00', estadoBajo:'#8E1E17', estadoSin:'#5E7684',
  serieExamen:'#053F5C', serieTarea:'#429EBD',
};
const OSCURO = {
  fondo:'#071620', superficie:'#0C2434', superficie2:'#12374C',
  texto:'#E4F1F7', texto2:'#9FBECD', texto3:'#6E8D9E',
  accion:'#F7AD19', accionPress:'#FFC14D', enlace:'#6FC7E3', enlacePress:'#9FE7F5',
  estadoAlto:'#3FC594', estadoMedio:'#D27014', estadoBajo:'#F1706C', estadoSin:'#9AA4A8',
  serieExamen:'#9FE7F5', serieTarea:'#45A6E8',
};

let fallos = 0;
const ok = (cond, msg) => { if (!cond) { fallos++; console.log('  ✗ ' + msg); } else console.log('  ✓ ' + msg); };

function contrastes(nombre, P) {
  console.log(`\n── CONTRASTE · ${nombre} ──`);
  const sup = P.superficie;
  const pruebas = [
    ['texto principal sobre superficie', P.texto, sup, 4.5],
    ['texto secundario sobre superficie', P.texto2, sup, 4.5],
    ['texto terciario sobre superficie', P.texto3, sup, 3.0],
    ['texto principal sobre fondo', P.texto, P.fondo, 4.5],
    ['texto secundario sobre superficie-2', P.texto2, P.superficie2, 4.5],
    ['enlace sobre superficie', P.enlace, sup, 4.5],
    ['enlace sobre fondo', P.enlace, P.fondo, 4.5],
    ['estado ALTO como texto', P.estadoAlto, sup, 4.5],
    ['estado MEDIO como texto', P.estadoMedio, sup, 4.5],
    ['estado BAJO como texto', P.estadoBajo, sup, 4.5],
    ['estado SIN DATOS como texto', P.estadoSin, sup, 4.5],
    ['serie examen sobre superficie (3:1 gráfico)', P.serieExamen, sup, 3.0],
    ['serie tarea sobre superficie (3:1 gráfico)', P.serieTarea, sup, 3.0],
  ];
  for (const [q, fg, bg, min] of pruebas) {
    const r = ratio(fg, bg);
    ok(r >= min, `${q}: ${r.toFixed(2)}:1 (mín ${min})`);
  }
  // El botón ámbar lleva tinta navy, nunca blanca.
  const tinta = nombre === 'CLARO' ? '#053F5C' : '#071620';
  const rb = ratio(P.accion, tinta);
  ok(rb >= 4.5, `botón ámbar con tinta ${tinta}: ${rb.toFixed(2)}:1`);
  const rblanco = ratio(P.accion, '#FFFFFF');
  console.log(`    (ámbar con tinta blanca sería ${rblanco.toFixed(2)}:1 — por eso no se usa)`);
}

function separacion(nombre, P) {
  console.log(`\n── SEPARACIÓN CROMÁTICA · ${nombre} ──`);
  const estados = { ALTO:P.estadoAlto, MEDIO:P.estadoMedio, BAJO:P.estadoBajo, SIN:P.estadoSin };
  const claves = Object.keys(estados);

  console.log('  Estados entre sí (mín ΔE 15 en visión normal, 11 en daltonismo):');
  for (let i = 0; i < claves.length; i++)
    for (let j = i+1; j < claves.length; j++) {
      const a = estados[claves[i]], b = estados[claves[j]];
      const d = { normal: deltaE(a,b) };
      for (const t of ['protan','deutan','tritan']) d[t] = deltaE(simular(a,t), simular(b,t));
      const peor = Math.min(...['protan','deutan','tritan'].map(t => d[t]));
      ok(d.normal >= 15 && peor >= 11,
        `${claves[i]}/${claves[j]}: normal ${d.normal.toFixed(1)} · prot ${d.protan.toFixed(1)} · deut ${d.deutan.toFixed(1)} · trit ${d.tritan.toFixed(1)}`);
    }

  // En claro el ámbar puede aparecer junto a texto de estado, así que se exige
  // ΔE 20. En oscuro solo existe como relleno sólido con tinta casi negra —una
  // forma que ningún chip de estado adopta— y basta con 15.
  const minAmbar = nombre === 'CLARO' ? 20 : 15;
  console.log(`  Ámbar de acción frente a cada estado (mín ΔE ${minAmbar}):`);
  for (const k of claves) {
    const d = deltaE(P.accion, estados[k]);
    const peor = Math.min(...['protan','deutan','tritan'].map(t => deltaE(simular(P.accion,t), simular(estados[k],t))));
    ok(d >= minAmbar && peor >= 10, `ámbar/${k}: normal ${d.toFixed(1)} · peor daltonismo ${peor.toFixed(1)}`);
  }

  console.log('  Series del gráfico entre sí y frente a los estados (mín ΔE 15):');
  const dSerie = deltaE(P.serieExamen, P.serieTarea);
  const peorSerie = Math.min(...['protan','deutan','tritan'].map(t => deltaE(simular(P.serieExamen,t), simular(P.serieTarea,t))));
  ok(dSerie >= 15 && peorSerie >= 12, `examen/tarea: normal ${dSerie.toFixed(1)} · peor daltonismo ${peorSerie.toFixed(1)}`);
  for (const k of claves)
    for (const [s, v] of [['examen',P.serieExamen],['tarea',P.serieTarea]]) {
      const d = deltaE(v, estados[k]);
      ok(d >= 15, `${s}/${k}: ${d.toFixed(1)}`);
    }
}

// ---------------------------------------------------------- avatares
// Los colores de identidad de App\Support\Avatar. No codifican nada, así que
// solo se les pide tres cosas: que no se lean como una alarma, que se vean
// sobre el marino de la barra lateral y que se distingan entre ellos.
const AVATARES = ['#2F5AC8', '#7A3BC4', '#C01A6E', '#1E7E92', '#5F5F7F'];

function avatares() {
  console.log('\n── AVATARES ──');
  const calidos = { BAJO: CLARO.estadoBajo, MEDIO: CLARO.estadoMedio, ALTO: CLARO.estadoAlto, ACCION: CLARO.accion };
  for (const c of AVATARES) {
    const r = ratio(c, '#FFFFFF');
    const marino = deltaE(c, CLARO.marino);
    const cerca = Object.entries(calidos)
      .map(([k, v]) => [k, deltaE(c, v)])
      .filter(([, d]) => d < 22);
    ok(r >= 4.5 && marino >= 15 && cerca.length === 0,
      `${c}: silueta blanca ${r.toFixed(2)}:1 · barra lateral ΔE ${marino.toFixed(0)}` +
      (cerca.length ? ` — demasiado cerca de ${cerca.map(([k, d]) => k + ' ' + d.toFixed(0)).join(', ')}` : ''));
  }
  let min = Infinity;
  for (let i = 0; i < AVATARES.length; i++)
    for (let j = i + 1; j < AVATARES.length; j++)
      min = Math.min(min, deltaE(AVATARES[i], AVATARES[j]));
  ok(min >= 12, `se distinguen entre sí: ΔE mínimo ${min.toFixed(1)} (mín 12)`);
}

for (const [n, P] of [['CLARO', CLARO], ['OSCURO', OSCURO]]) { contrastes(n, P); separacion(n, P); }
avatares();

console.log(`\n${'='.repeat(60)}`);
console.log(fallos === 0 ? 'PALETA VÁLIDA — 0 fallos' : `${fallos} FALLO(S)`);
process.exit(fallos === 0 ? 0 : 1);
