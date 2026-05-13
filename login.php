<?php
$title = "Login";
require_once __DIR__ . "/includes/functions.php";
$appName = get_app_setting('app_name','Absensi Sekolah');
$appLogo = get_app_setting('app_logo','');
$title = 'Login';
require_once __DIR__ . "/includes/auth.php";

if (current_user()) {
  $u = current_user();
  if ($u["role"] === "ADMIN") redirect("/admin/dashboard.php");
  redirect("/dashboard.php");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $username = trim($_POST["username"] ?? "");
  $password = $_POST["password"] ?? "";

  if ($username === "" || $password === "") {
    flash_set("warning", "Username & password wajib diisi.");
    redirect("/login.php");
  }

  $stmt = $mysqli->prepare("SELECT * FROM users WHERE username=? LIMIT 1");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();

  if (!$row || !password_verify($password, $row["password_hash"])) {
    flash_set("danger", "Login gagal. Cek username/password.");
    redirect("/login.php");
  }
  if ($row["status"] !== "ACTIVE") {
    flash_set("warning", "Akun belum aktif. Status: " . $row["status"]);
    redirect("/login.php");
  }

  login_user($row);
  if ($row["role"] === "ADMIN") redirect("/admin/dashboard.php");
  redirect("/dashboard.php");
}

require_once __DIR__ . "/includes/header.php";
?>
<div class="row justify-content-center">
  <div class="col-12 col-md-6">
    <div class="card card-soft p-3">
      <div class="text-center mb-2">
        <?php if (!empty($appLogo) && file_exists(__DIR__ . "/" . $appLogo)): ?>
        <img src="<?= $BASE_URL . "/" . e($appLogo) ?>" alt="logo" class="login-logo mb-2" style="width:72px;height:72px;object-fit:cover;border-radius:18px;border:1px solid rgba(10,40,80,.15);box-shadow:0 10px 22px rgba(10,40,80,.10);">
      <?php endif; ?>
      <div class="fw-semibold fs-4 text-primary"><?= e($appName) ?></div>
        <div class="text-secondary small">Silakan login untuk melanjutkan</div>
      </div>
      <h5 class="fw-semibold mb-3"><i class="bi bi-box-arrow-in-right me-1"></i>Login</h5>
      <form method="post" class="vstack gap-2">
        <div>
          <label class="form-label">Username</label>
          <input class="form-control" name="username" autocomplete="username" required>
        </div>
        <div>
          <label class="form-label">Password</label>
          <input class="form-control" type="password" name="password" autocomplete="current-password" required>
        </div>
        <button class="btn btn-primary btn-lg mt-2" type="submit">Masuk</button>
        <div class="text-secondary small mt-2">Belum punya akun? <a href="register.php">Register</a></div>
      </form>
    </div>
  </div>
</div>
<?php require_once __DIR__ . "/includes/footer.php"; ?>
