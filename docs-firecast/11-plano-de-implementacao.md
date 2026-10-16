# Plano de implementação com critérios de avanço

## Objetivo e recorte inicial

A meta é uma aplicação independente com alta paridade de interface e comportamento dentro do escopo funcional acordado. O primeiro recorte jogável serve para testar a arquitetura com duas outras pessoas; ele não substitui a meta de fidelidade completa. Esta entrega é um plano: nenhum aplicativo é implementado aqui.

S01–S07 demonstram estados de interface, não transições, regras completas ou APIs. A implementação deve manter uma matriz que associe cada requisito a uma captura, evidência estática/runtime autorizada ou decisão própria explicitamente aceita. Recursos somente sugeridos por rótulos não podem ser declarados equivalentes antes de verificar seu comportamento. A [arquitetura proposta](09-arquitetura-proposta.md) define uma base Laravel/Vue/Inertia/Reverb, com um banco e fila de banco; a documentação do [Inertia](https://inertiajs.com/) sustenta a integração com o servidor, e a do [Reverb](https://laravel.com/framework/docs/reverb) descreve seu papel WebSocket.

## Etapas e dependências

| Etapa | Dependências | Entrega concreta | Critério verificável para avançar |
|---|---|---|---|
| G0 — Requisitos e evidências | Catálogo S01–S07 | Matriz de telas, ações, estados, papéis e desconhecidos; cenários sintéticos | Cada item marcado como observado, inferido ou escolha; lacunas que impedem paridade identificadas |
| G1 — Fundação local | G0 para o recorte | Versões compatíveis fixadas, lockfiles, aplicação vazia, SQL, storage privado, sessão, fila, Reverb e comandos de inicialização | Outra pessoa sobe o ambiente pelo README; migrações e rollback testados; login e canal negam acesso indevido |
| G2 — Mesas e identidade | G1 | Perfil, catálogo, criação/entrada de mesa, associação e policies | Três contas distinguem proprietário/mestre/jogador; tentativas com IDs de outra mesa são negadas; favoritos/recentes persistem segundo regra documentada |
| G3 — Estado durável e sincronização | G2 | Comandos idempotentes, sequência por sala, eventos, outbox, retomada e snapshot | Reenvio não duplica; falha/reinício do worker recupera pendências; eventos duplicados ou fora de ordem convergem; auditoria não revela conteúdo privado |
| G4 — Conversa e mídia | G3 | Histórico, compositor estruturado, envio, paginação, imagens e biblioteca básica | Três sessões recebem o mesmo conteúdo autorizado; uploads inválidos são recusados; imagem privada não abre sem acesso; retomada recupera mensagens sem duplicação |
| G5 — Dados e macros declarativas | G4 | Parser limitado, rolagens persistidas, macros pessoais/compartilhadas e editor | Limites e sintaxe testados; resultado único no servidor; repetição da chave preserva resultado; policies cobrem criação/edição/execução |
| G6 — Primeiro recorte jogável | G5 | Início/lista/mesa com painéis básicos e fluxo de sessão de jogo | Anfitrião e dois amigos completam roteiro abaixo; falhas e diferenças registradas; recorte rotulado como parcial |
| G7 — Fichas, combate e layout | G6 + evidências das regras | Atributos, barras, fórmulas declarativas, tracker e persistência do layout | Concorrência não perde mudanças; ciclos de fórmulas recusados; recarregar restaura layout; comportamento comparado às evidências disponíveis |
| G8 — Fechamento de paridade | G7 + requisitos restantes | Estados e fluxos restantes do escopo, ajustes visuais, acessibilidade e compatibilidade acordada | Cada requisito tem teste de aceitação e resultado; diferenças possuem decisão explícita; nenhum fluxo obrigatório continua desconhecido |
| G9 — Operação compartilhada | G6 para piloto; G8 para declaração de paridade | HTTPS/WSS, backup, restauração, observabilidade, retenção, limites e instalação reproduzível | Restaurar banco+arquivos em ambiente limpo; recuperar outbox; acesso de dois amigos funciona no host de teste; rollback de versão ensaiado |

G7 pode ser projetada enquanto o piloto ocorre, mas os módulos dependentes só avançam após seus gates técnicos. Recursos ainda sem evidência podem ser prototipados como escolhas independentes; isso não fecha seu requisito de equivalência. G8 não pode ser aprovado com base apenas no sucesso do recorte inicial.

## Primeiro roteiro com dois amigos

Usar contas, nomes, imagens e mensagens sintéticos. Preparar uma mesa pública de teste e uma privada, com papéis diferentes. Os três navegadores usam o mesmo servidor, banco e serviço WebSocket. Não usar dados pessoais das capturas.

1. Anfitrião cria a mesa; dois amigos entram pelo catálogo e conferem participantes. Um usuário sem associação tenta abrir a mesa privada, mídia e canal privado e recebe negação.
2. Cada pessoa envia texto; uma envia imagem; o grupo percorre o histórico. Conferir confirmação e ordenação, inclusive com duas mensagens simultâneas.
3. Executar expressão de dados e macro declarativa. Repetir o mesmo comando com a mesma chave e verificar que não aparece nova rolagem ou nova mensagem.
4. Desconectar um navegador, enviar mensagens nos outros, reconectar e comparar histórico e cursor. Reiniciar o worker entre commit e entrega e verificar recuperação sem duplicatas visíveis.
5. Editar o mesmo recurso em duas sessões; uma alteração deve receber conflito de revisão, com recuperação clara. Remover associação e verificar revogação de HTTP, arquivos e assinatura de eventos.
6. Recarregar e reiniciar o servidor; conferir dados persistentes. Registrar o que falhou, latência observada e diferenças visuais em dimensões conhecidas.

Passar esse roteiro permite usar o recorte em um piloto. Não prova equivalência da linguagem de macros, regras de combate, plugins, música/SFX, layout completo ou comportamento das abas privadas.

## Testes por risco real

Testes de domínio cobrem parser de dados, fórmulas, ciclos, permissões e idempotência. Testes de integração SQL verificam atomicidade de comando/evento/outbox, bloqueio da sequência, concorrência, snapshot e recuperação após falha. Usar o banco escolhido para os cenários de bloqueio; substituí-lo em memória não valida essa parte.

Testes HTTP verificam validação, CSRF, autorização por sala e downloads privados. Cenários de navegador verificam envio otimista, reconexão, paginação e atalhos implementados. Comparações visuais usam conteúdo sintético e dimensões registradas; medidas originais ainda não verificadas não devem virar tolerâncias numéricas inventadas. Testes de acesso incluem canais privados e de presença, que exigem autorização no servidor. [Broadcasting do Laravel](https://laravel.com/framework/docs/broadcasting).

Para arquivos, verificar acesso a referências de outra sala, tipo real, limites, órfãos e restauração. O armazenamento usa um disk privado local com configuração reproduzível. [Filesystem do Laravel](https://laravel.com/framework/docs/filesystem). Medir carga com o perfil do piloto antes de introduzir Redis ou serviços adicionais.

## Bloqueios de evidência para alta paridade

| Área | O que as capturas não resolvem | Evidência necessária para fechar |
|---|---|---|
| Dados | Gramática, precedência, modificadores, erros e visibilidade | Casos de teste autorizados e resultados ou documentação/artefatos relevantes |
| Macros | Linguagem, APIs, limites e permissões | Editor e exemplos sintéticos; inspeção estática e execução autorizada quando necessária |
| Conversa | Tipos de mensagem, edição, remoção, privacidade e histórico | Cenários com três contas de teste e registro das transições |
| Biblioteca/imagens | Formatos, estrutura, compartilhamento e limites | Casos de criação/leitura e amostras não pessoais |
| Participantes/fichas | Significado das barras, fórmulas, edição e papéis | Esquemas ou cenários demonstrativos; S01 mostra apenas apresentação |
| Combate/layout | Algoritmo de turnos, arraste, reset e persistência | Sequências de interação autorizadas e estados antes/depois |
| Catálogo/perfil | Filtros, regras de vagas, recomendação e presença | Fluxos e critérios observáveis; não inferir algoritmo dos cartões |
| Plugins e áudio | Contratos e execução dos comandos visíveis no menu | Investigação dedicada; não executar plugins existentes nesta fase |

Quando uma evidência não puder ser obtida, registrar o requisito como parcial ou não resolvido e explicitar uma decisão própria. Uma regra inventada não se torna regra original porque parece plausível.

## Scripts e compatibilidade futura

Não executar plugins existentes durante a implementação inicial. Primeiro concluir macros declarativas e fórmulas com limites verificáveis. Se compatibilidade de scripts entrar no escopo, abrir etapa separada após identificar formato, linguagem e APIs: especificar contratos, construir executor isolado, impor limites e testar encerramento e acesso negado a rede/arquivos. A presença de arquivos de runtime ou DLLs não é suficiente para definir uma API compatível.

Compatibilidade completa de um ecossistema de plugins exige seu próprio inventário e critérios de aceitação; não é consequência automática de reproduzir S01–S07. Recursos de música/SFX e outros comandos do menu também precisam de requisitos próprios antes de fechar G8.

## Registro de conclusão

A cada gate, guardar versão fixada, commit, cenário, resultado, evidência e lacunas. O checklist final deve distinguir paridade visual, funcional e de formatos/integrações. A meta de alta paridade só é considerada concluída quando todos os requisitos obrigatórios do escopo estiverem verificados; o piloto e os módulos iniciais permanecem marcos intermediários.
