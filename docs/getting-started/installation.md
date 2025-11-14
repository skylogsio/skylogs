## 🐳 Quick Start with Docker

### Prerequisites
- Docker & Docker Compose installed  
- Git  

### Installation

```bash
# Clone the repository
git clone https://github.com/skylogsio/skylogs.git
cd skylogs

# Copy environment file
cp .env.example .env

# Build and start the application
docker-compose up -d --build

# Generate application key
docker-compose exec app php artisan key:generate

# Run migrations
docker-compose exec app php artisan migrate

# (Optional) Seed initial data
docker-compose exec app php artisan db:seed

```
