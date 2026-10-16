// Independent static inspection; target Lua is never evaluated or exported whole.
import fs from 'node:fs';
import path from 'node:path';
import {readZip,sha256} from './zip-inventory.mjs';
const input=process.argv[2] || path.join(process.env.LOCALAPPDATA,'Firecast','resource.zip');
const output=process.argv[3] || 'docs-firecast/evidencias/fase2/ndb.json';
const outer=readZip(input);
const packages=outer.members.filter(m=>m.name.endsWith('.rpk')).map(m=>({member:m,zip:readZip(m.payload)}));
const selected=[
 ['rrpginlua.rpk','sdk/ndb.lua',[[76,96],[111,128],[171,184],[186,216],[219,244],[247,280],[293,304],[365,386],[390,405],[429,477],[552,582],[599,605],[632,659],[662,677],[738,761],[805,809],[819,837],[845,864],[876,901],[909,944]]],
 ['rrpginlua.rpk','sdk/ndb_observer.dlua',[[7,23],[35,40],[44,48]]],
 ['rrpginlua.rpk','sdk/ndb_transaction.dlua',[[8,24],[46,50]]],
 ['rrpginlua.rpk','FichaBasicaMultiPlataforma.lfm',[[9,23]]],
 ['rrpginlua.rpk','NodeExplorer/PermissionsPopUp.lfm',[[19,19],[113,122],[129,140],[147,150],[255,267],[380,405],[419,425]]],
 ['rrpginlua.rpk','NodeExplorer/explorerHost.lua',[[14,21]]],
 ['rrpginlua.rpk','firedrive/firedriveNavigator.dlua',[[252,259],[326,330]]],
 ['rrpginlua.rpk','sdk/fireDriveCore.dlua',[[96,114],[118,135],[138,147],[154,164],[201,213]]],
 ['DnD5_SDK3.rpk','fichaDnD5.lfm',[[43,75]]],
 ['sceneinlua.rpk','inseridortokens/sceneInseridorFireDrive.lfm',[[1001,1028]]]
];
const sources=selected.map(([a,name,ranges],i)=>{const p=packages.find(p=>p.member.name.endsWith('/'+a));const m=p.zip.members.find(m=>m.name===name);if(!m||m.error||!m.crc32Matches)throw Error('Invalid selected member '+name);const lines=m.payload.toString('utf8').split(/\r?\n/);return {id:`NDB-S${i+1}`,source:p.member.name+'!/'+name,packageSha256:p.member.sha256,memberId:m.id,sha256:m.sha256,size:m.size,lineCount:lines.length,crc32Matches:m.crc32Matches,selections:ranges.map(([start,end])=>({start,end,lines:lines.slice(start-1,end).map((text,j)=>({line:start+j,text}))}))};});
const xmlCallSites=[];let searchedMembers=0;
for(const p of packages)for(const m of p.zip.members.filter(m=>/\.(lua|dlua|lfm|xml|txt)$/i.test(m.name))){searchedMembers++;m.payload.toString('utf8').split(/\r?\n/).forEach((s,i)=>{if(/\b(?:exportXML|importXML|ExportToXMLString|ImportXMLString)\b/.test(s))xmlCallSites.push({source:p.member.name+'!/'+m.name,sha256:m.sha256,line:i+1,text:s.trim()});});}
const result={evidenceId:'F2-NDB-001',kind:'independent static evidence, not a REA-issued Evidence ID',procedure:'readZip central directory and in-memory store/deflate decompression; UTF-8 text with 1-based lines split on CRLF/LF; selections only; no Lua evaluation, target launch, profile, cache or log access',toolVersion:process.version,input:outer.archive,sources,search:{scope:'All five shipped RPK members with lua/dlua/lfm/xml/txt extensions, literal exportXML/importXML/ExportToXMLString/ImportXMLString sites; no internet search',searchedMembers,xmlCallSites},limitations:['Native _ndb_/_obj_ calls opaque; no XML schema or round-trip observed','No runtime observation, atomicity, durability, persistence or synchronization established','Literal call-site search is bounded and cannot exclude an unreferenced XML fixture or dynamic call','Not an upstream published specification; shipped version only'],integrity:{inputSha256After:sha256(fs.readFileSync(input)),selectedMemberErrors:0}};
if(result.integrity.inputSha256After!==outer.archive.sha256)throw Error('Input changed');
fs.mkdirSync(path.dirname(output),{recursive:true});fs.writeFileSync(output,JSON.stringify(result,null,2)+'\n');
console.log(JSON.stringify({evidenceId:result.evidenceId,selectedSources:sources.length,searchedMembers,xmlCallSites:xmlCallSites.length,inputUnchanged:true,output}));
