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

### Coleta de dados

A coleta de feeds é executada automaticamente a cada hora. Você pode monitorar o processo através dos logs.

---

Feito com ❤️! Se tiver dúvidas, sugestões ou encontrar problemas, abra uma issue que a gente ajuda! 😉

Instância pública disponível em [lerama.pcdomanual.com](https://lerama.pcdomanual.com/)