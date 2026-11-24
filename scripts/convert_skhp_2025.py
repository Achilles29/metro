import json
import os
import subprocess
from pathlib import Path
from zipfile import ZipFile

root=Path('/tmp/metro-skhp-stage')
source=root/'source'
pdf=root/'pdf'
root.mkdir(mode=0o700,exist_ok=True)
source.mkdir(mode=0o700,exist_ok=True)
pdf.mkdir(mode=0o700,exist_ok=True)
archive='/www/wwwroot/metro/docs/source/SKHP 2025.zip'
data=json.load(open('/tmp/metro-skhp-analysis.json'))
docs=[d for d in data['docs'] if not Path(d['file']).name.startswith('~$')]
manifest={d['file']:str(pdf/f'{i:04}.pdf') for i,d in enumerate(docs,1)}
with ZipFile(archive) as z:
    for start in range(0,len(docs),15):
        batch=docs[start:start+15]
        paths=[]
        for i,d in enumerate(batch,start+1):
            target=source/f'{i:04}.rtf'
            if not Path(manifest[d['file']]).exists():
                with z.open(d['file']) as inp, target.open('wb') as out:
                    while chunk:=inp.read(1024*1024):out.write(chunk)
                paths.append(str(target))
        if paths:
            cmd=['libreoffice','-env:UserInstallation=file:///tmp/lo-metro-skhp-batch',
                 '--headless','--convert-to','pdf','--outdir',str(pdf),*paths]
            r=subprocess.run(cmd,stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True,timeout=240)
            if r.returncode:
                raise RuntimeError(f'Conversion failed at {start}: {r.stdout[-2000:]}')
        for path in paths:Path(path).unlink(missing_ok=True)
        for d in batch:
            out=Path(manifest[d['file']])
            if not out.is_file() or out.stat().st_size<100 or out.open('rb').read(5)!=b'%PDF-':
                raise RuntimeError(f'Missing or invalid PDF: {d["file"]}')
            os.chmod(out,0o600)
        print(f'Converted {min(start+15,len(docs))}/{len(docs)}',flush=True)
(root/'manifest.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2))
os.chmod(root/'manifest.json',0o600)
print('Done',len(manifest),flush=True)
