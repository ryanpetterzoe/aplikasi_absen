<?php
$title = "Absen Pulang";
require_once __DIR__ . "/includes/functions.php";
require_login();

$u = current_user();
$today = date("Y-m-d");

$schedule = get_work_schedule_for_date($mysqli, $today);
if ($schedule && ((int)$schedule["is_workday"]===0)) {
  $msg = ($schedule["is_holiday"] ?? 0) ? ("Hari ini libur: " . ($schedule["holiday_name"] ?? "")) : "Hari ini bukan hari kerja.";
  flash_set("warning", $msg);
  redirect("/dashboard.php");
}

if (has_leave_today($mysqli, $u["id"], $today)) {
  flash_set("warning", "Hari ini Anda ijin tidak berangkat. Tidak ada absen pulang.");
  redirect("/dashboard.php");
}
$att = attendance_today($mysqli, $u["id"], $today);
if (!$att || !$att["checkin_at"]) {
  flash_set("warning", "Anda belum absen masuk hari ini.");
  redirect("/dashboard.php");
}
if ($att["checkout_at"]) {
  flash_set("info", "Anda sudah absen pulang hari ini.");
  redirect("/dashboard.php");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $photo = $_POST["photoBase64"] ?? "";
  $lat = $_POST["lat"] ?? null;
  $lng = $_POST["lng"] ?? null;

  if ($photo==="" || $lat===null || $lng===null) {
    flash_set("danger", "Foto & GPS wajib.");
    redirect("/attendance_checkout.php");
  }

  $rules = get_work_schedule_for_date($mysqli, $today);
  $now = date("Y-m-d H:i:s");
  [$status_out, $note_out] = calc_status_out($rules, $now);

  $dir = __DIR__ . "/uploads/attendance/" . $u["id"] . "/";
  ensure_upload_dir($dir);
  $filename = $today . "_checkout.jpg";
  $relpath = "uploads/attendance/" . $u["id"] . "/" . $filename;

  if (!save_base64_jpeg($photo, $dir . $filename)) {
    flash_set("danger", "Gagal menyimpan foto. Pastikan permission folder uploads.");
    redirect("/attendance_checkout.php");
  }

  $stmt = $mysqli->prepare("UPDATE attendance SET checkout_at=?, checkout_lat=?, checkout_lng=?, checkout_photo_path=?, status_out=?, note_out=? 
    WHERE user_id=? AND att_date=?");
  $stmt->bind_param("sddsssds", $now, $lat, $lng, $relpath, $status_out, $note_out, $u["id"], $today);
  $stmt->execute();

  flash_set("success", "Absen pulang berhasil. Status: " . $status_out . ($note_out ? " (" . $note_out . ")" : ""));
  redirect("/dashboard.php");
}

require_once __DIR__ . "/includes/header.php";
?>
<div class="card card-soft p-3">
  <div class="d-flex align-items-center justify-content-between">
    <h5 class="fw-semibold mb-0"><i class="bi bi-box-arrow-left me-1"></i>Absen Pulang</h5>
    <a class="btn btn-sm btn-outline-secondary" href="dashboard.php">Kembali</a>
  </div>
  <div class="text-secondary small mt-1">Ambil foto dari kamera + GPS.</div>

  <div class="row g-3 mt-2">
    <div class="col-12 col-lg-6">
      
      <div class="border rounded-3 p-3 bg-white">
        <div class="fw-semibold"><i class="bi bi-camera me-1"></i>Foto Absensi</div>
        <div class="text-secondary small mt-1">Menggunakan kamera bawaan HP. Jika muncul pilihan galeri, pilih <b>Kamera</b>.</div>

        <input type="file" id="photoInput" accept="image/*" class="form-control d-none">

        <div class="d-grid gap-2 mt-3">
          <button class="btn btn-primary" id="btnCameraBack" type="button"><i class="bi bi-camera me-1"></i>Buka Kamera</button>
          <button class="btn btn-outline-secondary" id="btnCameraFront" type="button"><i class="bi bi-person-bounding-box me-1"></i>Kamera Depan</button>
        </div>
      </div>

    </div>
    <div class="col-12 col-lg-6">
      <div class="card border-0 bg-white rounded-3 p-3">
        <div class="fw-semibold">Preview</div>
        <img id="preview" class="img-fluid rounded-3 border mt-2" alt="preview" style="display:none;">
        <div id="gpsInfo" class="small text-secondary mt-2">GPS: belum diambil</div>

        <form method="post" id="form" class="mt-3">
          <input type="hidden" name="photoBase64" id="photoBase64">
          <input type="hidden" name="lat" id="lat">
          <input type="hidden" name="lng" id="lng">
          <button class="btn btn-primary btn-lg w-100" type="submit" id="btnSubmit" disabled><i class="bi bi-check2-circle me-1"></i>Kirim Absen Pulang</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="<?= $BASE_URL ?>/assets/js/camera.js"></script>
<script src="<?= $BASE_URL ?>/assets/js/image_compress.js"></script>
<script>
const preview = document.getElementById("preview");
const gpsInfo = document.getElementById("gpsInfo");
const btnSubmit = document.getElementById("btnSubmit");
const photoBase64 = document.getElementById("photoBase64");
const latEl = document.getElementById("lat");
const lngEl = document.getElementById("lng");
const photoInput = document.getElementById("photoInput");

function fileToDataURL(file){
  return new Promise((resolve,reject)=>{
    const fr = new FileReader();
    fr.onload = () => resolve(fr.result);
    fr.onerror = () => reject(new Error("Gagal membaca foto."));
    fr.readAsDataURL(file);
  });
}

async function handlePickedFile(file){
  if (!file) return;
  if (!(file.type||"").startsWith("image/")){
    alert("File bukan gambar.");
    photoInput.value = "";
    return;
  }
  // preview + base64
  const base64 = await compressImageFile(file, {maxDim: 1280, quality: 0.65});
  preview.src = base64;
  preview.style.display = "block";
  photoBase64.value = base64;

  // GPS
  const gps = await getGPS();
  latEl.value = gps.lat;
  lngEl.value = gps.lng;
  gpsInfo.textContent = `GPS: ${gps.lat.toFixed(6)}, ${gps.lng.toFixed(6)} (±${Math.round(gps.acc)}m)`;

  btnSubmit.disabled = false;
}

document.getElementById("btnCameraBack").onclick = () => {
  // hint kamera belakang
  photoInput.setAttribute("capture","environment");
  photoInput.click();
};

document.getElementById("btnCameraFront").onclick = () => {
  // hint kamera depan
  photoInput.setAttribute("capture","user");
  photoInput.click();
};

photoInput.onchange = async () => {
  try {
    const file = photoInput.files && photoInput.files[0] ? photoInput.files[0] : null;
    await handlePickedFile(file);
  } catch(e){
    alert("Gagal ambil foto (kompres)/GPS: " + (e.message||e));
  }
};
</script>
<?php require_once __DIR__ . "/includes/footer.php"; ?>