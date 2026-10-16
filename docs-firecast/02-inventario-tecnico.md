# Inventário técnico

**Atualização de versão:** em 16/10/2026, [UI-001](evidencias/fase2/runtime.json) confirmou título Firecast 8.13 e o caminho do processo com o mesmo hash em disco. A divergência com FileVersion/ProductVersion continua existindo; não foi comparado o hash da imagem em memória. Os resultados estáticos abaixo continuam válidos para o mesmo artefato.

Fonte principal: [arquivos.json](inventarios/arquivos.json), ID `LOCAL-INVENTORY-001`. O script [inventario.mjs](scripts-analise/inventario.mjs) registra caminho relativo, tamanho, SHA-256, assinatura inicial, seções PE, importações e marcadores em offsets de arquivo. O conjunto de 28 arquivos exclui o diretório de logs; não é inventário de todos os dados do aplicativo no computador.

| Componente | Observação | Limite |
|---|---|---|
| Firecast.exe | 285.902.821 bytes; PE32+; Machine 0x8664; sem CLR | Ausência de CLR não determina linguagem |
| Cliente / UI | TPF0 em 73 recursos; estilos `FMX_STYLE`, namespaces FMX e System.SysUtils, marcadores Embarcadero | Delphi/FireMonkey é inferência forte; versão do compilador não delimitada |
| obsFirecast32.dll / obsFirecast64.dll | PE x86 / x86-64 respectivamente, também marcadores FMX | Modo de integração OBS não executado |
| lua53x64.dll / lua54x64.dll | Ambos PE x64; strings Lua 5.3 / 5.4 | Cliente importa Lua 5.4 estaticamente; papel da 5.3 não confirmado |
| FFmpeg e libav* | PEs x64; bibliotecas declaram n3.4.11 | Metadados não provam quais codecs o aplicativo disponibiliza |
| libeay32 / ssleay32 | PE x64; metadados OpenSSL 1.0.2o | O nome `32` não significa arquitetura x86 aqui |
| yt-dlp.exe | PE x64; versão 2022.05.18, Python 3.8.10; marcador PyInstaller | Não foi aberto/desempacotado nem executado |
| fcwebrtc_win_x64.dll / sk4d.dll / rrpgcpp64.dll | PEs x64 | Nomes sugerem papéis, mas APIs e uso não foram rastreados |
| unins000.exe | PE x86, descrição Setup/Uninstall | Não executado |
| resource.zip | ZIP real; 21.462.948 bytes; SHA-256 `892afd517cfec3a33d060feb6a1137c76d9519fb68f5dfcb1b90c1a3a8f34f2b` | Conteúdo estático, não prova seleção em runtime |
| FirecastPlugin.ico | Arquivo distribuído, 366.015 bytes | Reutilização desconhecida |
| rv.txt e arquivos unins*.dat/msg | Inventariados por tamanho/assinatura/hash | Semântica não investigada |

[Versões declaradas pelo Windows](inventarios/versoes-windows.json) foram obtidas via `FileInfo.VersionInfo`, sem executar os arquivos. Para reproduzir, selecionar `.exe`/`.dll` recursivamente e registrar FileVersion, ProductVersion, ProductName, CompanyName e FileDescription; campos nulos permanecem desconhecidos. O [script](scripts-analise/versoes.ps1) guarda esse procedimento.

O executável principal tem 12 seções, incluindo `.text` de 106.564.096 bytes brutos, `.rsrc` de 3.057.152 e `.debug` de 159.335.397 bytes. O último byte das seções coincide com o fim do arquivo; não foi encontrado overlay por esse cálculo. O nome `.debug` não prova que contém fontes ou símbolos integralmente utilizáveis; seu formato interno não foi decodificado.

O diretório normal de importações contém 29 DLLs, incluindo `lua54x64.dll`, `avcodec-57.dll`, `avformat-57.dll`, `avutil-55.dll`, `swscale-4.dll`, `swresample-2.dll`, DirectWrite/Direct2D, D3D9 e OpenGL. Isso confirma vínculos declarados, não carregamento observado. O parser não decodifica `.didata` nem chamadas de carregamento dinâmico; dependências transitivas e opcionais não estão completas.

REA Evidence `ev_cee6f056eecc2b6073260af618e4faf007a8b34c20e46ce28408cd235235d995`, em [rea-pe-firecast.json](evidencias/rea-pe-firecast.json), examinou 359 entradas de diretório e encontrou 175 recursos: UNICODEDATA 6, cursores 4, ícones 10, blocos de strings 67, RCDATA 80, grupos de cursores 4, grupos de ícones 2, versão 1 e manifesto 1. A cobertura completa se refere à árvore PE, não aos recursos do aplicativo inteiro.

Os 80 RCDATA incluem 73 streams TPF0, três estilos Windows, `CLOUDS` com assinatura JPEG, `PACKAGEINFO`, `PLATFORMTARGETS` e `DVCLAL`. [recursos-pe.json](inventarios/recursos-pe.json) preserva offsets, tamanho e hash de cada payload. A leitura estruturada de TPF0 está em [formularios.json](inventarios/formularios.json), ID `LOCAL-FORMS-001`; não expande herança nem executa handlers.

A versão do cliente local deve continuar identificada por hash. A correlação com 8.13 é apoiada pelos anexos e pelo produto declarado no desinstalador, porém FileVersion do cliente é 8.0.0.0 e ProductVersion 1.0.0.0. Datas de modificação e timestamps PE não foram usados como prova da data de compilação.
