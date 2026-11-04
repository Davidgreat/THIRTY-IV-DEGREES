from pathlib import Path
import zipfile,json,hashlib
root=Path(__file__).resolve().parents[1];baseline=Path('/workspace/baseline-600');out=root/'artifacts';out.mkdir(exist_ok=True)
files=[]
for base in ['app','public']:
 for p in (root/base).rglob('*'):
  if not p.is_file() or 'uploads' in p.relative_to(root).parts:continue
  rel=p.relative_to(root);old=baseline/rel
  if not old.is_file() or p.read_bytes()!=old.read_bytes():files.append(rel)
for rel in [Path('index.php'),Path('router.php')]:
 if (root/rel).read_bytes()!=(baseline/rel).read_bytes():files.append(rel)
assert not any(str(p) in ['app/paystack.php','app/payment-verification.php','app/paystack-refunds.php','app/integrations.php'] for p in files)
manifest={'product':'thirtyvi-degrees','version':'6.1.0','name':'36° Compact Experience','description':'Compact responsive storefront, two-step bookings, grouped admin workspace and live chef availability. For installed version 6.0.0. No database migrations.'}
archive=out/'thirtyvi-degrees-update-6.1.0.zip'
with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED) as z:
 z.writestr('update.json',json.dumps(manifest,indent=2))
 for rel in sorted(files):z.write(root/rel,str(rel))
(out/'SHA256SUMS-6.1.0.txt').write_text(hashlib.sha256(archive.read_bytes()).hexdigest()+'  '+archive.name+'\n')
print(json.dumps({'archive':str(archive),'bytes':archive.stat().st_size,'runtime_files':[str(p) for p in sorted(files)]},indent=2))
