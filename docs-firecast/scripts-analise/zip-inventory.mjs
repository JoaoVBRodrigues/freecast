// Independent read-only ZIP inspector. Does not evaluate or export target payloads.
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import zlib from 'node:zlib';
export const sha256 = b => crypto.createHash('sha256').update(b).digest('hex');
const crcTable=Uint32Array.from({length:256},(_,n)=>{let c=n;for(let k=0;k<8;k++)c=c&1?0xedb88320^(c>>>1):c>>>1;return c>>>0;});
const crc32=b=>{let c=0xffffffff;for(const v of b)c=crcTable[(c^v)&255]^(c>>>8);return ((c^0xffffffff)>>>0).toString(16).padStart(8,'0');};
export function readZip(file) {
 const b=Buffer.isBuffer(file)?file:fs.readFileSync(file); let e=b.length-22;
 for(;e>=Math.max(0,b.length-65557);e--) if(b.readUInt32LE(e)===0x06054b50 && e+22+b.readUInt16LE(e+20)===b.length) break;
 if(e<0||b.readUInt32LE(e)!==0x06054b50)throw Error('EOCD not found');
 const n=b.readUInt16LE(e+10),cd=b.readUInt32LE(e+16); if(n===65535||cd===0xffffffff)throw Error('ZIP64 unsupported');
 const members=[];let p=cd;
 for(let i=0;i<n;i++){
  if(b.readUInt32LE(p)!==0x02014b50)throw Error('Central directory signature');
  const flags=b.readUInt16LE(p+8),method=b.readUInt16LE(p+10),size=b.readUInt32LE(p+24),compressedSize=b.readUInt32LE(p+20),nl=b.readUInt16LE(p+28),xl=b.readUInt16LE(p+30),cl=b.readUInt16LE(p+32),localOffset=b.readUInt32LE(p+42);
  const name=b.subarray(p+46,p+46+nl).toString(flags&2048?'utf8':'latin1');
  if(b.readUInt32LE(localOffset)!==0x04034b50)throw Error('Local header signature');
  const dataOffset=localOffset+30+b.readUInt16LE(localOffset+26)+b.readUInt16LE(localOffset+28),raw=b.subarray(dataOffset,dataOffset+compressedSize);
  let payload=null,error=null;try{if(flags&1)throw Error('Encrypted');payload=method===0?raw:method===8?zlib.inflateRawSync(raw,{maxOutputLength:256*1024*1024}):null;if(!payload)throw Error('Unsupported compression');if(payload.length!==size)throw Error('Size mismatch');}catch(ex){error=ex.message;}
  const m={id:`ZIP-M${String(i+1).padStart(5,'0')}`,name,extension:path.posix.extname(name).toLowerCase(),centralOffset:p,localOffset,dataOffset,compressedSize,size,compression:method,flags,crc32:b.readUInt32LE(p+16).toString(16).padStart(8,'0'),compressedSha256:sha256(raw),sha256:payload?sha256(payload):null,error,format:null,width:null,height:null,origin:'bundled resource; authorship unverified',license:null,reuse:'unknown',association:'unverified'};
  if(payload){if(payload.subarray(0,8).equals(Buffer.from([137,80,78,71,13,10,26,10]))){m.format='PNG';m.width=payload.readUInt32BE(16);m.height=payload.readUInt32BE(20);}else if(payload.subarray(0,3).toString()==='GIF'){m.format='GIF';m.width=payload.readUInt16LE(6);m.height=payload.readUInt16LE(8);}else if(payload.subarray(0,2).toString()==='BM'){m.format='BMP';m.width=payload.readInt32LE(18);m.height=Math.abs(payload.readInt32LE(22));}else if(payload.subarray(0,2).equals(Buffer.from([255,216]))){m.format='JPEG';let q=2;while(q+8<payload.length){if(payload[q]!==255){q++;continue;}const marker=payload[q+1];if([0xc0,0xc1,0xc2,0xc3,0xc5,0xc6,0xc7,0xc9,0xca,0xcb,0xcd,0xce,0xcf].includes(marker)){m.height=payload.readUInt16BE(q+5);m.width=payload.readUInt16BE(q+7);break;}if(marker===0xd8||marker===0xd9){q+=2;continue;}const len=payload.readUInt16BE(q+2);if(len<2)break;q+=2+len;}}}
  m.crc32Calculated=payload?crc32(payload):null;m.crc32Matches=payload?m.crc32===m.crc32Calculated:null;
  members.push({...m,payload});p+=46+nl+xl+cl;
 }
 return {archive:{path:Buffer.isBuffer(file)?'(in-memory nested ZIP)':file,size:b.length,sha256:sha256(b),eocdOffset:e,centralOffset:cd,centralSize:b.readUInt32LE(e+12),memberCount:n},members};
}
if(process.argv[1] && path.resolve(process.argv[1])===path.resolve(import.meta.filename)){
 const input=process.argv[2],out=process.argv[3];if(!input||!out)throw Error('Usage: node zip-inventory.mjs input.zip output.json');
 const r=readZip(input),counts={};for(const m of r.members)counts[m.extension||'(none)']=(counts[m.extension||'(none)']||0)+1;
 const result={evidenceId:'ZIP-001',method:'independent Node ZIP central-directory parser; in-memory decompression only; no target execution',toolVersion:process.version,archive:r.archive,counts,members:r.members.map(({payload,...m})=>m),limitations:['No ZIP64, encryption or compression beyond store/deflate','No target execution; filenames do not prove runtime use','Names without UTF-8 flag decoded as Latin-1; raw name bytes retained in archive','No payload exported; dimensions only PNG/GIF/BMP/JPEG headers']};
 fs.writeFileSync(out,JSON.stringify(result,null,2));console.log(JSON.stringify({archive:r.archive,counts,errors:result.members.filter(m=>m.error).length},null,2));
}
