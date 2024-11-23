# 📰 Lerama

[![pt-br](https://img.shields.io/badge/lang-pt--br-green.svg)](https://github.com/manualdousuario/lerama/blob/master/README.md)

O Lerama é um agregador de feeds ATOM e RSS2.0 feito como alternativa ao [OpenOrb](https://git.sr.ht/~lown/openorb) para o [PC do Manual](https://pcdomanual.com/).

## ✨ Recursos

- Agregação automática de feeds ATOM e RSS2.0
- Coleta automática de dados a cada hora
- Sistema de detecção e gestão de erros
- Busca em texto completo dos artigos
- Interface limpa e otimizada
- Suporte a múltiplos sites
- Sistema de cache eficiente
- Banco de dados MariaDB para armazenamento robusto

## 🐳 Docker

### Antes de começar

Só precisa ter instalado:
- Docker e docker compose

### Produção

1. Baixe o arquivo de configuração:
```bash
curl -o ./docker-compose.yml https://raw.githubusercontent.com/manualdousuario/lerama/main/docker-compose.yml
```

2. Configure o ambiente:
```bash
nano docker-compose.yml
```

```yaml
services:
  lerama:
    container_name: lerama
    image: ghcr.io/manualdousuario/lerama:latest
    ports:
      - "80:80"
    environment:
      DB_HOST: mariadb
      DB_USERNAME: USUARIO
      DB_PASSWORD: SENHA
      DB_NAME: BANCO_DE_DADOS
      SITE_URL: https://lerama.xyz
      SITE_NAME: Lerama
      ADMIN_PASSWORD: p@ssw0rd
    depends_on:
      - db
  db:
    image: mariadb:10.11
    container_name: db
    environment:
      MYSQL_ROOT_PASSWORD: SENHA_ROOT
      MYSQL_DATABASE: BANCO_DE_DADOS
      MYSQL_USER: USUARIO
      MYSQL_PASSWORD: SENHA
    ports:
      - 3306:3306
    volumes:
      - ./mariadb/data:/var/lib/mysql
```

### Configuração do Banco de Dados

1. Inicie os containers:
```bash
docker compose up -d
```

2. Acesse o MySQL e crie as tabelas:
```bash
docker exec -it db mysql -u USUARIO -pSENHA BANCO_DE_DADOS
```

```sql
CREATE TABLE IF NOT EXISTS sites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    url VARCHAR(255) NOT NULL,
    feed_url VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    error_count INT DEFAULT 0,
    last_error_check TIMESTAMP NULL DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255),
    publication_date DATETIME NOT NULL,
    link VARCHAR(255) NOT NULL,
    unique_identifier VARCHAR(255) NOT NULL,
    FOREIGN KEY (site_id) REFERENCES sites(id)
);

CREATE FULLTEXT INDEX idx_title_fulltext ON articles (title);
```

Verifique se as tabelas foram criadas: `SHOW TABLES;`

## ⚙️ Recomendações

- Utilize o [NGINX Proxy Manager](https://nginxproxymanager.com/) como webservice para maior proteção e camadas de cache
- Configure corretamente todas as variáveis de ambiente antes de iniciar
- Mantenha backups regulares do banco de dados

## 🛠️ Manutenção

### Logs

Para acompanhar a execução:
```bash
tail -f /var/log/lorema.log
```

### Coleta de Dados

A coleta de feeds é executada automaticamente a cada hora. Você pode monitorar o processo através dos logs.

---

Feito com ❤️! Se tiver dúvidas, sugestões ou encontrar problemas, abra uma issue que a gente ajuda! 😉

Instância pública disponível em [lerama.pcdomanual.com](https://lerama.pcdomanual.com/)
