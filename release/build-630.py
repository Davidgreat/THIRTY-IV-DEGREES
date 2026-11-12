from pathlib import Path
import json,subprocess,hashlib,zipfile
root=Path(__file__).resolve().parents[1];out=root/'artifacts';out.mkdir(exist_ok=True);baseline='abb14fa';files=[]
for base in ['app','public']:
 for p in (root/base).rglob('*'):
  if not p.is_file() or 'uploads' in p.relative_to(root).parts:continue
  rel=p.relative_to(root);old=subprocess.run(['git','show',f'{baseline}:{rel}'],cwd=root,capture_output=True)
  if old.returncode or old.stdout!=p.read_bytes():files.append(rel)
for rel in [Path('index.php'),Path('router.php')]:
 if subprocess.run(['git','show',f'{baseline}:{rel}'],cwd=root,capture_output=True,check=True).stdout!=(root/rel).read_bytes():files.append(rel)
assert not any(str(p) in ['app/checkout.php','public/checkout.php','public/assets/checkout-612.js','app/paystack.php','app/payment-verification.php','app/paystack-refunds.php','app/integrations.php'] for p in files)
archive=out/'thirtyvi-degrees-update-6.3.0.zip'
with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED) as z:
 z.writestr('update.json',json.dumps({'product':'thirtyvi-degrees','version':'6.3.0','name':'36° Owner Security & Product Galleries','description':'For 6.2.1: owner account security, editable categories and multi-image galleries. Includes additive catalogue tables.'},indent=2))
 for rel in sorted(files):z.write(root/rel,str(rel))
 z.write(root/'release/migrations/630_catalogue.sql','migrations/630_catalogue.sql')
(out/'SHA256SUMS-6.3.0.txt').write_text(hashlib.sha256(archive.read_bytes()).hexdigest()+'  '+archive.name+'\n')
print(json.dumps({'archive':str(archive),'bytes':archive.stat().st_size,'files':[str(f) for f in sorted(files)]},indent=2))
