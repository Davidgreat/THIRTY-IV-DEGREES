from pathlib import Path
import zipfile,json,hashlib
root=Path(__file__).resolve().parents[1]
baseline=Path('/workspace/platform-source/public_html')
out=root/'artifacts';out.mkdir(exist_ok=True)
files=[]
for base in ['app','public']:
 for p in (root/base).rglob('*'):
  if not p.is_file() or 'uploads' in p.relative_to(root).parts:continue
  rel=p.relative_to(root);old=baseline/rel
  if not old.is_file() or p.read_bytes()!=old.read_bytes():files.append(rel)
for rel in [Path('index.php'),Path('router.php')]:
 if (root/rel).read_bytes()!=(baseline/rel).read_bytes():files.append(rel)
manifest={'product':'thirtyvi-degrees','version':'6.0.0','name':'36° Payment & Studio Update','description':'Owner-managed test/live Paystack, payment/access fixes, account recovery and responsive glass dashboards.'}
with zipfile.ZipFile(out/'thirtyvi-degrees-update-6.0.0.zip','w',zipfile.ZIP_DEFLATED) as z:
 z.writestr('update.json',json.dumps(manifest,indent=2))
 for rel in sorted(files):z.write(root/rel,str(rel))
 for p in (root/'release/migrations').glob('*.sql'):z.write(p,'migrations/'+p.name)
archive=out/'thirtyvi-degrees-update-6.0.0.zip'
(out/'SHA256SUMS.txt').write_text(hashlib.sha256(archive.read_bytes()).hexdigest()+'  '+archive.name+'\n')
print(json.dumps({'archive':str(archive),'size':archive.stat().st_size,'changed_files':[str(p) for p in sorted(files)]},indent=2))
