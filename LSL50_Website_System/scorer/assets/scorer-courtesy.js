const courtesyRunnerForm = document.getElementById('courtesyRunnerForm');
function syncCourtesyRunnerOut() {
  const baseSelect = courtesyRunnerForm?.querySelector('[name="base"]');
  const outInput = courtesyRunnerForm?.querySelector('[name="runner_out_id"]');
  if (!baseSelect || !outInput) return;
  outInput.value = baseSelect.options[baseSelect.selectedIndex]?.dataset.runnerId || '';
}
courtesyRunnerForm?.querySelector('[name="base"]')?.addEventListener('change', syncCourtesyRunnerOut);
syncCourtesyRunnerOut();
