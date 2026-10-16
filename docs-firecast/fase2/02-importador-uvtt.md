# Contrato estático do importador dd2vtt

O importador empacotado permite recuperar um contrato concreto de cena, paredes, portais, luzes e imagem. A análise responde parte de E07/E10/E11; não demonstra uma importação executada, exportação, roundtrip ou sincronização.

Evidência independente: [F2-UVTT-001](../evidencias/fase2/uvtt.json). Cada afirmação no JSON contém arquivo, SHA-256 e intervalo exato de linhas. Esse identificador pertence à investigação local, não ao ledger do REA. Procedimento: [fase2-uvtt.mjs](../scripts-analise/fase2-uvtt.mjs), usando o parser ZIP já existente, leitura/descompressão em memória e inspeção textual; nenhum Lua executado nem payload proprietário exportado. Reproduzir da raiz com `node docs-firecast/scripts-analise/fase2-uvtt.mjs`.

Identidades SHA-256:

| Artefato | SHA-256 |
|---|---|
| resource.zip | `892afd517cfec3a33d060feb6a1137c76d9519fb68f5dfcb1b90c1a3a8f34f2b` |
| complementosPadroes/UniversalVTTImporter.rpk | `b6d36cc71a23871c700172d6fe48201fd5c41aed4265640ac167eb8e5b049e33` |
| uvtt.lua | `6c0b3862e688bbd981c1114fe35d53fbbdd7f210f315bce3762a5e007a57430c` |

O RPK contém 55 membros e tem CRC coerente. `module.xml:2–18` identifica `Ambesek.dd2vtt`, versão 1.2, SDK 3.7/API 88.1. As linhas citadas a seguir pertencem ao `uvtt.lua` de 219 linhas salvo indicação diferente. São contadas após decodificação UTF-8 e separação CRLF/LF, sem remover BOM.

O registro de plugin adiciona o botão ao grupo `Gerador`, rótulo `Importar dd2vtt`, ícone interno `/icos/dd2vtt.png`. O callback testa `scene.isGM` e retorna sem abrir diálogo se falso; o botão é registrado antes desse teste. Em seguida abre seleção de arquivo `.dd2vtt` com múltipla seleção falsa e usa `arquivos[1]`, leitura UTF-8 e `decode` (`87–104`, UVTT-02). O wrapper expõe `isGM` como propriedade nativa somente de leitura (`sdk/sceneWrappers.dlua:62–75`). Isso comprova uma guarda cliente; a política do servidor permanece desconhecida.

| Campo efetivamente lido | Transformação / resultado | Linhas |
|---|---|---|
| `resolution.map_size.x`, `.y` | largura/altura do mundo = valor × `scene.grid.cellSize` atual | 104–107 |
| `image` | `writeBase64` em stream de memória; upload sugerido como PNG | 109–143 |
| `line_of_sight[]` | cada lista vira segmentos entre pontos adjacentes `{x,y}` e cada segmento vira quadrilátero opaco | 145–172 |
| `portals[].bounds[1]`, `[2]` | mesmo algoritmo de quadrilátero, sempre opaco; índices são Lua, correspondentes aos dois primeiros pontos JSON | 174–194 |
| `lights[].position.x`, `.y` | token em `background`, posição `(coordenada − 0,5) × cellSize` | 208–212 |
| `lights[].range` | `lightIntenseRange = range × cellSize`, `lightAngle = 360` | 213–215 |

O código completo não lê versão/formato, `resolution.pixels_per_grid`, origem do mapa, cor/intensidade de luz nem estado aberto de portal. Isso é uma busca negativa limitada ao importador 1.2, não ao suporte geral do Firecast ou à especificação Universal VTT. As unidades de entrada são tratadas como múltiplos da célula existente. Não há conversão explícita por pixel, unidade métrica, offset da grade ou inversão de eixo. O ajuste de −0,5 nas luzes é observado; explicá-lo como compensação pela âncora visual do token é apenas hipótese, pois o renderer nativo não foi analisado (UVTT-03/06/09).

Para um segmento de A a B, `wallWidth=0.05`: se `abs(A.x-B.x)>abs(A.y-B.y)`, os quatro vértices são A/B deslocados em y por ±0,05 e multiplicados pela célula. Caso contrário, desloca x. A espessura total nesse eixo é 0,1 célula. Isso não calcula normal perpendicular para diagonais e não fecha automaticamente uma polilinha; só processa pares adjacentes. Segmentos degenerados não são filtrados. Portais usam os dois primeiros `bounds`; outros atributos não são usados. O importador não cria objeto de porta interativa (UVTT-04/05).

Quando pelo menos uma parede ou portal é adicionado, ativa `fogOfWar.enabled` e define o retângulo `(0,0)–(worldWidth,worldHeight)` como `explorable`. Sem paredes/portais, não muda explicitamente esses dois estados. As chamadas `addOpaqueArea`, `setArea`, `addToken` e propriedades de luz chegam a métodos/propriedades nativos via SDK; o comportamento visual, persistência, validação, eventos e replicação não foi executado (`sdk/sceneWrappers.dlua:550–552,718–720,1003–1011`, UVTT-11).

O caminho inicial cria `/uploads` e `/uploads/AAAA-MM`, sem esperar callbacks de sucesso dos diretórios, e solicita upload em `/uploads/AAAA-MM/` + nome do arquivo + `.png`. Não inspeciona formato da imagem antes de sugerir essa extensão. Sucesso associa `fditem.url` ao fundo. Falha oferece salvar localmente ou comprimir e converter para JPEG (`112–143`). O wrapper de upload reinicia a posição do stream e rejeita tamanho estritamente maior que 128 MiB com `FILE TOO BIG`; delega operações ao plugin `RRPG.FIRECAST.FMXModule` (`sdk/fireDriveCore.dlua:1–5,22–77,96–151`). Isso é limite local desse SDK; cotas e políticas do serviço não foram determinadas (UVTT-08).

A alternativa de compressão lê configuração `uvttConfig.xml`. Se não houver chave, abre a página de desenvolvedores TinyPNG e solicita uma API key. Guarda em `config.apiKey` a codificação Base64 de `api:` + valor; a codificação não é criptografia. Descreve um POST autenticado Basic ao endpoint de redução Tinify com o stream; usa o header de resposta `Location` para outro POST que solicita `image/jpeg`; depois tenta upload FireDrive como JPEG. Se esse upload falhar, oferece salvamento JPEG local. No callback de erro HTTP, somente a string exata `HTTP/1.1 401 Unauthorized` limpa a chave. Não há teste explícito de status no callback de resposta (`6–85`, UVTT-07). Nenhum endpoint foi acessado nem chave/configuração pessoal lida nesta investigação. O wrapper HTTP delega `Send` ao objeto nativo `TLuaHTTPRequest`; não estabelece sozinho detalhes do envio real (`sdk/internetCore.dlua:89–177`).

Não há validação de schema, tipos, tamanho de arrays, dimensões positivas ou versão no handler, tampouco `pcall`, transação, limpeza de cena ou rollback (`87–219`). Inferências estáticas: entrada sem `resolution.map_size` pode falhar antes do upload; arrays ausentes ou nulos podem falhar em `ipairs`; ponto sem coordenadas pode falhar durante a geometria; um erro posterior pode deixar dimensões, áreas ou tokens já alterados. Uma repetição pode acumular itens porque só adiciona paredes/luzes. O upload assíncrono não condiciona a criação de geometria ao sucesso, e cancelar a alternativa de upload não desfaz essas alterações. Todos esses efeitos precisam de teste controlado para confirmar detalhes nativos (UVTT-09).

O parser JSON informa linha/coluna no erro, rejeita dados adicionais após o valor principal e converte `null` em `nil` (`jsonparser.lua:33–37,48–59,250–260`). Arrays são tabelas numéricas e objetos atribuem por chave, de modo que chaves repetidas substituem valores anteriores (`159–219`). Não há limite explícito de profundidade/tamanho nesse parser; isso não significa capacidade ilimitada, pois memória, runtime e camada nativa impõem limites ainda desconhecidos (UVTT-10).

Exemplo sintético independente, calculado manualmente e registrado no JSON: com célula 50, mapa 4×3 gera mundo 200×150. Segmento `(0,1)→(2,1)` gera `(0,47,5),(100,47,5),(100,52,5),(0,52,5)`. Portal `(2,1)→(2,2)`, mesmo acompanhado de `closed:false`, gera quadrilátero opaco de x 97,5 a 102,5 e y 50 a 100. Luz em `(1,5;2,5)`, alcance 3, gera token `(50,100)`, alcance intenso 150 e ângulo 360. O exemplo usa placeholder de imagem e não constitui arquivo importável. Não foi executado contra o alvo.

Estado das perguntas: campos e fórmulas, fluxo GUI, guarda cliente e dependências estão respondidos por leitura estática com alta confiança dentro desse arquivo. Falhas são parcialmente respondidas por controle de fluxo. Permissões efetivas, resultado visual, unidade física, comportamento de portas, serviços, sincronização, repetição e recuperação permanecem sem validação dinâmica. Próximo experimento útil: mapa próprio mínimo em cena sintética vazia, primeiro com arrays vazios e depois com um segmento, um portal marcado aberto e uma luz; observar dimensões, duplicação em segunda importação e comportamento após falha de upload, preservando dados próprios e sem usar mesas pessoais.
