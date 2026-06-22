# BRI — Biodiversity Risk Intelligence v0.2 AI

Projecte obert de CibraLAB per monitoritzar riscos emergents en la intersecció entre biodiversitat, salut pública, clima i economia.

## Què inclou

- Web pública HTML/CSS/JS.
- Mapa interactiu amb Leaflet.
- Dades JSON estàtiques per funcionar immediatament.
- Actualitzador PHP automàtic per cron.
- Dades meteorològiques i qualitat de l'aire via Open-Meteo, sense API key.
- Senyals oberts via RSS.
- Capa d'intel·ligència:
  - resum executiu,
  - detecció de senyals febles,
  - correlacions natura-salut-clima,
  - hipòtesis orientatives,
  - perspectiva a 7 i 30 dies,
  - informe HTML automàtic.
- Mode IA real opcional via endpoint compatible amb OpenAI.
- Mode fallback sense claus API, perquè el projecte funcioni igualment.

## Instal·lació a IONOS

1. Puja el contingut de `public/` a la carpeta pública del subdomini.
2. Puja `scripts/` i `config/` fora de la carpeta pública, si el teu hosting ho permet.
3. Executa manualment:

```bash
/usr/bin/php /ruta/al/projecte/scripts/update.php
```

4. Programa un cron diari:

```bash
0 6 * * * /usr/bin/php /ruta/al/projecte/scripts/update.php >/dev/null 2>&1
```

## Activar IA real

Per defecte el projecte funciona sense cap clau. Si vols activar LLM:

1. Copia `.env.example` com `.env` només al servidor.
2. Defineix variables d'entorn al panell o shell del servidor:

```bash
BRI_AI_ENABLED=1
BRI_AI_ENDPOINT=https://api.openai.com/v1/chat/completions
BRI_AI_MODEL=gpt-4o-mini
BRI_AI_API_KEY=la_teva_clau_real
```

No pugis mai `.env` a GitHub.

## Seguretat

Aquest repositori no inclou secrets, tokens ni credencials. `.gitignore` exclou `.env`, logs, claus i backups.

## Avís

BRI és una eina informativa i experimental. No emet alertes oficials, no substitueix autoritats sanitàries o ambientals i no ofereix diagnòstic mèdic.
