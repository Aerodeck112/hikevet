# Asistentul AI (Hiko)

Butonul „Întreabă-l pe Hiko” deschide un chat care răspunde la întrebări despre clinică.
Folosește modelul Claude (Anthropic) prin SDK-ul oficial pentru PHP (`anthropic-ai/sdk`).

## Pornire
1. În contul Anthropic (platform.claude.com) se creează o cheie API și se adaugă credit (plata e după consum).
2. Admin → Conținut site → **Asistent AI: setări**: lipești cheia (se salvează criptat, nu se mai afișează),
   bifezi „Asistentul apare pe site” și salvezi.
3. Model implicit: Claude Opus 5.5, efort „Echilibrat”. Se pot alege și Sonnet 5.5 sau Haiku 5.5 (mai ieftine).

## Ce știe
- Automat, din site: datele clinicii, programul (dacă e completat în Setări clinică), serviciile, echipa,
  întrebările frecvente, lista de prețuri activă a abonamentelor, regulile Wallet și cum se face o cerere de programare.
- **Asistent AI: ce știe** – informații scrise de clinică (program de sărbători, parcare, reguli). Au prioritate.
- **Asistent AI: întrebări** – ce au întrebat vizitatorii. La o întrebare la care asistentul n-a știut, butonul
  „Învață-l răspunsul” adaugă răspunsul corect la cunoștințe; asistentul îl folosește de la următoarea întrebare.
- Butonul „Reîncarcă ce știe asistentul” preia imediat modificările de pe site (altfel se reîncarcă singur la 30 de minute).

## Reguli fixe
- Răspunde doar despre clinică și îngrijirea animalelor de companie, în română.
- Nu pune diagnostice, nu recomandă medicamente sau doze; la urgențe trimite imediat la telefon.
- Nu inventează prețuri sau orare; dacă nu știe, trimite la telefon sau la cererea de programare.
- Abonamentul nu este numit „asigurare”.
- Linkurile din răspunsuri pot duce doar spre site-ul clinicii; textul este afișat fără HTML.

## Limite și costuri
- Cel mult N întrebări pe zi de la același vizitator (setabil, implicit 30) și 10 pe minut.
- Instrucțiunile și cunoștințele sunt păstrate în cache la Anthropic o oră, deci se plătesc integral doar la prima întrebare.
- Dacă modelul refuză o cerere din motive de siguranță, API-ul încearcă automat un model de rezervă (server-side fallback).
- Pagina de setări arată numărul de întrebări și tokenii folosiți în ultimele 30 de zile.

## Date personale
- Conversația stă în browser doar pe durata vizitei. Pe server se păstrează întrebarea și răspunsul, fără IP,
  60 de zile (ștergere automată zilnică), ca să vedem ce trebuie completat.
- Întrebările sunt trimise la Anthropic pentru generarea răspunsului; merită menționat în Politica de confidențialitate.
- Asistentul cere vizitatorilor să nu scrie date sensibile (CNP, card, parole).
