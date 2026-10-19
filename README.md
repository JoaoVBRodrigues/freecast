# Freecast

Mesa virtual independente para jogar RPG com amigos: mesas, biblioteca privada, mapas, conversa em tempo real, dados/macros simples, personagens/PV, combate, régua, desenhos, névoa manual e importação limitada de `.dd2vtt`.

Este workspace usa PostgreSQL `freecast`, em `127.0.0.1:5434`. As migrations 4B/4C foram aplicadas na revisão 4D após backup verificado. Não execute seed para preparar dados reais nem gere outra APP_KEY neste ambiente existente.

## Abrir agora no Windows

Requisitos: PHP 8.2 com PDO PostgreSQL, Composer, Node 22/npm e Docker Desktop/PostgreSQL 17. A importação exige GD PNG/WebP no servidor e CLI, além de `proc_open`. O helper habilita a DLL já instalada somente neste processo, sem editar o `php.ini` global.

Na raiz, abra um PowerShell de trabalho:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass
. .\scripts\Use-FreecastPhp.ps1
docker compose up -d postgres
if ($LASTEXITCODE -ne 0) { throw 'PostgreSQL não iniciou.' }
npm.cmd run build
if ($LASTEXITCODE -ne 0) { throw 'Build falhou.' }
php artisan serve --host=127.0.0.1 --port=8000
```

Em outros três terminais, abra o mesmo PowerShell de trabalho, na raiz, e execute o helper antes do respectivo comando:

```powershell
# Terminal 2
. .\scripts\Use-FreecastPhp.ps1
php artisan reverb:start --host=127.0.0.1 --port=8082

# Terminal 3
. .\scripts\Use-FreecastPhp.ps1
php artisan queue:work --tries=5 --timeout=20 --sleep=1

# Terminal 4
. .\scripts\Use-FreecastPhp.ps1
php artisan schedule:work
```

Abra **http://127.0.0.1:8000**. Este fluxo usa assets compilados e os valores locais existentes do `.env` (Reverb 8082), sem depender de Vite/5173. Encerre com **Ctrl+C nos quatro terminais**. PostgreSQL pode continuar ligado; para pará-lo use `docker compose stop postgres`, que alcança somente esse serviço deste projeto.

Para desenvolvimento com Vite, no shell com o helper use `$env:REVERB_SERVER_HOST='127.0.0.1'` e `composer dev`; o atalho inclui servidor, Vite, fila, Reverb e scheduler e exige 5173 livre. Não execute os dois fluxos simultaneamente.

## Jogar e operar

- [Guia 4D: mestre, jogadores, backup/restauração e matriz de recursos](docs-implementacao/ENTREGA-4D.md).
- [Importação dd2vtt, fixture própria e dependências](docs-implementacao/IMPORTACAO-DD2VTT.md).
- [Resultados e histórico](docs-implementacao/PROGRESSO.md), [decisões](docs-implementacao/DECISOES.md) e [evidências visuais](docs-implementacao/PARIDADE.md).
- [Piloto temporário pela internet](docs-implementacao/PILOTO-INTERNET.md): procedimento existente; nenhum túnel foi publicado na 4D.

Antes de compartilhar com amigos, o responsável ainda deve confirmar a troca das três senhas e revisar privacidade/senha/participantes da mesa do incidente, conforme [OPERACAO-4A.md](docs-implementacao/OPERACAO-4A.md). O backup atual não recupera valores anteriores desconhecidos. Três contexts Playwright neste PC não equivalem a três dispositivos físicos.

Paredes, portais e luzes importados são somente dados armazenados para o mestre: sem iluminação dinâmica, colisão ou portas funcionais. A névoa filtra entidades/projeções, mas não protege pixels do fundo já baixado.

## Verificar sem usar o desenvolvimento

Execute backend com os servidores de teste encerrados:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass
. .\scripts\Use-FreecastPhp.ps1
php artisan test
powershell.exe -NoProfile -ExecutionPolicy Bypass -File tests/Operational/ScriptSafety.ps1
powershell.exe -NoProfile -ExecutionPolicy Bypass -File tests/Operational/TestActionSafety.ps1
npm.cmd run build
```

Para navegador use exclusivamente o [executor isolado](docs-implementacao/ENTREGA-4D.md#repetir-os-testes), que verifica `freecast_test` e storage exclusivo no mesmo PHP das ações. Playwright recusa ausência do ambiente explícito; não o aponte para `freecast`. Preserve `.env`, APP_KEY e arquivos privados. `composer install`/`npm ci` instalam dependências; não execute `composer setup` ou seed sobre este ambiente existente.
