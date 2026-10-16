import {readZip} from './zip-inventory.mjs';import fs from 'node:fs';
const r=readZip(process.argv[2]);
const result={evidenceId:'ZIP-005',method:'selected text syntax observations; no target execution or original source export',archiveSha256:r.archive.sha256,packages:[]};
const queries={
 'rrpginlua.rpk':['FichaBasicaMultiPlataforma.lfm','ConfigDice/metodosExpostos.lua','sdk/ndb.lua','sdk/internet.lua','sdk/firecast.lua','sdk/system.lua','sdk/serpent.dlua','sdk/slaxml.dlua','turnos/CombatTrackerScripts.xml'],
 'DnD5_SDK3.rpk':['module.xml','contadores.xml'],
 'UniversalVTTImporter.rpk':['base64.lua','uvtt.lua'],
 'sceneinlua.rpk':['delaunay.lua'],
};
for(const p of r.members.filter(m=>queries[m.name.split('/').at(-1)])){
 const z=readZip(p.payload),pkg={container:p.name,observations:[]};result.packages.push(pkg);
 for(const m of z.members.filter(m=>queries[p.name.split('/').at(-1)].includes(m.name))){const t=m.payload.toString('utf8');pkg.observations.push({member:m.name,sha256:m.sha256,header:m.payload.subarray(0,12).toString('hex'),functions:[...t.matchAll(/function\s+([A-Za-z_][A-Za-z0-9_.:]*)\s*\(/g)].slice(0,35).map(x=>x[1]),fields:[...new Set([...t.matchAll(/\bfield="([^"]+)"/g)].map(x=>x[1]))].slice(0,35),events:[...new Set([...t.matchAll(/<event\s+name="([^"]+)"/g)].map(x=>x[1]))],licenseIndicators:{MIT:/MIT/.test(t),copyright:/copyright/i.test(t)},trackerMarkers:m.name==='turnos/CombatTrackerScripts.xml'?{masterProperty:t.includes('j.isMestre'),masterGuard:t.includes('if not self:souMestre() then'),clearTurn:t.includes('a.vez = false'),setTurn:t.includes('node.vez = true'),scroll:t.includes('scrollToNode(node)'),narration:t.includes('enviarNarracao(msg)')}:undefined});}
 pkg.nonSdkSourceNames=z.members.filter(m=>!m.name.startsWith('sdk/')&&/\.(lua|lfm|dlua|xlua)$/.test(m.name)).map(m=>m.name);
}
if(process.argv[3])fs.writeFileSync(process.argv[3],JSON.stringify(result,null,2));else console.log(JSON.stringify(result,null,2));
