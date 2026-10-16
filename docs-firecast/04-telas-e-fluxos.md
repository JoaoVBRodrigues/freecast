# Telas e fluxos observáveis do RRPG Firecast

## Escopo e evidência

O catálogo inicial abaixo usa as oito imagens anexadas à conversa, identificadas como S01–S08, sem ações executadas para produzi-lo. Na fase2, ações reais na mesa autorizada geraram [S09–S16](capturas/README.md), com dimensões/hash no [manifesto](capturas/manifesto-fase2.json) e [relatório próprio](fase2/05-testes-na-mesa.md). Os identificadores são referências locais, não IDs de Evidence REA. Os recortes novos excluem contas, avatares e histórico; somente o nome da mesa fornecido/autorizado pelo usuário foi preservado.

As imagens não foram disponibilizadas como arquivos locais nesta tarefa. Portanto, não há hashes, metadados de arquivo, coordenadas aferidas por ferramenta ou cópias em `capturas/`. As dimensões da tabela correspondem às dimensões apresentadas no contexto da tarefa; a proporção é compatível com a visualização, mas a resolução original não foi validada por leitura de arquivo. Posições, cores e tamanhos de controles descritos adiante são aproximações visuais, salvo os textos explicitamente legíveis.

| ID | Dimensão apresentada | Conteúdo | Versão legível |
|---|---|---|---|
| S01 | 867 × 465 px | Mesa com painéis, abas, conversa e participantes | Firecast 8.13 |
| S02 | 879 × 465 px | Mesa com menu superior direito aberto | Firecast 8.13 |
| S03 | 864 × 465 px | Início: recomendações, ações e recentes | Firecast 8.13 |
| S04 | 868 × 465 px | Continuação do início: favoritas e minhas | Firecast 8.13 |
| S05 | 866 × 465 px | Navegação de mesas online e cartões | Firecast 8.13 |
| S06 | 342 × 137 px | Recorte do compositor com comando de dados | Ausente no recorte |
| S07 | 418 × 465 px | Janela de gerenciamento de macros | Ausente no recorte |
| S08 | 878 × 465 px | Explorador de Arquivos na pasta do aplicativo | Ausente; não é uma tela do aplicativo |

A versão 8.13 é observada nas barras de título S01–S05. Isso não identifica a versão do executável selecionado em S08 nem prova que as oito imagens foram obtidas da mesma instalação ou sessão.

## S01 — Mesa e organização dos painéis

A janela usa barra de título vermelha, navegação vertical estreita à esquerda e uma faixa superior de abas com fechamento por “×” e botão “+”. Algumas abas têm o sufixo “(privado)”. Há uma linha vermelha sob a área de abas.

O corpo é dividido visualmente em três colunas. À esquerda, um painel de biblioteca contém busca “Procurar na biblioteca”, um botão com “+” e uma árvore com pasta. Abaixo ficam o painel de macros e um painel compacto de combate. O centro apresenta uma sequência de mensagens com avatares e imagens, barra de rolagem e compositor no rodapé. À direita há uma lista de participantes separada por “Mestre” e “Jogadores”, com avatares, barras horizontais coloridas e linhas de atributos. O significado exato, máximos e vínculo das barras a atributos não são determinados pela imagem.

Controles legíveis: “Gerenciar Macros”, “Limpar”, “Teste”, “Init”, “A/H”, “Próx. turno”, “Rodada” e “Digite uma mensagem...”. O compositor mostra ferramentas de formatação, anexação e outros ícones, além de uma fileira de símbolos de dados. Há divisórias com pequenos marcadores circulares; isso sugere pontos de ajuste de layout, mas arraste e redimensionamento não foram demonstrados.

Estado observado: mesa aberta com múltiplas abas e painéis simultâneos. Não foram observados envio de mensagem, execução de macro, rolagem, troca de abas ou avanço de turno.

## S02 — Menu da mesa

Um menu está aberto no canto superior direito, sobre a mesa. O restante da imagem aparece escurecido. A captura demonstra o estado aberto; o gesto que o abriu e o efeito de cada opção permanecem desconhecidos.

Opções legíveis, na ordem:

1. “Visão geral da mesa”
2. “Exibir Biblioteca da mesa”
3. “Exibir Music player”
4. “Exibir SFX player”
5. “Exibir Macros”
6. “Exibir Plugin Auto Updater”
7. “Exibir Tracker de Combate”
8. “Nova Janela (Ctrl + N)”
9. “Editar layout da tela (Ctrl + E)”
10. “Resetar todo o layout da tela”

As legendas estabelecem a existência visual de comandos para exibição de painéis, janelas e layout. Não demonstram persistência, confirmação de reset, atalhos funcionando, permissões necessárias ou suporte a múltiplos monitores.

## S03 — Início, recomendações e recentes

A página “Início” mantém a navegação lateral e mostra “Recomendações” no topo. Um cartão amplo à esquerda combina imagem, metadados, estado do mestre, descrição e controles de avaliação. À direita há uma grade de quatro cartões de ação: “Explorar mesas”, “Criar mesa”, “Conversar com amigos” e “Personalizar conta”. Os cartões usam, aproximadamente, azul vivo, vermelho rosado, azul petróleo e violeta. A seção “Recentes” começa abaixo com três cartões visíveis em uma linha.

Os cartões de mesa têm imagem ou ilustração substituta, título, sistema, quantidade de usuários online, menu de reticências e informações de estado. A imagem estabelece a composição da página, sem demonstrar a origem das recomendações ou o algoritmo de ordenação.

## S04 — Favoritas e minhas

O conteúdo está em uma posição inferior da página: o final de cartões anteriores aparece cortado no topo, seguido por “Favoritas” e “Minhas”. Favoritas mostra dois cartões na linha visível; Minhas mostra três. Há estados legíveis como “Mestre offline”, “Requer senha” e “Minha mesa”, acompanhados por ícones. Controles de avaliação aparecem no canto inferior direito dos cartões.

S03 e S04 são compatíveis com posições diferentes de rolagem da mesma página. A rolagem não foi capturada como ação e a continuidade não foi verificada em execução. Os rótulos não estabelecem quais operações um proprietário pode realizar.

## S05 — Mesas online

A navegação de mesas inclui um segundo painel à esquerda com “Online”, “Recomendadas”, “Descobrir”, “Recentes”, “Favoritas”, “Minhas”, “Procurar” e “Nova mesa”. “Online” aparece realçado com contorno vermelho. À direita, “Mesas online” acompanha um ícone semelhante a filtro/ordenação.

Os cartões são apresentados em duas colunas. Alguns exibem o selo verde “Há vagas”; há cartões com imagem de fundo e outros com símbolo substituto. São legíveis estados de mestre online, contagens de usuários, bloqueio por senha, menus de reticências e controles de avaliação. A barra lateral de rolagem indica conteúdo além da área mostrada.

A imagem não permite determinar paginação, filtros disponíveis, atualização em tempo real, regras do selo de vagas ou o fluxo de entrada em uma mesa.

## S06 — Compositor e comando de dados

O recorte mostra um campo multilinha com uma expressão iniciada por `/r`, usando parcelas `1d20`, `1d100`, `1d12`, `1d10`, `1d8`, `1d6` e `1d4` unidas por “+”. Existe um cursor ao final da expressão. Abaixo aparecem “B”, “I”, “U” e ícones semelhantes a cor/preenchimento, clipe, código e microfone. Uma segunda fileira contém símbolos de diferentes dados.

Isso comprova que a expressão está inserida no compositor. Não comprova aceitação da sintaxe, avaliação da expressão, aleatoriedade, resultado, associação dos ícones aos tipos de dado ou ação do microfone.

## S07 — Gerenciar macros

A janela tem título “Gerenciar Macros”, botão de fechamento “×” em uma área vermelha no topo direito e abas “Macros para todos da mesa” e “Minhas macros”. A área de conteúdo inclui o contexto da mesa, o botão “Nova macro para todos” e quatro linhas visíveis de macros, cada uma com “Editar...”. Os nomes das macros foram omitidos.

A aba e o botão permitem inferir uma distinção pretendida entre macros compartilhadas e pessoais. A imagem não demonstra autorização, sincronização entre usuários, estrutura do editor, linguagem das macros, salvamento ou exclusão. O fluxo criação → edição → execução ainda requer evidência.

## S08 — Pasta de instalação

Esta imagem mostra o Explorador de Arquivos do Windows em uma pasta chamada Firecast sob a área local de dados do usuário. Há um executável selecionado, bibliotecas DLL, executáveis auxiliares e uma pasta/arquivo de recursos. O endereço completo e dados pessoais foram omitidos.

O inventário visual sugere uma distribuição com dependências nativas e componentes auxiliares; sozinho, não identifica a tecnologia principal da interface, arquitetura, bibliotecas efetivamente carregadas ou implementação de qualquer função. A versão 8.13 não aparece nesta imagem. Tamanhos e datas exibidos não foram usados como identidade de artefato: a análise estática deve partir dos arquivos reais e seus hashes.

## Referência visual para reconstrução

Observa-se uma base cinza muito escura, superfícies ligeiramente mais claras, textos em branco/cinza, acentos vermelhos e cartões de ações com cores saturadas. Há tipografia sem serifa, com rótulos compactos e títulos em negrito. Família tipográfica, peso numérico, cores hexadecimais, raio dos cantos, espaçamento, escala de DPI e dimensões dos controles não foram medidos. Esses valores devem ser tratados como escolhas de implementação até medição sobre arquivos originais.

Critérios de paridade sugeridos, a verificar em uma reconstrução:

- Reproduzir a hierarquia de navegação: barra lateral, abas de mesa, início e catálogo de mesas.
- Organizar a mesa com biblioteca/macros/combate à esquerda, conversa ao centro e participantes à direita, permitindo avaliar o layout nas dimensões de referência.
- Apresentar os estados de mestre, senha, propriedade e vagas com texto e ícone, sem depender apenas de cor.
- Reproduzir os rótulos observados do menu, o compositor multilinha, as duas abas de macros e os controles de edição.
- Comparar visualmente capturas próprias em dimensões conhecidas; registrar separadamente diferenças de DPI, tipografia e conteúdo substituto.
- Validar os fluxos com uma mesa e dados de teste: navegação, entrada, envio, rolagem, macro, edição de layout e persistência. As imagens atuais não constituem testes desses comportamentos.

## Matriz de fluxos e lacunas

| Fluxo candidato | Evidência disponível | Situação |
|---|---|---|
| Início → catálogo de mesas | Ação em S03; catálogo em S05 | Estados observados; transição não executada |
| Início → favoritas/minhas | Seções em S04; itens laterais em S05 | Estados observados; regras de inclusão desconhecidas |
| Mesa → menu | Estado de mesa em S01; menu em S02 | Abertura/fechamento não executados |
| Menu → exibir/ocultar painéis | Comandos em S02; painéis em S01 | Efeito e persistência desconhecidos |
| Mesa → gerenciar macros | Botão em S01; janela em S07 | Transição inferida; edição não observada |
| Compositor → rolagem de dados | Expressão em S06 | Somente entrada de texto observada |
| Tracker → próximo turno | Controle em S01 | Regras e mudança de estado desconhecidas |
| Biblioteca → abrir/reusar conteúdo | Árvore em S01 | Estrutura e ações desconhecidas |
| Pasta instalada → código executado | Inventário visual em S08 | Requer identificação e inspeção dos artefatos reais |

O catálogo responde à organização visual, aos controles legíveis e aos estados capturados. Comportamento, protocolos, persistência, extensibilidade, permissões e correspondência com o binário permanecem fora da evidência dessas imagens.
