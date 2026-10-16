# Freecast

Aplicação web independente para jogar RPG com amigos. Esta entrega cobre autenticação, perfis, mesas e a interface inicial de início e mesa.

## Requisitos

- PHP 8.2+ com `pdo_pgsql` e `pgsql` para o banco de destino.
- Composer 2, Node.js 22 e npm.
- Docker Desktop para iniciar PostgreSQL 17 pelo Compose.

## Iniciar no Windows

1. Inicie Docker Desktop. No PowerShell, confirme `docker version`.
2. Copie `.env.example` para `.env` e configure `DB_PASSWORD` com uma senha local. O PostgreSQL do projeto publica `127.0.0.1:5434`, evitando conflito com os serviços que já usam as portas 5432 e 5433 neste host; mantenha `DB_PORT=5434` e `POSTGRES_HOST_PORT=5434`.
3. Habilite `extension=pdo_pgsql` e `extension=pgsql` em `C:\xampp\php\php.ini` (as DLLs já existem em `C:\xampp\php\ext`); abra um terminal novo e confirme `php -m | findstr /I "pdo_pgsql pgsql"`.
4. Na raiz do projeto:

   ```powershell
   composer install
   npm ci
   php artisan key:generate
   docker compose up -d postgres
   php artisan migrate --seed
   composer dev
   ```

5. Abra `http://127.0.0.1:8000`. O comando `composer dev` inicia HTTP, Vite, fila do banco e Reverb. Os logs continuam em `storage/logs/laravel.log`; o tail interativo do Pail não é iniciado porque exige `pcntl`, indisponível no PHP do Windows.

Neste host, as portas 5432 e 5433 já pertencem a containers de outros projetos; o Compose usa 5434 para não os interromper. `.env.example` mantém PostgreSQL como padrão. A validação desta entrega usou SQLite porque o PHP atual ainda não carrega `pdo_pgsql`; não a trate como validação PostgreSQL.

## Contas sintéticas do seed

`php artisan migrate --seed` opcionalmente cria três contas de demonstração e uma mesa. As contas são somente locais:

| Papel | E-mail | Senha |
|---|---|---|
| Proprietária/mestre | `mestre@freecast.test` | `Mesa2026!` |
| Jogador | `jogador@freecast.test` | `Mesa2026!` |
| Jogadora convidada | `convidada@freecast.test` | `Mesa2026!` |

Troque ou remova essas contas antes de expor qualquer ambiente. As senhas não devem ser reutilizadas.

## Verificações

```powershell
php artisan test
npm run build
npm run test:e2e
```

Playwright Test usa Chromium. Se não estiver instalado ainda, execute `npx playwright install chromium`. Os testes de navegador usam o banco configurado pelo ambiente da aplicação e esperam as contas sintéticas do seed.

## Continuidade

- [Progresso da Parte 1](docs-implementacao/PROGRESSO.md)
- [Decisões e versões](docs-implementacao/DECISOES.md)
- [Matriz de paridade](docs-implementacao/PARIDADE.md)
