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

Esta entrega para antes da Parte 2. O histórico de conversa e os controles laterais são o recorte inicial; editor de fichas, execução de macros, rolagem estruturada, uploads e gerenciamento de combate não fazem parte desta etapa.
