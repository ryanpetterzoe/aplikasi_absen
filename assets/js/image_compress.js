
// Client-side image compression for attendance photos
// - Resize longest edge to maxDim (default 1280px)
// - Encode to JPEG quality (default 0.65)
// This reduces bandwidth + storage and strips EXIF metadata.
async function compressImageFile(file, opts = {}) {
  const maxDim = opts.maxDim || 1280;
  const quality = (typeof opts.quality === "number") ? opts.quality : 0.65;

  if (!file) throw new Error("File kosong.");
  if (!(file.type || "").startsWith("image/")) throw new Error("File bukan gambar.");

  const dataUrl = await new Promise((resolve, reject) => {
    const fr = new FileReader();
    fr.onload = () => resolve(fr.result);
    fr.onerror = () => reject(new Error("Gagal membaca gambar."));
    fr.readAsDataURL(file);
  });

  const img = await new Promise((resolve, reject) => {
    const i = new Image();
    i.onload = () => resolve(i);
    i.onerror = () => reject(new Error("Gagal memuat gambar."));
    i.src = dataUrl;
  });

  let w = img.naturalWidth || img.width;
  let h = img.naturalHeight || img.height;
  if (!w || !h) throw new Error("Ukuran gambar tidak valid.");

  const scale = Math.min(1, maxDim / Math.max(w, h));
  const nw = Math.max(1, Math.round(w * scale));
  const nh = Math.max(1, Math.round(h * scale));

  const canvas = document.createElement("canvas");
  canvas.width = nw;
  canvas.height = nh;
  const ctx = canvas.getContext("2d", { alpha: false });
  ctx.drawImage(img, 0, 0, nw, nh);

  // JPEG best compatibility; strips metadata
  const out = canvas.toDataURL("image/jpeg", quality);
  return out;
}

// aliases for older calls
window.compressImageFile = compressImageFile;
window.compressPhoto = compressImageFile;
