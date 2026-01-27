# 📰 Lerama

[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-purple.svg)](https://www.php.net/)
[![Docker](https://img.shields.io/badge/Docker-ready-blue.svg)](https://www.docker.com/)
[![GPL v3](https://img.shields.io/badge/license-GPL%20v3-blue.svg)](LICENSE.md)
[![en](https://img.shields.io/badge/lang-en-red.svg)](https://github.com/manualdousuario/lerama/blob/master/README.en.md)

Agregador de feeds leve e eficiente, desenvolvido como alternativa ao [OpenOrb](https://git.sr.ht/~lown/openorb) para o [PC do Manual](https://pcdomanual.com/).

🌐 **Instância pública**: [lerama.pcdomanual.com](https://lerama.pcdomanual.com/)

---

## ✨ Recursos

  - RSS 1.0, RSS 2.0, ATOM, RDF, JSON Feed
  - Importação via CSV
  - Filtro por feed individual, categorias e tópicos/tags
  - Busca textual em títulos e conteúdo
  - Processamento em lote
  - Atualização incremental
  - Suporte a proxy para feeds bloqueados
  - Download automático de thumbnails
  - Cache de imagens
  - Gerenciamento de feeds, categorias e tags
  - Sugestões da comunidade
  - Multi-idioma: Português (pt-BR), Inglês (en), Espanhol (es)

---

## 🚀 Instalação

1. **Baixe o arquivo de configuração:**
   ```bash
   curl -o docker-compose.yml https://raw.githubusercontent.com/manualdousuario/lerama/main/docker-compose.yml
   ```

2. **Configure as variáveis de ambiente:**
   ```bash
   nano docker-compose.yml
   ```

   **Variáveis obrigatórias:**
   ```yaml
   ADMIN_USERNAME: seu_usuario    # Usuário admin
   ADMIN_PASSWORD: senha_forte    # Senha do admin (min. 8 caracteres)
   APP_URL: https://seu-dominio.com
   
   # Banco de dados
   LERAMA_DB_HOST: db
   LERAMA_DB_NAME: lerama
   LERAMA_DB_USER: root
   LERAMA_DB_PASS: senha_segura
   ```

3. **Inicie os containers:**
   ```bash
   docker-compose up -d
   ```

4. **Acesse o sistema:**
   - Frontend: `http://localhost:80`
   - Admin: `http://localhost:80/admin`

---

## 💬 Suporte

- 🐛 Encontrou um bug? [Abra uma issue](https://github.com/manualdousuario/lerama/issues)
- 💡 Tem uma sugestão? [Abra uma issue](https://github.com/manualdousuario/lerama/issues)

---

Feito com ❤️ para o [PC do Manual](https://pcdomanual.com/)