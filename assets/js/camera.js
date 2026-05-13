let __camStream = null;
let __camFacing = "environment";

/**
 * Stop kamera (jika sedang jalan).
 */
function stopCamera() {
  if (__camStream) {
    __camStream.getTracks().forEach(t => t.stop());
    __camStream = null;
  }
}

/**
 * Buka kamera dan stream ke <video>.
 * Catatan: getUserMedia butuh HTTPS atau localhost (secure context).
 */
async function openCamera(videoEl, facingMode = "environment") {
  if (!window.isSecureContext) {
    throw new Error("Akses kamera butuh HTTPS (atau localhost). Jika akses via IP LAN, gunakan https (SSL) atau URL https dari ngrok.");
  }
  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    throw new Error("Browser tidak mendukung getUserMedia.");
  }

  stopCamera();
  __camFacing = facingMode;

  const constraints = {
    audio: false,
    video: {
      facingMode: { ideal: facingMode },
      width: { ideal: 1280 },
      height: { ideal: 720 }
    }
  };

    try {
    __camStream = await navigator.mediaDevices.getUserMedia(constraints);
  } catch(e) {
    // fallback: tanpa facingMode khusus
    const c2 = JSON.parse(JSON.stringify(constraints));
    if (c2.video && c2.video.facingMode) delete c2.video.facingMode;
    __camStream = await navigator.mediaDevices.getUserMedia(c2);
  }
  videoEl.srcObject = __camStream;
  await videoEl.play();
  return __camStream;
}

/**
 * Flip kamera depan/belakang.
 */
async function flipCamera(videoEl) {
  const next = (__camFacing === "environment") ? "user" : "environment";
  return openCamera(videoEl, next);
}

/**
 * Ambil foto dari <video> ke <canvas> lalu kembalikan base64 JPEG.
 */
function captureToBase64(videoEl, canvasEl, quality = 0.85) {
  const w = videoEl.videoWidth || 640;
  const h = videoEl.videoHeight || 480;
  canvasEl.width = w;
  canvasEl.height = h;
  const ctx = canvasEl.getContext("2d");
  ctx.drawImage(videoEl, 0, 0, w, h);
  return canvasEl.toDataURL("image/jpeg", quality);
}

/**
 * Ambil lokasi GPS (Promise).
 */
function getGps() {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) return reject(new Error("GPS tidak didukung."));
    navigator.geolocation.getCurrentPosition(
      (pos) => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude, acc: pos.coords.accuracy }),
      (err) => reject(err),
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
  });
}


// Alias untuk kompatibilitas lama
function getGPS(){ return getGps(); }
