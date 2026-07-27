# 📰 Lerama

[![Laravel 13](https://img.shields.io/badge/Laravel-13-red.svg)](https://laravel.com/)
[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-purple.svg)](https://www.php.net/)
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
- Multi-idioma: Português (`pt_BR`), Inglês (`en`), Espanhol (`es`)

---

## 🚀 Instalação

1. **Baixe o arquivo de configuração:**
   ```bash
   curl -o docker-compose.yml https://raw.githubusercontent.com/manualdousuario/lerama/main/compose.yml
   ```

2. **Configure as variáveis de ambiente:**
   ```bash
   nano compose.yml
   ```

   **Variáveis obrigatórias:**
   ```yaml
   APP_URL: https://seu-dominio.com
   LERAMA_DB_HOST: db
   LERAMA_DB_NAME: lerama
   LERAMA_DB_USER: root
   ADMIN_EMAIL: voce@seu-dominio.com
   ADMIN_PASSWORD: senha_com_8_ou_mais_caracteres
   ```

3. **Inicie os containers:**
   ```bash
   docker-compose up -d
   ```

4. **Acesse o sistema:**
   - Frontend: `http://localhost:8080`
   - Admin: `http://localhost:8080/admin`

   O painel administrativo usa [Filament](https://filamentphp.com/). O operador é
   criado no primeiro boot a partir de `ADMIN_EMAIL` e `ADMIN_PASSWORD` — o login
   é feito com o **e-mail**. Alterar `ADMIN_PASSWORD` e reiniciar o container
   redefine a senha.


---

## 🛠️ Comandos

```bash
php artisan feed:process              # Processa feeds agendados (roda a cada minuto)
php artisan feed:id {ID}              # Processa um feed específico
php artisan feed:check-status         # Verifica feeds pausados (roda 1x/dia)
php artisan feed:check-real-content   # Reclassifica visibilidade dos itens
php artisan lerama:setup-admin        # Cria/atualiza o operador a partir das ADMIN_*
php artisan filament:assets           # Republica os assets do painel
```

---

## 💬 Suporte

- 🐛 Encontrou um bug? [Abra uma issue](https://github.com/manualdousuario/lerama/issues)
- 💡 Tem uma sugestão? [Abra uma issue](https://github.com/manualdousuario/lerama/issues)

---

Feito com ❤️ para o [PC do Manual](https://pcdomanual.com/)
