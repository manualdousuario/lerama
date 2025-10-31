# 📰 Lerama

[![pt-br](https://img.shields.io/badge/lang-pt--br-green.svg)](https://github.com/manualdousuario/lerama/blob/master/README.md)
[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-purple.svg)](https://www.php.net/)
[![Docker](https://img.shields.io/badge/Docker-ready-blue.svg)](https://www.docker.com/)
[![GPL v3](https://img.shields.io/badge/license-GPL%20v3-blue.svg)](LICENSE.md)

Agregador de feeds moderno e simples feito como alternativa ao [OpenOrb](https://git.sr.ht/~lown/openorb) para o [PC do Manual](https://pcdomanual.com/).

🌐 **Instância pública**: [lerama.pcdomanual.com](https://lerama.pcdomanual.com/)

## ✨ Recursos

- 📡 Suporte a múltiplos formatos: RSS 1.0/2.0, ATOM, RDF, JSON, CSV, XML
- 🔍 Busca e filtros por feed, categoria e tag
- 🎨 Interface limpa e responsiva
- 🔄 Processamento automático de feeds

## 🚀 Instalação Rápida (Docker)

### Passo a Passo

1. Baixe o arquivo de configuração:
```bash
curl -o docker-compose.yml https://raw.githubusercontent.com/manualdousuario/lerama/main/docker-compose.yml
```

2. Edite as variáveis de ambiente:
```bash
nano docker-compose.yml
```

Configure pelo menos:
- `ADMIN_USERNAME` e `ADMIN_PASSWORD` (credenciais admin)
- `APP_URL` (URL pública do seu site)
- Credenciais do banco de dados

3. Inicie os containers:
```bash
docker-compose up -d
```

4. Acesse: `http://localhost:8077`

## 🎯 CLI

```bash
# Processar todos os feeds
php bin/lerama process

# Processar feed específico
php bin/lerama process --feed=123

# Importar feeds de CSV
php bin/lerama import feeds.csv
```

## 🔧 Configuração

### Variáveis de Ambiente Principais

```env
# Banco de dados
LERAMA_DB_HOST=localhost
LERAMA_DB_NAME=lerama
LERAMA_DB_USER=root
LERAMA_DB_PASS=senha

# Admin
ADMIN_USERNAME=admin
ADMIN_PASSWORD=senha_forte
ADMIN_EMAIL=admin@exemplo.com

# SMTP (opcional)
SMTP_HOST=smtp.exemplo.com
SMTP_PORT=587
SMTP_USERNAME=usuario
SMTP_PASSWORD=senha
SMTP_SECURE=tls

# Proxy (opcional)
PROXY_LIST=proxy1:port:user:pass,proxy2:port
```

## 💬 Suporte

- 🐛 Encontrou um bug? [Abra uma issue](https://github.com/manualdousuario/lerama/issues)
- 💡 Tem uma sugestão? [Abra uma issue](https://github.com/manualdousuario/lerama/issues)

---

Projeto inspirado no [OpenOrb](https://git.sr.ht/~lown/openorb).

Feito com ❤️ para o [PC do Manual](https://pcdomanual.com/)