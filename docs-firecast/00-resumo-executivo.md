# Resultado da primeira etapa

**Atualização em 16/10/2026:** a [fase2](fase2/README.md) acrescentou contratos de macros/NDB/UVTT, fontes e cores e os [primeiros testes reais](fase2/05-testes-na-mesa.md). O título 8.13 e a mesa autorizada foram confirmados; texto e rolagens funcionaram, mas `/macros` respondeu comando desconhecido. O restante deste resumo descreve a etapa inicial; não representa o estado atual das pendências de runtime. Consulte [PROGRESSO](PROGRESSO.md).

O alvo existe em `C:\Users\João\AppData\Local\Firecast`. Foram inventariados 28 arquivos, somando 423.598.042 bytes, excluindo `LogErrors`. A identidade do cliente é SHA-256 `4f80d08ab252ffd0b541c89cf09610028c4f8e45879cb8969bc45e8ac74c1086`.

O cliente é PE32+ x86-64 sem diretório CLR. Os recursos TPF0, estilos FMX e marcadores Embarcadero/Delphi constituem evidência forte de Delphi/FireMonkey; isso é uma inferência de tecnologia, não recuperação do projeto original. Os demais componentes têm identidades próprias: FFmpeg, OpenSSL, Lua, OBS e um executável com metadados Python/PyInstaller. A presença de uma biblioteca não confirma seu uso em uma sessão.

A versão visual é 8.13 em cinco capturas, enquanto `Firecast.exe` declara FileVersion 8.0.0.0 e ProductVersion 1.0.0.0. O desinstalador declara produto 8.13. Não houve execução para ligar definitivamente essas capturas ao hash local.

REA 6.5.0 foi utilizado de fato: inventário completo de 175 recursos PE e inspeção do ZIP, com registro dos resultados originais. Um cliente MCP stdio efêmero acessou a instalação existente; nenhuma configuração foi modificada. A sessão de arquivo ZIP foi fechada.

Foram decodificados estaticamente 73 formulários TPF0 do executável, com consumo completo dos streams. Isso recupera propriedades serializadas e nomes de handlers, sem seus corpos. O `resource.zip` tem 1.422 entradas e inclui pacotes, scripts, templates, imagens e sons; os detalhes estão em [dados](05-dados-e-formatos.md) e [plugins](06-fichas-scripts-e-plugins.md).

As oito imagens fornecidas documentam a mesa, menu de layout, início, listas, editor de mensagens, macros e diretório de instalação. Não demonstram envio, persistência, autorização ou sincronização. Nenhum experimento autenticado foi executado. As regras de rolagens, permissões, conflitos, importação e reconexão continuam insuficientes para uma reprodução fiel.

Recomenda-se provisoriamente Laravel com Vue/Inertia e Reverb, como [proposta independente](09-arquitetura-proposta.md). Não é uma descrição da arquitetura interna do Firecast. A futura aplicação não precisará ser escrita em Delphi. As três metas de compatibilidade — reproduzir fichas, importar dados e executar plugins existentes — precisam de avaliações separadas.
