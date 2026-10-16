# Contrato estático NDB e importação/exportação

O SDK distribuído permite fechar parte do contrato Lua: regras de atribuição, acesso aos filhos, observadores, invólucros de transação e fluxo UTF-8 de exportação. O formato XML efetivo e as garantias de persistência continuam abertos, pois são implementados no host nativo. Nenhum cliente, script Lua, conta ou mesa foi executado.

Evidência local: [F2-NDB-001](../evidencias/fase2/ndb.json), produzida por [fase2-ndb.mjs](../scripts-analise/fase2-ndb.mjs). Esse ID identifica evidência independente; **não é Evidence emitida pelo REA**. Complementa a análise REA prévia sem reabrir sessão nativa. Procedimento: diretório central ZIP, descompressão somente em memória, SHA-256 e CRC dos membros, leitura textual UTF-8, linhas contadas desde 1 por CRLF/LF. Não são gravadas fontes completas. Reprodução: `node docs-firecast/scripts-analise/fase2-ndb.mjs`; argumentos opcionais são ZIP de entrada e JSON de saída.

O ZIP de entrada permaneceu com SHA-256 `892afd517cfec3a33d060feb6a1137c76d9519fb68f5dfcb1b90c1a3a8f34f2b`. Os dez membros selecionados passaram pela conferência de CRC. O JSON contém SHA-256, tamanho, identidade do pacote, nome do membro e seleções com linhas; permite conferir as referências abaixo contra os mesmos bytes.

| ID | Membro | SHA-256 |
|---|---|---|
| NDB-S1 | rrpginlua.rpk!/sdk/ndb.lua | `0f2bc02fedde396a46d86f0e32476ef993c1eca5f022cfc0fc08d0b2efbc5551` |
| NDB-S2 | rrpginlua.rpk!/sdk/ndb_observer.dlua | `c37111dcd48ec76e94f7b542c6f464879d6a8fdfbcd1f00afbcff6369755ddfd` |
| NDB-S3 | rrpginlua.rpk!/sdk/ndb_transaction.dlua | `d0fc244e7b7675df764f3466aa718c2ce3170bd0e5cca9bbb02337a5da130ae6` |
| NDB-S4 | rrpginlua.rpk!/FichaBasicaMultiPlataforma.lfm | `6f61d0b0872859cff3d91910c779df868aaf32cd9390c0eb2be3814e48169961` |
| NDB-S5 | rrpginlua.rpk!/NodeExplorer/PermissionsPopUp.lfm | `439d2a851149b1474833d641d9086642881e88e2a234eaf85a4f5657cf52ede5` |
| NDB-S6 | rrpginlua.rpk!/NodeExplorer/explorerHost.lua | `8bf21f80e972062c19fbe29f6389d8a75eb4558f54b3541fc8ede6a4cdcffb08` |
| NDB-S7 | rrpginlua.rpk!/firedrive/firedriveNavigator.dlua | `7e26321276979e2408e2028612c0109d5a9f0092afe5c151e95e4536e7097fc1` |
| NDB-S8 | rrpginlua.rpk!/sdk/fireDriveCore.dlua | `df9a8226c1b9223e3a44215de71ac885dfa4d0050e72a06d4b6598019fe8a8d6` |
| NDB-S9 | DnD5_SDK3.rpk!/fichaDnD5.lfm | `b4a89c0e2b3126c34368099d721dc8bb04a15e60b4591389fa3da70dcd2ff869` |
| NDB-S10 | sceneinlua.rpk!/inseridortokens/sceneInseridorFireDrive.lfm | `d099331854f59909c906c9255820df43f90caf89c74484a53431a0d6a8e283d7` |

## Nós, atributos e conversões

Confirmado em NDB-S1: a fachada Lua consulta primeiro um filho com a chave solicitada e só então o atributo (293–304). A escrita normal converte a chave com `tostring` (231–232) e aplica estas regras:

| Valor atribuído | Regra implementada em Lua | Fronteira nativa |
|---|---|---|
| `nil` | Solicita remover atributo e filhos de mesmo nome | `RemoveAtributeAndChildNodes`, grafia original, 235–237 |
| função | Gera erro localizado | 240–244; não chega à atribuição |
| tabela comum | Remove atributo homônimo, encontra/cria filho e copia a tabela para ele | 247–275; criar/remover/atribuir são chamadas nativas |
| nó ou fachada | Usa cópia recursiva para o filho, com controle de handles visitados | 171–184 e 261–266 |
| outros tipos | Solicita remover filhos homônimos e atribui atributo | 277–280; validação/conversão efetiva pelo host desconhecida |

A cópia de tabela limpa o destino antes de percorrer seus pares; a cópia de nó usa `beginUpdate`/`endUpdate` com `tryFinally`. Isso **não** prova rollback ou atomicidade. Não foi encontrada validação Lua geral de número, booleano, string, userdata ou thread no ramo escalar. Também não há limite geral de tamanho ou profundidade nessa atribuição. Tabela comum circular merece teste: o controle de visitados visto na cópia de nós não aparece em `copyTableToNode`.

`getAllChilds` percorre índices nativos de 0 a count−1 e produz array Lua de 1 a count (76–92). `getChildNodes(nil)` e `getAttributes(nil)` devolvem tabelas vazias; `getNodeHandle` só aceita uma tabela marcada como fachada (464–477). `isNodeObject` retorna o marcador raw, podendo ser `nil` para tabela comum, não necessariamente booleano `false`. `pairs`/`ipairs` são substituídos globalmente pelo módulo: preparam atributos e filhos por nome (909–924). Filhos podem sobrepor chaves de atributos nesse estado; a ordenação vem das funções originais e não é especificada aqui.

`load` expande o caminho via VHD, adiciona `/` inicial quando necessário, substitui userName não string por `""` e reutiliza banco aberto pela chave de caminho, sem incluir userName nessa chave (365–386). `newMemNodeDatabase(initialContent)` passa o argumento diretamente ao construtor nativo e devolve a raiz; não define formato de initialContent em Lua (390–405). Isso não estabelece o formato físico do banco local.

## XML: assinatura, retorno e consumidores

Confirmado em NDB-S1 111–117 e 552–582: `NDB.exportXML(nodeObj)` delega a `ExportToXMLString`; `NDB.importXML(nodeObj, xmlString)` delega a `ImportXMLString`. Não existem parâmetros de opções, flags ou configuração de codificação nesses wrappers. Nó nil ou fachada sem nó gera erro localizado. Não há validação XML em Lua.

O import converte o segundo argumento por `(tostring(xmlString)) or ""`. Sob a semântica padrão de Lua, nil vira a string `"nil"`; a expressão **não** implementa default de string vazia para nil. A função `tostring` não foi executada nesta investigação. Export e import repassam o resultado da chamada nativa: o tipo efetivo de retorno do host não está declarado. O nome `ExportToXMLString` e seu consumo como texto sustentam a inferência de retorno textual para export; para import não há retorno tipado recuperado.

`NDB.copy(dest, src)` chama export no source e import no destino; com source nil/sem nó solicita limpar destino (599–605). Esse uso mostra intenção de cópia por XML; não demonstra round-trip sem perdas, substituição/mesclagem, permissões preservadas, IDs ou comportamento de XML inválido.

NDB-S9 43–75 fornece um exemplo **oficial distribuído de fluxo**, sem fornecer um documento exportado: exporta `sheet`, codifica UTF-8, escreve stream e pede salvar `sheet.xml` como `application/xml`; a importação abre um arquivo, lê seu tamanho total, decodifica UTF-8 e chama importXML. Não há limite explícito de tamanho nem teste de cancelamento visível no callback selecionado. NDB-S10 1001–1028 repete o padrão para `tokens.xml`. Não se confunde esse XML de dados com o LFM de interface.

A busca literal em 446 membros textuais dos cinco RPK encontrou 72 referências aos quatro nomes de import/export, incluindo definições e duplicatas compiladas para Lua. A lista completa está no JSON. Não foi identificado nessa busca um XML sintético exportado pronto ou schema NDB; a busca de chamadas não exclui um fixture sem referência ou chamadas dinâmicas. Não foi inventado exemplo de formato real.

## Observadores e prontidão

NDB-S2 7–23 declara `enabled` tipo bool e os eventos: onChildAdded, onChildRemoved, onDeepChildAdded, onDeepChildRemoved, onDeleted, onChanged, onDeepChanged, onUserChanged, onDeepUserChanged, onPersistedChanged, onDeepPersistedChanged, onPermissionListChanged, onDeepPermissionListChanged, onFinalPermissionsCouldBeChanged e onStateChanged. Os seis eventos Changed de atributos recebem `node, attribute, oldValue`; os demais recebem `node`. O wrapper conecta `TLuaNodeObserver` ao handle e guarda o nó (44–48). Não estabelece ordem, agregação, entrega, origem de user/persisted, valor novo ou herança deep.

`getState` devolve ProviderState do host e usa `"closed"` como fallback; `onReady` agenda callback com delay 1 quando aberto, agenda falha para nil/closed e monitora OnProviderStateChange/OnLoaded, removendo listeners após decidir (NDB-S1 632–641, 845–901). O número 1 é argumento de setTimeout, não garantia de latência observada. NDB-S5 129–140 usa um observer para liberar a interface após estado deixar `opening`.

## Transações, permissões e FireDrive

NDB-S3 expõe commit, rollback, createUndoData e applyUndoData. Todos delegam ao host; createUndoData encapsula o handle retornado, e applyUndoData passa `undoData.handle` sem guarda nil (8–24). newTransaction cria `TLuaNodeTransaction`, inicializa com handle e guarda o nó (46–50). NDB-S1 738–761 passa handle ou 0 a PushTransaction e chama PopTransaction. Atomicidade, isolamento, escopo em filhos e undo após commit continuam desconhecidos.

NDB-S5 cria transação ao mostrar o popup (113–122), empilha-a antes de setPermission e desempilha depois (255–267), solicita rollback ao ocultar (147–150); controles posteriores solicitam rollback/commit (380–405). Isso confirma uso pela UI, não eficácia nem precedência de permissões no servidor. Seletores user/group, sete nomes de permissão e valores allow/deny/strongAllow/indefinido estão no consumidor; o wrapper converte nomes e IDs com tostring e passa allowance sem conversão. O fluxo por LocalID está em NDB-S6 14–21. A avaliação final de permissões permanece nativa.

NDB-S4 9–23 vincula quatro editores a atributosStr, habilidadesStr, equipamentosStr e historiaStr: campos dessa ficha, sem schema universal. NDB-S7 252–259 e 326–330 usa banco em memória como estado da lista FireDrive. Isso não demonstra que os arquivos remotos sejam persistidos em NDB.

NDB-S8 96–135 confirma limite Lua de upload **maior que** 128 × 1024 × 1024 bytes: falha opcional `FILE TOO BIG`, retorno nil; exatamente 128 MiB passa por esse teste. O stream é reposicionado em 0 antes do limite. Upload usa stream compartilhado e MIME informado ou inferido; quickUpload valida MIME. Esse limite pertence ao wrapper FireDrive e não ao XML NDB nem a uma cota garantida do servidor. Transporte, permissões e armazenamento remotos não foram observados.

## Situação das perguntas

| Pergunta | Resultado |
|---|---|
| Acesso, atribuição, filhos e cópias Lua | Respondida estaticamente; operações nativas opacas |
| Opções/defaults e caminho import/export | Respondida no wrapper e consumidores distribuídos |
| XML real, schema e compatibilidade | Não resolvida; nenhum export de execução produzido |
| Eventos e transações disponíveis | Interfaces respondidas; garantias parcialmente respondidas |
| Persistência, sincronização, conflito, autoridade | Não resolvida |

Próximo experimento dependente de runtime: banco sintético em memória com string Unicode, número, booleano, ausência e filhos repetidos; export antes/depois; import em outra raiz; comparar atributos/filhos; registrar retornos e erros para XML inválido e nil. Separadamente, medir observer e rollback em nó sintético. Esses testes devem usar o host real com autorização e evidência própria; o presente resultado não os substitui.
