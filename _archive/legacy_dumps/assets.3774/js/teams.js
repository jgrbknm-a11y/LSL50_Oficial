(async ()=>{
  const out = document.getElementById('teamsTableBody');
  const {data} = await fetchJSON('teams');
  out.innerHTML = data.map(t=>`<tr>
    <td><span class="badge">${t.id}</span></td>
    <td>${t.name}</td>
    <td>${t.manager||''}</td>
    <td>${t.city||''}</td>
    <td>${t.founded||''}</td>
  </tr>`).join('');
})();
