<?php
// Footer settings (safe defaults)
$appName = function_exists('get_app_setting') ? get_app_setting('app_name','Absensi Sekolah') : 'Absensi Sekolah';
$footerText = function_exists('get_app_setting') ? (get_app_setting('footer_text','') ?? '') : '';
?>
</main>
<footer id="appFooter" class="py-4">
  <div class="container">
    <div class="text-center small text-secondary">
      <?= e(trim((string)($footerText ?? '')) !== '' ? ($footerText ?? '') : ('© ' . date('Y') . ' ' . ($appName ?? 'Absensi Sekolah'))) ?>
    </div>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function(){
  const el = document.getElementById('clockWIB');
  if(!el) return;
  const fmt = new Intl.DateTimeFormat('id-ID', {
    timeZone: 'Asia/Jakarta',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  });
  function tick(){ el.textContent = 'WIB ' + fmt.format(new Date()); }
  tick();
  setInterval(tick, 1000);
})();
</script>

</body>
</html>
