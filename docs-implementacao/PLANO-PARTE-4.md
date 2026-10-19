
# Parte 4A — personagens e combate

- [x] Consultar docs-firecast e perfil Backend Architect (name confirmado).
- [x] Schema aditivo; projeções privadas, eventos de invalidação, comandos idempotentes.
- [x] CRUD, retrato privado, recursos/PV e atribuição validados.
- [x] Tokens e tracker integrados aos painéis existentes.
- [x] PostgreSQL freecast_test e storage isolado antes de uploads.
- [x] Build oficial e navegador com mestre e dois jogadores, realtime/reconexão.
- [x] Documentar decisões próprias, resultados, limites e retomada antes da 4B.

Entrega utilizável concluída; Parte 4B não iniciada. Resultados e incidente operacional estão em PROGRESSO.md; regras independentes e contrato estão em DECISOES.md e CONTRATO-PARTE-4A.yaml.

## Parte 4B — checklist

- [x] Documentação original e consulta Backend Architect para autorização/persistência.
- [x] Régua local euclidiana, escala e cancelamento.
- [x] Quatro desenhos limitados, persistência e autorização.
- [x] Névoa manual, projeções servidor e invalidações sem IDs de entidades.
- [x] Testes PostgreSQL isolado, CSRF real e Playwright com três contexts.
- [x] Build, capturas próprias e documentação de limites.
- [x] Informar migration pendente no desenvolvimento, sem iniciar 4C.

4B concluída em 19/10/2026. Cinco cenários browser aprovados, suíte backend inicial de 50 testes e revalidação focal final de 10 testes/165 assertions. Build local restaurado e serviços de validação encerrados. Migration `2026_10_19_000000_add_map_tools` confirmada pendente em desenvolvimento por consulta somente leitura. Roteiro visual e limitações em PROGRESSO.md e DECISOES.md; importador dd2vtt permanece na 4C.

## Parte 4C — importação dd2vtt

- [x] Ler continuidade, perfil Backend Architect e contrato estático original.
- [x] Consultar fonte primária do formato legado e definir diferenças próprias.
- [x] Parser limitado, coordenadas centralizadas e fixtures sintéticas.
- [x] Prévia privada/expirável, cancelar e confirmar idempotente com compensação.
- [x] UI, geometria só mestre e duplicação preservada.
- [x] PostgreSQL/storage isolados, falhas, CSRF/browser, regressões e build.
- [x] Guia, capturas, resultados reais e migrations pendentes; parar antes da 4D.

4C concluída em 19/10/2026. Backend inicial 56 testes/537 assertions e focal final 6/134; cinco cenários browser distintos aprovados, incluindo 4C com CSRF419/419/200 e verificação do fundo carregado no canvas. Build oficial aprovado, serviços próprios encerrados e assets locais restaurados. Migrations 4B `2026_10_19_000000_add_map_tools` e 4C `2026_10_19_000001_add_dd2vtt_imports` permanecem pendentes em desenvolvimento, confirmadas por consulta somente leitura. Guia e fixture mínima em IMPORTACAO-DD2VTT.md. Recuperação das contas/mesa anterior continua pendente da confirmação do responsável. 4D não iniciada.
