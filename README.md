 # CV Maker

Aplikasi CV Maker berbasis CodeIgniter 4, PHP 8.2+, PostgreSQL Supabase, Tailwind CSS CLI, Alpine.js, dan Dompdf.

## Prasyarat

- PHP 8.2 atau lebih baru dengan ekstensi `intl`, `mbstring`, `dom`, `pgsql`, dan `gd`. Ekstensi `sqlite3` diperlukan untuk menjalankan seluruh test bawaan.
- Composer 2.
- Node.js dan npm.
- Project Supabase dengan akses ke Supavisor Session pooler.

Ekstensi `pgsql` dipakai driver Postgre bawaan CodeIgniter. Ekstensi `gd` diperlukan untuk pemrosesan gambar. `pdo_pgsql` tidak dibutuhkan oleh driver ini.

## Setup lokal

1. Pasang dependency PHP:

   ```powershell
   composer install
   ```

2. Jika `.env` belum ada, salin `.env.example` ke `.env`. Jika sudah ada, pertahankan file tersebut dan tambahkan atau perbarui nilai yang diperlukan. Jangan commit `.env`.

   ```powershell
   if (-not (Test-Path .env)) { Copy-Item .env.example .env }
   ```

3. Di Supabase, buka **Project Settings > Database > Connection string**, pilih **Session pooler** dari Supavisor, lalu salin host, username, dan password ke `.env`. Gunakan port `5432`, database `postgres`, serta `sslmode = require`. Ganti nilai contoh `region` dan `project-ref`.

4. Aktifkan ekstensi `pgsql` dan `gd` di `php.ini` yang digunakan PHP CLI. Di instalasi XAMPP, pastikan `php_pgsql.dll` dan `php_gd.dll` tersedia di folder ekstensi, lalu aktifkan `extension=pgsql` dan `extension=gd`. Mulai ulang terminal setelah mengubah konfigurasi PHP.

5. Pastikan runtime dan koneksi:

   ```powershell
   php -v
   php -m
   php spark db:check-supabase
   ```

   Perintah koneksi hanya bekerja saat `CI_ENVIRONMENT = development`. Hasil berhasil berarti query `SELECT 1` dapat dijalankan melalui koneksi yang dikonfigurasi.

6. Jalankan migration Shield dan skema CV. Shield menyediakan tabel `users` bawaan dengan ID integer, sehingga `cvs.user_id` memakai integer dan tidak ada tabel `users` kedua.

   ```powershell
   php spark migrate --all
   ```

   Migration CV memakai UUID dengan default `gen_random_uuid()`, timestamp PostgreSQL `timestamptz`, JSONB, foreign key cascade, indeks, dan RLS tanpa policy. RLS juga diaktifkan pada tabel autentikasi Shield. Untuk rollback batch terakhir:

   ```powershell
   php spark migrate:rollback
   ```

   Setelah rollback, jalankan kembali `php spark migrate --all`.

7. Untuk mengisi satu CV contoh dan akun Shield khusus development:

   ```powershell
   php spark db:seed App\\Database\\Seeds\\CvExampleSeeder
   ```

   Seeder menolak environment production. Akun contoh memakai email `cv-contoh@example.test` dan kata sandi acak yang tidak disimpan.

8. Pasang dan bangun aset frontend:

   ```powershell
   npm install
   npm run build
   ```

9. Jalankan aplikasi:

   ```powershell
   php spark serve --host 127.0.0.1 --port 8080
   ```

   Buka `http://localhost:8080/`.

## Dependency utama

- `codeigniter4/shield`: autentikasi resmi CodeIgniter, digunakan untuk fondasi alur akun.
- `dompdf/dompdf`: render dokumen PDF. Dependency ini sudah menjadi bagian dari starter project dan dipertahankan.
- `tailwindcss` dan `@tailwindcss/cli`: compile utility CSS secara lokal.
- `alpinejs`: interaksi kecil pada UI, dibundel sebagai aset lokal agar halaman tidak bergantung pada CDN.

## Autentikasi dan dashboard

- Halaman masuk, pendaftaran, tautan masuk untuk pemulihan akun, dan ubah kata sandi tersedia dalam bahasa Indonesia.
- Atur kredensial SMTP pada `.env` untuk mengirim tautan masuk. Nilai SMTP disimpan di environment, bukan di source code.
- Tautan pemulihan berlaku satu jam. Permintaan dengan alamat yang tidak terdaftar menampilkan konfirmasi umum yang sama tanpa mengungkap status akun.
- Dashboard tersedia di `/dashboard`. Setiap daftar, pembukaan, duplikasi, dan penghapusan CV dibatasi berdasarkan `user_id` pemilik.
- Penghapusan CV menggunakan soft delete. Data anak CV ikut terhapus secara cascade hanya saat CV dihapus permanen.
- Form mutasi memakai CSRF session; filter Shield melindungi dashboard; filter auth rate membatasi permintaan login, daftar, dan pemulihan maksimal 10 per menit per alamat IP.

Setelah `.env` dan migration siap, buka `/register` untuk membuat akun. Pendaftaran dan masuk mengarahkan ke dashboard.

## Tema dan pratinjau CV

- Editor CV menyediakan sebelas tema: Akademik, ATS Klasik, Ringkas, Minimalis, Modern, Desainer, Editorial, Eksekutif, Portofolio, Profesional, dan Dua Kolom. Pilihan tema disimpan pada CV tanpa mengubah bagian CV lainnya.
- Akademik, ATS Klasik, Ringkas, Minimalis, dan Modern memakai struktur satu kolom dengan elemen HTML semantik. Desainer, Editorial, Eksekutif, Portofolio, Profesional, dan Dua Kolom memakai tata letak visual. Label ramah ATS menjelaskan struktur, bukan menjamin hasil pembacaan atau kelulusan seleksi.
- Pratinjau tema dibuka di iframe bersandbox dan tidak menyimpan pilihan tema sampai pengguna memilihnya di editor.
- Render HTML untuk pratinjau dan `DompdfRenderer` memakai template serta stylesheet tema yang sama. Font CV Maker didaftarkan dari font DejaVu Sans lokal yang disediakan oleh Dompdf; akses font dan gambar jarak jauh dinonaktifkan.
- Konfigurasi tiap tema berada pada `app/Views/themes/<theme_key>/theme.json`, dengan template dan stylesheet di folder yang sama. Format tidak valid ditolak oleh `ThemeRegistry`.

## Pembayaran unduhan

- Unduhan PDF dikenai biaya satu kali sebesar Rp15.000 per CV. Setelah pembayaran disetujui, CV tersebut dapat diunduh kembali tanpa pembayaran tambahan. Duplikat CV dianggap CV baru.
- Pembayaran memakai QRIS yang diunggah admin, tanpa gateway. Pengguna mengirim bukti pembayaran, lalu admin memeriksanya secara manual sebelum unduhan dibuka. Gambar QRIS dan bukti disimpan di `writable/uploads/cv-payments`, tidak disajikan sebagai file publik.
- Tambahkan `CV_PAYMENT_ADMIN_EMAILS = 'email-admin@example.id'` ke `.env`, memakai alamat email akun yang sudah terdaftar. Beberapa admin dapat dipisahkan dengan koma. Admin masuk lalu membuka dashboard dan memilih Pemeriksaan pembayaran untuk memasang gambar QRIS serta memeriksa antrean.
- Harga dapat diubah melalui `CV_DOWNLOAD_PRICE_IDR = 15000`. Nilai disalin ke catatan pembayaran saat bukti dikirim.
- Migration `CreateCvDownloadPayments` membuat catatan pembayaran, indeks, foreign key cascade, serta RLS tanpa policy pada PostgreSQL. Terapkan migration sebelum membuka unduhan berbayar. Aplikasi tidak menjalankan migration otomatis.
- Bukti yang ditolak dapat dikirim ulang. Bukti yang masih menunggu atau sudah disetujui tidak dapat diganti. Unduhan hanya dibuka untuk pemilik CV setelah status menjadi disetujui.

## Halaman error dan indeks mesin pencari

- Halaman 404 dan 500 menggunakan tampilan Indonesia yang responsif, mandiri dari aset CSS aplikasi, dan tidak menampilkan detail exception di production.
- `/robots.txt` dibentuk dari `app.baseURL`, mengizinkan halaman beranda, mengecualikan rute akun dan dashboard, serta menunjuk ke sitemap.
- `/sitemap.xml` hanya memuat URL beranda yang publik. Editor, akun, pembayaran, pratinjau CV, dan data lowongan tidak dimasukkan.

## Tinjau dan unduh PDF

- Langkah 8 menyediakan ukuran kertas A4 atau Letter, tiga skala teks, serta pilihan warna dari palet tema. Pengaturan disimpan otomatis tanpa mengubah isi CV.
- Pratinjau HTML dan PDF memakai dokumen dan stylesheet tema yang sama. Jumlah halaman dihitung oleh Dompdf dari hasil render aktual.
- Unduh tersedia dari tombol pada langkah 8 atau endpoint terlindungi `/dashboard/cv/{id}/download`. Unduhan dibatasi lima kali per menit per pengguna dan hanya dapat diakses pemilik CV.
- PDF menggunakan DejaVu Sans lokal, mematikan akses resource jarak jauh, dan mengirim header unduhan privat tanpa cache. Item pengalaman dan bagian lain dijaga agar tidak terpotong di tengah jika memungkinkan.
- Tes ekstraksi teks memerlukan Python dan paket opsional `pypdf` pada mesin test. Pasang dengan `python -m pip install pypdf`; ini bukan dependency aplikasi.

## Persetujuan dan profil pencarian lowongan

- Migration Job Matching menambahkan `job_preferences`, `skills_dictionary`, dan `title_dictionary`. Semua tabel memakai UUID, relasi CV cascade, dan RLS tanpa policy pada PostgreSQL.
- Isi kamus lintas profesi setelah migration dengan `php spark db:seed App\\Database\\Seeds\\JobDictionarySeeder`. Seeder idempoten, mencakup lebih dari 200 keahlian dan lebih dari 120 jabatan dengan alias Indonesia dan Inggris.
- Tombol Cari Lowongan pada dashboard membuka persetujuan eksplisit yang tidak dicentang secara default. Setelah persetujuan, jabatan, kota, jenis kerja, dan preferensi lain dapat ditinjau serta diubah. Pencabutan persetujuan menghapus relasi hasil untuk CV tersebut.
- Jooble menjadi penyedia default. Isi `jobMatching.joobleApiKey` pada `.env` sebelum pencarian. Request server memakai `POST https://jooble.org/api/{api_key}` dengan `keywords`, `location`, dan `page`; field respons yang dipetakan meliputi `totalCount`, `jobs`, `id`, `title`, `company`, `location`, `snippet`, `salary`, `source`, `type`, `link`, dan `updated`. Akses langsung ke dokumentasi resmi Jooble saat implementasi terhalang respons HTTP 403, sehingga kontrak ini belum diverifikasi terhadap dokumentasi resmi atau kredensial aktif.
- Query hanya dirakit dari jabatan dan keahlian kanonis dalam kamus serta kota. Nama, kontak, alamat lengkap, dan nama perusahaan pada CV tidak dikirim. HTTP memakai timeout 8 detik dan maksimal dua retry untuk kegagalan jaringan atau HTTP 5xx. Cache query berlaku 12 jam, posting disimpan maksimal 7 hari, kuota pencarian 10 per hari, dan jeda refresh per CV 5 menit. Nilai dapat diubah pada `app/Config/JobMatching.php`.
- Migration menambah `job_query_cache`, `job_postings`, `job_refresh_log`, dan `job_matches`; pada PostgreSQL migration mengaktifkan `pg_trgm`, membuat kolom `tsvector` terhitung beserta indeks GIN, dan mengaktifkan RLS tanpa policy. Bersihkan cache dan lowongan kedaluwarsa menggunakan `php spark jobs:cleanup`.
- Fase pencocokan menambahkan skor, label, keyakinan, rincian komponen, skill cocok/kurang, alasan, status, catatan, dan waktu perhitungan ke `job_matches`. Hasil dihitung ulang setelah preferensi disimpan dan setelah autosave isi CV; perubahan status dan catatan tidak tertimpa saat skor dihitung ulang.
- Skor dasar merupakan rata-rata berbobot yang dinormalisasi hanya dari komponen yang tersedia: judul, keahlian, tingkat pengalaman, lokasi, kesegaran, dan gaji. Penalti kata kunci yang dikecualikan serta jenis kerja yang tidak dipilih diterapkan setelah normalisasi. Kartu hanya menampilkan skor bersama label; rincian memperlihatkan nilai, bobot awal, bobot efektif, skill yang cocok/kurang, penalti, alasan, dan tingkat keyakinan. Cuplikan pendek menurunkan keyakinan.
- Untuk mengubah scoring, edit `scoreWeights`, `scoreThresholds`, `titleSimilarityWeights`, `seniorityDistanceScores`, `locationScores`, `salaryScores`, `seniorityBoundariesYears`, `confidenceMinimumComponents`, `singleSkillMaximumScore`, `excludedKeywordPenalty`, `incompatibleJobTypePenalty`, `minimumSnippetLength`, `freshnessFullScoreDays`, dan `freshnessLowScoreDays` di `app/Config/JobMatching.php`. Bobot komponen yang tidak tersedia dikeluarkan lalu dinormalisasi ulang. Angka penalti mengurangi skor akhir setelah perhitungan bobot.
- Setelah menyimpan preferensi, buka hasil untuk memulai atau memperbarui pencarian. Daftar hasil memiliki filter kota dengan kecocokan persis, jenis kerja, mode kerja, status tersimpan, skor minimum, pengurutan, dan paginasi.
- Detail lowongan menampilkan rincian skor dan cuplikan, serta tindakan simpan atau sembunyikan. Status hasil tersimpan pada `job_matches` dan tidak tertimpa saat skor dihitung ulang. Tautan keluar hanya ditampilkan untuk URL HTTP atau HTTPS dan membuka halaman penyedia di tab baru.
- Hasil dan detail mengatribusikan penyedia serta menjelaskan bahwa penilaian memakai cuplikan yang disediakan, bukan uraian lengkap. Aplikasi tidak mengklaim mendukung apply langsung. Jalankan migration dan seeder kamus sebelum mengaktifkan pencarian.
- Pelacak lamaran menyimpan status, waktu perubahan, catatan user, dan riwayat perubahan. Lowongan tersembunyi dikecualikan dari daftar aktif. Migration 040 membuat tracker terpisah; saat posting kedaluwarsa, data penyedia dihapus sesuai masa simpan dan tracker hanya menyimpan penanda bahwa lowongan tersimpan mungkin sudah ditutup, tanpa judul atau tautan sumber.
- Halaman Insight menghitung skill yang sering diminta dari hasil relevan teratas, mengkanonisasi alias dan mengeluarkan skill yang sudah tercatat di CV. Ringkasan gaji baru ditampilkan saat jumlah sampel mencapai `minimumSalarySamplesForSummary`. Batas skor dan jumlah lowongan ditentukan oleh `minimumScoreForSkillGap` dan `topJobsForSkillGap` di `app/Config/JobMatching.php`.
- Pemeriksaan kata kunci membandingkan skill yang kurang dengan teks CV dan memberi lokasi bagian yang mungkin sesuai. Ini hanya petunjuk pengeditan; UI secara eksplisit mengingatkan agar user tidak menambah atau mengklaim skill tanpa bukti nyata.

## Pengujian

```powershell
php -d extension=php_sqlite3.dll vendor\bin\phpunit --no-coverage
```

Tes memakai konfigurasi SQLite database pengujian CodeIgniter dan tidak memerlukan kredensial Supabase. Aktifkan ekstensi `sqlite3` untuk menjalankannya.

## Menambah tema

1. Buat folder baru di `app/Views/themes/<theme_key>/`. Gunakan kunci huruf kecil, angka, dan tanda hubung, misalnya `ats-clean`.
2. Tambahkan `theme.json` dengan format berikut. Setiap warna palet harus berupa hex enam digit dan palet berisi 3 sampai 8 warna unik.

   ```json
   {
     "key": "ats-clean",
     "name_key": "Themes.ats_clean_name",
     "description_key": "Themes.ats_clean_description",
     "ats_friendly": true,
     "layout": "single-column",
     "font_family": "CVMaker Sans",
     "accent_palette": ["#245B65", "#1D4ED8", "#7C2D12"]
   }
   ```

3. Tambahkan kunci nama dan deskripsi ke `app/Language/id/Themes.php`.
4. Buat `template.php` yang menggunakan `themes/shared/document`, lalu buat `style.css` untuk layout tema. Tampilkan hanya data yang sudah disediakan oleh renderer dan escape semua nilai dinamis dengan `esc()`. Hindari tabel, foto, dan SVG pada tema ATS.
5. Gunakan kelas bersama seperti `.cv-document`, `.cv-section`, `.cv-section h2`, dan `.cv-entry`. Jangan mengandalkan font atau gambar jarak jauh. Tema baru harus dapat dicetak oleh Dompdf.
6. Jalankan tes dan build:

   ```powershell
   php -d extension=php_sqlite3.dll vendor\bin\phpunit --no-coverage
   npm run build
   ```

`ThemeRegistry` memvalidasi manifest dan keberadaan template serta stylesheet. Daftar metadata tema disimpan dalam cache selama lima menit; perubahan manifest membuat fingerprint cache berubah.

## Checklist keamanan dan aksesibilitas

- Semua form mutasi memakai token CSRF. Dashboard dan editor memerlukan sesi Shield; akses CV dicek berdasarkan pemilik.
- Logout menggunakan POST dan CSRF. Autosave, upload foto, hitung halaman, dan unduh PDF memiliki batas permintaan per akun.
- Upload foto hanya menerima JPG, PNG, atau WebP yang bisa dibaca GD. Ukuran dan dimensi dibatasi, lalu gambar didekode dan ditulis ulang sebagai JPEG di dalam `writable/`.
- Output user di-escape. Renderer tema memakai template lokal; URL yang ditampilkan hanya menerima skema HTTP atau HTTPS.
- CSP aktif dengan sumber script lokal, koneksi same-origin, frame same-origin, objek dinonaktifkan, dan `script-src-attr 'none'`. Alpine.js memerlukan `unsafe-eval` untuk evaluator ekspresinya, sehingga jangan menambahkan script pihak ketiga ke layout.
- Style inline diizinkan oleh CSP untuk pratinjau dokumen dan swatch warna; nilainya hanya berasal dari template lokal dan palet hex yang tervalidasi.
- Filter header mengirim X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, dan kebijakan isolasi origin. HTTPS production mengaktifkan redirect dan HSTS melalui filter `forcehttps`.
- Kunci database, SMTP, dan encryption hanya berasal dari `.env`. Tidak ada kunci Supabase yang dibutuhkan browser. Jangan menaruh `.env`, foto, file log, atau cache writable ke repository.
- Layout menyediakan tautan lewati konten, fokus terlihat, label form, pesan status untuk teknologi bantu, navigasi keyboard, dan dukungan `prefers-reduced-motion`.
- Warna aksen tema dibatasi pada palet yang tervalidasi. Teks dan state tidak dibedakan melalui warna saja.
- Tidak ada efek blur atau gradient dekoratif pada UI. File CSS dan JavaScript statis dikompresi oleh Apache `mod_deflate` saat modul tersedia.
- Renderer memuat CV milik user dalam satu query, lalu memuat seluruh jenis bagian anak melalui satu query `UNION ALL` dengan parameter terikat.

## Deploy production

1. Siapkan PHP 8.2+ dengan `intl`, `mbstring`, `dom`, `pgsql`, dan `gd`; aktifkan ekstensi tersebut untuk PHP-FPM atau runtime web yang dipakai. Arahkan document root web server hanya ke folder `public/`, bukan root repository.
2. Pasang dependency production dan build aset sebelum deploy:

   ```powershell
   composer install --no-dev --prefer-dist --optimize-autoloader
   npm ci
   npm run build
   ```

3. Buat `.env` dari `.env.example`, jangan menyalin nilai lokal. Atur minimal:

   ```ini
   CI_ENVIRONMENT = production
   app.baseURL = 'https://cv.example.id/'
   app.forceGlobalSecureRequests = true
   CV_DOWNLOAD_PRICE_IDR = 15000
   CV_PAYMENT_ADMIN_EMAILS = 'email-admin@example.id'
   cookie.secure = true
   encryption.key = 'nilai-acak-yang-dihasilkan-php-spark-key-generate'
   database.default.hostname = 'host-pooler-dari-supabase'
   database.default.username = 'user-pooler-dari-supabase'
   database.default.password = 'password-database'
   database.default.port = 5432
   database.default.DBDriver = Postgre
   database.default.sslmode = require
   ```

   Buat key dengan `php spark key:generate`, simpan nilainya di secret manager, lalu atur SMTP pada `.env` bila fitur tautan masuk digunakan. Jangan gunakan Supabase service-role key atau anon key sebagai kredensial koneksi database.
4. Pastikan HTTPS aktif di load balancer/web server sebelum mengaktifkan redirect HTTPS. Bila aplikasi berjalan di belakang reverse proxy, konfigurasi hanya IP proxy yang dipercaya pada `Config\App::$proxyIPs`; jangan mempercayai header forwarded dari semua alamat.
5. Berikan akun layanan web hak baca pada kode dan hak tulis terbatas hanya pada direktori `writable/`. Contoh Linux: pemilik deploy, grup PHP-FPM, direktori writable `750` dan file writable `640`. Jangan menggunakan izin `777`. Di Windows/IIS, berikan Modify hanya kepada identity application pool untuk folder `writable`.
6. Terapkan migration memakai kredensial database production setelah backup dan review:

   ```powershell
   php spark migrate --all
   ```

   Jangan jalankan seeder contoh di production. Pastikan RLS aktif tanpa policy dan aplikasi memakai koneksi server PostgreSQL.
7. Aktifkan rewrite dan compression di web server. Konfigurasi `public/.htaccess` memakai `mod_rewrite`, `mod_deflate`, dan `mod_expires`; untuk Nginx, konfigurasikan `try_files` ke `public/index.php` serta gzip untuk HTML, CSS, JavaScript, JSON, dan SVG. Uji header HTTPS, CSP, kompresi, login, autosave, upload, pratinjau, dan unduhan PDF setelah deploy.
8. Pantau log aplikasi dan database, siapkan backup serta prosedur restore, dan rotasi segera setiap credential yang pernah masuk ke log, tiket, atau percakapan.
