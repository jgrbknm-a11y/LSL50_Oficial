(async ()=>{
  const tokenInput = document.getElementById('token');
  const btnSave = document.getElementById('btnSave');
  const textarea = document.getElementById('payload');
  const selectName = document.getElementById('dataset');

  selectName.addEventListener('change', async ()=>{
    const {data} = await (await fetch(`/api/load.php?name=${selectName.value}`)).json();
    textarea.value = JSON.stringify({name:selectName.value, data}, null, 2);
  });
  selectName.dispatchEvent(new Event('change'));

  btnSave.addEventListener('click', async ()=>{
    const payload = JSON.parse(textarea.value);
    const res = await fetch('/api/save.php', {
      method:'POST',
      headers:{
        'Content-Type':'application/json',
        'X-Admin-Token': tokenInput.value.trim()
      },
      body: JSON.stringify(payload)
    });
    const j = await res.json();
    alert(res.ok? 'Guardado: '+JSON.stringify(j): 'Error: '+JSON.stringify(j));
  });
})();
