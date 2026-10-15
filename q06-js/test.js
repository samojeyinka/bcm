// ============== run: node test.js — loads index.html + script.js in jsdom and clicks the real buttons ==============
const { JSDOM } = require('jsdom');
const path = require('path');
let fails = 0;
const ok = (name, cond, extra = '') => { if (!cond) fails++; console.log(`[${cond ? 'PASS' : 'FAIL'}] ${name}${cond ? '' : '  -> ' + extra}`); };

JSDOM.fromFile(path.join(__dirname, 'index.html'), { runScripts: 'dangerously', resources: 'usable' }).then((dom) => {
  dom.window.addEventListener('load', () => {
    const d = dom.window.document;
    const art = d.getElementById('article');
    const more = d.getElementById('more');
    const toggle = d.getElementById('toggle-images');
    const kids = () => Array.from(art.children).filter((e) => e.tagName !== 'H1');
    const seq = () => kids().map((e) => e.tagName === 'HR' ? 'HR' : 'P').join(',');
    const ps = () => kids().filter((e) => e.tagName === 'P');
    // ============== the pattern the brief asks for: P,P,HR,P,P,HR. .. and no trailing HR ==============
    const expected = (n) => { const o = []; for (let i = 1; i <= n; i++) { o.push('P'); if (i % 2 === 0 && i < n) o.push('HR'); } return o.join(','); };
    const imgPs = () => ps().filter((p) => p.querySelector('img'));
    const hiddenCount = () => ps().filter((p) => p.hidden).length;

    console.log('--- initial load ---');
    ok('3 paragraphs, sequence P,P,HR,P', seq() === 'P,P,HR,P', seq());
    ok('toggle button exists (made by JS) after Hit me', toggle && more.nextElementSibling === toggle);
    ok('toggle label "Hide image paragraphs"', toggle.textContent === 'Hide image paragraphs');
    const orig = ps().map((p) => p.textContent.trim().slice(0, 20));

    console.log('--- repeated clicks, images visible ---');
    const orders = new Set();
    for (let c = 1; c <= 30; c++) {
      more.click();
      const n = 3 + 3 * c;
      if (seq() !== expected(n)) { ok(`click ${c}: separator pattern`, false, seq()); break; }
      const batch = ps().slice(-3).map((p) => p.textContent.trim().slice(0, 20));
      orders.add(batch.join('|'));
      if ([...batch].sort().join() !== [...orig].sort().join()) { ok(`click ${c}: batch is a permutation of the 3 originals`, false); break; }
    }
    ok('30 clicks: HR pattern correct every time (93 paragraphs)', seq() === expected(93) && ps().length === 93);
    ok('30 clicks: each batch holds the 3 originals exactly once', true);
    ok(`order is randomised (saw ${orders.size} different orders of 6 possible)`, orders.size > 1);
    ok('no HR at the end, none doubled', !seq().endsWith('HR') && !seq().includes('HR,HR'));
    ok('HR count = floor((n-1)/2)', art.querySelectorAll('hr').length === Math.floor((93 - 1) / 2));
    ok('no duplicate ids / no stray ids', d.querySelectorAll('[id]').length === 3); // ============== article, more, toggle-images ==============

    console.log('--- toggle BEFORE adding paragraphs ---');
    const nBefore = ps().length, imgBefore = imgPs().length;
    toggle.click();
    ok(`all ${imgBefore} image paragraphs hidden`, imgPs().every((p) => p.hidden) && hiddenCount() === imgBefore);
    ok('non-image paragraphs still visible', ps().filter((p) => !p.querySelector('img')).every((p) => !p.hidden));
    ok('label + aria-pressed updated', toggle.textContent === 'Show image paragraphs' && toggle.getAttribute('aria-pressed') === 'true');
    ok('paragraph count unchanged by toggling; hidden ones remain in DOM', ps().length === nBefore);
    ok('separators unchanged by toggling', seq() === expected(nBefore));

    console.log('--- add paragraphs WHILE hidden ---');
    for (let c = 0; c < 5; c++) more.click();
    const n2 = nBefore + 15;
    ok('new image paragraphs are hidden too', imgPs().every((p) => p.hidden) && imgPs().length === imgBefore + 5);
    ok('hidden paragraphs still counted for HR placement', seq() === expected(n2), seq().slice(-30));
    ok('visible = non-image paragraphs only', hiddenCount() === imgPs().length);

    console.log('--- toggle back, then add while visible ---');
    toggle.click();
    ok('all paragraphs visible again', hiddenCount() === 0 && toggle.getAttribute('aria-pressed') === 'false');
    more.click();
    ok('new paragraphs visible while toggle is "show"', hiddenCount() === 0 && ps().length === n2 + 3 && seq() === expected(n2 + 3));

    console.log(fails ? `\n${fails} FAILED` : '\nAll checks passed');
    process.exit(fails ? 1 : 0);
  });
});
