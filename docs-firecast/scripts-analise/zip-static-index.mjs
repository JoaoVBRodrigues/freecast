// Syntax-pattern inventory, not a language parser or execution trace.
import {readZip} from './zip-inventory.mjs';import fs from 'node:fs';
const r=readZip(process.argv[2]), findings=[];
for(const p of r.members.filter(m=>m.extension==='.rpk')){
 const z=readZip(p.payload),modules=[],licenses=[];
 for(const m of z.members){
  if(!/\.(lua|lfm|xml|md|txt|lang|dlua|xlua)$/.test(m.name))continue;
  const t=m.payload.toString('utf8');const textual=!m.payload.subarray(0,4).equals(Buffer.from([27,76,117,97]));
  if(/license|licen[cç]|copyright|permission is hereby|redistribut/i.test(t)) licenses.push({member:m.name,sha256:m.sha256,note:'keyword hit only; grant and scope not established'});
  if(textual){const requires=[...t.matchAll(/require\s*(?:\(\s*)?["']([^"']+)["']/g)].map(x=>x[1]);const events=[...t.matchAll(/(?:event\s+name|on[A-Z]\w*)\s*=\s*["']([^"']{1,80})["']/g)].map(x=>x[1]);const api=[...t.matchAll(/\b(Firecast|RRPG|NDB|ndb|GUI|gui|Internet|internet|Dialogs|dialog|JSON|json|System|system|Scene|scene)\.([A-Za-z_]\w*)/g)].map(x=>x[0]);
   if(requires.length||events.length||api.length)modules.push({member:m.name,sha256:m.sha256,requires:[...new Set(requires)],events:[...new Set(events)].filter(x=>/^on[A-Z]/.test(x)),api:[...new Set(api)].sort()});
  }
 }
 findings.push({parent:p.name,parentSha256:p.sha256,licenseKeywordHits:licenses,modules});
}
const summaries=r.members.filter(m=>['.xml','.style','.lang','.bin'].includes(m.extension)).reduce((a,m)=>{const k=m.extension+' header '+m.payload.subarray(0,4).toString('hex');a[k]=(a[k]||0)+1;return a},{});
const output={evidenceId:'ZIP-003',method:'bounded regular-expression syntax scan of text members, no execution, no original code exported',outerHeaderCounts:summaries,packages:findings};fs.writeFileSync(process.argv[3],JSON.stringify(output,null,2));console.log(JSON.stringify({outerHeaderCounts:summaries,packages:findings.map(p=>({parent:p.parent,modules:p.modules.length,licenseKeywordHits:p.licenseKeywordHits}))},null,2));
