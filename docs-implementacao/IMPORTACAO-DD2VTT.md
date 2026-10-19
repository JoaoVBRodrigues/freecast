# Importação dd2vtt — Parte 4C

Na mesa, abra **Mapas e biblioteca → Importar dd2vtt**, selecione um arquivo local e pressione **Validar e preparar prévia**. Confira imagem, dimensões, grade, contagens e avisos. **Confirmar importação e abrir novo mapa** cria um mapa novo; **Cancelar importação** descarta apenas a prévia. Somente proprietário/mestre pode importar. O autor da prévia precisa conservar essa permissão para confirmar.

Para experimentar, use [minimo.dd2vtt](../tests/Fixtures/dd2vtt/minimo.dd2vtt), arquivo pequeno de autoria própria com PNG de 200 × 200, grade 50 e listas vazias. [geometria.dd2vtt](../tests/Fixtures/dd2vtt/geometria.dd2vtt) acrescenta uma parede, portal e luz sintéticos. Não contém arte de terceiros. O gerador independente está em `tests/Fixtures/dd2vtt/generate.cjs`.

O novo mapa começa com **névoa manual habilitada e totalmente coberta**, sem revelar células por paredes ou luzes. Não vira mapa ativo automaticamente. Mapas, tokens, fichas e combate existentes não são alterados. O fundo fica no disco privado, com download autorizado aos membros da mesa; não é recurso exclusivo do mestre. A névoa não impede baixar os pixels do fundo, conforme o limite já documentado da 4B.

No inspector do mapa, **Dados importados dd2vtt (somente mestre)** mostra contagens, avisos e a estrutura normalizada inspecionável. Paredes, portais e luzes **são somente dados armazenados** nesta etapa: não há renderização funcional, iluminação dinâmica, linha de visão, colisão nem porta interativa. Nada é convertido em desenho público. Duplicar o mapa conserva esses dados e a máscara; redimensionar depois não recalcula as coordenadas históricas importadas.

## Contrato adotado e fontes

A análise estática do [importador original 1.2](../docs-firecast/fase2/02-importador-uvtt.md) comprovou leitura de tamanho, imagem, segmentos, limites de portais e posição/alcance de luz usando a célula já existente; não lê origem, pixels/célula ou versão. Não demonstrou importação em execução. Não repetimos REA nem afirmamos equivalência com o Firecast.

A [documentação primária do exportador/importador Arkenforge](https://arkenforge.com/universal-vtt-files/) descreve as unidades em células, resolução, geometria, estado de portal, propriedades de luz e imagem embutida. O [código do importador Moo Man](https://raw.githubusercontent.com/moo-man/FVTT-DD-Import/master/ddimport.js), funções `makeWall`, `GetDoors`, `GetLights` e `isWithinMap`, corrobora subtrair a origem antes da escala. São referências do formato legado e de uma implementação independente; não constituem prova de comportamento do Firecast.

O Freecast aceita **somente `format` numérico 0.2 ou 0.3**, um subconjunto deliberado do legado; UVTT v2 não é aceito. Usa `resolution.map_size × pixels_per_grid` como tamanho em pixels e, em todos os pontos, **mundo = (coordenada − map_origin) × pixels_per_grid**, com y para baixo. Distâncias não subtraem a origem. Não aplica o deslocamento −0,5 observado nas luzes do importador original. A conversão é centralizada no servidor em `Dd2vttParser::point`; os resultados já são coordenadas mundiais usadas pelo editor e por `mapCoordinates.ts`, sem segunda conversão no cliente.

| Campo | Política Freecast |
|---|---|
| `resolution.map_origin` | x/y finitos; ausente/nulo usa zero com aviso |
| `resolution.map_size` | x/y positivos; devem gerar pixels inteiros |
| `resolution.pixels_per_grid` | inteiro, também tamanho da célula existente |
| `line_of_sight`, `objects_line_of_sight` | polilinhas separadas, conservando sua origem de dados |
| `portals` | dois `bounds`; position, rotation em radianos, closed, freestanding preservados |
| `lights` | position, range em pixels, intensity, color hex e shadows preservados |
| `image` | somente PNG/WebP embutido em Base64 canônica, sem prefixo/URL |

Listas opcionais ausentes viram vazias com aviso; listas nulas são recusadas. Portal sem campos opcionais usa posição média dos limites, rotação zero, closed=true e freestanding=false. Luz sem opcionais usa intensidade 1, cor ffffffff e shadows=true. Valores opcionais nulos nesses campos recebem os mesmos padrões. `environment` e campos adicionais são ignorados com aviso; nunca são executados ou usados para buscar recursos. Não há compatibilidade completa prometida.

## Limites e falhas

- Arquivo JSON até **29 MiB**, profundidade 32; imagem decodificada de Base64 até **20 MiB**, com overhead da Base64 incluído no limite do arquivo.
- Dimensões de mundo/imagem entre **200 e 8000** por eixo, área até **16 milhões de pixels**, grade inteira entre **8 e 256**. Dimensões reais devem coincidir com resolução calculada. A máscara continua em células fixas de 64, até 15.625 células.
- Até 1.000 paredes no total, 1.000 portais e 1.000 luzes; polilinha de 2 a 256 pontos, portal com exatamente 2 limites, até 20.000 pontos no total. Pontos normalizados precisam estar dentro do mapa. Origem/coordenadas de entrada entre −1.000.000 e +1.000.000; alcance entre 0 e 1.000 células, intensidade entre 0 e 100. Números não finitos e tipos incorretos são recusados.
- Base64 estrita com decodificação/reencodificação idêntica, MIME real e dimensões por PHP; validação de chunks/CRC/finalização PNG ou comprimento/chunks RIFF/WebP. Isso **não é uma decodificação completa do bitmap**. Não se instalou GD ou outra dependência. A interface exige carregar a imagem antes de habilitar confirmação e bloqueia erro de exibição.
- PHP do servidor precisa permitir upload de pelo menos 29 MiB e POST de pelo menos 32 MiB. O Apache **isolado de teste** fixa 29M/32M; a consulta do PHP CLI atual registrou 40M/40M. Nenhum php.ini global foi alterado. Um limite do servidor menor pode recusar o arquivo antes da validação da aplicação.

Prévia expira após **30 minutos**, vinculada ao autor, mesa e imagem verificada por digest; o JSON bruto não é guardado. Título é vinculado à prévia, e nomes repetidos recebem sufixo ao confirmar. O ID da prévia é a chave durável da confirmação: reenvio retorna o mesmo recibo/mapa sem duplicar asset/evento. Selecionar novamente o arquivo gera nova prévia e permite nova importação deliberada. Mapa confirmado removido retorna 410 ao replay; não é recriado silenciosamente.

Staging e fundo têm caminhos privados aleatórios definidos pelo servidor. Transação, lock da mesa, recibo e outbox reutilizam `RoomSyncService`. Erro antes de commit remove somente arquivos criados pela chamada sem asset persistido; erro posterior ao commit preserva o fundo. Prévia confirmada não depende do staging para replay. Falha ao limpar staging é recuperável por `php artisan maps:prune-import-previews`, também agendado a cada hora: remove prévias expiradas não confirmadas e staging residual confirmado, sem apagar fundos ou recibos. O scheduler deve estar executando para limpeza automática; sem ele, rode o comando no ambiente apropriado. Falhas de disco persistentes exigem corrigir o disco e repetir a limpeza.

## Validar localmente sem desenvolvimento

Não execute seed ou migrations em `freecast` para testar este fluxo. Use os comandos existentes `Run-Part4ATests.ps1 -Action Prepare`, `Build`, `Serve`, `Reverb`, `Worker` e `Browser4C` em ambiente isolado. As guardas PHP confirmam PostgreSQL `freecast_test` e storage exclusivo antes de escrever; staging é subdiretório desse storage. Runtime `validation` conserva CSRF normal. PHPUnit roda separadamente com os serviços browser encerrados.

As migrations 4B e 4C permanecem pendentes no desenvolvimento; aplique-as somente quando decidir atualizar esse ambiente, sem seed. A recuperação das contas/mesa do incidente anterior permanece aguardando sua confirmação conforme [OPERACAO-4A.md](OPERACAO-4A.md). Não houve túnel público nesta rodada.
