# Testes na mesa autorizada — 16/10/2026

O usuário autorizou usar a sessão já aberta e a mesa **Creator's Game**. Isso permitiu avançar em E01 e em parte de E04. Não foi feito login, criada conta, alterada permissão, desconectada sessão ou editada configuração. A evidência é observação de UI via PowerShell/.NET/Win32, **não** runtime REA. [Registro F2-RUNTIME-001](../evidencias/fase2/runtime.json) contém procedimentos, ações, resultados, hashes e limites.

O processo selecionado foi somente `C:\Users\João\AppData\Local\Firecast\Firecast.exe`, PID 39312 nessa execução. O arquivo em disco continua com SHA-256 `4f80d08ab252ffd0b541c89cf09610028c4f8e45879cb8969bc45e8ac74c1086`. O título observado é Firecast 8.13. Isso liga versão visual ao caminho/arquivo do processo em execução, sem comparar os bytes da imagem de memória.

| Caso | Entrada / procedimento | Resultado observado | Fonte |
|---|---|---|---|
| Identidade | Abrir Configurações da mesa sem editar, aguardar carregamento | Nome da mesa autorizada e título 8.13 confirmados | UI-001; S09/S10 |
| Texto | `[REA TESTE] F2-CHAT-001 texto simples`, Enter | Mensagem aparece no histórico; compositor fica vazio | UI-002; S11 |
| Soma determinística | `/r 1d1 + 2` | `1d1 + 2 = 3`, decomposição `[1] + 2` | UI-003; S12 |
| Erro de expressão | `/r 1d1 + 2 * 3`, caractere `*` enviado por WM_CHAR | Falha de interpretação; UI mostra operador como ponto/bullet e acusa caractere desconhecido | UI-004; S13 |
| Sete tipos de dados | `/r 1d4 + 1d6 + 1d8 + 1d10 + 1d12 + 1d20 + 1d100` | Componentes `[2]+[1]+[5]+[7]+[7]+[15]+[100]`, soma 137; animação 3D observada | UI-005; S14/S15 |
| Comando de macros | `/macros` | `Comando desconhecido: "macros".`; gerenciador não abriu | UI-006; S16 |

Os casos de dados não validam distribuição aleatória, autoridade do servidor, sintaxe inteira, limites, modificadores ou recebimento por outra conta. O caso com `*` também não prova ausência geral de multiplicação: a transformação da entrada pelo compositor deve ser isolada em teste posterior com teclado/colagem. Não houve captura do protocolo para localizar onde ocorre cada validação.

Há uma divergência estática/runtime concreta: o pacote anuncia /macros, mas a sessão respondeu comando desconhecido. Posteriormente, o usuário informou em 16/10/2026 que macros exigem Gold/gasto e há restrições também para criar mapas e pastas. Os critérios comerciais e sua relação causal com a resposta de /macros não foram verificados independentemente. Fonte: [USR-001](../evidencias/relato-gold.json). Isso muda a prioridade: não tratar a indisponibilidade como provável defeito de carregamento. Os testes pagos ficam pendentes de acesso; não houve compra, atualização de plugin ou alteração de licença.

## Capturas e método

Foram guardados oito recortes PNG [S09–S16](../capturas/manifesto-fase2.json), sem nomes de conta, avatares, abas privadas ou histórico anterior. O nome da mesa foi informado e autorizado pelo usuário. `PrintWindow` captura somente a janela selecionada; o recorte é cópia determinística de pixels, sem interpolação, redimensionamento ou reconstrução generativa. Dimensão física observada da janela: 1938×1038 pixels. Os recortes não são baselines da janela inteira.

O sandbox encontrou o processo, mas não expôs janelas visíveis. A sonda fora do isolamento do terminal expôs o HWND; a busca UIAutomation pelo nome da mesa não retornou elementos. O executor aprovou a observação/ação delimitada. Não houve rejeição de aprovação automática. Nenhum ajuste de configuração permanente foi necessário.

A primeira tentativa usou coordenadas virtualizadas e abriu o menu +. A diferença 1550×830 versus 1938×1038 indicou o problema de DPI; a execução seguinte fixou `SetThreadDpiAwarenessContext(-4)` e conferiu a geometria antes de cada clique. A aba adicional de configurações criada nessa navegação foi fechada; ao fim a mesa ficou aberta com compositor vazio. Uma tentativa de captura parcial via viewport não produziu o recorte esperado; seus dois arquivos foram descartados, e os PNG finais foram inspecionados visualmente após a cópia exata da região. As capturas integrais foram transitórias e removidas.

Scripts: [sonda](../scripts-analise/fase2-janela.ps1), [captura delimitada](../scripts-analise/fase2-captura-janela.ps1), [ações explícitas](../scripts-analise/fase2-acao-janela.ps1) e [consolidação](../scripts-analise/fase2-runtime-registro.mjs). Reproduzir a consolidação com `node docs-firecast/scripts-analise/fase2-runtime-registro.mjs` apenas reconfere os recortes e o arquivo atual; **não repete os testes**. Reexecutar ações exige observar a janela/mesa, confirmar geometria atual e escolher novamente coordenadas, sem reutilizar cegamente PID/HWND ou posições.

Efeitos persistentes: uma mensagem sintética e duas rolagens válidas ficaram no histórico da mesa. As duas respostas de erro podem ter registro local; não foram apagadas. A instalação original, plugins, macros e configurações não foram escritos. O teste não deve ser descrito como inteiramente somente leitura.
