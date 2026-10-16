// Read-only inspection of bundled macro logic. Never evaluates Lua or writes payloads.
import fs from 'node:fs';
import path from 'node:path';
import {readZip, sha256} from './zip-inventory.mjs';
const root = path.resolve(import.meta.dirname, '..');
const source = JSON.parse(fs.readFileSync(path.join(root, 'inventarios/resource-zip.json'), 'utf8'));
const outer = readZip(source.archive.path);
if (outer.archive.sha256 !== source.archive.sha256) throw Error('Original archive identity changed');
const pkg = outer.members.find(m => m.name === 'complementosPadroes/rrpginlua.rpk');
if (!pkg) throw Error('Selected package missing');
const nested = readZip(pkg.payload);
const selected = nested.members.filter(m => m.name.startsWith('macros/'));
const S='macros/RRPGSimpleMacros.lua', G='macros/RRPGSimpleMacrosGerenciar.lfm', D='macros/TablesDockMacros.lfm', I='macros/TablesDockMacrosItem.lfm', L='macros/rrpgMacros.dlua';
const specifications = [
 ['MAC-001', S, 24, 48, [26,33,46,47], 'Name normalization and prepared fields'],
 ['MAC-002', S, 53, 80, [60,62,75], 'User schema: global and room branches'],
 ['MAC-003', S, 142, 187, [155,158,167,179], 'Lookup priority: user room, user global, shared room'],
 ['MAC-004', S, 192, 242, [194,197,199,203,220,237], 'Simple line dispatch and Lua branch; execution guard'],
 ['MAC-005', S, 248, 298, [254,258,263,271,272,275,296], 'Local XML migration, backup and memory fallback'],
 ['MAC-006', S, 324, 361, [324,329,332,349,353,360], 'Command listener and advertised /macros command'],
 ['MAC-007', S, 379, 475, [386,390,394,415,453,458,463,472], 'User NDB loading, invalidation and lifecycle'],
 ['MAC-008', G, 153, 207, [160,169,173,179,185,192,194,199,201], 'Management scopes and visibility'],
 ['MAC-009', G, 28, 77, [32,37,38,69,71,77], 'Editor fields and normalization binding'],
 ['MAC-010', D, 19, 57, [26,28,30,31,32,46,50,54], 'Dock enumeration includes all scopes'],
 ['MAC-011', I, 5, 21, [8,9,10,21], 'Dock click invokes copied node directly'],
 ['MAC-012', L, 438, 453, [439,442,452], 'Parameter alias and whitespace-only argument splitting'],
 ['MAC-013', L, 1102, 1142, [1104,1114,1117,1134,1139], 'Lua text compilation and coroutine bootstrap'],
 ['MAC-014', L, 507, 561, [508,514,520,526,527,546,552,558], 'Chat and dice wrappers delegate core behavior'],
 ['MAC-015', L, 1037, 1099, [1048,1053,1057,1061,1077], 'Environment construction; sandbox guarantees unknown'],
 ['MAC-016', S, 83, 138, [88,100,104,108,112,116], 'Shared room NDB and observer invalidation'],
 ['MAC-017', L, 227, 247, [231,238,245], 'Nested macro lookup and missing macro error'],
];
const spans=specifications.map(([evidenceId,member,startLine,endLine,sampleLines,description])=>{
 const m=selected.find(x=>x.name===member); if(!m)throw Error(member);
 const text=m.payload.toString('utf8'); const lines=text.split('\n');
 const begin=Buffer.byteLength(lines.slice(0,startLine-1).join('\n')+(startLine>1?'\n':''));
 const end=Buffer.byteLength(lines.slice(0,endLine).join('\n')+(endLine<lines.length?'\n':''));
 return {evidenceId,member,memberSha256:m.sha256,description,startLine,endLine,byteStart:begin,byteEndExclusive:end,spanSha256:sha256(m.payload.subarray(begin,end)),samples:sampleLines.map(line=>({line,text:lines[line-1].replace(/\r$/,'')}))};
});
const pattern=/permission|isMestre|souMestre|export|importXML|maxLength|readOnly/i;
const boundedSearch=selected.flatMap(m=>m.payload.toString('utf8').split(/\r?\n/).flatMap((line,i)=>pattern.test(line)?[{member:m.name,line:i+1,text:line}]:[]));
const result={evidenceId:'F2-MACROS',method:'Independent readZip static inspection; in-memory decompression only; no target/Lua execution, no full source export',toolVersion:process.version,outer:outer.archive,package:Object.fromEntries(Object.entries(pkg).filter(([k])=>k!=='payload')),offsetConvention:'Package offsets relative to uncompressed RPK; byte ranges relative to uncompressed UTF-8 member, zero based; lines one based including BOM',members:selected.map(({payload,...m})=>m),spans,boundedSearch:{pattern:pattern.source,scope:'all 12 members under macros/ only',hits:boundedSearch},limitations:['Static packaged logic does not establish runtime use or server enforcement','NDB native implementation, actual persisted XML, network and runtime not observed','Generated .lfm.lua members inventoried; findings use readable .lfm and non-generated Lua','No target code or assets exported; only minimal selected evidence snippets']};
const output=path.join(root,'evidencias/fase2/macros.json');fs.mkdirSync(path.dirname(output),{recursive:true});fs.writeFileSync(output,JSON.stringify(result,null,2));
console.log(JSON.stringify({evidenceId:result.evidenceId,outerSha256:outer.archive.sha256,packageSha256:pkg.sha256,members:selected.length,spans:spans.length,boundedHits:boundedSearch.length,output}));
