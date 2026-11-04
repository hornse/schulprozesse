// Verhaltensprüfungen im echten Browser (S74, koordination
// AUFTRAG-doku-hilfe-S74.md; V37: Ist ein Browser da, wird das Verhalten
// geprüft, nicht der Quelltext).
//
// Startet einen Chromium ohne Fenster, liefert backend/public über einen
// kleinen Ersatz-Server aus (die API antwortet mit festen Antworten, keine
// Datenbank) und steuert die Seite über das DevTools-Protokoll mit ECHTEN
// Tasten- und Mausereignissen. Gemessen wird, was danach passiert: welche
// Anfragen der Server bekommt, was sichtbar ist.
//
// Die Sätze, die der Ersatz-Server schickt und die die Hilfe zitieren muss,
// kommen aus backend/src/Auth/Anmeldebremse.php (php), nicht aus dieser Datei.
//
// Aufruf: node tests/browser_test.mjs [public-Verzeichnis]
// Chromium: $CHROME, sonst Google Chrome, sonst der headless shell aus dem
// Playwright-Cache. Fehlt jeder, endet die Datei mit „ÜBERSPRUNGEN“ und
// Exit 3 – sichtbar, nicht grün.
//
// SPDX-License-Identifier: GPL-3.0-or-later

import http from 'node:http';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawn, execFileSync } from 'node:child_process';

const WURZEL = path.resolve(new URL('..', import.meta.url).pathname);
const PUB = path.resolve(process.argv[2] || path.join(WURZEL, 'backend/public'));
const BREMSE = path.join(process.argv[3] || WURZEL, 'backend/src/Auth/Anmeldebremse.php');

function findeChrome() {
  const kandidaten = [process.env.CHROME,
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome'];
  const cache = path.join(os.homedir(), 'Library/Caches/ms-playwright');
  try {
    for (const d of fs.readdirSync(cache).filter(n => n.startsWith('chromium_headless_shell-')).sort().reverse())
      for (const u of fs.readdirSync(path.join(cache, d)))
        kandidaten.push(path.join(cache, d, u, 'chrome-headless-shell'));
  } catch { /* kein Cache */ }
  return kandidaten.find(k => k && fs.existsSync(k));
}
const CHROME = findeChrome();
if (!CHROME) { console.log('  ÜBERSPRUNGEN: kein Chromium gefunden ($CHROME setzen)'); process.exit(3); }

let gruen = 0, rot = 0;
const pruefe = (ok, text, warum = '') => {
  if (ok) { gruen++; console.log('  ✓ ' + text); }
  else    { rot++;   console.log('  ✗ ' + text + (warum ? ' – ' + warum : '')); }
};

// ---- Die Sätze aus dem Code ------------------------------------------
// Fehlt eine Konstante, ist sie null: Der Ersatz-Server schickt dann den
// heutigen Satz, die Zitatprüfungen werden rot.
const SAETZE = JSON.parse(execFileSync('php', ['-r', `
  require $argv[1];
  $k = 'App\\\\Auth\\\\Anmeldebremse';
  $c = fn($n) => defined("$k::$n") ? constant("$k::$n") : null;
  $s = $c('SCHWELLE');
  echo json_encode(['abgelehnt' => $c('SATZ_ABGELEHNT'), 'ausfall' => $c('SATZ_AUSFALL'), 'schwelle' => $s,
    'warten' => App\\Auth\\Anmeldebremse::urteil(['fehlversuche' => $s ?? 5, 'seit' => 0], $s ?? 5)['meldung']]);
`, BREMSE], { encoding: 'utf8' }));
const ABGELEHNT = SAETZE.abgelehnt ?? 'Anmeldung nicht möglich. Bitte Zugangsdaten prüfen.';
const AUSFALL = SAETZE.ausfall ?? 'WebUntis ist gerade nicht erreichbar. Bitte in ein paar Minuten erneut versuchen.';

// ---- Ersatz-Server ---------------------------------------------------
// LOGIN: 'ok' | 'falsch' | 'warten' | 'ausfall'. SITZUNG: 'aktiv' | 'ab'.
let LOGIN = 'ok', SITZUNG = 'aktiv', ANGEMELDET = false;
const anfragen = [];
const MIME = { html: 'text/html; charset=utf-8', js: 'application/javascript', css: 'text/css', svg: 'image/svg+xml' };
const srv = http.createServer((q, r) => {
  const u = new URL(q.url, 'http://x').pathname;
  const json = (status, body) => { r.writeHead(status, { 'Content-Type': 'application/json' }); r.end(JSON.stringify(body)); };
  if (u.startsWith('/api/')) {
    anfragen.push(q.method + ' ' + u);
    if (u === '/api/login') {
      if (LOGIN === 'ok') { ANGEMELDET = true; return json(200, { webuntis_user: 'probe', anzeigename: 'Probe', rolle: 'mitglied' }); }
      if (LOGIN === 'warten') return json(429, { error: SAETZE.warten });
      if (LOGIN === 'ausfall') return json(503, { error: AUSFALL });
      return json(401, { error: ABGELEHNT });
    }
    if (u === '/api/dashboard') return json(200, [{ id: 1, label: 'Probeprozess öffentlich', schritte: [] }]);
    if (!ANGEMELDET || SITZUNG === 'ab') return json(401, { error: 'Nicht angemeldet.' });
    if (u === '/api/me') return json(200, { webuntis_user: 'probe', anzeigename: 'Probe', rolle: 'mitglied' });
    return json(200, u.startsWith('/api/einstellungen') ? {} : []);
  }
  const f = path.join(PUB, u === '/' ? 'index.html' : u);
  if (!f.startsWith(PUB + path.sep) || !fs.existsSync(f) || fs.statSync(f).isDirectory()) { r.writeHead(404); return r.end(); }
  r.writeHead(200, { 'Content-Type': MIME[f.split('.').pop()] || 'application/octet-stream' });
  r.end(fs.readFileSync(f));
});
await new Promise(res => srv.listen(0, '127.0.0.1', res));
const BASIS = `http://127.0.0.1:${srv.address().port}/`;

// ---- Browser ---------------------------------------------------------
const profil = fs.mkdtempSync(path.join(os.tmpdir(), 'sp-browser-'));
const chrome = spawn(CHROME, ['--headless=new', '--remote-debugging-port=0', '--no-first-run',
  '--user-data-dir=' + profil, '--window-size=1280,900', 'about:blank'], { stdio: ['ignore', 'ignore', 'pipe'] });
const ende = (code) => {
  chrome.kill(); srv.close();
  setTimeout(() => { try { fs.rmSync(profil, { recursive: true, force: true }); } catch {} process.exit(code); }, 300);
};
const wsUrl = await new Promise((res, rej) => {
  let b = ''; const t = setTimeout(() => rej(new Error('Chromium startet nicht')), 15000);
  chrome.stderr.on('data', d => { b += d; const m = b.match(/ws:\/\/\S+/); if (m) { clearTimeout(t); res(m[0]); } });
});
const ws = new WebSocket(wsUrl);
await new Promise(r => ws.onopen = r);
let nr = 0; const offen = {}; const fehler = [];
ws.onmessage = e => {
  const m = JSON.parse(e.data);
  if (m.id && offen[m.id]) { offen[m.id](m); delete offen[m.id]; }
  else if (m.method === 'Runtime.exceptionThrown') fehler.push(m.params.exceptionDetails?.exception?.description || 'Ausnahme');
};
let S;
const cdp = (method, params = {}) => new Promise(r => { const i = ++nr; offen[i] = r; ws.send(JSON.stringify({ id: i, method, params, sessionId: S })); });
const werte = async (ausdruck) => (await cdp('Runtime.evaluate', { expression: ausdruck, awaitPromise: true, returnByValue: true })).result?.result?.value;
const pause = (ms) => new Promise(r => setTimeout(r, ms));
const bis = async (ausdruck, ms = 3000) => { const t = Date.now(); while (Date.now() - t < ms) { if (await werte(ausdruck)) return true; await pause(50); } return false; };

const { result: { targetId } } = await cdp('Target.createTarget', { url: 'about:blank' });
S = (await cdp('Target.attachToTarget', { targetId, flatten: true })).result.sessionId;
await cdp('Runtime.enable'); await cdp('Page.enable');

const TASTE = { Enter: ['Enter', 13, '\r'], Tab: ['Tab', 9, ''] };
async function taste(key) {
  const [code, vk, text] = TASTE[key] || ['Key' + key.toUpperCase(), key.toUpperCase().charCodeAt(0), key];
  await cdp('Input.dispatchKeyEvent', { type: 'keyDown', key, code, windowsVirtualKeyCode: vk, text: text || undefined });
  await cdp('Input.dispatchKeyEvent', { type: 'keyUp', key, code, windowsVirtualKeyCode: vk });
}
async function tippe(s) { for (const c of s) await taste(c); }
// Ein sichtbares, nicht verdecktes Bedienelement mit diesem Namen.
const bedien = (muster) => `(() => {
  const k = [...document.querySelectorAll('a, button, [role=button], [role=link]')].filter(e => {
    const name = (e.getAttribute('aria-label') || e.textContent || '').trim();
    if (!${muster}.test(name)) return false;
    const r = e.getBoundingClientRect();
    if (!r.width || !r.height || getComputedStyle(e).visibility === 'hidden') return false;
    const oben = document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2);
    return oben && (oben === e || e.contains(oben));
  });
  return k[0] || null; })()`;
async function klicke(ausdruck) {
  const p = await werte(`(() => { const e = ${ausdruck}; if (!e) return null;
    e.scrollIntoView({block:'center'}); const r = e.getBoundingClientRect();
    return r.width && r.height ? {x: r.x + r.width/2, y: r.y + r.height/2} : null; })()`);
  if (!p) return false;
  for (const type of ['mousePressed', 'mouseReleased'])
    await cdp('Input.dispatchMouseEvent', { type, x: p.x, y: p.y, button: 'left', clickCount: 1 });
  return true;
}
async function frisch() {
  ANGEMELDET = false; SITZUNG = 'aktiv';
  await cdp('Page.navigate', { url: BASIS });
  await bis(`document.readyState === 'complete' && !!${bedien('/^anmelden$/i')}`, 5000);
  await pause(300);
  anfragen.length = 0; fehler.length = 0;
}
const logins = () => anfragen.filter(a => a === 'POST /api/login').length;
const formular = `(() => { const f = document.getElementById('login-form'); return !!f && document.body.contains(f) && f.getBoundingClientRect().height > 0; })()`;
const appText = `(document.getElementById('app')?.innerText || '')`;
async function anmeldeVersuch(benutzer, passwort) {
  await klicke(bedien('/^anmelden$/i'));
  await bis(formular, 2000);
  await werte(`document.getElementById('login-user').focus()`);
  await tippe(benutzer); await taste('Tab'); await tippe(passwort); await taste('Enter');
}

// ---- Voraussetzung und Start -----------------------------------------
await frisch();
const knopf = await werte(`!!${bedien('/^anmelden$/i')}`);
await klicke(bedien('/^anmelden$/i'));
pruefe(knopf && await bis(formular, 2000),
  'Voraussetzung: „Anmelden“ sichtbar, ein Klick zeigt das Anmeldeformular');

await frisch();
pruefe(!(await werte(formular)) && (await werte(appText)).includes('Probeprozess öffentlich'),
  'Start ohne Anmeldung: das öffentliche Dashboard, nicht die Anmeldemaske',
  (await werte(formular)) ? 'die Anmeldemaske steht da' : 'öffentlicher Prozess nicht sichtbar');

// ---- Anmelden --------------------------------------------------------
LOGIN = 'ok';
await frisch();
await anmeldeVersuch('abc', 'geheim');
await bis(`!${formular}`, 3000);
pruefe(logins() === 1 && !(await werte(formular)),
  'Enter im Passwortfeld schickt genau eine Anmeldung ab und meldet an',
  `${logins()} × POST /api/login, Formular ${await werte(formular) ? 'noch da' : 'weg'}`);

LOGIN = 'falsch';
await frisch();
await anmeldeVersuch('abc', 'falsch');
await bis(`${appText}.includes(${JSON.stringify(ABGELEHNT)})`, 3000);
const imFormular = await werte(`(() => { const f = document.getElementById('login-form'); const w = f && f.closest('.login-wrap');
  return !!w && document.body.contains(w) && w.innerText.includes(${JSON.stringify(ABGELEHNT)}); })()`);
pruefe(logins() === 1 && imFormular,
  'falsches Passwort (401): der Satz des Servers steht sichtbar am eingehängten Formular',
  `${logins()} × POST, sichtbar: „${(await werte(`document.getElementById('login-fehler')?.innerText || ''`))}“`);
pruefe(await werte(`document.getElementById('login-user')?.value === 'abc'`),
  'falsches Passwort (401): der Benutzername bleibt stehen',
  `Feld: „${await werte(`document.getElementById('login-user')?.value ?? '(kein Feld)'`)}“`);

LOGIN = 'warten';
await frisch();
await anmeldeVersuch('abc', 'falsch');
await bis(`${appText}.includes('Zu viele Fehlversuche')`, 3000);
pruefe(await werte(`${appText}.includes(${JSON.stringify(SAETZE.warten)})`) && await werte(formular),
  'Wartezeit (429): der Wartesatz der Bremse steht am Formular');

LOGIN = 'ausfall';
await frisch();
await anmeldeVersuch('abc', 'geheim');
await bis(`${appText}.includes('nicht erreichbar')`, 3000);
pruefe(await werte(`${appText}.includes(${JSON.stringify(AUSFALL)})`) && await werte(formular),
  'WebUntis-Ausfall (503): der Ausfallsatz steht am Formular');

// Eine Sitzung läuft ab: Der 401 eines anderen Aufrufs führt weiter zur
// Anmeldung. Ausgelöst über api() der Seite selbst.
LOGIN = 'ok';
await frisch();
await anmeldeVersuch('abc', 'geheim');
await bis(`!${formular}`, 3000);
SITZUNG = 'ab';
await werte(`api('/api/prozesse').catch(() => null)`);
pruefe(await bis(formular, 2000),
  'Sitzung abgelaufen: ein 401 außerhalb der Anmeldung führt zur Anmeldemaske');

// ---- Hilfe ohne Anmeldung --------------------------------------------
const hilfeOffen = `${appText}.includes('Häufige Fragen')`;
await frisch();
const amDesktop = await klicke(bedien('/^hilfe$/i'));
pruefe(amDesktop && await bis(hilfeOffen, 2000) && logins() === 0,
  'Hilfe ohne Anmeldung, Desktop (1280 px): „Hilfe“ in der Leiste öffnet die Hilfe');

await cdp('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 2, mobile: true });
await frisch();
const menue = await klicke(bedien('/^men[üu]$/i'));
await pause(300);
const amTelefon = menue && await klicke(bedien('/^hilfe$/i'));
pruefe(amTelefon && await bis(hilfeOffen, 2000) && logins() === 0,
  'Hilfe ohne Anmeldung, Telefon (390 px): „Menü“, dann „Hilfe“ öffnet die Hilfe');
await cdp('Emulation.clearDeviceMetricsOverride');

// ---- Was die Hilfe zitiert (sichtbarer Text, alle Fragen aufgeklappt) --
await frisch();
await klicke(bedien('/^hilfe$/i'));
await bis(hilfeOffen, 2000);
await klicke(bedien('/^häufige fragen/i'));
await bis(`document.querySelectorAll('.faq-frage').length > 0`, 2000);
const fragen = await werte(`document.querySelectorAll('.faq-frage').length`);
for (let i = 0; i < fragen; i++) await klicke(`document.querySelectorAll('.faq-frage')[${i}]`);
const faq = (await werte(`document.querySelector('.hilfe-faq')?.innerText || ''`)).replace(/\s+/g, ' ');
const offeneAntworten = await werte(`[...document.querySelectorAll('.faq-antwort')].filter(a => a.getBoundingClientRect().height > 0).length`);
pruefe(fragen > 0 && offeneAntworten === fragen && faq.length > 500,
  'Voraussetzung: FAQ sichtbar, alle Antworten aufgeklappt', `${offeneAntworten}/${fragen} offen, ${faq.length} Zeichen`);
const zitiert = (satz) => faq.includes('„' + satz + '“');
pruefe(SAETZE.abgelehnt !== null && zitiert(SAETZE.abgelehnt), 'Hilfe zitiert den Ablehnungssatz der Anwendung wörtlich');
pruefe(SAETZE.ausfall !== null && zitiert(SAETZE.ausfall), 'Hilfe zitiert den Ausfallsatz der Anwendung wörtlich');
const warteZitat = SAETZE.warten.replace(/\d+/, '…');
pruefe(zitiert(warteZitat), `Hilfe zitiert den Wartesatz der Bremse („${warteZitat}“)`);
const ZAHLWORT = ['null', 'einem', 'zwei', 'drei', 'vier', 'fünf', 'sechs', 'sieben', 'acht', 'neun', 'zehn'];
const wort = ZAHLWORT[SAETZE.schwelle ?? 5];
pruefe(SAETZE.schwelle !== null && new RegExp(`Nach ${wort} Fehlversuchen`).test(faq)
       && !/Nach (null|einem|zwei|drei|vier|fünf|sechs|sieben|acht|neun|zehn|\d+) Fehlversuchen/.test(faq.replace(`Nach ${wort} Fehlversuchen`, '')),
  `Hilfe nennt die Schwelle der Bremse („Nach ${wort} Fehlversuchen“) und keine andere`);
// FALLSTRICKE 8: kein Satz, der erklärt, dass bei einer Ablehnung das
// Passwort stimmte. Die allgemeine Auskunft „Ein korrektes Passwort allein
// reicht nicht“ bleibt (Auftrag S74, C) – sie sagt nichts über einen Versuch.
const verboten = faq.match(/Passwort (stimmte|war richtig)|Kontotyp|nicht freigeschaltet|Datensatz|nicht zugelassen/i);
pruefe(!verboten, 'Hilfe: kein Satz, den es nach FALLSTRICKE 8 nicht geben darf', verboten ? `„${verboten[0]}“` : '');

ws.close();
console.log(`  ${gruen}/${gruen + rot} im Browser bestanden`);
ende(rot ? 1 : 0);
