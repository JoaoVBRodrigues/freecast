import {readZip} from './zip-inventory.mjs';
import fs from 'node:fs';
const outer=readZip(process.argv[2]);
const results=[];
for(const parent of outer.members.filter(m=>m.extension==='.rpk')){
 const nested=readZip(parent.payload),members=nested.members.map(({payload,...m})=>({...m,id:parent.id+'-'+m.id,container:parent.name}));
 results.push({evidenceId:'ZIP-RPK-'+String(results.length+1).padStart(2,'0'),parentId:parent.id,parent:parent.name,archive:nested.archive,members});
 const manifest=nested.members.find(m=>m.name==='module.xml');const t=manifest?.payload.toString('utf8')||'';
 const value=tag=>t.match(new RegExp('<'+tag+'>([^<]*)</'+tag+'>'))?.[1]||null;
 const attrs=t.match(/<module ([^>]+)>/)?.[1]||'';
 const summary={parent:parent.name,memberCount:members.length,id:value('id'),version:value('version'),name:value('name'),description:value('description'),author:value('author'),sdkVersion:attrs.match(/sdkVersion="([^"]+)"/)?.[1],sdkApiVersion:attrs.match(/sdkApiVersion="([^"]+)"/)?.[1],sdkMinorApiVersion:attrs.match(/sdkMinorApiVersion="([^"]+)"/)?.[1],manifestId:members.find(m=>m.name==='module.xml')?.id,manifestSha256:manifest?.sha256};
 results.at(-1).manifestSummary=summary;console.log(JSON.stringify(summary));
}
fs.writeFileSync(process.argv[3],JSON.stringify({evidenceId:'ZIP-002',method:'same read-only parser; nested ZIP payloads decompressed in memory only; offsets relative to uncompressed RPK',packages:results},null,2));
