#!/usr/bin/env node
/*
 * Test: reine Umsortierlogik für "Schritte verschieben" (Drag-and-drop,
 * Pfeiltasten) – innerhalb einer Phase und phasenübergreifend.
 *
 * Lädt die echten Funktionen verschiebeSchritt() und
 * pruefeSchrittlisteUnveraendert() aus app.js (kein Nachbau, gleiches
 * Verfahren wie tests/gruppierung.test.js).
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

const verschiebeSchritt = new Function(
  `${extrahiereFunktion(quelltext, 'verschiebeSchritt')}\nreturn verschiebeSchritt;`
)();
const pruefeSchrittlisteUnveraendert = new Function(
  `${extrahiereFunktion(quelltext, 'pruefeSchrittlisteUnveraendert')}\nreturn pruefeSchrittlisteUnveraendert;`
)();
const gruppiereNachPhase = new Function(
  `${extrahiereFunktion(quelltext, 'gruppiereNachPhase')}\nreturn gruppiereNachPhase;`
)();

let fehler = 0;
function gruen(txt) { console.log(`  ✓ ${txt}`); }
function rot(txt, detail) { console.log(`  ✗ ${txt}${detail ? ' – ' + detail : ''}`); fehler++; }

function pruefeGleich(label, ist, soll) {
  const istJson = JSON.stringify(ist), sollJson = JSON.stringify(soll);
  if (istJson === sollJson) gruen(label);
  else rot(label, `ist=${istJson} soll=${sollJson}`);
}

// Grunddaten: Phase A mit 3 Schritten, Phase B mit 2, Phase C mit genau 1.
function grundliste() {
  return [
    { id: 1, phase: 'Phase A', phase_farbe: '#111', reihenfolge: 1, titel: 'A1' },
    { id: 2, phase: 'Phase A', phase_farbe: '#111', reihenfolge: 2, titel: 'A2' },
    { id: 3, phase: 'Phase A', phase_farbe: '#111', reihenfolge: 3, titel: 'A3' },
    { id: 4, phase: 'Phase B', phase_farbe: '#222', reihenfolge: 1, titel: 'B1' },
    { id: 5, phase: 'Phase B', phase_farbe: '#222', reihenfolge: 2, titel: 'B2' },
    { id: 6, phase: 'Phase C', phase_farbe: '#333', reihenfolge: 1, titel: 'C1' },
  ];
}
const titel = (liste) => liste.map((s) => s.titel);
const reihenfolgeInPhase = (liste, phase) => liste
  .filter((s) => s.phase === phase)
  .sort((a, b) => a.reihenfolge - b.reihenfolge)
  .map((s) => s.titel);

// 1) Innerhalb einer Phase nach oben (A3 vor A2)
{
  const neu = verschiebeSchritt(grundliste(), 3, 'Phase A', 1);
  pruefeGleich('innerhalb der Phase nach oben', reihenfolgeInPhase(neu, 'Phase A'), ['A1', 'A3', 'A2']);
}

// 2) Innerhalb einer Phase nach unten (A1 hinter A2)
{
  const neu = verschiebeSchritt(grundliste(), 1, 'Phase A', 1);
  pruefeGleich('innerhalb der Phase nach unten', reihenfolgeInPhase(neu, 'Phase A'), ['A2', 'A1', 'A3']);
}

// 3) An den Anfang der eigenen Phase
{
  const neu = verschiebeSchritt(grundliste(), 3, 'Phase A', 0);
  pruefeGleich('an den Anfang der Phase', reihenfolgeInPhase(neu, 'Phase A'), ['A3', 'A1', 'A2']);
}

// 4) Ans Ende der eigenen Phase
{
  const neu = verschiebeSchritt(grundliste(), 1, 'Phase A', 2);
  pruefeGleich('ans Ende der Phase', reihenfolgeInPhase(neu, 'Phase A'), ['A2', 'A3', 'A1']);
}

// 5) In eine andere (nicht-leere) Phase, an eine bestimmte Position
{
  const neu = verschiebeSchritt(grundliste(), 1, 'Phase B', 1);
  pruefeGleich('in andere Phase verschoben – Zielphase korrekt', reihenfolgeInPhase(neu, 'Phase B'), ['B1', 'A1', 'B2']);
  pruefeGleich('in andere Phase verschoben – Quellphase lückenlos neu nummeriert', reihenfolgeInPhase(neu, 'Phase A'), ['A2', 'A3']);
  const bewegter = neu.find((s) => s.id === 1);
  pruefeGleich('verschobener Schritt zeigt neue Phase im phase-Feld', bewegter.phase, 'Phase B');
  pruefeGleich('verschobener Schritt übernimmt die Farbe der Zielphase', bewegter.phase_farbe, '#222');
}

// 6) In eine leere Phase (Phase D existiert nur als Zielname, kein Schritt trägt ihn)
{
  const neu = verschiebeSchritt(grundliste(), 6, 'Phase D', 0);
  pruefeGleich('in leere Phase verschoben', reihenfolgeInPhase(neu, 'Phase D'), ['C1']);
  pruefeGleich('Gesamtzahl bleibt gleich', neu.length, grundliste().length);
}

// 7) Einzelner Schritt als einziger seiner Phase – Verschieben innerhalb der
//    eigenen (einelementigen) Phase darf nichts kaputt machen.
{
  const neu = verschiebeSchritt(grundliste(), 6, 'Phase C', 0);
  pruefeGleich('einziger Schritt seiner Phase bleibt bei sich selbst', reihenfolgeInPhase(neu, 'Phase C'), ['C1']);
}

// 8) Robustheit: kein Schritt geht verloren oder verdoppelt sich, bei jeder
//    der obigen Operationen.
{
  const alt = grundliste();
  const neu = verschiebeSchritt(alt, 2, 'Phase B', 0);
  if (pruefeSchrittlisteUnveraendert(alt, neu)) gruen('pruefeSchrittlisteUnveraendert erkennt eine gültige Verschiebung als unverändert');
  else rot('pruefeSchrittlisteUnveraendert erkennt eine gültige Verschiebung als unverändert');

  const kaputt = neu.filter((s) => s.id !== 5); // simuliert einen verlorenen Schritt
  if (!pruefeSchrittlisteUnveraendert(alt, kaputt)) gruen('pruefeSchrittlisteUnveraendert schlägt bei einem verlorenen Schritt an (Gegenprobe)');
  else rot('pruefeSchrittlisteUnveraendert schlägt bei einem verlorenen Schritt an (Gegenprobe)');

  const verdoppelt = [...neu, { ...neu[0] }];
  if (!pruefeSchrittlisteUnveraendert(alt, verdoppelt)) gruen('pruefeSchrittlisteUnveraendert schlägt bei einer Verdopplung an (Gegenprobe)');
  else rot('pruefeSchrittlisteUnveraendert schlägt bei einer Verdopplung an (Gegenprobe)');
}

// 9) Regressionstest (alter Fehler, siehe tests/gruppierung.test.js): das
//    Ergebnis von verschiebeSchritt() darf, durch gruppiereNachPhase()
//    gruppiert, nie eine Phase doppelt erzeugen – auch wenn die Verschiebung
//    einen Schritt zwischen fremde Phasen hindurch an eine neue Stelle setzt.
{
  const neu = verschiebeSchritt(grundliste(), 1, 'Phase C', 0); // A1 hinter C1, weit hinten in der flachen Liste
  const gruppen = gruppiereNachPhase(neu);
  const phaseAGruppen = gruppen.filter((g) => g.phase === 'Phase A');
  if (phaseAGruppen.length === 1) gruen('verschobene Liste erzeugt keine doppelte Phasenüberschrift');
  else rot('verschobene Liste erzeugt keine doppelte Phasenüberschrift', `Phase A ${phaseAGruppen.length}× statt 1×`);
}

if (fehler > 0) { console.log(`${fehler} FEHLER`); process.exit(1); }
console.log('ALLES GRÜN');
process.exit(0);
