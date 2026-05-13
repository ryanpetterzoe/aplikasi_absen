<?php
// Export master data (academic_years / majors / classes) to Excel/CSV

require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/excel.php";
require_login();
require_role("ADMIN");

$type = $_GET["type"] ?? "majors";
$allowed = ["majors","years","classes"];
if (!in_array($type, $allowed, true)) $type = "majors";

if ($type === "years") {
  $res = $mysqli->query("SELECT name,is_active FROM academic_years ORDER BY is_active DESC, id DESC");
  $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  $headers = ["name","is_active(1/0)"];
  $out = [];
  foreach ($rows as $r) $out[] = [$r["name"], (int)($r["is_active"] ?? 0)];
  excel_output_table("academic_years", $headers, $out);
}

if ($type === "classes") {
  $sql = "SELECT c.grade,c.name, m.name AS major_name, y.name AS year_name, u.full_name AS homeroom_name, c.is_active
          FROM classes c
          LEFT JOIN majors m ON m.id=c.major_id
          LEFT JOIN academic_years y ON y.id=c.academic_year_id
          LEFT JOIN users u ON u.id=c.homeroom_teacher_id
          ORDER BY c.grade ASC, c.name ASC";
  $res = $mysqli->query($sql);
  $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  $headers = ["grade","name","major_name","homeroom_teacher","academic_year","is_active(1/0)"];
  $out = [];
  foreach ($rows as $r) {
    $out[] = [(int)$r["grade"], $r["name"], $r["major_name"], $r["homeroom_name"], $r["year_name"], (int)($r["is_active"] ?? 1)];
  }
  excel_output_table("classes", $headers, $out);
}

// default majors
$res = $mysqli->query("SELECT name FROM majors ORDER BY name");
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$headers = ["name"];
$out = [];
foreach ($rows as $r) $out[] = [$r["name"]];
excel_output_table("majors", $headers, $out);
