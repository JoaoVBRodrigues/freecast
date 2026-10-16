# Paridade visual e funcional — Partes 1 e 2

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
