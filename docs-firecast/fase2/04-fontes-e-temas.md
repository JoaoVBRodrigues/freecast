# Fontes e constantes do tema flat

Foi confirmada a correspondência entre as famílias declaradas pelo tema e as tabelas `name` dos 24 TTF empacotados: Roboto, Lato e Inconsolata. O tema também declara Times New Roman para capitulares, mas nenhum dos 24 TTF tem essa família. Essa observação não determina o fallback instalado nem a fonte efetivamente renderizada nas capturas.

Fonte da investigação: [F2-FONTES-TEMAS-001](../evidencias/fase2/fontes-temas.json), suplemento independente ao inventário REA anterior, com SHA-256 dos arquivos, tabelas SFNT, offsets exatos dos registros `name`, plataforma/idioma/encoding e linhas XML. Procedimento reproduzível: `node docs-firecast/scripts-analise/fase2-fontes-temas.mjs`, usando [o script](../scripts-analise/fase2-fontes-temas.mjs). O ZIP é descomprimido apenas em memória; nenhuma fonte foi instalada, carregada, rasterizada ou exportada. URLs de licença não foram acessadas.

O arquivo `resource.zip` é o inventariado anteriormente, SHA-256 `892afd517cfec3a33d060feb6a1137c76d9519fb68f5dfcb1b90c1a3a8f34f2b`. A leitura cobre todos os membros `.ttf` desse ZIP e seleciona registros `name` 0, 1, 2, 4, 6, 13, 14, 16 e 17. O parser verifica limites da tabela/dos registros e CRC dos membros. Strings Unicode de plataformas 0/3 são decodificadas como UTF-16BE; plataforma Macintosh usa aproximação Latin-1 explicitamente identificada. As conclusões de família abaixo usam registros Unicode da plataforma 3.

| Grupo | Arquivos / variações encontradas | Declaração de licença nos registros |
|---|---|---|
| Inconsolata | 2: Regular, Bold | Regular: `name13/14` declaram SIL OFL 1.1; Bold: `name13/14` declaram Apache 2.0 |
| Lato | 10: Regular, Italic, Bold, BoldItalic, Light, LightItalic, Medium, MediumItalic, Semibold, SemiboldItalic | `name13/14` declaram SIL OFL 1.1; texto menciona Reserved Font Name Lato |
| Roboto | 12: Regular, Italic, Bold, BoldItalic, Black, BlackItalic, Light, LightItalic, Medium, MediumItalic, Thin, ThinItalic | `name14` aponta para Apache 2.0; não foi encontrado `name13` entre os registros desses arquivos |

Essas são declarações por arquivo, não uma autorização de redistribuição estabelecida nesta investigação. O caso Inconsolata mostra por que não convém atribuir uma licença única ao grupo pelo nome. Os textos de `name13/14` e os marcadores de copyright/licença selecionados estão preservados no JSON com localização e hash; os binários de fonte permanecem no ZIP original.

A família SFNT básica (`name1`) pode incluir o peso no próprio nome: Lato Light, Lato Medium, Lato Semibold; Roboto Black, Roboto Light, Roboto Medium, Roboto Thin. Nesses arquivos, `name2` pode ser Regular ou Italic. O JSON também retém família/subfamília tipográficas (`name16/17`) quando presentes. Não se deve reconstruir todas as variantes apenas concatenando `name1` e uma suposição derivada do nome do arquivo.

| Arquivo XML em `themes/flat/` | SHA-256 |
|---|---|
| consts.xml | `d8a97455929eff1a948399211e40c9270e99737fb2827be451849f46b1f802c0` |
| theme_baseDefinitions.xml | `47a612bdead926d240d66b0d46ae23802229626717e3cb4298ae2066ee77e941` |
| theme_buttons.xml | `3e59732aa59cacd8c586784234e98b27001effdcd3849a479c0a0fb79c9b0367` |
| theme_edits.xml | `3dce3ef3a8a075e32c230e1fbaab5717b61ba223f7692e1a26acecc8249b4ca7` |
| theme_labels.xml | `04587e6e329a337e194143c645f901b60da98bd0589fbd6d56fd7c979cb5d372` |

As constantes de `consts.xml:5–8` são `default_font_name=Roboto`, `title_font_name=Lato`, `console_font_name=Inconsolata`, `initial_cap_font_name=Times New Roman`. Em `theme_baseDefinitions.xml:3–8`, `text` usa família padrão, tamanho 14 e estilo vazio; `textBig` herda `text`, tamanho 17 e bold; `textPrompt` declara família padrão e tamanho 14. Os tamanhos são números declarados pelo tema; conversão para pixels/DPI permanece sem medição.

Em `theme_baseDefinitions.xml:40–52`, `_baseTaleMarkStyleSheet` declara família padrão, tamanho 15, cor de corpo `text_content_foreground_color`, cabeçalho com família de título e capitular de tamanho 40 na família de capitular. `_consoleTalemarkStyleSheet` herda essa base e declara família de console, tamanho 16, `regularTextContrast=5`, `largeTextContrast=3.5`, `optmizeContrast=true`. São parâmetros da configuração, não medições de contraste na tela.

Classes diretamente relevantes confirmam os vínculos: `theme_edits.xml:26` liga `taleMarkRichEditStyle` à base TaleMark; `40–53` configura log e carimbo com família padrão e tamanho 11; `55–56` liga log/input de console à folha de console. `theme_buttons.xml:215` usa a família de título e tamanho 16 em `_BaseButtonNavStyle`. `theme_labels.xml:53` declara título de formulário Lato, bold, tamanho 24; `39` e `95` também referenciam a família de título. Essas relações são declarações estáticas; ordem final de aplicação, herança múltipla e substituições do runtime não foram executadas.

Foram inventariadas 141 constantes. A substituição recursiva independente de `$(nome)` recupera, entre outras:

| Papel declarado | Valor de seis dígitos | Âncora em consts.xml |
|---|---|---|
| cor de face | `#F2F2F2` | 11, 76 |
| cor dominante | `#EB3449` | 20, 87 |
| fundo | `#212121` | 34, 100 |
| fundo secundário | `#272727` | 38, 102 |
| fundo terciário | `#333333` | 40, 104 |
| conteúdo | `#363B45` | 36, 105 |
| texto de chat | `#B9BABC` | 46, 113 |
| ícones da toolbar do chat | `#7F8185` | 59, 138 |
| quatro cartões alternativos | `#0060F0`, `#C2003A`, `#0076A8`, `#8000FF` | 176–179 |

O [catálogo de amostras de cor](04-swatches.html) usa 66 literais de seis dígitos em CSS e uma fonte genérica do sistema. É uma visualização independente de constantes, sem assets/fontes originais, não uma captura ou reprodução integral da interface. Valores simbólicos como `white` e `red` são preservados no JSON sem conversão implícita.

Os valores de oito dígitos permanecem opacos quanto à ordem do canal alpha: `#00000000` (`consts.xml:3`), substituições que resultam em `#F3564470` (`27,79`), `#757D8190` (`114`) e `#21212190` (`133`). Em `theme_edits.xml:40–53`, `hotNewMessages_color` concatena `A8` à cor dominante pálida e `hoveredMessageBkgColor` declara `#0000001A`. Não foram tratados como RGBA/ARGB nem apresentados no catálogo CSS. O JSON mantém valores brutos, substituídos e referência de classe, sem interpretar alpha.

E12 avançou em identidade tipográfica e tokens estáticos. Permanecem pendentes licença completa por artefato, procedência/autorizações aplicáveis, confirmação do tema ativo, fallback de Times New Roman, seleção efetiva de variantes, geometria e cores medidas com captura local, DPI e contraste renderizado. Nenhum arquivo original foi alterado.
