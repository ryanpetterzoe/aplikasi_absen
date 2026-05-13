<?php
// === AUTO CONFIG / INSTALLER ===
$CONFIG_LOCAL = __DIR__ . "/config.local.php";
$INSTALL_LOCK = __DIR__ . "/installed.lock";

// Jika belum terpasang, arahkan ke installer (kecuali sedang di folder /install)
if (!file_exists($CONFIG_LOCAL) || !file_exists($INSTALL_LOCK)) {
  $isInstaller = (strpos($_SERVER["REQUEST_URI"] ?? "", "/install") !== false);
  if (!$isInstaller) {
      $base = dirname($_SERVER["SCRIPT_NAME"] ?? "");
  $base = preg_replace("#/(admin|install)$#", "", $base);
  if ($base === "" ) $base = "/";
  header("Location: " . rtrim($base, "/") . "/install/index.php");
    exit;
  }
  // Installer akan handle koneksi DB sendiri; jangan die di sini.
  $db_host = $db_host ?? "localhost";
  $db_user = $db_user ?? "root";
  $db_pass = $db_pass ?? "";
  $db_name = $db_name ?? "absensi_sekolah";
  $base = dirname($_SERVER["SCRIPT_NAME"] ?? "");
  $base = preg_replace("#/(admin|install)$#", "", $base);
  $BASE_URL = $BASE_URL ?? (rtrim($base, "/") ?: "");
} else {
  require_once $CONFIG_LOCAL;
}

// === KONFIGURASI DATABASE ===
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_errno) {
  die("Gagal konek DB: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8mb4");

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Timezone aplikasi
date_default_timezone_set("Asia/Jakarta");
// Set timezone MySQL (jika didukung)
@$mysqli->query("SET time_zone = '+07:00'");

// Base URL (di-set via installer / config.local.php)
$BASE_URL = $BASE_URL ?? "/absensi_sekolah";
// Normalize BASE_URL (biar aman di root "/")
$BASE_URL = is_string($BASE_URL) ? trim($BASE_URL) : "";
if ($BASE_URL === "/") $BASE_URL = "";
$BASE_URL = rtrim($BASE_URL, "/");
?>