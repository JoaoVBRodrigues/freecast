import fs from 'node:fs';import crypto from 'node:crypto';
const root=JSON.parse(fs.readFileSync(process.argv[2])),nested=JSON.parse(fs.readFileSync(process.argv[3]));
const members=[...root.members,...nested.packages.flatMap(p=>p.members)];
const source=fs.readFileSync(root.archive.path),sourceSha256=crypto.createHash('sha256').update(source).digest('hex');
const checks={evidenceId:'ZIP-006',method:'inventory consistency and CRC verification; input read again after analysis',tool:process.version,sourceSha256,sourceSha256Matches:sourceSha256===root.archive.sha256,outerMembers:root.members.length,nestedMembers:nested.packages.reduce((n,p)=>n+p.members.length,0),payloadReadErrors:members.filter(m=>m.error).length,crcMismatches:members.filter(m=>m.crc32Matches!==true).length,missingPayloadHashes:members.filter(m=>!m.sha256).length,uncompressedOuterBytes:root.members.reduce((n,m)=>n+m.size,0),compressedOuterBytes:root.members.reduce((n,m)=>n+m.compressedSize,0),payloadsExported:0};
fs.writeFileSync(process.argv[4],JSON.stringify(checks,null,2));console.log(JSON.stringify(checks,null,2));
if(!checks.sourceSha256Matches||checks.payloadReadErrors||checks.crcMismatches||checks.missingPayloadHashes)process.exitCode=1;
