# Comunicação e sincronização

Não foram observados pacotes, endpoints em uso, formatos de frames, autenticação ou reconexão. Não houve captura HAR/SAZ/PCAP. Na fase2, a sessão já aberta foi usada com autorização para [enviar texto e rolagens](fase2/05-testes-na-mesa.md), observando a resposta visual em um cliente. Isso não identifica transporte, confirmação do servidor, persistência nem recebimento por terceiros; o protocolo permanece não verificado.

`LOCAL-INVENTORY-001` confirma bibliotecas distribuídas Lua, OpenSSL e `fcwebrtc_win_x64.dll`; as importações estáticas normais do cliente estão em [02](02-inventario-tecnico.md). Os nomes das bibliotecas e declarações de API em scripts são indícios de capacidades, não prova de transporte, codec, criptografia em uso ou arquitetura do servidor.

S01 mostra participantes e conversa em uma mesa; S03–S05 exibem contagens/indicadores online; S02 mostra players. São estados visuais de aplicações possivelmente conectadas no momento da captura. Não foram medidos frequência, origem, atraso ou consistência dessas informações. Formulários de chat privado individual/grupo confirmam estruturas locais, sem estabelecer as regras de distribuição.

| Questão | Estado | Evidência/experimento necessário |
|---|---|---|
| Backend de conta/sala e sessão | Não verificado | E02/E03: conta de teste, registrar apenas estados; segredos excluídos |
| HTTP, TCP, WebSocket ou outro transporte | Não verificado | E08: captura autorizada mínima em mesa sintética; identificar handshakes sem publicar credenciais |
| Autoridade dos resultados de dados | Não verificado | E04: dois clientes, resposta e histórico; depois desconexão controlada |
| Ordem, duplicatas e confirmação de mensagens | Não verificado | E08: enviar IDs sintéticos, intercalar dois autores, observar reentrada |
| Privacidade de mensagens | Não verificado | E03/E04: mestre, jogador destinatário e não destinatário; testar nova sessão/histórico |
| Persistência de campos e conflitos | Não verificado | E05/E08: editar o mesmo campo e campos diferentes em clientes distintos |
| Mídia: cópia, URL ou stream | Não verificado | E06/E07: arquivo sintético identificável, remoção e reentrada |
| Recuperação de conexão | Não verificado | E09: desconectar e restaurar apenas o cliente de teste; medir indicador, retentativa e recuperação |

E08 não precisa começar por interceptação TLS: primeiro comparar estados e registrar tráfego de teste disponível por mecanismo suportado. Não contornar autenticação, alterar trust store, quebrar pinning nem acessar mensagens de terceiros. Capturas brutas potencialmente sensíveis não entram nos relatórios; guardar somente projeções sanitizadas e sua proveniência.

Para a futura aplicação, a estratégia transação/outbox, cursores, idempotência e canal autorizado está descrita em [09](09-arquitetura-proposta.md). É proposta de engenharia independente, não protocolo recuperado do Firecast. Reproduzir os fluxos e regras não requer interoperar com os servidores originais.
