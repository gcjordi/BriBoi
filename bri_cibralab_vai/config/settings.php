<?php
return [
  'site_name' => 'Biodiversity Risk Intelligence',
  'site_subtitle' => 'Observatori obert de biodiversitat, salut pública i riscos emergents',
  'timezone' => 'Europe/Madrid',
  'regions_file' => __DIR__ . '/regions.json',
  'public_data_dir' => __DIR__ . '/../public/data',
  'reports_dir' => __DIR__ . '/../public/reports',
  'logs_dir' => __DIR__ . '/../logs',
  'rss_feeds' => [
    'ECDC' => 'https://www.ecdc.europa.eu/en/rss.xml',
    'WHO' => 'https://www.who.int/rss-feeds/news-english.xml',
    'Copernicus' => 'https://climate.copernicus.eu/rss.xml',
    'EEA' => 'https://www.eea.europa.eu/highlights/RSS',
    'ReliefWeb Health' => 'https://reliefweb.int/updates/rss.xml?advanced-search=%28T4598%29',
    'PubMed zoonosis' => 'https://pubmed.ncbi.nlm.nih.gov/rss/search/1Hf0zzC9cVbQp8Y7QJBZ8n9tK5QnV8vL8aR4mR0mP6x/?limit=15&utm_campaign=pubmed-2&fc=20260621215000'
  ],
  'keywords' => [
    'zoonotic' => ['zoonotic','avian influenza','west nile','dengue','mosquito','tick','vector','outbreak','virus','paparra','mosquit','brote','zoonosis','one health','malaria','chikungunya'],
    'climate' => ['heat','drought','wildfire','fire','flood','extreme weather','calor','sequera','incendi','inundació','heatwave','storm'],
    'biodiversity' => ['biodiversity','invasive','species','habitat','ecosystem','wildlife','biodiversitat','invasora','espècie','fauna','pollinator','forest'],
    'air' => ['air quality','pollution','particulate','ozone','qualitat de l’aire','contaminació','pm2.5','pm10'],
    'economic' => ['agriculture','tourism','insurance','crop','livestock','agricultura','turisme','assegurances','cultiu','ramaderia']
  ],
  'open_meteo_endpoint' => 'https://api.open-meteo.com/v1/forecast',
  'air_quality_endpoint' => 'https://air-quality-api.open-meteo.com/v1/air-quality',
  'ai' => [
    'enabled' => getenv('BRI_AI_ENABLED') === '1',
    'provider' => getenv('BRI_AI_PROVIDER') ?: 'openai_compatible',
    'endpoint' => getenv('BRI_AI_ENDPOINT') ?: 'https://api.openai.com/v1/chat/completions',
    'api_key' => getenv('BRI_AI_API_KEY') ?: '',
    'model' => getenv('BRI_AI_MODEL') ?: 'gpt-4o-mini',
    'timeout' => 45,
    'max_tokens' => 1800
  ]
];
