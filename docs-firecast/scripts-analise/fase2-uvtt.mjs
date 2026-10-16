// Independent static analysis. Reads packaged Lua as data; never evaluates it.
import fs from 'node:fs';
import path from 'node:path';
import {readZip,sha256} from './zip-inventory.mjs';
const root=path.resolve(import.meta.dirname,'..');
const source=JSON.parse(fs.readFileSync(path.join(root,'inventarios/resource-zip.json'),'utf8')).archive.path;
const zip=readZip(source);
const member=zip.members.find(m=>m.name==='complementosPadroes/UniversalVTTImporter.rpk');
if(!member?.payload||!member.crc32Matches)throw Error('Missing or corrupt RPK');
const rpk=readZip(member.payload);
const selected=['module.xml','uvtt.lua','jsonparser.lua','base64.lua','sdk/scene.lua','sdk/sceneCore.dlua','sdk/sceneWrappers.dlua','sdk/fireDriveCore.dlua','sdk/internetCore.dlua'];
const sources=selected.map(name=>{const m=rpk.members.find(m=>m.name===name);if(!m?.payload||!m.crc32Matches)throw Error(name);return {name,sha256:m.sha256,size:m.size,lines:m.payload.toString('utf8').split(/\r?\n/).length,crc32Matches:m.crc32Matches};});
const refs=(file,start,end)=>({file,lineStart:start,lineEnd:end,sha256:sources.find(s=>s.name===file).sha256});
const claims=[
 ['UVTT-01','observed-static','Plugin Ambesek.dd2vtt version 1.2; SDK 3.7, API 88.1.',[refs('module.xml',2,18)]],
 ['UVTT-02','observed-static','Tool button Gerador / Importar dd2vtt; callback returns when scene.isGM is false, then offers single dd2vtt file selection and UTF-8 JSON decode.',[refs('uvtt.lua',87,104),refs('sdk/sceneWrappers.dlua',62,75)]],
 ['UVTT-03','observed-static','World width and height = resolution.map_size x/y times existing grid.cellSize. Image decoded from dg.image into memory and uploaded as arq.name + .png.',[refs('uvtt.lua',104,143)]],
 ['UVTT-04','observed-static','Each adjacent line_of_sight pair becomes opaque quadrilateral: dominant x extends y by +/-0.05 grid units, otherwise extends x. Coordinates multiply cellSize.',[refs('uvtt.lua',145,172)]],
 ['UVTT-05','observed-static','Each portal bounds[1..2] becomes same opaque quadrilateral; no open/closed branch. Any wall/portal enables fog and sets full world rectangle explorable.',[refs('uvtt.lua',174,206)]],
 ['UVTT-06','observed-static','Each light creates a background token at (position-0.5)*cellSize, intense range=range*cellSize, angle=360.',[refs('uvtt.lua',208,215)]],
 ['UVTT-07','observed-static','PNG upload failure offers local save or TinyPNG compression/JPEG conversion. Basic auth config key is base64(api:input). 401 exact error string clears key.',[refs('uvtt.lua',6,85),refs('uvtt.lua',124,143)]],
 ['UVTT-08','observed-static','SDK upload checks stream table, resets stream position to zero, rejects size strictly above 128 MiB; delegates to RRPG.FIRECAST.FMXModule using plugin messages.',[refs('sdk/fireDriveCore.dlua',1,5),refs('sdk/fireDriveCore.dlua',22,77),refs('sdk/fireDriveCore.dlua',96,151)]],
 ['UVTT-09','inference-static','No schema/version/range validation, rollback, scene clear, portal state conversion, map-origin or pixel-scale conversion appears in complete 219-line importer. Missing/malformed fields can fail after prior changes; repeats can append walls/lights.',[refs('uvtt.lua',87,219)]],
 ['UVTT-10','observed-static','JSON decode rejects trailing garbage, reports line/column; null becomes nil. Arrays use numeric indices, objects assignment permits duplicate-key overwrite.',[refs('jsonparser.lua',33,37),refs('jsonparser.lua',48,59),refs('jsonparser.lua',159,219),refs('jsonparser.lua',250,260)]],
 ['UVTT-11','observed-static','SDK scene, fog and lighting operations delegate to native object calls/properties; semantics, authorization and replication beyond wrappers not established.',[refs('sdk/sceneCore.dlua',25,106),refs('sdk/sceneWrappers.dlua',62,75),refs('sdk/sceneWrappers.dlua',550,552),refs('sdk/sceneWrappers.dlua',718,720),refs('sdk/sceneWrappers.dlua',1003,1011)]]
].map(([id,classification,claim,evidence])=>({id,classification,claim,evidence}));
const synthetic={classification:'independent handwritten contract examples; not executed against Lua or Firecast',cellSize:50,input:{resolution:{map_size:{x:4,y:3}},image:'<base64 of an independently authored PNG; placeholder, not importable>',line_of_sight:[[{x:0,y:1},{x:2,y:1}]],portals:[{bounds:[{x:2,y:1},{x:2,y:2}],closed:false}],lights:[{position:{x:1.5,y:2.5},range:3}]},derivedByArithmetic:{worldWidth:200,worldHeight:150,wallPolygon:[{x:0,y:47.5},{x:100,y:47.5},{x:100,y:52.5},{x:0,y:52.5}],portalPolygon:[{x:97.5,y:50},{x:97.5,y:100},{x:102.5,y:100},{x:102.5,y:50}],light:{x:50,y:100,lightIntenseRange:150,lightAngle:360},fogEnabled:true,portalClosedFieldIgnored:true}};
const result={evidenceId:'F2-UVTT-001',method:'Node ZIP parser; store/deflate decompression in memory; line-numbered static reading; no target execution, payload export, login or network',nodeVersion:process.version,scriptSha256:sha256(fs.readFileSync(import.meta.filename)),archive:zip.archive,rpk:{name:member.name,size:member.size,sha256:member.sha256,crc32Matches:member.crc32Matches,members:rpk.archive.memberCount},sources,claims,synthetic,limitations:['Not a REA MCP Evidence ID; independent reproducible evidence supplement to prior REA inventory','Source lines are UTF-8 text split on CRLF/LF; BOM retained in line 1','No dynamic import, parser execution, uploads, endpoint access, role tests or server validation','Contract reflects shipped importer version 1.2 only, not whole Universal VTT specification','Malformed JSON/schema failures are predictions from static control flow; native API coercion and exceptions remain unknown','Base64 image placeholder is deliberately not a complete import fixture']};
const out=path.join(root,'evidencias/fase2/uvtt.json');fs.mkdirSync(path.dirname(out),{recursive:true});fs.writeFileSync(out,JSON.stringify(result,null,2)+'\n');
console.log(JSON.stringify({evidenceId:result.evidenceId,rpk:result.rpk,sources:sources.length,claims:claims.length,output:out}));
