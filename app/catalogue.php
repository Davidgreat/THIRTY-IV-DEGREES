<?php
declare(strict_types=1);
function catalogue_image_url(string $value):string{
 $value=trim($value);if($value==='')return '';
 if(strlen($value)>1000||preg_match('/[\x00-\x20]/',$value))throw new InvalidArgumentException('An image address is invalid.');
 if(preg_match('~^/public/(uploads|assets)/[a-zA-Z0-9_./-]+$~D',$value)&&!str_contains($value,'..'))return $value;
 $parts=parse_url($value);if($parts&&in_array(strtolower($parts['scheme']??''),['http','https'],true)&&!empty($parts['host'])&&!isset($parts['user'])&&!isset($parts['pass']))return $value;
 throw new InvalidArgumentException('Use an HTTPS image address or an image from your media library.');
}
function catalogue_category(string $name,bool $inStore):int{
 $name=trim($name);if($name===''||mb_strlen($name)>120)throw new InvalidArgumentException('Enter a category name of up to 120 characters.');
 $slug=strtolower(trim(preg_replace('/[^a-z0-9]+/','-',strtolower($name)),'-'));
 if($slug==='')$slug='category-'.bin2hex(random_bytes(5));
 if(one('SELECT id FROM categories WHERE name=? OR slug=?',[$name,$slug]))throw new InvalidArgumentException('That category already exists. Choose it from the category list.');
 sql('INSERT INTO categories(name,slug) VALUES(?,?)',[$name,$slug]);$id=(int)db()->lastInsertId();
 sql('INSERT INTO category_channels(category_id,in_store) VALUES(?,?)',[$id,(int)$inStore]);return $id;
}
function catalogue_category_save():never{
 try{
  db()->beginTransaction();$id=(int)($_POST['id']??0);$name=str('name',120);$store=isset($_POST['in_store']);
  if($id){if(!$name||!one('SELECT id FROM categories WHERE id=? FOR UPDATE',[$id]))throw new InvalidArgumentException('Choose an existing category and enter its name.');if(one('SELECT id FROM categories WHERE name=? AND id<>?',[$name,$id]))throw new InvalidArgumentException('Another category already uses that name.');sql('UPDATE categories SET name=? WHERE id=?',[$name,$id]);sql('INSERT INTO category_channels(category_id,in_store) VALUES(?,?) ON DUPLICATE KEY UPDATE in_store=VALUES(in_store)',[$id,(int)$store]);}
  else $id=catalogue_category($name,$store);
  audit('category.save',(string)$id);db()->commit();flash('Category saved.');
 }catch(Throwable $error){if(db()->inTransaction())db()->rollBack();flash($error instanceof InvalidArgumentException?$error->getMessage():'Category could not be saved. Please try again.','error');}
 go('admin/categories');
}
function catalogue_store_condition(string $alias='c'):string{
 return "(EXISTS(SELECT 1 FROM category_channels cc WHERE cc.category_id=$alias.id AND cc.in_store=1) OR (NOT EXISTS(SELECT 1 FROM category_channels cc WHERE cc.category_id=$alias.id) AND LOWER($alias.slug) IN ('brownies','small-chops','special-drinks','desserts','snacks','pastries','beverages','drinks','treats','shop')))";
}
function catalogue_product_save():never{
 $created=[];$id=(int)($_POST['id']??0);
 try{
  if($id&&!one('SELECT id FROM products WHERE id=?',[$id]))throw new InvalidArgumentException('This meal no longer exists.');
  $name=str('name',180);$price=$_POST['price']??'';
  if(!$name||!is_scalar($price)||!is_numeric($price)||!is_finite((float)$price)||(float)$price<0||(float)$price>10000000)throw new InvalidArgumentException('Enter a meal name and a valid price between 0 and 10,000,000.');
  $cover=catalogue_image_url(str('image',1000));$urls=$_POST['gallery_url']??[];$alts=$_POST['gallery_alt']??[];$orders=$_POST['gallery_order']??[];$remove=$_POST['gallery_remove']??[];
  if(!is_array($urls)||count($urls)>7||!is_array($alts)||!is_array($orders)||!is_array($remove))throw new InvalidArgumentException('Use up to seven additional gallery images.');
  $gallery=[];
  foreach(array_values($urls) as $i=>$url){if(in_array((string)$i,array_map('strval',$remove),true))continue;if(!is_string($url))throw new InvalidArgumentException('Invalid gallery image.');$url=catalogue_image_url($url);if($url!=='')$gallery[]=['image'=>$url,'alt'=>mb_substr(is_scalar($alts[$i]??'')?(string)($alts[$i]??''):'',0,180),'order'=>max(0,min(999,(int)($orders[$i]??$i)))];}
  if(($_POST['gallery_present']??'')!=='1'&&$id){$gallery=[];foreach(all('SELECT image,alt_text FROM product_gallery WHERE product_id=? ORDER BY sort_order,id',[$id]) as $i=>$photo)$gallery[]=['image'=>$photo['image'],'alt'=>$photo['alt_text'],'order'=>$i];}
  $files=$_FILES['gallery_images']??null;$fileNames=$files['name']??[];
  if(!is_array($fileNames))throw new InvalidArgumentException('Invalid image selection.');
  $count=count(array_filter($fileNames,static fn($v)=>is_string($v)&&$v!==''));if(count($gallery)+$count>7)throw new InvalidArgumentException('Use up to seven additional gallery images. Remove an existing image before adding more.');
  // Validate the whole batch before moving files, then clean only new files on any failure.
  $uploads=[];if(!empty($_FILES['product_image']['name']))$uploads['product_image']=$_FILES['product_image'];
  foreach($fileNames as $i=>$fileName)if($fileName!==''){$file=[];foreach(['name','tmp_name','size','error','type'] as $key)$file[$key]=$files[$key][$i]??null;$uploads['catalogue_image_'.$i]=$file;}
  foreach($uploads as $file){if(($file['error']??-1)!==UPLOAD_ERR_OK||(int)$file['size']>6000000||!is_uploaded_file((string)$file['tmp_name'])||!in_array((new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']),['image/jpeg','image/png','image/webp'],true)||!getimagesize($file['tmp_name']))throw new InvalidArgumentException('Each uploaded image must be a valid JPG, PNG or WebP of up to 6 MB.');}
  foreach($uploads as $key=>$file){$_FILES[$key]=$file;$uploaded=upload_media($key);$created[]=$uploaded;if($key==='product_image')$cover=$uploaded;else $gallery[]=['image'=>$uploaded,'alt'=>$name.' — additional view','order'=>count($gallery)];}
  if($cover===''&&$gallery){$first=array_shift($gallery);$cover=$first['image'];}
  usort($gallery,static fn($a,$b)=>$a['order']<=>$b['order']);
  db()->beginTransaction();$category=(int)($_POST['category_id']??0)?:null;
  if(str('new_category',120)!=='')$category=catalogue_category(str('new_category',120),isset($_POST['new_category_store']));
  elseif($category&&!one('SELECT id FROM categories WHERE id=?',[$category]))throw new InvalidArgumentException('Choose a valid category.');
  $values=[$category,$name,str('description',4000),$cover,(float)$price,isset($_POST['available'])?1:0,isset($_POST['featured'])?1:0];
  if($id)sql('UPDATE products SET category_id=?,name=?,description=?,image=?,price=?,available=?,featured=? WHERE id=?',[...$values,$id]);else{sql('INSERT INTO products(category_id,name,description,image,price,available,featured) VALUES(?,?,?,?,?,?,?)',$values);$id=(int)db()->lastInsertId();}
  sql('DELETE FROM product_gallery WHERE product_id=?',[$id]);foreach($gallery as $i=>$photo)sql('INSERT INTO product_gallery(product_id,image,alt_text,sort_order) VALUES(?,?,?,?)',[$id,$photo['image'],$photo['alt'],$i]);
  audit('product.save',(string)$id);db()->commit();flash('Meal and gallery saved.');go('admin/product/edit?id='.$id);
 }catch(Throwable $error){if(db()->inTransaction())db()->rollBack();foreach($created as $file)if(is_string($file)&&preg_match('~^/public/uploads/[a-f0-9]{36}\.(jpg|png|webp)$~D',$file))@unlink(ROOT.$file);abort($error instanceof InvalidArgumentException?400:500,$error instanceof InvalidArgumentException?$error->getMessage():'The meal could not be saved. Return to the editor and try again.');}
}
function catalogue_gallery(array $product):void{
 $photos=[];if($product['image']!=='')$photos[]=['image'=>$product['image'],'alt_text'=>$product['name']];foreach(all('SELECT image,alt_text FROM product_gallery WHERE product_id=? ORDER BY sort_order,id',[$product['id']]) as $photo)$photos[]=$photo;
 if(!$photos)return;
 ?><div class="product-gallery" data-product-gallery><img class="gallery-main" data-gallery-main src="<?=esc($photos[0]['image'])?>" alt="<?=esc($photos[0]['alt_text']?:$product['name'])?>"><?php if(count($photos)>1):?><p class="gallery-count" data-gallery-count role="status">Image 1 of <?=count($photos)?></p><div class="gallery-thumbnails" aria-label="Product photos"><?php foreach($photos as $i=>$photo):?><a href="<?=esc($photo['image'])?>" data-gallery-image data-image-alt="<?=esc($photo['alt_text']?:$product['name'].' — view '.($i+1))?>" aria-label="View image <?=$i+1?>" <?=$i===0?'aria-current="true"':''?>><img src="<?=esc($photo['image'])?>" alt="" loading="lazy"></a><?php endforeach;?></div><?php endif;?></div><?php
}
