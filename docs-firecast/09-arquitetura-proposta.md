# Arquitetura proposta para uma aplicação independente

## Decisão e limites da proposta

Propõe-se um monólito modular Laravel, interface Vue com Inertia no navegador, atualizações WebSocket por Reverb/Echo, um banco SQL e arquivos privados no disco local. A fila começa no próprio banco. Redis, renderização no servidor (SSR) e microserviços não são pré-requisitos. Versões de PHP, Laravel, Vue, Inertia, Reverb, Echo, Node e banco serão selecionadas e fixadas ao iniciar a implementação, após verificar compatibilidade e requisitos oficiais; este documento não fixa números pela documentação corrente.

Esta é uma decisão de projeto independente, não uma descrição da arquitetura interna do Firecast. S01–S07 sustentam a necessidade visual de conversa com formatação e imagens, compositor com expressão de dados, macros, painéis, lista de participantes e catálogos de mesas. Elas não estabelecem as regras originais de execução, permissões, persistência ou sincronização. Campos calculados e scripts abaixo são extensões propostas e dependem de requisitos adicionais.

Inertia permite integrar componentes Vue a rotas e controladores do servidor; isso favorece manter navegação, sessão e autorização no mesmo projeto. [Documentação oficial do Inertia](https://inertiajs.com/). Reverb fornece o servidor WebSocket integrado ao ecossistema Laravel; Echo recebe os eventos no navegador. [Reverb](https://laravel.com/framework/docs/reverb), [broadcasting](https://laravel.com/framework/docs/broadcasting).

| Alternativa | Adequação à proposta | Decisão |
|---|---|---|
| Blade/Livewire | Bom para páginas e formulários; interação contínua de compositor, painéis e estados locais exigiria uma estratégia adicional de estado no navegador | Possível alternativa; não é a base escolhida |
| Vue/Inertia | Componentes interativos com rotas e sessão centralizadas no backend | Escolhida |
| SPA separada + API | Facilita clientes independentes, mas amplia contratos, autenticação e operação para o primeiro produto | Reavaliar se surgir outro cliente |
| Polling HTTP | Simples; consultas repetidas e atraso dependem do intervalo | Recuperação e diagnóstico |
| SSE | Eventos servidor → navegador; comandos continuam por HTTP | Viável, sem vantagem suficiente para mudar a escolha |
| WebSocket | Canal contínuo para eventos e presença; requer processo persistente e reconexão | Reverb/Echo; comandos duráveis continuam por HTTP |

## Módulos e responsabilidades

| Módulo | Responsabilidade | Origem da necessidade |
|---|---|---|
| Identidade | Contas, sessão, perfis e avatar | Perfis e participantes em S01; personalização em S03 |
| Mesas | Catálogo, associação, favoritos, histórico de acesso e papéis | S03–S05; regras propostas |
| Conversa | Mensagens estruturadas, histórico, canais e referências de mídia | S01 e S06 |
| Dados | Parser limitado, avaliação no servidor e resultado persistido | Expressão em S06; semântica a validar |
| Macros | Catálogo pessoal/da mesa, versões e execução autorizada | S01 e S07; linguagem desconhecida |
| Biblioteca e mídia | Pastas, referências e arquivos privados | S01 |
| Participantes e fichas | Presença, perfis, atributos e barras configuráveis | S01; fórmulas ainda não observadas |
| Layout e combate | Preferências de painéis e estado de iniciativa/turnos | S01–S02; regras desconhecidas |
| Sincronização | Eventos, outbox, retomada e entrega | Necessidade da nova aplicação multiusuário |

Controladores validam HTTP e delegam a casos de uso. Casos de uso aplicam policies e transações; serviços de domínio calculam dados e fórmulas. Jobs cuidam de tarefas repetíveis e publicação. Componentes Vue apresentam estado e intenções; o servidor decide o estado persistente. A existência de módulos não exige processos ou bancos separados.

## Modelo de dados inicial

Um banco PostgreSQL é a preferência proposta para desenvolvimento e uso compartilhado; outro SQL só deve ser adotado após rever bloqueios, índices e tipos. Há uma única fonte persistente de verdade.

| Grupo | Entidades propostas e restrições importantes |
|---|---|
| Identidade | `users`, `profiles`, sessões; identidade pública separada de credenciais |
| Mesas | `rooms`, `room_memberships` com unicidade sala/usuário, `room_favorites`, `room_visits`; papéis explícitos |
| Conversa | `conversations`, `conversation_members`, `messages` com autor, conteúdo estruturado, versão e sequência; visibilidade explícita |
| Mídia | `assets` com proprietário, sala, tamanho, tipo detectado, hash e caminho interno; `message_assets`, `library_items` e pastas |
| Dados/macros | `dice_rolls` com expressão normalizada e resultado; `macros`, `macro_versions`, `macro_executions` |
| Fichas | `characters`, `sheet_templates`, `sheet_fields`, `character_values`; tipo, validação e versão de esquema |
| Layout/combate | `user_room_layouts`, `combat_sessions`, `combat_entries`; revisão para concorrência |
| Consistência | `room_sequences`, `room_events`, `outbox_entries`, `command_receipts`; jobs e falhas da fila |

Campos calculados usam expressão declarativa com funções permitidas, tipos, limites e grafo de dependências. Detectar ciclos antes de salvar; recalcular no servidor em ordem definida e persistir a revisão dos dados de entrada. Não usar `eval` de PHP ou JavaScript. Fichas e fórmulas não têm paridade original estabelecida pelas capturas.

## Autenticação e autorização

Usar sessão do mesmo domínio, cookies adequados ao ambiente e CSRF nos comandos HTTP. Policies verificam leitura de mesa, associação, papel, edição de mensagem, arquivo, macro e ficha. A propriedade de um ID não substitui autorização. Proposta inicial: proprietário, mestre e jogador, com matriz explícita de capacidades; essa matriz não é uma regra extraída do Firecast.

Privacidade deve existir no banco e no payload. A presença pública de um membro não autoriza ler suas conversas privadas. Autorizar canais privados e de presença por usuário e sala; o Laravel oferece callbacks de autorização desses canais. [Broadcasting: autorização e presença](https://laravel.com/framework/docs/broadcasting). Mudanças de associação exigem revalidação e retirada de acesso; definir e testar a duração máxima de uma assinatura previamente autorizada. Segredos não devem ser enviados a um canal amplo para filtragem no navegador.

## Contratos HTTP e eventos

As rotas abaixo são propostas versionadas; não representam o protocolo original.

| Operação | Contrato proposto |
|---|---|
| Navegação | `GET /rooms`, `GET /rooms/{room}` via Inertia |
| Histórico | `GET /api/v1/rooms/{room}/messages?before=...` com cursor |
| Comandos | `POST /api/v1/rooms/{room}/messages`, `/rolls`, `/macro-executions` |
| Macros/layout | `POST .../macros`, `PATCH .../macros/{macro}`, `PUT .../layout` |
| Upload | `POST .../assets` multipart; `GET /assets/{asset}` autorizado |
| Retomada | `GET .../events?afterSequence=...` e `GET .../snapshot` |

Todo comando durável inclui `idempotencyKey` único por ator/sala/operação e um hash do conteúdo normalizado. Repetir a chave e o mesmo conteúdo retorna o recibo original; reutilizá-la com conteúdo diferente retorna conflito. Atualizações concorrentes incluem `expectedRevision`; conflito retorna a revisão atual. Uma resposta aceita contém `commandId`, IDs criados, revisão e sequência, sem depender da chegada do WebSocket. Erros distinguem validação, falta de acesso e conflito.

Envelope de evento: `schemaVersion`, `eventId`, `roomId`, `sequence`, `type`, `entityId`, `entityRevision`, `commandId`, `idempotencyKey`, `occurredAt` e `payload` autorizado. Exemplos: `message.created`, `dice.rolled`, `macro.updated`, `character.updated`, `combat.updated`. Presença é transitória e não integra o histórico durável.

Na mesma transação SQL, o caso de uso registra o comando, modifica o domínio, incrementa a sequência da sala com bloqueio, grava o evento e a outbox. A entrega ocorre depois do commit por worker. Falha entre publicar e marcar como entregue pode duplicar eventos: consumidores deduplicam por `eventId`, e o dispatcher pode tentar novamente. O frontend confirma mensagens otimistas pelo `commandId`, inclusive quando HTTP e WebSocket chegam em ordem inversa.

Uma sequência global por sala pode conter eventos de audiências diferentes. A retomada devolve somente eventos autorizados e um `nextSequence` correspondente ao intervalo examinado; ausência de um número visível não implica perda. O cliente usa consultas de retomada para fechar intervalos, sem receber conteúdo privado. Saltos de sequência podem revelar atividade: se esse metadado também precisar ser privado, expor cursores opacos e ordenar a entrega por audiência, mantendo a sequência interna da sala. Se os eventos necessários expiraram, receber `snapshotRequired` e recarregar snapshot com cursor consistente. Carregar snapshot em N, assinar e buscar após N resolve eventos ocorridos durante a conexão. Mudanças de acesso e paginação são revalidadas em cada consulta. Após reiniciar os processos, o banco permite reconstruir o estado; a fila WebSocket não é o histórico.

## Uploads e execução de conteúdo

Armazenar arquivos em um disk privado local fora do diretório público; o Laravel abstrai armazenamento por disks, incluindo o driver local. [File Storage](https://laravel.com/framework/docs/filesystem). Download passa por autorização ou autorização temporária de escopo limitado, sem revelar caminho físico. Validar tamanho, tipo real e limites de imagem; criar nomes internos aleatórios, miniaturas em job e limpar órfãos após janela definida. Tratar texto rico como documento estruturado com marcações permitidas e sanitização, sem HTML ou scripts arbitrários. Aplicar quotas por usuário/sala e incluir arquivos no backup.

Macros iniciais são ações declarativas versionadas, por exemplo chamar o serviço de rolagem ou produzir uma mensagem validada. Não executar plugins existentes, Lua, JavaScript ou binários obtidos da instalação. Compatibilidade de scripts depende de evidência própria de linguagem, APIs e permissões. Um eventual executor de scripts exigiria uma etapa separada: processo/worker isolado, protocolo mínimo, ausência de rede e acesso a arquivos por padrão, limites de CPU/memória/tempo e testes de encerramento. Isso ainda não está implementado e não é garantia de compatibilidade.

## Organização e operação local propostas

Estrutura futura: `app/Modules/{Identity,Rooms,Chat,Dice,Macros,Library,Characters,Combat,Sync}`, `resources/js/{Pages,Components,Features}`, `routes/{web,api,channels}.php`, `database/{migrations,factories,seeders}`, `tests/{Feature,Domain,Browser}` e `storage/app/private`. Manter este dossiê documental separado do aplicativo. Não criar essa estrutura executável nesta fase.

Requisitos locais: PHP/extensões compatíveis com a versão escolhida, Composer, Node e gerenciador fixado por lockfile, PostgreSQL, navegador e permissão para escrever somente em storage/cache. Processos: servidor HTTP, compilador de assets no desenvolvimento, worker da fila de banco e Reverb. Configuração `.env.example` sem segredos; portas e origens de WebSocket explícitas. Para dois amigos em outros computadores, planejar acesso ao mesmo host por LAN ou um ambiente controlado com HTTPS/WSS; `localhost` de cada navegador é um computador diferente.

Testar com três contas sintéticas — anfitrião e dois amigos — em perfis de navegador separados, incluindo entrada simultânea, mensagem e imagem, rolagem, macro, conflito de edição, perda e retomada de conexão e impedimento de leitura privada. Registrar versões, latência observada, cursores e resultados. Só ampliar infraestrutura quando medidas de carga justificarem; backup/restauração SQL + arquivos, retenção de eventos e monitoramento de outbox fazem parte da operação desde o primeiro piloto.

## Acesso aos recursos na implementação própria

Proposta a partir da preferência expressa pelo usuário: macros, criação de mapas e organização em pastas integram o núcleo, sem assinatura Gold, créditos ou pagamento por desbloqueio. Isso é escolha para o novo produto, separada das restrições comerciais relatadas sobre o original. Permissões de mestre/jogador, cotas técnicas e custos reais de hospedagem/armazenamento continuam sendo questões distintas. A investigação segue sem implementar o aplicativo.
