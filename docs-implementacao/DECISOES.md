# Decisões de implementação

## Versões fixadas

As versões diretas foram resolvidas e gravadas nos lockfiles gerados; patches futuros só devem mudar junto com compatibilidade e testes.

| Componente | Versão |
|---|---|
| PHP disponível | 8.2.12 |
| Laravel Framework | 12.70.0 |
| Inertia Laravel | 2.0.28 |
| Laravel Breeze | 2.4.2 |
| Vue | 3.5.44 |
| Inertia Vue | 2.3.29 |
| TypeScript | 5.9.3 |
| Node.js / npm | 22.23.2 / 10.9.2 |
| Vite | 7.3.7 |
| Laravel Reverb | 1.12.0 |
| Laravel Echo / Echo Vue | 2.6.1 / 2.6.1 |
| Playwright Test | 1.64.1 |
| PostgreSQL | 17 (imagem `postgres:17-alpine`, configurada mas ainda não iniciada) |
| Fontsource Roboto / Lato / Inconsolata | 5.3.0 / 5.3.0 / 5.3.0 |
| Lucide Vue | `@lucide/vue` 1.58.0 |

Context7 confirma que Laravel 12 aceita PHP 8.2 e oferece o starter kit Vue/Inertia com TypeScript; o adaptador Inertia 2.0.28 foi resolvido com Laravel 12.62.0 e a atualização compatível corrigiu a base para 12.70.0. O scaffold foi executado com PHP 8.2.12, e `composer.lock` e `package-lock.json` preservam as versões instaladas. Docker Engine 29.2.1 está disponível. A imagem PostgreSQL 17 do projeto não foi iniciada.

## Fronteiras

- Monólito Laravel/Inertia/Vue; um PostgreSQL como estado compartilhado, filas no banco e armazenamento local privado.
- Reverb transmite mensagens apenas no canal privado `rooms.{id}`; a policy do canal consulta associação persistida. HTTP continua sendo a escrita durável.
- Owner cria a mesa; owner e master estão autorizados a administrar a mesa; jogadores entram e podem sair. Conteúdo e mensagens exigem associação, mesmo que a mesa apareça publicamente no catálogo.
- Senhas de mesas restritas são armazenadas com hash. O catálogo não enumera mesas privadas.
- SQLite local é apenas um modo de validação neste ambiente sem servidor PostgreSQL; `.env.example` aponta para PostgreSQL.
- Não há Redis/SSR/microserviços, execução de scripts/plugins legados, engine de dados, upload ou editor de fichas nesta parte.
- O atalho `composer dev` omite o tail interativo do Pail: o PHP Windows instalado não oferece `pcntl`. Os logs permanecem em `storage/logs/laravel.log`, e o processo web/Vite/fila/Reverb é iniciado normalmente.
- `composer dev` fixa o Vite em `127.0.0.1:5173` para não mudar silenciosamente de porta se `localhost` resolver para IPv6; o endpoint Echo local usa `REVERB_HOST=127.0.0.1`.

## Fontes, ícones e paleta

Roboto, Lato e Inconsolata são dependências npm Fontsource 5.3.0; o registro npm declara SIL OFL 1.1 para as três. Nenhum TTF do aplicativo original foi copiado. Ícones vêm do pacote `@lucide/vue` (ISC). Ilustrações dos cartões são gradientes e formas CSS originais.

Tokens de cor em `resources/css/app.css`: acento `#EB3449`, fundos `#212121`, `#272727`, `#333333`, superfície `#363B45` e texto do chat `#B9BABC`.

## Requisitos do host para PostgreSQL

Este ambiente tem PHP/Composer/Node e Docker Engine 29.2.1. Outros projetos usam as portas 5432 (`petsitters_postgres`) e 5433 (`cineingresso_db`); nenhum foi parado nem alterado. A porta 5434 estava livre na verificação. O PHP não carrega `pdo_pgsql`/`pgsql`, embora as DLLs estejam instaladas. Para usar o banco-alvo no Windows:

1. Se o Engine estiver parado, inicie Docker Desktop e confirme `docker version`.
2. Em `C:\xampp\php\php.ini`, confirme `extension_dir="C:\xampp\php\ext"` e habilite `extension=pdo_pgsql` e `extension=pgsql`; abra um terminal novo e confirme `php -m | findstr /I "pdo_pgsql pgsql"`.
3. Copie `.env.example` para `.env`, defina `DB_PASSWORD` com uma senha local e mantenha `DB_HOST=127.0.0.1`, `DB_PORT=5434`, `POSTGRES_HOST_PORT=5434`, `DB_DATABASE=freecast` e `DB_USERNAME=freecast`. Se 5434 também estiver ocupada, altere ambos os valores de porta para a mesma porta livre.
4. Execute `docker compose up -d postgres`, `composer install`, `npm ci`, `php artisan key:generate` e `php artisan migrate --seed`.
5. Inicie `composer dev` para HTTP, Vite, fila e Reverb; consulte os logs em `storage/logs/laravel.log`.

As extensões `pdo_pgsql`/`pgsql` permanecem desativadas e a instância deste projeto não foi iniciada. Os testes desta etapa usam SQLite; isso não substitui validação final no PostgreSQL.
