# Entrega integrada — Parte 4D

Esta revisão conclui o recorte jogável das Partes 1–4C, corrigindo problemas encontrados sem acrescentar sistemas. Resultado final: 63 testes backend/597 assertions, sete cenários operacionais, sete cenários browser distintos e build oficial aprovados; serviços de validação encerrados. Reality Checker concluiu revisão somente leitura sem novo bloqueio técnico no escopo. Entrega local concluída; piloto público continua condicionado às ações manuais abaixo. Resultados executados estão no início de [PROGRESSO.md](PROGRESSO.md). As rodadas anteriores são históricas; seus números e pendências descrevem aquele momento.

## Mestre: primeira sessão

1. Inicie os quatro processos locais conforme o [README](../README.md) e abra `http://127.0.0.1:8000`. Entre com conta individual. Crie uma mesa e escolha privacidade/senha na criação. A mesa do incidente exige revisão própria, não nova execução do seed.
2. Abra **Mapas e biblioteca**. Envie imagens próprias como fundo/token/retrato, ou **Importar dd2vtt** com `tests/Fixtures/dd2vtt/geometria.dd2vtt`. Confira imagem, grade, contagens e avisos; confirme para criar um mapa novo. Ele não vira ativo automaticamente: use o controle de mapa ativo para apresentá-lo aos jogadores.
3. O mapa importado começa coberto. Em **Escala e névoa manual**, revele a região desejada ou **Revelar tudo**, confirmando. Adicione tokens. Selecione em **Itens no mapa** e escolha quem pode mover. Essa atribuição é independente da ficha.
4. Em **Personagens**, crie a ficha; informe máximo antes do PV atual, atribua jogador e escolha retrato. Publique nome/PV somente para compartilhá-los. Com token selecionado, use **Vincular token** na ficha. Notas, atributos e recursos completos ficam restritos ao mestre/atribuído autorizado.
5. A aba da mesa volta à conversa. Envie texto público/narração/privado; escolha destinatário para privado. Use `/r 1d20+2`, crie uma macro com expressão permitida e execute-a pelo botão. Não há scripts/fórmulas arbitrárias.
6. **Régua** mede um arraste; Escape cancela. Linha/Retângulo/Elipse/Livre criam desenhos. A lista permite selecionar/remover os seus; mestre remove todos. Zoom é ancorado no cursor; Espaço+arraste move a câmera. Volte à seleção antes de arrastar tokens.
7. Revele/oculte retângulos. Ficha com token coberto em qualquer mapa deixa de ser projetada aos jogadores, inclusive ao atribuído. Cobrir novamente retira estado atual, sem apagar conhecimento anterior ou imagens baixadas.
8. Em **Combate**, adicione personagens/iniciativas e, opcionalmente, token vinculado. Inicie, avance turnos/rodadas e encerre. Empates seguem inclusão. O mesmo tracker aparece na conversa e no mapa.

Conflito 409 exige reabrir com estado atualizado antes de salvar; não force um draft obsoleto com outra chave. Após reload/reconexão, espere a recuperação do estado compartilhado.

## Jogadores

Entre com sua conta e abra o mapa ativo. Mova somente tokens visíveis, desbloqueados e atribuídos a você. A ficha atribuída é editável enquanto autorizada e visível pelas regras de névoa. Outros personagens publicados mostram apenas o resumo permitido. PV sincroniza os clientes autorizados.

Conversa, privado com destinatário, `/r`, macros permitidas, régua e desenhos próprios estão disponíveis. Jogador não importa mapas nem administra névoa/combate. Se perder acesso durante edição, o editor fecha e o servidor reavalia a autorização. Não compartilhe login.

## Matriz final

| Recurso | Estado e limite |
|---|---|
| Sessão, perfil, mesas, favoritas/recentes | Concluído no recorte documentado |
| Biblioteca privada, mapas, tokens e camadas | Concluído; downloads autorizados no acesso |
| Realtime, presença, histórico, reconexão, revisões e outbox | Concluído; fila/scheduler devem estar ativos |
| Chat público/privado/narração e formatação | Concluído; marcadores próprios, sem HTML arbitrário |
| Dados e macros | Parcial: dados usuais e somas/subtrações/macros simples; sem scripts ou motor de regras |
| Personagens, retratos, recursos/PV, vínculo e combate | Concluído no contrato 4A; sem fichas originais/automação de regras |
| Régua, quatro desenhos e névoa manual | Concluído no contrato 4B; sem visão dinâmica ou sigilo dos pixels do fundo |
| Importação dd2vtt | Parcial: subconjunto legado 0.2/0.3 limitado, prévia/cancelar/confirmar, novo mapa |
| Paredes/portais/luzes importados | Somente dados do mestre; sem iluminação, LOS, colisão ou portas funcionais |
| Editar privacidade/senha de mesa existente pela UI | Não implementado; revisão local explícita em OPERACAO-4A |
| Voz, plugins Lua, marketplace e dados 3D | Não implementados e fora do escopo |
| Piloto em três dispositivos físicos | Pendente; automação usa três contexts neste PC |
| Recuperação das contas/mesa do incidente | Pendente da confirmação do responsável |

## Backup, restauração e atualização sem seed

Backup novo da 4D, **anterior à atualização do schema**: `C:\Users\João\FreecastBackups\current-4d-20261019-124510`. Fora do Git, ACL restrita ao usuário/SYSTEM. Dump custom listado e restaurado em base descartável: 3 usuários, 36 mesas e 8 migrations anteriores. A base foi removida. Os 135 arquivos privados coincidiram por tamanho/SHA-256; o digest do dump foi conferido antes das migrations. Hashes permanecem somente no manifesto privado. Isso preserva o estado posterior ao incidente; não recupera valores anteriores desconhecidos.

Para novo backup, feche os processos do Freecast durante a cópia banco/storage. Crie diretório novo fora do workspace, retire herança e conceda acesso somente a você/SYSTEM. Confira no PHP a conexão efetiva (`pgsql`, `current_database() = freecast`, loopback5434) e root privado; não deduza o destino apenas do `.env`. No Compose deste workspace:

```powershell
$backupDir = Read-Host 'Diretório protegido novo, fora do Git'
if (-not (Test-Path -LiteralPath $backupDir -PathType Container)) { throw 'Diretório inexistente.' }
if (Test-Path -LiteralPath (Join-Path $backupDir 'freecast.dump')) { throw 'Backup já existe.' }
docker exec freecast-postgres-1 pg_dump -Fc -U freecast -d freecast -f /tmp/freecast-manual.dump
if ($LASTEXITCODE -ne 0) { throw 'pg_dump falhou.' }
docker cp freecast-postgres-1:/tmp/freecast-manual.dump (Join-Path $backupDir 'freecast.dump')
if ($LASTEXITCODE -ne 0) { throw 'Cópia falhou.' }
docker exec freecast-postgres-1 pg_restore --list /tmp/freecast-manual.dump
if ($LASTEXITCODE -ne 0) { throw 'Dump inválido.' }
Copy-Item -LiteralPath .\storage\app\private -Destination (Join-Path $backupDir 'private') -Recurse -ErrorAction Stop
```

Confira cada arquivo da cópia por tamanho/hash e guarde o manifesto privado. Valide o dump com `pg_restore --exit-on-error --no-owner` numa **nova base descartável**, nunca em `freecast` para testar. Só depois remova seu temporário `/tmp/freecast-manual.dump`. `pg_restore --list` sozinho não testa restauração. Adapte o caminho do storage se a consulta efetiva indicar outro root. Não registre credenciais no console.

Para conferir uma restauração, use uma base nova cujo nome deixe explícito que é revisão; não reutilize nome existente:

```powershell
$restoreDb = Read-Host 'Nome novo: freecast_restore_<identificador>'
if ($restoreDb -notmatch '^freecast_restore_[a-z0-9_]+$') { throw 'Destino de revisão inválido.' }
docker exec freecast-postgres-1 createdb -U freecast $restoreDb
if ($LASTEXITCODE -ne 0) { throw 'Base já existe ou criação falhou; pare aqui.' }
docker exec freecast-postgres-1 pg_restore --exit-on-error --no-owner -U freecast -d $restoreDb /tmp/freecast-manual.dump
if ($LASTEXITCODE -ne 0) { throw 'Restauração falhou; preserve o backup e inspecione a base nova.' }
docker exec freecast-postgres-1 psql -U freecast -d $restoreDb -c 'SELECT count(*) FROM users; SELECT count(*) FROM rooms;'
if ($LASTEXITCODE -ne 0) { throw 'Verificação falhou.' }
```

Esse exemplo usa o dump temporário do passo anterior; para backup já arquivado, copie-o novamente para um path temporário próprio no container antes de restaurar. Confira as relações/arquivos na base nova. Não há troca automática de destino.

Restauração real exige decisão explícita: pare processos e faça backup do estado que será substituído. Mantenha APP_KEY e banco/storage do mesmo snapshot. Só substitua o destino confirmado após revisão; não use `--clean`, apague storage ou restaure o banco inteiro automaticamente. O dump pré-4B/4C desta rodada exige reaplicar as duas migrations se usado numa recuperação futura. Nenhuma restauração do desenvolvimento foi executada na 4D.

As duas migrations aditivas autorizadas foram inspecionadas e aplicadas individualmente, em ordem, em `freecast`, após verificar destino/backup. Nenhuma outra migration ou seed foi executada nesse banco. Linhas existentes de contas/mesas/associações foram comparadas antes/depois e permaneceram iguais. Atualização futura exige novo backup, `migrate:status`, inspeção e aprovação dos paths específicos; não use `migrate --seed`, `migrate:fresh`, rollback automático ou `composer setup` sobre dados existentes.

## Repetir os testes

Backend/operacionais/build: [README](../README.md). Nunca execute PHPUnit enquanto servidores/browser estiverem usando `freecast_test`.

Em terminais descartáveis, na raiz:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/Run-Part4ATests.ps1 -Action Prepare
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/Run-Part4ATests.ps1 -Action Build
```

Abra três terminais com o mesmo comando usando `-Action Serve`, `-Action Reverb` e `-Action Worker`. Execute `-Action Browser4D`; `-Action Visual4D` captura nos tamanhos originais e `-Action Regression4D` executa salas/mapas/importação. Outras regressões: `Browser4C`, `Browser4A`, `Browser4B`, `Regression4B` e `Browser`. Antes de `-Action Realtime`, encerre o worker externo: esse cenário controla o próprio worker. Feche os processos próprios com Ctrl+C e confira 8014/8084 sem listeners. No shell local normal, `npm.cmd run build` restaura os assets locais.

O executor confirma PostgreSQL efetivo `freecast_test` e storage exclusivo no mesmo PHP antes das ações. Falhas nativas/bootstrap/guarda/preparação propagam código não zero; Playwright recusa ambiente ausente em vez de iniciar um servidor usando `.env` de desenvolvimento. Runtime `validation` mantém CSRF padrão. `maps:prune-import-previews` está agendado por hora e `rooms:dispatch-outbox` por minuto. `composer dev` e o piloto incluem scheduler; assets compilados exigem o quarto terminal.

## Piloto com amigos

Procedimento existente: [PILOTO-INTERNET.md](PILOTO-INTERNET.md), com início/parada/preflight. Nenhum túnel, porta de firewall ou configuração de outro projeto foi alterado na 4D. A liberação pública **continua condicionada** à troca das três senhas e revisão de privacidade/senha/participantes, com confirmação em [OPERACAO-4A.md](OPERACAO-4A.md). Execução pública completa e três dispositivos externos não foram repetidos nesta rodada. Confira a entrega local agora; não marque contas/mesa como recuperadas automaticamente.
