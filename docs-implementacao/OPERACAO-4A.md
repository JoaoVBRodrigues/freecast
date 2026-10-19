# Correção operacional da Parte 4A — 18/10/2026

Esta rodada preserva a implementação 4A e não inicia 4B. Não houve túnel, alteração de firewall, seed em `freecast`, restauração do desenvolvimento nem mudança de `.env`/APP_KEY.

## Estado preservado e limites de recuperação

Backup do estado **atual, posterior ao incidente**: `C:\Users\João\FreecastBackups\current-4a-20261018-211809`. Diretório fora do workspace/Git, ACL sem herança, acesso concedido apenas ao usuário atual e SYSTEM. Contém dump PostgreSQL custom, cópia do storage privado efetivo `storage/app/private`, manifesto SHA-256 e registro de verificação. Não compartilhar esse diretório: ele contém dados privados e hashes de credenciais do banco, mesmo que não sejam exibidos nos relatórios.

O dump foi validado com `pg_restore --list` e restaurado com `--exit-on-error --no-owner` em uma base descartável exclusiva, depois removida. Leitura da restauração confirmou 3 usuários/36 mesas. Os 135 arquivos do storage foram comparados individualmente por tamanho/hash entre origem e cópia; os hashes ficam somente no manifesto privado. O runtime de desenvolvimento foi consultado e confirmou `freecast` e o caminho privado copiado. A verificação não restaurou `freecast` nem `freecast_test`.

Não foi encontrado backup anterior **no diretório conhecido `C:\Users\João\FreecastBackups`**, que tinha zero entradas antes desta cópia. Não se afirma inexistência de backups em outros discos/serviços. SQLite preservado não foi considerado backup anterior equivalente do PostgreSQL. Esta cópia não recupera senhas/configurações anteriores ao seed. Sem backup anterior ou informação do responsável, os valores originais permanecem desconhecidos e não podem ser reconstruídos.

## Incidente e contenção

O seeder anterior usava `updateOrCreate`: redefinia senha/nome de `mestre@freecast.test` (Nara Vale), `jogador@freecast.test` (Theo Arantes) e `convidada@freecast.test` (Lia Monte). Também atualizava sistema/descrição/publicidade/senha da mesa demonstrativa do mestre, papéis das três associações e data de visita; inseria favoritos/mensagens sintéticas ausentes. Não removia mensagens existentes nem redefinia senhas de outras contas. O código e o incidente comprovam quais campos foram escritos; não comprovam que todos os valores anteriores diferiam dos novos.

Consulta atual confirmou mesa ID 1, `A Cripta das Marés`, sistema D&D 5ª Edição, pública/sem senha, mestre como owner e os outros dois como player. Descrição e data de visita foram alvos do seed anterior; seus valores anteriores não foram guardados.

Após o backup, conexão administrativa ao PostgreSQL identificou **91 sessões** das três contas. Foram removidas em transação, somente por `user_id` dessas contas, e a contagem posterior foi **zero**. Nenhuma tinha `remember_token` (zero antes/depois). Não houve login web nas contas de desenvolvimento nem troca de senha nesta rodada. O banco e o storage de desenvolvimento foram preservados, exceto pela contenção explicitamente descrita. Usuários, mesa e associações não foram restaurados/reconfigurados.

## Prevenção

- `Run-Part4ATests.ps1` interrompe falha de dot-source, verifica código de cada executável nativo e termina com erro propagado ao PowerShell chamador. Resolução de caminho curto do Apache verifica código antes de processar saída.
- `test-action.php` exige ambiente explícito de validação e confirma isolamento no **mesmo processo PHP** que chama migrations e seed. Não executa seed se migrate falhar. Não há fallback autorizado para o `.env` de desenvolvimento.
- `TestEnvironmentGuard` valida driver PostgreSQL efetivo, `SELECT current_database() = freecast_test` e caminho exclusivo de storage permitido. O provider aplica a guarda no boot do runtime de testes/validação, antes de sessão HTTP, comandos de migration e traits destrutivas de PHPUnit. Caminhos reais divergentes por link são recusados.
- `TestFixtureSeeder` faz a mesma guarda antes da primeira escrita. `DatabaseSeeder` normal usa criação sem atualização e, quando a mesa demonstrativa já existe, retorna sem tocar suas relações. O teste compara todas as linhas das tabelas afetadas antes/depois de reexecutar o seed num banco de teste.
- As conexões de sessão/cache/locks/fila são fixadas em `pgsql`; DB_URL é limpo. Aplicação/Reverb ficam em loopback 8014/8084. `APP_ENV=validation` conserva o middleware CSRF padrão; nenhuma exceção/remoção foi adicionada.
- Mocks seguros verificam recusa a banco efetivo incorreto, driver incorreto e storage de desenvolvimento, sem tentativa de escrita em `freecast`. `tests/Operational/ScriptSafety.ps1` testa falha de ambiente (zero executáveis), preflight (uma chamada mock) e preparação (duas chamadas mock); cada filho PowerShell retorna erro.

## Ações manuais pendentes do responsável

1. Mantenha a exposição pública desligada. Use somente o fluxo local normal para desenvolvimento; não use `freecast_test` para dados pessoais.
2. Entre separadamente com cada um dos três emails e abra `/profile`. Em **Update Password**, informe **Current Password**, escolha sua própria senha em **New Password**, repita em **Confirm Password** e pressione **Save**. Não envie a senha ao chat, linha de comando ou arquivo de log. Repita para as três contas. As sessões antigas já foram invalidadas; encerre as sessões novas ao terminar se compartilhar o computador.
3. Abra a mesa `A Cripta das Marés` como mestre e confira a lista de participantes. Decida quem deve permanecer e qual deve ser a privacidade/senha da mesa. Hoje ela está pública e sem senha. A aplicação não tem formulário para editar essas propriedades de uma mesa existente; não há botão de remoção de participante nesse painel. Portanto não basta procurar um controle na interface.
4. Para a revisão administrativa local, `php artisan tinker` permite ler, sem imprimir hashes:

```php
$mesa = App\Models\Room::query()->select(['id', 'title', 'owner_id', 'system', 'description', 'is_public'])->whereKey(1)->where('title', 'A Cripta das Marés')->firstOrFail();
$mesa->only(['id', 'title', 'owner_id', 'system', 'description', 'is_public']);
App\Models\Room::whereKey(1)->whereNotNull('password_hash')->exists(); // somente presença
$mesa->memberships()->with('user:id,email,name')->get(['id', 'user_id', 'role']);
```

Se decidir restringir a mesa, execute **você** no Tinker local o bloco abaixo. A senha é lida em prompt oculto, sem literal no histórico; nada disto foi executado nesta rodada. A senha da mesa deve ter pelo menos 6 caracteres, conforme a regra atual. Para mantê-la pública, não execute o bloco. Remoção/alteração de participantes deve ser uma decisão explícita do responsável, com IDs conferidos; não apagar associações em massa.

```php
if (Laravel\Prompts\confirm('Confirmar tornar privada SOMENTE a mesa ID 1?', false)) {
    $senhaMesa = Laravel\Prompts\password('Nova senha da mesa');
    $confirmacaoMesa = Laravel\Prompts\password('Repita a senha da mesa');
    if (mb_strlen($senhaMesa) < 6 || $senhaMesa !== $confirmacaoMesa) {
        throw new RuntimeException('Senha insuficiente ou confirmação diferente; nenhuma alteração.');
    }
    $mesa->is_public = false;
    $mesa->password_hash = Illuminate\Support\Facades\Hash::make($senhaMesa);
    $mesa->save();
    unset($senhaMesa, $confirmacaoMesa);
}
```

Confirme ao final a troca das três senhas e a decisão sobre privacidade/senha/participantes. **Recuperação das contas/mesa permanece pendente dessa confirmação.** O backup atual protege contra novos incidentes, mas não desfaz o incidente anterior.

## Repetir a validação isolada

Resultados executados: **47 testes backend / 306 assertions**, **3 cenários mock PowerShell**, **3 regressões browser rooms/maps**, **1 cenário browser 4A com CSRF 419/419/200** e **1 cenário realtime legado (1,3 min)**, além do build oficial. A primeira tentativa 4A criou corrida de revisão ao inserir a prova CSRF antes do editor do jogador; a prova foi movida ao final, mantendo as verificações. A primeira tentativa realtime detectou leitura HTTP ainda pendente ao fechar página no listener de coleta do teste; agora a leitura é aguardada antes da checagem de privacidade e fechamento, sem suprimir erros. As rodadas finais passaram. Não houve CSRF bypass nem validação física de três dispositivos.

Ao encerrar, processos próprios foram finalizados, TCP 8014/8084 confirmado fechado, cloudflared ausente e build local normal aprovado, restaurando assets. `.env`/APP_KEY não foram escritos; `.env` permanece ignorado pelo Git. Não havia checksum inicial desta rodada para alegar prova criptográfica histórica de preservação. A cópia protegida do dump foi adicionalmente comparada por hash ao original restaurado e listada novamente; os temporários de dump no container foram removidos. A captura histórica 4A foi preservada e a nova imagem salva como [parte4a-validacao-operacional.png](../capturas/parte4a-validacao-operacional.png).

Há base técnica para começar 4B em uma tarefa separada. Contas/mesa ainda aguardam revisão e confirmação manual antes de qualquer piloto público; não são consideradas recuperadas. 4B não foi implementada nesta rodada.

Execute `powershell.exe -NoProfile -ExecutionPolicy Bypass -File tests/Operational/ScriptSafety.ps1` para os mocks. Execute PHPUnit com os serviços browser encerrados. Depois use `Run-Part4ATests.ps1 -Action Prepare`, `Build`, `Serve` e `Reverb`; para 4A/regressões use `Worker` em outro terminal e `Browser`. Pare esse worker antes de `Realtime`, pois o teste legado inicia/para seu próprio worker para provar recuperação. `Realtime` não cria túnel. Cada ação usa processos descartáveis e verifica isolamento.

Ao terminar, encerre apenas os processos próprios, confira 8014/8084 fechadas e rode `npm.cmd run build` no ambiente local normal para restaurar assets. O teste realtime mantém a exigência HTTPS/WSS no mesmo hostname/porta para piloto público; em HTTP local compara host e porta Reverb explícita. Seus novos screenshots ficam no outputDir de teste, preservando a captura histórica.
