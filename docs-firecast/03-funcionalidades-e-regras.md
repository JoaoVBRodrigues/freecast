# Funcionalidades e regras: cobertura

**Fase2:** [macros](fase2/01-macros.md), [UVTT](fase2/02-importador-uvtt.md), [NDB](fase2/03-contrato-ndb.md) e [testes](fase2/05-testes-na-mesa.md) ampliam este roteiro inicial. Texto e duas rolagens foram observados em uma conta; `/macros` não foi reconhecido nessa sessão, apesar do handler distribuído. Permissões de terceiros, persistência, gramática inteira e servidor continuam sem validação. As tabelas abaixo preservam a cobertura inicial e devem ser lidas junto desses resultados.

Esta etapa identifica superfícies de funcionalidades, não valida seu funcionamento ponta a ponta. “Encontrado” significa que existe evidência visual ou estrutural no escopo indicado. Regras de servidor, permissões efetivas e persistência exigem experimentos próprios. A coluna de lacunas não deve ser convertida em requisitos confirmados.

| Módulo solicitado | Estado no escopo | Fonte | O que falta |
|---|---|---|---|
| Entrada, conta, perfil e preferências | Encontrado estaticamente | `LOCAL-FORMS-001`: TFRMDISCONNECTEDSTATEFORM e TFRMCONFIGURATION*; S03 | Login real, validações, recuperação, sessão e gravação |
| Listar, criar e entrar em mesas | Encontrado visualmente; recursos de busca encontrados | S03–S05; TFRMEXPLOREROOMS* | Criação e entrada, filtros e paginação executados |
| Mestre/jogador, convites e permissões | Papéis visíveis; recurso de permissões encontrado; convites ainda não investigados | S01; TFRMROOMPERMISSIONSSTATEFORM | Matriz de ações por papel, convites, expulsão e transferência |
| Chat, comandos, reservadas, rolagens | Superfícies encontradas | S01, S06; TFRMROOMPVTCHATUIMODULE e TFRMROOMGROUPPVTCHATUIMODULE | Gramática, envio, entrega, histórico e destinatários |
| Personagens, fichas, campos calculados e persistência | Recursos de ficha e pacote encontrados; barras visíveis | S01; TFRMCHARACTERSHEETMODULE, TFRMROOMUISHEET; pacotes em [06](06-fichas-scripts-e-plugins.md) | Dados reais de teste, cálculo, vínculo das barras e salvamento |
| Plugins, scripts, extensões, APIs | Encontrado estaticamente | RPK/manifests/scripts; [06](06-fichas-scripts-e-plugins.md) | Ciclo de execução, capacidades e isolamento |
| Biblioteca de imagens/sons/documentos | Biblioteca visual e assets distribuídos encontrados | S01; TFRMROOMLIBRARYMODULE; inventário ZIP | Upload, tipos aceitos, cotas, leitura e exclusão |
| Mapas, tokens, movimentos, camadas e ferramentas | Recurso de cena e scripts encontrados; operação ainda não investigada | TFRMSCENEMODULE; pacote sceneinlua | Canvas, escalas, camadas, permissões, movimento concorrente |
| Áudio, música, comunicação | Itens de menu e recursos encontrados | S02; TFRMMUSICPLAYERMODULE, TFRMSFXPLAYERMODULE, TFRMCONFERENCECONFIGPAGE | Reprodução, transporte, voz, latência e permissões |
| Histórico, importação/exportação, recuperação | Importador distribuído encontrado; demais fluxos ainda não investigados | Pacote UniversalVTTImporter; [06](06-fichas-scripts-e-plugins.md) | Formato de dados de usuário, roundtrip, backup e restauração |
| Reconexão, sincronização, erros | Ainda não investigado em execução | Recursos de estado desconectado não comprovam o protocolo | [Experimentos E08–E09](12-lacunas-e-proximos-experimentos.md) |

Nenhum dos módulos inteiros foi classificado como ausente. Há buscas negativas limitadas: o inventário não selecionou um banco de dados pessoal nem capturas de rede; isso não significa que o aplicativo não os tenha.

| Funcionalidade encontrada | Entradas e ações observáveis/serializadas | Estado e resposta conhecidos | Regras, permissões, persistência e externos |
|---|---|---|---|
| Login/cadastro | Campos de conta/senha; senha marcada `Password=true`; handlers de login, recuperar senha, cadastro, duas etapas e termos | Stream serializado; formulário raiz 1040×733 em design, não medida de janela em execução | Validações, backend e mensagens de erro desconhecidos; nenhum valor pessoal lido |
| Perfil | Bindings `profile.avatar`, `profile.nick`, `profile.status`, `profile.grammaticalGender`, capa, biografia e preferências textuais | Avatar editável e ricos editores serializados; status enum online/away/offline | `DefaultValue=3` não coincide diretamente com lista textual: não deduzir regra; armazenamento e acesso desconhecidos |
| Preferências | `preferences.show3DRolls`, tamanho/espaçamento do chat, idioma, inicialização com SO e modos de atualização | Defaults e handlers no stream; modos none/stableline/devline | Unidade, coerção de tipo, gravação e efeito runtime não medidos |
| Notificações | Bindings de sons de dados, menção, expulsão, mensagem e abertura de PVT; solicitar atenção | Muitos defaults serializados true | Não comprova som efetivamente reproduzido nem evento de rede |
| Catálogo | Navegação online/recomendadas/descobrir/recentes/favoritas/minhas/procurar/nova mesa; cartões e indicadores | Cartões com contagens e mestre online/offline em S03–S05 | Ordenação e elegibilidade desconhecidas; contagens não devem ser deduzidas do cliente |
| Busca | Campo de palavras, checkboxes sem senha/com jogadores/mestre online, características desejadas/indesejadas | Controles e handlers no TFRMEXPLOREROOMSSEARCHSTATE | Operadores, combinação de filtros, limites e autorização não executados |
| Mesa e layout | Abas; menu para exibir painéis, nova janela, editar/resetar layout | S01–S02; três colunas e divisores | Arraste, salvamento, escopo por conta/sala e atalhos precisam de teste |
| Chat e dados | Texto rico, imagens no histórico; compositor com `/r` e soma de dados em S06 | Expressão digitada, sem resultado nessa captura | Tipos de dados, parser, precedência, erros, aleatoriedade e autoridade desconhecidos |
| Conversas reservadas | Abas marcadas privado e formulários individual/grupo | Existência de superfície; destinatários não transcritos | Não comprova confidencialidade, retenção ou entrega apenas a membros |
| Macros | Abas para todos da mesa/minhas; criar macro para todos; editar linhas | Diálogo de S07; lista no painel S01 | Parâmetros, expansão, permissões de criação/edição, armazenamento e comandos desconhecidos |
| Combate | Lista compacta, iniciativa, próximo turno e rodada | S01 mostra estado visual; `CombatTrackerScripts.xml` contém guard de mestre para ativação de turno | O script desmarca outros atores e marca o selecionado após consultar `isMestre`; confirmação estática, sem validar servidor, caminhos alternativos, empates ou sincronização. Ver [06](06-fichas-scripts-e-plugins.md) |
| Biblioteca | Busca, árvore de pastas, botão + | S01 | Tipos de itens, ACLs, localização dos bytes, upload e remoção desconhecidos |
| Fichas | Módulos declarados, campos/barras no painel de participantes, templates distribuídos | Forma e scripts presentes | Quem escreve e lê cada campo, fórmulas, persistência e protocolo não comprovados |
| SFX/música | Itens de menu e recurso de lista de faixas com botão de adicionar/volume | TFRMSFXPLAYERMODULE tem dimensões de design 575×446 e handler btnAddClick | Formato de fonte, início compartilhado, streaming e sincronização não medidos |

Fontes reproduzíveis: executar [formularios.mjs](scripts-analise/formularios.mjs), localizar o recurso pelo nome em [formularios.json](inventarios/formularios.json), e consultar propriedade/handler no objeto. Nomes de handlers não são corpos de funções. Os streams incluem valores padrão em tempo de design, não dados persistidos de usuários.

Os exemplos mínimos abaixo são sintéticos, baseados apenas nos nomes dos bindings, e não um formato de exportação confirmado:

```json
{"profile":{"nick":"Pessoa de teste","status":"online"},"preferences":{"show3DRolls":true}}
```

Para obter regras implementáveis, executar os experimentos de [12](12-lacunas-e-proximos-experimentos.md), atualizando o [registro de achados](evidencias/achados.json) com versão, ação, resultado e limites. A proposta em [09](09-arquitetura-proposta.md) pode escolher regras próprias provisórias, mas elas não satisfazem paridade até comparação.
