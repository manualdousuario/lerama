
# Lerama

O Lerama é um agregador de feeds ATOM e RSS2.0 feito como alternativa ao [OpenOrb](https://git.sr.ht/~lown/openorb) para o [PC do Manual](https://pcdomanual.com/).

*Essa é a primeira vez que faço uma integração completa de um projeto com imagens Docker, toda melhoria, correção, ajuste e PR`s são bem vindas!*

## Docker

Apos instalar o docker, vamos criar um *compose*:

`curl -o ./docker-compose.yml https://raw.githubusercontent.com/manualdousuario/lerama/main/docker-compose.yml`

`nano docker-compose.yml`

```
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
services:
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

Atualize as informações dos environments e em seguida pode rodar `docker compose up -d`
Todos as tags de environment são obrigatorias.

Antes de começar, precisamos criar as tabelas do banco de dados.

`docker exec -it db mysql -u USUARIO -pSENHA BANCO_DE_DADOS`

```
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

Após executar o SQL, você pode verificar se as tabelas foram criadas com sucesso: `SHOW TABLES`;

## Informações adicionais

Recomendo que utilize o [NGINX Proxy Manager](https://nginxproxymanager.com/) como webservice a frente dessa imagem, isso dará mais proteção e camadas de cache.

As rotinas de coleta de dados irão rodar a cada hora e o log pode ser visto em `/var/log/lorema.log`

Uma instalação pública está disponivel em [PC do Manual](https://lerama.pcdomanual.com/) 