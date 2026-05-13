
// Small button click animation
document.addEventListener("click", (e) => {
  const btn = e.target.closest(".btn");
  if (!btn) return;
  btn.classList.remove("btn-press");
  void btn.offsetWidth; // reflow
  btn.classList.add("btn-press");
});
