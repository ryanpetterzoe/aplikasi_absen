# Aplikasi Absensi Sekolah (Web Mobile) - PHP (XAMPP)

Aplikasi ini dibuat supaya bisa langsung dicoba di XAMPP (Apache + MySQL).
Fitur utama:
- Multi role: Admin, Kepala Sekolah, Yayasan, Guru, Siswa
- Register (pending) -> disetujui admin
- Absensi masuk & pulang: foto dari kamera (bukan galeri) + GPS
- Log absensi: kartu + peta (Leaflet/OpenStreetMap)
- Ijin tidak berangkat
- Admin: master sekolah (logo), tahun pelajaran, jurusan, kelas (wali kelas), jam kerja & toleransi telat
- Rekap sederhana + cetak surat keterangan rekap (halaman print)
 - Export rekap ke Excel (.xlsx) / CSV
 - Import data users (Siswa/Guru/dll) dari Excel/CSV

## Cara Install (XAMPP)
1. Copy folder `absensi_sekolah` ke: `xampp/htdocs/`
2. Buat database MySQL: `absensi_sekolah`
3. Import file `database.sql` via phpMyAdmin
4. Edit koneksi DB di: `includes/config.php`
5. Akses: `http://localhost/absensi_sekolah/`

## Excel Import/Export (Opsional XLSX)
Fitur export/import bisa jalan tanpa library tambahan menggunakan CSV.

Jika ingin **XLSX** (.xlsx) gunakan Composer:
1) Install Composer di Windows
2) Buka CMD / PowerShell pada folder project `absensi_sekolah`
3) Jalankan:
   - `composer require phpoffice/phpspreadsheet`

Setelah itu tombol **Excel** (rekap) dan **Import Excel** (admin -> Data Users) akan support .xlsx.

## Akun Admin Default
- Username: admin
- Password: admin123

> Setelah login admin, disarankan ganti password.

## Catatan
- Folder `uploads/` harus bisa ditulis (write permission).
- Absensi butuh HTTPS agar permission kamera/lokasi lebih mulus di mobile.
  Untuk uji lokal, biasanya masih bisa di `http://localhost` (tergantung browser).


## Installer (XAMPP)
1) Copy folder ke `htdocs/absensi_sekolah`
2) Buka `http://localhost/absensi_sekolah/install/`
3) Isi DB + Admin lalu Install.

Jika ingin install ulang: hapus `includes/config.local.php` dan `includes/installed.lock`.
