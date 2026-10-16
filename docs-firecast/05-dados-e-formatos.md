# Dados e formatos observados

Continuação estática: o [contrato NDB](fase2/03-contrato-ndb.md) agora detalha precedência filho/atributo, conversões, XML, observadores e transações. O [importador UVTT](fase2/02-importador-uvtt.md) tem campos e transformações especificados. Nenhum XML de ficha real foi exportado nesta rodada; essas duas análises não fecham o schema ou a persistência.

Escopo desta seção: leitura estática de `C:/Users/João/AppData/Local/Firecast/resource.zip`, sem executar o cliente ou scripts e sem abrir perfis, caches pessoais ou logs. Os identificadores `ZIP-*` são evidências de scripts locais independentes, **não** Evidence emitida pelo REA. A inspeção REA complementar está em [inspect_artifact.json](evidencias/inspect_artifact.json), Evidence `ev_37f10b27dd4efeaacbf18c12bdf44b61655ffbef3390c4c1e33fedc94a09578e` e inventário `ev_b14487d294ff13b32c6cf64e4476f3b03056d8e829bae07a13bea04788726c33`.

## Evidências e reprodução

| ID | Fonte e procedimento | Resultado e classificação | Limitação |
|---|---|---|---|
| ZIP-001 | [resource-zip.json](inventarios/resource-zip.json), produzido por `node docs-firecast/scripts-analise/zip-inventory.mjs "C:/Users/João/AppData/Local/Firecast/resource.zip" docs-firecast/inventarios/resource-zip.json` | Confirmado: ZIP de 21.462.948 bytes, 1.422 membros, todos armazenados sem compressão externa; hashes dos bytes de cada membro, offsets absolutos, CRC informado e recalculado | Inventário não demonstra que cada recurso é usado pela versão em execução |
| ZIP-002 | [rpk-members.json](inventarios/rpk-members.json), script `zip-packages.mjs`, mesmos argumentos de entrada e caminho de saída correspondente | Confirmado: os cinco RPK distribuídos são ZIPs aninhados, com 741 membros somados; manifests XML identificam módulos e SDK | Offsets internos são relativos ao RPK descomprimido, nunca diretamente ao ZIP externo |
| ZIP-003 | [zip-static-index.json](inventarios/zip-static-index.json), script `zip-static-index.mjs` | Confirmado: assinaturas de cabeçalhos, referências textuais a eventos, módulos e APIs | Busca por expressão regular; comentários e nomes de módulos podem aparecer como candidatos; não é resolução semântica de chamadas |
| ZIP-005 | [zip-focused.json](evidencias/zip-focused.json), `node docs-firecast/scripts-analise/zip-focused.mjs "C:/Users/João/AppData/Local/Firecast/resource.zip" docs-firecast/evidencias/zip-focused.json` | Confirmado: campos declarados em ficha básica, eventos dos contadores, métodos do wrapper NDB, marcadores da regra de turno e avisos de bibliotecas | Saída seletiva reprodutível; não exporta o código original |
| ZIP-006 | [zip-verificacao.json](evidencias/zip-verificacao.json), `node docs-firecast/scripts-analise/zip-verify.mjs docs-firecast/inventarios/resource-zip.json docs-firecast/inventarios/rpk-members.json docs-firecast/evidencias/zip-verificacao.json` | Confirmado: 2.163 hashes de payload presentes, zero erros de leitura e zero divergências de CRC; SHA-256 do arquivo original permanece igual | Verificação de integridade não estabelece autenticidade ou direitos |

SHA-256 do arquivo de entrada em ZIP-001: `892afd517cfec3a33d060feb6a1137c76d9519fb68f5dfcb1b90c1a3a8f34f2b`. Ferramenta: Node.js `v22.23.2`, módulos padrão `fs`, `crypto`, `zlib`. A contagem externa e os totais de bytes foram conferidos também com `System.IO.Compression.ZipFile.OpenRead`: 1.422 entradas, 21.219.638 bytes de payload tanto comprimido quanto descomprimido. O parser não usa caminhos de membros para escrever arquivos.

## Formatos encontrados

| Formato efetivo | Fonte | Estrutura observada | Situação |
|---|---|---|---|
| ZIP externo | `resource.zip`, diretório central no offset 21.329.906, 133.020 bytes | Nomes, tamanhos, método, flags, CRC, offsets e payload | Confirmado por parser local; REA também identificou o arquivo |
| RPK como ZIP | `complementosPadroes/*.rpk`, ZIP-M00001 a ZIP-M00005 | `module.xml`, `sdk/`, Lua, formulários, localizações e imagens | Confirmado; não foi executada instalação do pacote |
| XML textual UTF-8 | 543 dos 1.029 membros externos `.xml` | 20 iniciam BOM UTF-8, 523 iniciam declaração XML | Confirmado por cabeçalho; não foi validado todo documento contra schema |
| Stream binário `TPF0` com extensão `.xml` | Outros 486 membros externos `.xml`, por exemplo ZIP-M00124 | Tipos/propriedades serializados, como `TFuxFrameStyle`, elementos gráficos e brushes | Cabeçalho e strings confirmados; relação com serialização de objetos Delphi é inferida a partir do stream e corroborável pela análise PE; não converter automaticamente estes arquivos com parser XML |
| Estilo textual de objetos | 3 arquivos `temas/*.style` | Iniciam `object TStyleContainer` | Confirmado por bytes; layout completo e regras de herança não reconstruídos |
| Estilo binário | 4 `.style` em `precompiled/themes/` | Prefixo ASCII `FMX_` | Confirmado; formato completo não documentado |
| Localização textual | `lang/pt.txt`, `lang/en.txt`, `lang/colors_*.txt` | Português inicia BOM UTF-8, seções como `[pt-br]`/`[pt]`, pares chave=valor, placeholders `%d`/`%s` | Confirmado em amostras; não supor semântica de escapes ou fallback sem teste |
| Localização/tema pré-compilado | `.lang` e 3 `.xml.bin` | Cabeçalhos binários distintos registrados em ZIP-003 | Existência confirmada; schema e compatibilidade desconhecidos |
| Formulário LFM distribuído | Dentro de RPK, exemplo `FichaBasicaMultiPlataforma.lfm` | XML UTF-8 com controles, atributos `field`, eventos e scripts; arquivos `.lfm.lua` também distribuídos | Confirmado em amostras; a extensão LFM aqui não implica o mesmo formato dos recursos TPF0 |
| Lua textual e extensões auxiliares | `.lua`, `.dlua`, `.xlua` dentro dos RPK | Fontes legíveis, wrappers e imports `require` | Confirmado para arquivos inspecionados; extensão auxiliar não define por si mesma compilação ou dialeto |
| Imagem, áudio, fonte e modelos | PNG/JPEG, MP3/WAV, TTF, OBJ/MTL | Inventários e dimensões de imagem em [08-assets-e-reutilizacao.md](08-assets-e-reutilizacao.md) | Tipos de imagem confirmados por assinatura; demais extensões ainda precisam de parser especializado |

Os streams PE TPF0 também receberam análise separada em [formularios.json](inventarios/formularios.json). Esses formulários pertencem ao executável; não confundir suas identidades e offsets com os membros ZIP acima.

## Modelo de dados exposto pelos scripts

`rrpginlua.rpk!/sdk/ndb.lua`, SHA-256 `0f2bc02fedde396a46d86f0e32476ef993c1eca5f022cfc0fc08d0b2efbc5551`, expõe objetos de nó com atributos, filhos, nome e identidade local. O wrapper define `getAttribute`, `setAttribute`, `getChild`, `addChild`, `delete`, `getPersistedAttributeValue`, `getLocalID`, `exportXML`, `importXML`, `beginUpdate`, `endUpdate` e métodos de permissões. Também há referências a observadores, transações, broadcast e horário UTC de servidor. **Confirmado: essas interfaces aparecem no código distribuído. Inferido: fichas usam uma árvore de dados observável. Não verificado: representação física em disco, protocolo, atomicidade, durabilidade e autoridade de servidor.**

Chamadas por handle, como `_obj_invokeEx`, fazem a ponte com o host. Não foi recuperada uma implementação independente completa do banco a partir desse wrapper. Não há evidência nesta leitura de que o armazenamento original seja SQL, SQLite ou JSON.

A ficha básica multiplataforma declara `atributosStr`, `habilidadesStr`, `equipamentosStr`, `historiaStr`. `DnD5_SDK3.rpk!/contadores.xml`, SHA-256 `95026478e6d7c661b8404f0a4b7d887f7e073543a36e9334e33e98466b941977`, conecta uma lista ao campo `contadores` e declara eventos `onCompare`, `onItemAdded`, `onItemRemoved`, `onChange`; a lógica textual propaga descanso curto/longo aos filhos. Esses são nomes do modelo da ficha distribuída, não um schema universal de personagem. As regras de D&D não foram validadas por sessão de teste.

Exemplo mínimo **sintético**, apenas para expressar a relação controle/campo observada, sem ser exportação válida de uma ficha real:

```xml
<form name="FichaTeste">
  <edit field="nomeDeTeste" />
</form>
```

O XML de exportação NDB não foi gerado e sua estrutura não deve ser confundida com esse XML de interface. Não existem dados reais de personagens neste relatório.

## Fronteira ainda aberta

Não inspecionados: arquivos de configuração fora do pacote, base local de usuário, caches, backups e snapshots quiescentes. O nome `.cache_hash` existe no pacote; apenas tamanho/hash foram registrados, sem atribuir-lhe política de invalidação. A presença de `NDB.exportXML`/`importXML` não define o formato nem prova round-trip.

Próximos experimentos: criar personagem sintético em conta/mesa de teste, exportar sem identificadores pessoais, alterar um campo de cada tipo, repetir e comparar; testar listas, Unicode, campos ausentes, números, permissões e versões. Para configurações, comparar cópias antes/depois de uma única preferência. Para RPK, desenvolver um pacote próprio mínimo e comparar seu manifesto e erros de validação com o SDK documentado. Nenhum desses experimentos foi executado nesta etapa.
