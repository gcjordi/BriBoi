# AGENTS.md — Instruccions per a Codex

Objectiu: mantenir BRI com a projecte obert, segur, transparent i fàcil de desplegar a hosting PHP compartit.

Regles obligatòries:
- No afegir secrets, claus API, tokens, credencials ni fitxers .env reals.
- No canviar el projecte cap a dependències pesades sense justificació.
- Mantenir compatibilitat amb PHP 8.1+ i hosting compartit tipus IONOS.
- Els càlculs de risc han de ser explicables i traçables.
- La IA pot resumir, classificar i formular hipòtesis, però no ha d'emetre alertes oficials ni consell mèdic.
- Qualsevol nou connector extern ha de tenir timeout, fallback i no trencar la web si falla.
- Els canvis importants han d'anar per pull request i sense auto-merge.

Prioritats:
1. Seguretat.
2. Simplicitat.
3. Transparència metodològica.
4. Valor social i científic.
