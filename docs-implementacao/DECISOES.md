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

## Sincronização, conversa, dados e macros — Parte 3

- A fonte da verdade continua sendo PostgreSQL. Comandos duráveis usam `RoomSyncService`: autorização e bloqueio transacional da mesa, recibo por ator/operação/chave idempotente, hash do conteúdo, sequência por audiência, `commandId`/`eventId` e revisão esperada para alterações concorrentes. Repetição idêntica devolve o recibo; a mesma chave com conteúdo diferente conflita.
- Eventos e atualizações de domínio são gravados na transação. `RoomOutboxDispatcher` enfileira `DeliverRoomEvent` após o commit; o job marca publicação somente depois de transmitir. `rooms:dispatch-outbox` recupera eventos não publicados e o scheduler tenta reenfileirá-los uma vez por minuto. O banco e os eventos duráveis permitem recuperar após reinício; a fila WebSocket não é o histórico.
- A audiência fica no servidor: `room` para eventos públicos, `masters` para mestres/proprietário e `user:{id}` para eventos individuais. Conteúdo exclusivo do mestre não aparece nos props, snapshots ou eventos enviados a jogadores. Mensagens privadas só geram eventos para autor e destinatário. Presença usa canal autorizado da mesa e não é histórico durável.
- O movimento final é uma escrita HTTP durável; previews de posição são eventos transitórios autorizados e limitados pela rota. Revisões detectam conflito e o cliente reconcilia estado. Ao conectar/reconectar, `echo().connector.onConnectionChange()` aciona retomada HTTP paginada; a entrada no canal também solicita recuperação. O conjunto de `eventId` evita aplicar duas vezes eventos recebidos por HTTP e WebSocket.
- A revogação remove a associação e chama o endpoint de encerramento de conexões de usuário do Reverb. Canais, histórico, eventos e mídia revalidam associação/autorização em cada solicitação; o teste browser observou fechamento da conexão e HTTP 403 para arquivo/eventos após a remoção.
- Mensagens aceitam apenas texto limitado a 2.000 caracteres, narração, público, privado e até quatro anexos autorizados. A apresentação implementa negrito (`**...**`), itálico (`*...*`), sublinhado (`__...__`) e quebras de linha. HTML original é escapado antes da conversão desses três marcadores em tags permitidas; não há `v-html` sobre entrada não escapada, HTML arbitrário ou scripts.
- `/r` é interpretado por parser próprio, sem `eval`; suporta d4/d6/d8/d10/d12/d20/d100, somas, subtrações e constantes dentro de limites de entrada/quantidade. O servidor sorteia, persiste expressão/componentes/total e transmite o mesmo resultado sob a chave idempotente.
- Macros usam os três escopos `personal_room`, `personal_global` e `shared_room`. Por nome, a precedência é pessoal da mesa → pessoal global → compartilhada. O botão executa o ID selecionado e não resolve o nome novamente. Decisão própria: apenas proprietário/mestre cria/edita/exclui macros compartilhadas; jogador pode executá-las. Corpos aceitam comandos simples de mensagem e `/r`, com limites de expansão; Lua e scripts arbitrários permanecem proibidos.
- A configuração do aplicativo e o payload Reverb são distintos: no servidor, `REVERB_HOST` aponta para o Reverb local; no navegador, `VITE_REVERB_HOST` deve apontar para o IP alcançável pelo cliente. Reverb escuta em `REVERB_SERVER_HOST`/`REVERB_SERVER_PORT`. A variável `VITE_*` exige rebuild dos assets. Não usar `localhost` em navegadores remotos.
- Histórico das tentativas locais: o container preexistente `ttrpg-social-reverb-1` publica TCP 8081 e permaneceu intacto. O Freecast passou a usar 8082; `/up` respondeu HTTP 200 e um fluxo manual de login/upload respondeu 302. As primeiras tentativas automatizadas em 8082 ainda tiveram HTTP 419, antes das correções finais do ambiente dos processos e do helper CSRF. A validação final via Quick Tunnel está registrada abaixo; isso não comprova piloto físico em LAN.
- Context7 consultado para Laravel Reverb e Laravel Echo. A documentação consultada de Reverb descreve `POST /apps/{appId}/users/{userId}/terminate_connections`; o resultado é de documentação corrente, sem pin para 1.12.0, e foi cruzado com a rota da instalação `laravel/reverb` e o método instalado do Pusher PHP SDK. Para Echo foi consultado o ramo 2.x, compatível com os pacotes instalados 2.6.1; `onConnectionChange` e a desconexão de listener foram confirmados também nos tipos locais. A referência Laravel retornada pelo Context7 não era específica para Laravel 12; uso de transação/`afterCommit` foi conferido no código e validado pelos testes com Laravel 12.70.0.
- Resultados locais anteriores: `php artisan test` passou 40 testes/199 assertions e `npm run build` passou. Os primeiros browser tests foram anteriores à confirmação do conflito na porta 8081; as tentativas seguintes em 8082 registraram o HTTP 419 descrito acima. Foram substituídos pela validação final via Quick Tunnel, detalhada na seção abaixo.
- Capturas Playwright MCP: `capturas/mapa-tempo-real-parte3.png` em 867 × 465 contém mapa e tokens sintéticos próprios; `capturas/chat-formatacao-parte3.png` em 867 × 466 mostra formatação segura no chat. As imagens validam a implementação, não servem como evidência de paridade do editor original.

### Preparação de piloto LAN

Usar um servidor HTTP real (Apache/Nginx ou equivalente), não `php artisan serve`. No servidor, configurar o document root para `public`, listener HTTP em `0.0.0.0:8000`, `APP_URL=http://<IP-LAN>:8000`, `APP_DEBUG=false`, `REVERB_HOST=127.0.0.1`, `REVERB_PORT=8082`, `REVERB_SERVER_HOST=0.0.0.0`, `REVERB_SERVER_PORT=8082`, `VITE_REVERB_HOST=<IP-LAN>`, `VITE_REVERB_PORT=8082` e esquema `http`; preservar `APP_KEY` e demais segredos. Confirmar que 8082 está livre antes de usar; neste ambiente 8081 está ocupado pelo container preexistente `ttrpg-social-reverb-1`, que não deve ser parado nem modificado. Rodar `npm run build` após alterar `VITE_*`. Iniciar PostgreSQL do projeto, Apache, `php artisan reverb:start --host=0.0.0.0 --port=8082`, `php artisan queue:work --tries=5 --timeout=20 --sleep=1` e `php artisan schedule:work`. Abrir no firewall privado apenas TCP 8000/8082; manter 5434 local. Mestre e jogadores acessam `http://<IP-LAN>:8000` e WebSocket `ws://<IP-LAN>:8082` (Echo monta o path do protocolo); nunca usar `localhost` nos clientes remotos. Em HTTPS, fornecer certificados e mudar para HTTPS/WSS. Três computadores físicos e firewall continuam sem validação.

### Piloto temporario por Cloudflare Quick Tunnel

- O Quick Tunnel encaminha somente para Caddy em `127.0.0.1:8091`. Caddy encaminha `/app/*` ao Reverb em `127.0.0.1:8082` e as demais rotas ao Apache isolado em `127.0.0.1:8000`, com document root `public`. PostgreSQL continua apenas em `127.0.0.1:5434`; Vite, phpMyAdmin e endpoints internos do Reverb nao sao expostos. A porta 8090 ja era usada por outro projeto, portanto foi escolhida 8091; o Reverb proprio permanece na 8082 e o container de outro projeto em 8081 nao foi alterado.
- `Caddyfile.Pilot` confia nos cabecalhos encaminhados apenas de loopback. Cloudflare define `X-Forwarded-Proto` com o esquema externo e Caddy preserva esses valores por sua configuracao de trusted proxies; Laravel aceita forwarded host/proto somente de `127.0.0.1`/`::1`. O host Caddy e a allowlist do Reverb sao limitados ao hostname aleatorio da inicializacao. Context7 consultado para Caddy `reverse_proxy`/trusted proxies e cabecalhos Cloudflare; Laravel 12.70.0 foi verificado tambem no codigo instalado.
- O hostname e capturado uma vez dos logs do cloudflared antes de configurar Caddy, APP_URL, allowlist do Reverb e assets. Paginas usam HTTPS e o Echo usa WSS no mesmo host/porta publica 443; comunicacao de publicacao Laravel-Reverb permanece HTTP em loopback.
- A sessao do piloto usa `freecast_pilot_session`, host-only, `Secure`, `HttpOnly` e `SameSite=Lax`. A chave APP_KEY original e preservada e injetada apenas nos processos; a chave publica Reverb vai em `VITE_REVERB_APP_KEY`, nunca o segredo Reverb. `REVERB_APP_ID`, `REVERB_APP_KEY` e `REVERB_APP_SECRET` sao passados aos processos PHP do piloto e exigidos pelo preflight; os valores nao sao registrados nem enviados ao bundle.
- Apache e iniciado com configuracao isolada em `%LOCALAPPDATA%\Freecast\pilot\apache`, sem incluir a configuracao global do XAMPP. O caminho do workspace contem caractere acentuado; a configuracao Apache e ASCII, portanto usa caminhos Windows curtos (8.3) para document root, PID e log. Se o volume nao oferecer caminho curto ASCII, a inicializacao falha sem iniciar o Apache. O processo de parada confere PID, horario, executavel e marcador exclusivo do Apache Freecast antes de agir.
- A configuracao do piloto e aplicada somente ao ambiente dos processos filhos. `.env` nao e alterado, cache de configuracao anterior e restaurado apenas se nao houver substituicao posterior, e `Stop-Pilot.ps1` recompila assets com as variaveis locais atuais. O script nao fecha o PostgreSQL do projeto nem outros containers.
- A porta 8081 e de `ttrpg-social-reverb-1`, e 8090 e de outro projeto; ambas permaneceram intactas. A verificacao do `docker compose up -d postgres` confirmou o banco Freecast saudavel em 5434 e os dados/migrations presentes nas bases `freecast` e `freecast_test`.
- As primeiras rodadas funcionais identificaram incompatibilidade de caminho Apache com o perfil Windows acentuado (corrigida com caminhos 8.3), credenciais Reverb ausentes nos processos Apache/worker, conexoes de sessao apontando para SQLite e, depois, tentativa PostgreSQL sem senha no runtime Apache. A inicializacao agora passa explicitamente a configuracao PostgreSQL de `.env` aos processos filhos, fixa sessao/cache/fila em `pgsql` e valida host `127.0.0.1`, porta 5434, banco, usuario/senha e conexoes efetivas no preflight. `DB_URL` e limpo para impedir override herdado. Nenhuma senha e gravada em arquivo ou `VITE_*`.
- O helper Playwright de CSRF inicialmente esperava o nome local padrao `freecast-session`, embora o piloto usasse `freecast_pilot_session`; a falha era do diagnostico, nao um 419 do login. O helper agora escolhe o nome exclusivo do piloto e continua validando Secure, host-only, SameSite, cookie XSRF e igualdade do header sem registrar valores. A rodada publica final passou com status de login 302, sem 419 e sem diagnostico de falha. A causa do 419 historico ainda nao foi comprovada por A/B controlado do cookie legado.
- Validacao final: `php artisan test` passou 40 testes/199 assertions no `freecast_test`; Playwright publico passou os tres testes de salas/mapas e o teste de tres contexts realtime. Os cenarios confirmaram HTTPS/WSS mesmo host, uploads/mapa/tokens apos reload, autorizacao, chat simultaneo, resultado de dado uniforme, mensagem privada ausente do terceiro context/trafego, reconexao, worker e revogacao. Detalhes e limitacoes fisicas estao em `PROGRESSO.md` e `PILOTO-INTERNET.md`; isto nao e piloto concluido em tres computadores externos.
- A inicializacao x64 verificou cloudflared 2026.10.1 por Authenticode Cloudflare, Inc. e Caddy 2.11.7 pelo checksum oficial SHA-512. Os arquivos oficiais ficam em `%LOCALAPPDATA%\Freecast\pilot\tools`; logs e manifesto de processos nao guardam senhas.
- A base de desenvolvimento `freecast` nao e migrada nem reiniciada. `-UseTestDatabase` seleciona apenas `freecast_test`; testes publicos usam contas sinteticas com senhas aleatorias injetadas no processo. O preflight rejeita senhas demonstrativas padrao e `APP_DEBUG=true`.
- A inicializacao x64 verificou cloudflared 2026.10.1 por Authenticode Cloudflare, Inc. e Caddy 2.11.7 pelo checksum oficial SHA-512. Os arquivos oficiais ficam em `%LOCALAPPDATA%\Freecast\pilot\tools`; logs e manifesto de processos nao guardam senhas.
- A base de desenvolvimento `freecast` nao e migrada nem reiniciada. `-UseTestDatabase` seleciona apenas `freecast_test`; testes publicos usam contas sinteticas com senhas aleatorias injetadas no processo. O preflight rejeita senhas demonstrativas padrao e `APP_DEBUG=true`.
