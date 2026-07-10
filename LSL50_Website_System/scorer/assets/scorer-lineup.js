const requiredLineupPositions = ['P','C','1B','2B','3B','SS','SF','LF','CF','CR','RF'];
function validateLineupForm(form) {
  const rows = Array.from(form.querySelectorAll('.lineup-row'));
  const playerMap = new Map();
  const positionMap = new Map();
  const positions = new Set();
  const errors = [];
  rows.forEach(row => row.classList.remove('invalid'));
  rows.forEach(row => {
    const player = row.querySelector('select[name$="[player_id]"]');
    const position = row.querySelector('select[name$="[field_position]"]');
    const order = row.querySelector('.order-badge')?.textContent?.trim() || '';
    const playerValue = player?.value || '';
    const positionValue = position?.value || '';
    if (!playerValue && positionValue) {
      errors.push(`Turno ${order}: posición sin jugador.`);
      row.classList.add('invalid');
    }
    if (playerValue && !positionValue) {
      errors.push(`Turno ${order}: falta posición.`);
      row.classList.add('invalid');
    }
    if (playerValue) {
      if (playerMap.has(playerValue)) {
        errors.push(`Jugador repetido en turnos ${playerMap.get(playerValue)} y ${order}.`);
        row.classList.add('invalid');
      } else {
        playerMap.set(playerValue, order);
      }
    }
    if (playerValue && positionValue) {
      positions.add(positionValue);
      if (positionValue !== 'DH' && positionMap.has(positionValue)) {
        errors.push(`Posición ${positionValue} repetida.`);
        row.classList.add('invalid');
      } else {
        positionMap.set(positionValue, order);
      }
    }
  });
  requiredLineupPositions.forEach(position => {
    if (!positions.has(position)) errors.push(`Falta la posición ${position}.`);
  });
  const errorBox = form.querySelector('[data-lineup-error]');
  if (errorBox) {
    errorBox.textContent = errors.slice(0, 5).join(' ');
    errorBox.classList.toggle('active', errors.length > 0);
  }
  return errors.length === 0;
}
document.querySelectorAll('.lineup-form').forEach(form => {
  form.addEventListener('change', () => validateLineupForm(form));
  form.addEventListener('submit', event => {
    if (!validateLineupForm(form)) event.preventDefault();
  });
});
