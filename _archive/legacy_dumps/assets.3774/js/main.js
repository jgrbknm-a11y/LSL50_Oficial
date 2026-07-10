async function fetchJSON(name){
  const res = await fetch(`/api/load.php?name=${encodeURIComponent(name)}`, {cache:'no-store'});
  if(!res.ok) throw new Error('Load failed');
  return await res.json();
}
function el(sel, root=document){ return root.querySelector(sel); }
function els(sel, root=document){ return Array.from(root.querySelectorAll(sel)); }
