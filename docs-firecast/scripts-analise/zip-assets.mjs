import fs from 'node:fs';
const outer=JSON.parse(fs.readFileSync(process.argv[2])),nested=JSON.parse(fs.readFileSync(process.argv[3]));
const extensions=new Set(['.png','.jpg','.jpeg','.gif','.bmp','.ttf','.mp3','.wav','.obj','.mtl','.style']);
const groups=[{container:'resource.zip',members:outer.members},...nested.packages.map(p=>({container:p.parent,members:p.members}))];
const assets=[];
for(const g of groups)for(const m of g.members.filter(m=>extensions.has(m.extension))){
 let association='unverified';if(m.name.startsWith('dados/'))association='inferred from path: dice presentation';else if(/temas|themes|frames/.test(m.name))association='inferred from path: theme/frame';else if(m.name.startsWith('icos/'))association='inferred from path: UI icon';
 assets.push({id:m.id,container:g.container,name:m.name,format:m.format||m.extension.slice(1).toUpperCase()+' (extension only)',size:m.size,width:m.width,height:m.height,duration:null,sha256:m.sha256,method:m.format?'header inspection in memory':'central directory and payload hash; type from extension',association,origin:g.container==='resource.zip'?'distributed in resource.zip; authorship unknown':'distributed plugin; authorship of individual asset unknown',license:null,reuse:'unknown',extracted:false});
}
const summary=groups.map(g=>({container:g.container,members:g.members.length,assets:assets.filter(m=>m.container===g.container).length,images:g.members.filter(m=>['.png','.jpg','.jpeg','.gif','.bmp'].includes(m.extension)).length}));
fs.writeFileSync(process.argv[4],JSON.stringify({evidenceId:'ZIP-004',sourceArchiveSha256:outer.archive.sha256,summary,extractedCount:0,visualCatalog:[],assets,limitations:['No license grant confirmed for image/audio/font/model assets','No payload exported; no contact sheets generated','No image decoding/rendering; dimensions from simple headers only','Audio durations not measured','Duplicate bundled assets remain separate entries; no ownership inferred from package author']},null,2));console.log(JSON.stringify(summary,null,2));
