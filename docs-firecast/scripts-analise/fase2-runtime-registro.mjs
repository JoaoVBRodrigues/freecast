// Consolida observações manuais das saídas de ferramenta e capturas selecionadas.
// Não executa o Firecast nem repete as ações. Screenshots são verificadas visualmente.
import fs from 'node:fs';import crypto from 'node:crypto';
const sha=b=>crypto.createHash('sha256').update(b).digest('hex');
const root='docs-firecast/';
const definitions=[
 ['S09','S09-versao-runtime.png',[34,10,140,24],'Barra de título: Firecast 8.13'],
 ['S10','S10-mesa-teste.png',[450,205,450,82],"Campo Nome da mesa: Creator's Game"],
 ['S11','S11-chat-texto-teste.png',[548,823,713,33],'Mensagem sintética enviada'],
 ['S12','S12-rolagem-deterministica.png',[660,820,605,33],'Expressão 1d1 + 2 = 3, decomposição [1] + 2'],
 ['S13','S13-erro-expressao.png',[480,820,795,35],'Falha de interpretação, caractere exibido como ponto/bullet'],
 ['S14','S14-rolagem-multiplos-dados.png',[659,796,615,28],'Soma de sete tipos de dados, primeira linha'],
 ['S15','S15-rolagem-continuacao.png',[548,824,726,28],'Continuação da decomposição da soma'],
 ['S16','S16-macros-comando-desconhecido.png',[480,820,795,35],'Comando desconhecido: macros']
];
const captures=definitions.map(([id,name,region,result])=>{const b=fs.readFileSync(root+'capturas/'+name);if(b.subarray(0,8).toString('hex')!=='89504e470d0a1a0a')throw Error('not PNG');const width=b.readUInt32BE(16),height=b.readUInt32BE(20);if(width!==region[2]||height!==region[3])throw Error('dimension mismatch');return{id,file:'capturas/'+name,sha256:sha(b),bytes:b.length,width,height,regionInWindow:region,result,classification:'confirmado por observação visual'};});
const exe=fs.readFileSync('C:/Users/João/AppData/Local/Firecast/Firecast.exe');
const observations=[
 {id:'UI-001',source:['S09','S10','janela-host.json'],procedure:'Selecionar Configurações da mesa, aguardar carregamento e observar nome; ler caminho do processo e hash do arquivo em disco.',result:'Firecast 8.13 em sessão já aberta na mesa autorizada; arquivo no caminho do processo coincide com inventário.',classification:'confirmado',limitations:'Hash do arquivo em disco não é hash da imagem de memória; não foi efetuado login ou relançamento.'},
 {id:'UI-002',source:['S11'],input:'[REA TESTE] F2-CHAT-001 texto simples',procedure:'Clicar compositor, enviar WM_CHAR para texto e WM_KEYDOWN/UP Enter; observar histórico e compositor.',result:'Mensagem aparece no histórico e compositor volta vazio.',classification:'confirmado',limitations:'Um cliente/conta; não prova recebimento por terceiros nem persistência após reentrada.'},
 {id:'UI-003',source:['S12'],input:'/r 1d1 + 2',procedure:'Enviar expressão no compositor da mesa autorizada.',result:{expression:'1d1 + 2',total:3,dice:[1],modifier:2},classification:'confirmado',limitations:'Caso único, sem provar gramática completa, autoridade ou distribuição estatística.'},
 {id:'UI-004',source:['S13'],input:'/r 1d1 + 2 * 3',procedure:'Enviar texto com caractere ASCII * via WM_CHAR.',result:'Erro Falha ao interpretar a expressão; apresentação exibe o operador como ponto/bullet e acusa caractere desconhecido.',classification:'confirmado',limitations:'Editor/forma de entrada pode transformar * antes do parser; não concluir que multiplicação é universalmente ausente. Repetir por teclado/colagem e inspecionar texto efetivo.'},
 {id:'UI-005',source:['S14','S15'],input:'/r 1d4 + 1d6 + 1d8 + 1d10 + 1d12 + 1d20 + 1d100',procedure:'Enviar uma expressão e aguardar animação e resultado final.',result:{total:137,components:[2,1,5,7,7,15,100],sumVerified:2+1+5+7+7+15+100===137,animation3DObserved:true},classification:'confirmado',limitations:'Uma amostra; animação observada em captura transitória não preservada para excluir dados pessoais; só saída matemática foi retida.'},
 {id:'UI-006',source:['S16','macros.json'],input:'/macros',procedure:'Enviar comando anunciado no handler distribuído, sem editar configurações/plugins.',result:'Comando desconhecido: macros; nenhum gerenciador abriu.',classification:'confirmado',limitations:'Causa desconhecida; código empacotado não prova carregamento. Não atribuir a permissão, licença, corrupção ou servidor sem evidência.'},
 {id:'UI-007',source:['janela-sandbox.json','janela-host.json'],procedure:'EnumWindows por PID exato, UIAutomation NameProperty igual ao nome autorizado; fora do sandbox somente após aprovação do executor.',result:'Sandbox não expôs janela; host expôs HWND 3016692; UIAutomation não encontrou nome da mesa; captura e cliques usaram Win32 com contexto DPI por thread.',classification:'confirmado',limitations:'Não é REA runtime; tecnologia observada .NET/Win32. Sonda de nome não exaure a árvore de acessibilidade.'}
];
const actions=[
 ['RUNTIME-001','03:02:46.499Z','Tentativa inicial de selecionar configurações abriu menu + por virtualização DPI; sem escrita'],
 ['RUNTIME-002','03:03:44.486Z','Selecionada opção configurações; aba adicional criada; sem edição'],
 ['RUNTIME-003','03:05:08.425Z','Retorno à aba Mesa'],
 ['RUNTIME-004','03:05:43.330Z','Texto sintético inserido e conferido antes de envio'],
 ['RUNTIME-005','03:06:07.110Z','Mensagem sintética enviada'],
 ['RUNTIME-006','03:06:36.019Z','Expressão 1d1 + 2 enviada'],
 ['RUNTIME-007','03:07:23.072Z','Expressão com * enviada; erro observado'],
 ['RUNTIME-008','03:07:56.357Z','Soma de sete dados enviada'],
 ['RUNTIME-009','03:09:22.752Z','/macros enviado; comando desconhecido observado'],
 ['RUNTIME-010','03:09:57.173Z','Configurações reabertas para recorte de nome'],
 ['RUNTIME-011','03:10:32.720Z','Fechada somente a aba adicional criada'],
 ['RUNTIME-012','03:10:33.207Z','Retorno à aba Mesa, compositor vazio']
].map(([id,time,action])=>({id,atUtc:'2026-10-16T'+time,action,source:'transcrição das saídas de exec_command observadas nesta conversa, sem log de conteúdo pessoal'}));
const result={id:'F2-RUNTIME-001',collectedAtUtc:new Date().toISOString(),tool:'PowerShell/.NET/System.Drawing/Win32, observação e entradas na janela selecionada; não é Evidence REA',authorization:'Usuário autorizou usar sessão já aberta e mesa Creator\'s Game nesta conversa em 16/10/2026; não inclui outras mesas ou conversas.',target:{path:'C:/Users/João/AppData/Local/Firecast/Firecast.exe',processId:39312,exeSha256:sha(exe),windowPixels:[1938,1038],dpi:'SetThreadDpiAwarenessContext(-4); comparação inicial virtual 1550x830 versus captura física 1938x1038'},captures,observations,actions,persistentEffects:'Uma mensagem sintética e duas rolagens válidas ficaram no histórico da mesa; erros podem existir no histórico do cliente. Não foram apagados. Nenhum arquivo distribuído, configuração, plugin ou macro foi alterado.',privacy:'Somente recortes sem contas, avatares ou histórico anterior são preservados; captura integral transitória removida ao final.',limitations:['Nenhum login/cadastro/conta adicional.','Sem captura de pacotes ou observação do servidor.','Nenhum teste de reconexão ou alteração de papéis.','Recortes excluem parte do layout, por privacidade; não são screenshots de janela inteira.']};
fs.writeFileSync(root+'evidencias/fase2/runtime.json',JSON.stringify(result,null,2));
fs.writeFileSync(root+'capturas/manifesto-fase2.json',JSON.stringify({id:result.id,method:'PrintWindow selected window, exact pixel region, no rescale',windowPixels:result.target.windowPixels,captures},null,2));
console.log(JSON.stringify({id:result.id,captures:captures.length,observations:observations.length,exeSha256:result.target.exeSha256}));
