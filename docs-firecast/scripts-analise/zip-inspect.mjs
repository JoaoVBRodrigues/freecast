// Reports small, selected textual/header observations only. Never executes target code.
import {readZip,sha256} from './zip-inventory.mjs';
import fs from 'node:fs';
const r=readZip(process.argv[2]);
if(process.argv.includes('--names')){console.log(r.members.map(m=>m.id+' '+m.name).join('\n'));process.exit();}
const selected=process.argv.slice(3).filter(x=>!x.startsWith('--'));
for(const m of r.members.filter(m=>selected.includes(m.name))){const b=m.payload;console.log(JSON.stringify({id:m.id,name:m.name,size:m.size,sha256:m.sha256,headerHex:b.subarray(0,32).toString('hex'),preview:/\.(xml|txt)$/.test(m.name)?b.toString('utf8').slice(0,1400):null},null,2));}
