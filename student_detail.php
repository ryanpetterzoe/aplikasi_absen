<?php
require_once __DIR__ . "/includes/functions.php";
require_login();
require_role(["ADMIN","GURU","KEPSEK","YAYASAN"]);

$u = current_user();

$target_id = (int)($_GET["id"] ?? 0);
$from = $_GET["from"] ?? date("Y-m-01");
$to = $_GET["to"] ?? date("Y-m-d");

if ($target_id <= 0) { flash_set("warning","ID tidak valid."); redirect("/dashboard.php"); }

// Ambil target (hanya siswa)
$stmt = $mysqli->prepare("SELECT u.id,u.full_name,u.role,u.photo_path,u.class_id,u.academic_year_id,
  c.name AS class_name
  FROM users u
  LEFT JOIN classes c ON c.id=u.class_id
  WHERE u.id=? AND u.role='SISWA' LIMIT 1");
$stmt->bind_param("i", $target_id);
$stmt->execute();
$target = $stmt->get_result()->fetch_assoc();
if (!$target) { flash_set("warning","Siswa tidak ditemukan."); redirect("/dashboard.php"); }

// Guru: boleh lihat semua siswa (sesuai request terbaru)
if ($u["role"]==="GURU") {
  // tidak ada pembatasan wali kelas
}

// Ambil attendance by date
$stmt = $mysqli->prepare("SELECT * FROM attendance WHERE user_id=? AND att_date BETWEEN ? AND ?");
$stmt->bind_param("iss", $target_id, $from, $to);
$stmt->execute();
$res = $stmt->get_result();
$att_by_date = [];
while ($row = $res->fetch_assoc()) $att_by_date[$row["att_date"]] = $row;

// Ambil ijin by date (include keterangan)
$stmt = $mysqli->prepare("SELECT DATE(leave_date) AS leave_date, reason, description, status, decision_note
  FROM leave_requests WHERE user_id=? AND DATE(leave_date) BETWEEN ? AND ? ORDER BY leave_date DESC");
$stmt->bind_param("iss", $target_id, $from, $to);
$stmt->execute();
$leave_by_date = [];
$res2 = $stmt->get_result();
while ($lr = $res2->fetch_assoc()) $leave_by_date[$lr["leave_date"]] = $lr;

// Bangun daftar tanggal (terbaru dulu) dan status untuk setiap hari kerja + libur + hari yg ada record
$ay_id = (int)($target["academic_year_id"] ?? get_active_academic_year_id($mysqli));
$workdays = list_workday_dates($mysqli, $from, $to, $ay_id);
$workday_set = array_fill_keys($workdays, true);

$dates = [];
$from_dt = new DateTime($from);
$to_dt = new DateTime($to);
while ($from_dt <= $to_dt) { $dates[] = $from_dt->format("Y-m-d"); $from_dt->modify("+1 day"); }
$dates = array_reverse($dates);

$rows = [];
foreach ($dates as $d) {
  $a = $att_by_date[$d] ?? null;
  $l = $leave_by_date[$d] ?? null;
  $holiday = is_holiday($mysqli, $d, $ay_id);
  $is_workday = isset($workday_set[$d]) ? 1 : 0;

  $status_label = "OFF";
  $badge = "secondary";

  if ($holiday) { $status_label="LIBUR"; $badge="info"; }
  elseif ($is_workday) {
    if ($l) {
      if (($l["status"]??"")==="APPROVED") { $status_label="IJIN"; $badge="primary"; }
      elseif (($l["status"]??"")==="PENDING") { $status_label="IJIN (PENDING)"; $badge="warning text-dark"; }
      elseif (($l["status"]??"")==="REJECTED") { $status_label="TANPA KETERANGAN"; $badge="danger"; }
      else { $status_label="IJIN"; $badge="primary"; }
    } elseif ($a) { $status_label="HADIR"; $badge="success"; }
    else { $status_label="TANPA KETERANGAN"; $badge="danger"; }
  }

  // tampilkan jika: hari kerja/libur ATAU ada record attendance/ijin
  if ($is_workday || $holiday || $a || $l) {
    $rows[] = ["d"=>$d, "att"=>$a, "leave"=>$l, "holiday"=>$holiday, "status"=>$status_label, "badge"=>$badge];
  }
}

require_once __DIR__ . "/includes/header.php";
?>
<div class="card card-soft p-3">
  <div class="d-flex align-items-center justify-content-between">
    <div>
      <h5 class="fw-semibold mb-0"><i class="bi bi-search me-1"></i>Detail Absensi</h5>
      <div class="text-secondary small"><?= e($target["full_name"]) ?> • <?= e(role_label($target["role"])) ?> • <?= e($target["class_name"] ?? "-") ?></div>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-sm btn-outline-success" href="student_detail_export.php?id=<?= (int)$target_id ?>&from=<?= e($from) ?>&to=<?= e($to) ?>"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
      <a class="btn btn-sm btn-outline-secondary" href="javascript:history.back()">Kembali</a>
    </div>
  </div>

  <div class="table-responsive mt-3">
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Masuk</th>
          <th>Status</th>
          <th>Pulang</th>
          <th>Status</th>
          <th>Ijin</th>
          <th class="text-end">Detail</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): 
          $a = $r["att"];
          $l = $r["leave"];
          $d = $r["d"];
        ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= e(indonesian_date($d)) ?></div>
              <div class="small">
                <span class="badge bg-<?= e($r["badge"]) ?>"><?= e($r["status"]) ?></span>
                <?php if (!empty($r["holiday"])): ?>
                  <span class="text-secondary">• <?= e($r["holiday"]) ?></span>
                <?php endif; ?>
              </div>
            </td>

            <td><?= $a && $a["checkin_at"] ? e(date("H:i", strtotime($a["checkin_at"]))) : "-" ?></td>
            <td>
              <?php if ($a && !empty($a["status_in"])): ?>
                <span class="badge bg-<?= in_array(($a["status_in"]??""), ["TELAT","LATE"], true) ? "warning text-dark":"success" ?>"><?= e(att_code_label($a["status_in"])) ?></span>
              <?php else: ?>
                -
              <?php endif; ?>
            </td>

            <td><?= $a && $a["checkout_at"] ? e(date("H:i", strtotime($a["checkout_at"]))) : "-" ?></td>
            <td>
              <?php if ($a && !empty($a["status_out"])): ?>
                <span class="badge bg-<?= in_array(($a["status_out"]??""), ["PULANG LEBIH AWAL","EARLY"], true) ? "warning text-dark":"success" ?>"><?= e(att_code_label($a["status_out"])) ?></span>
                <?php if (!empty($a["note_out"])): ?>
                  <div class="small text-secondary"><?= e($a["note_out"]) ?></div>
                <?php endif; ?>
              <?php else: ?>
                -
              <?php endif; ?>
            </td>

            <td>
              <?php if ($l): ?>
                <span class="badge bg-<?= $l["status"]==="APPROVED" ? "primary" : ($l["status"]==="PENDING" ? "warning text-dark" : "secondary") ?>">
                  <?= e($l["status"]) ?>
                </span>
                <div class="small text-secondary"><?= e($l["reason"] ?? "-") ?></div>
                <?php if (!empty($l["description"])): ?>
                  <div class="small text-secondary">Ket: <?= e($l["description"]) ?></div>
                <?php endif; ?>
                <?php if (!empty($l["decision_note"])): ?>
                  <div class="small text-secondary">Catatan: <?= e($l["decision_note"]) ?></div>
                <?php endif; ?>
              <?php else: ?>
                -
              <?php endif; ?>
            </td>

            <td class="text-end">
              <?php if ($a && (!empty($a["checkin_photo_path"]) || (!empty($a["checkin_lat"]) && !empty($a["checkin_lng"])))): ?>
                <button class="btn btn-sm btn-outline-primary mb-1" type="button"
                  onclick="openDetail('Masuk - <?= e(indonesian_date($d)) ?>','<?= e($BASE_URL . '/' . ($a["checkin_photo_path"] ?? "")) ?>','<?= e($a["checkin_lat"] ?? "") ?>','<?= e($a["checkin_lng"] ?? "") ?>')">
                  <i class="bi bi-camera me-1"></i>Masuk
                </button>
              <?php endif; ?>
              <?php if ($a && (!empty($a["checkout_photo_path"]) || (!empty($a["checkout_lat"]) && !empty($a["checkout_lng"])))): ?>
                <button class="btn btn-sm btn-outline-secondary" type="button"
                  onclick="openDetail('Pulang - <?= e(indonesian_date($d)) ?>','<?= e($BASE_URL . '/' . ($a["checkout_photo_path"] ?? "")) ?>','<?= e($a["checkout_lat"] ?? "") ?>','<?= e($a["checkout_lng"] ?? "") ?>')">
                  <i class="bi bi-camera me-1"></i>Pulang
                </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal detail -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title" id="detailTitle">Detail</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="detailPhotoWrap" class="mb-2"></div>
        <div id="detailMapWrap" style="height:280px;border-radius:12px;overflow:hidden;border:1px solid rgba(10,40,80,.15)"></div>
      </div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
let mapObj=null, markerObj=null;
function openDetail(title, photoUrl, lat, lng){
  document.getElementById('detailTitle').innerText = title;
  const wrap = document.getElementById('detailPhotoWrap');
  wrap.innerHTML = '';
  if(photoUrl && !photoUrl.endsWith('/')){
    wrap.innerHTML = `<img src="${photoUrl}" style="width:100%;max-height:360px;object-fit:cover;border-radius:14px;border:1px solid rgba(10,40,80,.15)">`;
  }
  const mapWrap = document.getElementById('detailMapWrap');
  mapWrap.innerHTML = '';
  if(lat && lng){
    mapObj = L.map('detailMapWrap').setView([parseFloat(lat), parseFloat(lng)], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19}).addTo(mapObj);
    markerObj = L.marker([parseFloat(lat), parseFloat(lng)]).addTo(mapObj);
  } else {
    mapWrap.innerHTML = `<div class="text-secondary small p-3">Lokasi tidak tersedia.</div>`;
  }
  const m = new bootstrap.Modal(document.getElementById('detailModal'));
  m.show();
  setTimeout(()=>{ if(mapObj) mapObj.invalidateSize(); }, 400);
}
</script>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
