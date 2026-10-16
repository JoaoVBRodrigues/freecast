import fs from 'node:fs';
import path from 'node:path';
import {spawnSync} from 'node:child_process';
const cli='C:/Users/João/AppData/Local/npm-cache/_npx/ad9a3fdbcad992c6/node_modules/rea-agents/scripts/rea.mjs';
const out=path.resolve('docs-firecast/evidencias');fs.mkdirSync(out,{recursive:true});
const jobs=[['rea-doctor',['doctor','--client','codex','--json']],['rea-pe-firecast',['inspect-pe-resources','C:/Users/João/AppData/Local/Firecast/Firecast.exe','--max-file-bytes','536870912','--format','json']]];
for(const [id,args] of jobs){const file=path.join(out,id+'.json');if(fs.existsSync(file)){console.log(id+': existing; not repeated');continue;}const r=spawnSync(process.execPath,[cli,...args],{encoding:'utf8',maxBuffer:64*1024*1024,timeout:120000,windowsHide:true});fs.writeFileSync(file,r.stdout||'{}');if(r.stderr)fs.writeFileSync(path.join(out,id+'.stderr.txt'),r.stderr);console.log(JSON.stringify({id,exit:r.status,error:r.error?.message,bytes:r.stdout?.length}));}
