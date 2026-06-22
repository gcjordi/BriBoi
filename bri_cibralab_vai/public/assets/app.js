const levelColor = {low:'#60d394', moderate:'#ffd166', high:'#f77f00', critical:'#ef476f'};
async function getJSON(path){ const r = await fetch(path + '?v=' + Date.now()); if(!r.ok) throw new Error(path); return r.json(); }
function badge(cls,txt){ return `<span class="badge ${cls}">${txt}</span>`; }
function fmtDate(s){ try{return new Date(s).toLocaleString('ca-ES')}catch(e){return s} }
function componentBars(c){
  return Object.entries(c||{}).map(([k,v])=>`<div class="barrow"><span>${k}</span><div class="bar"><i style="width:${Math.max(0,Math.min(100,v))}%"></i></div><b>${Math.round(v)}</b></div>`).join('');
}
function renderList(items){ return (items||[]).map(x=>`<li>${typeof x==='string'?x:(x.title?`<strong>${x.title}</strong>: ${x.why_it_matters||''}`:JSON.stringify(x))}</li>`).join(''); }
async function main(){
  const [scores, signals, insights, report] = await Promise.all([
    getJSON('data/bri_scores.json'), getJSON('data/news_signals.json'), getJSON('data/ai_insights.json').catch(()=>null), getJSON('data/report.json').catch(()=>null)
  ]);
  document.getElementById('updated').textContent = 'Actualitzat: ' + fmtDate(scores.updated_at);
  const items = scores.items || [];
  const avg = Math.round(items.reduce((a,b)=>a+b.score,0)/Math.max(1,items.length));
  const high = items.filter(x=>x.score>=51).length;
  const critical = items.filter(x=>x.score>=76).length;
  const max = [...items].sort((a,b)=>b.score-a.score)[0] || {region:'-', score:0};
  document.getElementById('summary').innerHTML = `
    <article class="card"><span>Score mitjà</span><b>${avg}</b></article>
    <article class="card"><span>Territoris</span><b>${items.length}</b></article>
    <article class="card"><span>Risc alt o superior</span><b>${high}</b></article>
    <article class="card"><span>Focus principal</span><b>${max.region}</b></article>`;
  document.getElementById('regions').innerHTML = items.sort((a,b)=>b.score-a.score).map(x=>`<tr><td><strong>${x.region}</strong><br><span class="muted">${x.country}</span></td><td>${badge(x.level_class,x.level)}</td><td><strong>${x.score}</strong>/100</td><td>${x.main_risks.join(', ')}</td><td>${componentBars(x.components)}</td></tr>`).join('');
  const map = L.map('map', {scrollWheelZoom:false}).setView([40.1,-3.2],5);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:12, attribution:'© OpenStreetMap'}).addTo(map);
  items.forEach(x=>{
    L.circleMarker([x.lat,x.lon],{radius:10+Math.round(x.score/8),color:levelColor[x.level_class],fillColor:levelColor[x.level_class],fillOpacity:.72,weight:2})
      .addTo(map).bindPopup(`<strong>${x.region}</strong><br>${badge(x.level_class,x.level)}<br>Score: ${x.score}/100<br>${x.summary}<hr>${componentBars(x.components)}`);
  });
  document.getElementById('signals').innerHTML = (signals.items||[]).slice(0,8).map(s=>`<article class="signal"><h3>${s.title}</h3><p>${s.summary||''}</p><small>${s.source} · ${(s.categories||[]).join(', ')}</small>${s.link?` · <a target="_blank" rel="noopener" href="${s.link}">Font</a>`:''}</article>`).join('') || '<p class="muted">Encara no hi ha senyals. Executa scripts/update.php.</p>';
  if(insights){
    document.getElementById('aiBox').innerHTML = `<h2>Capa d’intel·ligència IA</h2><p class="lead">${insights.executive_summary||''}</p><div class="grid2"><article><h3>Senyals febles</h3><ul>${renderList(insights.weak_signals)}</ul></article><article><h3>Correlacions natura-salut-clima</h3><ul>${renderList(insights.cross_domain_correlations)}</ul></article></div><div class="grid2"><article><h3>Perspectiva 7 dies</h3><p>${insights.seven_day_outlook||''}</p></article><article><h3>Perspectiva 30 dies</h3><p>${insights.thirty_day_outlook||''}</p></article></div><p class="muted">Confiança estimada: ${insights.confidence||'n/d'}/100 · Mode: ${insights.mode||'LLM configurat o anàlisi IA'}</p>${report?.url?`<a class="button" href="${report.url}">Llegir informe complet</a>`:''}`;
  }
}
main().catch(err=>{document.body.insertAdjacentHTML('afterbegin',`<div style="padding:16px;background:#ef476f;color:white">No s'han pogut carregar les dades. Executa scripts/update.php o revisa /public/data.</div>`);console.error(err);});
