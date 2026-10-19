// Original synthetic PNG and legacy UVTT fixtures; no application/database bootstrap.
const fs=require('fs'),zlib=require('zlib'),path=require('path');
function crc(b){let c=0xffffffff;for(const v of b){c^=v;for(let n=0;n<8;n++)c=(c>>>1)^((c&1)?0xedb88320:0);}return (c^0xffffffff)>>>0;}
function chunk(type,data){const b=Buffer.concat([Buffer.from(type),data]),n=Buffer.alloc(4),c=Buffer.alloc(4);n.writeUInt32BE(data.length);c.writeUInt32BE(crc(b));return Buffer.concat([n,b,c]);}
const ihdr=Buffer.alloc(13);ihdr.writeUInt32BE(200);ihdr.writeUInt32BE(200,4);ihdr[8]=8;ihdr[9]=2;
const pixels=Buffer.alloc(200*(1+600));for(let y=0;y<200;y++){for(let x=0;x<200;x++){const c=x%50===0||y%50===0?[74,85,100]:x>=70&&x<=130&&y>=70&&y<=130?[70,130,145]:[38,43,54];pixels.set(c,y*601+1+x*3);}}
const png=Buffer.concat([Buffer.from('89504e470d0a1a0a','hex'),chunk('IHDR',ihdr),chunk('IDAT',zlib.deflateSync(pixels)),chunk('IEND',Buffer.alloc(0))]);
const base={format:0.3,resolution:{map_origin:{x:0,y:0},map_size:{x:4,y:4},pixels_per_grid:50},line_of_sight:[],portals:[],lights:[],image:png.toString('base64')};
function save(name,d){fs.writeFileSync(path.join(__dirname,name+'.dd2vtt'),JSON.stringify(d,null,2));}
save('minimo',base);base.resolution.map_origin={x:10,y:20};base.line_of_sight=[[{x:10,y:21},{x:12,y:21}]];base.portals=[{position:{x:12,y:21.5},bounds:[{x:12,y:21},{x:12,y:22}],rotation:Math.PI/2,closed:false,freestanding:false}];base.lights=[{position:{x:11.5,y:22.5},range:3,intensity:0.7,color:'ffcc88ff',shadows:true}];save('geometria',base);fs.writeFileSync(path.join(__dirname,'invalido.dd2vtt'),'{"format":0.3,"image":"https://invalid.example/never-fetch"}');
