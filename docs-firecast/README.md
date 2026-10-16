# Investigação do RRPG Firecast

Etapa 1 em 15/10/2026; [fase 2](fase2/README.md) em 16/10/2026 (America/Sao_Paulo), com contratos estáticos e testes na sessão já aberta, autorizada pelo usuário na mesa Creator's Game. Nenhuma aplicação foi implementada. O usuário manteve o Firecast aberto; esta investigação não realizou login nem relançou o cliente.

Leia [resumo](00-resumo-executivo.md), [progresso](PROGRESSO.md) e [contexto para continuação](CONTEXTO-PARA-IMPLEMENTACAO.md). A meta continua sendo alta fidelidade funcional e visual; este inventário não estabelece paridade completa.

| Documento | Conteúdo |
|---|---|
| [01](01-ambiente-e-metodologia.md) | Escopo, ferramentas, reprodução e limites |
| [02](02-inventario-tecnico.md) | Executáveis, arquiteturas, versões e dependências |
| [03](03-funcionalidades-e-regras.md) | Cobertura dos módulos e contratos ainda desconhecidos |
| [04](04-telas-e-fluxos.md) | Oito referências visuais, controles e fluxos |
| [05](05-dados-e-formatos.md) | ZIP, RPK, formulários e formatos |
| [06](06-fichas-scripts-e-plugins.md) | Linguagens, APIs e compatibilidade |
| [07](07-comunicacao-e-sincronizacao.md) | Limites da evidência de comunicação |
| [08](08-assets-e-reutilizacao.md) | Inventários e situação de reutilização |
| [09](09-arquitetura-proposta.md) | Proposta independente Laravel |
| [10](10-matriz-de-paridade.csv) | Requisitos candidatos e critérios de aceitação |
| [11](11-plano-de-implementacao.md) | Recorte inicial, dependências e meta completa |
| [12](12-lacunas-e-proximos-experimentos.md) | Experimentos necessários |

[Registro de 29 achados](evidencias/achados.json) distingue confirmação estática, observação visual e inferência. IDs `ev_…` são Evidence do REA; IDs `LOCAL-…`, `ZIP-…`, `FORM-…`, `F2-*`, `UI-*` e `S…` são referências locais ou visuais, não Evidence REA.

S01–S08 permanecem como anexos da conversa; S09–S16 são recortes locais sanitizados e rastreáveis. [capturas/README.md](capturas/README.md) distingue os dois conjuntos. Assets não foram copiados para redistribuição. Todos os relatórios são locais; nenhum conteúdo foi publicado.
