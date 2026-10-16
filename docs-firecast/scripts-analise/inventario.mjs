// Leitura estática: não executa o alvo e não lê logs/conteúdo de perfis.
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
const root = 'C:/Users/João/AppData/Local/Firecast';
const out = path.resolve('docs-firecast/inventarios');
fs.mkdirSync(out,{recursive:true});
const sha = b=>crypto.createHash('sha256').update(b).digest('hex');
function pe(b) {
 if(b.toString('ascii',0,2)!=='MZ')return null;
 const p=b.readUInt32LE(0x3c); if(p+24>b.length||b.toString('ascii',p,p+4)!=='PE\0\0')return {format:'MZ sem PE reconhecido'};
 const n=b.readUInt16LE(p+6), os=b.readUInt16LE(p+20), o=p+24, magic=b.readUInt16LE(o), is64=magic===0x20b, machine=b.readUInt16LE(p+4);
 const sections=[];for(let i=0;i<n;i++){const s=o+os+i*40; sections.push({name:b.toString('ascii',s,s+8).replace(/\0.*$/,''),virtualSize:b.readUInt32LE(s+8),rva:b.readUInt32LE(s+12),rawSize:b.readUInt32LE(s+16),offset:b.readUInt32LE(s+20)});}
 const offset=rva=>{const s=sections.find(s=>rva>=s.rva&&rva<s.rva+Math.max(s.rawSize,s.virtualSize));return s?s.offset+rva-s.rva:null;};
 const ascii=at=>{let end=at;while(end<b.length&&b[end]&&end<at+4096)end++;return b.toString('ascii',at,end);};
 const d=o+(is64?112:96), imports=[], irva=b.readUInt32LE(d+8), it=offset(irva);
 if(irva&&it!==null)for(let j=it;j+20<b.length&&b.readUInt32LE(j+12);j+=20){const name=offset(b.readUInt32LE(j+12));if(name!==null)imports.push(ascii(name));if(imports.length>2048)throw Error('import budget');}
 const cliRva=b.readUInt32LE(d+14*8), entryRva=b.readUInt32LE(o+16);
 // Only fixed diagnostic markers: no broad dump of potentially personal strings.
 const markers=['Embarcadero','Borland','Delphi','FireMonkey','FMX.','System.SysUtils','Lua 5.3','Lua 5.4','PyInstaller'];
 const markerHits=[];for(const text of markers){for(const encoding of ['ascii','utf16le']){const needle=Buffer.from(text,encoding);let pos=0,count=0,first=[];while((pos=b.indexOf(needle,pos))!==-1){if(first.length<8)first.push(pos);count++;pos+=needle.length;}if(count)markerHits.push({text,encoding,count,firstFileOffsets:first});}}
 const rawEnd=Math.max(...sections.map(s=>s.offset+s.rawSize));
 return {format:is64?'PE32+':'PE32',machine:'0x'+machine.toString(16),architecture:machine===0x8664?'x86-64':machine===0x14c?'x86':'unknown',cliDirectoryPresent:cliRva!==0,entryRva:'0x'+entryRva.toString(16),sections,imports,markerHits,overlay:{offset:rawEnd,bytes:Math.max(0,b.length-rawEnd)},limitations:['Imports are static directory names, not observed library loading.','Absent CLR directory does not identify original programming language.','Markers alone do not establish compiler or runtime behavior.','Overlay includes any certificate table; not automatically a package.']};
}
const files=[];
function walk(dir){for(const ent of fs.readdirSync(dir,{withFileTypes:true})){const full=path.join(dir,ent.name),rel=path.relative(root,full).replaceAll('\\','/');if(ent.isDirectory()){if(ent.name==='LogErrors')continue;walk(full);}else if(ent.isFile()){const b=fs.readFileSync(full),s=fs.statSync(full);files.push({id:'FILE-'+String(files.length+1).padStart(3,'0'),path:rel,size:b.length,sha256:sha(b),mtimeUtc:s.mtime.toISOString(),signatureHex:b.subarray(0,16).toString('hex'),pe:pe(b)});}}}
walk(root);
const result={id:'LOCAL-INVENTORY-001',collectedAtUtc:new Date().toISOString(),root,tool:{node:process.version,script:'scripts-analise/inventario.mjs'},scope:'Regular installation files recursively, excluding LogErrors directory and no external profile search',files,limitations:['Live installation, no atomic snapshot.','File modification times are not build times.','Application not executed.']};
fs.writeFileSync(path.join(out,'arquivos.json'),JSON.stringify(result,null,2));
console.log(JSON.stringify({id:result.id,count:files.length,totalBytes:files.reduce((s,f)=>s+f.size,0),pe:files.filter(f=>f.pe).map(f=>({path:f.path,size:f.size,architecture:f.pe.architecture,managed:f.pe.cliDirectoryPresent,markers:f.pe.markerHits}))}));
