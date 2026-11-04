<?php
declare(strict_types=1);
// Admin-only package installer. Requires PHP ZipArchive and write access to application files.
function package_zip(string $field): ZipArchive {
 if(!class_exists('ZipArchive')) throw new RuntimeException('PHP zip extension is required.');
 if(!isset($_FILES[$field]) || $_FILES[$field]['error']!==UPLOAD_ERR_OK)throw new RuntimeException('Choose a valid ZIP file.');
 $f=$_FILES[$field];if($f['size']>25*1024*1024)throw new RuntimeException('Maximum package size is 25 MB.');
 $z=new ZipArchive();if($z->open($f['tmp_name'])!==true)throw new RuntimeException('Could not open ZIP.');
 if($z->numFiles>1500){$z->close();throw new RuntimeException('Too many package files.');}
 $total=0;
 for($i=0;$i<$z->numFiles;$i++){
  $stat=$z->statIndex($i);$n=str_replace('\\','/',$stat['name']);$total+=(int)$stat['size'];
  if($total>90*1024*1024||strlen($n)>240||str_contains($n,"\0")||str_starts_with($n,'/')||preg_match('~(^|/)\.\.(/|$)|^[A-Za-z]:|(^|/)\.htaccess$~i',$n)){$z->close();throw new RuntimeException('Unsafe ZIP path or archive size.');}
  $mode=(($stat['external_attributes']??0)>>16)&0170000;
  if($mode===0120000){$z->close();throw new RuntimeException('Symlinks are not permitted.');}
 }
 return $z;
}
function copy_tree(string $from,string $to):void{
 if(!is_dir($to)&&!mkdir($to,0755,true)&&!is_dir($to))throw new RuntimeException('Cannot create directory: '.$to);
 foreach(new DirectoryIterator($from) as $item){if($item->isDot())continue;$dest=$to.'/'.$item->getFilename();if($item->isDir())copy_tree($item->getPathname(),$dest);else if(!copy($item->getPathname(),$dest))throw new RuntimeException('Cannot write: '.$dest);}
}
function unpack_checked(ZipArchive $zip,string $target):void{
 if(!mkdir($target,0700,true))throw new RuntimeException('Cannot create staging directory.');
 for($i=0;$i<$zip->numFiles;$i++){
  $name=str_replace('\\','/',$zip->getNameIndex($i));$dest=$target.'/'.$name;
  if(str_ends_with($name,'/')){if(!is_dir($dest))mkdir($dest,0755,true);continue;}
  if(!is_dir(dirname($dest)))mkdir(dirname($dest),0755,true);
  $in=$zip->getStream($zip->getNameIndex($i));$out=fopen($dest,'wb');if(!$in||!$out)throw new RuntimeException('Failed to extract file.');stream_copy_to_stream($in,$out);fclose($in);fclose($out);
 }
 $zip->close();
}
function delete_tree(string $path):void{if(!is_dir($path))return;foreach(new FilesystemIterator($path) as $f){if($f->isDir()&&!$f->isLink())delete_tree($f->getPathname());else unlink($f->getPathname());}rmdir($path);}
function install_package(string $type):string{
 $u=admin();if($u['role']!=='admin')throw new RuntimeException('Only the owner administrator can install code.');
 $z=package_zip('package');$temp=ROOT.'/storage/.packages/'.bin2hex(random_bytes(8));unpack_checked($z,$temp);
 try{
  if($type==='plugin'){
   $dirs=array_values(array_filter(glob($temp.'/*')?:[],'is_dir'));$source=count($dirs)===1&&is_file($dirs[0].'/plugin.json')?$dirs[0]:$temp;
   $manifest=$source.'/plugin.json';if(!is_file($manifest)||!is_file($source.'/bootstrap.php'))throw new RuntimeException('Plugin ZIP must contain plugin.json and bootstrap.php.');
   $meta=json_decode(file_get_contents($manifest),true);$slug=$meta['slug']??basename($source);
   if(!preg_match('/^[a-z][a-z0-9-]{1,60}$/',$slug))throw new RuntimeException('Plugin slug must use lowercase letters, numbers and hyphens.');
   $target=ROOT.'/plugins/'.$slug;if(is_dir($target))throw new RuntimeException('Plugin already installed. Remove it manually before reinstalling.');
   if(!isset($meta['version'])||!preg_match('/^\d+\.\d+\.\d+$/',(string)$meta['version']))throw new RuntimeException('Plugin needs a semantic version.');if(isset($meta['min_core'])&&version_compare(setting('app_version','2.0.0'),(string)$meta['min_core'],'<'))throw new RuntimeException('Plugin requires a newer core version.');$meta['enabled']=false;file_put_contents($manifest,json_encode($meta,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));copy_tree($source,$target);audit('plugin.install',$slug);return 'Plugin uploaded. Review it before enabling.';
  }
  if($type==='update'){
   if(!is_file($temp.'/update.json'))throw new RuntimeException('Update ZIP must have update.json at its root.');
   $meta=json_decode(file_get_contents($temp.'/update.json'),true);if(($meta['product']??'')!=='thirtyvi-degrees'||empty($meta['version']))throw new RuntimeException('This is not a 36° update.');
   $allowed=['app','public','index.php','router.php'];$backup=ROOT.'/storage/.backups/'.date('Ymd-His').'-'.bin2hex(random_bytes(3));mkdir($backup,0700,true);
   $current=setting('app_version','2.0.0');if(version_compare((string)$meta['version'],$current,'<='))throw new RuntimeException('Update version must be newer than installed version.');
   $applied=[];
   // Record exact changed paths and their prior state. Never include config.php or customer uploads.
   $tracked=[];
   foreach($allowed as $name){$source=$temp.'/'.$name;if(!file_exists($source))continue;
    if(is_dir($source)){$iter=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source,FilesystemIterator::SKIP_DOTS));foreach($iter as $entry){if(!$entry->isFile()||$entry->isLink())throw new RuntimeException('Unsafe update entry.');$relative=$name.'/'.substr($entry->getPathname(),strlen($source)+1);$tracked[]=$relative;}}
    else $tracked[]=$name;
   }
   foreach($tracked as $relative){$dest=ROOT.'/'.$relative;if(is_link($dest))throw new RuntimeException('Cannot replace symlink: '.$relative);if(is_file($dest)){$snapshot=$backup.'/files/'.$relative;if(!is_dir(dirname($snapshot)))mkdir(dirname($snapshot),0700,true);if(!copy($dest,$snapshot))throw new RuntimeException('Cannot save restore point: '.$relative);}}
   $manifest=['from'=>$current,'to'=>(string)$meta['version'],'created_at'=>gmdate('c'),'files'=>$tracked,'has_migrations'=>is_dir($temp.'/migrations')&&count(glob($temp.'/migrations/*.sql')?:[])>0];
   if(file_put_contents($backup.'/restore-point.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),LOCK_EX)===false)throw new RuntimeException('Cannot write restore point manifest.');
   try{
    foreach($allowed as $name){$source=$temp.'/'.$name;if(!file_exists($source))continue;$dest=ROOT.'/'.$name;
      // Prior versions of changed files are stored in the restore point manifest above.
      if(is_dir($source))copy_tree($source,$dest);else if(!copy($source,$dest))throw new RuntimeException('Cannot update '.$name);
      $applied[]=$name;
    }
    // Each migration is executed once, tracked in the database. Prefer additive/idempotent SQL.
    $migrationDir=$temp.'/migrations';
    if(is_dir($migrationDir)){
      sql('CREATE TABLE IF NOT EXISTS schema_migrations (name VARCHAR(190) PRIMARY KEY, applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
      foreach(glob($migrationDir.'/*.sql')?:[] as $file){$name=basename($file);if(one('SELECT name FROM schema_migrations WHERE name=?',[$name]))continue;
        $sqlText=file_get_contents($file);foreach(array_filter(array_map('trim',explode(';',$sqlText))) as $query)db()->exec($query);
        sql('INSERT INTO schema_migrations(name) VALUES(?)',[$name]);
      }
    }
    // Invalidate replaced scripts so subsequent requests use this release immediately.
    clearstatcache();
    if(function_exists('opcache_invalidate'))foreach($tracked as $relative)if(str_ends_with($relative,'.php'))@opcache_invalidate(ROOT.'/'.$relative,true);
    sql('INSERT INTO settings(`key`,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['app_version',(string)$meta['version']]);audit('site.update',(string)$meta['version']);return 'Update installed. File backup: storage/.backups/'.basename($backup);
   }catch(Throwable $e){foreach(array_reverse($tracked) as $relative){$source=$backup.'/files/'.$relative;$dest=ROOT.'/'.$relative;if(is_file($source))copy($source,$dest);elseif(is_file($dest))unlink($dest);}throw new RuntimeException('Update failed. Changed files restored where possible. Database changes may require manual recovery: '.$e->getMessage());}

  }
  throw new RuntimeException('Unsupported package type.');
 }finally{delete_tree($temp);}
}
// Restore points apply to code files only. Never reverse SQL migrations automatically.
function site_restore_points():array{
 $points=[];$dir=ROOT.'/storage/.backups';foreach(glob($dir.'/*/restore-point.json')?:[] as $file){
  $m=json_decode((string)file_get_contents($file),true);if(!is_array($m)||empty($m['from'])||empty($m['to'])||!isset($m['files'])||!is_array($m['files']))continue;
  $m['id']=basename(dirname($file));$points[]=$m;
 }usort($points,static fn($a,$b)=>strcmp($b['id'],$a['id']));return $points;
}
function site_restore_to(string $targetId):string{
 $u=admin();if($u['role']!=='admin')throw new RuntimeException('Only the owner administrator can restore site files.');
 if(!preg_match('/^[0-9]{8}-[0-9]{6}-[a-f0-9]{6}$/',$targetId))throw new RuntimeException('Invalid restore point.');
 $lock=ROOT.'/storage/.backups/.restore.lock';$handle=fopen($lock,'c');if(!$handle||!flock($handle,LOCK_EX|LOCK_NB))throw new RuntimeException('Another restore operation is in progress.');
 try{
  $points=site_restore_points();$current=setting('app_version','2.0.0');$chain=[];$expected=$current;$found=false;
  foreach($points as $p){if(($p['to']??'')!==$expected)break;$chain[]=$p;$expected=(string)$p['from'];if($p['id']===$targetId){$found=true;break;}}
  if(!$found)throw new RuntimeException('Restore point is not in the contiguous update history. Only updates installed after this feature can be reverted.');
  foreach($chain as $p){if(!empty($p['has_migrations']))throw new RuntimeException('This update included database migrations. Restore manually from a verified full backup instead.');
   foreach($p['files'] as $path){if(!is_string($path)||!preg_match('~^(index\.php|router\.php|(?:app|public)/[A-Za-z0-9_./-]+)$~',$path)||str_contains($path,'..')||str_contains($path,'//'))throw new RuntimeException('Unsafe restore manifest.');
    if(is_link(ROOT.'/'.$path))throw new RuntimeException('Restore target is a symlink.');$prior=ROOT.'/storage/.backups/'.$p['id'].'/files/'.$path;
    if(!is_file($prior)&&!is_file(ROOT.'/'.$path))continue;
    if(is_file($prior)&&!is_readable($prior))throw new RuntimeException('Restore point is incomplete.');
   }
  }
  // Save current versions of affected files to a recovery snapshot before changing anything.
  $safety=ROOT.'/storage/.backups/.recovery-'.date('Ymd-His').'-'.bin2hex(random_bytes(3));if(!mkdir($safety,0700,true))throw new RuntimeException('Could not prepare recovery snapshot.');
  $all=[];foreach($chain as $p)foreach($p['files'] as $path)$all[$path]=true;
  foreach(array_keys($all) as $path){$src=ROOT.'/'.$path;if(is_file($src)){$dst=$safety.'/'.$path;if(!is_dir(dirname($dst)))mkdir(dirname($dst),0700,true);if(!copy($src,$dst))throw new RuntimeException('Recovery snapshot failed.');}}
  try{
   foreach($chain as $p){foreach($p['files'] as $path){$prior=ROOT.'/storage/.backups/'.$p['id'].'/files/'.$path;$dest=ROOT.'/'.$path;
    if(is_file($prior)){if(!is_dir(dirname($dest)))mkdir(dirname($dest),0755,true);if(!copy($prior,$dest))throw new RuntimeException('Could not restore '.$path);}
    elseif(is_file($dest)&&!unlink($dest))throw new RuntimeException('Could not remove new file '.$path);
   }}
   sql('INSERT INTO settings(`key`,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['app_version',$expected]);audit('site.restore',$current.' -> '.$expected);
   // Archive consumed restore points to prevent accidentally replaying an invalid history.
   foreach($chain as $p){$manifest=ROOT.'/storage/.backups/'.$p['id'].'/restore-point.json';if(is_file($manifest))rename($manifest,ROOT.'/storage/.backups/'.$p['id'].'/restored-point.json');}
  }catch(Throwable $e){foreach(array_keys($all) as $path){$src=$safety.'/'.$path;$dest=ROOT.'/'.$path;if(is_file($src))copy($src,$dest);elseif(is_file($dest))unlink($dest);}throw new RuntimeException('Restore failed; recovery snapshot reapplied where possible: '.$e->getMessage());}
  return 'Site files restored to version '.$expected.'. Database data was not rolled back. Review the site immediately.';
 }finally{flock($handle,LOCK_UN);fclose($handle);}
}
function toggle_plugin(string $slug,bool $enabled):void{
 $u=admin();if($u['role']!=='admin')throw new RuntimeException('Only the owner administrator can manage plugins.');
 if(!preg_match('/^[a-z][a-z0-9-]{1,60}$/',$slug))throw new RuntimeException('Invalid plugin.');
 $f=ROOT.'/plugins/'.$slug.'/plugin.json';if(!is_file($f))throw new RuntimeException('Plugin not found.');
 $m=json_decode(file_get_contents($f),true);$m['enabled']=$enabled;if(file_put_contents($f,json_encode($m,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),LOCK_EX)===false)throw new RuntimeException('Cannot update plugin permissions.');audit('plugin.toggle',$slug.' '.($enabled?'enabled':'disabled'));
}
