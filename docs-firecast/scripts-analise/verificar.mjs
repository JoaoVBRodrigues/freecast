// Verificações de documentação e integridade; não executa o alvo nem seus scripts.
import fs from 'node:fs';import path from 'node:path';import crypto from 'node:crypto';import {spawnSync} from 'node:child_process';
const root=path.resolve('docs-firecast');const sha=b=>crypto.createHash('sha256').update(b).digest('hex');
const inventory=JSON.parse(fs.readFileSync(path.join(root,'inventarios/arquivos.json')));
const originals=inventory.files.map(f=>{const b=fs.readFileSync(path.join(inventory.root,f.path));return {path:f.path,sha256:sha(b),matches:sha(b)===f.sha256&&b.length===f.size};});
const all=[];function walk(dir){for(const e of fs.readdirSync(dir,{withFileTypes:true})){const p=path.join(dir,e.name);e.isDirectory()?walk(p):all.push(p);}}walk(root);
const json=[],syntax=[],links=[];
for(const file of all){const rel=path.relative(root,file).replaceAll('\\','/');if(file.endsWith('.json')&&!file.endsWith('verificacao.json')){try{JSON.parse(fs.readFileSync(file,'utf8').replace(/^\uFEFF/,''));json.push({path:rel,valid:true});}catch(e){json.push({path:rel,valid:false,error:e.message});}}
 if(file.endsWith('.mjs')){const r=spawnSync(process.execPath,['--check',file],{encoding:'utf8',windowsHide:true});syntax.push({path:rel,valid:r.status===0,error:r.status===0?null:r.stderr});}
 if(file.endsWith('.md')){const t=fs.readFileSync(file,'utf8');for(const m of t.matchAll(/\]\(([^)]+)\)/g)){const ref=m[1];if(/^(?:https?:|#|mailto:)/.test(ref))continue;const target=ref.split('#')[0];links.push({source:rel,target:ref,exists:fs.existsSync(path.resolve(path.dirname(file),target))});}}
}
const required=['README.md','00-resumo-executivo.md','01-ambiente-e-metodologia.md','02-inventario-tecnico.md','03-funcionalidades-e-regras.md','04-telas-e-fluxos.md','05-dados-e-formatos.md','06-fichas-scripts-e-plugins.md','07-comunicacao-e-sincronizacao.md','08-assets-e-reutilizacao.md','09-arquitetura-proposta.md','10-matriz-de-paridade.csv','11-plano-de-implementacao.md','12-lacunas-e-proximos-experimentos.md','CONTEXTO-PARA-IMPLEMENTACAO.md','PROGRESSO.md'].map(p=>({path:p,exists:fs.existsSync(path.join(root,p))}));
// Self-link is intentional; this report is written below.
for(const l of links)if(l.target==='evidencias/verificacao.json')l.exists=true;
const pe=JSON.parse(fs.readFileSync(path.join(root,'evidencias/rea-pe-firecast.json')));const wrap=JSON.parse(fs.readFileSync(path.join(root,'evidencias/inspect_artifact.json')));const art=wrap.structuredContent??JSON.parse(wrap.content.find(c=>c.type==='text').text);
const zip=JSON.parse(fs.readFileSync(path.join(root,'evidencias/zip-verificacao.json')));
const hashes=all.filter(f=>/\.(mjs|ps1)$/.test(f)).map(f=>({path:path.relative(root,f).replaceAll('\\','/'),sha256:sha(fs.readFileSync(f))}));
const runtimeManifest=path.join(root,'capturas/manifesto-fase2.json');
const captures=fs.existsSync(runtimeManifest)?JSON.parse(fs.readFileSync(runtimeManifest)).captures.map(c=>{const b=fs.readFileSync(path.join(root,c.file));return{path:c.file,valid:sha(b)===c.sha256&&b.readUInt32BE(16)===c.width&&b.readUInt32BE(20)===c.height};}):[];
const psPath=path.join(root,'evidencias/fase2/powershell-sintaxe.json');
const powershellSyntax=fs.existsSync(psPath)?JSON.parse(fs.readFileSync(psPath,'utf8').replace(/^\uFEFF/,'')):[];
const ok=originals.every(f=>f.matches)&&json.every(f=>f.valid)&&syntax.every(f=>f.valid)&&links.every(f=>f.exists)&&required.every(f=>f.exists)&&captures.every(f=>f.valid)&&powershellSyntax.every(f=>f.Valid);
const report={id:'LOCAL-VERIFY-002',atUtc:new Date().toISOString(),node:process.version,ok,originals,json,syntax,powershellSyntax,captures,links,required,analysisScripts:hashes,evidenceIds:{pe:pe.evidence_id,archive:art.evidence_id},zipVerificationReference:'zip-verificacao.json',limitations:['This verifier does not execute the target; authorized UI tests are recorded separately in fase2/runtime.json.','Hash equality compares initial inventory against this later read; no atomic snapshot or continuous monitoring.','Relative links checked for file existence, not anchors or remote availability.','PowerShell AST checks are loaded from the separate parsing report; no scripts executed by this verifier.']};
fs.writeFileSync(path.join(root,'evidencias/verificacao.json'),JSON.stringify(report,null,2));
console.log(JSON.stringify({ok,originals:originals.length,changed:originals.filter(f=>!f.matches),json:json.length,syntax:syntax.length,powershellSyntax:powershellSyntax.length,captures:captures.length,brokenLinks:links.filter(f=>!f.exists),missing:required.filter(f=>!f.exists),zipEvidenceId:zip.evidenceId}));
if(!ok)process.exitCode=1;
