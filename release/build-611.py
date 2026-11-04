from pathlib import Path
import json,subprocess,hashlib,zipfile
root=Path(__file__).resolve().parents[1];out=root/'artifacts';out.mkdir(exist_ok=True)
# 6.1.0 is a committed, reviewable baseline; no hosting config is read.
baseline='cd6b5b8'
files=[]
for base in ['app','public/assets']:
 for p in (root/base).rglob('*'):
  if not p.is_file():continue
  rel=p.relative_to(root)
  old=subprocess.run(['git','show',f'{baseline}:{rel}'],cwd=root,capture_output=True)
  if old.returncode or old.stdout!=p.read_bytes():files.append(rel)
for rel in [Path('index.php'),Path('router.php')]:
 old=subprocess.run(['git','show',f'{baseline}:{rel}'],cwd=root,capture_output=True,check=True)
 if old.stdout!=(root/rel).read_bytes():files.append(rel)
archive=out/'thirtyvi-degrees-update-6.1.1.zip'
with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED) as z:
 z.writestr('update.json',json.dumps({'product':'thirtyvi-degrees','version':'6.1.1','name':'36° Checkout Payment Hotfix','description':'For 6.1.0: direct hosted payment handoff, visible validation, retained checkout details and safe gateway error feedback. No migrations.'},indent=2))
 for rel in sorted(files):z.write(root/rel,str(rel))
(out/'SHA256SUMS-6.1.1.txt').write_text(hashlib.sha256(archive.read_bytes()).hexdigest()+'  '+archive.name+'\n')
print(json.dumps({'archive':str(archive),'bytes':archive.stat().st_size,'files':[str(f) for f in files]},indent=2))
