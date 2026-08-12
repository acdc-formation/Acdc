/**
 * Vérifie que le tracé d'une signature est CONTINU : chaque segment doit
 * commencer exactement là où le précédent s'est arrêté, et le tracé complet
 * doit couvrir tout le geste, du premier au dernier point.
 *
 * Le contexte est un mouchard : il enregistre les chemins au lieu de dessiner.
 */
const fs = require('fs');

function makeCtx() {
  const segs = [];
  let cur = null;
  return {
    lineWidth: 2.2, lineCap: '', lineJoin: '', strokeStyle: '', fillStyle: '',
    segments: segs,
    beginPath() { cur = null; },
    moveTo(x, y) { cur = { from: [x, y], to: null, ctrl: null }; },
    lineTo(x, y) { if (cur) cur.to = [x, y]; },
    quadraticCurveTo(cx, cy, x, y) { if (cur) { cur.ctrl = [cx, cy]; cur.to = [x, y]; } },
    arc() {}, fill() {},
    stroke() { if (cur && cur.to) segs.push(cur); cur = null; },
  };
}

/* L'algorithme tel qu'il est écrit dans le plugin (3.25.238). */
function run(points, ctx) {
  let drawing = false, lastX = 0, lastY = 0, midX = 0, midY = 0;
  const start = (p) => { drawing = true; lastX = midX = p.x; lastY = midY = p.y;
    ctx.beginPath(); ctx.arc(p.x, p.y, ctx.lineWidth / 2, 0, 6.284); ctx.fill(); };
  const move = (p) => { if (!drawing) return;
    const mx = (lastX + p.x) / 2, my = (lastY + p.y) / 2;
    ctx.beginPath(); ctx.moveTo(midX, midY); ctx.quadraticCurveTo(lastX, lastY, mx, my); ctx.stroke();
    midX = mx; midY = my; lastX = p.x; lastY = p.y; };
  const stop = () => { if (drawing) { ctx.beginPath(); ctx.moveTo(midX, midY); ctx.lineTo(lastX, lastY); ctx.stroke(); } drawing = false; };

  start(points[0]);
  for (let i = 1; i < points.length; i++) { move(points[i]); }
  stop();
}

/* L'algorithme fautif de la 3.25.236, pour prouver que le test le rejette. */
function runBuggy(points, ctx) {
  let drawing = false, lastX = 0, lastY = 0;
  const start = (p) => { drawing = true; lastX = p.x; lastY = p.y; };
  const move = (p) => { if (!drawing) return;
    const mx = (lastX + p.x) / 2, my = (lastY + p.y) / 2;
    ctx.beginPath(); ctx.moveTo(lastX, lastY); ctx.quadraticCurveTo(lastX, lastY, mx, my); ctx.stroke();
    lastX = p.x; lastY = p.y; };
  start(points[0]);
  for (let i = 1; i < points.length; i++) { move(points[i]); }
  drawing = false;
}

const eq = (a, b) => Math.abs(a[0] - b[0]) < 1e-9 && Math.abs(a[1] - b[1]) < 1e-9;

function check(label, fn, points) {
  const ctx = makeCtx();
  fn(points, ctx);
  const segs = ctx.segments;
  const problems = [];
  if (!segs.length) { problems.push('aucun segment tracé'); }
  for (let i = 1; i < segs.length; i++) {
    if (!eq(segs[i].from, segs[i - 1].to)) {
      problems.push(`trou entre le segment ${i - 1} et le ${i} : fin ${segs[i - 1].to} ≠ départ ${segs[i].from}`);
    }
  }
  const first = points[0], last = points[points.length - 1];
  if (segs.length && !eq(segs[0].from, [first.x, first.y])) {
    problems.push(`le tracé ne part pas du point de posé (${segs[0].from} au lieu de ${[first.x, first.y]})`);
  }
  if (segs.length && !eq(segs[segs.length - 1].to, [last.x, last.y])) {
    problems.push(`le tracé ne rejoint pas le dernier point (${segs[segs.length - 1].to} au lieu de ${[last.x, last.y]})`);
  }
  const verdict = problems.length ? 'ÉCHEC' : 'OK';
  console.log(`${verdict} — ${label} (${segs.length} segments)`);
  problems.slice(0, 3).forEach((p) => console.log('        ' + p));
  return problems.length === 0;
}

/* Un geste plausible : une boucle de signature échantillonnée. */
const pts = [];
for (let i = 0; i <= 60; i++) {
  const t = i / 60 * Math.PI * 3;
  pts.push({ x: 20 + i * 4 + Math.sin(t) * 12, y: 80 + Math.cos(t * 1.7) * 30 });
}

const okFixed = check('3.25.238 — tracé corrigé', run, pts);
const okBuggy = check('3.25.236 — tracé fautif (doit ÉCHOUER)', runBuggy, pts);
const okShort = check('3.25.238 — geste de deux points', run, [{ x: 10, y: 10 }, { x: 40, y: 25 }]);

if (okFixed && okShort && !okBuggy) {
  console.log('\nRÉSULTAT : le tracé corrigé est continu, et le test rejette bien l’ancien.');
  process.exit(0);
}
console.log('\nRÉSULTAT : test non concluant.');
process.exit(1);
