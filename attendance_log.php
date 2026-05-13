<?php
$title = "Log Absensi Saya";
require_once __DIR__ . "/includes/functions.php";
require_login();
$u = current_user();

$from = $_GET["from"] ?? date("Y-m-01");
$to   = $_GET["to"] ?? date("Y-m-d");

// ambil attendance dalam rentang
$stmt = $mysqli->prepare("SELECT * FROM attendance WHERE user_id=? AND att_date BETWEEN ? AND ? ORDER BY att_date DESC");
$stmt->bind_param("iss", $u["id"], $from, $to);
$stmt->execute();
$res = $stmt->get_result();
$att_by_date = [];
while ($row = $res->fetch_assoc()) { $att_by_date[$row["att_date"]] = $row; }

// ambil ijin dalam rentang (semua status)
$stmt = $mysqli->prepare("SELECT DATE(leave_date) AS leave_date, reason, description, status, decided_at, decision_note FROM leave_requests
  WHERE user_id=? AND DATE(leave_date) BETWEEN ? AND ? ORDER BY leave_date DESC");
$stmt->bind_param("iss", $u["id"], $from, $to);
$stmt->execute();
$res = $stmt->get_result();
$leave_by_date = [];
while ($row = $res->fetch_assoc()) { $leave_by_date[$row["leave_date"]] = $row; }

// tanggal-tanggal yang ditampilkan: gabungkan rentang + hari kerja (untuk alpha)
$ay_id = get_active_academic_year_id($mysqli);
$workday_set = array_flip(list_workday_dates($mysqli, $from, $to, $ay_id));
$all_dates = date_range_inclusive($from, $to);

$rows = [];
foreach ($all_dates as $d) {
  $a = $att_by_date[$d] ?? null;
  $l = $leave_by_date[$d] ?? null;

  $is_workday = isset($workday_set[$d]) ? 1 : 0;
  $holiday_name = is_holiday($mysqli, $d, $ay_id);

  // status display
  $status_label = "OFF";
  $status_badge = "secondary";

  if ($holiday_name) {
    $status_label = "LIBUR";
    $status_badge = "info";
  } elseif ($is_workday) {
    if ($l) {
      if (($l["status"] ?? "") === "APPROVED") { $status_label = "IJIN"; $status_badge = "primary"; }
      elseif (($l["status"] ?? "") === "PENDING") { $status_label = "IJIN (PENDING)"; $status_badge = "warning text-dark"; }
      elseif (($l["status"] ?? "") === "REJECTED") { $status_label = "TANPA KETERANGAN"; $status_badge = "danger"; }
      else { $status_label = "IJIN"; $status_badge = "primary"; }
    } elseif ($a) {
      $status_label = "HADIR";
      $status_badge = "success";
    } else {
      $status_label = "TANPA KETERANGAN";
      $status_badge = "danger";
    }
  }

  $rows[] = [
    "att_date" => $d,
    "is_workday" => $is_workday,
    "holiday_name" => $holiday_name,
    "leave" => $l,
    "att" => $a,
    "status_label" => $status_label,
    "status_badge" => $status_badge,
  ];
}

require_once __DIR__ . "/includes/header.php";
?>

<div class="d-flex justify-content-between align-items-center mb-2">
  <h5 class="mb-0">Log Absensi Saya</h5>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-success btn-sm" href="attendance_log_export.php?from=<?= e($from) ?>&to=<?= e($to) ?>"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
    <a class="btn btn-outline-primary btn-sm" target="_blank" href="attendance_log_print.php?from=<?= e($from) ?>&to=<?= e($to) ?>">Print</a>
  </div>
</div>

<form class="row g-2 mb-3">
  <div class="col-6">
    <label class="form-label small">Dari</label>
    <input type="date" class="form-control" name="from" value="<?= e($from) ?>">
  </div>
  <div class="col-6">
    <label class="form-label small">Sampai</label>
    <input type="date" class="form-control" name="to" value="<?= e($to) ?>">
  </div>
  <div class="col-12 d-grid">
    <button class="btn btn-primary">Tampilkan</button>
  </div>
</form>

<?php foreach ($rows as $r): 
  $a = $r["att"];
  $l = $r["leave"];
?>
  <div class="card mb-2">
    <div class="card-body">
      <div class="d-flex justify-content-between">
        <div>
          <div class="fw-bold"><?= e(indonesian_date($r["att_date"])) ?></div>
          <?php if ($r["holiday_name"]): ?>
            <div class="small text-secondary">Libur: <?= e($r["holiday_name"]) ?></div>
          <?php endif; ?>
        </div>
        <div class="text-end">
          <span class="badge bg-<?= e($r["status_badge"]) ?>"><?= e($r["status_label"]) ?></span>
        </div>
      </div>

      <?php if ($l): ?>
        <div class="small mt-2">
          <div><span class="text-secondary">Ijin:</span> <?= e($l["reason"] ?? "-") ?></div>
          <?php if (!empty($l["description"])): ?>
            <div><span class="text-secondary">Keterangan:</span> <?= e($l["description"]) ?></div>
          <?php endif; ?>
          <div><span class="text-secondary">Status:</span> <?= e($l["status"] ?? "-") ?></div>
          <?php if (!empty($l["decision_note"])): ?>
            <div><span class="text-secondary">Catatan:</span> <?= e($l["decision_note"]) ?></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($a): ?>
        <div class="row mt-2 small">
          <div class="col-6">
            <div class="text-secondary">Masuk</div>
            <div><?= $a["checkin_at"] ? e(date("H:i", strtotime($a["checkin_at"]))) : "-" ?></div>
            <?php if (!empty($a["status_in"])): ?>
              <span class="badge bg-<?= ($a["status_in"]==="LATE" ? "danger" : "success") ?>"><?= e(att_code_label($a["status_in"])) ?></span>
            <?php endif; ?>
          </div>
          <div class="col-6">
            <div class="text-secondary">Pulang</div>
            <div><?= $a["checkout_at"] ? e(date("H:i", strtotime($a["checkout_at"]))) : "-" ?></div>
            <?php if (!empty($a["status_out"])): ?>
              <span class="badge bg-<?= ($a["status_out"]==="EARLY" ? "warning text-dark" : "success") ?>"><?= e(att_code_label($a["status_out"])) ?></span>
            <?php endif; ?>
          </div>
        </div>

        
        <div class="mt-2 d-flex gap-2 flex-wrap">
          <?php if (!empty($a["checkin_photo_path"]) || (!empty($a["checkin_lat"]) && !empty($a["checkin_lng"]))): ?>
            <button type="button" class="btn btn-outline-secondary btn-sm"
              data-bs-toggle="modal" data-bs-target="#mapModal"
              data-title="Masuk - <?= e(indonesian_date($r['att_date'])) ?>"
              data-lat="<?= (float)($a['checkin_lat'] ?? 0) ?>"
              data-lng="<?= (float)($a['checkin_lng'] ?? 0) ?>"
              data-photo="<?= $BASE_URL . '/' . e($a['checkin_photo_path'] ?? '') ?>">
              <i class="bi bi-camera me-1"></i>Detail Masuk
            </button>
          <?php endif; ?>

          <?php if (!empty($a["checkout_photo_path"]) || (!empty($a["checkout_lat"]) && !empty($a["checkout_lng"]))): ?>
            <button type="button" class="btn btn-outline-secondary btn-sm"
              data-bs-toggle="modal" data-bs-target="#mapModal"
              data-title="Pulang - <?= e(indonesian_date($r['att_date'])) ?>"
              data-lat="<?= (float)($a['checkout_lat'] ?? 0) ?>"
              data-lng="<?= (float)($a['checkout_lng'] ?? 0) ?>"
              data-photo="<?= $BASE_URL . '/' . e($a['checkout_photo_path'] ?? '') ?>">
              <i class="bi bi-camera me-1"></i>Detail Pulang
            </button>
          <?php endif; ?>
        </div>

      <?php endif; ?>

    </div>
  </div>
<?php endforeach; ?>


<!-- Modal Foto & Peta -->
<div class="modal fade" id="mapModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title" id="mapTitle">Detail</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <img id="mapPhoto" src="" class="img-fluid rounded-3 border mb-3 d-none" alt="foto">
        <div id="mapWrap">
          <iframe id="osmFrame" style="width:100%;height:340px;" class="rounded-3 border" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
          <div class="small text-secondary mt-2" id="mapCoord"></div>
        </div>
        <div id="mapNoCoord" class="alert alert-secondary d-none mb-0">Lokasi tidak tersedia untuk data ini.</div>
      </div>
    </div>
  </div>
</div>


<script>
function showMap(title, lat, lng, photoUrl){
  document.getElementById("mapTitle").textContent = title || "Detail";
  const photoEl = document.getElementById("mapPhoto");
  const frame = document.getElementById("osmFrame");

  // foto
  if (photoUrl && typeof photoUrl === "string" && photoUrl.trim().length > 0 && !photoUrl.endsWith("/")) {
    photoEl.src = photoUrl;
    photoEl.classList.remove("d-none");
  } else {
    photoEl.src = "";
    photoEl.classList.add("d-none");
  }

  const no = document.getElementById("mapNoCoord");
  const wrap = document.getElementById("mapWrap");
  const coord = document.getElementById("mapCoord");

  const nlat = Number(lat);
  const nlng = Number(lng);
  if (isFinite(nlat) && isFinite(nlng) && nlat !== 0 && nlng !== 0) {
    no.classList.add("d-none");
    wrap.classList.remove("d-none");
    coord.textContent = `Koordinat: ${nlat.toFixed(6)}, ${nlng.toFixed(6)}`;

    const d = 0.003;
    const left = nlng - d, right = nlng + d, bottom = nlat - d, top = nlat + d;
    const bbox = encodeURIComponent(`${left},${bottom},${right},${top}`);
    const marker = encodeURIComponent(`${nlat},${nlng}`);
    frame.src = `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&layer=mapnik&marker=${marker}`;
  } else {
    wrap.classList.add("d-none");
    no.classList.remove("d-none");
    coord.textContent = "";
    frame.src = "about:blank";
  }
}

const mapModalEl = document.getElementById("mapModal");
mapModalEl.addEventListener("show.bs.modal", function (event) {
  const btn = event.relatedTarget;
  if (!btn) return;
  const title = btn.getAttribute("data-title") || "Detail";
  const lat = btn.getAttribute("data-lat") || "0";
  const lng = btn.getAttribute("data-lng") || "0";
  const photo = btn.getAttribute("data-photo") || "";
  showMap(title, lat, lng, photo);
});
</script>



<?php require_once __DIR__ . "/includes/footer.php"; ?>
