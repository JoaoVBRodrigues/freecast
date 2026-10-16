# Progresso da implementação

## Parte 1/4 — base jogável

- [x] Scaffold Laravel 12 com Vue 3, Inertia, TypeScript e autenticação por sessão.
- [x] Perfil básico de jogador e autorização de leitura por associação à mesa.
- [x] Criar, descobrir, entrar e sair de mesas; papéis owner/master/player.
- [x] Favoritas e histórico recentes persistidos por usuário.
- [x] Início em cartões, navegação compacta e aba de mesa.
- [x] Mesa densa em três colunas com biblioteca, macros, combate, conversa e participantes.
- [x] Painéis de mesa e preferências de exibição persistidos por usuário/mesa.
- [x] Reverb/Echo, fila no banco e canais privados autorizados.
- [x] Seeder opcional com três contas e uma mesa sintética.
- [x] Playwright Test configurado e fluxos de login, criação/entrada e acesso negado especificados.
- [x] Testes Laravel: `php artisan test` — 29 testes, 81 assertions aprovadas.
- [x] Build: `npm run build` — `vue-tsc` e Vite concluídos.
- [x] Playwright: login, criação/entrada em mesa, negação de acesso indevido e entrega de mensagem em tempo real por Reverb/Echo entre duas sessões — 2 testes aprovados.
- [x] Capturas do início e mesa verificadas em 867 × 465 e comparadas visualmente com as referências.
- [x] `composer dev` iniciado e verificado no Windows; processo Pail foi removido do atalho por depender de `pcntl`, e Vite está fixado em IPv4/porta 5173.
- [x] PostgreSQL validado para as Partes 1 e 2; detalhes e resultados reais registrados em “Validação PostgreSQL” abaixo.

Na Parte 1, o histórico de conversa e os controles laterais foram o recorte inicial; editor de fichas, execução de macros, rolagem estruturada e gerenciamento de combate continuam fora do escopo.

## Parte 2/4 — biblioteca de imagens e editor de mapas

- [x] Biblioteca da mesa com pastas, upload de imagens privadas, validação e downloads autorizados.
- [x] CRUD de mapas: criar, renomear, duplicar, excluir e abrir; grade vazia ou fundo, dimensões e aparência da grade persistidos.
- [x] Editor Konva com zoom ancorado no cursor, pan por Espaço, enquadramento, seleção, arraste, redimensionamento, rotação e edição de tokens.
- [x] Camadas de fundo, objetos, tokens e mestre com visibilidade, bloqueio e ordem visual persistidos.
- [x] Atribuição de token a jogador; jogadores só movimentam tokens visíveis, desbloqueados e atribuídos a eles.
- [x] Preferências de câmera e encaixe salvas localmente por usuário/mapa; estado compartilhado persistido no banco.
- [x] Testes Laravel: `php artisan test` — 33 testes, 151 assertions aprovadas; inclui dados ocultos e negação de downloads privados.
- [x] Build: `npm run build` — `vue-tsc` e Vite concluídos.
- [x] Playwright Test: `npm run test:e2e` — 3 testes aprovados. O fluxo de mapa cria a mesa e o mapa, envia fundo e três tokens sintéticos, testa zoom/pan/arraste com encaixe, permissões do jogador e persistência após recarga.
- [x] Captura Playwright MCP do canvas em 867 × 465 salva em `capturas/editor-mapa.png`; verifica a implementação nova, não paridade com o editor original.
- [x] PostgreSQL validado nesta etapa; testes backend e navegador agora usam bases PostgreSQL distintas, sem tocar no arquivo SQLite.

Esta etapa termina antes da Parte 3. A edição é persistente, mas ainda não sincroniza alterações simultâneas entre navegadores; visão dinâmica, áudio, plugins Lua e importador UVTT permanecem fora do escopo.

## Validação PostgreSQL — Partes 1 e 2

- [x] `where.exe php` e `php --ini` confirmaram o PHP CLI ativo em `C:\xampp\php\php.exe` e o arquivo `C:\xampp\php\php.ini`. Após backup em `C:\xampp\php\php.ini.freecast-postgres-20261016204746.bak`, foram habilitadas somente as entradas `pdo_pgsql` e `pgsql`; `php -m` confirmou ambas carregadas.
- [x] Reconfirmada a porta 5434 livre. `docker compose up -d postgres` iniciou somente `freecast-postgres-1` (`postgres:17-alpine`), saudável em `127.0.0.1:5434`; containers de outros projetos não foram alterados.
- [x] O `.env` existente foi ajustado apenas para a conexão PostgreSQL do Freecast; a `APP_KEY` e demais configurações foram preservadas. `.env` permanece ignorado pelo Git e o segredo do banco não foi registrado nos documentos.
- [x] `php artisan config:clear`, `php artisan migrate --database=pgsql --force` e `php artisan db:seed --database=pgsql --force` concluíram na base de desenvolvimento vazia `freecast`. Todas as seis migrations estão aplicadas; o seed idempotente mantém as três contas sintéticas.
- [x] A base SQLite `database/database.sqlite` foi preservada (290816 bytes; sem transferência de dados). Nenhum dado SQLite foi migrado automaticamente.
- [x] Criada a base exclusiva `freecast_test`. `phpunit.xml` aponta explicitamente para `pgsql`/`freecast_test`, e `PostgresTestDatabaseTest` falha se a suíte tentar usar outra conexão/base.
- [x] `php artisan test` — 34 testes, 153 assertions aprovadas no PostgreSQL isolado.
- [x] `npm run test:e2e` — 3 testes aprovados contra a aplicação conectada ao PostgreSQL: login, criação/entrada em mesa, negação de acesso, upload privado, mapa, três tokens, atribuição, movimentação e persistência após reload. As contagens posteriores no PostgreSQL de desenvolvimento confirmaram os dados sintéticos criados pelo navegador.
- [x] `composer dev` iniciou web, fila, Vite e Reverb; `/login` respondeu HTTP 200, Reverb escutou em 8080 e o teste de navegador confirmou a entrega de mensagem da mesa em tempo real. O canvas não sincroniza edições simultâneas; isso permanece na Parte 3.
- [x] O arquivo de log existente foi preservado; suas mensagens SQLite terminam antes da troca para PostgreSQL e do início dos serviços corrigidos. Não há erros novos registrados após a inicialização PostgreSQL.

**Pendência:** a troca de `.env` para PostgreSQL não transfere dados da base SQLite. Se houver conteúdo local que precise ser mantido, planejar uma transferência explícita antes de descartá-lo. A base PostgreSQL está pronta para iniciar a Parte 3.
