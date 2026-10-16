// Cliente MCP stdio efêmero usando launcher público instalado; não registra MCP.
import fs from 'node:fs';import {spawn} from 'node:child_process';import {createInterface} from 'node:readline';
const cli='C:/Users/João/AppData/Local/npm-cache/_npx/ad9a3fdbcad992c6/node_modules/rea-agents/scripts/rea.mjs';
const base='docs-firecast/evidencias/';
const child=spawn(process.execPath,[cli,'--mcp'],{stdio:['pipe','pipe','pipe'],windowsHide:true});
const errors=fs.createWriteStream(base+'rea-sessao.stderr.txt');child.stderr.pipe(errors);const pending=new Map();let id=0;
const lines=createInterface({input:child.stdout});lines.on('line',line=>{let m;try{m=JSON.parse(line);}catch{return;}if(pending.has(m.id)){const p=pending.get(m.id);clearTimeout(p.timer);pending.delete(m.id);m.error?p.reject(Error(JSON.stringify(m.error))):p.resolve(m.result);}});
function rpc(method,params){return new Promise((resolve,reject)=>{const seq=++id;const timer=setTimeout(()=>{pending.delete(seq);reject(Error('timeout '+method));},90000);pending.set(seq,{resolve,reject,timer});child.stdin.write(JSON.stringify({jsonrpc:'2.0',id:seq,method,params})+'\n');});}
async function call(name,args={}){const r=await rpc('tools/call',{name,arguments:args});fs.writeFileSync(base+name+'.json',JSON.stringify(r));return r;}
try{const init=await rpc('initialize',{protocolVersion:'2024-11-05',capabilities:{},clientInfo:{name:'firecast-static-inventory',version:'1'}});child.stdin.write(JSON.stringify({jsonrpc:'2.0',method:'notifications/initialized'})+'\n');const listing=await rpc('tools/list',{});fs.writeFileSync(base+'rea-tools-schema.json',JSON.stringify(listing));console.log(JSON.stringify({server:init.serverInfo,tools:listing.tools.length,selectedTools:listing.tools.filter(t=>['open_binary','inspect_artifact','close_binary'].includes(t.name)).map(t=>t.name)}));const session=await call('binary_session');console.log(JSON.stringify({sessionKeys:Object.keys(session),isError:session.isError}));
 if(process.argv[2]==='archive'){
  const opened=await call('open_binary',{path:'C:/Users/João/AppData/Local/Firecast/resource.zip'});
  if(opened.isError)throw Error('open_binary failed; see evidence');
  try{await call('inspect_artifact',{});}finally{await call('close_binary');}
 }
}finally{child.stdin.end();child.kill();lines.close();errors.end();}
