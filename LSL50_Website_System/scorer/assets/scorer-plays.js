(function () {
const playForm = document.getElementById('playForm');
  const expectedBatterId = playForm?.dataset.expectedBatterId || '0';
const resultDestinationMap = {
  '1B': '1B',
  'BB': '1B',
  'HBP': '1B',
  'E': '1B',
  'FC': '1B',
  '2B': '2B',
  '3B': '3B',
  'HR': 'H',
  'OUT': 'OUT',
  'SO': 'OUT',
  'SF': 'OUT',
  'SH': 'OUT',
  'SB': 'OUT',
  'WP': 'OUT',
  'PB': 'OUT'
};
function syncResultButtons() {
  const result = playForm?.querySelector('[name="result"]')?.value || 'OUT';
  const outDetail = playForm?.querySelector('[name="out_detail"]')?.value || '';
  const notes = playForm?.querySelector('[name="notes"]')?.value || '';
  playForm?.querySelectorAll('[data-result-value]').forEach(button => {
    const detail = button.getAttribute('data-out-detail') || '';
    const note = button.getAttribute('data-note') || '';
    const matchesResult = button.getAttribute('data-result-value') === result;
    const matchesDetail = detail ? detail === outDetail : true;
    const matchesNote = note ? notes.includes(note) : true;
    button.classList.toggle('active', matchesResult && matchesDetail && matchesNote);
  });
}
function cleanRunnerName(text) {
  return (text || '').replace(/^\s*[-–]\s*/, '').replace(/^[^-]+ - /, '').trim() || 'Corredor';
}
function currentBattingTeamId() {
  return playForm?.querySelector('[name="batting_team_id"]')?.value || '';
}
function optionMatchesTeam(option, teamId) {
  return !option.dataset.teamId || option.dataset.teamId === teamId;
}
function clearIfWrongTeam(select, teamId) {
  if (!select || !select.value) return;
  const option = select.options[select.selectedIndex];
  if (option && !optionMatchesTeam(option, teamId)) select.value = '';
}
function pickFirstTeamOption(select, teamId) {
  if (!select || select.value) return;
  const first = Array.from(select.options).find(option => option.value && optionMatchesTeam(option, teamId));
  if (first) select.value = first.value;
}
function filterSelectByTeam(select, teamId, requireSelection) {
  if (!select) return;
  Array.from(select.options).forEach(option => {
    const shouldHide = !!option.dataset.teamId && option.dataset.teamId !== teamId;
    option.hidden = shouldHide;
    option.disabled = shouldHide;
  });
  clearIfWrongTeam(select, teamId);
  if (requireSelection) pickFirstTeamOption(select, teamId);
}
function applyTeamFilter() {
  if (!playForm) return;
  const teamId = currentBattingTeamId();
  const batterSelect = playForm.querySelector('[data-batter-display]');
  filterSelectByTeam(batterSelect, teamId, true);
  if (expectedBatterId !== '0' && batterSelect?.querySelector(`option[value="${expectedBatterId}"]:not(:disabled)`)) {
    batterSelect.value = expectedBatterId;
  }
  ['runner_1b_id','runner_2b_id','runner_3b_id'].forEach(name => {
    filterSelectByTeam(playForm.querySelector(`[name="${name}"]`), teamId, false);
  });
}
function syncBatterDestination() {
  if (!playForm) return;
  const result = playForm.querySelector('[name="result"]')?.value || 'OUT';
  const destination = resultDestinationMap[result];
  const batterTo = playForm.querySelector('[name="batter_to"]');
  if (destination && batterTo) batterTo.value = destination;
  syncOutsDefault(result);
  syncOutDetailDefault(result);
  if (result === 'HR') syncHomeRunRunners();
  if (['WP','PB'].includes(result)) syncPitchAdvanceRunners();
  if (result === '1B') syncSingleAdvanceRunners();
  if (result === '3B') syncTripleAdvanceRunners();
  if (result === 'SF') syncSacrificeFlyRunners();
  if (result === 'E') syncErrorAdvanceRunners();
  syncExtraBaseAdvancement();
  updateBatterDestinationNote(result, destination || 'OUT');
}
function syncOutsDefault(result) {
  const outsInput = playForm?.querySelector('[name="outs_on_play"]');
  if (!outsInput) return;
  if (['OUT','SO','SF','SH'].includes(result) && Number(outsInput.value || 0) === 0) outsInput.value = 1;
  if (!['OUT','SO','SF','SH'].includes(result) && Number(outsInput.value || 0) === 1) outsInput.value = 0;
}
function syncOutDetailDefault(result) {
  const detailInput = playForm?.querySelector('[name="out_detail"]');
  const note = playForm?.querySelector('[data-out-detail-note]');
  if (!detailInput) return;
  const automaticValues = ['K', 'SF', 'SH'];
  if (result === 'SO' && (!detailInput.value || automaticValues.includes(detailInput.value))) detailInput.value = 'K';
  else if (result === 'SF' && (!detailInput.value || automaticValues.includes(detailInput.value))) detailInput.value = 'SF';
  else if (result === 'SH' && (!detailInput.value || automaticValues.includes(detailInput.value))) detailInput.value = 'SH';
  else if (!['OUT','SO','SF','SH','FC'].includes(result) && automaticValues.includes(detailInput.value)) detailInput.value = '';
  if (note) {
    if (['OUT','SO','SF','SH','FC'].includes(result)) {
      note.textContent = 'Ejemplos: K, F8, G6-3, 6-4, 6-4-3, SF, SH. Puedes escribir otro código.';
    } else {
      note.textContent = 'Este detalle se usa solo cuando la jugada produce out o fielder choice.';
    }
  }
}
function syncHomeRunRunners() {
  let scoringRunners = 1;
  ['1b','2b','3b'].forEach(base => {
    const runner = playForm?.querySelector(`[name="runner_${base}_id"]`);
    const destination = playForm?.querySelector(`[name="runner_${base}_to"]`);
    if (runner?.value && destination) {
      destination.value = 'H';
      scoringRunners++;
    }
  });
  const runsInput = playForm?.querySelector('[name="runs_scored"]');
  const rbiInput = playForm?.querySelector('[name="rbi"]');
  if (runsInput) runsInput.value = scoringRunners;
  if (rbiInput) rbiInput.value = scoringRunners;
}
function syncPitchAdvanceRunners() {
  const advances = [
    ['1b', ['2B','3B','OUT'], '2B'],
    ['2b', ['3B','OUT'], '3B'],
    ['3b', ['3B','OUT','STAY'], '3B']
  ];
  advances.forEach(([base, allowed, fallback]) => {
    const runner = playForm?.querySelector(`[name="runner_${base}_id"]`);
    const destination = playForm?.querySelector(`[name="runner_${base}_to"]`);
    if (runner?.value && destination && !allowed.includes(destination.value)) destination.value = fallback;
  });
  const runsInput = playForm?.querySelector('[name="runs_scored"]');
  const rbiInput = playForm?.querySelector('[name="rbi"]');
  if (runsInput) runsInput.value = 0;
  if (rbiInput) rbiInput.value = 0;
}
function syncForcedAdvanceRunners() {
  const result = playForm?.querySelector('[name="result"]')?.value || '';
  if (!['BB','HBP'].includes(result)) return;
  const runner1 = playForm?.querySelector('[name="runner_1b_id"]');
  const runner2 = playForm?.querySelector('[name="runner_2b_id"]');
  const runner3 = playForm?.querySelector('[name="runner_3b_id"]');
  const runner1To = playForm?.querySelector('[name="runner_1b_to"]');
  const runner2To = playForm?.querySelector('[name="runner_2b_to"]');
  const runner3To = playForm?.querySelector('[name="runner_3b_to"]');
  if (runner1?.value && runner1To && ['', 'STAY', '1B'].includes(runner1To.value)) runner1To.value = '2B';
  if (runner1?.value && runner2?.value && runner2To && ['', 'STAY', '2B'].includes(runner2To.value)) runner2To.value = '3B';
  if (runner1?.value && runner2?.value && runner3?.value && runner3To && ['', 'STAY', '3B'].includes(runner3To.value)) {
    runner3To.value = 'H';
    const runsInput = playForm?.querySelector('[name="runs_scored"]');
    const rbiInput = playForm?.querySelector('[name="rbi"]');
    if (runsInput) runsInput.value = Math.max(Number(runsInput.value || 0), 1);
    if (rbiInput) rbiInput.value = Math.max(Number(rbiInput.value || 0), 1);
  } else if (runner3?.value && runner3To && ['', 'STAY'].includes(runner3To.value)) {
    runner3To.value = '3B';
  }
}
function syncErrorAdvanceRunners() {
  const runner1 = playForm?.querySelector('[name="runner_1b_id"]');
  const runner2 = playForm?.querySelector('[name="runner_2b_id"]');
  const runner3 = playForm?.querySelector('[name="runner_3b_id"]');
  const runner1To = playForm?.querySelector('[name="runner_1b_to"]');
  const runner2To = playForm?.querySelector('[name="runner_2b_to"]');
  const runner3To = playForm?.querySelector('[name="runner_3b_to"]');
  if (runner1?.value && runner1To && ['', 'STAY', '1B'].includes(runner1To.value)) runner1To.value = '2B';
  if (runner1?.value && runner2?.value && runner2To && ['', 'STAY', '2B'].includes(runner2To.value)) runner2To.value = '3B';
  if (runner1?.value && runner2?.value && runner3?.value && runner3To && ['', 'STAY', '3B'].includes(runner3To.value)) {
    runner3To.value = 'H';
    const runsInput = playForm?.querySelector('[name="runs_scored"]');
    const rbiInput = playForm?.querySelector('[name="rbi"]');
    if (runsInput) runsInput.value = Math.max(Number(runsInput.value || 0), 1);
    if (rbiInput) rbiInput.value = 0;
  } else if (runner3?.value && runner3To && ['', 'STAY'].includes(runner3To.value)) {
    runner3To.value = '3B';
  }
}
function syncSingleAdvanceRunners() {
  const runner1 = playForm?.querySelector('[name="runner_1b_id"]');
  const runner2 = playForm?.querySelector('[name="runner_2b_id"]');
  const runner3 = playForm?.querySelector('[name="runner_3b_id"]');
  const runner1To = playForm?.querySelector('[name="runner_1b_to"]');
  const runner2To = playForm?.querySelector('[name="runner_2b_to"]');
  const runner3To = playForm?.querySelector('[name="runner_3b_to"]');
  if (runner1?.value && runner1To && ['', 'STAY', '1B'].includes(runner1To.value)) runner1To.value = '2B';
  if (runner2?.value && runner2To && ['', 'STAY', '2B'].includes(runner2To.value)) runner2To.value = '3B';
  if (runner3?.value && runner3To && ['', 'STAY', '3B'].includes(runner3To.value)) {
    runner3To.value = 'H';
    const runsInput = playForm?.querySelector('[name="runs_scored"]');
    const rbiInput = playForm?.querySelector('[name="rbi"]');
    if (runsInput) runsInput.value = Math.max(Number(runsInput.value || 0), 1);
    if (rbiInput) rbiInput.value = Math.max(Number(rbiInput.value || 0), 1);
  }
}
function syncTripleAdvanceRunners() {
  let scoringRunners = 0;
  ['1b','2b','3b'].forEach(base => {
    const runner = playForm?.querySelector(`[name="runner_${base}_id"]`);
    const destination = playForm?.querySelector(`[name="runner_${base}_to"]`);
    if (runner?.value && destination && ['', 'STAY', '1B', '2B', '3B'].includes(destination.value)) {
      destination.value = 'H';
      scoringRunners++;
    }
  });
  const runsInput = playForm?.querySelector('[name="runs_scored"]');
  const rbiInput = playForm?.querySelector('[name="rbi"]');
  if (runsInput) runsInput.value = Math.max(Number(runsInput.value || 0), scoringRunners);
  if (rbiInput) rbiInput.value = Math.max(Number(rbiInput.value || 0), scoringRunners);
}
function syncSacrificeFlyRunners() {
  const runner3 = playForm?.querySelector('[name="runner_3b_id"]');
  const runner3To = playForm?.querySelector('[name="runner_3b_to"]');
  if (runner3?.value && runner3To && ['', 'STAY', '3B'].includes(runner3To.value)) {
    runner3To.value = 'H';
    const runsInput = playForm?.querySelector('[name="runs_scored"]');
    const rbiInput = playForm?.querySelector('[name="rbi"]');
    if (runsInput) runsInput.value = Math.max(Number(runsInput.value || 0), 1);
    if (rbiInput) rbiInput.value = Math.max(Number(rbiInput.value || 0), 1);
  }
}
function forceDetailKind() {
  const detail = (playForm?.querySelector('[name="out_detail"]')?.value || '').toUpperCase().trim();
  if (['6-4','4-6','5-4','5-2','1-2','3-2'].includes(detail)) return 'force';
  if (['6-4-3','4-6-3','5-4-3','5-2-3','1-2-3','3-2-3'].includes(detail)) return 'dp';
  return '';
}
function forceOutBase() {
  const detail = (playForm?.querySelector('[name="out_detail"]')?.value || '').toUpperCase().trim();
  if (['6-4','4-6','5-4','6-4-3','4-6-3','5-4-3'].includes(detail)) return '1B';
  if (['5-2','1-2','3-2','5-2-3','1-2-3','3-2-3'].includes(detail)) return '3B';
  return '';
}
function syncForceOrDoublePlay() {
  const kind = forceDetailKind();
  if (!kind) return;
  const outBase = forceOutBase();
  const batterTo = playForm?.querySelector('[name="batter_to"]');
  const runner1 = playForm?.querySelector('[name="runner_1b_id"]');
  const runner2 = playForm?.querySelector('[name="runner_2b_id"]');
  const runner3 = playForm?.querySelector('[name="runner_3b_id"]');
  const runner1To = playForm?.querySelector('[name="runner_1b_to"]');
  const runner2To = playForm?.querySelector('[name="runner_2b_to"]');
  const runner3To = playForm?.querySelector('[name="runner_3b_to"]');
  const outsInput = playForm?.querySelector('[name="outs_on_play"]');
  const currentOutsInput = playForm?.querySelector('[name="current_outs"]');
  const runsInput = playForm?.querySelector('[name="runs_scored"]');
  const rbiInput = playForm?.querySelector('[name="rbi"]');
  const inningEndsOnPlay = (Number(currentOutsInput?.value || 0) + (kind === 'dp' ? 2 : 1)) >= 3;
  if (kind === 'force') {
    if (batterTo) batterTo.value = '1B';
    if (outBase === '1B' && runner1?.value && runner1To) runner1To.value = 'OUT';
    if (outBase === '3B') {
      if (runner3?.value && runner3To) runner3To.value = 'OUT';
      if (runner2?.value && runner2To && ['', 'STAY', '2B'].includes(runner2To.value)) runner2To.value = '3B';
      if (runner1?.value && runner1To && ['', 'STAY', '1B'].includes(runner1To.value)) runner1To.value = '2B';
    }
    if (outsInput) outsInput.value = Math.max(Number(outsInput.value || 0), 1);
  }
  if (kind === 'dp') {
    if (batterTo) batterTo.value = 'OUT';
    if (outBase === '1B') {
      if (runner1?.value && runner1To) runner1To.value = 'OUT';
      if (!inningEndsOnPlay && runner2?.value && runner2To && ['', 'STAY', '2B'].includes(runner2To.value)) runner2To.value = '3B';
      if (!inningEndsOnPlay && runner3?.value && runner3To && ['', 'STAY', '3B'].includes(runner3To.value)) {
        runner3To.value = 'H';
        if (runsInput) runsInput.value = Math.max(Number(runsInput.value || 0), 1);
      }
    }
    if (outBase === '3B') {
      if (runner3?.value && runner3To) runner3To.value = 'OUT';
      if (!inningEndsOnPlay && runner2?.value && runner2To && ['', 'STAY', '2B'].includes(runner2To.value)) runner2To.value = '3B';
      if (!inningEndsOnPlay && runner1?.value && runner1To && ['', 'STAY', '1B'].includes(runner1To.value)) runner1To.value = '2B';
    }
    if (outsInput) outsInput.value = Math.max(Number(outsInput.value || 0), 2);
    if (rbiInput) rbiInput.value = 0;
  }
}
function runnerFromFirstShortOnDouble() {
  const result = playForm?.querySelector('[name="result"]')?.value || '';
  const runner1 = playForm?.querySelector('[name="runner_1b_id"]');
  const destination = playForm?.querySelector('[name="runner_1b_to"]');
  if (result !== '2B' || !runner1?.value || !destination) return false;
  return ['', 'STAY', '1B', '2B'].includes(destination.value);
}
function syncExtraBaseAdvancement() {
  const alertBox = playForm?.querySelector('[data-advancement-alert]');
  if (!alertBox) return;
  const result = playForm?.querySelector('[name="result"]')?.value || '';
  const runner1 = playForm?.querySelector('[name="runner_1b_id"]');
  const destination = playForm?.querySelector('[name="runner_1b_to"]');
  if (['WP','PB'].includes(result)) {
    alertBox.textContent = 'WP/PB no embasa al bateador ni permite avance a Home en esta liga. Confirma solo avances de corredores a 2B o 3B.';
    alertBox.classList.add('active');
    return;
  }
  if (['BB','HBP'].includes(result)) {
    syncForcedAdvanceRunners();
    alertBox.textContent = 'BB/HBP: el bateador va a 1B. Los corredores avanzan solo si están forzados; con bases llenas anota el corredor de 3B.';
    alertBox.classList.add('active');
    return;
  }
  if (result === 'E') {
    syncErrorAdvanceRunners();
    alertBox.textContent = 'Error con bateador embasado: los corredores avanzan si están forzados. Con bases llenas anota el corredor de 3B, pero no se acredita RBI.';
    alertBox.classList.add('active');
    return;
  }
  if (result === '1B') {
    syncSingleAdvanceRunners();
    alertBox.textContent = 'Sencillo: el bateador queda en 1B. El corredor de 3B anota y el de 2B avanza mínimo a 3B; ajusta a Home u Out si la jugada lo requiere.';
    alertBox.classList.add('active');
    return;
  }
  if (result === '3B') {
    syncTripleAdvanceRunners();
    alertBox.textContent = 'Triple: el bateador queda en 3B y los corredores en base se sugieren anotados. Corrige solo si hubo out en una base o una jugada especial.';
    alertBox.classList.add('active');
    return;
  }
  if (result === 'SF') {
    syncSacrificeFlyRunners();
    alertBox.textContent = 'Fly de sacrificio: el bateador es out y el corredor de 3B anota por defecto. Ajusta si la defensa lo puso out o no avanzó.';
    alertBox.classList.add('active');
    return;
  }
  const forceKind = forceDetailKind();
  if (forceKind === 'force') {
    syncForceOrDoublePlay();
    alertBox.textContent = forceOutBase() === '3B'
      ? 'Jugada forzada en home: el corredor de 3B queda Out, el bateador llega a 1B y los otros corredores avanzan si estaban forzados.'
      : 'Jugada forzada en segunda: el corredor de 1B queda Out y el bateador llega a 1B por selección. Ajusta solo si el out fue en otra base.';
    alertBox.classList.add('active');
    return;
  }
  if (forceKind === 'dp') {
    syncForceOrDoublePlay();
    const currentOuts = Number(playForm?.querySelector('[name="current_outs"]')?.value || 0);
    const closesInning = currentOuts + 2 >= 3;
    alertBox.textContent = forceOutBase() === '3B'
      ? 'Doble play por home: el corredor de 3B y el bateador quedan Out. Los otros corredores avanzan solo si la entrada continúa.'
      : (closesInning
        ? 'Doble play por segunda y primera: el corredor de 1B y el bateador quedan Out. Como completa el tercer out, no se acredita carrera automática.'
        : 'Doble play por segunda y primera: el corredor de 1B y el bateador quedan Out. Con bases llenas, 2B pasa a 3B y 3B anota si no fue puesto Out.');
    alertBox.classList.add('active');
    return;
  }
  if (result === '2B' && runner1?.value && destination && ['', 'STAY', '1B', '2B'].includes(destination.value)) {
    destination.value = '3B';
    alertBox.textContent = 'Doble con corredor en 1B: el corredor debe llegar mínimo a 3B. Si anotó, cambia el destino a Home; si fue puesto out, cambia a Out.';
    alertBox.classList.add('active');
  } else if (result === '2B' && runner1?.value) {
    alertBox.textContent = 'Doble con corredor en 1B: revisa si el corredor llegó a 3B, anotó en Home o fue Out.';
    alertBox.classList.add('active');
  } else {
    alertBox.textContent = '';
    alertBox.classList.remove('active');
  }
}
function updateBatterDestinationNote(result, destination) {
  const note = playForm?.querySelector('[data-batter-destination-note]');
  if (!note) return;
  const destinationLabel = {
    '1B': 'primera base',
    '2B': 'segunda base',
    '3B': 'tercera base',
    'H': 'home',
    'OUT': 'out'
  }[destination] || 'out';
  if (['1B','2B','3B','HR','BB','HBP','E','FC'].includes(result)) {
    note.textContent = `Automático: el bateador queda en ${destinationLabel}. Si avanzó más en la jugada, cambia este destino.`;
    if (result === 'HR') note.textContent = 'Automático: jonrón, bateador y corredores anotan; las bases deben quedar vacías.';
  } else if (['WP','PB'].includes(result)) {
    note.textContent = 'Wild pitch / passed ball: no embasa al bateador ni cambia el turno; solo confirma avance de corredores a 2B o 3B.';
  } else {
    note.textContent = 'Destino automático según el resultado; puedes cambiarlo si la jugada lo requiere.';
  }
}
function selectedRunner(selectName) {
  const select = playForm?.querySelector(`[name="${selectName}"]`);
  if (!select || !select.value) return null;
  return { id: select.value, name: cleanRunnerName(select.options[select.selectedIndex]?.textContent || '') };
}
function selectedOptionText(name) {
  const field = playForm?.querySelector(`[name="${name}"]`);
  if (!field) return '';
  if (field.tagName === 'SELECT') return field.options[field.selectedIndex]?.textContent?.trim() || '';
  return field.value || '';
}
function destinationText(name, fallback) {
  const text = selectedOptionText(name);
  return text && text !== '-' ? text : fallback;
}
function updatePlayPreview() {
  const preview = playForm?.querySelector('[data-play-preview]');
  if (!preview) return;
  const batterCard = playForm.querySelector('.locked-batter strong')?.textContent?.trim();
  const batterSelect = playForm.querySelector('[data-batter-display]');
  const batterName = batterCard || cleanRunnerName(batterSelect?.options[batterSelect.selectedIndex]?.textContent || 'Bateador');
  const result = selectedOptionText('result') || 'Out';
  const batterTo = destinationText('batter_to', 'Out');
  const outs = playForm.querySelector('[name="outs_on_play"]')?.value || '0';
  const runs = playForm.querySelector('[name="runs_scored"]')?.value || '0';
  const rbi = playForm.querySelector('[name="rbi"]')?.value || '0';
  const runnerLines = [
    ['runner_1b_id', 'runner_1b_to', '1B'],
    ['runner_2b_id', 'runner_2b_to', '2B'],
    ['runner_3b_id', 'runner_3b_to', '3B']
  ].map(([runnerName, destinationName, base]) => {
    const runner = selectedRunner(runnerName);
    if (!runner) return '';
    return `${base}: ${runner.name} -> ${destinationText(destinationName, 'se queda')}`;
  }).filter(Boolean);
  const runners = runnerLines.length ? ` | Corredores: ${runnerLines.join('; ')}` : ' | Sin corredores en base';
  preview.textContent = `${batterName}: ${result}, bateador ${batterTo}. Outs: ${outs}. Carreras: ${runs}. RBI: ${rbi}.${runners}`;
}
function selectedDestination(selectName, fallback) {
  const value = playForm?.querySelector(`[name="${selectName}"]`)?.value || '';
  return value === 'STAY' || value === '' ? fallback : value;
}
function placeRunner(state, runner, destination) {
  if (!runner) return;
  ['1B','2B','3B'].forEach(base => {
    if (state[base]?.id === runner.id) state[base] = null;
  });
  if (['1B','2B','3B'].includes(destination)) state[destination] = runner;
}
function renderDiamond(state) {
  ['1B','2B','3B'].forEach(base => {
    const hasRunner = !!state[base];
    document.querySelector(`[data-live-base="${base}"]`)?.classList.toggle('active', hasRunner);
    const chip = document.querySelector(`[data-live-chip="${base}"]`);
    if (chip) {
      chip.classList.toggle('active', hasRunner);
      const span = chip.querySelector('span');
      if (span) span.textContent = hasRunner ? state[base].name : 'Vacía';
    }
  });
}
function updateDiamondPreview() {
  if (!playForm) return;
  const state = { '1B': null, '2B': null, '3B': null };
  const r1 = selectedRunner('runner_1b_id');
  const r2 = selectedRunner('runner_2b_id');
  const r3 = selectedRunner('runner_3b_id');
  placeRunner(state, r1, selectedDestination('runner_1b_to', '1B'));
  placeRunner(state, r2, selectedDestination('runner_2b_to', '2B'));
  placeRunner(state, r3, selectedDestination('runner_3b_to', '3B'));
  const batterSelect = playForm.querySelector('[data-batter-display]');
  const batter = batterSelect?.value ? { id: `b-${batterSelect.value}`, name: cleanRunnerName(batterSelect.options[batterSelect.selectedIndex]?.textContent || '') } : null;
  placeRunner(state, batter, playForm.querySelector('[name="batter_to"]')?.value || 'OUT');
  renderDiamond(state);
}
playForm?.querySelector('[name="batting_team_id"]')?.addEventListener('change', () => {
  applyTeamFilter();
  syncExtraBaseAdvancement();
  updateDiamondPreview();
});
playForm?.querySelector('[name="result"]')?.addEventListener('change', () => {
  syncBatterDestination();
  syncResultButtons();
  updatePlayPreview();
  updateDiamondPreview();
});
playForm?.querySelectorAll('[data-result-value]').forEach(button => {
  button.addEventListener('click', () => {
    const resultSelect = playForm.querySelector('[name="result"]');
    if (!resultSelect) return;
    const resultValue = button.getAttribute('data-result-value') || 'OUT';
    const outDetailValue = button.getAttribute('data-out-detail') || '';
    const outsValue = button.getAttribute('data-outs') || '';
    const noteValue = button.getAttribute('data-note') || '';
    resultSelect.value = resultValue;
    const detailInput = playForm.querySelector('[name="out_detail"]');
    if (detailInput && outDetailValue) detailInput.value = outDetailValue;
    const outsInput = playForm.querySelector('[name="outs_on_play"]');
    if (outsInput && outsValue) outsInput.value = outsValue;
    const notesInput = playForm.querySelector('[name="notes"]');
    if (notesInput && noteValue && !notesInput.value.includes(noteValue)) {
      notesInput.value = notesInput.value ? `${notesInput.value}; ${noteValue}` : noteValue;
    }
    syncBatterDestination();
    if (detailInput && outDetailValue) detailInput.value = outDetailValue;
    if (outsInput && outsValue) outsInput.value = outsValue;
    if (notesInput && noteValue && !notesInput.value.includes(noteValue)) {
      notesInput.value = notesInput.value ? `${notesInput.value}; ${noteValue}` : noteValue;
    }
    syncResultButtons();
    updatePlayPreview();
    updateDiamondPreview();
  });
});
playForm?.querySelectorAll('[data-adjust]').forEach(button => {
  button.addEventListener('click', () => {
    const amount = Number(button.dataset.amount || 1);
    const fieldName = button.dataset.adjust === 'rbi' ? 'rbi' : 'runs_scored';
    const field = playForm.querySelector(`[name="${fieldName}"]`);
    if (!field) return;
    field.value = Math.max(0, Number(field.value || 0) + amount);
    updatePlayPreview();
  });
});
playForm?.querySelectorAll('select,input').forEach(input => input.addEventListener('input', () => {
  syncExtraBaseAdvancement();
  updatePlayPreview();
  updateDiamondPreview();
}));
playForm?.querySelectorAll('select').forEach(input => input.addEventListener('change', () => {
  syncExtraBaseAdvancement();
  updatePlayPreview();
  updateDiamondPreview();
}));
playForm?.addEventListener('submit', event => {
  if (runnerFromFirstShortOnDouble()) {
    event.preventDefault();
    syncExtraBaseAdvancement();
    alert('Doble con corredor en primera: corrige el avance del corredor a 3B, Home u Out antes de guardar.');
    return;
  }
  const forcedOutBase = forceOutBase();
  if (forceDetailKind() && forcedOutBase === '1B' && !playForm?.querySelector('[name="runner_1b_id"]')?.value) {
    event.preventDefault();
    syncExtraBaseAdvancement();
    alert('Esta jugada de forzado/doble play por segunda necesita un corredor en 1B. Si el out fue sobre otro corredor, usa el botón correcto o el destino manual.');
    return;
  }
  if (forceDetailKind() && forcedOutBase === '3B' && !playForm?.querySelector('[name="runner_3b_id"]')?.value) {
    event.preventDefault();
    syncExtraBaseAdvancement();
    alert('Esta jugada de forzado/doble play por home necesita un corredor en 3B. Si el out fue sobre otro corredor, usa el botón correcto o el destino manual.');
  }
});
applyTeamFilter();
syncBatterDestination();
syncResultButtons();
syncExtraBaseAdvancement();
updatePlayPreview();
updateDiamondPreview();
})();
