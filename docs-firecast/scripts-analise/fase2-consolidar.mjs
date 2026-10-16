import fs from 'node:fs';
const root='docs-firecast/';
const old=JSON.parse(fs.readFileSync(root+'evidencias/achados.json'));
const additions=[];
const add=(id,source,procedure,result,classification,limitations)=>additions.push({id,phase:'fase2',source,procedure,result,classification,limitations});
add('F21','evidencias/fase2/runtime.json#UI-001','Observar título e perfil da mesa autorizada; cruzar caminho do processo e hash em disco.','Cliente exibe8.13, mesa confirmada; arquivo do caminho de processo tem mesmo hash do inventário.','confirmado','Não é hash de memória; nenhuma nova autenticação; origem E01 é sessão existente autorizada.');
add('F22','evidencias/fase2/runtime.json#UI-002','Enviar mensagem sintética na mesa autorizada e observar compositor/histórico.','Texto simples aparece no chat e compositor fica vazio.','confirmado','Uma conta; sem validar persistência ou recebimento por outra sessão.');
add('F23','evidencias/fase2/runtime.json#UI-003,UI-005','Enviar /r 1d1 + 2 e soma dos sete tipos; aguardar saída.','Caso determinístico total3; sete componentes2,1,5,7,7,15,100 total137; animação3D observada.','confirmado','Não determina parser completo, distribuição aleatória ou autoridade do servidor.');
add('F24','evidencias/fase2/runtime.json#UI-004','Enviar /r 1d1 + 2 * 3 por WM_CHAR.','Erro visual; operador exibido como ponto/bullet desconhecido.','confirmado','Transformação do editor não isolada; não concluir ausência universal de multiplicação.');
add('F25',['evidencias/fase2/runtime.json#UI-006','evidencias/fase2/macros.json'],'Comparar listener distribuído com comando /macros na sessão existente.','Código anuncia /macros; runtime responde comando desconhecido.','confirmado','Causa não determinada; não prova licença, permissão, corrupção ou falha de servidor.');
add('F26','evidencias/fase2/macros.json','node docs-firecast/scripts-analise/fase2-macros.mjs','Três escopos; precedência pessoal mesa > pessoal global > compartilhada; S envia linhas e L compila em coroutine; migração XML identificada.','confirmado','Confirmação estática; precedência de listeners, permissões e carregamento runtime não validados.');
add('F27','evidencias/fase2/ndb.json','node docs-firecast/scripts-analise/fase2-ndb.mjs','Fachada NDB prioriza filho na leitura; nil remove atributo/filhos; XML e transações dependem do host; consumidores salvam sheet.xml/tokens.xml em UTF8.','confirmado','Schema XML, durabilidade, atomicidade e sincronização permanecem desconhecidos.');
add('F28','evidencias/fase2/uvtt.json','node docs-firecast/scripts-analise/fase2-uvtt.mjs','Recuperados campos e transformações de mapa, paredes, portais e luzes; guarda scene.isGM e limite local128MiB no wrapper FireDrive.','confirmado','Contrato estático do importador1.2, não importação executada ou limite do servidor/NDB; uploads e geometria não atômicos no fluxo analisado.');
add('F29','evidencias/fase2/fontes-temas.json','node docs-firecast/scripts-analise/fase2-fontes-temas.mjs','24TTF,141constantes,30classes: Roboto padrão/Lato títulos/Inconsolata console;66cores6hex catalogadas; declarações por fonte registradas.','confirmado','Não mede fonte final renderizada; metadados não estabelecem redistribuição; alpha8hex não interpretado.');
old.findings=old.findings.filter(f=>!additions.some(a=>a.id===f.id)).map(f=>({...f,phase:f.phase??'etapa1'})).concat(additions);old.scope='Etapa1 e fase2: estática, UI autorizada em uma conta e lacunas';fs.writeFileSync(root+'evidencias/achados.json',JSON.stringify(old,null,2));
const csvPath=root+'10-matriz-de-paridade.csv';const source=fs.readFileSync(csvPath,'utf8');const backup=root+'evidencias/fase2/paridade-antes.csv';if(!fs.existsSync(backup))fs.writeFileSync(backup,source);
const rows=source.trimEnd().split('\n').map(line=>[...line.matchAll(/"((?:[^"]|"")*)"/g)].map(m=>m[1].replaceAll('""','"')));
for(const r of rows){if(r.length!==7)throw Error('Unexpected CSV format');switch(r[0]){
 case'PAR09':r[2]+=';F22';r[5]='parcial; texto enviado em um cliente, sem persistência/mídia multiusuário';break;
 case'PAR11':r[2]+=';F23;F24';r[5]='parcial; d1+2, sete tipos e erro observados, sem gramática completa';break;
 case'PAR12':r[2]+=';F25;F26';r[5]='parcial; contrato estático, /macros não reconhecido no runtime';break;
 case'PAR14':case'PAR15':case'PAR20':case'PAR22':r[2]+=';F27';break;
 case'PAR19':case'PAR26':r[2]+=';F28';break;
 case'PAR25':r[2]+=';F29;S09-S16';r[5]='parcial; tema e fontes declarados, recortes runtime medidos';break;
}}
for(const r of rows)r[2]=[...new Set(r[2].split(';'))].join(';');
const q=s=>'"'+s.replaceAll('"','""')+'"';fs.writeFileSync(csvPath,rows.map(r=>r.map(q).join(',')).join('\n')+'\n');
console.log(JSON.stringify({findings:old.findings.length,parityRows:rows.length-1}));
