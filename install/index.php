<?php
// Installer Absensi Sekolah (XAMPP / Hosting)
// Versi robust: kompatibel shared-hosting (tanpa error kolom lama)
error_reporting(E_ALL);
ini_set("display_errors", 1);

$root = realpath(__DIR__ . "/..");
$CONFIG_LOCAL = $root . "/includes/config.local.php";
$INSTALL_LOCK = $root . "/includes/installed.lock";
$dbsqlFile = $root . "/database.sql";

// Backup/Restore helper (tanpa mysqldump)
$DBTOOLS = $root . "/includes/db_tools.php";
if (file_exists($DBTOOLS)) {
  require_once $DBTOOLS;
}

function e($s){ return htmlspecialchars((string)($s ?? ""), ENT_QUOTES, "UTF-8"); }

function guess_base_url() {
  $sn = $_SERVER["SCRIPT_NAME"] ?? "";
  // /absensi_sekolah/install/index.php -> /absensi_sekolah
  $sn = preg_replace("#/install/.*$#", "", $sn);
  return rtrim($sn, "/");
}

function has_column(mysqli $db, string $table, string $col): bool {
  $colEsc = $db->real_escape_string($col);
  $tblEsc = $db->real_escape_string($table);
  $r = $db->query("SHOW COLUMNS FROM `$tblEsc` LIKE '$colEsc'");
  return $r && $r->num_rows > 0;
}

function ensure_column(mysqli $db, string $table, string $col, string $alterSql): void {
  if (!has_column($db, $table, $col)) {
    $db->query($alterSql);
  }
}

function read_app_key_from_config(string $configLocal): ?string {
  if (!file_exists($configLocal)) return null;
  // include dalam scope fungsi biar tidak "mengotori" variable global installer
  $APP_KEY = null;
  include $configLocal;
  return $APP_KEY ?? null;
}

function gen_app_key(): string {
  try {
    return bin2hex(random_bytes(16));
  } catch (Throwable $t) {
    return bin2hex(openssl_random_pseudo_bytes(16));
  }
}

$alreadyInstalled = file_exists($CONFIG_LOCAL) && file_exists($INSTALL_LOCK);

$base_url = guess_base_url();
$defaults = [
  "db_host" => "localhost",
  "db_name" => "absensi_sekolah",
  "db_user" => "root",
  "db_pass" => "",
  "app_name" => "Absensi Sekolah",
  "admin_user" => "admin",
  "admin_pass" => "admin123",
  "base_url" => $base_url ?: "/absensi_sekolah",
];

$data = $defaults;
$errors = [];
$success = false;
$restoreSuccess = false;
$restoreInfo = '';

$action = $_POST['action'] ?? 'install';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  foreach ($defaults as $k => $v) {
    if (isset($_POST[$k])) $data[$k] = trim((string)$_POST[$k]);
  }

  // validasi untuk action backup/restore: cukup db_host/db_name/db_user
  if ($action !== 'install') {
    foreach (["db_host","db_name","db_user"] as $k) {
      if ($data[$k] === "") $errors[] = "Field '$k' wajib diisi.";
    }
    // jika sudah terinstall, butuh APP_KEY
    if ($alreadyInstalled) {
      $storedKey = read_app_key_from_config($CONFIG_LOCAL);
      $inputKey = trim((string)($_POST['app_key'] ?? ''));
      if ($storedKey && $inputKey !== $storedKey) {
        $errors[] = "APP_KEY salah. Cek di includes/config.local.php (variabel \$APP_KEY).";
      } elseif (!$storedKey) {
        // kompatibilitas versi lama: jika APP_KEY belum ada, tetap izinkan tapi beri peringatan
        // (user tetap harus tahu kredensial DB yang benar)
      }
    }
  }

  // ACTION: BACKUP
  if ($action === 'backup' && !$errors) {
    try {
      mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
      $db = new mysqli($data["db_host"], $data["db_user"], $data["db_pass"], $data["db_name"]);
      $db->set_charset('utf8mb4');
      @$db->query("SET time_zone = '+07:00'");

      $gzip = isset($_POST['gzip']) && $_POST['gzip'] === '1';
      $base = 'absensi_sekolah_' . date('Ymd_His');
      dbtools_dump($db, [
        'gzip' => $gzip,
        'include_data' => true,
        'filename_base' => $base,
      ]);
      exit;
    } catch (Throwable $t) {
      $errors[] = $t->getMessage();
    }
  }

  // ACTION: RESTORE
  if ($action === 'restore' && !$errors) {
    $confirm = strtoupper(trim((string)($_POST['confirm'] ?? '')));
    if ($confirm !== 'RESTORE') {
      $errors[] = "Konfirmasi salah. Ketik RESTORE untuk melanjutkan.";
    } elseif (empty($_FILES['sql_file']['name'])) {
      $errors[] = "Pilih file .sql atau .sql.gz terlebih dahulu.";
    } else {
      $name = (string)$_FILES['sql_file']['name'];
      $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
      if (!in_array($ext, ['sql','gz'], true)) {
        $errors[] = "Format file harus .sql atau .sql.gz";
      } elseif (!is_uploaded_file($_FILES['sql_file']['tmp_name'])) {
        $errors[] = "Upload file gagal. Coba ulangi.";
      } else {
        try {
          mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
          $db = new mysqli($data["db_host"], $data["db_user"], $data["db_pass"], $data["db_name"]);
          $db->set_charset('utf8mb4');
          @$db->query("SET time_zone = '+07:00'");

          $r = dbtools_import($db, $_FILES['sql_file']['tmp_name']);
          if (($r['ok'] ?? false) === true) {
            $restoreSuccess = true;
            $restoreInfo = 'Restore berhasil. Statements dieksekusi: ' . (int)($r['executed'] ?? 0);
          } else {
            $errors[] = $r['error'] ?? 'Restore gagal.';
          }
        } catch (Throwable $t) {
          $errors[] = $t->getMessage();
        }
      }
    }
  }

  // basic validation
  if ($action === 'install') {
    foreach (["db_host","db_name","db_user","admin_user","admin_pass","app_name","base_url"] as $k) {
      if ($data[$k] === "") $errors[] = "Field '$k' wajib diisi.";
    }
  }

  if (!$errors && $action === 'install') {
    if ($data["base_url"] === "/") $data["base_url"] = "";
    try {
      mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

      // 1) konek ke DB (kalau DB belum ada, coba create kalau punya privilege)
      $db = null;
      try {
        $db = new mysqli($data["db_host"], $data["db_user"], $data["db_pass"], $data["db_name"]);
      } catch (mysqli_sql_exception $ex) {
        // unknown database -> coba create (biasanya hanya bisa di XAMPP)
        $db2 = new mysqli($data["db_host"], $data["db_user"], $data["db_pass"]);
        $db2->set_charset("utf8mb4");
        $dbNameEsc = $db2->real_escape_string($data["db_name"]);
        try {
          $db2->query("CREATE DATABASE IF NOT EXISTS `$dbNameEsc` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
          $db2->select_db($data["db_name"]);
          $db = $db2;
        } catch (mysqli_sql_exception $ex2) {
          throw new Exception("Database belum ada / tidak bisa dibuat otomatis. Buat database dulu di panel hosting, lalu isi DB Name yang benar. Detail: " . $ex2->getMessage());
        }
      }

      $db->set_charset("utf8mb4");
      // timezone MySQL (optional)
      @$db->query("SET time_zone = '+07:00'");

      // 2) import schema
      if (!file_exists($dbsqlFile)) throw new Exception("database.sql tidak ditemukan.");
      $sql = file_get_contents($dbsqlFile);

      // buang CREATE DATABASE / USE jika ada
      $sql = preg_replace("/^\s*CREATE\s+DATABASE\b.*?;\s*/mi", "", $sql);
      $sql = preg_replace("/^\s*USE\b.*?;\s*/mi", "", $sql);

      if (!$db->multi_query($sql)) {
        throw new Exception("Gagal import schema: " . $db->error);
      }
      do { $db->store_result(); } while ($db->more_results() && $db->next_result());

      // 3) migrations (kolom tambahan)
      // users.photo_path
      ensure_column($db, "users", "photo_path", "ALTER TABLE users ADD COLUMN photo_path VARCHAR(255) NULL AFTER address");

      // school_profile: kolom baru identitas
      ensure_column($db, "school_profile", "school_address", "ALTER TABLE school_profile ADD COLUMN school_address TEXT NULL AFTER school_name");
      ensure_column($db, "school_profile", "school_phone", "ALTER TABLE school_profile ADD COLUMN school_phone VARCHAR(100) NULL AFTER school_address");
      ensure_column($db, "school_profile", "school_email", "ALTER TABLE school_profile ADD COLUMN school_email VARCHAR(120) NULL AFTER school_phone");
      ensure_column($db, "school_profile", "city", "ALTER TABLE school_profile ADD COLUMN city VARCHAR(100) NULL AFTER school_email");

      // 3b) kompatibilitas versi lama (address/phone/email) -> hanya jika kolom lama ada
      if (has_column($db, "school_profile", "address")) {
        $db->query("UPDATE school_profile SET school_address = COALESCE(NULLIF(school_address,''), address) WHERE school_address IS NULL OR school_address=''");
      }
      if (has_column($db, "school_profile", "phone")) {
        $db->query("UPDATE school_profile SET school_phone = COALESCE(NULLIF(school_phone,''), phone) WHERE school_phone IS NULL OR school_phone=''");
      }
      if (has_column($db, "school_profile", "email")) {
        $db->query("UPDATE school_profile SET school_email = COALESCE(NULLIF(school_email,''), email) WHERE school_email IS NULL OR school_email=''");
      }

      // 4) set admin username & password
      $hash = password_hash($data["admin_pass"], PASSWORD_BCRYPT);
      $res = $db->query("SELECT id FROM users WHERE role='ADMIN' ORDER BY id ASC LIMIT 1");
      if ($res && ($row = $res->fetch_assoc())) {
        $id = (int)$row["id"];
        $stmt = $db->prepare("UPDATE users SET username=?, password_hash=?, status='ACTIVE', full_name='Administrator', must_change_password=0 WHERE id=?");
        $stmt->bind_param("ssi", $data["admin_user"], $hash, $id);
        $stmt->execute();
      } else {
        $stmt = $db->prepare("INSERT INTO users (role,status,username,password_hash,full_name,must_change_password) VALUES('ADMIN','ACTIVE',?,?, 'Administrator',0)");
        $stmt->bind_param("ss", $data["admin_user"], $hash);
        $stmt->execute();
      }

      // 5) set app settings: app_name + base_url
      $stmt = $db->prepare("INSERT INTO app_settings (`key`,`value`) VALUES('app_name',?) ON DUPLICATE KEY UPDATE value=VALUES(value)");
      $stmt->bind_param("s", $data["app_name"]);
      $stmt->execute();

      $stmt = $db->prepare("INSERT INTO app_settings (`key`,`value`) VALUES('base_url',?) ON DUPLICATE KEY UPDATE value=VALUES(value)");
      $stmt->bind_param("s", $data["base_url"]);
      $stmt->execute();

      // 5b) upload logo aplikasi (opsional)
      if (!empty($_FILES["app_logo"]["name"])) {
        $ext = strtolower(pathinfo($_FILES["app_logo"]["name"], PATHINFO_EXTENSION));
        if (!in_array($ext, ["jpg","jpeg","png","webp"], true)) {
          throw new Exception("Format logo harus jpg/png/webp.");
        }
        $dir = $root . "/uploads/app";
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $filename = "logo_" . time() . "." . $ext;
        $dest = $dir . "/" . $filename;
        if (!move_uploaded_file($_FILES["app_logo"]["tmp_name"], $dest)) {
          throw new Exception("Gagal upload logo aplikasi.");
        }
        $rel = "uploads/app/" . $filename;
        $stmt = $db->prepare("INSERT INTO app_settings (`key`,`value`) VALUES('app_logo',?) ON DUPLICATE KEY UPDATE value=VALUES(value)");
        $stmt->bind_param("s", $rel);
        $stmt->execute();
      }

      // 6) write config.local.php
      $appKey = gen_app_key();
      $cfg = "<?php\n";
      $cfg .= '$db_host = ' . var_export($data["db_host"], true) . ";\n";
      $cfg .= '$db_name = ' . var_export($data["db_name"], true) . ";\n";
      $cfg .= '$db_user = ' . var_export($data["db_user"], true) . ";\n";
      $cfg .= '$db_pass = ' . var_export($data["db_pass"], true) . ";\n";
      $cfg .= '$APP_KEY = ' . var_export($appKey, true) . ";\n";
      $cfg .= '$BASE_URL = ' . var_export($data["base_url"], true) . ";\n";
      $cfg .= "?>\n";

      file_put_contents($CONFIG_LOCAL, $cfg);
      file_put_contents($INSTALL_LOCK, "installed:" . date("c"));

      $success = true;

    } catch (Throwable $t) {
      $errors[] = $t->getMessage();
    }
  }
}

?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Installer - Absensi Sekolah</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body{background:linear-gradient(180deg,#f6f9ff 0%,#ffffff 60%);}
    .card-soft{border:1px solid rgba(10,40,80,.12); border-radius:18px; box-shadow:0 10px 24px rgba(10,40,80,.06);}
    .brand{display:flex; gap:12px; align-items:center;}
    .brand .dot{width:44px;height:44px;border-radius:14px;background:#0d2b5b;display:flex;align-items:center;justify-content:center;color:#fff;}
    .hint{font-size:12px;color:#6c757d;}
  </style>
</head>
<body>
<div class="container py-5">
  <div class="mx-auto" style="max-width:880px;">
    <div class="brand mb-3">
      <div class="dot"><i class="bi bi-shield-check"></i></div>
      <div>
        <div class="fw-bold">Installer Absensi Sekolah</div>
        <div class="hint">Isi data koneksi database hosting/XAMPP, lalu klik Install.</div>
      </div>
    </div>

    <?php if ($alreadyInstalled): ?>
      <div class="alert alert-info card-soft p-3">
        <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-1"></i>Aplikasi sudah terinstall</div>
        <div class="small text-secondary">Kamu masih bisa pakai fitur <b>Backup/Restore DB</b> di bawah. Demi keamanan, sebaiknya hapus folder <b>/install</b> setelah selesai.</div>
      </div>
    <?php endif; ?>

    <?php if ($restoreSuccess): ?>
      <div class="alert alert-success card-soft p-3">
        <div class="fw-semibold mb-1"><i class="bi bi-check2-circle me-1"></i><?= e($restoreInfo) ?></div>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success card-soft p-3">
        <div class="fw-semibold mb-1"><i class="bi bi-check2-circle me-1"></i>Install berhasil!</div>
        <div class="small text-secondary mb-2">Silakan login menggunakan akun admin yang kamu set.</div>
        <a class="btn btn-primary" href="<?= e($data["base_url"]) ?>/login.php">Ke Halaman Login</a>
      </div>
    <?php else: ?>
      <?php if ($errors): ?>
        <div class="alert alert-danger card-soft p-3">
          <div class="fw-semibold mb-2"><i class="bi bi-exclamation-triangle me-1"></i>Terjadi kesalahan</div>
          <ul class="mb-0">
            <?php foreach($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <div class="card card-soft p-4">
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="install">
          <div class="row g-3">
            <div class="col-12">
              <div class="fw-semibold mb-1"><i class="bi bi-database me-1"></i>Database</div>
              <div class="hint">Di hosting, DB biasanya harus dibuat dulu di panel. DB Host umumnya <b>localhost</b> (lihat panel hosting jika berbeda).</div>
            </div>

            <div class="col-md-4">
              <label class="form-label">DB Host</label>
              <input class="form-control" name="db_host" value="<?= e($data["db_host"]) ?>" placeholder="localhost">
            </div>
            <div class="col-md-4">
              <label class="form-label">DB Name</label>
              <input class="form-control" name="db_name" value="<?= e($data["db_name"]) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">DB User</label>
              <input class="form-control" name="db_user" value="<?= e($data["db_user"]) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">DB Password</label>
              <input class="form-control" type="password" name="db_pass" value="<?= e($data["db_pass"]) ?>">
            </div>

            <div class="col-12 mt-2">
              <div class="fw-semibold mb-1"><i class="bi bi-gear me-1"></i>Pengaturan Aplikasi</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Nama Aplikasi</label>
              <input class="form-control" name="app_name" value="<?= e($data["app_name"]) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Base URL</label>
              <input class="form-control" name="base_url" value="<?= e($data["base_url"]) ?>" placeholder="/absensi_sekolah">
              <div class="hint">Contoh hosting: <b>/</b> atau <b>/nama-folder</b></div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Logo Aplikasi (opsional)</label>
              <input class="form-control" type="file" name="app_logo" accept=".jpg,.jpeg,.png,.webp">
            </div>

            <div class="col-12 mt-2">
              <div class="fw-semibold mb-1"><i class="bi bi-person-badge me-1"></i>Akun Admin</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Username Admin</label>
              <input class="form-control" name="admin_user" value="<?= e($data["admin_user"]) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Password Admin</label>
              <input class="form-control" type="password" name="admin_pass" value="<?= e($data["admin_pass"]) ?>">
            </div>

            <div class="col-12 mt-2 d-flex gap-2">
              <button class="btn btn-primary px-4" type="submit"><i class="bi bi-lightning-charge me-1"></i>Install</button>
              <a class="btn btn-outline-secondary" href="<?= e($data["base_url"]) ?>/login.php"><i class="bi bi-box-arrow-in-right me-1"></i>Login</a>
            </div>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <div class="card card-soft p-4 mt-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
          <div class="fw-semibold"><i class="bi bi-hdd-network me-1"></i>Backup & Restore DB (Installer)</div>
          <div class="hint">Bisa dipakai untuk pindah hosting / recovery. Format: <b>.sql</b> atau <b>.sql.gz</b>.</div>
        </div>
      </div>

      <div class="alert alert-warning small mb-3">
        <b>Catatan keamanan:</b> setelah selesai, sebaiknya hapus folder <b>/install</b>.
      </div>

      <div class="row g-3">
        <div class="col-12 col-lg-6">
          <div class="border rounded-4 p-3">
            <div class="fw-semibold mb-2"><i class="bi bi-download me-1"></i>Backup</div>
            <form method="post">
              <input type="hidden" name="action" value="backup">
              <div class="row g-2">
                <div class="col-6">
                  <label class="form-label">DB Host</label>
                  <input class="form-control" name="db_host" value="<?= e($data["db_host"]) ?>">
                </div>
                <div class="col-6">
                  <label class="form-label">DB Name</label>
                  <input class="form-control" name="db_name" value="<?= e($data["db_name"]) ?>">
                </div>
                <div class="col-6">
                  <label class="form-label">DB User</label>
                  <input class="form-control" name="db_user" value="<?= e($data["db_user"]) ?>">
                </div>
                <div class="col-6">
                  <label class="form-label">DB Password</label>
                  <input class="form-control" type="password" name="db_pass" value="">
                </div>

                <?php if ($alreadyInstalled): ?>
                  <div class="col-12">
                    <label class="form-label">APP_KEY</label>
                    <input class="form-control" name="app_key" placeholder="Lihat di includes/config.local.php" required>
                    <div class="hint">Wajib jika aplikasi sudah terinstall.</div>
                  </div>
                <?php endif; ?>

                <div class="col-12 d-flex gap-2 mt-1">
                  <button class="btn btn-primary" type="submit"><i class="bi bi-filetype-sql me-1"></i>Download .SQL</button>
                  <button class="btn btn-outline-primary" type="submit" name="gzip" value="1"><i class="bi bi-file-zip me-1"></i>Download .SQL.GZ</button>
                </div>
              </div>
            </form>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="border rounded-4 p-3">
            <div class="fw-semibold mb-2"><i class="bi bi-upload me-1"></i>Restore</div>
            <div class="alert alert-danger small">Restore akan menimpa data (DROP/CREATE). Pastikan sudah backup.</div>
            <form method="post" enctype="multipart/form-data">
              <input type="hidden" name="action" value="restore">
              <div class="row g-2">
                <div class="col-6">
                  <label class="form-label">DB Host</label>
                  <input class="form-control" name="db_host" value="<?= e($data["db_host"]) ?>">
                </div>
                <div class="col-6">
                  <label class="form-label">DB Name</label>
                  <input class="form-control" name="db_name" value="<?= e($data["db_name"]) ?>">
                </div>
                <div class="col-6">
                  <label class="form-label">DB User</label>
                  <input class="form-control" name="db_user" value="<?= e($data["db_user"]) ?>">
                </div>
                <div class="col-6">
                  <label class="form-label">DB Password</label>
                  <input class="form-control" type="password" name="db_pass" value="">
                </div>

                <?php if ($alreadyInstalled): ?>
                  <div class="col-12">
                    <label class="form-label">APP_KEY</label>
                    <input class="form-control" name="app_key" placeholder="Lihat di includes/config.local.php" required>
                  </div>
                <?php endif; ?>

                <div class="col-12">
                  <label class="form-label">File backup (.sql / .sql.gz)</label>
                  <input class="form-control" type="file" name="sql_file" accept=".sql,.gz" required>
                </div>
                <div class="col-12">
                  <label class="form-label">Konfirmasi</label>
                  <input class="form-control" name="confirm" placeholder="Ketik RESTORE" required>
                  <div class="hint">Ketik <b>RESTORE</b> untuk melanjutkan.</div>
                </div>
                <div class="col-12 mt-1">
                  <button class="btn btn-danger" type="submit"><i class="bi bi-arrow-clockwise me-1"></i>Restore Database</button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <div class="text-center hint mt-3">
      © <?= date("Y") ?> Absensi Sekolah • Timezone Asia/Jakarta
    </div>
  </div>
</div>
</body>
</html>
