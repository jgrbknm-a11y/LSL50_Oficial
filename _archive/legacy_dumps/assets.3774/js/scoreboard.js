(async ()=>{
  const gameId = new URLSearchParams(location.search).get('game') || 'G-001';
  const {data:gamesDoc} = await fetchJSON('games');
  const game = (gamesDoc || []).find(g=>g.game_id===gameId) || {home:'HOME',away:'AWAY',innings:[],final:null};

  const elHome = document.getElementById('homeName');
  const elAway = document.getElementById('awayName');
  const elScore = document.getElementById('score');
  const innWrap = document.getElementById('inn');

  elHome.textContent = game.home;
  elAway.textContent = game.away;

  function totals(g){
    let h=0,a=0;
    (g.innings||[]).forEach(x=>{ a += +x.away||0; h += +x.home||0; });
    return {a,h};
  }
  const t = totals(game);
  elScore.textContent = `${t.a} : ${t.h}`;

  const cells = [];
  for(let i=1;i<=9;i++){
    const inn = (game.innings||[])[i-1] || {};
    cells.push(`<div>${inn.away??''}</div><div>${inn.home??''}</div>`);
  }
  innWrap.innerHTML = cells.join('');
})();
