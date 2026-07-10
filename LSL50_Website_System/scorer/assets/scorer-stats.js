(function () {
  const scoreForm = document.getElementById('scoreForm');
  const homeTeamId = parseInt(scoreForm?.dataset.homeTeamId || '0', 10);
  const awayTeamId = parseInt(scoreForm?.dataset.awayTeamId || '0', 10);
function statValue(row, key) {
const input = row.querySelector(`input[name$="[${key}]"]`);
return parseInt(input ? input.value || 0 : 0);
}
function updateScorePreview(){
let home = 0, away = 0;
document.querySelectorAll('#boxTable tr[data-scorer-row]').forEach(tr => {
  const teamCell = tr.querySelector('[data-team-id]');
  const teamId = parseInt(teamCell ? teamCell.dataset.teamId : tr.querySelector('input[name$="[team_id]"]').value);
  const runs = statValue(tr, 'R');
  const paCell = tr.querySelector('[data-pa-auto]');
  if (paCell) paCell.textContent = statValue(tr, 'AB') + statValue(tr, 'BB') + statValue(tr, 'HBP') + statValue(tr, 'SH') + statValue(tr, 'SF');
  if (teamId === homeTeamId) home += runs;
  if (teamId === awayTeamId) away += runs;
});
document.getElementById('homeScorePreview').textContent = home;
document.getElementById('awayScorePreview').textContent = away;
}
document.querySelectorAll('.stat').forEach(input => input.addEventListener('input', updateScorePreview));
updateScorePreview();
})();
