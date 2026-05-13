<?php
require_once __DIR__ . "/includes/functions.php";
require_login();
require_role("GURU");

$u = current_user();

$from = $_GET["from"] ?? date("Y-m-01");
$to = $_GET["to"] ?? date("Y-m-d");
$student_id = (int)($_GET["student_id"] ?? 0);
if ($student_id<=0) die("student_id tidak valid");

$school = $mysqli->query("SELECT * FROM school_profile WHERE id=1")->fetch_assoc();

$stmt = $mysqli->prepare("SELECT u.id,u.full_name,u.nisn,u.photo_path,u.class_id,u.academic_year_id,
  c.name AS class_name, ay.name AS year_name
  FROM users u
  LEFT JOIN classes c ON c.id=u.class_id
  LEFT JOIN academic_years ay ON ay.id=u.academic_year_id
  WHERE u.id=? AND u.role='SISWA' LIMIT 1");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
if (!$student) die("Siswa tidak ditemukan.");

// attendance by date
$stmt = $mysqli->prepare("SELECT * FROM attendance WHERE user_id=? AND att_date BETWEEN ? AND ?");
$stmt->bind_param("iss", $student_id, $from, $to);
$stmt->execute();
$att_by_date = [];
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $att_by_date[$row["att_date"]] = $row;

// leave by date
$stmt = $mysqli->prepare("SELECT DATE(leave_date) AS leave_date, reason, description, status, decision_note
  FROM leave_requests WHERE user_id=? AND DATE(leave_date) BETWEEN ? AND ? ORDER BY leave_date ASC");
$stmt->bind_param("iss", $student_id, $from, $to);
$stmt->execute();
$leave_by_date = [];
$res2 = $stmt->get_result();
while ($lr = $res2->fetch_assoc()) $leave_by_date[$lr["leave_date"]] = $lr;

$ay_id = (int)($student["academic_year_id"] ?? get_active_academic_year_id($mysqli));
$workdays = list_workday_dates($mysqli, $from, $to, $ay_id);
$workday_set = array_fill_keys($workdays, true);

// build rows by date (asc)
$dates = [];
$from_dt = new DateTime($from);
$to_dt = new DateTime($to);
while ($from_dt <= $to_dt) { $dates[] = $from_dt->format("Y-m-d"); $from_dt->modify("+1 day"); }

$rows = [];
$cnt_hadir=0; $cnt_telat=0; $cnt_ijin=0; $cnt_tanpa=0;
foreach ($dates as $d) {
  $a = $att_by_date[$d] ?? null;
  $l = $leave_by_date[$d] ?? null;
  $holiday = is_holiday($mysqli, $d, $ay_id);
  $is_workday = isset($workday_set[$d]) ? 1 : 0;

  // hanya hari kerja (dan libur untuk konteks)
  if (!$is_workday && !$holiday && !$a && !$l) continue;

  $status = "OFF";
  if ($holiday) $status = "LIBUR";
  elseif ($is_workday) {
    if ($l) {
      if (($l["status"]??"")==="APPROVED") { $status="IJIN"; $cnt_ijin++; }
      elseif (($l["status"]??"")==="PENDING") { $status="IJIN (PENDING)"; }
      elseif (($l["status"]??"")==="REJECTED") { $status="TANPA KETERANGAN"; $cnt_tanpa++; }
      else { $status="IJIN"; $cnt_ijin++; }
    } elseif ($a) {
      $status="HADIR"; $cnt_hadir++;
      if (($a["status_in"]??"")==="TELAT") $cnt_telat++;
    } else {
      $status="TANPA KETERANGAN"; $cnt_tanpa++;
    }
  }

  $rows[] = ["d"=>$d, "att"=>$a, "leave"=>$l, "holiday"=>$holiday, "status"=>$status];
}

?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Surat Rekap Absensi</title>
  <style>
    body{ font-family: Arial, sans-serif; color:#111; }
    .wrap{ max-width: 900px; margin: 24px auto; padding: 10px; }
    .kop{ display:flex; gap:16px; align-items:center; border-bottom:2px solid #111; padding-bottom:10px; }
    .kop-text{ flex:1; text-align:center; }
    .kop-name{ font-size:16px; font-weight:bold; text-transform:uppercase; }
    .kop-line{ font-size:12px; }
    .letter-meta{ margin-top:10px; text-align:right; font-size:12px; }
    .kop img{ width:64px; height:64px; object-fit:contain; }
    h1{ font-size: 18px; margin: 18px 0 6px; text-align:center; }
    .sub{ text-align:center; font-size: 12px; margin-bottom: 16px; }
    table{ width:100%; border-collapse:collapse; font-size: 12px; }
    th,td{ border:1px solid #222; padding:6px; vertical-align:top; }
    th{ background:#f1f3f5; }
    .meta td{ border:0; padding:3px; }
    .ttd{ margin-top: 28px; display:flex; justify-content: flex-end; }
    .ttd .box{ width: 260px; text-align:center; }
    .small{ font-size:11px; color:#444; }
  </style>
</head>
<body>
<div class="wrap">
  <div class="kop">
    <?php if (!empty($school["logo_path"]) && file_exists(__DIR__ . "/" . $school["logo_path"])): ?>
      <img src="<?= e($school["logo_path"]) ?>" alt="logo">
    <?php else: ?>
      <div style="width:64px;height:64px"></div>
    <?php endif; ?>
    <div class="kop-text">
      <div class="kop-name"><?= e($school["school_name"] ?? "Sekolah") ?></div>
      <div class="kop-line"><?= nl2br(e($school["school_address"] ?? ($school["address"] ?? ""))) ?></div>
      <div class="kop-line">
        <?php
          $ph = $school["school_phone"] ?? ($school["phone"] ?? ($school["school_phone"] ?? ""));
          $em = $school["school_email"] ?? ($school["email"] ?? "");
          $line = [];
          if (!empty($ph)) $line[] = "Telp: " . $ph;
          if (!empty($em)) $line[] = "Email: " . $em;
          echo e(implode(" • ", $line));
        ?>
      </div>
    </div>
  </div>

  <div class="letter-meta">
    <?php $city = $school["city"] ?? ($school["city_name"] ?? ""); ?>
    <div><?= e($city ?: "") ?><?= $city ? ", " : "" ?><?= e(indonesian_date(date("Y-m-d"))) ?></div>
  </div>

  <h1>SURAT KETERANGAN REKAP ABSENSI SISWA</h1>
  <div class="sub">Periode: <?= e(indonesian_date($from)) ?> s/d <?= e(indonesian_date($to)) ?></div>

  <table class="meta">
    <tr><td width="120">Nama</td><td>: <?= e($student["full_name"]) ?></td></tr>
    <tr><td>NISN</td><td>: <?= e($student["nisn"] ?? "-") ?></td></tr>
    <tr><td>Kelas</td><td>: <?= e($student["class_name"] ?? "-") ?></td></tr>
    <tr><td>Tahun Pelajaran</td><td>: <?= e($student["year_name"] ?? "-") ?></td></tr>
  </table>

  <br>
  <table>
    <thead>
      <tr>
        <th width="120">Tanggal</th>
        <th width="60">Masuk</th>
        <th width="80">Status Masuk</th>
        <th width="60">Pulang</th>
        <th width="80">Status Pulang</th>
        <th>Status Hari</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($rows as $r):
        $a = $r["att"]; $l=$r["leave"]; $d=$r["d"];
      ?>
      <tr>
        <td><?= e(indonesian_date($d)) ?></td>
        <td><?= $a && $a["checkin_at"] ? e(date("H:i", strtotime($a["checkin_at"]))) : "-" ?></td>
        <td><?= $a && !empty($a["status_in"]) ? e(att_code_label($a["status_in"])) : "-" ?></td>
        <td><?= $a && $a["checkout_at"] ? e(date("H:i", strtotime($a["checkout_at"]))) : "-" ?></td>
        <td>
          <?php
            // sesuai permintaan: jangan masukkan keterangan "Pulang lebih awal" ke surat
            if ($a && !empty($a["status_out"]) && $a["status_out"]!=="PULANG LEBIH AWAL") echo e(att_code_label($a["status_out"]));
            else echo "-";
          ?>
        </td>
        <td>
          <b><?= e($r["status"]) ?></b>
          <?php if (!empty($r["holiday"])): ?>
            <div class="small">Libur: <?= e($r["holiday"]) ?></div>
          <?php elseif ($l): ?>
            <div class="small">Alasan: <?= e($l["reason"] ?? "-") ?></div>
            <?php if (!empty($l["description"])): ?><div class="small">Ket: <?= e($l["description"]) ?></div><?php endif; ?>
            <?php if (!empty($l["decision_note"])): ?><div class="small">Catatan: <?= e($l["decision_note"]) ?></div><?php endif; ?>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <br>
  <table>
    <tr>
      <th>Hadir</th><th>Telat</th><th>Ijin (Approved)</th><th>Tanpa Keterangan</th>
    </tr>
    <tr>
      <td style="text-align:center;"><?= (int)$cnt_hadir ?></td>
      <td style="text-align:center;"><?= (int)$cnt_telat ?></td>
      <td style="text-align:center;"><?= (int)$cnt_ijin ?></td>
      <td style="text-align:center;"><?= (int)$cnt_tanpa ?></td>
    </tr>
  </table>

  <div class="ttd">
    <div class="box">
      <div><?= e($school["city"] ?? "") ?>, <?= e(indonesian_date(date("Y-m-d"))) ?></div>
      <div style="margin-top:8px;">Guru</div>
      <div style="height:70px;"></div>
      <div><b><?= e($u["full_name"]) ?></b></div>
    </div>
  </div>
</div>
<script>window.print();</script>
</body>
</html>
