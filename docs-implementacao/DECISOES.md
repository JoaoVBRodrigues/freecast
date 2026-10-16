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
| PostgreSQL | 17 (imagem `postgres:17-alpine`, validada em `127.0.0.1:5434`) |
| Fontsource Roboto / Lato / Inconsolata | 5.3.0 / 5.3.0 / 5.3.0 |
| Lucide Vue | `@lucide/vue` 1.58.0 |
| Vue Konva | `vue-konva` 4.0.1 / Konva 10.9.0 |

Context7 confirma que Laravel 12 aceita PHP 8.2 e oferece o starter kit Vue/Inertia com TypeScript; o adaptador Inertia 2.0.28 foi resolvido com Laravel 12.62.0 e a atualização compatível corrigiu a base para 12.70.0. O scaffold foi executado com PHP 8.2.12, e `composer.lock` e `package-lock.json` preservam as versões instaladas. Docker Engine 29.2.1 e PostgreSQL 17 foram validados; o único serviço do Compose iniciado foi o PostgreSQL deste projeto, na porta 5434.

## Fronteiras

- Monólito Laravel/Inertia/Vue; um PostgreSQL como estado compartilhado, filas no banco e armazenamento local privado.
- Reverb transmite mensagens apenas no canal privado `rooms.{id}`; a policy do canal consulta associação persistida. HTTP continua sendo a escrita durável.
- Owner cria a mesa; owner e master estão autorizados a administrar a mesa; jogadores entram e podem sair. Conteúdo e mensagens exigem associação, mesmo que a mesa apareça publicamente no catálogo.
- Senhas de mesas restritas são armazenadas com hash. O catálogo não enumera mesas privadas.
- PostgreSQL é o banco de desenvolvimento ativo. A base SQLite anterior permanece em `database/database.sqlite` sem migração ou alteração dos dados.
- Não há Redis/SSR/microserviços, execução de scripts/plugins legados, engine de dados ou editor de fichas nas Partes 1 e 2.
- O atalho `composer dev` omite o tail interativo do Pail: o PHP Windows instalado não oferece `pcntl`. Os logs permanecem em `storage/logs/laravel.log`, e o processo web/Vite/fila/Reverb é iniciado normalmente.
- `composer dev` fixa o Vite em `127.0.0.1:5173` para não mudar silenciosamente de porta se `localhost` resolver para IPv6; o endpoint Echo local usa `REVERB_HOST=127.0.0.1`.
- A Parte 1 não incluía upload nem editor de mapas; a Parte 2 adiciona esses recursos sem mudar o limite arquitetural do monólito, storage privado, fila no banco ou dependência de PostgreSQL para estado compartilhado.

## Mapas e biblioteca — Parte 2

- Mapas, pastas, recursos, camadas e objetos pertencem à mesa e são persistidos no banco. Imagens usam o disco privado `local`, com upload limitado a 20 MB e dimensões máximas de 8192 × 8192; o download sempre passa por autorização HTTP.
- Camadas, objetos invisíveis e assets exclusivos do mestre não são serializados para jogadores. Jogadores recebem apenas camadas públicas visíveis e só podem alterar `x/y` de tokens visíveis, desbloqueados e atribuídos à própria conta; mestres controlam todos.
- Câmera e opção de encaixe são preferências locais no `localStorage`, indexadas por usuário/mapa. Dimensões, grade, camadas, ordem e propriedades/posições dos tokens são compartilhadas no banco.
- `mapCoordinates.ts` concentra conversões tela/mundo, zoom ancorado e encaixe. O `dragBoundFunc` do Konva recebe/devolve coordenadas absolutas do canvas; o editor converte para coordenadas de mundo antes de aplicar snap/limites e persiste a posição local do nó.
- `vue-konva` 4.0.1 e Konva 10.9.0 foram escolhidos para Vue 3 após confirmação no Context7; as versões ficam travadas em `package-lock.json`. O editor original não foi validado visualmente; canvas e interações desta etapa são decisões próprias, sem alegação de paridade comprovada.

## Fontes, ícones e paleta

Roboto, Lato e Inconsolata são dependências npm Fontsource 5.3.0; o registro npm declara SIL OFL 1.1 para as três. Nenhum TTF do aplicativo original foi copiado. Ícones vêm do pacote `@lucide/vue` (ISC). Ilustrações dos cartões são gradientes e formas CSS originais.

Tokens de cor em `resources/css/app.css`: acento `#EB3449`, fundos `#212121`, `#272727`, `#333333`, superfície `#363B45` e texto do chat `#B9BABC`.

## Validação PostgreSQL — host Windows

- `where.exe php` identificou `C:\xampp\php\php.exe`; `php --ini` confirmou `C:\xampp\php\php.ini` como configuração efetivamente carregada. `pdo_pgsql` e `pgsql` estavam comentadas nesse arquivo, embora as DLLs existissem.
- Foi feito backup em `C:\xampp\php\php.ini.freecast-postgres-20261016204746.bak`; somente `;extension=pdo_pgsql` e `;extension=pgsql` foram habilitadas. `php -m` confirmou os dois módulos carregados.
- `docker compose up -d postgres` iniciou apenas `freecast-postgres-1`, imagem `postgres:17-alpine`, saudável e publicada em `127.0.0.1:5434`. A porta 5434 foi conferida livre antes da inicialização; nenhum container de outro projeto foi parado ou alterado.
- O `.env` existente foi ajustado somente nas variáveis da conexão PostgreSQL (`DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `POSTGRES_HOST_PORT`). A `APP_KEY` foi preservada, o segredo local não foi impresso nem registrado e o arquivo continua ignorado pelo Git. Não foi substituído pelo `.env.example`.
- A base de desenvolvimento `freecast` estava vazia. `php artisan config:clear`, `php artisan migrate --database=pgsql --force` e `php artisan db:seed --database=pgsql --force` aplicaram as seis migrations e as três contas sintéticas. Uma segunda execução do seed confirmou idempotência.
- `database/database.sqlite` permaneceu intacto; os dados não foram copiados. A conexão PostgreSQL não importa automaticamente o conteúdo anterior.
- Foi criada uma base isolada `freecast_test`. Como `phpunit.xml` declara explicitamente `DB_CONNECTION=pgsql`, `DB_DATABASE=freecast_test`, host e porta, a suíte não usa SQLite silenciosamente. `PostgresTestDatabaseTest` verifica a conexão efetiva e o nome da base. `.env.testing` não é necessário nesta configuração.
- Resultados executados: `php artisan test` — 34 testes/153 assertions aprovadas; `npm run test:e2e` — 3 testes aprovados. A aplicação conectada ao PostgreSQL foi validada com login, criação/entrada em mesa, autorização, upload privado, mapa, tokens, atribuição e persistência após reload. O browser também confirmou uma mensagem da mesa via Reverb.
- `composer dev` iniciou HTTP, fila, Vite e Reverb; `/login` respondeu HTTP 200, e Reverb iniciou em `0.0.0.0:8080`. A suíte usa fila síncrona nos testes; esta validação confirma inicialização do worker, não processamento de tarefas assíncronas de aplicação.
- O `storage/logs/laravel.log` preexistente foi mantido. As mensagens SQLite nele terminam antes da troca de conexão e do início do stack PostgreSQL; não há erros posteriores à inicialização. Nos testes Playwright, a espera pelo heading após login foi ampliada para 15 segundos devido à latência medida do POST/redirecionamento no host Windows; a mesma condição de sucesso continua sendo verificada.

Comandos para iniciar no host já configurado: `docker compose up -d postgres` e `composer dev`. A base está pronta para a Parte 3; transferência de eventuais dados SQLite continua sendo uma operação separada e pendente.
