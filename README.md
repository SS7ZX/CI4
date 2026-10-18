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

## Pengujian

```powershell
php -d extension=php_sqlite3.dll vendor\bin\phpunit --no-coverage
```

Tes memakai konfigurasi SQLite database pengujian CodeIgniter dan tidak memerlukan kredensial Supabase. Aktifkan ekstensi `sqlite3` untuk menjalankannya.
