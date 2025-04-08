# Lerama Feed Aggregator

A PHP-based feed aggregator application that supports multiple feed formats including RSS, Atom, RDF, CSV, JSON, and XML.

## Features

- Collect and aggregate content from various feed formats
- Web interface to browse aggregated content
- Admin panel to manage feeds and content
- CLI command for feed processing
- JSON and RSS API endpoints
- Search functionality
- Responsive design with TailwindCSS
- Automatic feed type detection

## Requirements

- PHP 8.4 or higher
- MariaDB/MySQL
- Composer

## Installation

1. Clone the repository:
   ```
   git clone https://github.com/yourusername/lerama.git
   cd lerama
   ```

2. Install dependencies:
   ```
   composer install
   ```

3. Create a `.env` file:
   ```
   cp .env.example .env
   ```

4. Update the `.env` file with your database credentials and other settings.

5. Create the database and tables (choose one method):
   
   **Method 1:** Using the setup script:
   ```
   php setup-database.php
   ```
   
   **Method 2:** Manually with MySQL:
   ```
   mysql -u username -p < database/schema.sql
   ```

6. Make the CLI command executable (Linux/macOS only):
   ```
   chmod +x bin/lerama
   ```
   
   On Windows, you can run the CLI command using:
   ```
   php bin/lerama
   ```

## Deployment

You can use the provided deployment script to simplify the installation and setup process (Linux/macOS):

```
chmod +x deploy.sh
./deploy.sh --help
```

For Windows users, you can use the provided batch script:

```
deploy.bat --help
```

This batch script provides similar functionality to the bash script but is designed for Windows systems.

Available options:

- `--install`: Install dependencies and set up the application
- `--update`: Update the application (git pull and composer update)
- `--setup-nginx`: Set up NGINX configuration
- `--setup-cron`: Set up cron jobs
- `--setup-database`: Set up the database
- `--docker`: Deploy using Docker

Examples:

```
./deploy.sh --install
./deploy.sh --setup-nginx
./deploy.sh --docker
```

You can also run multiple options together:

```
./deploy.sh --install --setup-database --setup-cron
```

## Usage

```
   chmod +x bin/lerama
   ```

## Usage

### Web Interface

You can use either PHP's built-in development server or NGINX to serve the application.

#### Using PHP's built-in server (for development)

```
php -S localhost:8000 -t public
```

Then visit `http://localhost:8000` in your browser.

#### Using NGINX (recommended for production)

1. Copy the provided `nginx.conf` file to your NGINX configuration directory:
   ```
   sudo cp nginx.conf /etc/nginx/sites-available/lerama
   ```

2. Create a symbolic link to enable the site:
   ```
   sudo ln -s /etc/nginx/sites-available/lerama /etc/nginx/sites-enabled/
   ```

3. Edit the configuration file to update the server_name and root path:
   ```
   sudo nano /etc/nginx/sites-available/lerama
   ```

4. Test the NGINX configuration:
   ```
   sudo nginx -t
   ```

5. Restart NGINX:
   ```
   sudo systemctl restart nginx
   ```

6. Make sure PHP-FPM is installed and running:
   ```
   sudo apt install php8.4-fpm
   sudo systemctl start php8.4-fpm
   sudo systemctl enable php8.4-fpm
   ```

7. Visit your domain in the browser.

### CLI Command

Process all feeds:

```
./bin/lerama --process
```

Process a specific feed by ID:

```
./bin/lerama --process --feed=1
```

Show help:

```
./bin/lerama --help
```

### Automating Feed Processing with Cron

A crontab file is provided to help you set up automated feed processing:

1. Edit the crontab file to update the paths:
   ```
   nano crontab
   ```

2. Install the cron jobs (as root or with sudo):
   ```
   cp crontab /etc/cron.d/lerama
   chmod 644 /etc/cron.d/lerama
   ```

3. Verify the cron job is installed:
   ```
   crontab -l
   ```

The default configuration processes all feeds every hour and cleans up old items daily.

## Feed Formats Supported

- RSS 1.0 and 2.0
- Atom
- RDF
- CSV
- JSON
- XML

## Directory Structure

- `bin/` - CLI commands
- `config/` - Configuration files
- `database/` - Database schema and migrations
- `public/` - Public-facing files (web entry point)
- `src/` - Application source code
  - `Commands/` - CLI command classes
  - `Controllers/` - Web controllers
  - `Middleware/` - HTTP middleware
- `templates/` - View templates
  - `admin/` - Admin panel templates

## Docker Deployment

The application can be easily deployed using Docker and Docker Compose.

### Prerequisites

- Docker
- Docker Compose

### Deployment Steps

1. Clone the repository:
   ```
   git clone https://github.com/yourusername/lerama.git
   cd lerama
   ```

2. Create a `.env` file:
   ```
   cp .env.example .env
   ```

3. Update the `.env` file with your settings. For Docker deployment, set:
   ```
   DB_HOST=db
   DB_USER=lerama
   DB_PASS=your_password
   DB_NAME=lerama
   DB_PORT=3306
   ```

4. Build and start the containers:
   ```
   docker-compose up -d
   ```

5. Access the application:
   - Web interface: http://localhost:8080
   - phpMyAdmin: http://localhost:8081

### Running CLI Commands in Docker

To run the feed processor in Docker:

```
docker-compose exec app php bin/lerama --process
```

### Stopping the Containers

```
docker-compose down
```

## License

MIT