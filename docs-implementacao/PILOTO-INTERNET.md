# Piloto temporario pela internet

Este guia descreve o piloto do Freecast com Cloudflare Quick Tunnel em um computador Windows. O link e temporario, muda a cada inicializacao e so funciona enquanto o PC e os processos do piloto estiverem ligados.

## Exposicao de rede

O Quick Tunnel aponta somente para o Caddy local em `127.0.0.1:8091`. O Caddy encaminha:

- Rotas HTTP da aplicacao para Apache/PHP em `127.0.0.1:8000`, com document root em `public`.
- `/app/*` para o Reverb em `127.0.0.1:8082`, preservando o caminho e o upgrade WebSocket.

O navegador usa o mesmo hostname publico para HTTPS e WSS na porta 443. O Laravel publica internamente no Reverb por HTTP em loopback. PostgreSQL continua restrito a `127.0.0.1:5434`. Vite, phpMyAdmin, PostgreSQL e endpoints internos de publicacao do Reverb nao sao encaminhados.

O Caddy aceita cabecalhos encaminhados somente do proxy local Cloudflare Tunnel; o Laravel confia apenas nos enderecos de loopback usados pelo Caddy. A lista de origens do Reverb contem o hostname `trycloudflare.com` daquela inicializacao.

Nao e necessario abrir portas no roteador nem no firewall para usar o tunnel; mantenha os listeners internos em loopback.

## Preparar contas e dados

1. Antes de iniciar a exposicao, crie ou confirme uma conta individual para cada participante e defina uma senha exclusiva, nao trivial, com pelo menos 12 caracteres e mistura de maiusculas, minusculas, numeros e simbolos.
2. Nao compartilhe contas e nao use a senha demonstrativa `Mesa2026!`. O preflight interrompe a inicializacao se uma das tres contas semeadas ainda aceitar essa senha.
3. Preserve o `.env`, a `APP_KEY`, a base `freecast` e os arquivos privados existentes. O script aplica configuracoes do piloto apenas aos processos filhos; nao roda migrations nem troca o banco por padrao.
4. Para validacao automatizada isolada, use `-UseTestDatabase`. Essa opcao aponta para `freecast_test`; nao a use como banco de uma sessao real.

## Iniciar e compartilhar

Abra o PowerShell na raiz do projeto. Para iniciar o piloto com a base de desenvolvimento:

```powershell
.\scripts\Start-Pilot.ps1
```

O preflight exige `APP_DEBUG=false`, `APP_ENV=production`, PostgreSQL em `127.0.0.1:5434`, fila/cache/sessoes no banco, Reverb em `127.0.0.1:8082`, origem/cookie seguros e credenciais demonstrativas alteradas. O script aplica `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD` do `.env` somente ao ambiente dos processos do Freecast; no modo de teste, somente `DB_DATABASE` muda para `freecast_test`. As conexoes de sessoes, fila e cache sao fixadas em PostgreSQL, e `DB_URL` e limpo para impedir uma sobrescrita herdada. Nenhum valor e escrito em `VITE_*`, no manifesto ou nos logs. O script verifica as portas `8000`, `8082` e `8091`, inicia apenas o servico PostgreSQL deste Compose se necessario, cria o Quick Tunnel uma vez, captura o hostname, compila os assets com o destino WSS publico e inicia Apache, Caddy, Reverb, worker e scheduler. Ele nao executa migrations nem altera `.env`.

Copie do console o endereco `https://<hostname>.trycloudflare.com` apresentado como pronto e envie apenas aos amigos. Todos devem abrir esse endereco, nunca `localhost` ou uma porta interna. Cada pessoa entra com a propria conta. O Reverb usa `wss://<hostname>.trycloudflare.com/app/<chave-publica>` na porta 443; a chave publica de conexao nao e um segredo. A chave secreta do Reverb e usada apenas no servidor.

Para uma verificacao deliberada contra a base isolada de testes:

```powershell
.\scripts\Start-Pilot.ps1 -UseTestDatabase
```

Essa opcao nao limpa, semeia nem migra `freecast_test`; prepare os fixtures de teste separadamente. Nunca use contas de teste ou senhas demonstrativas em um piloto com dados reais.

## Backup

Salve os backups fora do workspace e proteja-os com criptografia/ACL adequada. No PowerShell aberto na raiz do projeto, substitua `freecast` se o nome de usuario/banco do Compose tiver sido alterado:

```powershell
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backupDir = Join-Path $env:USERPROFILE 'FreecastBackups'
New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
$dumpPath = Join-Path $backupDir "freecast-$stamp.dump"
& $env:ComSpec /d /c "docker compose exec -T postgres pg_dump -Fc -U freecast -d freecast > `"$dumpPath`""
if ($LASTEXITCODE -ne 0) { throw 'O backup PostgreSQL falhou.' }
Copy-Item -LiteralPath .\storage\app\private -Destination (Join-Path $backupDir "private-$stamp") -Recurse
```

O armazenamento privado pode conter mapas, tokens e anexos; nao o coloque em `public` nem o envie junto com o dump. Guarde o dump e a copia de arquivos em local protegido e teste a restauracao antes de depender deles.

## Encerrar e voltar ao modo local

Na mesma raiz do projeto:

```powershell
.\scripts\Stop-Pilot.ps1
```

O encerramento confere PID, executavel e linha de comando antes de parar somente os processos do Freecast registrados pelo inicio. O script restaura um cache de configuracao anterior apenas se o arquivo nao tiver sido recriado ou alterado depois; recompila os assets com as variaveis locais atuais e remove o registro de processos. Ele nao altera `.env`, nao encerra processos de outros projetos e deixa o PostgreSQL do Freecast em execucao. O Quick Tunnel para junto com o `cloudflared`, invalidando o link.

O modo local volta a usar as configuracoes atuais de `.env`. Se o script avisar que um PID ja nao corresponde ao processo registrado, ele deixa esse PID intacto; inspecione os logs e o registro em `%LOCALAPPDATA%\Freecast\pilot` antes de qualquer acao manual.

## Logs e ferramentas

Estado de processos e logs ficam em `%LOCALAPPDATA%\Freecast\pilot`; nao incluem senhas da aplicacao. Os executaveis x64 de `cloudflared` e Caddy ficam em `tools` sob esse diretorio. O script verifica a assinatura Authenticode do cloudflared e o checksum oficial do arquivo do Caddy ao provisiona-los.

## Validacao

Na rodada final, `php artisan test` passou com 40 testes/199 assertions usando `freecast_test` via PostgreSQL. `rooms.spec.ts` e `maps.spec.ts` passaram com 3 testes; `realtime.spec.ts` passou com 1 teste. Os cenarios rodaram em tres contexts Playwright independentes atraves de um hostname Quick Tunnel temporario, verificando HTTPS/WSS, login sem 419, cookie/CSRF, mesas, upload privado, mapa/tokens e persistencia, chat concorrente, dado idempotente, privacidade, reconexao, recuperacao da fila e revogacao de canais/midia. A captura sintetica do canvas foi salva em `../test-results/realtime-map-three-contexts.png`.

O processo foi encerrado apos os testes; nao ha listener do piloto em 8000, 8082 ou 8091, e o PostgreSQL do projeto continua ativo apenas em 5434. O link usado nao foi mantido nos registros e deixa de funcionar quando o tunnel para. Esses resultados nao substituem um teste com amigos em tres dispositivos/rede externos; o computador deve permanecer ligado durante um piloto real. Antes de iniciar com `freecast`, troque as senhas das contas demonstrativas; o preflight bloqueia as credenciais conhecidas. A causa exata do antigo 419 nao foi comprovada por A/B do cookie legado, embora nao tenha ocorrido na validacao final.

Os testes browser enviaram imagens sinteticas para o armazenamento privado. Depois, a suite PHPUnit reconfigurou `freecast_test`; nao removi arquivos do storage compartilhado para evitar apagar conteudo preexistente. Arquivos de teste sem metadados correspondentes podem permanecer; nao apague o armazenamento privado em massa.
