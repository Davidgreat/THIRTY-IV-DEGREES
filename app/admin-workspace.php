<?php
/** One catalogue drives navigation and the owner's tool guide. Existing routes stay intact. */
function admin_tool_groups():array{return [
 'operations'=>['title'=>'Orders & bookings','hint'=>'Daily kitchen and event work','icon'=>'01','tools'=>[
 'orders'=>['Orders','Update kitchen progress, fulfilment and customer order status.'],
 'bookings'=>['Chef & catering bookings','Review inquiries, send quotations and confirm events.'],
 'calendar'=>['Availability calendar','Block unavailable dates and review upcoming events.']]],
 'menu'=>['title'=>'Menu & stock','hint'=>'Products, choices and inventory','icon'=>'02','tools'=>[
 'products'=>['Menu & products','Add meals, prices, categories and customization choices.'],
 'inventory'=>['Stock control','Track available quantities and low-stock thresholds.']]],
 'payments'=>['title'=>'Payments & reports','hint'=>'Paystack, transactions and refunds','icon'=>'03','tools'=>[
 'payments'=>['Transactions','Review recorded payments and their test/live environment.'],
 'paystack'=>['Paystack setup','Manage payment activation and test/live credentials.'],
 'checkout-health'=>['Checkout diagnostics','Inspect endpoint stages and missing database fields without changing records.'],
 'refunds'=>['Refund reviews','Review eligible payments and submit or reconcile refunds.'],
 'analytics'=>['Sales reports','Understand paid order value, popular meals and booking activity.']]],
 'website'=>['title'=>'Website & content','hint'=>'Appearance, promotions and media','icon'=>'04','tools'=>[
 'settings'=>['Brand & service settings','Edit business details, colours, delivery fee and chef availability.'],
 'sliders'=>['Homepage slideshow','Choose the images, videos and copy featured on the homepage.'],
 'banners'=>['Promotional banners','Manage compact announcements and calls to action.'],
 'media'=>['Media library','Upload images and copy their URLs into products and pages.'],
 'pages'=>['Information pages','Maintain your story, policies and other site information.']]],
 'customers'=>['title'=>'Customers & messages','hint'=>'People, communication and reviews','icon'=>'05','tools'=>[
 'customers'=>['Customer accounts','Look up registered customers and their contact details.'],
 'whatsapp'=>['WhatsApp messages','Configure the business number and open prepared customer messages.'],
 'email'=>['Email delivery','Configure SMTP, test sending and review the delivery queue.'],
 'email-templates'=>['Email templates','Edit the messages sent for orders, bookings and payment updates.'],
 'reviews'=>['Experience reviews','Approve or hide customer feedback about the business.'],
 'product-reviews'=>['Meal reviews','Moderate ratings and feedback on individual meals.']]],
 'system'=>['title'=>'Access & system','hint'=>'Team permissions and maintenance','icon'=>'06','tools'=>[
 'staff'=>['Staff access','Assign manager roles and restrict operational permissions.'],
 'integrations'=>['Other connections','Review optional API fields and integration requirements.'],
 'plugins'=>['Plugins','Manage trusted extensions installed on your website.'],
 'updates'=>['Website updates','Install trusted update ZIPs and view available restore points.']]],
];}
function admin_visible_groups():array{
 $groups=admin_tool_groups();if(admin()['role']==='admin')return $groups;
 $allowed=['orders','bookings','calendar','inventory','customers','whatsapp'];
 foreach($groups as $key=>&$group){$group['tools']=array_intersect_key($group['tools'],array_flip($allowed));if(!$group['tools'])unset($groups[$key]);}unset($group);return $groups;
}
function admin_tool_info(string $section):array{
 if($section==='')return ['Overview','Your daily priorities, recent activity and quick access to the right tools.'];
 if($section==='workspace')return ['Workspace guide','Find the right place for each task. All existing tools are grouped below.'];
 if(str_starts_with($section,'product/')||$section==='meal-options')$section='products';
 foreach(admin_tool_groups() as $group)if(isset($group['tools'][$section]))return $group['tools'][$section];
 return ['', 'Manage this part of your business.'];
}
function admin_workspace_guide():void{
 ?><div class="workspace-intro"><h2>What would you like to do?</h2><p>Start with a section below. Each tool explains what it controls.</p></div><div class="workspace-groups"><?php foreach(admin_visible_groups() as $key=>$group):?><section class="workspace-group" id="workspace-<?=$key?>"><header><span><?=$group['icon']?></span><div><h2><?=esc($group['title'])?></h2><p><?=esc($group['hint'])?></p></div></header><div><?php foreach($group['tools'] as $route=>$tool):?><a class="workspace-tool" href="/admin/<?=$route?>"><div><strong><?=esc($tool[0])?></strong><p><?=esc($tool[1])?></p></div><span aria-hidden="true">↗</span></a><?php endforeach;?></div></section><?php endforeach;?></div><?php
}
