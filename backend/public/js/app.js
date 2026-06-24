/*!
 * Schulprozesse – prozesse.hornse.de
 * Copyright (C) 2026 Sebastian Horn, Friedrich-Rückert-Gymnasium Düsseldorf
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

// ============================================================================
// STATE
// ============================================================================
const STATE = {
  user: null,               // { webuntis_user, anzeigename, rolle }
  prozesse: [],             // alle Prozesse des eingeloggten Nutzers
  aktiverProzess: null,     // aktuell ausgewählter Prozess-Datensatz
  schritte: [],             // Schritte des aktiven Prozesses
  prozessId: null,          // ID des aktiven Prozesses
  teilnehmer: [],           // Teilnehmer des aktiven Prozesses
  phasen: [],               // Phasen der Vorlagenverwaltung (admin)
  vorlagen: [],             // Vorlagen (admin)
  vorlagenSets: [],         // Snapshots (admin)
  rollen: [],               // Zugriffsliste (admin)
  publicDashboard: [],      // [ { id, label, schritte } ] ohne Login
  ansicht: 'dashboard',     // 'dashboard' | 'checkliste' | 'zeitstrahl' | 'login'
  offeneSchritte: new Set(),
  offeneVorlagen: new Set(),
  ganttZoom: 1,
};

const $app          = document.getElementById('app');
const $werBinIch    = document.getElementById('wer-bin-ich');
const $shellUser    = document.getElementById('shell-user');
const $shellNav     = document.getElementById('shell-nav');
const $prozessLeiste = document.getElementById('prozess-leiste');

// ============================================================================
// API-Wrapper
// ============================================================================
async function api(path, { method = 'GET', body } = {}) {
  const headers = {};
  if (method !== 'GET') headers['X-Requested-With'] = 'SchuljahreswechselApp';
  if (body !== undefined) headers['Content-Type'] = 'application/json';

  const res = await fetch(path, {
    method,
    headers,
    credentials: 'same-origin',
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || `Fehler (${res.status})`);
  return data;
}

// ============================================================================
// Auth
// ============================================================================
async function checkAuth() {
  try { STATE.user = await api('/api/me'); } catch { STATE.user = null; }
}

async function doLogin(username, password) {
  STATE.user = await api('/api/login', { method: 'POST', body: { username, password } });
  await ladeAlles();
  STATE.ansicht = 'dashboard';
  render();
}

async function doLogout() {
  await api('/api/logout', { method: 'POST' });
  STATE.user = null;
  STATE.prozesse = [];
  STATE.aktiverProzess = null;
  STATE.schritte = [];
  STATE.prozessId = null;
  STATE.teilnehmer = [];
  STATE.phasen = [];
  STATE.vorlagen = [];
  STATE.vorlagenSets = [];
  STATE.rollen = [];
  STATE.ansicht = 'dashboard';
  STATE.offeneSchritte = new Set();
  STATE.offeneVorlagen = new Set();
  render();
}

// ============================================================================
// Daten laden
// ============================================================================
async function ladePublicDashboard() {
  STATE.publicDashboard = await api('/api/dashboard');
}

async function ladeAlles() {
  STATE.prozesse = await api('/api/prozesse');

  // Aktiven Prozess bestimmen – immer den ersten als Standard,
  // kein aktiv-Flag mehr nötig
  if (!STATE.aktiverProzess && STATE.prozesse.length > 0) {
    STATE.aktiverProzess = STATE.prozesse[0];
    STATE.prozessId      = STATE.aktiverProzess.id;
  } else if (STATE.prozessId) {
    STATE.aktiverProzess = STATE.prozesse.find((p) => p.id === STATE.prozessId) ?? STATE.prozesse[0];
    STATE.prozessId      = STATE.aktiverProzess?.id ?? null;
  }

  if (STATE.prozessId) {
    const res = await api(`/api/schritte?prozess_id=${STATE.prozessId}`);
    STATE.schritte  = res.schritte;

    const teilRes = await api(`/api/prozesse/${STATE.prozessId}/teilnehmer`);
    STATE.teilnehmer = teilRes;
  }

  // Phasen und Vorlagen für Admins UND Verantwortliche laden
  const istVerantwortlich = STATE.teilnehmer.some(
    (t) => t.webuntis_user === STATE.user?.webuntis_user && t.rolle === 'verantwortlich'
  );
  if (STATE.user?.rolle === 'admin' || istVerantwortlich) {
    STATE.phasen      = await api('/api/phasen');
    STATE.vorlagen    = await api('/api/vorlagen');
    STATE.vorlagenSets = await api('/api/vorlagen-sets');
  }

  if (STATE.user?.rolle === 'admin') {
    STATE.rollen = await api('/api/rollen');
  }
}

async function waehleProzess(id) {
  STATE.prozessId      = id;
  STATE.aktiverProzess = STATE.prozesse.find((p) => p.id === id);
  STATE.schritte       = [];
  STATE.teilnehmer     = [];
  STATE.offeneSchritte = new Set();

  const [schrittRes, teilRes] = await Promise.all([
    api(`/api/schritte?prozess_id=${id}`),
    api(`/api/prozesse/${id}/teilnehmer`),
  ]);
  STATE.schritte   = schrittRes.schritte;
  STATE.teilnehmer = teilRes;

  render();
}

async function toggleSchritt(id, erledigt) {
  await api(`/api/schritte/${id}`, { method: 'PATCH', body: { erledigt } });
  await waehleProzess(STATE.prozessId);
}

async function aktualisiereFeld(id, feld, wert) {
  await api(`/api/schritte/${id}`, { method: 'PATCH', body: { [feld]: wert } });
  const sofortRerender = ['kann_parallel', 'start_datum', 'geplantes_datum'];
  if (sofortRerender.includes(feld)) {
    const res = await api(`/api/schritte?prozess_id=${STATE.prozessId}`);
    STATE.schritte = res.schritte;
    await ladePublicDashboard();
    render();
  } else if (feld === 'kommentar') {
    const gefunden = STATE.schritte.find((s) => s.id === id);
    if (gefunden) gefunden.kommentar = wert;
  }
}

async function neuerProzess(label, beschreibung, oeffentlich, setId) {
  await api('/api/prozesse', { method: 'POST', body: { label, beschreibung, oeffentlich, set_id: setId || null } });
  await ladeAlles();
  await ladePublicDashboard();
  render();
}

async function prozessAktualisieren(id, felder) {
  await api(`/api/prozesse/${id}`, { method: 'PATCH', body: felder });
  await ladeAlles();
  await ladePublicDashboard();
  render();
}

async function aktiviereProzess(id) {
  await api(`/api/prozesse/${id}/aktivieren`, { method: 'POST' });
  await ladeAlles();
  render();
}

async function teilnehmerHinzufuegen(prozessId, webuntis_user, rolle) {
  await api(`/api/prozesse/${prozessId}/teilnehmer`, { method: 'POST', body: { webuntis_user, rolle } });
  const res = await api(`/api/prozesse/${prozessId}/teilnehmer`);
  STATE.teilnehmer = res;
  render();
}

async function teilnehmerEntfernen(prozessId, webuntis_user) {
  await api(`/api/prozesse/${prozessId}/teilnehmer/${encodeURIComponent(webuntis_user)}`, { method: 'DELETE' });
  const res = await api(`/api/prozesse/${prozessId}/teilnehmer`);
  STATE.teilnehmer = res;
  render();
}

async function setzeRolle(webuntis_user, rolle, anzeigename) {
  await api('/api/rollen', { method: 'POST', body: { webuntis_user, rolle, anzeigename } });
  await ladeAlles();
  render();
}

async function loescheRolle(webuntis_user) {
  await api(`/api/rollen/${encodeURIComponent(webuntis_user)}`, { method: 'DELETE' });
  await ladeAlles();
  render();
}

async function neueVorlage(phase_id, titel) {
  await api('/api/vorlagen', { method: 'POST', body: { phase_id, titel } });
  await ladeAlles();
  await ladePublicDashboard();
  render();
}

async function vorlageAktualisieren(id, felder) {
  await api(`/api/vorlagen/${id}`, { method: 'PATCH', body: felder });
  await ladeAlles();
  await ladePublicDashboard();
  render();
}

async function reihenfolgeAendern(phase_id, vorlage_ids) {
  await api('/api/vorlagen/reihenfolge', { method: 'POST', body: { phase_id, vorlage_ids } });
  await ladeAlles();
  await ladePublicDashboard();
  render();
}

async function neuePhase(name, farbe) {
  await api('/api/phasen', { method: 'POST', body: { name, farbe } });
  await ladeAlles();
  render();
}

async function phaseAktualisieren(id, felder) {
  await api(`/api/phasen/${id}`, { method: 'PATCH', body: felder });
  await ladeAlles();
  await ladePublicDashboard();
  render();
}

async function reihenfolgePhasenAendern(phasen_ids) {
  await api('/api/phasen/reihenfolge', { method: 'POST', body: { phasen_ids } });
  await ladeAlles();
  await ladePublicDashboard();
  render();
}

async function speichereVorlagenSet(name, beschreibung) {
  await api('/api/vorlagen-sets', { method: 'POST', body: { name, beschreibung } });
  await ladeAlles();
  render();
}

async function loescheVorlagenSet(id) {
  await api(`/api/vorlagen-sets/${id}`, { method: 'DELETE' });
  await ladeAlles();
  render();
}

// ============================================================================
// Hilfsfunktionen (Datum, Markdown, Farbwahl)
// ============================================================================
function heuteISO() { return new Date().toISOString().slice(0, 10); }
function inNTagenISO(n) { const d = new Date(); d.setDate(d.getDate() + n); return d.toISOString().slice(0, 10); }
function formatDatum(iso) { const [, m, t] = iso.split('-'); return `${t}.${m}.`; }

const VORDEFINIERTE_FARBEN = [
  '#D98A2B','#E85D4A','#C0392B','#B5577A','#8E44AD',
  '#5B6FA8','#2980B9','#16A085','#3D7B6F','#27AE60',
  '#2ECC71','#F39C12','#7F8C8D','#3B3B3B','#1A1A2E',
];

function renderFarbwahl(aktuelleFarbe, onChange) {
  const container = document.createElement('div');
  container.className = 'farbwahl';
  const palette = document.createElement('div');
  palette.className = 'farbwahl-palette';
  for (const farbe of VORDEFINIERTE_FARBEN) {
    const k = document.createElement('button');
    k.type = 'button';
    k.className = 'farbwahl-kaestchen' + (farbe === aktuelleFarbe ? ' aktiv' : '');
    k.style.background = farbe; k.title = farbe;
    k.addEventListener('click', () => {
      container.querySelectorAll('.farbwahl-kaestchen').forEach((x) => x.classList.remove('aktiv'));
      k.classList.add('aktiv'); hexInput.value = farbe; vorschau.style.background = farbe; onChange(farbe);
    });
    palette.appendChild(k);
  }
  container.appendChild(palette);
  const eigeneZeile = document.createElement('div'); eigeneZeile.className = 'farbwahl-eigene';
  const vorschau = document.createElement('div'); vorschau.className = 'farbwahl-vorschau'; vorschau.style.background = aktuelleFarbe;
  const hexInput = document.createElement('input'); hexInput.type = 'text'; hexInput.className = 'farbwahl-hex';
  hexInput.value = aktuelleFarbe; hexInput.maxLength = 7; hexInput.placeholder = '#5B6FA8';
  hexInput.addEventListener('input', () => {
    if (/^#[0-9A-Fa-f]{6}$/.test(hexInput.value.trim())) {
      vorschau.style.background = hexInput.value.trim();
      container.querySelectorAll('.farbwahl-kaestchen').forEach((x) => x.classList.remove('aktiv'));
      onChange(hexInput.value.trim());
    }
  });
  eigeneZeile.appendChild(vorschau); eigeneZeile.appendChild(hexInput);
  container.appendChild(eigeneZeile);
  return container;
}

function inlineFormatierung(text) {
  return text
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.+?)\*/g, '<em>$1</em>')
    .replace(/_(.+?)_/g, '<em>$1</em>')
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    .replace(/\[(.+?)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
}

function markdownZuHtml(text) {
  if (!text) return '';
  const escaped = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  const zeilen = escaped.split('\n');
  const ausgabe = []; let listenArt = null;
  function listeSchliessen() { if (listenArt) { ausgabe.push(`</${listenArt}>`); listenArt = null; } }
  for (const zeile of zeilen) {
    const ulMatch = zeile.match(/^[-*]\s+(.*)/);
    const olMatch = zeile.match(/^\d+\.\s+(.*)/);
    if (ulMatch) { if (listenArt !== 'ul') { listeSchliessen(); ausgabe.push('<ul>'); listenArt = 'ul'; } ausgabe.push(`<li>${inlineFormatierung(ulMatch[1])}</li>`); }
    else if (olMatch) { if (listenArt !== 'ol') { listeSchliessen(); ausgabe.push('<ol>'); listenArt = 'ol'; } ausgabe.push(`<li>${inlineFormatierung(olMatch[1])}</li>`); }
    else { listeSchliessen(); ausgabe.push(zeile.trim() === '' ? '<br>' : `<p style="margin:0 0 6px;">${inlineFormatierung(zeile)}</p>`); }
  }
  listeSchliessen();
  return ausgabe.join('\n');
}

function textareaFormatierungEinfuegen(textarea, { umschliessen, zeilenPraefix } = {}) {
  const start = textarea.selectionStart, end = textarea.selectionEnd;
  if (zeilenPraefix) {
    const zs = textarea.value.lastIndexOf('\n', start - 1) + 1;
    let ze = textarea.value.indexOf('\n', end); if (ze === -1) ze = textarea.value.length;
    const block = textarea.value.slice(zs, ze);
    const neuerBlock = block.split('\n').map((z) => z.startsWith(zeilenPraefix) ? z : zeilenPraefix + z).join('\n');
    textarea.value = textarea.value.slice(0, zs) + neuerBlock + textarea.value.slice(ze);
    textarea.focus(); textarea.setSelectionRange(zs, zs + neuerBlock.length);
  } else {
    const ausgewaehlt = textarea.value.slice(start, end) || 'Text';
    textarea.value = textarea.value.slice(0, start) + umschliessen + ausgewaehlt + umschliessen + textarea.value.slice(end);
    textarea.focus(); textarea.setSelectionRange(start + umschliessen.length, start + umschliessen.length + ausgewaehlt.length);
  }
  textarea.dispatchEvent(new Event('input'));
}

function phasenAnzeigeName(phaseName, phaseReihenfolge, alleSchritte) {
  const map = new Map();
  for (const s of alleSchritte) { if (!map.has(s.phase)) map.set(s.phase, s.phase_reihenfolge ?? 0); }
  const sortiert = [...map.entries()].sort((a, b) => a[1] - b[1]);
  const index = sortiert.findIndex(([name]) => name === phaseName);
  const nummer = index >= 0 ? index + 1 : phaseReihenfolge;
  return `${nummer}. ${phaseName.replace(/^\d+\.\s*/, '')}`;
}

function phasenAnzeigeNameAusListe(phase) {
  const index = STATE.phasen.findIndex((p) => p.id === phase.id);
  return index >= 0 ? `${index + 1}. ${phase.name.replace(/^\d+\.\s*/, '')}` : phase.name;
}

function ueberschneidenSich(a, b) {
  if (!a.geplantes_datum || !b.geplantes_datum) return false;
  const aStart = a.start_datum ?? a.geplantes_datum, aEnde = a.geplantes_datum;
  const bStart = b.start_datum ?? b.geplantes_datum, bEnde = b.geplantes_datum;
  return aStart <= bEnde && bStart <= aEnde;
}

function berechneParallelIds(liste) {
  const terminiert = liste.filter((s) => s.geplantes_datum);
  const ids = new Set();
  for (let i = 0; i < terminiert.length; i++)
    for (let j = i + 1; j < terminiert.length; j++)
      if (ueberschneidenSich(terminiert[i], terminiert[j])) { ids.add(terminiert[i].id ?? i); ids.add(terminiert[j].id ?? j); }
  return ids;
}

// ============================================================================
// Rendering – Haupt-Render
// ============================================================================
let dragZustand = null;
let dragZustandPhase = null;

function render() {
  renderShell();
  renderProzessLeiste();

  $app.innerHTML = '';

  if (STATE.ansicht === 'login') {
    $app.appendChild(renderLogin());
  } else if (STATE.ansicht === 'checkliste' && STATE.user) {
    $app.appendChild(renderChecklist());
  } else if (STATE.ansicht === 'zeitstrahl') {
    $app.appendChild(renderZeitstrahl());
  } else if (STATE.ansicht === 'prozess-verwalten' && STATE.user) {
    $app.appendChild(renderProzessVerwaltungSeite());
  } else if (STATE.ansicht === 'admin' && STATE.user?.rolle === 'admin') {
    $app.appendChild(renderAdminSeite());
  } else {
    $app.appendChild(renderDashboard());
  }
}

function renderShell() {
  // Benutzer-Bereich oben rechts
  if (STATE.user) {
    $shellUser.innerHTML = '';
    const wrap = document.createElement('div');
    wrap.className = 'shell-user';
    wrap.innerHTML = `
      <span class="shell-user-name">${STATE.user.anzeigename}</span>
      <span class="shell-user-rolle rolle-${STATE.user.rolle}">${STATE.user.rolle}</span>`;
    const abmelden = document.createElement('button');
    abmelden.className = 'shell-abmelden';
    abmelden.textContent = 'Abmelden';
    abmelden.addEventListener('click', doLogout);
    wrap.appendChild(abmelden);
    $shellUser.appendChild(wrap);
  } else {
    $shellUser.innerHTML = '';
    const btn = document.createElement('button');
    btn.className = 'shell-anmelden';
    btn.textContent = 'Anmelden';
    btn.addEventListener('click', () => { STATE.ansicht = 'login'; render(); });
    $shellUser.appendChild(btn);
  }

  // Navigation
  $shellNav.innerHTML = '';

  const tabs = [
    { id: 'dashboard',  label: 'Dashboard',  icon: '◎',  immer: true },
    { id: 'checkliste', label: 'Checkliste', icon: '☑',  nurLogin: true },
    { id: 'zeitstrahl', label: 'Zeitstrahl', icon: '◫',  immer: true },
  ];

  tabs.forEach((t) => {
    if (t.nurLogin && !STATE.user) return;
    const btn = document.createElement('button');
    btn.className = 'nav-tab' + (STATE.ansicht === t.id ? ' aktiv' : '');
    btn.innerHTML = `<span class="nav-icon">${t.icon}</span>${t.label}`;
    btn.addEventListener('click', () => { STATE.ansicht = t.id; render(); });
    $shellNav.appendChild(btn);
  });

  // Trennlinie vor prozessspezifischen Tabs
  if (STATE.user && STATE.prozessId) {
    const kannVerwalten = STATE.user.rolle === 'admin' ||
      STATE.teilnehmer.some((t) =>
        t.webuntis_user === STATE.user.webuntis_user && t.rolle === 'verantwortlich'
      );

    if (kannVerwalten) {
      const sep = document.createElement('div'); sep.className = 'nav-sep';
      $shellNav.appendChild(sep);
      const btn = document.createElement('button');
      btn.className = 'nav-tab' + (STATE.ansicht === 'prozess-verwalten' ? ' aktiv' : '');
      btn.innerHTML = `<span class="nav-icon">⚙</span>Prozess verwalten`;
      btn.addEventListener('click', () => { STATE.ansicht = 'prozess-verwalten'; render(); });
      $shellNav.appendChild(btn);
    }

    if (STATE.user.rolle === 'admin') {
      const btn = document.createElement('button');
      btn.className = 'nav-tab' + (STATE.ansicht === 'admin' ? ' aktiv' : '');
      btn.innerHTML = `<span class="nav-icon">⚡</span>Admin`;
      btn.addEventListener('click', () => { STATE.ansicht = 'admin'; render(); });
      $shellNav.appendChild(btn);
    }
  }
}

function renderProzessLeiste() {
  if (!STATE.user || STATE.prozesse.length === 0) {
    $prozessLeiste.style.display = 'none';
    return;
  }
  $prozessLeiste.style.display = 'flex';
  $prozessLeiste.innerHTML = '';

  STATE.prozesse.forEach((p) => {
    const tab = document.createElement('button');
    tab.className = 'prozess-tab' + (p.id === STATE.prozessId ? ' aktiv' : '');
    const schloss = p.oeffentlich ? '' : ' 🔒';
    tab.textContent = p.label + schloss;
    tab.title = p.beschreibung || p.label;
    tab.addEventListener('click', async () => {
      if (p.id === STATE.prozessId) return;
      await waehleProzess(p.id);
    });
    $prozessLeiste.appendChild(tab);
  });
}

// renderProzessVerwaltungSeite – eigene Seite statt Block am Ende
function renderProzessVerwaltungSeite() {
  const container = document.createElement('div');
  container.innerHTML = `<div class="page-header">
    <h2 class="page-title">Prozess verwalten</h2>
    <span class="page-subtitle">${STATE.aktiverProzess?.label ?? ''}</span>
  </div>`;
  container.appendChild(renderProzessVerwaltungInhalt());

  // Verantwortliche können auch Phasen und Schritte ihres Prozesses verwalten
  const vorlagenSection = document.createElement('div');
  vorlagenSection.className = 'admin-section';
  vorlagenSection.style.marginTop = '24px';
  vorlagenSection.appendChild(renderVorlagenVerwaltung());
  container.appendChild(vorlagenSection);

  return container;
}

// renderAdminSeite – eigene Seite statt Block am Ende
function renderAdminSeite() {
  const container = document.createElement('div');
  container.innerHTML = `<div class="page-header">
    <h2 class="page-title">Administration</h2>
  </div>`;
  container.appendChild(renderProzesseBlock());
  container.appendChild(renderZugriffBlock());
  container.appendChild(renderVorlagenVerwaltung());
  container.appendChild(renderAktivitaetsprotokoll());
  return container;
}

// ============================================================================
// Login
// ============================================================================
function renderLogin() {
  const wrapper = document.createElement('div');
  wrapper.className = 'login-wrap';
  wrapper.innerHTML = `
    <h2>Anmelden</h2>
    <p>WebUntis-Zugangsdaten verwenden – nur für freigegebene Personen.</p>
    <div id="login-fehler"></div>
    <form id="login-form">
      <label for="login-user">Benutzername</label>
      <input id="login-user" type="text" autocomplete="username" required>
      <label for="login-pass">Passwort</label>
      <input id="login-pass" type="password" autocomplete="current-password" required>
      <button class="btn" type="submit">Anmelden</button>
    </form>`;
  wrapper.querySelector('#login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const $fehler = wrapper.querySelector('#login-fehler');
    $fehler.innerHTML = '';
    try {
      await doLogin(wrapper.querySelector('#login-user').value, wrapper.querySelector('#login-pass').value);
    } catch (err) {
      $fehler.innerHTML = `<p class="fehler">${err.message}</p>`;
    }
  });
  return wrapper;
}

// ============================================================================
// Dashboard (öffentlich – Prozess-Tabs)
// ============================================================================
function renderDashboard() {
  const container = document.createElement('div');

  if (STATE.user) {
    // Eingeloggt: Dashboard des aktuell gewählten Prozesses
    if (!STATE.aktiverProzess) {
      container.appendChild(Object.assign(document.createElement('p'),
        { textContent: 'Kein Prozess zugewiesen. Bitte einen Admin bitten.' }));
      return container;
    }
    return renderDashboardFuerProzess(STATE.schritte, STATE.aktiverProzess.label, true);
  }

  // Öffentlich: alle öffentlichen Prozesse als Tabs
  const liste = STATE.publicDashboard;
  if (!liste || liste.length === 0) {
    const p = document.createElement('p');
    p.textContent = 'Aktuell keine öffentlichen Prozesse.';
    container.appendChild(p);
    return container;
  }

  if (liste.length === 1) {
    return renderDashboardFuerProzess(liste[0].schritte, liste[0].label, false);
  }

  // Mehrere öffentliche Prozesse als Tabs
  let aktiverTab = 0;
  const tabs = document.createElement('div');
  tabs.className = 'prozess-tabs kein-druck';
  liste.forEach((p, i) => {
    const btn = document.createElement('button');
    btn.className = 'prozess-tab' + (i === 0 ? ' aktiv' : '');
    btn.textContent = p.label;
    btn.addEventListener('click', () => {
      aktiverTab = i;
      tabs.querySelectorAll('.prozess-tab').forEach((b, j) => b.classList.toggle('aktiv', j === i));
      inhalt.innerHTML = '';
      inhalt.appendChild(renderDashboardFuerProzess(liste[i].schritte, liste[i].label, false));
    });
    tabs.appendChild(btn);
  });
  container.appendChild(tabs);
  const inhalt = document.createElement('div');
  inhalt.appendChild(renderDashboardFuerProzess(liste[0].schritte, liste[0].label, false));
  container.appendChild(inhalt);
  return container;
}

function renderDashboardFuerProzess(schritte, label, eingeloggt) {
  const container = document.createElement('div');

  if (!eingeloggt && label) {
    const h = document.createElement('p');
    h.className = 'dash-schuljahr';
    h.textContent = label;
    container.appendChild(h);
  }

  if (!schritte || schritte.length === 0) {
    const p = document.createElement('p');
    p.textContent = 'Keine Schritte vorhanden.';
    container.appendChild(p);
    return container;
  }

  const { aktuell, ueberfaellig, demnaechst, phasen, parallelIds } = berechneDashboardDaten(schritte);
  const heute = heuteISO();

  const aktuellBlock = document.createElement('div');
  aktuellBlock.className = 'dash-block dash-aktuell';
  aktuellBlock.style.setProperty('--accent', aktuell?.phase_farbe ?? 'var(--accent-default)');
  if (aktuell) {
    const metaTeile = [phasenAnzeigeName(aktuell.phase, aktuell.phase_reihenfolge, schritte)];
    if (eingeloggt && aktuell.verantwortlich_anzeigename !== undefined) {
      metaTeile.push(aktuell.verantwortlich_anzeigename || 'noch niemand zugewiesen');
    }
    const parallelHinweis = (aktuell.kann_parallel || parallelIds.has(aktuell.id))
      ? `<span class="parallel-badge" style="margin-left:6px;">⇉ parallel</span>` : '';
    aktuellBlock.innerHTML = `
      <p class="dash-label">Aktuell dran</p>
      <p class="dash-titel" style="color:${aktuell.phase_farbe}">${aktuell.titel}${parallelHinweis}</p>
      <p class="dash-meta">${metaTeile.join(' · ')}</p>`;
  } else {
    aktuellBlock.innerHTML = `<p class="dash-label">Aktuell dran</p><p class="dash-titel">Alles erledigt 🎉</p>`;
  }
  const grid = document.createElement('div');
  grid.className = 'dash-grid';
  grid.appendChild(aktuellBlock);
  if (ueberfaellig.length) grid.appendChild(renderDashListe('Überfällig', ueberfaellig, true, parallelIds));
  if (demnaechst.length) grid.appendChild(renderDashListe('Demnächst (14 Tage)', demnaechst, false, parallelIds));

  const phasenBlock = document.createElement('div');
  phasenBlock.className = 'dash-block';
  phasenBlock.innerHTML = '<p class="dash-label">Fortschritt je Phase</p>';
  for (const [phase, daten] of phasen) {
    const prozent = daten.gesamt ? Math.round((daten.erledigt / daten.gesamt) * 100) : 0;
    const zeile = document.createElement('div');
    zeile.className = 'dash-phasenzeile';
    zeile.innerHTML = `
      <span class="dash-phasenname" style="color:${daten.farbe}">${phasenAnzeigeName(phase, daten.reihenfolge, schritte)}</span>
      <div class="progress-track" style="flex:1;"><div class="progress-fill" style="width:${prozent}%;background:${daten.farbe}"></div></div>
      <span class="progress-label">${daten.erledigt}/${daten.gesamt}</span>`;
    phasenBlock.appendChild(zeile);
  }
  grid.appendChild(phasenBlock);
  container.appendChild(grid);
  return container;
}

function berechneDashboardDaten(liste) {
  const heute = heuteISO(), in14 = inNTagenISO(14);
  const offen = liste.filter((s) => !s.erledigt);
  const aktuell = offen[0] ?? null;
  const ueberfaellig = offen.filter((s) => s.geplantes_datum && s.geplantes_datum < heute)
    .sort((a, b) => a.geplantes_datum.localeCompare(b.geplantes_datum));
  const demnaechst = offen.filter((s) => s.geplantes_datum && s.geplantes_datum >= heute && s.geplantes_datum <= in14)
    .sort((a, b) => a.geplantes_datum.localeCompare(b.geplantes_datum));
  const parallelIds = berechneParallelIds(offen);
  const phasen = new Map();
  for (const s of liste) {
    if (!phasen.has(s.phase)) phasen.set(s.phase, { farbe: s.phase_farbe, reihenfolge: s.phase_reihenfolge ?? 0, gesamt: 0, erledigt: 0 });
    const e = phasen.get(s.phase); e.gesamt++; if (s.erledigt) e.erledigt++;
  }
  return { aktuell, ueberfaellig, demnaechst, phasen, parallelIds };
}

function renderDashListe(titel, liste, istUeberfaellig, parallelIds = new Set()) {
  const block = document.createElement('div');
  block.className = 'dash-block';
  const items = liste.map((s) => {
    const p = s.kann_parallel || parallelIds.has(s.id);
    return `<li>
      <span class="dash-datum ${istUeberfaellig ? 'dash-datum-rot' : ''}">${formatDatum(s.geplantes_datum)}</span>
      <span>${s.titel}</span>
      ${p ? '<span class="parallel-badge">⇉</span>' : ''}
    </li>`;
  }).join('');
  block.innerHTML = `<p class="dash-label">${titel}</p><ul class="dash-liste">${items}</ul>`;
  return block;
}

// ============================================================================
// Checkliste
// ============================================================================
function renderChecklist() {
  const container = document.createElement('div');

  if (!STATE.prozessId || STATE.schritte.length === 0) {
    const p = document.createElement('p');
    p.textContent = 'Keine Schritte vorhanden.';
    container.appendChild(p);
    return container;
  }

  const gesamt = STATE.schritte.length;
  const erledigt = STATE.schritte.filter((s) => s.erledigt).length;
  const prozent = gesamt ? Math.round((erledigt / gesamt) * 100) : 0;

  const fortschritt = document.createElement('div');
  fortschritt.className = 'progress-wrap';
  fortschritt.innerHTML = `
    <div class="progress-track"><div class="progress-fill" style="width:${prozent}%"></div></div>
    <span class="progress-label">${erledigt} / ${gesamt}</span>`;
  container.appendChild(fortschritt);
  container.appendChild(renderExportLeiste('checkliste'));

  const parallelIds = berechneParallelIds(STATE.schritte.filter((s) => !s.erledigt));
  let aktuellePhase = null, aktiverParallelBlock = null, aktivesParallelDatum = null;

  for (const schritt of STATE.schritte) {
    if (schritt.phase !== aktuellePhase) {
      aktuellePhase = schritt.phase;
      if (aktiverParallelBlock) { container.appendChild(aktiverParallelBlock); aktiverParallelBlock = null; aktivesParallelDatum = null; }
      const h = document.createElement('div');
      h.className = 'phase-title'; h.style.color = schritt.phase_farbe;
      h.textContent = phasenAnzeigeName(schritt.phase, schritt.phase_reihenfolge, STATE.schritte);
      container.appendChild(h);
    }

    const istParallel = schritt.geplantes_datum && parallelIds.has(schritt.id);
    if (istParallel && schritt.geplantes_datum === aktivesParallelDatum) {
      aktiverParallelBlock.appendChild(renderSchritt(schritt));
    } else {
      if (aktiverParallelBlock) { container.appendChild(aktiverParallelBlock); aktiverParallelBlock = null; aktivesParallelDatum = null; }
      if (istParallel) {
        aktiverParallelBlock = document.createElement('div');
        aktiverParallelBlock.className = 'parallel-gruppe';
        const lbl = document.createElement('div'); lbl.className = 'parallel-gruppe-label';
        lbl.innerHTML = `<span class="parallel-badge">⇉ parallel – ${formatDatum(schritt.geplantes_datum)}</span>`;
        aktiverParallelBlock.appendChild(lbl);
        aktiverParallelBlock.appendChild(renderSchritt(schritt));
        aktivesParallelDatum = schritt.geplantes_datum;
      } else {
        container.appendChild(renderSchritt(schritt));
      }
    }
  }
  if (aktiverParallelBlock) container.appendChild(aktiverParallelBlock);
  return container;
}

function renderSchritt(schritt) {
  const el = document.createElement('div');
  el.className = 'schritt' + (schritt.erledigt ? ' erledigt' : '') + (schritt.kann_parallel ? ' parallel' : '');
  el.style.setProperty('--accent', schritt.phase_farbe);

  const parallelBadge = schritt.kann_parallel
    ? `<span class="parallel-badge" title="Kann parallel erledigt werden">⇉ parallel</span>` : '';

  el.innerHTML = `
    <div class="schritt-zeile">
      <span class="checkbox ${schritt.erledigt ? 'checked' : ''}" data-rolle="checkbox"></span>
      <span class="schritt-text ${schritt.erledigt ? 'erledigt' : ''}">${schritt.titel}</span>
      ${parallelBadge}
      <span class="chev" data-rolle="chevron">▸</span>
    </div>
    <div class="schritt-detail" data-rolle="detail">
      <div class="detail-text">${markdownZuHtml(schritt.beschreibung)}</div>
      <div class="felder">
        <div class="feld"><label>Verantwortlich</label>
          <input type="text" data-feld="verantwortlich_anzeigename" value="${schritt.verantwortlich_anzeigename ?? ''}">
        </div>
        <div class="feld"><label>Start</label>
          <input type="date" data-feld="start_datum" value="${schritt.start_datum ?? ''}">
        </div>
        <div class="feld"><label>Zieldatum</label>
          <input type="date" data-feld="geplantes_datum" value="${schritt.geplantes_datum ?? ''}">
        </div>
        <div class="feld feld-breit"><label>Kommentar (nur für Angemeldete)</label>
          <textarea data-feld="kommentar" rows="2" placeholder="Kurznotiz …">${schritt.kommentar ?? ''}</textarea>
        </div>
        <div class="feld"><label>Parallel möglich</label>
          <label class="toggle-wrap">
            <input type="checkbox" data-feld="kann_parallel" ${schritt.kann_parallel ? 'checked' : ''}>
            <span class="toggle-label">für diesen Prozess</span>
          </label>
        </div>
      </div>
    </div>`;

  const detailEl = el.querySelector('[data-rolle="detail"]');
  const chevronEl = el.querySelector('[data-rolle="chevron"]');
  if (STATE.offeneSchritte.has(schritt.id)) { detailEl.classList.add('offen'); chevronEl.classList.add('offen'); }

  el.querySelector('[data-rolle="checkbox"]').addEventListener('click', (e) => {
    e.stopPropagation(); toggleSchritt(schritt.id, !schritt.erledigt);
  });
  el.querySelector('.schritt-zeile').addEventListener('click', () => {
    const istOffen = detailEl.classList.toggle('offen'); chevronEl.classList.toggle('offen');
    if (istOffen) STATE.offeneSchritte.add(schritt.id); else STATE.offeneSchritte.delete(schritt.id);
  });
  el.querySelectorAll('[data-feld]').forEach((input) => {
    input.addEventListener('change', () => {
      const wert = input.type === 'checkbox' ? input.checked : input.value;
      aktualisiereFeld(schritt.id, input.dataset.feld, wert);
      if (input.dataset.feld === 'kommentar') {
        const f = STATE.schritte.find((s) => s.id === schritt.id);
        if (f) f.kommentar = wert;
      }
    });
  });
  return el;
}

// ============================================================================
// Export
// ============================================================================
function renderExportLeiste(typ) {
  const leiste = document.createElement('div');
  leiste.className = 'export-leiste kein-druck';

  if (typ === 'checkliste') {
    const csvBtn = document.createElement('button');
    csvBtn.className = 'btn-sekundaer btn'; csvBtn.style.width = 'auto'; csvBtn.innerHTML = '⬇ CSV';
    csvBtn.addEventListener('click', () => {
      window.location.href = `/api/export/csv?prozess_id=${STATE.prozessId}`;
    });
    const pdfBtn = document.createElement('button');
    pdfBtn.className = 'btn-sekundaer btn'; pdfBtn.style.width = 'auto'; pdfBtn.innerHTML = '🖨 PDF';
    pdfBtn.addEventListener('click', () => window.print());
    leiste.appendChild(csvBtn); leiste.appendChild(pdfBtn);
  }

  if (typ === 'zeitstrahl') {
    const svgBtn = document.createElement('button');
    svgBtn.className = 'btn-sekundaer btn'; svgBtn.style.width = 'auto'; svgBtn.innerHTML = '⬇ SVG';
    svgBtn.addEventListener('click', () => exportiereGanttAlsSvg());
    const pdfBtn = document.createElement('button');
    pdfBtn.className = 'btn-sekundaer btn'; pdfBtn.style.width = 'auto'; pdfBtn.innerHTML = '🖨 Drucken';
    pdfBtn.addEventListener('click', () => window.print());
    leiste.appendChild(svgBtn); leiste.appendChild(pdfBtn);
  }
  return leiste;
}

// ============================================================================
// Zeitstrahl (Gantt + Timeline) – vereinfacht, gleiche Logik wie alte App
// ============================================================================
function renderZeitstrahl() {
  const eingeloggt = !!STATE.user;
  const container = document.createElement('div');

  if (!eingeloggt && STATE.publicDashboard && STATE.publicDashboard.length > 1) {
    // Öffentlich mit mehreren Prozessen: Tabs anzeigen
    let aktiverOeffentlicherIdx = 0;
    const tabs = document.createElement('div');
    tabs.className = 'prozess-tabs kein-druck';
    const inhalt = document.createElement('div');

    STATE.publicDashboard.forEach((p, i) => {
      const btn = document.createElement('button');
      btn.className = 'prozess-tab' + (i === 0 ? ' aktiv' : '');
      btn.textContent = p.label;
      btn.addEventListener('click', () => {
        aktiverOeffentlicherIdx = i;
        tabs.querySelectorAll('.prozess-tab').forEach((b, j) => b.classList.toggle('aktiv', j === i));
        inhalt.innerHTML = '';
        renderZeitstrahlInhalt(p.schritte, false, inhalt);
      });
      tabs.appendChild(btn);
    });
    container.appendChild(tabs);
    renderZeitstrahlInhalt(STATE.publicDashboard[0].schritte, false, inhalt);
    container.appendChild(inhalt);
    return container;
  }

  const liste = eingeloggt ? STATE.schritte : (STATE.publicDashboard?.[0]?.schritte ?? []);
  renderZeitstrahlInhalt(liste, eingeloggt, container);
  return container;
}

function renderZeitstrahlInhalt(liste, eingeloggt, container) {
  if (!liste || liste.length === 0) {
    container.appendChild(Object.assign(document.createElement('p'),
      { textContent: 'Keine Daten.', style: 'color:var(--muted);font-size:13px;' }));
    return container;
  }

  let aktiverUntertab = 'gantt';

  // Wrapper für alles was zum Zeitstrahl gehört (Tabs + Inhalt)
  const zeitstrahlWrapper = document.createElement('div');
  container.appendChild(zeitstrahlWrapper);

  function renderUntertabs() {
    zeitstrahlWrapper.innerHTML = '';

    const tabs = document.createElement('div');
    tabs.className = 'zeitstrahl-tabs kein-druck';
    tabs.innerHTML = `
      <button class="zt-tab ${aktiverUntertab === 'gantt' ? 'aktiv' : ''}" data-zt="gantt">Gantt</button>
      <button class="zt-tab ${aktiverUntertab === 'timeline' ? 'aktiv' : ''}" data-zt="timeline">Timeline</button>`;
    tabs.appendChild(renderExportLeiste('zeitstrahl'));
    tabs.querySelectorAll('[data-zt]').forEach((btn) => {
      btn.addEventListener('click', () => {
        aktiverUntertab = btn.dataset.zt;
        renderUntertabs();
      });
    });

    const inhalt = document.createElement('div');
    inhalt.className = 'zeitstrahl-inhalt';
    inhalt.appendChild(
      aktiverUntertab === 'gantt'
        ? renderGantt(liste, eingeloggt)
        : renderTimeline(liste, eingeloggt)
    );

    zeitstrahlWrapper.appendChild(tabs);
    zeitstrahlWrapper.appendChild(inhalt);
  }

  renderUntertabs();
  return container;
}

function renderGantt(liste, eingeloggt) {
  const mitDatum = liste.filter((s) => s.geplantes_datum);
  const ohneDatum = liste.filter((s) => !s.geplantes_datum);
  const wrapper = document.createElement('div');
  if (mitDatum.length === 0) {
    wrapper.appendChild(Object.assign(document.createElement('p'), { textContent: 'Noch keine Datumsangaben eingetragen.', style: 'color:var(--muted);font-size:13px;' }));
    if (ohneDatum.length) wrapper.appendChild(renderOhneDatumListe(ohneDatum));
    return wrapper;
  }
  const daten = mitDatum.flatMap((s) => s.start_datum ? [s.start_datum, s.geplantes_datum] : [s.geplantes_datum]).sort();
  const minDatum = new Date(daten[0]), maxDatum = new Date(daten[daten.length - 1]);
  const heute = heuteISO(), zoom = STATE.ganttZoom;
  const spanTage = Math.max(7, Math.ceil((maxDatum - minDatum) / 86400000) + 2);
  const spanSpalten = Math.ceil(spanTage / zoom);

  const zoomZeile = document.createElement('div');
  zoomZeile.className = 'gantt-zoom kein-druck';
  const zoomLabels = { 1: 'Tagesansicht', 7: 'Wochenansicht' };
  zoomZeile.innerHTML = `<span>Zoom:</span><input type="range" min="1" max="7" step="1" value="${zoom}"><span>${zoomLabels[zoom] || zoom + ' Tage/Spalte'}</span>`;
  zoomZeile.querySelector('input').addEventListener('input', (e) => {
    STATE.ganttZoom = Number(e.target.value);
    wrapper.replaceWith(renderGantt(liste, eingeloggt));
  });
  wrapper.appendChild(zoomZeile);

  const phasenReihenfolge = [];
  const gesehene = new Set();
  for (const s of liste) { if (!gesehene.has(s.phase)) { phasenReihenfolge.push(s.phase); gesehene.add(s.phase); } }

  const gantt = document.createElement('div'); gantt.className = 'gantt-wrap';
  const kopfzeile = document.createElement('div'); kopfzeile.className = 'gantt-kopf';
  let kopfHtml = '<div class="gantt-label-zelle"></div>';
  // Beschriftungsintervall: bei vielen Spalten nur jeden n-ten beschriften
  const labelIntervall = spanSpalten > 60 ? 7 : spanSpalten > 30 ? 3 : spanSpalten > 15 ? 2 : 1;
  for (let i = 0; i < spanSpalten; i++) {
    const d = new Date(minDatum); d.setDate(d.getDate() + i * zoom);
    const iso = d.toISOString().slice(0, 10);
    const endD = new Date(d.getTime() + (zoom - 1) * 86400000);
    const istHeuteSpalte = iso <= heute && heute <= endD.toISOString().slice(0, 10);
    const zeigeLabel = i % labelIntervall === 0 || d.getDate() === 1;
    const label = zeigeLabel ? `${d.getDate()}.${d.getMonth()+1}.` : '';
    kopfHtml += `<div class="gantt-tag ${istHeuteSpalte ? 'gantt-heute' : ''}">${label}</div>`;
  }
  kopfzeile.innerHTML = kopfHtml; gantt.appendChild(kopfzeile);

  const tagZuSpalte = (iso) => Math.floor(Math.round((new Date(iso) - minDatum) / 86400000) / zoom);
  let letztePhase = null;

  for (const schritt of liste) {
    if (schritt.phase !== letztePhase) {
      letztePhase = schritt.phase;
      const nr = phasenReihenfolge.indexOf(schritt.phase) + 1;
      const pZeile = document.createElement('div'); pZeile.className = 'gantt-phase-zeile';
      let rHtml = '';
      for (let i = 0; i < spanSpalten; i++) {
        const d = new Date(minDatum); d.setDate(d.getDate() + i * zoom);
        const endD = new Date(d.getTime() + (zoom - 1) * 86400000);
        const ih = d.toISOString().slice(0,10) <= heute && heute <= endD.toISOString().slice(0,10);
        rHtml += `<div class="gantt-zelle${ih ? ' gantt-heute-spalte' : ''}"></div>`;
      }
      pZeile.innerHTML = `<div class="gantt-label-zelle gantt-phase-label" style="color:${schritt.phase_farbe};">${nr}. ${schritt.phase.replace(/^\d+\.\s*/, '')}</div><div class="gantt-raster" style="grid-template-columns:repeat(${spanSpalten},1fr);">${rHtml}</div>`;
      gantt.appendChild(pZeile);
    }
    if (!schritt.geplantes_datum) continue;
    const startSpalte = schritt.start_datum ? tagZuSpalte(schritt.start_datum) : tagZuSpalte(schritt.geplantes_datum);
    const endeSpalte = tagZuSpalte(schritt.geplantes_datum);
    const hatBalken = startSpalte < endeSpalte;
    const zeile = document.createElement('div'); zeile.className = 'gantt-zeile';
    const statusKlasse = schritt.erledigt ? 'gantt-erledigt' : schritt.geplantes_datum < heute ? 'gantt-ueberfaellig' : '';
    const meta = eingeloggt && schritt.verantwortlich_anzeigename ? ` · ${schritt.verantwortlich_anzeigename}` : '';
    let rHtml = '';
    for (let i = 0; i < spanSpalten; i++) {
      const d = new Date(minDatum); d.setDate(d.getDate() + i * zoom);
      const endD = new Date(d.getTime() + (zoom - 1) * 86400000);
      const ih = d.toISOString().slice(0,10) <= heute && heute <= endD.toISOString().slice(0,10);
      let inhalt = '';
      if (hatBalken) {
        if (i === startSpalte) inhalt = `<div class="gantt-balken gantt-balken-start ${statusKlasse}" style="background:${schritt.phase_farbe};" title="${schritt.titel}${meta}"></div>`;
        else if (i > startSpalte && i < endeSpalte) inhalt = `<div class="gantt-balken gantt-balken-mitte ${statusKlasse}" style="background:${schritt.phase_farbe};"></div>`;
        else if (i === endeSpalte) inhalt = `<div class="gantt-balken gantt-balken-ende ${statusKlasse}" style="background:${schritt.phase_farbe};"></div>`;
      } else {
        if (i === endeSpalte) inhalt = `<div class="gantt-balken gantt-punkt ${statusKlasse}" style="background:${schritt.phase_farbe};" title="${schritt.titel}${meta}"></div>`;
      }
      rHtml += `<div class="gantt-zelle${ih ? ' gantt-heute-spalte' : ''}">${inhalt}</div>`;
    }
    zeile.innerHTML = `<div class="gantt-label-zelle gantt-schritt-label ${schritt.erledigt ? 'erledigt' : ''}">${schritt.erledigt ? '✓ ' : ''}${schritt.titel}${meta}</div><div class="gantt-raster" style="grid-template-columns:repeat(${spanSpalten},1fr);">${rHtml}</div>`;
    gantt.appendChild(zeile);
  }
  wrapper.appendChild(gantt);
  if (ohneDatum.length) wrapper.appendChild(renderOhneDatumListe(ohneDatum));
  return wrapper;
}

function renderTimeline(liste, eingeloggt) {
  const mitDatum = [...liste.filter((s) => s.geplantes_datum)].sort((a, b) => a.geplantes_datum.localeCompare(b.geplantes_datum));
  const ohneDatum = liste.filter((s) => !s.geplantes_datum);
  const heute = heuteISO();
  const wrapper = document.createElement('div');
  const tl = document.createElement('div'); tl.className = 'timeline';
  let letztesDatum = null;
  for (const schritt of mitDatum) {
    if (schritt.geplantes_datum !== letztesDatum) {
      letztesDatum = schritt.geplantes_datum;
      const istHeute = schritt.geplantes_datum === heute, istVerg = schritt.geplantes_datum < heute;
      const trenn = document.createElement('div'); trenn.className = 'tl-datum-zeile';
      trenn.innerHTML = `<div class="tl-datum-linie"></div><div class="tl-datum-label ${istHeute ? 'tl-heute' : istVerg ? 'tl-vergangenheit' : ''}">${istHeute ? '📍 Heute · ' : ''}${formatDatum(schritt.geplantes_datum)}</div><div class="tl-datum-linie"></div>`;
      tl.appendChild(trenn);
    }
    const el = document.createElement('div');
    el.className = `tl-eintrag ${schritt.erledigt ? 'tl-erledigt' : schritt.geplantes_datum < heute ? 'tl-ueberfaellig' : ''}`;
    el.style.setProperty('--accent', schritt.phase_farbe);
    const phasenNr = schritt.phase.match(/^\d+\./)?.[0] ?? '';
    const meta = eingeloggt && schritt.verantwortlich_anzeigename ? `<span class="tl-meta">${schritt.verantwortlich_anzeigename}</span>` : '';
    const zeitraum = schritt.start_datum ? `<span class="tl-meta">ab ${formatDatum(schritt.start_datum)}</span>` : '';
    el.innerHTML = `<div class="tl-punkt"></div><div class="tl-inhalt"><span class="tl-phase" style="color:${schritt.phase_farbe};">${phasenNr}</span><span class="tl-titel ${schritt.erledigt ? 'erledigt' : ''}">${schritt.titel}</span>${zeitraum}${meta}</div>`;
    tl.appendChild(el);
  }
  wrapper.appendChild(tl);
  if (ohneDatum.length) wrapper.appendChild(renderOhneDatumListe(ohneDatum));
  return wrapper;
}

function renderOhneDatumListe(liste) {
  const block = document.createElement('div');
  block.innerHTML = `<p class="dash-label" style="margin-top:20px;">Ohne Datum (${liste.length})</p><ul style="font-size:12.5px;color:var(--muted);padding-left:16px;margin:4px 0;">${liste.map((s) => `<li>${s.titel}</li>`).join('')}</ul>`;
  return block;
}

function xmlEsc(str) {
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function exportiereGanttAlsSvg() {
  const liste = STATE.user ? STATE.schritte : (STATE.publicDashboard?.[0]?.schritte ?? []);
  const mitDatum = liste.filter((s) => s.geplantes_datum);
  if (mitDatum.length === 0) { alert('Keine Daten mit Datumsangaben vorhanden.'); return; }
  const daten = mitDatum.flatMap((s) => s.start_datum ? [s.start_datum, s.geplantes_datum] : [s.geplantes_datum]).sort();
  const minDatum = new Date(daten[0]), maxDatum = new Date(daten[daten.length - 1]);
  const zoom = STATE.ganttZoom, spanTage = Math.max(7, Math.ceil((maxDatum - minDatum) / 86400000) + 2);
  const spanSpalten = Math.ceil(spanTage / zoom);
  const LABELBREITE = 240, SPALTENBREITE = 30, ZEILENHOEHE = 26, KOPFHOEHE = 32;
  const phasen = []; const gesehene = new Set();
  for (const s of liste) { if (!gesehene.has(s.phase)) { phasen.push(s.phase); gesehene.add(s.phase); } }
  const rasterBreite = spanSpalten * SPALTENBREITE;
  const breite = LABELBREITE + rasterBreite + 32;
  const hoehe = KOPFHOEHE + (phasen.length + liste.length) * ZEILENHOEHE + 20;
  const tagZuSpalte = (iso) => Math.floor(Math.round((new Date(iso) - minDatum) / 86400000) / zoom);
  const heute = heuteISO();
  let svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${breite}" height="${hoehe}" font-family="Arial, Helvetica, sans-serif" font-size="11">`;
  svg += `<rect width="${breite}" height="${hoehe}" fill="#F5F2ED"/>`;
  svg += `<defs><clipPath id="rc"><rect x="${LABELBREITE}" y="0" width="${rasterBreite}" height="${hoehe}"/></clipPath></defs>`;
  for (let i = 0; i <= spanSpalten; i++) {
    const x = LABELBREITE + i * SPALTENBREITE;
    svg += `<line x1="${x}" y1="${KOPFHOEHE}" x2="${x}" y2="${hoehe}" stroke="#ddd" stroke-width="0.5"/>`;
    if (i < spanSpalten) {
      const d = new Date(minDatum); d.setDate(d.getDate() + i * zoom);
      const iso = d.toISOString().slice(0, 10), endD = new Date(d.getTime() + (zoom - 1) * 86400000);
      const ih = iso <= heute && heute <= endD.toISOString().slice(0, 10);
      if (ih) svg += `<rect x="${x}" y="0" width="${SPALTENBREITE}" height="${hoehe}" fill="rgba(91,111,168,0.08)"/>`;
      svg += `<text x="${x+2}" y="${KOPFHOEHE-6}" fill="${ih ? '#5B6FA8' : '#888'}" font-size="9" font-weight="${ih ? 'bold' : 'normal'}">${xmlEsc(`${d.getDate()}.${d.getMonth()+1}.`)}</text>`;
    }
  }
  svg += `<line x1="0" y1="${KOPFHOEHE}" x2="${breite}" y2="${KOPFHOEHE}" stroke="#ccc" stroke-width="1"/>`;
  let zeilenY = KOPFHOEHE, letztePhase = null, phaseNr = 0;
  for (const schritt of liste) {
    if (schritt.phase !== letztePhase) {
      letztePhase = schritt.phase; phaseNr++;
      svg += `<rect x="0" y="${zeilenY}" width="${breite}" height="${ZEILENHOEHE}" fill="${schritt.phase_farbe}28"/>`;
      svg += `<text x="8" y="${zeilenY+ZEILENHOEHE-7}" fill="${schritt.phase_farbe}" font-weight="bold" font-size="11">${xmlEsc(phaseNr + '. ' + schritt.phase.replace(/^\d+\.\s*/, ''))}</text>`;
      zeilenY += ZEILENHOEHE;
    }
    svg += `<rect x="0" y="${zeilenY}" width="${breite}" height="${ZEILENHOEHE}" fill="${zeilenY%(ZEILENHOEHE*2)===0?'#fff':'#F9F8F5'}"/>`;
    const tt = schritt.titel.length > 32 ? schritt.titel.slice(0,31)+'…' : schritt.titel;
    svg += `<text x="10" y="${zeilenY+ZEILENHOEHE-7}" fill="${schritt.erledigt?'#aaa':'#333'}" text-decoration="${schritt.erledigt?'line-through':'none'}" font-size="10">${xmlEsc(tt)}</text>`;
    if (schritt.geplantes_datum) {
      const s0 = Math.max(0, Math.min(tagZuSpalte(schritt.start_datum ?? schritt.geplantes_datum), spanSpalten-1));
      const s1 = Math.max(0, Math.min(tagZuSpalte(schritt.geplantes_datum), spanSpalten-1));
      const x1 = LABELBREITE + s0*SPALTENBREITE+2, x2 = LABELBREITE + s1*SPALTENBREITE+SPALTENBREITE-2;
      const y = zeilenY+7, h = 12;
      const farbe = schritt.erledigt ? '#ccc' : (schritt.geplantes_datum < heute ? '#c0392b' : schritt.phase_farbe);
      svg += `<g clip-path="url(#rc)">`;
      if (s0 < s1) svg += `<rect x="${x1}" y="${y}" width="${x2-x1}" height="${h}" rx="4" fill="${farbe}" opacity="0.85"/>`;
      else svg += `<circle cx="${LABELBREITE+s0*SPALTENBREITE+SPALTENBREITE/2}" cy="${y+h/2}" r="6" fill="${farbe}"/>`;
      svg += `</g>`;
    }
    zeilenY += ZEILENHOEHE;
  }
  svg += '</svg>';
  const blob = new Blob([svg], { type: 'image/svg+xml;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a'); a.href = url; a.download = 'schulprozess_zeitstrahl.svg'; a.click();
  URL.revokeObjectURL(url);
}

// ============================================================================
// Prozess-Verwaltung (Verantwortliche + Admins)
// ============================================================================
function renderProzessVerwaltungInhalt() {
  const container = document.createElement('div');
  container.className = 'admin-section';

  // Öffentlich/Privat
  const oeffentlich = STATE.aktiverProzess?.oeffentlich ?? 1;
  const sichtbarBlock = document.createElement('div');
  sichtbarBlock.innerHTML = `
    <h3 style="font-size:13px;color:var(--muted);">Sichtbarkeit</h3>
    <label class="toggle-wrap" style="font-size:13px;gap:10px;">
      <input type="checkbox" id="toggle-oeffentlich" ${oeffentlich ? 'checked' : ''}>
      <span>${oeffentlich ? '🌐 Öffentlich (im Dashboard sichtbar)' : '🔒 Privat (nur für Teilnehmer)'}</span>
    </label>`;
  sichtbarBlock.querySelector('#toggle-oeffentlich').addEventListener('change', (e) => {
    prozessAktualisieren(STATE.prozessId, { oeffentlich: e.target.checked });
  });
  container.appendChild(sichtbarBlock);

  // Teilnehmer
  const teilnehmerBlock = document.createElement('div');
  teilnehmerBlock.innerHTML = `
    <h3 class="">Teilnehmer</h3>
    <table class="admin-tabelle">
      <thead><tr><th>Kürzel</th><th>Name</th><th>Rolle</th><th></th></tr></thead>
      <tbody>
        ${STATE.teilnehmer.map((t) => `
          <tr>
            <td>${t.webuntis_user}</td>
            <td>${t.anzeigename ?? t.webuntis_user}</td>
            <td>
              <select data-tn-user="${t.webuntis_user}">
                <option value="verantwortlich" ${t.rolle === 'verantwortlich' ? 'selected' : ''}>verantwortlich</option>
                <option value="mitarbeitend" ${t.rolle === 'mitarbeitend' ? 'selected' : ''}>mitarbeitend</option>
              </select>
            </td>
            <td>
              <button class="btn-sekundaer btn" data-tn-remove="${t.webuntis_user}"
                style="width:auto;color:#c0392b;border-color:#c0392b;">entfernen</button>
            </td>
          </tr>`).join('')}
      </tbody>
    </table>
    <form class="inline-form" id="neuer-tn-form" style="margin-top:10px;">
      <div class="feld"><label>Kürzel</label><input type="text" id="tn-user" required></div>
      <div class="feld"><label>Rolle</label>
        <select id="tn-rolle">
          <option value="mitarbeitend">mitarbeitend</option>
          <option value="verantwortlich">verantwortlich</option>
        </select>
      </div>
      <button class="btn" type="submit" style="width:auto;">Hinzufügen</button>
    </form>
    <p style="font-size:11px;color:var(--muted);">Nur Personen die bereits unter "Zugriff" freigegeben sind können hinzugefügt werden.</p>`;

  teilnehmerBlock.querySelectorAll('select[data-tn-user]').forEach((sel) => {
    sel.addEventListener('change', () => teilnehmerHinzufuegen(STATE.prozessId, sel.dataset.tnUser, sel.value));
  });
  teilnehmerBlock.querySelectorAll('[data-tn-remove]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (confirm(`${btn.dataset.tnRemove} aus dem Prozess entfernen?`)) {
        try { await teilnehmerEntfernen(STATE.prozessId, btn.dataset.tnRemove); }
        catch (err) { alert(err.message); }
      }
    });
  });
  teilnehmerBlock.querySelector('#neuer-tn-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const user = teilnehmerBlock.querySelector('#tn-user').value.trim();
    const rolle = teilnehmerBlock.querySelector('#tn-rolle').value;
    if (user) {
      try { await teilnehmerHinzufuegen(STATE.prozessId, user, rolle); }
      catch (err) { alert(err.message); }
    }
  });
  container.appendChild(teilnehmerBlock);

  return container;
}

// ============================================================================
// Admin-Inhaltsblöcke (genutzt von renderAdminSeite)
// ============================================================================
function renderAdminBereich() {
  // Rückwärtskompatibilität – wird nicht mehr direkt aufgerufen
  const container = document.createElement('div');
  container.appendChild(renderProzesseBlock());
  container.appendChild(renderZugriffBlock());
  container.appendChild(renderVorlagenVerwaltung());
  container.appendChild(renderAktivitaetsprotokoll());
  return container;
}

function renderProzesseBlock() {
  const block = document.createElement('div');
  block.innerHTML = `
    <h3 style="font-size:13px;color:var(--muted);">Prozesse</h3>
    <table class="admin-tabelle">
      <thead><tr><th>Name</th><th>Öffentlich</th><th>Schritte</th><th>Teilnehmer</th></tr></thead>
      <tbody>
        ${STATE.prozesse.map((p) => `
          <tr>
            <td>${p.label}${p.beschreibung ? `<span style="font-size:11px;color:var(--muted);margin-left:6px;">${p.beschreibung}</span>` : ''}</td>
            <td>${p.oeffentlich ? '🌐 öffentlich' : '🔒 privat'}</td>
            <td>${p.erledigt_anzahl ?? 0}/${p.schritt_anzahl ?? 0}</td>
            <td>${p.teilnehmer_anzahl ?? 0}</td>
          </tr>`).join('')}
      </tbody>
    </table>
    <h3 class="">Neuen Prozess anlegen</h3>
    <form class="inline-form" id="neuer-prozess-form">
      <div class="feld" style="flex:1;"><label>Name</label>
        <input type="text" id="prozess-label" placeholder="z. B. Abitur 2027" required style="width:100%;"></div>
      <div class="feld" style="flex:1;"><label>Beschreibung (optional)</label>
        <input type="text" id="prozess-beschreibung" style="width:100%;"></div>
      <div class="feld"><label>Basis</label>
        <select id="prozess-set">
          <option value="">Aktuelle Vorlage (WebUntis-Wechsel)</option>
          <option value="leer">⬜ Leer starten (keine Schritte)</option>
          ${STATE.vorlagenSets.map((s) => `<option value="${s.id}">${s.name}</option>`).join('')}
        </select>
      </div>
      <div class="feld"><label>Sichtbarkeit</label>
        <select id="prozess-oeffentlich">
          <option value="1">🌐 Öffentlich</option>
          <option value="0">🔒 Privat</option>
        </select>
      </div>
      <button class="btn" type="submit" style="width:auto;">Anlegen</button>
    </form>

    <h3 class="">Gespeicherte Vorlagen (Snapshots)</h3>
    <div>
      ${STATE.vorlagenSets.length === 0
        ? '<p style="font-size:12px;color:var(--muted);">Noch keine Snapshots.</p>'
        : STATE.vorlagenSets.map((s) => `
          <div class="vorlagen-set-zeile">
            <div><strong>${s.name}</strong>
              ${s.beschreibung ? `<span style="font-size:11px;color:var(--muted);margin-left:6px;">${s.beschreibung}</span>` : ''}
              <span style="font-size:11px;color:var(--muted);margin-left:6px;">· ${s.schritt_anzahl} Schritte · ${s.erstellt_von} · ${s.erstellt_am?.slice(0,10)}</span>
            </div>
            <button class="btn-sekundaer btn btn-loeschen" data-loeschen-set="${s.id}" style="width:auto;color:#c0392b;border-color:#c0392b;">löschen</button>
          </div>`).join('')}
    </div>
    <form class="inline-form" id="neuer-snapshot-form" style="margin-top:10px;">
      <div class="feld" style="flex:1;"><label>Snapshot-Name</label>
        <input type="text" id="snapshot-name" placeholder="z. B. Abitur-Prozess 2026" required style="width:100%;"></div>
      <div class="feld" style="flex:1;"><label>Beschreibung (optional)</label>
        <input type="text" id="snapshot-beschreibung" style="width:100%;"></div>
      <button class="btn" type="submit" style="width:auto;">Jetzt einfrieren</button>
    </form>`;

  block.querySelector('#neuer-prozess-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const label = block.querySelector('#prozess-label').value.trim();
    const beschreibung = block.querySelector('#prozess-beschreibung').value.trim() || null;
    const setId = block.querySelector('#prozess-set').value;
    const setIdWert = setId === '' ? null : setId === 'leer' ? 'leer' : Number(setId);
    const oeffentlich = Number(block.querySelector('#prozess-oeffentlich').value);
    if (label) neuerProzess(label, beschreibung, oeffentlich, setIdWert);
  });
  block.querySelector('#neuer-snapshot-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const name = block.querySelector('#snapshot-name').value.trim();
    const beschreibung = block.querySelector('#snapshot-beschreibung').value.trim() || null;
    if (name) speichereVorlagenSet(name, beschreibung);
  });
  block.querySelectorAll('[data-loeschen-set]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const s = STATE.vorlagenSets.find((x) => x.id === Number(btn.dataset.loeschenSet));
      if (confirm(`Snapshot „${s?.name}" wirklich löschen?`)) {
        try { await loescheVorlagenSet(Number(btn.dataset.loeschenSet)); } catch (err) { alert(err.message); }
      }
    });
  });
  return block;
}

function renderZugriffBlock() {
  const rollenBlock = document.createElement('div');
  rollenBlock.innerHTML = `
    <h3 class="">Zugriff (App-Freigaben)</h3>
    <table class="admin-tabelle">
      <thead><tr><th>Kürzel</th><th>Name</th><th>Rolle</th><th></th></tr></thead>
      <tbody>
        ${STATE.rollen.map((r) => `
          <tr>
            <td>${r.webuntis_user}</td><td>${r.anzeigename}</td>
            <td><select data-rolle-user="${r.webuntis_user}" data-rolle-name="${r.anzeigename}">
              <option value="mitglied" ${r.rolle === 'mitglied' ? 'selected' : ''}>mitglied</option>
              <option value="admin" ${r.rolle === 'admin' ? 'selected' : ''}>admin</option>
            </select></td>
            <td><button class="btn-sekundaer btn btn-loeschen" data-loeschen="${r.webuntis_user}"
              style="width:auto;color:#c0392b;border-color:#c0392b;" ${r.webuntis_user === STATE.user?.webuntis_user ? 'disabled' : ''}>entfernen</button></td>
          </tr>`).join('')}
      </tbody>
    </table>
    <form class="inline-form" id="neue-person-form" style="margin-top:10px;">
      <div class="feld"><label>Kürzel</label><input type="text" id="neue-person-user" required></div>
      <div class="feld"><label>Name</label><input type="text" id="neue-person-name"></div>
      <div class="feld"><label>Rolle</label>
        <select id="neue-person-rolle"><option value="mitglied">mitglied</option><option value="admin">admin</option></select>
      </div>
      <button class="btn" type="submit" style="width:auto;">Freigeben</button>
    </form>
    <p style="font-size:11px;color:var(--muted);">Nur freigegebene Personen können sich anmelden. Danach in „Prozess verwalten" als Teilnehmer hinzufügen.</p>`;

  rollenBlock.querySelectorAll('select[data-rolle-user]').forEach((sel) => {
    sel.addEventListener('change', () => setzeRolle(sel.dataset.rolleUser, sel.value, sel.dataset.rolleName));
  });
  rollenBlock.querySelectorAll('[data-loeschen]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (confirm(`${btn.dataset.loeschen} entfernen?`)) {
        try { await loescheRolle(btn.dataset.loeschen); } catch (err) { alert(err.message); }
      }
    });
  });
  rollenBlock.querySelector('#neue-person-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const user = rollenBlock.querySelector('#neue-person-user').value.trim();
    const name = rollenBlock.querySelector('#neue-person-name').value.trim();
    const rolle = rollenBlock.querySelector('#neue-person-rolle').value;
    if (user) setzeRolle(user, rolle, name || user);
  });
  return rollenBlock;
}

// Vorlagen-Verwaltung (Phasen + Schritte – gleiche Logik wie alte App)
function renderVorlagenVerwaltung() {
  const block = document.createElement('div');
  block.innerHTML = '<h3 class="">Vorlage verwalten (Phasen & Schritte)</h3>';

  const phasenListe = document.createElement('div'); phasenListe.className = 'phasen-liste';
  for (const phase of STATE.phasen) {
    const vorlagenDerPhase = STATE.vorlagen.filter((v) => v.phase_id === phase.id).sort((a, b) => a.reihenfolge - b.reihenfolge);
    phasenListe.appendChild(renderPhasenBlock(phase, vorlagenDerPhase));
  }
  phasenListe.addEventListener('dragover', (e) => { if (dragZustandPhase) e.preventDefault(); });
  phasenListe.addEventListener('drop', (e) => {
    if (!dragZustandPhase) return; e.preventDefault();
    const zielEl = e.target.closest('[data-phasen-block-id]');
    const alleIds = STATE.phasen.map((p) => p.id);
    const ohne = alleIds.filter((id) => id !== dragZustandPhase.id);
    const zielId = zielEl ? Number(zielEl.dataset.phasenBlockId) : null;
    const zi = zielId ? ohne.indexOf(zielId) : -1;
    const neu = zi === -1 ? [...ohne, dragZustandPhase.id] : [...ohne.slice(0, zi), dragZustandPhase.id, ...ohne.slice(zi)];
    reihenfolgePhasenAendern(neu);
  });
  block.appendChild(phasenListe);

  const neuePhaseForm = document.createElement('div'); neuePhaseForm.className = 'neue-phase-form'; neuePhaseForm.style.marginTop = '14px';
  const nameInput = document.createElement('input'); nameInput.type = 'text'; nameInput.placeholder = 'Neue Phase...'; nameInput.style.cssText = 'flex:1;font-size:13px;padding:6px 8px;border:1px solid var(--line);border-radius:6px;';
  let neuerPhaseFarbe = '#5B6FA8';
  const farbwahlNeu = renderFarbwahl(neuerPhaseFarbe, (f) => { neuerPhaseFarbe = f; });
  const btnNeu = document.createElement('button'); btnNeu.className = 'btn'; btnNeu.style.cssText = 'width:auto;margin-top:8px;'; btnNeu.textContent = 'Phase anlegen';
  btnNeu.addEventListener('click', () => { if (nameInput.value.trim()) neuePhase(nameInput.value.trim(), neuerPhaseFarbe); });
  const reihe = document.createElement('div'); reihe.style.cssText = 'display:flex;gap:8px;align-items:center;';
  reihe.appendChild(nameInput); neuePhaseForm.appendChild(reihe); neuePhaseForm.appendChild(farbwahlNeu); neuePhaseForm.appendChild(btnNeu);
  block.appendChild(neuePhaseForm);
  return block;
}

function renderPhasenBlock(phase, vorlagen) {
  const wrapper = document.createElement('div');
  wrapper.className = 'phasen-block'; wrapper.dataset.phasenBlockId = phase.id; wrapper.draggable = true;

  const kopf = document.createElement('div'); kopf.className = 'phasen-kopf'; kopf.style.setProperty('--phase-farbe', phase.farbe);
  const griff = document.createElement('span'); griff.className = 'zieh-griff phasen-griff'; griff.title = 'Phase verschieben'; griff.textContent = '⠿';
  const nummer = STATE.phasen.findIndex((p) => p.id === phase.id) + 1;
  const nameOhneNr = phase.name.replace(/^\d+\.\s*/, '');
  const nummerSpan = document.createElement('span'); nummerSpan.className = 'phasen-nummer'; nummerSpan.style.cssText = 'color:var(--phase-farbe);font-weight:700;font-size:14px;flex-shrink:0;'; nummerSpan.textContent = nummer + '.';
  const nameFeld = document.createElement('input'); nameFeld.type = 'text'; nameFeld.className = 'phasen-name-feld'; nameFeld.value = nameOhneNr; nameFeld.placeholder = 'Phasenname';
  nameFeld.addEventListener('change', (e) => phaseAktualisieren(phase.id, { name: e.target.value.replace(/^\d+\.\s*/, '') }));

  const farbBtn = document.createElement('button'); farbBtn.type = 'button'; farbBtn.className = 'phasen-farb-btn';
  farbBtn.style.cssText = `background:${phase.farbe};width:22px;height:22px;border-radius:4px;border:2px solid rgba(0,0,0,.15);cursor:pointer;flex-shrink:0;`;
  const farbPopup = document.createElement('div'); farbPopup.className = 'farb-popup'; farbPopup.style.display = 'none';
  farbPopup.appendChild(renderFarbwahl(phase.farbe, (f) => { farbBtn.style.background = f; kopf.style.setProperty('--phase-farbe', f); phaseAktualisieren(phase.id, { farbe: f }); }));
  farbBtn.addEventListener('click', (e) => { e.stopPropagation(); farbPopup.style.display = farbPopup.style.display === 'none' ? 'block' : 'none'; });
  document.addEventListener('click', () => { farbPopup.style.display = 'none'; });
  const farbWrap = document.createElement('div'); farbWrap.style.position = 'relative'; farbWrap.appendChild(farbBtn); farbWrap.appendChild(farbPopup);

  kopf.appendChild(griff); kopf.appendChild(farbWrap); kopf.appendChild(nummerSpan); kopf.appendChild(nameFeld);
  wrapper.addEventListener('dragstart', (e) => { if (e.target.closest('.vorlagen-zeile-wrapper')) return; dragZustandPhase = { id: phase.id }; wrapper.classList.add('wird-gezogen'); e.dataTransfer.effectAllowed = 'move'; });
  wrapper.addEventListener('dragend', () => { wrapper.classList.remove('wird-gezogen'); dragZustandPhase = null; });
  wrapper.appendChild(kopf);

  const liste = document.createElement('div'); liste.className = 'vorlagen-liste';
  for (const v of vorlagen) liste.appendChild(renderVorlagenZeile(v));
  liste.addEventListener('dragover', (e) => { if (dragZustand && dragZustand.phase_id === phase.id) e.preventDefault(); });
  liste.addEventListener('drop', (e) => {
    if (!dragZustand || dragZustand.phase_id !== phase.id) return; e.preventDefault();
    const zielEl = e.target.closest('[data-vorlage-id]');
    const aktIds = vorlagen.map((v) => v.id);
    const ohne = aktIds.filter((id) => id !== dragZustand.id);
    const zielId = zielEl ? Number(zielEl.dataset.vorlageId) : null;
    const zi = zielId ? ohne.indexOf(zielId) : -1;
    const neu = zi === -1 ? [...ohne, dragZustand.id] : [...ohne.slice(0, zi), dragZustand.id, ...ohne.slice(zi)];
    reihenfolgeAendern(phase.id, neu);
  });
  wrapper.appendChild(liste);

  const neuerSchrittForm = document.createElement('form'); neuerSchrittForm.className = 'inline-form'; neuerSchrittForm.style.cssText = 'margin:6px 8px 10px;';
  neuerSchrittForm.innerHTML = `<input type="text" class="neuer-schritt-titel" placeholder="Neuer Schritt..." style="flex:1;"><button class="btn" type="submit" style="width:auto;">+</button>`;
  neuerSchrittForm.addEventListener('submit', (e) => { e.preventDefault(); const t = neuerSchrittForm.querySelector('.neuer-schritt-titel').value.trim(); if (t) neueVorlage(phase.id, t); });
  wrapper.appendChild(neuerSchrittForm);
  return wrapper;
}

function renderVorlagenZeile(v) {
  const wrapper = document.createElement('div');
  wrapper.className = 'vorlagen-zeile-wrapper' + (v.aktiv ? '' : ' inaktiv');
  wrapper.draggable = true; wrapper.dataset.vorlageId = v.id;

  wrapper.innerHTML = `
    <div class="vorlagen-zeile">
      <span class="zieh-griff" title="Ziehen zum Umsortieren">⠿</span>
      <input type="text" class="vorlagen-titel-feld" value="${v.titel}" data-feld="titel">
      <select class="vorlagen-phase-feld" data-feld="phase_id">
        ${STATE.phasen.map((p) => `<option value="${p.id}" ${p.id === v.phase_id ? 'selected' : ''}>${p.name}</option>`).join('')}
      </select>
      <label class="toggle-wrap" title="Default parallel" style="margin-left:4px;">
        <input type="checkbox" data-toggle-parallel ${v.kann_parallel ? 'checked' : ''}>
        <span class="toggle-label">⇉ Default</span>
      </label>
      <button class="btn-sekundaer btn" data-toggle-aktiv style="width:auto;">${v.aktiv ? 'deaktivieren' : 'reaktivieren'}</button>
      <span class="chev" data-rolle="vorlagen-chevron">▸</span>
    </div>
    <div class="schritt-detail" data-rolle="vorlagen-detail" style="padding:0 14px 14px 26px;">
      <label style="font-size:10.5px;color:var(--muted);display:block;margin-bottom:4px;font-family:'IBM Plex Mono',monospace;text-transform:uppercase;letter-spacing:.04em;">
        Weiterführende Infos (nur für Angemeldete)
      </label>
      <div class="md-toolbar">
        <button type="button" data-md="fett"><strong>F</strong></button>
        <button type="button" data-md="kursiv"><em>K</em></button>
        <button type="button" data-md="liste">• Liste</button>
        <button type="button" data-md="nummeriert">1. Liste</button>
        <button type="button" data-md="link">🔗 Link</button>
      </div>
      <textarea class="vorlagen-beschreibung-feld" data-feld="beschreibung" rows="3" placeholder="Hinweise, Links ...">${v.beschreibung ?? ''}</textarea>
      <div class="vorlagen-vorschau" data-rolle="vorlagen-vorschau">${markdownZuHtml(v.beschreibung) || '<span style="color:var(--muted);">Vorschau</span>'}</div>
    </div>`;

  const ta = wrapper.querySelector('[data-feld="beschreibung"]');
  const vorschau = wrapper.querySelector('[data-rolle="vorlagen-vorschau"]');
  ta.addEventListener('input', () => { vorschau.innerHTML = markdownZuHtml(ta.value) || '<span style="color:var(--muted);">Vorschau</span>'; });

  wrapper.querySelectorAll('[data-md]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault(); const art = btn.dataset.md;
      if (art === 'fett') textareaFormatierungEinfuegen(ta, { umschliessen: '**' });
      else if (art === 'kursiv') textareaFormatierungEinfuegen(ta, { umschliessen: '*' });
      else if (art === 'liste') textareaFormatierungEinfuegen(ta, { zeilenPraefix: '- ' });
      else if (art === 'nummeriert') textareaFormatierungEinfuegen(ta, { zeilenPraefix: '1. ' });
      else if (art === 'link') {
        const s = ta.selectionStart, e2 = ta.selectionEnd, a = ta.value.slice(s, e2) || 'Linktext';
        ta.value = ta.value.slice(0, s) + `[${a}](https://)` + ta.value.slice(e2);
        ta.focus(); ta.setSelectionRange(s+a.length+3, s+a.length+11); ta.dispatchEvent(new Event('input'));
      }
    });
  });

  const vorlagenDetailEl = wrapper.querySelector('[data-rolle="vorlagen-detail"]');
  const vorlagenChevronEl = wrapper.querySelector('[data-rolle="vorlagen-chevron"]');
  if (STATE.offeneVorlagen.has(v.id)) { vorlagenDetailEl.classList.add('offen'); vorlagenChevronEl.classList.add('offen'); }
  vorlagenChevronEl.addEventListener('click', () => {
    const o = vorlagenDetailEl.classList.toggle('offen'); vorlagenChevronEl.classList.toggle('offen');
    if (o) STATE.offeneVorlagen.add(v.id); else STATE.offeneVorlagen.delete(v.id);
  });

  wrapper.addEventListener('dragstart', (e) => { dragZustand = { id: v.id, phase_id: v.phase_id }; wrapper.classList.add('wird-gezogen'); e.stopPropagation(); });
  wrapper.addEventListener('dragend', () => { wrapper.classList.remove('wird-gezogen'); dragZustand = null; });
  wrapper.querySelector('[data-feld="titel"]').addEventListener('change', (e) => vorlageAktualisieren(v.id, { titel: e.target.value }));
  wrapper.querySelector('[data-feld="phase_id"]').addEventListener('change', (e) => vorlageAktualisieren(v.id, { phase_id: Number(e.target.value) }));
  wrapper.querySelector('[data-feld="beschreibung"]').addEventListener('change', (e) => vorlageAktualisieren(v.id, { beschreibung: e.target.value }));
  wrapper.querySelector('[data-toggle-aktiv]').addEventListener('click', () => vorlageAktualisieren(v.id, { aktiv: !v.aktiv }));
  wrapper.querySelector('[data-toggle-parallel]').addEventListener('change', (e) => vorlageAktualisieren(v.id, { kann_parallel: e.target.checked }));
  return wrapper;
}

async function ladeAktivitaeten() {
  try { return await api(`/api/aktivitaeten?prozess_id=${STATE.prozessId}`); } catch { return []; }
}

function renderAktivitaetsprotokoll() {
  const block = document.createElement('div');
  block.innerHTML = `<h3 class="">Aktivitätsprotokoll</h3><div id="aktivitaeten-liste" style="font-size:12px;">Lädt…</div>`;
  const exportBtn = document.createElement('button'); exportBtn.className = 'btn-sekundaer btn kein-druck'; exportBtn.style.cssText = 'width:auto;margin-top:8px;font-size:11px;'; exportBtn.textContent = '⬇ Als CSV exportieren';
  exportBtn.addEventListener('click', () => { window.location.href = `/api/export/aktivitaeten?prozess_id=${STATE.prozessId}`; });
  block.appendChild(exportBtn);
  const ereignisTexte = { schritt_erledigt:'✓ erledigt', schritt_rueckgaengig:'↩ rückgängig', verantwortlich_gesetzt:'👤 Verantwortlich', datum_gesetzt:'📅 Zieldatum', startdatum_gesetzt:'📅 Startdatum', kommentar_gesetzt:'💬 Kommentar' };
  ladeAktivitaeten().then((eintraege) => {
    const liste = block.querySelector('#aktivitaeten-liste');
    if (eintraege.length === 0) { liste.textContent = 'Noch keine Aktivitäten.'; return; }
    const tabelle = document.createElement('table'); tabelle.className = 'admin-tabelle';
    tabelle.innerHTML = `<thead><tr><th>Wann</th><th>Wer</th><th>Schritt</th><th>Aktion</th></tr></thead>`;
    const tbody = document.createElement('tbody');
    for (const e of eintraege) {
      const tr = document.createElement('tr');
      const wann = new Date(e.zeitstempel).toLocaleString('de-DE', { day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' });
      tr.innerHTML = `<td>${wann}</td><td>${e.anzeigename}</td><td>${e.schritt_titel}</td><td>${(ereignisTexte[e.ereignis]??e.ereignis)+(e.wert_neu?': '+e.wert_neu:'')}</td>`;
      tbody.appendChild(tr);
    }
    tabelle.appendChild(tbody); liste.innerHTML = ''; liste.appendChild(tabelle);
  });
  return block;
}

// ============================================================================
// Start
// ============================================================================
(async function start() {
  await ladePublicDashboard();
  await checkAuth();
  if (STATE.user) await ladeAlles();
  render();
})();
