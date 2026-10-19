# Progresso da implementação

## Parte 4B — régua, desenhos e névoa manual (18–19/10/2026)

- Perfil **Frontend Developer** carregado de `.codex/agents/frontend-developer.toml`, com um único editor por vez. Backend Architect consultado sequencialmente para autorização, persistência e sincronização. O agente de implementação atingiu seu limite de uso no fechamento; a sessão principal assumiu somente as verificações finais, encerramento e documentação, sem iniciar outro agente. Leitura de docs-firecast/03 e 06: regras completas do original não estavam comprovadas; a implementação usa decisões próprias, descritas em [DECISOES.md](DECISOES.md). Sem nova engenharia reversa e sem necessidade de Context7.
- Régua local euclidiana em células/unidade, encaixe opcional, escala/unidade configuráveis pelo mestre, Escape/troca de ferramenta para cancelar. Reutiliza `mapCoordinates.ts`; zoom/pan e escala não movem tokens. Composable e componentes próprios evitam concentrar ferramentas no editor.
- Linha, retângulo, elipse e livre persistentes/sincronizados; seleção no canvas ou lista; remoção pelo autor ou mestre. Paleta/espessuras limitadas, 256 pontos, 500 desenhos/mapa e 32 KiB/comando. Servidor valida geometria, normaliza números e não aceita SVG/HTML/scripts.
- Névoa compartilhada por células fixas de 64 unidades, ativar/desativar, revelar/ocultar retângulos, revelar/ocultar tudo com confirmação. Mestre vê cobertura violeta translúcida; jogador recebe máscara opaca. Regra dos tokens pelo centro; desenhos exigem caixa inteira revelada. Ficha de qualquer token coberto em qualquer mapa fica ausente inclusive do atribuído; combate/retrato/edição e projeções de outros mapas respeitam a mesma regra. Cobrir novamente remove estado atual, sem promessa de apagar conhecimento anterior, pixels do fundo baixado ou templates públicos da biblioteca.
- Contratos reutilizados: lock/transação da mesa, revisão, idempotência e outbox. Eventos de objeto/ferramenta são invalidações sem IDs/geometria; histórico/outbox antigos de objetos são normalizados. Previews sob névoa só ao mestre, com autorização serializada. Mudanças privadas acordam projeção pública por invalidação vazia. Editor e conversa atualizam fichas/combate quando **outro mapa** muda ou é removido. Corrigida recuperação que convertia cursor de audiência ausente em `NaN`; Editor também recupera na conexão do Echo.
- Isolamento preservado: guardas existentes no mesmo PHP, `freecast_test`, storage de teste e `APP_ENV=validation` com middleware CSRF normal. Nenhum seed/migration em `freecast`, nenhuma alteração de `.env`/APP_KEY, contas/senhas/sessões/privacidade/participantes de desenvolvimento, storage privado antigo, firewall ou processos de outros projetos. Não houve túnel nem repetição da recuperação operacional. A revisão manual das contas/mesa do incidente continua pendente do responsável, conforme [OPERACAO-4A.md](OPERACAO-4A.md).
- Migration aditiva **`2026_10_19_000000_add_map_tools` aplicada somente em `freecast_test`; pendente em desenvolvimento**. Cria desenhos e campos de escala/máscara, sem modificar tabelas anteriores de personagens/combate. Duplicar conserva ferramentas; redimensionar zera a máscara para não reinterpretar índices.
- Backend inicial completo: **50 testes / 410 assertions aprovadas**. Mocks PowerShell: **3 cenários aprovados**, sem escrita no desenvolvimento. Build oficial `vue-tsc && vite build` aprovado, com aviso já conhecido de bundle principal ~598 kB. Vite/Apache precisaram execução autorizada fora do sandbox por resolução do caminho, mantendo apenas loopback.
- Playwright 4B: **1 cenário aprovado (1,5 min)**, mestre e dois jogadores. Mouse real mediu 3 × 4 células = 5, repetiu com zoom e pan efetivo, escala de 2 resultou 10 metros e token ficou inalterado. Criou quatro tipos pela UI, persistiu/recarregou/sincronizou/removou desenho próprio, negou exclusão alheia 403. Testou névoa por região/tudo, confirmação cancelada, reload/offline/reconexão, ausência de entidades/fichas/combate/retratos em payload/listas, purga ao cobrir novamente, replay e conflito 409. Comando autenticado de névoa sem token e com token inválido: **419/419 e mapa idêntico**; o mesmo payload com token válido: **200**.
- Regressões: **characters.spec.ts + maps.spec.ts — 2 aprovadas (2,0 min)**, incluindo PV/vínculo/combate/reatribuição, privacidade, CSRF 4A, movimento autorizado e câmera local. Focal entre mapas: **1 aprovado (51,2 s)**; enquanto jogadores mantêm A aberto, cobrir representação vinculada em B remove nome/PV da ficha em A, mantém o nome base do token A, fecha editor e filtra combate; desativar ou remover B restaura a projeção.
- Tentativas corrigidas: helper Inertia inicialmente omitira versão e recebeu 409; régua concluída era cancelada ao sair do canvas e agora só gesto em curso é cancelado; recuperação/reload expôs o cursor `NaN` corrigido acima. Nas regressões, a lista nova reutilizava classe de tokens e foi separada; zoom foi validado pela fórmula exata da roda, substituindo o limiar absoluto dependente da altura antiga. Focal de remoção de mapa usou DELETE cru e seguiu redirect como DELETE (405); corrigiu-se o helper para o contrato Inertia/303. Nenhum middleware/permissão/conflito foi relaxado para aprovar.
- Evidências próprias em 867 × 465: [mestre e cobertura](../capturas/parte4b-mestre.png), [jogador sem entidades cobertas](../capturas/parte4b-jogador-coberto.png), conferidas visualmente. Capturas históricas preservadas; testes 4B/regressões usam diretórios próprios ignorados, com cópias finais em `capturas/`.
- Fechamento em 19/10: realtime legado aprovado, confirmado pelo resultado final `passed` e captura no diretório isolado `test-results/parte4a`, sem processo de teste/worker restante. Total de **5 cenários browser aprovados** (4B, entre mapas, personagens, mapas e realtime). Depois de encerrar os serviços, a revalidação backend afetada (`MapToolsTest|RoomRealtimeTest`) passou **10 testes / 165 assertions**, incluindo duplicação/redimensionamento, projeções e invalidação pública vazia sem divulgar eventos privados. Build local oficial restaurado e aprovado; permanece somente o aviso de bundle >500 kB. Apache/Reverb próprios encerrados e portas **8014/8084 confirmadas fechadas**. Consulta somente leitura em `freecast` confirmou a migration 4B pendente; nenhuma migration/seed foi executada nessa base nesta rodada. Parte 4B concluída; 4C não iniciada.

### Teste visual e retomada

Para repetir em isolamento, use `Run-Part4ATests.ps1` com `Prepare`, `Build`, `Serve`, `Reverb` e `Worker` em terminais descartáveis. `Browser4B` executa as ferramentas e o cenário entre mapas; `Regression4B` executa personagens/mapas. Antes de `Realtime`, encerre o worker externo: esse teste administra seu próprio worker. Feche servidores/browser antes de PHPUnit. Todas as ações preservam as guardas operacionais.

Para testar manualmente no desenvolvimento, a migration acima ainda precisa ser aplicada **por decisão do responsável**, antes de abrir mapas com o código novo. Não foi aplicada nesta rodada. O comando aditivo específico é `php artisan migrate --path=database/migrations/2026_10_19_000000_add_map_tools.php`; não execute seed demonstrativo para preparar dados pessoais. Mantenha exposição pública desligada e as ações de recuperação pendentes.

1. Abra/crie mapa e selecione **Régua**; arraste 3 células para o lado e 4 para baixo: 5 células. Em **Escala e névoa manual**, salve a unidade/escala, repita após zoom/Espaço+arraste e use Escape.
2. Selecione **Linha**, **Retângulo**, **Elipse** ou **Livre**, escolha cor/traço e arraste. Recarregue; selecione no canvas ou lista **Desenhos** e remova. Jogador só remove os próprios atualmente visíveis.
3. Como mestre, ative névoa, use **Revelar região**/**Ocultar região** e confira um jogador. **Revelar tudo**/**Ocultar tudo** pedem confirmação. Recarregue/reconecte; token coberto não aparece na lista nem no payload. Uma representação coberta em outro mapa também retira a ficha/PV do mapa aberto.

Parte 4C/dd2vtt, iluminação, visão dinâmica e entrega de imagens em blocos permanecem fora desta rodada. Não houve validação em três dispositivos físicos nem certificação de configuração futura de produção.

## Correção operacional e validação 4A — 18/10/2026

Detalhes, limites de recuperação e procedimento manual: [OPERACAO-4A.md](OPERACAO-4A.md).

- Backup verificável do **estado atual após incidente**, fora do Git: `C:\Users\João\FreecastBackups\current-4a-20261018-211809`; dump listado e restaurado em banco descartável depois removido, 135 arquivos privados conferidos por tamanho/SHA-256, ACL de arquivos/diretórios restrita ao usuário/SYSTEM. Manifestos privados não exibem hashes neste relatório. Nenhum backup anterior encontrado **somente no diretório conhecido**; não foi feita busca indiscriminada por outros backups. Não recupera valores anteriores ao incidente.
- Consulta de desenvolvimento confirmou as três contas e mesa ID 1 pública/sem senha, owner e dois players. Após o backup foram invalidadas somente **91 sessões dessas contas → zero**, em transação administrativa; remember_tokens já eram zero. Senhas, propriedades da mesa e associações não foram corrigidas automaticamente. Recuperação continua **pendente de confirmação do responsável**.
- Prevenção: códigos nativos explícitos e falhas de PowerShell filhos propagadas; ambiente/banco/storage verificados antes da primeira escrita e no mesmo PHP de migration/seed; guarda no boot antecede RefreshDatabase e sessões HTTP. `TestFixtureSeeder` separado recusa banco incorreto; seed demonstrativo reexecutado preserva contas/mesa/associações existentes. Não foi executado seed em `freecast` nesta rodada.
- PHPUnit: **47 testes / 306 assertions aprovadas**, PostgreSQL `freecast_test`, incluindo mocks de recusa sem escrita no desenvolvimento e comparação integral das tabelas afetadas ao reexecutar demo seed no teste. Mocks PowerShell: **3 cenários aprovados**, verificando falha de ambiente, preflight nativo e preparação nativa com ExitCode não zero no filho e nenhuma chamada posterior.
- Build oficial isolado aprovado (`vue-tsc && vite build`), com aviso conhecido de bundle >500 kB. Primeira execução sandbox negou leitura esbuild; código de falha propagou ao chamador e execução autorizada fora sandbox passou. Apache também exigiu execução fora sandbox por acesso ao caminho curto, mantendo listener somente loopback.
- Browser: **3 regressões rooms/maps aprovadas**; **4A aprovada (1 teste, 1,1 min)** com mestre/dois jogadores, UI, vínculo, PV, combate, privacidade HTTP/frames, replay/409, reload, reconexão e reatribuição. Runtime `APP_ENV=validation`, guard PHP e middleware CSRF padrão, sem exceções novas: POST autenticado sem token e com token inválido deu **419/419**, snapshot completo ficou idêntico após cada recusa; **o mesmo payload** com sessão/token válidos deu **200** e atualizou PV. Isso comprova CSRF ativo nessa configuração isolada; não certifica configuração futura de produção.
- Tentativa browser inicial passou a prova CSRF, mas as duas mutações extras antes de abrir o editor do jogador causaram conflito de revisão no draft. A prova foi movida para o fim, mantendo exigências 419/419/200 e todos os asserts originais; a rodada final 4A passou. Nenhuma proteção de conflito foi removida.
- Realtime legado relevante: **1 teste aprovado (1,3 min)** com três contexts, mapa/chat/rolagem, privado/oculto, reconexão, parada/reinício do worker e revogação de sessão de canal/download/histórico. Sem túnel; HTTP/WS loopback 8014/8084. Primeira tentativa falhou ao ler body HTTP depois de fechar deliberadamente página no listener do próprio teste; corrigido para aguardar todas as leituras antes da checagem negativa de privacidade/fechamento. Nenhuma leitura falha é ignorada e os asserts originais permanecem. O worker externo foi encerrado antes do legado para não mascarar o teste de reinício do seu worker próprio.
- Encerramento: processos próprios finalizados, tentativa TCP confirmou **8014/8084 fechadas**, nenhum processo cloudflared ativo. Build oficial local normal reexecutado e aprovado para restaurar assets com o `.env` atual. O `.env` continua ignorado e não houve ação que o escrevesse; não havia checksum anterior desta rodada para alegar comparação criptográfica histórica. Capturas anteriores preservadas; [captura operacional 4A](../capturas/parte4a-validacao-operacional.png) salva separadamente. Dump protegido comparado ao original restaurado, com hashes iguais sem impressão, e temporários próprios no container removidos.

Não houve túnel público, mudanças de firewall ou implementação 4B. Ações manuais: trocar as três senhas em `/profile` e decidir privacidade/senha/participantes da mesa, conforme roteiro seguro. Não marcar contas/mesa como recuperadas antes dessa confirmação.

**Retomada:** a correção e a validação técnica 4A estão concluídas; há base técnica para começar 4B em trabalho separado. A recuperação operacional das contas/mesa ainda depende das ações e da confirmação do responsável; não liberar piloto público antes dessa revisão. Esta rodada para antes de implementar 4B.

## Parte 4A — personagens, recursos e combate (18/10/2026)

- CRUD de personagens com retrato da biblioteca privada, anotações, atributos textuais, recursos configuráveis e PV. Limites: `0 <= atual <= máximo <= 1.000.000`. Sem fórmulas, scripts ou fichas originais.
- Proprietário/mestre cria/remove/atribui/publica e escolhe retrato. Jogador atribuído edita nome, notas, atributos, recursos e PV. Outros membros só recebem resumo nome/PV/retrato permitido dos públicos; fichas privadas são omitidas de HTTP/snapshots. Retratos têm endpoint privado com autorização atual; exclusão de imagem referenciada é bloqueada.
- Tokens vinculados usam nome e barra de PV derivados da ficha autorizada, no canvas e na lista. Revisões da ficha e do token protegem vínculo/desvínculo. Duplicação conserva vínculo sem copiar nome privado para o nome base. Remover ficha desvincula tokens e remove atores do combate.
- Tracker do mestre: iniciar/encerrar, adicionar/remover personagem com token vinculado opcional, editar iniciativa, ordenar, destacar ator, avançar turno/rodada. Empate usa ordem de inclusão. Componente modular CharacterPanel integrado à conversa e ao mapa.
- RoomSyncService reutilizado: lock da mesa, transação, recibos idempotentes, revisões e outbox. Eventos `characters.changed` são invalidações sem conteúdo/IDs de ficha; snapshot usa autorização atual. Recibos só contêm IDs/revisões. Reconexão recupera eventos e busca estado atual; reatribuição fecha editor sem autorização.
- Migration aditiva `2026_10_18_000000_create_characters_and_combat` aplicada. `.env`, APP_KEY e arquivos privados existentes não foram editados. Não houve migrate:fresh em desenvolvimento nem exposição pública. Scripts do piloto foram preservados.
- Backend final: `php artisan test` — **43 testes, 289 assertions**, PostgreSQL **freecast_test**; inclui HTTP/Inertia de tokens, privacidade/downloads, outra mesa, PV/recursos inválidos, reatribuição, replay, conflito, empate/rodada/remoção e lista vazia.
- `npm run build` oficial (`vue-tsc && vite build`) passou, sem erro de @types/node ou dependências novas. Permanece aviso Vite de bundle principal >500 kB. Assets locais restaurados ao final com `.env` atual.
- Playwright rooms/maps: **3 testes aprovados**. `characters.spec.ts`: **1 teste aprovado, 58,6s**, mestre + dois jogadores em contexts independentes. Testou formulário completo, atribuição/retrato/vínculo, PV via Reverb, projeção de mapa de dois clientes, conteúdo privado ausente de HTTP/frames, edição negada, combate, reenvio idempotente/409, reload, offline/reconexão e perda de acesso a editor aberto por reatribuição.
- Tentativa inicial com PHP embutido apresentou resets de assets e foi interrompida. Rodada final usou Apache próprio em **127.0.0.1:8014**, Reverb **127.0.0.1:8084** e worker próprios. Primeira rodada Apache falhou por seletor incorreto de teste `.asset-card`; corrigido para `.asset-library-card`. Somente 4A foi reexecutada após as três regressões passarem.
- Antes de uploads, PHPUnit usou `storage/app/testing/phpunit-private`, com guarda em Tests/TestCase. Servidor/Playwright verificaram conexão real **freecast_test** e `storage/app/testing/browser-4a-private` por assert-test-environment.php. Nenhum upload desta rodada foi ao storage de desenvolvimento; nenhum arquivo antigo foi apagado em massa. Captura anterior realtime-map-three-contexts.png foi restaurada do HEAD após limpeza automática de outputDir; executor agora usa `test-results/parte4a`.
- Playwright MCP foi usado para navegar, inspecionar e capturar [parte4a-personagens-combate.png](../capturas/parte4a-personagens-combate.png), conferida em **867 × 465**. Mostra recursos sintéticos do fixture, com posição/tamanho do token ajustados somente nele para legibilidade. REA e Context7 não foram utilizados: documentação existente bastou e não houve dúvida de API que exigisse consulta.
- Processos de teste próprios encerrados; conexão TCP confirmou **8014/8084 fechadas**. Backend final executado depois de encerrar browser/processos, pois compartilha freecast_test com o browser.

### Incidente operacional desta rodada

Uma tentativa inicial de dot-source foi bloqueada pela ExecutionPolicy, mas o shell prosseguiu com `php artisan migrate --force` e `php artisan db:seed --force` no desenvolvimento **freecast**. A migration foi aditiva. O seed **redefiniu as senhas** de `mestre@freecast.test`, `jogador@freecast.test` e `convidada@freecast.test` para `Mesa2026!`, e os nomes para Nara Vale, Theo Arantes e Lia Monte. Também atualizou a mesa sintética A Cripta das Marés (sistema/descrição, pública, senha de mesa nula), associações dos três usuários, favoritos/visita previstos pelo seed e mensagens sintéticas ausentes. Os valores/hashes anteriores não foram guardados nesta rodada e não podem ser restaurados sem backup. Não se afirma que os dados de desenvolvimento ficaram intactos. **Troque as senhas dessas contas antes do piloto; não execute o seed para uso real.**

O executor corrigido usa `powershell.exe -NoProfile -ExecutionPolicy Bypass -File`, `$ErrorActionPreference = 'Stop'` e assert PHP obrigatório de banco/storage antes das ações. Preparações posteriores e todos os uploads/browser usaram ambiente isolado. Arquivos de desenvolvimento foram preservados.

### Abrir e testar manualmente

No modo local normal, aplique `php artisan migrate --force` somente se a migration estiver pendente (já aplicada neste workspace). Inicie aplicação, Reverb e worker pelo fluxo existente. Abra mesa como proprietário/mestre e role Combate até **Personagens → Novo personagem**. Informe máximo antes do PV atual, escolha jogador/retrato e marque publicação somente para compartilhar nome/PV. O jogador atribuído abre a ficha pelo nome. Retratos são enviados pela biblioteca existente; remova a referência antes de excluir imagem usada.

Para vincular: abra mapa, adicione/selecione um token em **Itens no mapa**, role inspector até Personagens e use **Vincular token**. O botão permite desvincular. Atribuição de movimento do token permanece independente da atribuição da ficha. Para combate: selecione personagens/iniciativa, adicione, inicie e avance turnos; a conversa mantém o mesmo tracker sem sair do chat.

Para repetir testes isolados (feche outros testes usando freecast_test), na raiz:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/Run-Part4ATests.ps1 -Action Prepare
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/Run-Part4ATests.ps1 -Action Build
```

Abra três terminais com o mesmo comando, usando `-Action Serve`, `-Action Reverb`, `-Action Worker`. Serve gera config Apache exclusiva em storage/app/testing/apache-4a, requer XAMPP em C:/xampp e caminho curto ASCII. Em outro terminal, execute `-Action Browser` (4A + salas/mapas) ou `-Action Browser4A`. Cada ação verifica banco/storage. Encerre esses terminais por Ctrl+C, confira 8014/8084 fechadas e rode `npm.cmd run build` em terminal normal para restaurar assets locais. Prepare semeia apenas freecast_test; não use essa base para dados reais. Execute `php artisan test` separadamente com browser/servidores encerrados.

Limites: runtime browser usou APP_ENV=testing, que pode dispensar CSRF no Laravel; esta rodada valida autorização/funcionalidade/realtime, **não certifica CSRF em produção**. Realtime legado completo/public pilot não foi repetido; não houve teste com três dispositivos físicos. Régua/desenho/névoa/dd2vtt (4B) não foram iniciados. Retomada: backup/troca das senhas demonstrativas e piloto manual autorizado, depois planejar 4B.

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

## Parte 3/4 — sincronização, conversa, dados e macros

- [x] Presença por mesa e seleção de mapa ativo pelo mestre; membros e dados enviados conforme a audiência autorizada.
- [x] Movimento otimista de tokens com previews transitórios e posição final persistida, revisão para conflitos e recibos idempotentes.
- [x] Eventos duráveis em outbox PostgreSQL, entrega por worker após commit, tentativas e comando agendado para reenfileirar eventos pendentes.
- [x] Recuperação de eventos do histórico após entrada/reconexão do Echo; o cliente reconcilia por audiência e deduplica por `eventId`.
- [x] Histórico paginado, mensagens públicas/narração/privadas e anexos autorizados da biblioteca. Mensagens privadas são encaminhadas aos canais individuais de autor/destinatário, não ao canal público da sala.
- [x] Formatação limitada a negrito, itálico, sublinhado e quebras de linha; HTML fornecido pelo usuário é escapado antes da renderização.
- [x] `/r` com d4, d6, d8, d10, d12, d20, d100, modificadores aditivos/subtrativos e constantes; resultado e componentes são sorteados uma vez, persistidos e idempotentes.
- [x] Macros pessoais por mesa, pessoais globais e compartilhadas da mesa; resolução por nome respeita precedência, enquanto o botão executa o item selecionado. Edição de macro compartilhada restrita a proprietário/mestre; jogadores podem executar macro compartilhada.
- [x] Revogação remove a associação, encerra conexões Reverb do usuário e nega novamente canais, histórico e downloads privados.
- [x] Migration aditiva `2026_10_17_000000_create_room_sync_tables` aplicada no PostgreSQL de desenvolvimento; `php artisan migrate:status --database=pgsql` confirma as sete migrations como `Ran`. Não foi usado `migrate:fresh`.
- [x] Testes backend: `php artisan test` — 40 testes, 199 assertions aprovadas na base PostgreSQL exclusiva `freecast_test`.
- [x] Build: `npm run build` — `vue-tsc` e Vite concluídos.
- [x] Revalidação Playwright concluída via Quick Tunnel com Apache, Caddy e Reverb próprio em 8082: `rooms.spec.ts` + `maps.spec.ts` passaram (3 testes) e `realtime.spec.ts` passou (1 teste), em três contexts independentes.
- [x] O login final não recebeu HTTP 419; HTTPS/WSS, cookie `Secure` host-only, XSRF, ausência de conteúdo misto e conexão ao hostname público foram verificados. A causa do 419 histórico continua sem confirmação por A/B do cookie legado.
- [x] Teste de texto rico confirmou tags permitidas e que conteúdo HTML arbitrário aparece como texto, sem criar elemento executável.
- [x] Captura Playwright MCP [mapa-tempo-real-parte3.png](../capturas/mapa-tempo-real-parte3.png) conferida em 867 × 465 com dados sintéticos próprios. Não comprova paridade visual do editor original.
- [x] Captura Playwright MCP [chat-formatacao-parte3.png](../capturas/chat-formatacao-parte3.png) inspecionada em viewport 867 × 466; formatação permitida aparece e HTML é texto escapado.

### Piloto em três computadores pela LAN — roteiro não executado

O roteiro abaixo continua disponível para uma futura validação de LAN. As tentativas iniciais contra o Reverb em 8081 e o posterior HTTP 419 foram superadas na validação automatizada pública via Quick Tunnel, descrita na seção seguinte; nenhuma delas foi um teste com três computadores físicos. Para testar na mesma rede:

1. No computador servidor, obtenha o IPv4 privado da interface usada pelos três computadores e use-o como `<IP-LAN>`. Mantenha PostgreSQL publicado somente em `127.0.0.1:5434`.
2. Configure o Apache com o `DocumentRoot` deste projeto em `public` e listener `0.0.0.0:8000`. Não use `php artisan serve` para este piloto.
3. Confirme primeiro que TCP 8082 está livre no servidor. No `.env`, mantenha `BROADCAST_CONNECTION=reverb`, `QUEUE_CONNECTION=database`, `REVERB_HOST=127.0.0.1`, `REVERB_PORT=8082`, `REVERB_SCHEME=http`, `REVERB_SERVER_HOST=0.0.0.0` e `REVERB_SERVER_PORT=8082`. Defina `APP_URL=http://<IP-LAN>:8000`, `VITE_REVERB_HOST=<IP-LAN>`, `VITE_REVERB_PORT=8082` e `VITE_REVERB_SCHEME=http`. Preserve a `APP_KEY`, não exponha credenciais e use `APP_DEBUG=false` na rede. Não tente liberar 8081 enquanto o container preexistente o ocupar.
4. Rode `npm run build` depois de alterar as variáveis `VITE_*`; confirme que `public/hot` não está apontando clientes para o servidor Vite de desenvolvimento.
5. Inicie os processos no computador servidor: `docker compose up -d postgres`, Apache, `php artisan reverb:start --host=0.0.0.0 --port=8082`, `php artisan queue:work --tries=5 --timeout=20 --sleep=1` e `php artisan schedule:work`.
6. Libere TCP 8000 (HTTP) e TCP 8082 (WebSocket) somente no perfil de rede privada do Windows Firewall. Não exponha a porta 5434 aos computadores clientes.
7. No mestre e nos dois jogadores, abra `http://<IP-LAN>:8000` — nunca `localhost`. O navegador conectará ao Reverb por `ws://<IP-LAN>:8082` (o Echo acrescenta o caminho/protocolo Reverb); em HTTPS, configure TLS e use WSS.
8. Entre com três contas sintéticas, entre na mesma mesa, abra o mapa ativo, atribua um token a um jogador, troque mensagens/rolagens e desconecte/reconecte um cliente. Confirme no servidor HTTP e WebSocket a entrega e recuperação, sem compartilhar URLs locais ou credenciais.

**Pendências:** a causa do HTTP 419 histórico segue sem A/B controlado do cookie legado; a validação física em três computadores externos ainda não foi realizada. Visão dinâmica, áudio e plugins continuam fora desta etapa; a Parte 4 não foi iniciada.

## Preparacao do piloto publico temporario

- [x] `scripts/Start-Pilot.ps1`, `scripts/Stop-Pilot.ps1`, `scripts/Caddyfile.Pilot` e `PILOTO-INTERNET.md` adicionados sem alterar `.env` ou a configuracao global do XAMPP. Cloudflared 2026.10.1 (assinatura Authenticode Cloudflare valida) e Caddy 2.11.7 (checksum oficial validado) estao em `%LOCALAPPDATA%\Freecast\pilot\tools`.
- [x] Caddy validado com `PILOT_HOST` sintetico. Apache 2.4.58/PHP 8.2.12 iniciou com configuracao isolada e document root `public`; caminhos curtos 8.3 corrigiram a incompatibilidade do perfil Windows acentuado.
- [x] Quick Tunnel capturou hostname temporario sem reiniciar o tunel; Caddy ficou em `127.0.0.1:8091`, Laravel/Apache em `127.0.0.1:8000`, Reverb em `127.0.0.1:8082`. O navegador alcançou HTTPS e WSS sob o mesmo host; PostgreSQL permaneceu em `127.0.0.1:5434`.
- [x] `APP_DEBUG=false`, cookie exclusivo seguro, APP_KEY preservada e credenciais Reverb server-only passaram o preflight no `freecast_test`. O `.env` permaneceu inalterado; contas E2E usaram senhas aleatorias apenas no processo.
- [x] Conexoes do piloto para PostgreSQL, sessoes, cache e fila agora sao fixadas em `pgsql`; host/porta/usuario/senha sao herdados do `.env` somente pelos processos do Freecast. O preflight valida loopback `127.0.0.1:5434`, credenciais e conexoes persistentes antes de iniciar o tunnel.
- [x] Diagnostico corrigido: logs de uma rodada anterior mostraram sessoes tentando SQLite com o nome `freecast_test`; outra rodada registrou autenticacao PostgreSQL sem senha no Apache. A configuracao explicita dos processos e o preflight mais estrito eliminaram esses erros na rodada final.
- [x] `php artisan test` — 40 testes/199 assertions aprovadas. `phpunit.xml` aponta para `freecast_test`/PostgreSQL; o teste `PostgresTestDatabaseTest` passou. Consulta posterior confirmou sete migrations em cada base; `freecast` permaneceu com tres usuarios e 36 mesas. Nenhuma migration foi rodada em `freecast`.
- [x] Playwright atraves de um Quick Tunnel publico: `rooms.spec.ts` + `maps.spec.ts` — 3 passaram (1,4 min); `realtime.spec.ts` — 1 passou (1,2 min). Os tres contexts independentes validaram HTTPS/WSS no mesmo host, cookie `Secure`/host-only/`SameSite=Lax`, XSRF correspondente, ausencia de HTTP misto e nenhum 419.
- [x] O teste de mapas validou upload do fundo e tres tokens sinteticos, permissao, arraste/zoom/pan, posicoes persistidas apos reload e screenshot do canvas em viewport 867 × 465. O teste realtime verificou movimento recebido pelos tres participantes, chats simultaneos sem duplicacao, resultado unico de dado, mensagem privada e token oculto ausentes do terceiro context/trafego, reconexao, recuperacao apos reinicio do worker e revogacao de canal/midia.
- [x] `npm run build`, validacao Caddy, PHP lint e parse dos scripts PowerShell passaram. O build emite apenas o aviso ja existente de bundle JS maior que 500 kB. Uma chamada isolada de `tsc` para os testes nao foi validada por falta de `@types/node`; nenhuma dependencia foi adicionada.
- Nota de preservacao: os testes browser enviaram imagens sinteticas ao storage privado; depois, PHPUnit reinicializou os metadados de `freecast_test`. Os arquivos do storage compartilhado foram mantidos para evitar remover conteudo preexistente; alguns arquivos de teste podem permanecer sem metadados.
- [ ] Teste com tres computadores/rede externos continua pendente. Nenhuma porta foi aberta no roteador/firewall. Para o modo real, altere as senhas das contas demonstrativas; o preflight recusa as senhas conhecidas. A Parte 4 nao foi iniciada.
