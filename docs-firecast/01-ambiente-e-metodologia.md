# Ambiente e metodologia

**Continuação em 16/10/2026:** os métodos abaixo descrevem a etapa 1. A fase2 reutilizou as Evidence existentes e acrescentou leitura seletiva de scripts/fontes/temas e interação autorizada na sessão aberta. [Testes de UI](fase2/05-testes-na-mesa.md) usaram PowerShell/.NET/Win32, com aprovações do executor para a janela delimitada fora do sandbox; não REA runtime. O usuário autorizou explicitamente a mesa Creator's Game, sem fornecer credenciais. Nenhuma configuração de REA/Codex foi alterada e nenhum cliente foi relançado.

Data de referência: 15/10/2026, America/Sao_Paulo. Host Windows x64; shell PowerShell; Node.js v22.23.2; REA 6.5.0; skill instalada versão 42, catálogo com 207 ferramentas. Os caminhos do workspace, da instalação e da skill foram confirmados por leitura do filesystem.

Foram procurados `AGENTS.md` em `C:\`, `C:\Users`, `C:\Users\João`, `C:\Users\João\Documents` e no workspace; nenhum existia nesses locais. Instruções de outros projetos e resultados antigos não selecionam alvos para esta investigação. A skill foi lida em `C:\Users\João\.agents\skills\reverse-engineer-anything\SKILL.md`, incluindo as referências de recuperação e de artefatos nativos.

O launcher existente está em `C:/Users/João/AppData/Local/npm-cache/_npx/ad9a3fdbcad992c6/node_modules/rea-agents/scripts/rea.mjs`. Não houve download, atualização, instalação ou alteração de configurações. O primeiro comando via `npx` encontrou a política de execução do wrapper PowerShell; a chamada direta ao launcher público por Node resolveu esse problema. O alias Python disponível não pôde executar; a análise usou Node e APIs .NET existentes.

O `doctor` confirmou registro Codex alinhado, mas isso não conectou ferramentas ao chat. [Diagnóstico preservado](evidencias/rea-doctor.json) inclui limitações de providers: Ghidra não configurado no ambiente desse processo, Hopper ausente e falha do autocontrole nativo Windows. Há Ghidra/JDK de outra investigação no workspace; sua existência não comprova disponibilidade nesta sessão. Não foi necessária descompilação para responder ao inventário inicial, nem foi tentada a abertura nativa do Firecast. Não se trata de uma rejeição automática de aprovação.

[rea-sessao.mjs](scripts-analise/rea-sessao.mjs) iniciou um subprocesso MCP local, leu `tools/list` e `binary_session`, abriu somente `resource.zip`, chamou `inspect_artifact` e `close_binary`. O schema efetivo está em [rea-tools-schema.json](evidencias/rea-tools-schema.json). O registro não foi reinstalado. A análise PE é portátil e não usa disassembler.

Reprodução, a partir da raiz do workspace:

```powershell
node docs-firecast/scripts-analise/inventario.mjs
node docs-firecast/scripts-analise/rea-estatico.mjs
node docs-firecast/scripts-analise/formularios.mjs
node docs-firecast/scripts-analise/assets-pe.mjs
node docs-firecast/scripts-analise/rea-sessao.mjs archive
```

Os scripts de ZIP estão descritos em [formatos](05-dados-e-formatos.md). `rea-estatico.mjs` reutiliza saídas existentes para não repetir análises idênticas. Os scripts de inventário sobrescrevem suas próprias saídas: arquive os resultados atuais antes de investigar outra versão. O caminho da instalação e o launcher são explícitos, não descoberta automática de alvos.

O inventário lê arquivos distribuídos recursivamente, exceto `LogErrors`. Não lê conversas, tokens, perfis externos ou bancos pessoais. Os originais não são escritos. Hashes e tempos foram coletados de instalação viva, sem snapshot atômico. A reconferência final detecta alterações entre as duas leituras, mas não prova imutabilidade contínua.

Classes de evidência: confirmado estático = bytes ou estrutura diretamente interpretados; confirmado visual = controle visível nos anexos; inferido = interpretação apoiada nesses dados; não verificado = exige leitura adicional ou experimento. Um nome de recurso não prova que a funcionalidade está acessível. Texto de script distribuído comprova o que ele contém, não execução no cliente.

Nenhum pseudocódigo foi produzido, nenhum código original completo foi recuperado e nenhum pacote foi executado. Recursos não foram extraídos para reutilização sem licença identificada. Os anexos pessoais foram descritos sem transcrever seus conteúdos identificadores.
