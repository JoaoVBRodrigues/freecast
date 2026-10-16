# Assets e reutilização

**Fase2:** [24 TTF e constantes/classes do tema](fase2/04-fontes-e-temas.md) receberam inspeção de metadados. Há declarações de licença específicas por arquivo, ainda sem auditoria de redistribuição. [66 amostras de cor](fase2/04-swatches.html) foram geradas em CSS sem usar assets/fontes originais. [Recortes de UI autorizados](capturas/README.md) são evidência de teste, separados dos assets de produto. As contagens de 628/515 abaixo são do inventário ZIP inicial.

**Nenhum asset do alvo foi extraído para arquivo, copiado para uma aplicação ou publicado.** Os payloads ZIP foram lidos e, quando necessário, descomprimidos em memória para identificação, dimensão e SHA-256. A inspeção estática inicial não executou o cliente nem abriu dados pessoais; os testes posteriores de UI são documentados separadamente. O catálogo de assets extraídos continua com zero imagens porque não foi confirmada licença de reutilização visual nesse conjunto.

## Inventários rastreáveis

| Evidência | Conteúdo | Cobertura |
|---|---|---|
| ZIP-001, [resource-zip.json](inventarios/resource-zip.json) | Todos os nomes, tamanhos, offsets, CRCs e hashes externos | 1.422 membros de `resource.zip` |
| ZIP-002, [rpk-members.json](inventarios/rpk-members.json) | Mesmo inventário para os cinco RPK; manifestos resumidos | 741 membros internos; duplicatas preservadas por identidade de contêiner |
| ZIP-004, [assets-resource.json](inventarios/assets-resource.json) | Manifesto de assets com ID, contêiner, nome, formato, tamanho, dimensões, duração quando conhecida, hash, método, associação e situação de reutilização | 628 registros: 379 externos e 249 em RPK; 515 imagens ao todo, sem deduplicar |
| PE, [recursos-pe.json](inventarios/recursos-pe.json) | Recursos incorporados ao executável, escopo separado do ZIP | Evidence REA `ev_cee6f056eecc2b6073260af618e4faf007a8b34c20e46ce28408cd235235d995`; não somar à contagem ZIP como arquivos independentes |
| LOCAL-ASSETS-PE-001, [assets-instalacao.json](inventarios/assets-instalacao.json) | ICO distribuído como arquivo independente, com hash e dimensões declaradas das variantes | `FirecastPlugin.ico`; licença e associação à interface não verificadas |

ZIP-004 é produzido por `node docs-firecast/scripts-analise/zip-assets.mjs docs-firecast/inventarios/resource-zip.json docs-firecast/inventarios/rpk-members.json docs-firecast/inventarios/assets-resource.json`. Os inventários de origem são produzidos conforme [05-dados-e-formatos.md](05-dados-e-formatos.md), com Node.js v22.23.2.

`node docs-firecast/scripts-analise/assets-pe.mjs` complementa o manifesto PE com origem, associação, dimensão e método; campos desconhecidos ficam nulos. As dimensões de ícones vêm dos grupos PE, com zero representando 256 pixels, ou dos diretórios ICO, e não de renderização. PNG é reconhecido pelo IHDR; JPEG pela assinatura, sem medir dimensões dos payloads PE nesta etapa. Este script deve rodar depois de `formularios.mjs`, que gera a versão básica do manifesto PE. Nenhum payload é extraído.

| Contêiner | Imagens | Outros assets inventariados |
|---|---|---|
| `resource.zip` | 256 PNG e 10 JPEG | 46 MP3, 4 WAV, 24 TTF, 16 OBJ, 16 MTL, 7 estilos |
| `AutoUpdater.rpk` | 4 | Nenhum nas extensões de assets selecionadas |
| `DnD5_SDK3.rpk` | 87 | Idem |
| `rrpginlua.rpk` | 29 | Idem |
| `sceneinlua.rpk` | 128 | Idem |
| `UniversalVTTImporter.rpk` | 1 | Idem |

Para PNG/JPEG, tipo e dimensões foram obtidos dos cabeçalhos, sem renderizar a imagem. As dimensões não medem seu tamanho efetivo na interface nem sua área visível. Durações de áudio, metadados internos de fontes e geometria dos modelos **não foram medidos**; os campos correspondentes permanecem nulos. OBJ/MTL, MP3/WAV e TTF foram classificados por extensão nesta etapa. Nenhum desses dados deve ser convertido em afirmação de validação integral do formato.

Os diretórios `dados/modelos/hq/` e `lq/` contêm nomes relativos a d4, d6, d8, d10, d10cent, d12, d20 e dFudge; sons aparecem sob `dados/sons/`. A associação com apresentação dos dados é **inferida pelos caminhos**, não confirmada por execução. De forma semelhante, `frames`, `themes` e `icos` orientam a associação ao tema/ícones. O manifesto marca explicitamente essas associações como inferidas ou não verificadas.

## Origem e direitos

Estar no pacote instalado não demonstra autoria nem licença. Os manifests atribuem módulos a Firecast, Firecast - AlyssonRPG ou Ambesek; essas atribuições não transferem automaticamente direitos sobre imagens, fontes ou marcas dentro deles. Não foram inspecionados perfis de usuários, portanto não se atribuiu nenhum asset distribuído a conteúdo adicionado pelo usuário.

ZIP-003/ZIP-005 encontraram avisos de bibliotecas de terceiros: Serpent 0.303 com indicação MIT, SLAXML v0.7 com indicação MIT, Delaunay com indicação MIT e `base64.lua` com texto de licenciamento alternativo incluindo MIT. Esses avisos estão vinculados aos respectivos scripts, hashes e contêineres no índice; **não se estendem às imagens, fontes, modelos, sons ou outros scripts do pacote**. Não foram feitas extrações dessas bibliotecas nem auditoria integral de modificações e obrigações. Para reutilização futura, preferir a distribuição upstream com licença e versão verificadas.

Situação dos 628 registros de assets: **desconhecida**; zero com reutilização confirmada nesta etapa. A busca de avisos foi delimitada por nomes e palavras em arquivos textuais de RPK; não prova ausência de licenças em metadados de fontes, arquivos binários ou documentação externa. Não houve consulta a URLs distribuídas nem publicação em repositório remoto.

## Substituição para uma implementação independente

Proposta: produzir ícones e temas próprios que preservem hierarquia, estados e legibilidade observados; usar fontes obtidas de origem com licença identificada; gerar modelos de dados e sons próprios ou usar acervo com licença compatível documentada; não incorporar retratos, avatares, artes de sistemas ou marcas presentes nas capturas do usuário. Os requisitos de geometria e interação podem ser especificados separadamente dos pixels dos assets.

Para um catálogo visual futuro: verificar licença individual, registrar origem/versão/termos e o hash do arquivo autorizado, então exportar somente itens permitidos para `assets-extraidos/`, mantendo o vínculo de ID. A extração e a geração de miniaturas não foram executadas agora. A ausência de catálogo não significa ausência de recursos; significa ausência de itens com permissão confirmada neste levantamento.
