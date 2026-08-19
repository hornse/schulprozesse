#!/usr/bin/env node
/*
 * Regressionstest: Phasen-Gruppierung in Checkliste, Zeitstrahl und SVG-Export.
 *
 * Bug (August 2026): Ein neuer Schritt in einer Phase erschien in einer
 * zweiten, dublizierten Phasenüberschrift, sobald die flache Schrittliste
 * ihn nicht mehr direkt neben seinen Phasengeschwistern führte (Ursache:
 * handleCreateInstanzSchritt vergibt reihenfolge über den ganzen Prozess
 * statt je Phase, backend/api/schritte.php – und die Ansichten gruppierten
 * nur über Zeilennachbarschaft statt über den Phasenwert).
 *
 * Dieser Test lädt die echte Funktion gruppiereNachPhase() aus app.js (kein
 * Nachbau) und prüft, dass eine Phase genau eine Gruppe ergibt, auch wenn
 * ihre Schritte in der flachen Liste nicht zusammenhängen.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
'use strict';
const fs = require('fs');
const path = require('path');

const appJsPfad = path.join(__dirname, '..', 'backend', 'public', 'js', 'app.js');
const quelltext = fs.readFileSync(appJsPfad, 'utf8');

function extrahiereFunktion(quelltext, name) {
  const marker = `function ${name}(`;
  const start = quelltext.indexOf(marker);
  if (start === -1) throw new Error(`Funktion ${name} nicht in app.js gefunden.`);
  const klammerStart = quelltext.indexOf('{', start);
  let tiefe = 0, ende = -1;
  for (let i = klammerStart; i < quelltext.length; i++) {
    if (quelltext[i] === '{') tiefe++;
    else if (quelltext[i] === '}') {
      tiefe--;
      if (tiefe === 0) { ende = i + 1; break; }
    }
  }
  if (ende === -1) throw new Error(`Ende von ${name} nicht gefunden (unausgeglichene Klammern?).`);
  return quelltext.slice(start, ende);
}

const gruppiereNachPhase = new Function(
  `${extrahiereFunktion(quelltext, 'gruppiereNachPhase')}\nreturn gruppiereNachPhase;`
)();

let fehler = 0;
function gruen(txt) { console.log(`  ✓ ${txt}`); }
function rot(txt) { console.log(`  ✗ ${txt}`); fehler++; }

// Nachgestellt: Prozess mit eigenen Phasen "Phase A" (2 Schritte) und
// "Phase B" (1 Schritt). Danach wird ein weiterer Schritt zu "Phase A"
// hinzugefügt. In der echten API landet er wegen MAX(reihenfolge) über den
// gesamten Prozess (statt je Phase) und der fehlenden ORDER BY-Klausel in
// handleListSchritte am Ende der flachen Liste – hinter "Phase B".
const schritte = [
  { id: 1, phase: 'Phase A', phase_farbe: '#111111', phase_reihenfolge: 1, titel: 'A1' },
  { id: 2, phase: 'Phase A', phase_farbe: '#111111', phase_reihenfolge: 1, titel: 'A2' },
  { id: 3, phase: 'Phase B', phase_farbe: '#222222', phase_reihenfolge: 2, titel: 'B1' },
  { id: 4, phase: 'Phase A', phase_farbe: '#111111', phase_reihenfolge: 1, titel: 'A3 (neu)' },
];

const gruppen = gruppiereNachPhase(schritte);
const phaseAGruppen = gruppen.filter((g) => g.phase === 'Phase A');

if (phaseAGruppen.length === 1) {
  gruen('ein zwischen fremde Phasen geschobener Schritt erzeugt keine zweite Phasenüberschrift');
} else {
  rot(`"Phase A" erscheint ${phaseAGruppen.length}× statt 1× (Dublette)`);
}

const gesamtAnzahl = gruppen.reduce((n, g) => n + g.schritte.length, 0);
if (gesamtAnzahl === schritte.length) {
  gruen('kein Schritt geht beim Gruppieren verloren oder wird verdoppelt');
} else {
  rot(`Gruppen enthalten ${gesamtAnzahl} Schritte statt ${schritte.length}`);
}

if (phaseAGruppen.length === 1) {
  const ids = phaseAGruppen[0].schritte.map((s) => s.id).join(',');
  if (ids === '1,2,4') {
    gruen('der neue Schritt steht bei seinen Geschwistern in "Phase A"');
  } else {
    rot(`Reihenfolge in "Phase A" ist ${ids} statt 1,2,4`);
  }
}

if (fehler > 0) { console.log(`${fehler} FEHLER`); process.exit(1); }
console.log('ALLES GRÜN');
process.exit(0);
