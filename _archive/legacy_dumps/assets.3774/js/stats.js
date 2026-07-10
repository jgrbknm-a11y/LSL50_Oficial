(async ()=>{
  const out = document.getElementById('playersStatsBody');
  const {data:players} = await fetchJSON('players');
  const {data:statsDoc} = await fetchJSON('stats');
  const pstats = statsDoc.players || {};
  const rows = players.map(p=>{
    const s = pstats[p.id] || {G:0,AB:0,H:0,HR:0,RBI:0,BB:0,SO:0,AVG:0,OBP:0};
    const avg = s.AB? (s.H/s.AB).toFixed(3) : '0.000';
    return `<tr>
      <td><span class="badge">${p.id}</span></td>
      <td>${p.name}</td>
      <td>${p.team_id}</td>
      <td>${s.G||0}</td>
      <td>${s.AB||0}</td>
      <td>${s.H||0}</td>
      <td>${s.HR||0}</td>
      <td>${s.RBI||0}</td>
      <td>${avg}</td>
    </tr>`;
  });
  out.innerHTML = rows.join('');
})();
