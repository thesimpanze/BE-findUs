# Docker Setup untuk BE-findMy

Panduan lengkap untuk menjalankan proyek Laravel dengan Docker.

## Prasyarat

- Docker (versi 20.10+)
- Docker Compose (versi 1.29+)
- Git

## Struktur Docker

Proyek ini menggunakan beberapa service:

- **app**: Aplikasi Laravel (PHP 8.3-FPM)
- **nginx**: Web server Nginx
- **db**: Database MySQL 8.0
- **redis**: Cache Redis 7
- **phpmyadmin**: Management tool untuk database

## Cara Menjalankan

### 1. Setup Awal

```bash
# Clone repository
git clone <repository-url>
cd BE-findMy

# Copy environment file
cp .env.docker .env

# Build images
docker-compose build
# Start containers
docker-compose up -d

docker-compose exec app composer install
docker-compose exec app chmod -R 777 storage bootstrap/cache
# Generate app key
docker-compose exec app php artisan key:generate

# Run migrations
docker-compose exec app php artisan migrate

# Seed database (opsional)
docker-compose exec app php artisan db:seed
```

### 2. Akses Aplikasi

- **Aplikasi Laravel**: http://localhost:8000
- **phpMyAdmin**: http://localhost:8080
    - Username: `findmy_user`
    - Password: `findmy_password`
    - Server: `db`

### 3. Command Dasar

```bash
# Jalankan containers
docker-compose up -d

# Lihat logs
docker-compose logs -f

# Stop containers
docker-compose down

# Rebuild images
docker-compose build --no-cache

# Akses shell di container app
docker-compose exec app bash

# Jalankan artisan commands
docker-compose exec app php artisan <command>

# Jalankan migrations
docker-compose exec app php artisan migrate

# Jalankan seeders
docker-compose exec app php artisan db:seed

# Clear caches
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear

# Restart containers
docker-compose restart

# Remove all containers, volumes, dan networks
docker-compose down -v
```

## Variabel Environment

Edit file `.env` untuk mengubah konfigurasi:

- `APP_KEY`: Kunci aplikasi (generate dengan artisan)
- `DB_*`: Konfigurasi database
- `REDIS_*`: Konfigurasi Redis
- `CACHE_DRIVER`: Driver cache (default: redis)
- `SESSION_DRIVER`: Driver session (default: redis)

## Port yang Digunakan

- `8000`: Aplikasi Laravel
- `80`: Nginx web server
- `443`: Nginx HTTPS (belum dikonfigurasi)
- `3306`: MySQL database
- `6379`: Redis
- `8080`: phpMyAdmin

## Troubleshooting

### Container tidak bisa dijalankan

```bash
# Check logs
docker-compose logs app

# Rebuild images
docker-compose build --no-cache

# Restart semua service
docker-compose down -v
docker-compose up -d
```

### Database connection error

```bash
# Pastikan db container sudah healthy
docker-compose ps

# Check database logs
docker-compose logs db

# Run migrations
docker-compose exec app php artisan migrate
```

### Permission denied di storage

```bash
# Fix permissions
docker-compose exec app chmod -R 775 storage bootstrap/cache
```

### Redis connection error

```bash
# Check redis container
docker-compose exec redis redis-cli ping

# Restart redis
docker-compose restart redis
```

## Production Setup

Untuk environment production:

1. Ubah `APP_ENV` menjadi `production`
2. Set `APP_DEBUG` menjadi `false`
3. Gunakan environment variables yang aman
4. Setup HTTPS dengan SSL certificate
5. Configure proper logging dan monitoring
6. Optimize Docker images (use multi-stage build)

## Performance Tips

- Gunakan `.dockerignore` untuk exclude unnecessary files
- Cache Docker layers dengan ordering commands yang tepat
- Use named volumes untuk persistent data
- Limit resource usage dengan docker-compose resource limits
- Monitor performance dengan `docker stats`

## Dokumentasi Tambahan

- [Laravel Documentation](https://laravel.com/docs)
- [Docker Documentation](https://docs.docker.com)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [Nginx Documentation](https://nginx.org/en/docs/)

## Support

Untuk bantuan lebih lanjut, silakan buat issue di repository ini.
