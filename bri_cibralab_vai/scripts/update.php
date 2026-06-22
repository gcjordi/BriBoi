<?php
// Biodiversity Risk Intelligence v0.2 AI - automatic updater
// Run from SSH/cron: /usr/bin/php /path/to/scripts/update.php

$config = require __DIR__ . '/../config/settings.php';
date_default_timezone_set($config['timezone']);

function ensure_dir($dir) { if (!is_dir($dir)) mkdir($dir, 0755, true); }
function write_json($path, $data) { file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); }
function read_json($path, $fallback=[]) { return is_file($path) ? (json_decode(file_get_contents($path), true) ?: $fallback) : $fallback; }
function fetch_url($url, $timeout=15) {
  $ctx = stream_context_create(['http'=>['timeout'=>$timeout,'header'=>"User-Agent: CibraLAB-BRI/0.2 (+https://cibralab.eu)\r\n"]]);
  $data = @file_get_contents($url, false, $ctx);
  return $data === false ? null : $data;
}
function clamp($n,$min=0,$max=100){ return max($min,min($max,$n)); }
function risk_level($score) {
  if ($score >= 76) return ['label'=>'crític','class'=>'critical'];
  if ($score >= 51) return ['label'=>'alt','class'=>'high'];
  if ($score >= 26) return ['label'=>'moderat','class'=>'moderate'];
  return ['label'=>'baix','class'=>'low'];
}
function cat_score($signals, $cat) {
  $n = 0; $max = 0;
  foreach ($signals as $s) if (in_array($cat, $s['categories'] ?? [])) { $n++; $max = max($max, $s['score'] ?? 0); }
  return clamp(min(100, ($n*10)+($max*0.55)));
}
function summarize_region_rule_based($region, $components, $mainRisks) {
  $risks = implode(', ', $mainRisks);
  $top = array_keys($components, max($components))[0] ?? 'climate';
  return "Risc calculat automàticament. Factors destacats: $risks. Component dominant: $top. Interpretació orientativa basada en dades obertes i senyals públics.";
}
function ai_call($config, $messages) {
  if (empty($config['ai']['enabled']) || empty($config['ai']['api_key'])) return null;
  $payload = [
    'model' => $config['ai']['model'],
    'messages' => $messages,
    'temperature' => 0.2,
    'max_tokens' => $config['ai']['max_tokens'],
    'response_format' => ['type'=>'json_object']
  ];
  $ch = curl_init($config['ai']['endpoint']);
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer '.$config['ai']['api_key']],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => $config['ai']['timeout']
  ]);
  $response = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  curl_close($ch);
  if (!$response || $code < 200 || $code >= 300) return null;
  $decoded = json_decode($response, true);
  $content = $decoded['choices'][0]['message']['content'] ?? null;
  return $content ? json_decode($content, true) : null;
}
function safe_excerpt($text, $len=350) { $text = trim(preg_replace('/\s+/u',' ', strip_tags((string)$text))); return mb_substr($text, 0, $len); }

ensure_dir($config['public_data_dir']);
ensure_dir($config['reports_dir']);
ensure_dir($config['logs_dir']);
$regions = read_json($config['regions_file']);
$now = date('c');

// 1) Weather/climate via Open-Meteo.
$climate = [];
$air = [];
foreach ($regions as $r) {
  $url = $config['open_meteo_endpoint'] . '?' . http_build_query([
    'latitude'=>$r['lat'], 'longitude'=>$r['lon'],
    'daily'=>'temperature_2m_max,precipitation_sum,wind_speed_10m_max',
    'forecast_days'=>7, 'timezone'=>'auto'
  ]);
  $raw = fetch_url($url);
  $parsed = $raw ? json_decode($raw, true) : null;
  $temps = $parsed['daily']['temperature_2m_max'] ?? [];
  $rain = $parsed['daily']['precipitation_sum'] ?? [];
  $wind = $parsed['daily']['wind_speed_10m_max'] ?? [];
  $maxTemp = count($temps) ? max($temps) : 28;
  $totalRain = count($rain) ? array_sum($rain) : 5;
  $maxWind = count($wind) ? max($wind) : 20;
  $heatScore = clamp(($maxTemp - 25) * 6);
  $droughtScore = clamp(60 - ($totalRain * 4));
  $fireScore = clamp(($heatScore*0.45)+($droughtScore*0.4)+($maxWind*0.4));
  $climate[$r['id']] = [
    'region'=>$r['name'], 'max_temperature_7d'=>round($maxTemp,1), 'rain_7d'=>round($totalRain,1), 'max_wind_7d'=>round($maxWind,1),
    'heat_score'=>round($heatScore), 'drought_score'=>round($droughtScore), 'fire_score'=>round($fireScore),
    'source'=>'Open-Meteo Forecast API', 'updated_at'=>$now
  ];

  $aqUrl = $config['air_quality_endpoint'] . '?' . http_build_query([
    'latitude'=>$r['lat'], 'longitude'=>$r['lon'],
    'hourly'=>'pm10,pm2_5,ozone', 'forecast_days'=>1, 'timezone'=>'auto'
  ]);
  $aqRaw = fetch_url($aqUrl);
  $aq = $aqRaw ? json_decode($aqRaw, true) : null;
  $pm25 = $aq['hourly']['pm2_5'] ?? [];
  $pm10 = $aq['hourly']['pm10'] ?? [];
  $ozone = $aq['hourly']['ozone'] ?? [];
  $avgPm25 = count($pm25) ? array_sum($pm25)/count($pm25) : 8;
  $avgPm10 = count($pm10) ? array_sum($pm10)/count($pm10) : 15;
  $avgOzone = count($ozone) ? array_sum($ozone)/count($ozone) : 60;
  $airScore = clamp(($avgPm25*2.2)+($avgPm10*0.8)+max(0,($avgOzone-80)*0.45));
  $air[$r['id']] = ['region'=>$r['name'],'pm25'=>round($avgPm25,1),'pm10'=>round($avgPm10,1),'ozone'=>round($avgOzone,1),'air_score'=>round($airScore),'source'=>'Open-Meteo Air Quality API','updated_at'=>$now];
}
write_json($config['public_data_dir'].'/climate.json', ['updated_at'=>$now,'items'=>$climate]);
write_json($config['public_data_dir'].'/air_quality.json', ['updated_at'=>$now,'items'=>$air]);

// 2) RSS/open-source signals.
$signals = [];
foreach ($config['rss_feeds'] as $source=>$feed) {
  $raw = fetch_url($feed);
  if (!$raw) continue;
  $xml = @simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOCDATA);
  if (!$xml) continue;
  $items = $xml->channel->item ?? $xml->entry ?? [];
  $count = 0;
  foreach ($items as $item) {
    if ($count++ > 25) break;
    $title = (string)($item->title ?? '');
    $desc = (string)($item->description ?? $item->summary ?? '');
    $link = (string)($item->link ?? '');
    if (!$link && isset($item->link['href'])) $link = (string)$item->link['href'];
    $text = mb_strtolower($title . ' ' . $desc);
    $cats = [];
    foreach ($config['keywords'] as $cat=>$words) foreach ($words as $w) if (mb_strpos($text, mb_strtolower($w)) !== false) { $cats[]=$cat; break; }
    if (!$cats) continue;
    $signals[] = [
      'source'=>$source, 'title'=>safe_excerpt($title,180), 'summary'=>safe_excerpt($desc,360),
      'link'=>$link, 'published'=>(string)($item->pubDate ?? $item->updated ?? $now), 'categories'=>array_values(array_unique($cats)),
      'score'=>min(100, 25 + count($cats)*18)
    ];
  }
}
usort($signals, fn($a,$b)=>($b['score']<=>$a['score']));
$signals = array_slice($signals, 0, 60);
write_json($config['public_data_dir'].'/news_signals.json', ['updated_at'=>$now,'items'=>$signals]);

// 3) Scores with explainable components.
$items = [];
$globalZoonotic = cat_score($signals, 'zoonotic');
$globalBiodiv = cat_score($signals, 'biodiversity');
$globalNews = clamp(count($signals)*4);
$globalEconomic = cat_score($signals, 'economic');
foreach ($regions as $r) {
  $c = $climate[$r['id']];
  $a = $air[$r['id']];
  $climateScore = round(($c['heat_score']*0.35)+($c['drought_score']*0.35)+($c['fire_score']*0.30));
  $zoonotic = clamp($globalZoonotic + ($c['max_temperature_7d']>30 ? 8:0) + ($c['rain_7d']>15 ? 6:0));
  $biodiv = clamp($globalBiodiv + ($c['drought_score']>45 ? 10:0) + ($c['fire_score']>45 ? 8:0));
  $airScore = $a['air_score'];
  $newsScore = clamp($globalNews + $globalEconomic*0.25);
  $score = round(($climateScore*0.25)+($zoonotic*0.25)+($biodiv*0.20)+($airScore*0.15)+($newsScore*0.15));
  $lvl = risk_level($score);
  $risks = [];
  if ($climateScore > 45) $risks[]='clima/calor';
  if ($c['drought_score'] > 45) $risks[]='sequera';
  if ($c['fire_score'] > 45) $risks[]='incendis';
  if ($zoonotic > 45) $risks[]='vectors/zoonosis';
  if ($biodiv > 45) $risks[]='biodiversitat';
  if ($airScore > 45) $risks[]='qualitat de l’aire';
  if (!$risks) $risks[]='vigilància general';
  $components = ['climate'=>$climateScore,'zoonotic'=>$zoonotic,'biodiversity'=>$biodiv,'air'=>$airScore,'news'=>$newsScore];
  $items[] = [
    'id'=>$r['id'],'region'=>$r['name'],'country'=>$r['country'],'lat'=>$r['lat'],'lon'=>$r['lon'],
    'score'=>$score,'level'=>$lvl['label'],'level_class'=>$lvl['class'],'main_risks'=>$risks,
    'components'=>$components,
    'explainability'=>[
      'climate'=>'Calor, sequera i vent dels propers 7 dies.',
      'zoonotic'=>'Senyals oberts sobre vectors, brots i malalties emergents.',
      'biodiversity'=>'Senyals sobre biodiversitat, espècies invasores, hàbitats i fauna.',
      'air'=>'Estimació de PM2.5, PM10 i ozó.',
      'news'=>'Volum i rellevància de senyals oberts recents.'
    ],
    'summary'=>summarize_region_rule_based($r['name'],$components,$risks),'updated_at'=>$now
  ];
}
write_json($config['public_data_dir'].'/bri_scores.json', ['updated_at'=>$now,'methodology'=>'BRI Score v0.2: clima 25%, zoonosi 25%, biodiversitat 20%, aire 15%, senyals de fonts obertes 15%. IA opcional per interpretació i senyals febles.','items'=>$items]);

// 4) AI intelligence layer: LLM if configured, otherwise deterministic fallback.
$topSignals = array_slice($signals, 0, 20);
$topRegions = array_slice($items, 0, 10);
$aiInput = ['updated_at'=>$now,'regions'=>$topRegions,'signals'=>$topSignals];
$aiResult = ai_call($config, [
  ['role'=>'system','content'=>'Ets un analista de biodiversitat, salut pública, clima i riscos emergents. Respon només JSON vàlid en català. No facis diagnòstic mèdic ni alertes oficials. Indica incerteses.'],
  ['role'=>'user','content'=>'Analitza aquestes dades BRI i retorna JSON amb: executive_summary, weak_signals(array), cross_domain_correlations(array), hypotheses(array), seven_day_outlook, thirty_day_outlook, recommended_public_actions(array), confidence(0-100), limitations(array). Dades: '.json_encode($aiInput, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]
]);
if (!$aiResult) {
  $avg = round(array_sum(array_column($items,'score'))/max(1,count($items)));
  $maxItem = $items[0]; foreach ($items as $it) if ($it['score']>$maxItem['score']) $maxItem=$it;
  $cats = [];
  foreach ($signals as $s) foreach (($s['categories']??[]) as $cat) $cats[$cat]=($cats[$cat]??0)+1;
  arsort($cats);
  $aiResult = [
    'mode'=>'rule_based_fallback',
    'executive_summary'=>"El sistema detecta un BRI mitjà de $avg/100. El territori amb més risc relatiu és {$maxItem['region']} amb {$maxItem['score']}/100. La lectura és orientativa i es basa en dades meteorològiques, qualitat ambiental i senyals oberts.",
    'weak_signals'=>array_slice(array_map(fn($s)=>['title'=>$s['title'],'why_it_matters'=>'Pot actuar com a senyal primerenc dins les categories '.implode(', ', $s['categories']??[]),'source'=>$s['source']], $topSignals),0,5),
    'cross_domain_correlations'=>[
      'La combinació de calor, baixa precipitació i senyals de biodiversitat pot elevar el risc d’estrès ecosistèmic.',
      'Els senyals sobre vectors o brots adquireixen més rellevància quan coincideixen amb temperatures elevades i episodis de pluja.',
      'La qualitat de l’aire pot amplificar impactes en salut quan coincideix amb calor sostinguda.'
    ],
    'hypotheses'=>[
      'Hipòtesi 1: les zones amb calor i sequera sostinguda poden incrementar vulnerabilitat ecològica a curt termini.',
      'Hipòtesi 2: els senyals sobre vectors mereixen seguiment quan coincideixen amb condicions climàtiques favorables.',
      'Hipòtesi 3: el risc socioeconòmic pot créixer si els factors ambientals afecten agricultura, turisme o salut comunitària.'
    ],
    'seven_day_outlook'=>'Seguiment especialment recomanat en territoris amb score alt o components climàtics elevats.',
    'thirty_day_outlook'=>'Si persisteixen calor, sequera i senyals de vectors, el risc agregat podria augmentar en diverses zones.',
    'recommended_public_actions'=>['Consultar sempre fonts oficials.','Evitar interpretar el BRI com una alerta sanitària.','Fer vigilància comunitària responsable sobre mosquits, fauna morta o espècies invasores.','Compartir dades obertes i observacions verificables.'],
    'confidence'=>55,
    'limitations'=>['Mode fallback sense LLM extern activat.','Les fonts RSS poden ser incompletes.','El sistema no substitueix autoritats públiques.']
  ];
}
$aiResult['updated_at']=$now;
write_json($config['public_data_dir'].'/ai_insights.json', $aiResult);

// 5) Public weekly HTML report.
$reportHtml = "<!doctype html><html lang='ca'><head><meta charset='utf-8'><meta name='viewport' content='width=device-width, initial-scale=1'><title>Informe BRI</title><link rel='stylesheet' href='../assets/style.css'></head><body><main class='doc'><a href='../index.html'>← Tornar</a><h1>Informe intel·ligent BRI</h1><p><strong>Actualitzat:</strong> $now</p><h2>Resum executiu</h2><p>".htmlspecialchars($aiResult['executive_summary'] ?? '')."</p><h2>Senyals febles</h2><ul>";
foreach (($aiResult['weak_signals'] ?? []) as $ws) { $txt = is_array($ws) ? (($ws['title']??'Senyal').' — '.($ws['why_it_matters']??'')) : $ws; $reportHtml .= '<li>'.htmlspecialchars($txt).'</li>'; }
$reportHtml .= "</ul><h2>Correlacions</h2><ul>";
foreach (($aiResult['cross_domain_correlations'] ?? []) as $c) $reportHtml .= '<li>'.htmlspecialchars(is_array($c)?json_encode($c,JSON_UNESCAPED_UNICODE):$c).'</li>';
$reportHtml .= "</ul><h2>Perspectives</h2><p><strong>7 dies:</strong> ".htmlspecialchars($aiResult['seven_day_outlook'] ?? '')."</p><p><strong>30 dies:</strong> ".htmlspecialchars($aiResult['thirty_day_outlook'] ?? '')."</p><h2>Limitacions</h2><ul>";
foreach (($aiResult['limitations'] ?? []) as $l) $reportHtml .= '<li>'.htmlspecialchars($l).'</li>';
$reportHtml .= "</ul><p class='muted'>No és una alerta oficial ni consell mèdic.</p></main></body></html>";
file_put_contents($config['reports_dir'].'/latest.html', $reportHtml);
write_json($config['public_data_dir'].'/report.json', ['updated_at'=>$now,'title'=>'Informe intel·ligent BRI','url'=>'reports/latest.html','summary'=>$aiResult['executive_summary'] ?? 'Informe generat.']);

echo "BRI v0.2 AI update completed at $now\n";
