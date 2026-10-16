# Project Instructions: CV Maker

## Stack (jangan diganti tanpa izin)
- PHP 8.2+ dan CodeIgniter 4 (versi 4.5 atau lebih baru, gunakan yang terpasang di composer.json)
- Database: Supabase (PostgreSQL), diakses lewat driver Postgre bawaan CodeIgniter 4. Jangan memakai supabase-js dan jangan memanggil PostgREST dari browser.
- Frontend: view CodeIgniter 4 (server rendered), Tailwind CSS lewat CLI, Alpine.js untuk interaksi kecil. Tidak ada React, Vue, atau framework SPA.
- PDF: Dompdf, dibungkus interface sendiri supaya mudah diganti.
- Test: PHPUnit bawaan CodeIgniter 4.

## Aturan gaya kode
- Ikuti PSR-12 dan konvensi CodeIgniter 4 (Models, Entities, Controllers, Filters, Libraries, Services, Config).
- Controller tipis. Logika bisnis ada di class Service (`app/Services`). Query ada di Model atau Repository.
- Selalu pakai `declare(strict_types=1);`, type hint untuk parameter dan return, dan visibility yang eksplisit.
- Validasi input lewat `Config\Validation` atau `$this->validate()` dengan rule dan pesan error bahasa Indonesia.
- Jangan pernah membuat query dengan string concatenation. Pakai Query Builder atau binding parameter.
- Semua output di view memakai `esc()`. Tidak ada `echo` data user tanpa escape.
- Semua form memakai `csrf_field()`. Semua endpoint JSON yang mengubah data wajib terlindungi CSRF.
- Secret hanya di `.env`. Jangan pernah hardcode key Supabase, password database, atau URL berisi kredensial. Jangan commit `.env`.
- Nama class, method, variabel, tabel, kolom: bahasa Inggris, snake_case untuk tabel dan kolom, PascalCase untuk class, camelCase untuk method dan variabel.
- Teks yang tampil ke user: bahasa Indonesia, disimpan di `app/Language/id/` dan dipanggil lewat `lang()`. Jangan hardcode string UI di view.

## Aturan kualitas output (penting)
- Tulis kode yang lengkap dan bisa jalan. Dilarang meninggalkan `TODO`, `// implement later`, `...`, atau fungsi kosong.
- Jangan menambah komentar yang hanya mengulang isi kode. Komentar hanya untuk alasan keputusan yang tidak jelas dari kode.
- Jangan menambah dependency baru tanpa menjelaskan alasannya dan menunggu persetujuan.
- Jangan mengubah file di luar lingkup tugas. Kalau perlu, sebutkan dulu file apa dan kenapa.
- Setiap fitur selesai harus disertai test dan instruksi cara menjalankannya.
- Jangan memakai em dash (tanda hubung panjang) atau en dash di teks UI, komentar, maupun dokumentasi. Pakai titik, koma, titik dua, atau tanda hubung biasa.
- Jangan memakai emoji di UI, kode, commit message, atau dokumentasi.
- Jangan menulis copy marketing bergaya generik seperti "Unlock your potential", "Seamless", "Elevate", "Revolutionize". Teks UI harus singkat, jelas, dan fungsional.
- Jangan membuat data dummy berbau lorem ipsum di UI produksi. Data contoh hanya di seeder dan jelas berlabel contoh.

## Aturan desain UI
- Gaya: bersih, fungsional, solid. Permukaan memakai warna solid dengan border tipis 1px.
- DILARANG: glassmorphism, backdrop-blur, transparansi berlapis, gradient ungu atau biru-ungu generik, bayangan besar berlebihan, neon glow, ilustrasi 3D acak.
- Gunakan design token lewat CSS variable atau konfigurasi Tailwind: 1 warna aksen, skala netral, radius konsisten (maksimal 8px), skala spacing 4px.
- Tipografi: satu font sans untuk UI (contoh Inter), bobot dan ukuran konsisten.
- Kontras warna minimal WCAG AA. Semua input punya label yang terhubung. Fokus keyboard terlihat jelas.
- Responsif dari 360px sampai desktop. Mobile first.
- State wajib ada: loading, kosong, error, sukses.

## Aturan database
- Skema dibuat hanya lewat migration CodeIgniter 4 (`app/Database/Migrations`). Jangan edit skema manual di dashboard Supabase.
- Primary key memakai `uuid` dengan default `gen_random_uuid()`. Timestamp memakai `timestamptz`.
- Semua tabel di schema `public` yang dibuat project ini harus mengaktifkan Row Level Security tanpa policy, supaya akses lewat anon key PostgREST tetap tertutup. Akses data hanya lewat koneksi server CodeIgniter 4.
- Karena koneksi server memakai role yang melewati RLS, kepemilikan data WAJIB dicek di layer aplikasi (setiap query data milik user harus memfilter `user_id`).
- Gunakan foreign key dengan `ON DELETE CASCADE` untuk data anak CV, index untuk kolom foreign key dan kolom yang sering difilter.

## Struktur folder target
app/
  Config/
  Controllers/ (Auth, Dashboard, CvBuilder, Preview, Download, Theme)
  Entities/
  Filters/
  Language/id/
  Libraries/Pdf/ (PdfRendererInterface, DompdfRenderer)
  Models/
  Services/ (CvService, ThemeRegistry, CvRenderService, PhotoService)
  Views/
    layouts/
    auth/
    dashboard/
    builder/ (step partials)
    themes/<theme_key>/ (template.php, style.css, theme.json, thumbnail.png)
  Database/Migrations/
  Database/Seeds/
tests/


## Modul Job Matching: aturan tambahan

### Sumber data
- Data lowongan hanya boleh diambil lewat API resmi penyedia yang punya izin penggunaan (contoh: Jooble API). Dilarang membuat scraper untuk JobStreet, Glints, LinkedIn, Kalibrr, Karir.com, Indeed, atau situs lain yang melarang scraping di Terms of Service mereka.
- Semua penyedia diakses lewat `JobProviderInterface`. Tidak boleh ada kode di luar folder `app/Libraries/Jobs/Providers` yang tahu detail format respons penyedia tertentu.
- Panggilan HTTP ke penyedia hanya dari server, memakai CodeIgniter `CURLRequest` dengan timeout, retry terbatas, dan penanganan error yang jelas. API key hanya di `.env`.
- Hormati ketentuan penyedia: tampilkan sumber, arahkan user ke link lowongan asli, jangan klaim bisa "apply langsung di aplikasi ini", dan jangan menyimpan data lebih lama dari yang diizinkan ketentuan penyedia. Atur masa simpan di `Config\JobMatching`.

### Privasi
- Data pribadi user (nama, email, telepon, alamat, foto) TIDAK PERNAH dikirim ke penyedia lowongan atau layanan pihak ketiga mana pun. Yang boleh dikirim hanya kata kunci pencarian (judul pekerjaan, skill umum) dan lokasi tingkat kota.
- Fitur pencarian lowongan hanya aktif setelah user memberi persetujuan eksplisit (checkbox yang tidak dicentang secara default). Simpan waktu persetujuan. User bisa mencabut persetujuan kapan saja, dan hasil pencarian terkait CV itu dihapus saat dicabut.
- Jika suatu saat ada layer AI/LLM, layer itu opsional, mati secara default, dan mengirim hanya potongan teks yang sudah dibersihkan dari data pribadi.

### Skor kecocokan
- Skor harus deterministik, bisa dijelaskan, dan bisa diuji. Tidak ada angka yang muncul tanpa rincian komponen dan alasan.
- Bobot dan ambang batas ada di `Config\JobMatching`, bukan tersebar di kode.
- Jika data lowongan terbatas (hanya cuplikan), tampilkan tingkat keyakinan (rendah, sedang, tinggi) dan teks yang jujur bahwa skor dihitung dari cuplikan deskripsi.
- Data yang tidak tersedia (misalnya gaji) tidak boleh dihitung sebagai nilai nol. Komponen itu dikeluarkan dan bobotnya dinormalisasi ulang.

### UI
- Badge skor tidak boleh hanya mengandalkan warna. Selalu sertakan angka dan label teks.
- Semua aturan UI di bagian atas tetap berlaku (tanpa glassmorphism, tanpa gradient dekoratif, tanpa emoji, dst).
- Link keluar memakai `target="_blank"` dan `rel="noopener noreferrer nofollow"`.

### Database
- Semua tabel baru mengikuti aturan yang sama: migration, uuid, timestamptz, RLS aktif tanpa policy, filter `user_id` atau kepemilikan CV di layer aplikasi.
- Pencarian teks memakai fitur Postgres (tsvector dan pg_trgm), bukan LIKE yang lambat. Aktifkan ekstensi lewat migration dengan `CREATE EXTENSION IF NOT EXISTS`.