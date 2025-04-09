# 📰 Lerama

[![pt-br](https://img.shields.io/badge/lang-pt--br-green.svg)](https://github.com/manualdousuario/lerama/blob/master/README.md)

O Lerama é um agregador de feeds ATOM, RSS1.0/2.0, JSON, CSV, XML feito como alternativa ao [OpenOrb](https://git.sr.ht/~lown/openorb) para o [PC do Manual](https://pcdomanual.com/).

## ✨ Recursos

- Agregação automática de feeds ATOM, RSS1.0/2.0, JSON, CSV, XML
- Sistema de detecção e gestão de erros
- Busca dos artigos e filtros
- Interface limpa e otimizada
- Suporte a múltiplos sites

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
    image: ghcr.io/altendorfme/lerama:latest
    container_name: lerama
    environment:
      TZ: ${TZ:-UTC}
      APP_URL: https://lerama.lab
      MYSQL_HOST: localhost
      MYSQL_PORT: 3306
      MYSQL_DATABASE: lerama
      MYSQL_USERNAME: root
      MYSQL_PASSWORD: root
      ADMIN_USERNAME: admin
      ADMIN_PASSWORD: admin
    ports:
      - 8077:8077
    volumes:
      - ./lerama/storage:/app/public/storage
    restart: unless-stopped
    networks:
      - lerama
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
    networks:
      - lerama
networks:
  lerama:
    driver: bridge
```

### Coleta de Dados

A coleta de feeds é executada automaticamente a cada hora. Você pode monitorar o processo através dos logs.

---

Feito com ❤️! Se tiver dúvidas, sugestões ou encontrar problemas, abra uma issue que a gente ajuda! 😉

Instância pública disponível em [lerama.pcdomanual.com](https://lerama.pcdomanual.com/)