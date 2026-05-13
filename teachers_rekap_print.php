<?php
$title = "Print Rekap Absensi Guru";
require_once __DIR__ . "/includes/functions.php";
require_login();
require_role(["ADMIN","KEPSEK","YAYASAN"]);
$me = current_user();

$from = $_GET["from"] ?? date("Y-m-01");
$to   = $_GET["to"] ?? date("Y-m-d");
$teacher_id = $_GET["teacher_id"] ?? "";

$ay_id = get_active_academic_year_id($mysqli);
$workdays = list_workday_dates($mysqli, $from, $to, $ay_id);
$school = get_school_profile($mysqli);

if ($teacher_id !== "") {
  // detail per guru (harian)
  $tid = (int)$teacher_id;
  $t = $mysqli->prepare("SELECT id,full_name,employee_no,role FROM users WHERE id=? AND role IN ('GURU','KEPSEK','YAYASAN') LIMIT 1");
  $t->bind_param("i", $tid);
  $t->execute();
  $teacher = $t->get_result()->fetch_assoc();
  if (!$teacher) { http_response_code(404); echo "Guru tidak ditemukan."; exit; }

  $stmt = $mysqli->prepare("SELECT * FROM attendance WHERE user_id=? AND att_date BETWEEN ? AND ? ORDER BY att_date ASC");
  $stmt->bind_param("iss", $tid, $from, $to);
  $stmt->execute();
  $att = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $att_by_date = [];
  foreach ($att as $a) $att_by_date[$a["att_date"]] = $a;

  $stmt = $mysqli->prepare("SELECT DATE(leave_date) AS leave_date, reason, status FROM leave_requests
    WHERE user_id=? AND DATE(leave_date) BETWEEN ? AND ? ORDER BY leave_date ASC");
  $stmt->bind_param("iss", $tid, $from, $to);
  $stmt->execute();
  $leave = [];
  $res = $stmt->get_result();
  while ($r = $res->fetch_assoc()) $leave[$r["leave_date"]] = $r;

  $dates = date_range_inclusive($from, $to);
  $workday_set = array_flip($workdays);

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

  ?>
  <!doctype html>
  <html><head>
    <meta charset="utf-8">
    <title><?= e($title) ?></title>
    <style>
      body{font-family: Arial, sans-serif; font-size:12px; color:#111;}
      .kop{display:flex; gap:14px; align-items:center; border-bottom:2px solid #111; padding-bottom:10px; margin-bottom:12px;}
      .kop img{width:60px; height:60px; object-fit:contain;}
      .kop .t1{font-size:16px; font-weight:700;}
      .kop .t2{font-size:12px;}
      h2{font-size:14px; margin:0 0 8px 0;}
      table{width:100%; border-collapse:collapse; margin-top:10px;}
      th,td{border:1px solid #333; padding:6px; vertical-align:top;}
      th{background:#f2f2f2;}
      .muted{color:#666;}
    </style>
  </head>
  <body onload="window.print()">
    <div class="kop">
      <?php if (!empty($school["logo_path"])): ?><img src="<?= e($school["logo_path"]) ?>" alt="logo"><?php endif; ?>
      <div>
        <div class="t1"><?= e($school["school_name"] ?? "Sekolah") ?></div>
        <div class="t2"><?= e($school["school_address"] ?? "") ?></div>
        <div class="t2 muted"><?= e($school["school_phone"] ?? "") ?></div>
      </div>
    </div>

    <h2>Detail Absensi Guru</h2>
    <div><b>Nama:</b> <?= e($teacher["full_name"]) ?> • <b>No Pegawai:</b> <?= e($teacher["employee_no"]) ?> • <b>Role:</b> <?= e(role_label($teacher["role"])) ?></div>
    <div class="muted">Periode: <?= e($from) ?> s/d <?= e($to) ?> • Total hari kerja: <?= (int)count($workdays) ?></div>

    <table>
      <thead>
        <tr>
          <th style="width:110px;">Tanggal</th>
          <th style="width:150px;">Status</th>
          <th style="width:70px;">Masuk</th>
          <th style="width:70px;">Pulang</th>
          <th>Keterangan</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $x): [$d,$status,$a,$l,$holiday] = $x; ?>
        <tr>
          <td><?= e($d) ?></td>
          <td><?= e($status) ?></td>
          <td><?= $a && $a["checkin_at"] ? e(date("H:i", strtotime($a["checkin_at"]))) : "-" ?></td>
          <td><?= $a && $a["checkout_at"] ? e(date("H:i", strtotime($a["checkout_at"]))) : "-" ?></td>
          <td>
            <?php if ($holiday): ?>Libur: <?= e($holiday) ?>
            <?php elseif ($l): ?>Ijin (<?= e($l["status"]) ?>): <?= e($l["reason"]) ?>
            <?php elseif ($a): ?>
              <?= !empty($a["status_in"]) ? "Masuk: ".e(att_code_label($a["status_in"])).". " : "" ?>
              <?= !empty($a["status_out"]) ? "Pulang: ".e(att_code_label($a["status_out"]))."." : "" ?>
              <?php if (!empty($a["lat_in"]) && !empty($a["lng_in"])): ?>
                • Lokasi: <?= e($a["lat_in"]) ?>,<?= e($a["lng_in"]) ?>
              <?php endif; ?>
            <?php else: ?>-
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </body></html>
  <?php
  exit;
}

// summary semua guru
$where = "";
$params = [$from,$to,$from,$to];
$types = "ssss";

$sql = "SELECT u.id,u.full_name,u.employee_no,u.role,
  COUNT(DISTINCT a.att_date) AS present_days,
  SUM(CASE WHEN a.status_in='LATE' THEN 1 ELSE 0 END) AS late_count,
  (SELECT COUNT(*) FROM leave_requests lr
     WHERE lr.user_id=u.id AND lr.status='APPROVED' AND DATE(lr.leave_date) BETWEEN ? AND ?) AS leave_count
  FROM users u
  LEFT JOIN attendance a ON a.user_id=u.id AND a.att_date BETWEEN ? AND ?
  WHERE u.status='ACTIVE' AND u.role='GURU' $where
  GROUP BY u.id
  ORDER BY u.full_name";

$stmt = $mysqli->prepare($sql);
stmt_bind_params($stmt, $types, $params);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) {
  $r["absent_count"] = count_absent_without_excuse($workdays, $r["present_days"], $r["leave_count"]);
  $rows[] = $r;
}
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
    table{width:100%; border-collapse:collapse; margin-top:10px;}
    th,td{border:1px solid #333; padding:6px; vertical-align:top;}
    th{background:#f2f2f2;}
    .muted{color:#666;}
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

<h2>Rekap Absensi Guru</h2>
<div class="muted">Periode: <?= e($from) ?> s/d <?= e($to) ?> • Total hari kerja: <?= (int)count($workdays) ?></div>

<table>
  <thead>
    <tr>
      <th style="width:26px;">No</th>
      <th>Nama</th>
      <th style="width:120px;">No Pegawai</th>
      <th style="width:70px;">Hadir</th>
      <th style="width:70px;">Telat</th>
      <th style="width:70px;">Ijin</th>
      <th style="width:90px;">Tanpa Ket.</th>
    </tr>
  </thead>
  <tbody>
    <?php $no=1; foreach($rows as $r): ?>
    <tr>
      <td><?= $no++ ?></td>
      <td><?= e($r["full_name"]) ?></td>
      <td><?= e($r["employee_no"]) ?></td>
      <td><?= (int)$r["present_days"] ?></td>
      <td><?= (int)$r["late_count"] ?></td>
      <td><?= (int)$r["leave_count"] ?></td>
      <td><?= (int)$r["absent_count"] ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

</body>
</html>
