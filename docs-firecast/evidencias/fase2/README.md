# Evidências da fase 2

Suplementos locais, não novos IDs de Evidence REA. Original ZIP e PE continuam vinculados aos hashes da primeira etapa.

| Arquivo | Conteúdo / produtor |
|---|---|
| [macros.json](macros.json) | 12 membros, 17 recortes; `fase2-macros.mjs` |
| [uvtt.json](uvtt.json) | Contrato do importador com fontes/linhas; `fase2-uvtt.mjs` |
| [ndb.json](ndb.json) | Contrato da fachada, consumidores e busca delimitada; `fase2-ndb.mjs` |
| [fontes-temas.json](fontes-temas.json) | Metadados SFNT, constantes e classes; `fase2-fontes-temas.mjs` |
| [janela-sandbox.json](janela-sandbox.json) / [janela-host.json](janela-host.json) | Sondas somente leitura do processo/janela, sem nomes de contas ou conversas |
| [runtime.json](runtime.json) | Observações UI-001–007, ações e hashes de S09–S16; `fase2-runtime-registro.mjs` consolida, não reexecuta |
| [paridade-antes.csv](paridade-antes.csv) | Matriz anterior à integração desta fase |

Reprodução dos scripts estáticos: `node docs-firecast/scripts-analise/fase2-macros.mjs`, `fase2-uvtt.mjs`, `fase2-ndb.mjs` e `fase2-fontes-temas.mjs` pelo mesmo caminho. Somente leitura do alvo. A consolidação é `node docs-firecast/scripts-analise/fase2-consolidar.mjs`; executar depois do produtor básico `consolidar.mjs` para manter a matriz atualizada.

Para UI, seguir o [relatório](../../fase2/05-testes-na-mesa.md). Não reusar cegamente coordenadas/handles. Recortes preservados estão em [manifesto-fase2.json](../../capturas/manifesto-fase2.json). Capturas integrais transitórias foram removidas.
