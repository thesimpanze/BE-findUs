#!/bin/bash

# Docker management script untuk BE-findMy

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Check if .env file exists
check_env() {
    if [ ! -f .env ]; then
        print_warning ".env file not found"
        print_info "Creating .env from .env.docker..."
        cp .env.docker .env
    fi
}

# Main command handler
case "$1" in
    start)
        print_info "Starting Docker containers..."
        check_env
        docker-compose up -d
        print_info "Waiting for services to be ready..."
        sleep 5
        print_info "Generating application key..."
        docker-compose exec -T app php artisan key:generate
        print_info "Running migrations..."
        docker-compose exec -T app php artisan migrate
        print_info "✓ All services started successfully!"
        print_info "App: http://localhost:8000"
        print_info "PhpMyAdmin: http://localhost:8080"
        ;;

    stop)
        print_info "Stopping Docker containers..."
        docker-compose down
        print_info "✓ Containers stopped"
        ;;

    restart)
        print_info "Restarting Docker containers..."
        docker-compose restart
        print_info "✓ Containers restarted"
        ;;

    rebuild)
        print_info "Rebuilding Docker images..."
        docker-compose build --no-cache
        print_info "✓ Images rebuilt"
        ;;

    clean)
        print_warning "This will remove all containers, volumes, and networks"
        read -p "Are you sure? (y/n) " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            print_info "Cleaning up..."
            docker-compose down -v
            print_info "✓ Cleanup completed"
        else
            print_info "Cleanup cancelled"
        fi
        ;;

    logs)
        print_info "Showing logs (press Ctrl+C to exit)..."
        docker-compose logs -f
        ;;

    shell)
        print_info "Opening shell in app container..."
        docker-compose exec app bash
        ;;

    artisan)
        shift
        docker-compose exec app php artisan "$@"
        ;;

    migrate)
        print_info "Running migrations..."
        docker-compose exec app php artisan migrate
        ;;

    seed)
        print_info "Seeding database..."
        docker-compose exec app php artisan db:seed
        ;;

    tinker)
        print_info "Opening Tinker shell..."
        docker-compose exec app php artisan tinker
        ;;

    test)
        print_info "Running tests..."
        docker-compose exec app php artisan test
        ;;

    status)
        print_info "Container status:"
        docker-compose ps
        ;;

    *)
        echo "Docker Management Script for BE-findMy"
        echo ""
        echo "Usage: $0 {command}"
        echo ""
        echo "Available commands:"
        echo "  start       - Start all containers and run migrations"
        echo "  stop        - Stop all containers"
        echo "  restart     - Restart all containers"
        echo "  rebuild     - Rebuild Docker images"
        echo "  clean       - Remove all containers and volumes"
        echo "  logs        - View container logs"
        echo "  shell       - Open shell in app container"
        echo "  artisan     - Run artisan command (e.g., ./docker.sh artisan make:model)"
        echo "  migrate     - Run database migrations"
        echo "  seed        - Seed the database"
        echo "  tinker      - Open Tinker shell"
        echo "  test        - Run tests"
        echo "  status      - Show container status"
        echo ""
        exit 1
        ;;
esac
