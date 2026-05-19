# Backend Aplikasi Berbagi Lokasi - Instruksi Project

## Catatan Nama Project

Nama aplikasi masih sementara dan dapat berubah nanti. Untuk dokumentasi internal, project ini dapat disebut sebagai FindUs Backend sampai nama final ditentukan.

## Gambaran Umum Project

Project ini adalah aplikasi berbagi lokasi yang secara konsep mirip dengan Life360. Sistem memungkinkan pengguna membuat atau bergabung ke dalam grup/circle privat dan membagikan lokasi real-time kepada anggota terpercaya.

Backend menyediakan endpoint REST API untuk autentikasi, manajemen pengguna, grup/circle, keanggotaan, pembaruan lokasi, riwayat lokasi, dan beberapa fitur premium.

## Tech Stack

- Backend: Laravel / PHP 8.3-FPM
- Web server: Nginx
- Database utama: PostgreSQL 16
- Ekstensi database spasial: PostGIS
- Layanan real-time: Firebase Realtime Database atau Cloud Firestore, mengikuti implementasi yang sudah ada di project
- Cache / penyimpanan sementara / queue: Redis 7
- Tool manajemen database: Adminer
- Mobile client: Flutter
- Gaya API: REST API dengan response JSON
- Autentikasi: Laravel Sanctum atau token-based authentication, mengikuti implementasi yang sudah ada
- Environment / deployment: Docker dan Docker Compose

## Docker / Environment

Project ini menggunakan Docker sebagai environment development utama untuk menjalankan backend Laravel dan service pendukung.

Setup Docker didokumentasikan di `DOCKER.md`. Sebelum menyarankan atau menjalankan command yang berkaitan dengan environment, periksa `DOCKER.md`, `Dockerfile`, `docker-compose.yml`, `.env`, dan `.env.example` jika tersedia.

Jangan mengasumsikan penggunaan PHP lokal, Composer lokal, PostgreSQL lokal, Redis lokal, atau service lokal lain. Project ini harus dijalankan melalui Docker kecuali diminta secara eksplisit.

Project ini menggunakan service Docker berikut:

- `app`: aplikasi Laravel yang berjalan dengan PHP 8.3-FPM
- `nginx`: web server Nginx
- `db`: database PostgreSQL 16
- `redis`: service cache Redis 7
- `adminer`: tool manajemen database PostgreSQL

Gunakan command `docker-compose` sesuai dokumentasi project yang sudah ada.

Command Docker umum:

```bash
docker-compose up -d
docker-compose down
docker-compose logs -f
docker-compose ps
docker-compose restart
docker-compose build --no-cache
```

Command setup awal:

```bash
cp .env.docker .env
docker-compose build
docker-compose up -d
docker-compose exec app composer install
docker-compose exec app chmod -R 775 storage bootstrap/cache
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate
docker-compose exec app php artisan db:seed
```

Command Laravel harus dijalankan melalui container `app`:

```bash
docker-compose exec app php artisan migrate
docker-compose exec app php artisan route:list
docker-compose exec app php artisan test
docker-compose exec app php artisan queue:work
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan db:seed
```

Command Composer harus dijalankan melalui container `app`:

```bash
docker-compose exec app composer install
docker-compose exec app composer dump-autoload
```

Command troubleshooting database dan Redis:

```bash
docker-compose exec db pg_isready -U findmy_user -d findmy
docker-compose logs db
docker-compose exec redis redis-cli ping
docker-compose restart redis
```

Akses aplikasi:

- Aplikasi Laravel: `http://localhost:8000`
- Adminer: `http://localhost:8080`

Akses database melalui Adminer:

- System: `PostgreSQL`
- Server: `db`
- Username: `findmy_user`
- Password: gunakan nilai yang ada di `.env.docker`
- Database: `findmy`

Catatan penting:

- Jangan menyarankan `php artisan serve` karena aplikasi Laravel dijalankan melalui Docker dan Nginx.
- Jangan menyarankan instalasi PHP, Composer, PostgreSQL, atau Redis secara lokal kecuali diminta secara eksplisit.
- Gunakan `docker-compose`, bukan `docker compose`, karena dokumentasi project yang sudah ada menggunakan `docker-compose`.
- Jika stack sebelumnya pernah dijalankan dengan MySQL, gunakan `docker-compose down -v` sebelum menjalankan stack PostgreSQL untuk menghindari konflik volume lama.
- Jika terjadi error permission pada storage, jalankan:

```bash
docker-compose exec app chmod -R 775 storage bootstrap/cache
```

## Peran Masing-Masing Teknologi

### Laravel

Laravel adalah framework backend utama. Semua business logic inti, validasi, autentikasi, otorisasi, endpoint API, dan integrasi service ditangani melalui Laravel.

### PostgreSQL

PostgreSQL adalah database utama untuk data permanen aplikasi, termasuk user, grup/circle, membership, subscription, lokasi, dan riwayat lokasi.

### PostGIS

PostGIS digunakan untuk kebutuhan data geospasial, seperti menyimpan titik koordinat, menghitung jarak antar lokasi, dan menjalankan query berbasis lokasi jika dibutuhkan.

### Firebase

Firebase digunakan untuk update lokasi real-time agar mobile client dapat menerima perubahan lokasi dengan cepat.

Catatan:

- Jangan menganggap Firebase sebagai database utama aplikasi.
- Firebase digunakan untuk fitur real-time.
- Data permanen tetap harus mengikuti struktur PostgreSQL/PostGIS kecuali ada instruksi eksplisit yang menyatakan sebaliknya.
- Jika project sudah memilih antara Firebase Realtime Database dan Cloud Firestore, ikuti implementasi yang sudah ada.

### Redis

Redis digunakan untuk cache, temporary state, atau pemrosesan queue jika dibutuhkan.

Contoh penggunaan Redis:

- caching data yang sering diakses
- menyimpan state lokasi terbaru secara sementara
- menyimpan state jangka pendek
- Laravel queue jobs
- optimasi proses agar backend tidak selalu membaca dari database utama

Catatan:

- Jangan menyimpan data permanen utama di Redis.
- Redis hanya boleh digunakan sebagai cache, temporary storage, lock storage, throttle storage, atau queue backend.

### Flutter

Flutter adalah mobile client yang mengakses backend melalui REST API dan dapat menerima update lokasi real-time melalui Firebase.

## Konsep Utama Domain

- User: akun yang terdaftar di aplikasi
- Circle / Group: grup privat tempat pengguna dapat berbagi lokasi
- Member: pengguna yang tergabung dalam circle/group
- Location: lokasi terbaru pengguna
- Location History: catatan riwayat lokasi yang disimpan secara berkala
- Premium User: pengguna yang memiliki akses ke fitur tambahan, misalnya melihat riwayat lokasi
- Subscription: data langganan premium pengguna
- Subscription Payment: data pembayaran yang berkaitan dengan langganan premium pengguna

## Aturan Location Tracking

- Lokasi terbaru pengguna dapat dikirim ke backend melalui REST API.
- Untuk real-time tracking, lokasi terbaru dapat disinkronkan ke Firebase agar mobile client dapat menerima update dengan cepat.
- PostgreSQL/PostGIS digunakan untuk menyimpan data lokasi utama dan riwayat lokasi.
- Firebase tidak boleh dianggap sebagai sumber data permanen utama kecuali diminta secara eksplisit.
- Redis dapat digunakan untuk cache lokasi terbaru atau temporary state agar backend tidak selalu membaca dari database utama.
- Riwayat lokasi tidak boleh disimpan terlalu sering.
- Riwayat lokasi sebaiknya hanya disimpan ketika:
  - pengguna berpindah dalam jarak yang bermakna, misalnya sekitar 20 meter, atau
  - interval waktu tertentu sudah lewat, misalnya setiap 5 menit.
- Pengguna premium dapat mengakses riwayat lokasi, misalnya dalam batas waktu tertentu seperti 7 hari terakhir.

## Aturan Coding Backend

- Ikuti struktur project Laravel yang sudah ada.
- Jangan menulis ulang file yang tidak berkaitan.
- Jangan mengganti nama tabel, kolom, model, atau route yang sudah ada kecuali diminta secara eksplisit.
- Sebelum mengedit, periksa dulu file yang relevan.
- Buat perubahan seminimal mungkin dan tetap fokus pada task yang diminta.
- Gunakan kembali pattern yang sudah ada di project.
- Jangan membuat duplikasi logic jika sudah ada service, helper, atau method model yang relevan.
- Gunakan Form Request validation jika sesuai dengan pattern project yang sudah ada.
- Gunakan API Resource class jika project sudah menggunakannya.
- Gunakan database transaction untuk operasi yang mengubah beberapa tabel.
- Kembalikan response JSON yang konsisten.
- Tangani error validasi, otorisasi, dan data tidak ditemukan dengan benar.
- Jangan melakukan refactor besar kecuali diminta secara eksplisit.

## Aturan Database

- Gunakan migration untuk perubahan struktur database.
- Untuk koordinat lokasi, prioritaskan kolom PostGIS geometry/geography jika project sudah menggunakannya.
- Jangan mengedit migration lama kecuali diminta secara eksplisit.
- Tambahkan migration baru daripada mengedit migration lama.
- Buat foreign key, index, dan constraint jika sesuai.
- Gunakan index untuk field yang sering digunakan dalam pencarian, relasi, atau query lokasi.
- Untuk data riwayat lokasi, pertimbangkan index pada:
  - `user_id`
  - `recorded_at`
  - `location` atau kolom `geography` yang relevan jika menggunakan PostGIS

## Format Response API

Gunakan struktur JSON yang konsisten untuk response sukses:

```json
{
  "success": true,
  "message": "Location updated successfully",
  "data": {}
}
```

Gunakan struktur JSON yang konsisten untuk response error:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {}
}
```

Jika project sudah memiliki format response sendiri, ikuti format yang sudah ada daripada membuat format baru.

## Aturan Autentikasi dan Otorisasi

- Gunakan sistem autentikasi yang sudah ada di project.
- Jangan mengganti sistem autentikasi tanpa instruksi eksplisit.
- Endpoint yang membutuhkan user login harus menggunakan middleware autentikasi yang sesuai.
- Pastikan user hanya dapat mengakses datanya sendiri atau data dari circle/group yang memang dia ikuti.
- Untuk fitur circle/group, pastikan hanya owner/admin yang dapat melakukan aksi terbatas seperti mengundang anggota, menghapus anggota, atau mengubah pengaturan circle.

## Aturan Circle / Group

- User dapat membuat circle/group.
- User dapat bergabung ke circle/group sesuai mekanisme yang sudah diimplementasikan atau diminta.
- Membership harus dicek sebelum user dapat melihat lokasi anggota lain.
- Jangan mengekspos lokasi user kepada pihak yang tidak berada dalam circle/group yang sama.
- Cegah duplicate membership dalam circle/group yang sama.
- Jika terdapat role di dalam circle/group, gunakan role tersebut untuk validasi akses.

## Aturan Premium / Subscription

- Fitur premium dapat digunakan untuk membatasi akses ke fitur tertentu, seperti melihat riwayat lokasi.
- Jangan mengizinkan fitur premium jika user tidak memiliki subscription aktif.
- Status subscription harus dicek dari database utama, bukan hanya dari cache.
- Redis dapat digunakan untuk cache status subscription, tetapi PostgreSQL tetap menjadi sumber data utama.
- Jika terdapat payment gateway, ikuti implementasi yang sudah ada di project.

## Aturan Integrasi Firebase

- Gunakan Firebase hanya untuk update real-time.
- Jangan memindahkan business logic inti ke Firebase.
- Backend tetap menjadi pusat validasi dan business logic.
- Jangan menulis data sensitif ke Firebase kecuali diperlukan.
- Pastikan struktur data Firebase tidak mengekspos lokasi user kepada pihak yang tidak berwenang.
- Jika backend perlu mengupdate Firebase, lakukan hanya setelah validasi dan otorisasi berhasil.
- Jika update Firebase gagal, tangani error dengan aman.

## Aturan Integrasi Redis

- Gunakan Redis untuk data sementara, cache, throttle, lock, atau queue.
- Jangan menyimpan data permanen utama di Redis.
- Jika menggunakan cache, pastikan ada strategi invalidation yang jelas.
- Untuk proses yang rentan race condition, pertimbangkan penggunaan lock.
- Jangan menjadikan Redis sebagai satu-satunya sumber data untuk data penting.

## Ekspektasi Testing

Ketika menambah atau mengubah logic backend:

- Jelaskan file mana saja yang berubah.
- Jelaskan behavior apa yang berubah.
- Berikan langkah testing menggunakan API request.
- Berikan contoh request dan response jika relevan.
- Sebutkan command migration, seeder, queue, cache, atau environment yang harus dijalankan.
- Jika ada risiko breaking change, jelaskan secara eksplisit.

## Command Umum

Karena project ini menggunakan Docker, prioritaskan command melalui container.

Project ini menggunakan command `docker-compose` sesuai `DOCKER.md`. Jangan gunakan command PHP, Composer, PostgreSQL, atau Redis lokal kecuali diminta secara eksplisit.

Service Laravel bernama `app`.

```bash
docker-compose up -d
docker-compose down
docker-compose logs -f
docker-compose ps
docker-compose restart
docker-compose build --no-cache
```

Command Laravel harus dijalankan melalui container `app`:

```bash
docker-compose exec app php artisan migrate
docker-compose exec app php artisan route:list
docker-compose exec app php artisan test
docker-compose exec app php artisan queue:work
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan db:seed
```

Command Composer harus dijalankan melalui container `app`:

```bash
docker-compose exec app composer install
docker-compose exec app composer dump-autoload
```

Command troubleshooting database dan Redis:

```bash
docker-compose exec db pg_isready -U findmy_user -d findmy
docker-compose logs db
docker-compose exec redis redis-cli ping
docker-compose restart redis
```

Hindari menyarankan command lokal seperti:

```bash
php artisan serve
php artisan migrate
composer install
```

kecuali project memang sengaja dijalankan di luar Docker.

## Aturan Eksekusi Task

Sebelum coding:

- Baca instruksi di file ini.
- Periksa struktur folder project.
- Periksa `DOCKER.md`, `Dockerfile`, dan `docker-compose.yml` jika tersedia.
- Periksa route, controller, model, migration, service, dan config file yang relevan.
- Pahami format response dan coding pattern yang sudah ada.

Saat coding:

- Fokus hanya pada task yang diminta.
- Jangan mengubah file yang tidak berkaitan.
- Jangan melakukan refactor besar.
- Jangan menghapus logic lama tanpa alasan yang jelas.
- Jangan mengganti nama tabel, kolom, route, atau model tanpa instruksi.
- Jangan menambahkan dependency baru tanpa alasan yang jelas.
- Jika dependency baru dibutuhkan, jelaskan alasannya terlebih dahulu.

Setelah coding:

- Jelaskan perubahan yang dilakukan.
- Sebutkan file yang berubah.
- Berikan langkah testing.
- Sebutkan command yang perlu dijalankan.
- Jika menggunakan Docker, berikan command `docker-compose` yang sesuai.
- Jelaskan asumsi jika ada bagian yang belum jelas.

## Perilaku Penting

Jangan terus-menerus meminta seluruh konteks project lagi. Gunakan file ini sebagai konteks dasar project.

Jika ada hal yang belum jelas:

- Periksa kode yang sudah ada terlebih dahulu.
- Ikuti pattern yang sudah digunakan di project.
- Buat perubahan paling kecil yang aman.
- Jika masih ambigu, sebutkan asumsi yang digunakan sebelum atau setelah melakukan perubahan.

Jangan membuat implementasi besar berdasarkan asumsi yang tidak ada di project. Selalu prioritaskan konsistensi dengan struktur kode dan pattern project yang sudah ada.