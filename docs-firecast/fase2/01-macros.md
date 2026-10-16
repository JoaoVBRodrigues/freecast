# Fase 2: contratos estáticos de macros

O pacote contém lógica legível suficiente para recuperar os três escopos, a precedência de comandos, os campos básicos e o caminho de execução de macros. Isso reduz parte da lacuna E04; permissões efetivas, sincronização, resultados de dados e persistência real continuam sem observação de execução.

A evidência [F2-MACROS](../evidencias/fase2/macros.json) contém 12 identidades de membros e 17 recortes identificados `MAC-001` a `MAC-017`, com SHA-256, linhas, intervalos de bytes, hash de cada intervalo e amostras mínimas. São observações estáticas do artefato distribuído, não testes funcionais. Nenhum Lua, formulário ou macro foi executado.

Origem: `resource.zip` SHA-256 `892afd517cfec3a33d060feb6a1137c76d9519fb68f5dfcb1b90c1a3a8f34f2b`, membro `complementosPadroes/rrpginlua.rpk` SHA-256 `64dbda95c73dffe6ae3284f87cf2ce3b479e2c2ada6b6efc4ce33ff8db6bb4a4`. Offsets ZIP internos são relativos ao RPK descomprimido em memória; os intervalos de texto são relativos ao membro descomprimido. O JSON preserva ambos. Os arquivos `.lfm.lua` gerados foram inventariados; os contratos abaixo se apoiam nos `.lfm`, `.lua` e `.dlua` legíveis, sem presumir qual representação foi carregada pelo cliente.

## Modelo lógico recuperado

| Escopo | Caminho no código | Observação |
|---|---|---|
| Pessoal global | NDB do usuário chamado `macros`, raiz `global.macros` | Disponível à busca sem mesa selecionada |
| Pessoal desta mesa | Mesmo NDB, raiz `mesas[codigoInterno].macros` | A busca usa `tostring(mesa.codigoInterno)`; o gerenciador indexa com `self._mesa.codigoInterno` |
| Para todos da mesa | `mesa:abrirNDBDeMesa("RRPG_Macros", ..., {criar=true})`, raiz `macros` | Documento de mesa separado do documento do usuário |

Fontes: `MAC-002`, `MAC-008`, `MAC-016`, `MAC-007`. Os nomes das raízes descrevem o esquema lógico acessado; não provam tipos internos de chave, formato XML nem organização física em disco.

Cada filho de `macros` fornece `macro` (nome), `acoes` (corpo; valor preparado padrão `""`) e `tipoMacro` (padrão preparado `"S"`). O editor oferece `S` simples e `L` Lua. Somente o valor exato `L` segue o compilador Lua; outros valores seguem o ramo simples. A criação de item usa `append()`; não foi encontrada chave de unicidade explícita nesse fluxo. Fontes: `MAC-001`, `MAC-004`, `MAC-009`, `MAC-008`.

O código de preparação filtra nomes normalizados vazios. Se filhos distintos virarem o mesmo nome preparado, a atribuição `nodoDestino[macroName] = macroPreparado` sobrescreve o valor anterior durante a enumeração. A ordem dos filhos de NDB não foi estabelecida; não há garantia documentada sobre qual duplicata vence (`MAC-001`).

## Nomes e precedência

A preparação do nome remove ocorrências dos caracteres espaço ASCII, `/` e `.` pelo padrão Lua `([ /.]+)`. Isso não equivale a eliminar todo whitespace: tabulações, por exemplo, não aparecem nesse padrão. A chave de busca usa `string.upper(Utils.removerAcentos(name))`. A remoção concreta de acentos depende de `Utils`, fora do recorte. O campo do editor aplica a preparação por `dataLink`; não foi encontrado limite `maxLength`, validação de palavra reservada ou rejeição explícita de duplicatas nos 12 membros. Fonte: `MAC-001`, `MAC-009` e busca limitada registrada no JSON.

A resolução de um comando segue:

1. Macro pessoal associada à mesa atual.
2. Macro pessoal global.
3. Macro compartilhada da mesa, se as anteriores não forem encontradas.

Essa ordem é implementada por retornos e condições no `globalFindMacro` (`MAC-003`). O listener `HandleChatCommand` trata `MACROS` e `RESETMIGRATION` antes de buscar nomes de macro, portanto esses dois comandos possuem precedência nesse handler. Ele anuncia `/macros` em sua resposta de comandos disponíveis (`MAC-006`). A precedência perante outros plugins/listeners do chat não foi estabelecida.

O painel do dock enumera os três escopos, na ordem compartilhadas, pessoais globais e pessoais da mesa, produzindo itens sequenciais; não usa um mapa pelo nome para eliminar colisões. O clique executa diretamente o item copiado para a lista, com parâmetro vazio e o chat ativo, sem resolver novamente o nome. Assim, o código permite que um botão de macro compartilhada execute seu próprio corpo mesmo quando uma macro pessoal homônima seria escolhida pelo comando digitado. Esta é uma consequência estática dos caminhos `MAC-010` e `MAC-011`; requer confirmação visual com dados sintéticos.

## Execução e expansão

Macro simples percorre o corpo com `[^\n\r]+` e chama `chat:enviarMensagem(linha)` para cada segmento, se houver chat. Linhas vazias não são emitidas; linhas contendo somente espaços não são eliminadas por esse trecho. Não há substituição explícita de placeholders ou concatenação de parâmetros no ramo simples. Se os comandos enviados forem reinterpretados pelo núcleo do chat, isso ocorre fora do ramo examinado (`MAC-004`).

Macro Lua chama `compileMacro`, injeta `parametro = message.parameter or message.parametro` e compila o corpo em modo textual com `load(body, "/" .. string.lower(macroName), "t", ambiente)`. Um erro de compilação passa pelo prefixo localizado `macros.error.compilingError`; a função compilada inicia em coroutine. `parameter` é um alias tardio de `parametro`; `arg` separa esse valor em tokens pelo padrão `[^%s]+`, sem parser de aspas nesse ponto. Fontes: `MAC-004`, `MAC-012`, `MAC-013`.

Existe proteção `executingMacros[macro.macro]`: uma entrada já marcada gera erro. O código limpa a marca no callback e também logo depois do retorno de `pcall(realExecutarMacro, ...)`. Portanto, não se deve afirmar que a proteção dura por toda uma macro Lua suspensa em coroutine; o ciclo assíncrono precisa de experimento. A chave contém apenas o nome, sem mesa/escopo nesse trecho (`MAC-004`). `invoke` busca outra macro pelos mesmos escopos e devolve erro quando não encontra (`MAC-017`).

O ambiente disponibiliza funções de chat e dados. `send` delega a `chat:asyncSendStd`; `roll` a `chat:asyncRoll`; `rollLocal` chama `rrpg.interpretarRolagem` e `rolarLocalmente`; ação, narração e NPC também delegam para APIs do chat. Isso comprova wrappers e encadeamento, sem recuperar gramática, limites, aleatoriedade, autorização ou destinatários dessas operações (`MAC-014`).

O ambiente copia tabelas de funções e NDB, aceita símbolos de `_obj_getSystemGlobalSymbols` quando disponível e redireciona `load` para o mesmo ambiente. Não há base para declarar isolamento de segurança ou limites de CPU/memória a partir deste recorte (`MAC-015`).

## Visibilidade, permissões e atualização

O gerenciador apresenta macros pessoais globais, pessoais desta mesa e compartilhadas. Sem mesa, oculta as abas específicas de mesa. Na abertura do documento compartilhado, uma resposta `nil` oculta sua aba; caso contrário, associa o documento à área editável. Não aparece uma verificação `isMestre` ou `souMestre` nesse caminho (`MAC-008`). A busca nos 12 membros encontra `isMestre` apenas em ordenação de jogadores do ambiente Lua; isso não é uma guarda de edição.

Esses fatos não provam que qualquer jogador possa modificar macros compartilhadas. Leitura, escrita e criação podem ser controladas pelo documento NDB, controles genéricos, código nativo ou servidor. A flag `podeTablesDock` limita alguns caminhos de anexação, mas sua semântica de autorização não foi recuperada. A abertura durante `attachMacrosToSession` percorre as mesas sem essa guarda local, enquanto `MesaJoined` a usa (`MAC-003`, `MAC-007`).

Os observadores do documento compartilhado invalidam seu cache em adição/remoção profunda, mudança profunda e estado. Os do documento do usuário invalidam a preparação em adição/remoção/mudança profunda. `SessionLogin` anexa os documentos, `SessionLost` desanexa, e `MesaJoined`/`MesaParted` gerenciam o vínculo da mesa (`MAC-007`, `MAC-016`). Esses handlers estabelecem a intenção de atualizar a projeção local, sem comprovar entrega de eventos, reconexão, atomicidade ou resolução de conflitos.

## Importação, exportação e migração

Há um caminho concreto de migração de `simpleMacros.xml` para o documento remoto do usuário: cria backup `simpleMacros.xml.bkp` se ausente, carrega o XML via NDB, usa `NDB.exportXML(localNDB)` e `NDB.importXML(remoteNDB, savedXML)` dentro de `Locale.withNoEval`, e marca `__migratedFromLocal = true`. Sem arquivo local, ainda marca a migração como feita. Sem documento remoto disponível, o carregador retorna um NDB de memória (`MAC-005`). Não foram abertos arquivos de usuário nem acionada essa rotina.

`RESETMIGRATION` redefine a marca do documento de macros e também solicita o documento de tokens para redefinir sua marca; a presença do handler foi registrada sem acionamento (`MAC-006`). As chamadas de export/import encontradas são internas à migração. Não foi encontrado fluxo de UI para exportar ou importar macros nos 12 membros pesquisados. Isso não exclui um fluxo em outro módulo. O formato XML real, substituição versus mesclagem, efeitos da repetição e preservação de campos dependem de NDB e permanecem desconhecidos.

## Experimentos ainda necessários

| Questão | Caso sintético mínimo |
|---|---|
| Precedência e dock | Mesmo nome nos três escopos, três textos distintos; comparar comando e cada botão |
| Normalização | Espaço, ponto, barra, acento, tabulação, nome vazio e duplicata; registrar nome salvo e encontrado |
| Papéis e permissões | Mestre e jogador tentam criar/editar/excluir macro compartilhada e reentram |
| Simples | Corpo com CRLF, linhas vazias, espaços e dois comandos; registrar ordem e erros |
| Lua e parâmetros | Parâmetro com espaços/aspas; compilação inválida; `invoke` direto, recursivo e após espera |
| Persistência | Três escopos antes/depois de reentrada e perda de sessão; documentar falha de acesso ao NDB |
| Migração | Apenas cópia sintética: XML conhecido, backup ausente/presente, marca falsa/verdadeira; comparar roundtrip |

Reprodução desta coleta estática: `node docs-firecast/scripts-analise/fase2-macros.mjs`. O script exige o hash já inventariado do `resource.zip`, usa o parser ZIP existente, mantém payloads somente em memória e grava exclusivamente a evidência derivada. A verificação estática não encerra E04 nem E10.
