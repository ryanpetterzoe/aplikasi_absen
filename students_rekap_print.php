<?php
$title = "Print Rekap Absensi Siswa";
require_once __DIR__ . "/includes/functions.php";
require_login();
require_role(["ADMIN","GURU","KEPSEK","YAYASAN"]);
$me = current_user();

$from = $_GET["from"] ?? date("Y-m-01");
$to   = $_GET["to"] ?? date("Y-m-d");
$group = $_GET["group"] ?? "student"; // student/class/major
$class_id = $_GET["class_id"] ?? "";
$major_id = $_GET["major_id"] ?? "";

$ay_id = get_active_academic_year_id($mysqli);
$workdays = list_workday_dates($mysqli, $from, $to, $ay_id);

$where = "";
$params = [$from,$to,$from,$to];
$types = "ssss";

// filter class/major
if ($class_id !== "") { $where .= " AND u.class_id=?"; $params[] = (int)$class_id; $types .= "i"; }
if ($major_id !== "") { $where .= " AND c.major_id=?"; $params[] = (int)$major_id; $types .= "i"; }
  // Guru bisa print rekap semua kelas (tidak dibatasi wali kelas)
$sql = "SELECT u.id,u.full_name,u.nisn,
  c.id AS class_id,c.name AS class_name,
  m.id AS major_id,m.name AS major_name,
  COUNT(DISTINCT a.att_date) AS present_days,
  SUM(CASE WHEN a.status_in='LATE' THEN 1 ELSE 0 END) AS late_count,
  (SELECT COUNT(*) FROM leave_requests lr
     WHERE lr.user_id=u.id AND lr.status='APPROVED' AND DATE(lr.leave_date) BETWEEN ? AND ?) AS leave_count
  FROM users u
  LEFT JOIN classes c ON c.id=u.class_id
  LEFT JOIN majors m ON m.id=c.major_id
  LEFT JOIN attendance a ON a.user_id=u.id AND a.att_date BETWEEN ? AND ?
  WHERE u.role='SISWA' AND u.status='ACTIVE' AND u.is_alumni=0 $where
  GROUP BY u.id
  ORDER BY c.name,u.full_name";

$stmt = $mysqli->prepare($sql);
stmt_bind_params($stmt, $types, $params);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) {
  $r["absent_count"] = count_absent_without_excuse($workdays, $r["present_days"], $r["leave_count"]);
  $rows[] = $r;
}

$school = get_school_profile($mysqli);

function agg_init() { return ["count_students"=>0,"present_days"=>0,"late_count"=>0,"leave_count"=>0,"absent_count"=>0]; }

$agg = [];
if ($group === "class") {
  foreach ($rows as $r) {
    $k = $r["class_name"] ?: "-";
    if (!isset($agg[$k])) $agg[$k] = agg_init();
    $agg[$k]["count_students"] += 1;
    $agg[$k]["present_days"] += (int)$r["present_days"];
    $agg[$k]["late_count"] += (int)$r["late_count"];
    $agg[$k]["leave_count"] += (int)$r["leave_count"];
    $agg[$k]["absent_count"] += (int)$r["absent_count"];
  }
} elseif ($group === "major") {
  foreach ($rows as $r) {
    $k = $r["major_name"] ?: "-";
    if (!isset($agg[$k])) $agg[$k] = agg_init();
    $agg[$k]["count_students"] += 1;
    $agg[$k]["present_days"] += (int)$r["present_days"];
    $agg[$k]["late_count"] += (int)$r["late_count"];
    $agg[$k]["leave_count"] += (int)$r["leave_count"];
    $agg[$k]["absent_count"] += (int)$r["absent_count"];
  }
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

<h2>Rekap Absensi Siswa</h2>
<div class="muted">Periode: <?= e($from) ?> s/d <?= e($to) ?> • Total hari kerja: <?= (int)count($workdays) ?></div>
<div class="muted">Dicetak: <?= e(date("Y-m-d H:i")) ?> WIB</div>

<?php if ($group === "student"): ?>
  <table>
    <thead>
      <tr>
        <th style="width:26px;">No</th>
        <th>Nama</th>
        <th style="width:90px;">NISN</th>
        <th style="width:120px;">Kelas</th>
        <th style="width:120px;">Jurusan</th>
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
        <td><?= e($r["nisn"]) ?></td>
        <td><?= e($r["class_name"]) ?></td>
        <td><?= e($r["major_name"]) ?></td>
        <td><?= (int)$r["present_days"] ?></td>
        <td><?= (int)$r["late_count"] ?></td>
        <td><?= (int)$r["leave_count"] ?></td>
        <td><?= (int)$r["absent_count"] ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php else: ?>
  <table>
    <thead>
      <tr>
        <th style="width:26px;">No</th>
        <th><?= $group==="class" ? "Kelas" : "Jurusan" ?></th>
        <th style="width:90px;">Jumlah Siswa</th>
        <th style="width:70px;">Hadir</th>
        <th style="width:70px;">Telat</th>
        <th style="width:70px;">Ijin</th>
        <th style="width:90px;">Tanpa Ket.</th>
      </tr>
    </thead>
    <tbody>
      <?php $no=1; foreach($agg as $k=>$a): ?>
      <tr>
        <td><?= $no++ ?></td>
        <td><?= e($k) ?></td>
        <td><?= (int)$a["count_students"] ?></td>
        <td><?= (int)$a["present_days"] ?></td>
        <td><?= (int)$a["late_count"] ?></td>
        <td><?= (int)$a["leave_count"] ?></td>
        <td><?= (int)$a["absent_count"] ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<div style="margin-top:40px; display:flex; justify-content:flex-end;">
  <div style="text-align:center; width:220px;">
    <div><?= e($school["city"] ?? "__________") ?>, <?= e(date("Y-m-d")) ?></div>
    <div style="margin-top:70px; font-weight:bold; text-decoration:underline;">(_____________________)</div>
    <div class="muted">Tanda Tangan</div>
  </div>
</div>

</body>
</html>
