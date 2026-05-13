<?php
$title = "Notifikasi";
require_once __DIR__ . "/includes/functions.php";
require_login();
$u = current_user();

if (isset($_GET["mark"]) && $_GET["mark"] === "all") {
  mark_all_notifications_read($mysqli, $u["id"]);
  flash_set("success","Notifikasi ditandai sudah dibaca.");
  $back = $_GET["back"] ?? "/dashboard.php";
  redirect($back);
}

$rows = [];
$stmt = $mysqli->prepare("SELECT id,title,message,is_read,created_at FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 100");
$stmt->bind_param("i", $u["id"]);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $rows[] = $r;

// mark displayed unread as read
$stmt = $mysqli->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?");
$stmt->bind_param("i", $u["id"]);
$stmt->execute();

require_once __DIR__ . "/includes/header.php";
?>
<div class="card card-soft p-3">
  <div class="d-flex justify-content-between align-items-center">
    <h5 class="fw-semibold mb-0"><i class="bi bi-bell me-1"></i>Notifikasi</h5>
    <a class="btn btn-sm btn-outline-secondary" href="dashboard.php">Kembali</a>
  </div>

  <div class="mt-3">
    <?php if (empty($rows)): ?>
      <div class="text-secondary">Belum ada notifikasi.</div>
    <?php else: ?>
      <div class="list-group">
        <?php foreach($rows as $n): ?>
          <div class="list-group-item">
            <div class="d-flex justify-content-between">
              <div>
                <div class="fw-semibold"><?= e($n["title"]) ?></div>
                <div><?= e($n["message"]) ?></div>
              </div>
              <div class="small text-secondary"><?= e(date("d/m/Y H:i", strtotime($n["created_at"]))) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . "/includes/footer.php"; ?>
