# Fichas, scripts e plugins

**Fase2:** contratos detalhados de [macros](fase2/01-macros.md), [NDB](fase2/03-contrato-ndb.md) e [importação UVTT](fase2/02-importador-uvtt.md). Importante divergência: o comando `/macros`, anunciado no código distribuído, respondeu comando desconhecido na [sessão testada](fase2/05-testes-na-mesa.md). Não se pode assumir que todos os pacotes inventariados estão carregados/operacionais. Nenhum plugin foi instalado, atualizado ou alterado para corrigir a diferença.

A distribuição contém cinco complementos RPK com manifests legíveis. Isso confirma módulos **presentes no pacote**; não confirma habilitação na conta, execução na sessão das capturas ou disponibilidade no servidor. Fonte: ZIP-002 em [rpk-members.json](inventarios/rpk-members.json); métodos e referências estáticas em ZIP-003, [zip-static-index.json](inventarios/zip-static-index.json). Todos os hashes, tamanhos e offsets dos 741 membros internos estão no inventário. Nenhum script do alvo foi executado.

## Módulos distribuídos

| RPK / ID declarado | Versão | SDK / API declarados | Membros | Papel declarado ou observado estaticamente |
|---|---|---|---|---|
| `AutoUpdater.rpk` / `Ambesek.Auto.Updater` | 1.3 | 3.7 / 88.1 | 79 | Manifesto: procurar atualizações de plugins/macros e obter plugins do Git do Firecast; autoria declarada Ambesek |
| `DnD5_SDK3.rpk` / `br.com.rrpg.DnD5_SDK3` | 1.2.3 | 3.7c / 88.3 | 190 | Manifesto: ficha D&D 5ª edição; autoria declarada Firecast; formulários, contadores, equipamento e magia distribuídos |
| `rrpginlua.rpk` / `RRPG.FIRECAST.FMXModule` | 1.0 | 3.7c / 88.3 | 159 | Manifesto: funcionalidades padrão, descritas como não desinstaláveis; ficha básica, macros, gestor de plugins, FireDrive e tracker de combate presentes no pacote |
| `sceneinlua.rpk` / `BR.COM.RRPG.SCENE3_IN_LUA` | 1.0 | 3.7c / 88.3 | 258 | Manifesto: núcleo do tabuleiro Scene 3; autoria declarada Firecast - AlyssonRPG |
| `UniversalVTTImporter.rpk` / `Ambesek.dd2vtt` | 1.2 | 3.7 / 88.1 | 55 | Manifesto: importação de dd2vtt; `uvtt.lua` referencia JSON, Base64 e API de cena; autoria declarada Ambesek |

As versões do SDK nos manifests são metadados de compilação e não substituem a versão do cliente. As datas declaradas nos manifests não autenticam a procedência. Ver [05-dados-e-formatos.md](05-dados-e-formatos.md) para o contêiner e codificações.

## Linguagem e dependência do host

**Confirmado estático (ZIP-003/ZIP-005):** há código Lua textual e formulários XML com eventos/scripts, além de `.lfm.lua` distribuídos. Há wrappers SDK para interface, Firecast, NDB, internet, sistema, áudio e outros módulos. Alguns usam carregamento tardio e invocações de objetos por handle; não são implementações autossuficientes do host. A presença de `.dlua` não prova bytecode: `sdk/serpent.dlua`, `sdk/slaxml.dlua` e outros selecionados são legíveis como texto.

O inventário de chamadas usa expressões regulares e pode incluir referências comentadas e nomes de módulos. Uma referência estática confirma que o texto existe, mas não a execução, efeito, autorização ou tratamento de erro. A versão do runtime Lua deve ser obtida dos imports/artefatos do cliente; o SDK de pacote não basta para fixar versão de VM.

| Superfície | Referências estáticas encontradas | Consequência para reimplementação | Lacuna |
|---|---|---|---|
| GUI e eventos | `GUI.newForm`, `GUI.showPopup`, XML com `recordList`, `dataLink` e eventos | Interpretador de layout e binding seriam necessários para formulários existentes | Ordem de eventos, foco, herança, medição e renderização não validados |
| Dados | `NDB.getChildNodes`, `newObserver`, `newTransaction`, `importXML`, `exportXML` | Dados são acessados via nós e observadores; exige adaptação se o novo backend usar SQL | Modelo físico, serialização, concorrência e sincronização não recuperados |
| Mesa/chat | `Firecast.getMesaDe`, `interpretarRolagem`, `getCurrentUser`, `Messaging`, `registerChatToolButton` | Plugins dependem de objetos do host e integração com chat/mesa | Algoritmo completo de rolagens e autoridade do servidor desconhecidos |
| Rede | `internet.newHTTPRequest`, `download`, `asyncDownload`, `stopDownload`, `httpEncode`, `httpDecode` | Existe superfície de rede exposta pelo wrapper | Restrições por plugin, TLS, cookies, limites e permissões não avaliados; nenhuma URL foi acessada |
| Sistema | `system.getClipboardText`, `setClipboardText`, `getScreenSize`, `isMobile`, `getAPIVersion` | Plugins podem depender de capacidades do cliente desktop | Não demonstra acesso irrestrito a disco nem equivalência no navegador |
| Arquivos/importação | `Dialogs.openFile`, `saveFile` em `uvtt.lua`; import de `vhd.lua` em macros/updater | Existem pontos de integração com seleção de arquivos e armazenamento virtual | Escopo efetivo do filesystem e formato persistido não verificados |
| Scene | `scene.items`, `fogOfWar`, `grid`, `viewport`, `worldWidth`, `worldHeight` em `uvtt.lua` | Importador depende de geometria, camadas e objetos próprios do host | O schema dd2vtt aceito e transformações não foram especificados nesta etapa |

## Regras concretas localizadas

ZIP-005, `rrpginlua.rpk!/turnos/CombatTrackerScripts.xml`, lido seletivamente: o método local que determina mestre consulta o jogador da mesa e `isMestre`; o trecho de ativação de turno retorna antes das mudanças se esse teste falha. Quando autorizado no fluxo textual, desmarca a vez dos demais atores e marca o ator selecionado, com scroll para o nó. Também há chamadas ao chat para narração/escrita e a `Firecast.interpretarRolagem`. **Confirmado:** regra presente no cliente distribuído. **Não verificado:** validação equivalente no servidor, caminhos alternativos e execução no tracker da captura. Reproduzir com dois atores e contas de teste mestre/jogador é o próximo experimento.

ZIP-003, `rrpginlua.rpk!/macros/RRPGSimpleMacros.lua`: referências a `asyncOpenUserNDB`, `NDB.load`, `newObserver`, XML de importação/exportação, mensageria e botões de chat. Isso oferece pontos concretos para investigar separação entre macros de usuário e mesa. Não foi recuperado aqui o schema integral, a precedência de nomes nem a gramática das macros.

ZIP-005, ficha básica: campos textuais `atributosStr`, `habilidadesStr`, `equipamentosStr`, `historiaStr`. ZIP-005, D&D contadores: inclusão/remoção e alterações de descanso integram a lista observável; o vínculo é descrito em [05](05-dados-e-formatos.md). A presença desses campos não demonstra que todas as fichas compartilham uma estrutura fixa.

ZIP-003, Scene 3: o pacote contém código de seleção/movimentação, camadas, régua, histórico de movimento, desenho, desfazer, névoa e navegação. A inspeção de conteúdo confirmou dependências de GUI, dados e cena. Os nomes dos arquivos orientam próximos testes; não são prova suficiente das regras completas de cada ferramenta.

## Três objetivos de compatibilidade diferentes

| Objetivo | Avaliação baseada nos artefatos | Trabalho necessário | Critério verificável proposto |
|---|---|---|---|
| Reproduzir comportamento das fichas | Viável como implementação própria, começando por tipos/fluxos observados; não implica executar código original | Especificar controles, bindings, cálculos, listas e permissões e escrever implementação independente | Mesmo conjunto sintético de entradas produz estado e valores esperados, com histórico documentado de eventos |
| Importar dados existentes | Parcialmente investigado: wrappers oferecem import/export XML, mas não foi obtida exportação de teste | Documentar schema real, versão, codificação, relações e migrações; validador de importação e relatório de perdas | Round-trip de exportações sintéticas com Unicode, nós, listas, campos ausentes e permissões, sem perda não declarada |
| Executar plugins/fichas existentes sem alteração | Custo alto: Lua sozinho não fornece GUI, NDB, Scene, handles e serviços Firecast | Camada de compatibilidade de APIs, runtime e layout; limites de rede/arquivos; versionamento e testes por plugin | Matriz de APIs implementadas e suíte de cenários por plugin/SDK; incompatibilidades explicitadas |

Para Laravel, comportamento próprio e importador documentado podem ser fases distintas. A execução de plugins arbitrários exigiria um projeto separado de runtime e isolamento; a documentação atual não estabelece um contrato suficiente para prometer compatibilidade. Isso é uma conclusão de planejamento, não arquitetura interna atribuída ao Firecast.

## Lacunas e experimentos

Faltam: gramática/avaliação de macros e dados; validação de manifests; instalação e atualização em conta de teste; callbacks de erros e ciclo de vida; permissões de APIs e rede; limites e isolamento; schema das exportações; execução de uma ficha mínima própria. Nenhuma hipótese sobre plugins baixados deve ser tomada como prova de código confiável ou concessão de direitos de redistribuição.
