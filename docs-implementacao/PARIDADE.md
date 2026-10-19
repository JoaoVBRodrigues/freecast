# Paridade visual e funcional — Partes 1 e 2

## Parte 4A — personagens e tracker independentes

- [Captura MCP 867 × 465](../capturas/parte4a-personagens-combate.png) mostra inspector com personagens, barras de PV e tracker, além do nome/PV derivado do token no canvas. Dados, nomes e retrato quadrado são sintéticos; posição/tamanho do token foi ajustado no fixture de teste para a captura.
- CharacterPanel modular aparece no painel de Combate da conversa e no inspector do mapa; mantém paleta, tipografia, densidade, navegação e acesso ao chat. Formulário de ficha inclui atribuição, retrato, publicação, notas privadas, atributos e recursos; jogador sem edição recebe resumo autorizado.
- Playwright com mestre e dois jogadores confirmou formulários, vínculo, PV ao vivo, tracker/rodada, privacidade de payloads, conflito, replay, reload/reconexão e fechamento de ficha após perda de autorização. Resultado real e limites do ambiente estão em PROGRESSO.md.
- A documentação original só comprova superfície visual e guard de mestre no script distribuído. PV, ACL, recursos configuráveis, vínculo, desempate e remoção são decisões próprias. A captura não compara fichas/tracker original nem prova equivalência de controles/comportamento. REA não foi necessário. 4B não foi iniciada.

## Referências alvo

- Início em viewport 867 × 465: navegação estreita, título sob linha de acento, destaque de mesa e quatro ações saturadas; recentes, favoritas e minhas em cartões.
- Mesa em viewport 867 × 465: abas no topo e três colunas densas para biblioteca/macros/combate, conversa e participantes.
- Tokens CSS iniciais iguais aos registrados em `docs-firecast/fase2/04-fontes-e-temas.md`.
- Famílias tipográficas distribuídas por pacotes Fontsource, não extraídas da aplicação original.

## Estado

Capturas reais do Playwright MCP foram feitas em 867 × 465 após seed e login:

- [Início](capturas/inicio.png) comparado com a tela de início em `referencias-visuais/1827783f-fd46-4fda-bd14-1cc4679c7dbb.png`.
- [Mesa](capturas/mesa.png) comparada com a tela de mesa em `referencias-visuais/591b091d-4b35-4424-aedc-aba2c335ec3e.png`.

A composição mantém navegação lateral estreita, destaque e ações no início, e três colunas de mesa com biblioteca/macros/combate, conversa e participantes. O seed, a paleta e a hierarquia foram conferidos no navegador; a tela da mesa não gerou erros no console após fixar o host local do Reverb. Ilustrações são novas formas CSS; ícones são Lucide; conteúdo do seed é sintético. O viewport usado tem a mesma dimensão das referências selecionadas.

Validação visual e funcional concluída: Playwright MCP capturou ambas as telas; os testes browser passaram incluindo mensagem em tempo real entre duas sessões. A paridade é visual/estrutural, não reprodução de conteúdo ou assets proprietários.

## Diferenças e limites assumidos

- Não se reproduz a moldura nativa do Windows; há uma faixa superior funcional dentro da página.
- A Parte 1 não implementa editor/execução de macros, parser de rolagem, upload, fichas nem regras de turno. Os controles correspondentes são apresentação inicial.
- O indicador de conectividade e as barras visuais de participantes não prometem telemetria nem valores de personagem.

## Parte 2 — biblioteca e editor de mapas

- A captura [Editor de mapas](capturas/editor-mapa.png) foi feita com Playwright MCP em viewport 867 × 465 usando fundo e tokens sintéticos próprios. Ela registra a aparência funcional implementada e não é uma comparação visual com o editor original.
- A documentação de investigação não validou visualmente o editor original. Por isso, zoom ancorado no cursor, pan por Espaço, controles de camada e o painel de propriedades são decisões novas, sem alegação de paridade comprovada.
- A interface mantém os tokens de cor, tipografia, densidade e hierarquia da Parte 1 e conserva o acesso à conversa da mesa por uma aba.
- O teste browser confirma upload do fundo, colocação e movimentação de três tokens, zoom/pan, encaixe, autorização, dados ocultos e recuperação após recarga. Não verifica sincronização entre navegadores, que pertence à Parte 3.

## Parte 3 — mesa multiusuário

- A captura [Mapa em tempo real](../capturas/mapa-tempo-real-parte3.png) foi feita por Playwright MCP em 867 × 465 com mapa/tokens sintéticos. Confere visualmente a tela e a composição existente do editor; não há captura validada do editor original para comparação e não se declara paridade nessa área.
- A captura [Chat formatado](../capturas/chat-formatacao-parte3.png) foi feita por Playwright MCP em 867 × 466; texto rico usa apenas os formatos independentes documentados e conteúdo HTML arbitrário aparece escapado. Não é comparação com um fluxo visual original.
- A apresentação mantém a faixa superior da aplicação, as abas, as três colunas, densidade, paleta, tipografia e acesso à conversa. O chat agora renderiza somente os marcadores próprios de negrito, itálico e sublinhado; HTML fornecido como texto permanece escapado.
- As tentativas anteriores registraram 419 e conflito de porta; foram corrigidas antes da rodada final. A validação Playwright via Quick Tunnel passou com três contexts independentes e Reverb do Freecast em 8082, conforme os resultados em `PROGRESSO.md`. A causa original do 419 não foi comprovada por A/B do cookie legado.
- O teste captura o canvas e verifica coordenadas/resultados por seletores do mapa em viewport 867 × 465; a geometria do editor é funcional da implementação própria. Não se afirma equivalência de controles, proporções internas ou comportamento ao editor original.
- Ainda não foi realizado piloto com três computadores físicos na LAN. As instruções de portas e endereços em `PROGRESSO.md` são um roteiro operacional a validar, não uma validação de rede concluída.
- A mudança de porta do Reverb não alterou a interface; as capturas continuam servindo apenas como evidência visual da implementação própria, sem alegação de paridade com o editor original.

## Preparacao do piloto publico temporario

- A preparacao nao altera os componentes visuais do Freecast; mantem a apresentacao existente e muda apenas a forma de servir paginas/assets e estabelecer o WebSocket.
- A rodada final via Quick Tunnel passou nos testes de salas/mapas e realtime em viewport 867 × 465. O navegador confirmou HTTPS/WSS no mesmo hostname, cookie `Secure`/host-only, ausencia de recursos HTTP mistos e os fluxos funcionais descritos em `PROGRESSO.md`.
- A captura [realtime-map-three-contexts.png](../test-results/realtime-map-three-contexts.png) documenta o canvas proprio com recursos sinteticos. Ela nao e comparacao visual com o Firecast nem comprovacao de paridade com o editor original, que nao foi validado visualmente.
- Os navegadores eram tres contexts independentes executados neste computador atraves do hostname publico. Isso valida o tunnel e o fluxo de rede externo ao loopback da aplicacao, mas nao substitui o teste com tres computadores/dispositivos fisicos.
