import fs from 'node:fs';
const ev=JSON.parse(fs.readFileSync('docs-firecast/evidencias/rea-pe-firecast.json'));
const file=fs.readFileSync(ev.normalized_result.artifact.path);
const old=JSON.parse(fs.readFileSync('docs-firecast/inventarios/recursos-pe.json'));
for(const asset of old.resources){const index=Number(asset.id.slice(3));const r=ev.normalized_result.resources[index];const b=file.subarray(r.payload.location.offset,r.payload.location.offset+r.payload.location.bytes);
 asset.dimensions=null;asset.durationSeconds=null;asset.origin='Embutido no Firecast.exe; autoria individual não determinada';asset.screenAssociation='não verificada';asset.method='REA inspect_pe_resources; complemento local de assinatura e dimensões declaradas em RT_GROUP_ICON';
 if(b.subarray(0,8).equals(Buffer.from('89504e470d0a1a0a','hex'))&&b.length>=24){asset.format='PNG';asset.dimensions={width:b.readUInt32BE(16),height:b.readUInt32BE(20),method:'PNG IHDR'};}
 else if(b.subarray(0,3).equals(Buffer.from('ffd8ff','hex')))asset.format='JPEG';
 for(const g of ev.normalized_result.icon_groups)for(const img of g.images)if(img.candidate_resource_indices.includes(index))asset.dimensions={width:img.width||256,height:img.height||256,method:'declared RT_GROUP_ICON, not rendered'};
}
fs.writeFileSync('docs-firecast/inventarios/recursos-pe.json',JSON.stringify(old,null,2));
const inv=JSON.parse(fs.readFileSync('docs-firecast/inventarios/arquivos.json'));
const f=inv.files.find(f=>f.path==='FirecastPlugin.ico');const b=fs.readFileSync(inv.root+'/'+f.path);const images=[];
if(b.readUInt16LE(0)!==0||b.readUInt16LE(2)!==1)throw Error('Invalid ICO header');
for(let i=0;i<b.readUInt16LE(4);i++){const p=6+i*16;images.push({width:b[p]||256,height:b[p+1]||256,bitCount:b.readUInt16LE(p+6),bytes:b.readUInt32LE(p+8),offset:b.readUInt32LE(p+12)});}
const asset={id:'INSTALL-ASSET-001',name:f.path,sourcePath:inv.root+'/'+f.path,format:'ICO',bytes:f.size,sha256:f.sha256,images,durationSeconds:null,method:'ICO directory only, no rendering or extraction',origin:'Distribuído na instalação; autoria não determinada',screenAssociation:'Associação de plugin sugerida pelo nome, não verificada',license:null,reuse:'desconhecida',extracted:false};
fs.writeFileSync('docs-firecast/inventarios/assets-instalacao.json',JSON.stringify({id:'LOCAL-ASSETS-PE-001',assets:[asset]},null,2));
console.log(JSON.stringify({peResources:old.resources.length,standaloneAssets:1,icoImages:images.length}));
