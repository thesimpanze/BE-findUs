@echo off
REM Docker management script untuk BE-findMy (Windows)

setlocal enabledelayedexpansion

if "%1"=="" (
    echo Docker Management Script for BE-findMy
    echo.
    echo Usage: %0 {command}
    echo.
    echo Available commands:
    echo   start       - Start all containers and run migrations
    echo   stop        - Stop all containers
    echo   restart     - Restart all containers
    echo   rebuild     - Rebuild Docker images
    echo   clean       - Remove all containers and volumes
    echo   logs        - View container logs
    echo   shell       - Open shell in app container
    echo   artisan     - Run artisan command
    echo   migrate     - Run database migrations
    echo   seed        - Seed the database
    echo   tinker      - Open Tinker shell
    echo   test        - Run tests
    echo   status      - Show container status
    goto :eof
)

if "%1"=="start" (
    echo [INFO] Starting Docker containers...
    if not exist .env (
        echo [INFO] Creating .env from .env.docker...
        copy .env.docker .env
    )
    docker-compose up -d
    echo [INFO] Waiting for services to be ready...
    timeout /t 5 /nobreak
    echo [INFO] Generating application key...
    docker-compose exec -T app php artisan key:generate
    echo [INFO] Running migrations...
    docker-compose exec -T app php artisan migrate
    echo [SUCCESS] All services started successfully!
    echo App: http://localhost:8000
    echo Adminer: http://localhost:8080
    goto :eof
)

if "%1"=="stop" (
    echo [INFO] Stopping Docker containers...
    docker-compose down
    echo [SUCCESS] Containers stopped
    goto :eof
)

if "%1"=="restart" (
    echo [INFO] Restarting Docker containers...
    docker-compose restart
    echo [SUCCESS] Containers restarted
    goto :eof
)

if "%1"=="rebuild" (
    echo [INFO] Rebuilding Docker images...
    docker-compose build --no-cache
    echo [SUCCESS] Images rebuilt
    goto :eof
)

if "%1"=="clean" (
    echo [WARNING] This will remove all containers, volumes, and networks
    set /p confirm="Are you sure? (y/n): "
    if /i "!confirm!"=="y" (
        echo [INFO] Cleaning up...
        docker-compose down -v
        echo [SUCCESS] Cleanup completed
    ) else (
        echo [INFO] Cleanup cancelled
    )
    goto :eof
)

if "%1"=="logs" (
    echo [INFO] Showing logs (press Ctrl+C to exit)...
    docker-compose logs -f
    goto :eof
)

if "%1"=="shell" (
    echo [INFO] Opening shell in app container...
    docker-compose exec app bash
    goto :eof
)

if "%1"=="artisan" (
    shift
    docker-compose exec app php artisan %*
    goto :eof
)

if "%1"=="migrate" (
    echo [INFO] Running migrations...
    docker-compose exec app php artisan migrate
    goto :eof
)

if "%1"=="seed" (
    echo [INFO] Seeding database...
    docker-compose exec app php artisan db:seed
    goto :eof
)

if "%1"=="tinker" (
    echo [INFO] Opening Tinker shell...
    docker-compose exec app php artisan tinker
    goto :eof
)

if "%1"=="test" (
    echo [INFO] Running tests...
    docker-compose exec app php artisan test
    goto :eof
)

if "%1"=="status" (
    echo [INFO] Container status:
    docker-compose ps
    goto :eof
)

echo Unknown command: %1
