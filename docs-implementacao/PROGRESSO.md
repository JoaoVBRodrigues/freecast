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
- [ ] Validar execução com PostgreSQL: Docker está disponível; habilitar `pdo_pgsql`/`pgsql` no PHP e iniciar o serviço do projeto em 5434. As portas 5432 e 5433 pertencem a outros projetos e não foram alteradas.

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
- [ ] Validar execução com PostgreSQL; os testes desta etapa usaram SQLite local.

Esta etapa termina antes da Parte 3. A edição é persistente, mas ainda não sincroniza alterações simultâneas entre navegadores; visão dinâmica, áudio, plugins Lua e importador UVTT permanecem fora do escopo.
