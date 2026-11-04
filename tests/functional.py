"""HTTP checks against synthetic local data only. No external gateway calls."""
import re,datetime,json,urllib.request,urllib.parse,urllib.error,http.cookiejar,types
class Session:
 def __init__(self):self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),urllib.request.ProxyHandler({}))
 def request(self,method,url,data=None,params=None,json_data=None):
  if params:url+='?'+urllib.parse.urlencode(params)
  headers={};payload=None
  if data is not None:payload=urllib.parse.urlencode(data).encode()
  if json_data is not None:payload=json.dumps(json_data).encode();headers['Content-Type']='application/json'
  try:r=self.opener.open(urllib.request.Request(url,data=payload,headers=headers,method=method))
  except urllib.error.HTTPError as e:r=e
  text=r.read().decode();status=r.status
  return types.SimpleNamespace(status_code=status,text=text,url=r.url,json=lambda:json.loads(text),raise_for_status=lambda: (_ for _ in ()).throw(RuntimeError(str(status))) if status>=400 else None)
 def get(self,url,params=None):return self.request('GET',url,params=params)
 def post(self,url,data=None,json=None):return self.request('POST',url,data=data,json_data=json)
requests=types.SimpleNamespace(Session=Session,get=lambda url:Session().get(url),post=lambda url,**kw:Session().post(url,**kw))
BASE='http://127.0.0.1:8086'
n=0
def check(ok,label):
 global n
 assert ok,label
 n+=1;print('PASS:',label)
def csrf(s,path):
 r=s.get(BASE+path);r.raise_for_status();m=re.search(r'name="csrf" value="([a-f0-9]+)"',r.text);assert m,path;return m[1]
def login(role):
 s=requests.Session();t=csrf(s,'/login');r=s.post(BASE+'/login',data={'csrf':t,'email':role+'@example.test','password':'Test-password-600'});check('/login' not in r.url,role+' login');return s
admin=login('admin');t=csrf(admin,'/admin/paystack')
secret='sk_test_'+'c'*24
r=admin.post(BASE+'/admin/paystack/save',data={'csrf':t,'paystack_mode':'test','paystack_enabled':'1','paystack_test_public':'pk_test_'+'c'*24,'paystack_test_secret':secret})
check('Paystack settings saved.' in r.text,'Owner saves and activates test credentials')
check(secret not in r.text,'Saved secret never rendered')
r=admin.post(BASE+'/admin/paystack/save',data={'csrf':t,'paystack_mode':'test','paystack_test_secret':'sk_live_'+'d'*24})
check('invalid format' in r.text,'Mixed-mode key rejected through admin form')
r=admin.post(BASE+'/admin/paystack/save',data={'csrf':t,'paystack_mode':'test'})
check('Payments paused' in r.text,'Owner pauses checkout while preserving keys')
check(admin.post(BASE+'/admin/paystack/save',data={'paystack_mode':'live'}).status_code==419,'CSRF protects settings')
# Missing banner admin page is now connected to existing save/delete actions.
r=admin.post(BASE+'/admin/banner/save',data={'csrf':t,'headline':'Functional test banner','placement':'home_top','target_url':'/menu','button_text':'Order','sort_order':'1','enabled':'1'})
check(r.status_code==200 and 'Functional test banner' in r.text,'Banner creation returns to working page')
check('Functional test banner' in requests.get(BASE+'/').text,'Enabled banner appears on storefront')
# Guest shopping, checkout and authorization.
s=requests.Session();t=csrf(s,'/product/2')
r=s.post(BASE+'/cart/add',data={'csrf':t,'product_id':'2','qty':'2'})
check('Jollof' in r.text,'Guest can add meal to bag')
t=csrf(s,'/checkout')
r=s.post(BASE+'/checkout',data={'csrf':t,'name':'Guest Test','email':'guest@example.test','phone':'08012345678','fulfillment':'pickup','address':''})
check('/order/' in r.url and 'Your Order' in r.text,'Guest checkout creates accessible order')
order_url=r.url;oid=re.search(r'/order/(\d+)',r.url)[1]
check(requests.get(order_url).status_code==403,'Different guest cannot read order')
outsider=requests.Session();outside_csrf=csrf(outsider,'/login')
check(outsider.post(BASE+'/pay',data={'csrf':outside_csrf,'type':'order','id':oid}).status_code==403,'Different guest cannot start payment by ID')
# Customer booking and ownership.
customer=login('customer');t=csrf(customer,'/private-chef');date=(datetime.date.today()+datetime.timedelta(days=7)).isoformat()
r=customer.post(BASE+'/booking/create',data={'csrf':t,'kind':'chef','name':'Customer Demo','email':'customer@example.test','phone':'08012345678','event_date':date,'event_time':'14:00','guests':'4','location':'Synthetic test address','event_type':'Dinner'})
check('/booking/' in r.url and 'Your Booking' in r.text,'Chef inquiry creates customer booking')
check(requests.get(r.url).status_code==403,'Unrelated visitor cannot read booking')
r=customer.get(BASE+'/booking/availability',params={'kind':'chef','date':'2027-02-30','time':'14:00'});check(r.json()['available']==False,'Impossible calendar date rejected')
r=customer.get(BASE+'/booking/availability',params={'kind':'chef','date':date,'time':'25:99'});check(r.json()['available']==False,'Invalid time rejected')
check('EVT-' in customer.get(BASE+'/account').text,'Customer dashboard shows real booking records')
manager=login('manager');t=csrf(manager,'/admin/orders');check(manager.post(BASE+'/admin/paystack/save',data={'csrf':t,'paystack_mode':'live'}).status_code==403,'Manager cannot alter payment configuration')
check(customer.get(BASE+'/admin/paystack').status_code==403,'Customer cannot open payment settings')
# Unauthenticated webhook input must never be accepted.
check(requests.post(BASE+'/payment/webhook',json={'event':'charge.success','data':{'reference':'PAY-fake'}}).status_code==403,'Unsigned webhook rejected')
print('TOTAL:',n,'passed')
