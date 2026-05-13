<?php
$title = "Print Log Absensi";
require_once __DIR__ . "/includes/functions.php";
require_login();
$u = current_user();

$from = $_GET["from"] ?? date("Y-m-01");
$to   = $_GET["to"] ?? date("Y-m-d");

$stmt = $mysqli->prepare("SELECT * FROM attendance WHERE user_id=? AND att_date BETWEEN ? AND ? ORDER BY att_date ASC");
$stmt->bind_param("iss", $u["id"], $from, $to);
$stmt->execute();
$att = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $mysqli->prepare("SELECT DATE(leave_date) AS leave_date, reason, description, status, decision_note FROM leave_requests
  WHERE user_id=? AND DATE(leave_date) BETWEEN ? AND ? ORDER BY leave_date ASC");
$stmt->bind_param("iss", $u["id"], $from, $to);
$stmt->execute();
$leave = [];
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $leave[$r["leave_date"]] = $r;

$ay_id = get_active_academic_year_id($mysqli);
$workday_set = array_flip(list_workday_dates($mysqli, $from, $to, $ay_id));
$dates = date_range_inclusive($from, $to);
$att_by_date = [];
foreach ($att as $a) $att_by_date[$a["att_date"]] = $a;

$rows = [];
foreach ($dates as $d) {
  $a = $att_by_date[$d] ?? null;
  $l = $leave[$d] ?? null;
  $holiday = is_holiday($mysqli, $d, $ay_id);
  $is_workday = isset($workday_set[$d]) ? 1 : 0;

  $status = "OFF";
  if ($holiday) $status = "LIBUR";
  elseif ($is_workday) {
    if ($l && ($l["status"]==="APPROVED")) $status = "IJIN";
    elseif ($l && ($l["status"]==="PENDING")) $status = "IJIN (PENDING)";
    elseif ($l && ($l["status"]==="REJECTED")) $status = "TANPA KETERANGAN";
    elseif ($a) $status = "HADIR";
    else $status = "TANPA KETERANGAN";
  }

  $rows[] = [$d,$status,$a,$l,$holiday];
}

$school = get_school_profile($mysqli);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title><?= e($title) ?></title>
  <style>
    body{font-family: Arial, sans-serif; font-size:12px; color:#111;}
    .kop{display:flex; gap:14px; align-items:center; border-bottom:2px solid #111; padding-bottom:10px; margin-bottom:12px;}
    .kop img{width:60px; height:60px; object-fit:contain;}
    .kop .t1{font-size:16px; font-weight:700;}
    .kop .t2{font-size:12px;}
    h2{font-size:14px; margin:0 0 8px 0;}
    table{width:100%; border-collapse:collapse;}
    th,td{border:1px solid #333; padding:6px; vertical-align:top;}
    th{background:#f2f2f2;}
    .muted{color:#666;}
    @media print { .noprint{display:none;} }
  </style>
</head>
<body onload="window.print()">

<div class="kop">
  <?php if (!empty($school["logo_path"])): ?>
    <img src="<?= e($school["logo_path"]) ?>" alt="logo">
  <?php endif; ?>
  <div>
    <div class="t1"><?= e($school["school_name"] ?? "Sekolah") ?></div>
    <div class="t2"><?= e($school["school_address"] ?? "") ?></div>
    <div class="t2 muted"><?= e($school["school_phone"] ?? "") ?></div>
  </div>
</div>

<h2>Log Absensi: <?= e($u["full_name"]) ?></h2>
<div class="muted">Periode: <?= e($from) ?> s/d <?= e($to) ?></div>

<table style="margin-top:10px;">
  <thead>
    <tr>
      <th style="width:120px;">Tanggal</th>
      <th style="width:140px;">Status</th>
      <th style="width:80px;">Masuk</th>
      <th style="width:80px;">Pulang</th>
      <th>Keterangan</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach($rows as $x):
      [$d,$status,$a,$l,$holiday] = $x;
    ?>
    <tr>
      <td><?= e($d) ?></td>
      <td><?= e($status) ?></td>
      <td><?= $a && $a["checkin_at"] ? e(date("H:i", strtotime($a["checkin_at"]))) : "-" ?></td>
      <td><?= $a && $a["checkout_at"] ? e(date("H:i", strtotime($a["checkout_at"]))) : "-" ?></td>
      <td>
        <?php if ($holiday): ?>
          Libur: <?= e($holiday) ?>
        <?php elseif ($l): ?>
          Ijin (<?= e($l["status"]) ?>): <?= e($l["reason"]) ?><?= !empty($l["description"]) ? " — Ket: " . e($l["description"]) : "" ?><?= !empty($l["decision_note"]) ? " — Catatan: " . e($l["decision_note"]) : "" ?>
        <?php elseif ($a): ?>
          <?= !empty($a["status_in"]) ? "Masuk: ".e(att_code_label($a["status_in"])).". " : "" ?>
          <?= !empty($a["status_out"]) ? "Pulang: ".e(att_code_label($a["status_out"]))."." : "" ?>
        <?php else: ?>
          -
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

</body>
</html>
