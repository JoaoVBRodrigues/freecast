# Progresso — atualizado em 16/10/2026

**Fase 2 avançou com contratos estáticos e testes reais na mesa autorizada. A investigação continua parcial; nenhuma aplicação implementada.**

Resultados novos:

- Usuário autorizou usar a sessão já aberta na mesa **Creator's Game**. Foram confirmados nome da mesa, título **Firecast 8.13** e identidade do arquivo do processo em disco. O aplicativo permaneceu aberto.
- Mensagem sintética enviada; `/r 1d1 + 2` retornou **3**. Uma soma com d4/d6/d8/d10/d12/d20/d100 retornou componentes **2,1,5,7,7,15,100**, total **137**, com animação 3D.
- Uma expressão com `*` gerou erro e exibiu o operador como ponto/bullet; a transformação do compositor ainda precisa ser isolada. Não é conclusão geral sobre multiplicação.
- Divergência encontrada: `/macros` está anunciado no handler distribuído, mas a sessão atual responde **comando desconhecido**. Não foram alterados plugins ou configurações para tentar corrigir isso.
- Recuperados três escopos e precedência de macros, normalização, execução simples/Lua e migração. NDB teve fachada, conversões, observadores, transações e fronteira de exportação XML documentados; schema real ainda não obtido.
- Especificados os campos e transformações usados pelo importador UVTT, incluindo guarda cliente de mestre e limite local de upload do wrapper. Importação não foi executada.
- Confirmados metadados de **24 TTF**, **141 constantes** e **30 classes** de tema. Gerado catálogo de **66 cores** sem copiar fontes/assets originais; licenças permanecem declarações a verificar.
- Cinco relatórios em [fase2/](fase2/README.md), evidências em [evidencias/fase2/](evidencias/fase2/README.md), **oito recortes sanitizados S09–S16** com hashes/dimensões e registro total de **29 achados**. Matriz de 27 requisitos atualizada, sem declarar paridade completa.

Efeitos na mesa: uma mensagem sintética e duas rolagens válidas ficaram no histórico; duas respostas de erro foram observadas. Nada foi apagado do histórico. A aba adicional criada na navegação foi fechada, deixando a mesa/compositor vazios de rascunho. Configurações, macros, plugins e arquivos distribuídos não foram escritos. Capturas integrais transitórias foram removidas; só recortes sem contas/avatares/conversas anteriores foram preservados.

O usuário informou em 16/10/2026 que macros exigem Gold/gasto e há restrições também para criar mapas e pastas. Os critérios comerciais e sua relação causal com a resposta de /macros não foram verificados independentemente. Fonte: [USR-001](evidencias/relato-gold.json). Os testes afetados ficam pendentes de acesso, sem compra ou alteração de licença nesta investigação.

Próximo ponto de retomada: avançar nos recursos acessíveis e obter ficha/export XML próprios quando disponíveis. Não priorizar reparação de macros como provável defeito técnico. Os contratos estáticos recuperados continuam úteis. Na aplicação independente, a proposta é incluir macros, mapas e pastas no núcleo sem cobrança por desbloqueio, preservando permissões por papel. Persistência, reconexão, segunda conta e regras de servidor continuam pendentes.

Leia [testes realizados e limites](fase2/05-testes-na-mesa.md), [experimentos](12-lacunas-e-proximos-experimentos.md) e [contexto atualizado](CONTEXTO-PARA-IMPLEMENTACAO.md). [verificacao.json](evidencias/verificacao.json) registra a conferência atual dos 28 originais e integridade documental. Execução da UI usou .NET/Win32, não REA runtime; as Evidence REA anteriores foram reutilizadas.

## Histórico da etapa 1 — 15/10/2026

O registro abaixo descreve a rodada inicial; suas pendências e restrições de execução foram parcialmente superadas pela fase 2 acima.

**Etapa 1 encerrada com cobertura estática e visual parcial. Investigação funcional completa e reprodução fiel ainda não concluídas. Nenhuma aplicação implementada.**

Concluído nesta rodada:

- Leitura do pedido anexado e da skill REA42; caminhos confirmados; nenhum AGENTS.md nos ancestrais aplicáveis pesquisados.
- Inventário de 28 arquivos da instalação, SHA-256, versões Windows, assinaturas PE, arquiteturas, importações normais e marcadores direcionados. `LogErrors` excluído.
- REA6.5.0 real: doctor, schema de 207 ferramentas, sessão inicial sem alvo, inventário PE portátil e abertura/inspeção/fechamento do ZIP. Resultados completos preservados, sem setup ou configuração nova.
- 175 recursos PE inventariados; 73 streams TPF0 com propriedades decodificadas; cinco RPK e 2.163 membros externos/internos com hashes/CRC; distinção XML textual e TPF0.
- Leitura seletiva de manifests, APIs e regras de tracker/ficha; nenhuma execução de scripts ou pseudocódigo apresentado como fonte original.
- Catálogo S01–S08; 628 registros de assets, 515 imagens, zero payloads extraídos. Sem licença visual confirmada; catálogo de extrações vazio e rastreável.
- Documentos 00–12, índice, contexto de continuação, registro de achados, matriz de 27 requisitos e scripts de análise. Proposta Laravel independente, sem implementação.

Pendências que impedem alta paridade:

- Ligar versão visível ao hash local por execução controlada e obter capturas locais sanitizadas com dimensões/hash.
- Entrada, criação/entrada de mesa, ações por papel, macros, resultados de dados e erros em contas/sala sintéticas.
- Schema real de exportação de ficha/NDB, cache/configuração/snapshot local e persistência; ainda não selecionados.
- Camadas, tokens, áudio/voz, importação e recuperação; somente recursos/scripts/menus encontrados até agora.
- Autoridade e regras do servidor, conflitos, ordem de mensagens, privacidade e reconexão; nenhum tráfego observado.
- Licenças individuais, fontes, dimensões visuais medidas e substitutos autorizados.

Limitações de ferramentas: o chat não expunha REA MCP; o subprocesso local funcionou para recursos/ZIP. Doctor apontou provider nativo sem configuração nesse ambiente e limitação do autocontrole Windows. Ghidra não foi aberto para este alvo. Não houve rejeição de aprovação automática nem necessidade de instalar ferramentas. `npx.ps1` foi bloqueado pela política PowerShell; o launcher público existente via Node permitiu seguir sem mudar a política. Python indisponível não impediu o inventário.

Validação: ver [verificacao.json](evidencias/verificacao.json) para reconferência dos hashes dos 28 originais, sintaxe dos scripts Node, JSONs e links locais. [zip-verificacao.json](evidencias/zip-verificacao.json) cobre hashes/CRC do pacote. Essas verificações não substituem testes funcionais do Firecast.

Ponto exato de retomada: [E01](12-lacunas-e-proximos-experimentos.md) e depois E02–E04. Reutilizar os inventários atuais e o [contexto](CONTEXTO-PARA-IMPLEMENTACAO.md); não repetir análises idênticas nem tratar o recorte inicial da implementação como paridade completa.
