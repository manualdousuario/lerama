# 📰 Lerama

[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-purple.svg)](https://www.php.net/)
[![Docker](https://img.shields.io/badge/Docker-ready-blue.svg)](https://www.docker.com/)
[![GPL v3](https://img.shields.io/badge/license-GPL%20v3-blue.svg)](LICENSE.md)
[![pt-br](https://img.shields.io/badge/lang-pt--br-green.svg)](https://github.com/manualdousuario/lerama/blob/master/README.md)

Lightweight and efficient feed aggregator, developed as an alternative to [OpenOrb](https://git.sr.ht/~lown/openorb) for [PC do Manual](https://pcdomanual.com/).

🌐 **Public instance**: [lerama.pcdomanual.com](https://lerama.pcdomanual.com/)

---

## ✨ Features

  - RSS 1.0, RSS 2.0, ATOM, RDF, JSON Feed
  - CSV import
  - Filter by individual feed, categories and topics/tags
  - Text search in titles and content
  - Cron scheduling
  - Batch processing
  - Incremental updates
  - Proxy support for blocked feeds
  - Automatic thumbnail download
  - Image caching
  - Feed, category and tag management
  - Community suggestions
  - Multi-language: Portuguese (pt-BR), English (en), Spanish (es)

---

## 🚀 Installation

1. **Download the configuration file:**
   ```bash
   curl -o docker-compose.yml https://raw.githubusercontent.com/manualdousuario/lerama/main/docker-compose.yml
   ```

2. **Configure environment variables:**
   ```bash
   nano docker-compose.yml
   ```

   **Required variables:**
   ```yaml
   ADMIN_USERNAME: your_username    # Admin user
   ADMIN_PASSWORD: strong_password  # Admin password (min. 8 characters)
   APP_URL: https://your-domain.com
   
   # Database
   LERAMA_DB_HOST: db
   LERAMA_DB_NAME: lerama
   LERAMA_DB_USER: root
   LERAMA_DB_PASS: secure_password
   ```

   **Cron:**
   ```yaml
   CRONTAB_PROCESS_FEEDS: "0 */4 * * *"  # Feed processing (default: every 4 hours)
   CRONTAB_FEED_STATUS: "0 0 * * *"      # Feed status check (default: midnight)
   CRONTAB_PROXY: "0 0 * * *"            # Proxy list update (default: midnight)
   ```

3. **Start the containers:**
   ```bash
   docker-compose up -d
   ```

4. **Access the system:**
   - Frontend: `http://localhost:80`
   - Admin: `http://localhost:80/admin`

---

## 💬 Support

- 🐛 Found a bug? [Open an issue](https://github.com/manualdousuario/lerama/issues)
- 💡 Have a suggestion? [Open an issue](https://github.com/manualdousuario/lerama/issues)

---

Made with ❤️ for [PC do Manual](https://pcdomanual.com/)