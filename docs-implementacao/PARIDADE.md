# Paridade visual e funcional — Parte 1

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
