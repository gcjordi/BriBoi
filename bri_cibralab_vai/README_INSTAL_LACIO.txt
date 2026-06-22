BRI v0.2 AI — Instal·lació ràpida a IONOS
==========================================

1) Puja el contingut de la carpeta public/ al directori públic del subdomini.
2) Mantén scripts/ i config/ fora de public/ sempre que sigui possible.
3) Executa /usr/bin/php scripts/update.php per actualitzar dades.
4) Programa un cron diari, per exemple a les 06:00.
5) La IA real és opcional. Sense clau API, el sistema funciona amb mode fallback explicable.

Variables d'entorn opcionals:
BRI_AI_ENABLED=1
BRI_AI_ENDPOINT=https://api.openai.com/v1/chat/completions
BRI_AI_MODEL=gpt-4o-mini
BRI_AI_API_KEY=CLAU_REAL_NOMES_AL_SERVIDOR

No pugis mai .env real a GitHub.
