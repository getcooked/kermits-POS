// Native, editable diagrams.net flowcharts. Run: node docs/generate-flowchart.cjs
const fs = require('node:fs');
const path = require('node:path');
const pages = [];
const W = 850, H = 1100;
function page(title, subtitle, foot) {
  const p = {title, subtitle, foot, nodes: [], edges: []}; pages.push(p); return p;
}
function n(p,id,text,x,y,kind='process',w=180,h=60) {
  const node={id,text,x,y,w,h,kind}; p.nodes.push(node); return node;
}
function d(p,id,text,x,y) { return n(p,id,text,x,y,'decision',180,104); }
function c(p,id,text,x,y) { return n(p,id,text,x,y,'connector',38,38); }
function off(p,id,text,x,y) { return n(p,id,text,x,y,'offpage',52,52); }
function note(p,id,text,x,y,w=240,h=40) { return n(p,id,text,x,y,'text',w,h); }
function e(p,a,b,label='',from='bottom',to='top',via=[]) {
  p.edges.push({a,b,label,from,to,via});
}
function chain(p,...ids) { for(let i=1;i<ids.length;i++) e(p,ids[i-1],ids[i]); }

let p=page('System access and login',"Kermit's Ordering and Reservation System",'A = login retry  |  R / F / L → page 2');
n(p,'start','Start',225,110,'terminator',160,68);
n(p,'open','Open the system website',215,220,'input');
d(p,'account','Have an\naccount?',215,320);
off(p,'register','R',580,346); note(p,'regnote','Register\nPage 2',525,407,180);
c(p,'retry','A',132,482);
n(p,'login','Enter username / email\nand password',215,470,'input');
off(p,'forgot','F',650,474);
d(p,'valid','Input and enabled\nreCAPTCHA valid?',215,575);
n(p,'invalid','Show input error',535,596); c(p,'retry2','A',606,692);
d(p,'locked','Login temporarily\nlocked?',215,720);
n(p,'wait','Show retry delay',535,742); c(p,'retry3','A',606,832);
d(p,'match','Credentials\ncorrect?',215,860);
n(p,'failure','Record failure; show\nerror or lockout',535,878); c(p,'retry4','A',735,889);
off(p,'role','L',279,986); note(p,'rolenote','L → role routing on page 2',350,992,250,30);
chain(p,'start','open','account'); e(p,'account','register','No','right','left');
e(p,'account','login','Yes'); e(p,'retry','login','','right','left'); chain(p,'login','valid');
e(p,'login','forgot','Forgot password','right','left');
e(p,'valid','invalid','No','right','left'); chain(p,'invalid','retry2'); e(p,'valid','locked','Yes');
e(p,'locked','wait','Yes','right','left'); chain(p,'wait','retry3'); e(p,'locked','match','No');
e(p,'match','failure','No','right','left'); e(p,'failure','retry4','','right','left'); e(p,'match','role','Yes');

p=page('Registration and role routing','Verified registration • successful login destinations','R ← p.1  |  L ← p.1  |  B → p.3  |  C → p.5  |  D → p.7  |  A → login on p.1');
off(p,'r','R',189,110); off(p,'l','L',574,110);
n(p,'email','Enter unused Gmail address',125,207,'input');
n(p,'send','Send verification code',125,302);
n(p,'code','Enter six-digit code',125,397,'input');
d(p,'verify','Code correct and\nunexpired?',125,492);
c(p,'rtry','R1',63,523); c(p,'rentry','R1',64,220);
n(p,'details','Enter account details\nand confirm password',125,642,'input');
d(p,'form','Details valid and\nverified email matches?',125,746);
c(p,'dtry','R2',63,779); c(p,'dentry','R2',64,653);
n(p,'create','Create customer account\nand sign in',125,888); off(p,'customer','B',189,983);
n(p,'session','Clear failed attempts;\nstart authenticated session',510,207);
d(p,'role','Account role?',510,315);
off(p,'b','B',445,489); note(p,'bn','Customer\nPage 3',402,550,130);
off(p,'cc','C',574,489); note(p,'cn','Cashier\nPage 5',536,550,130);
off(p,'dd','D',703,489); note(p,'dn','Super admin\nPage 7',660,550,130);
n(p,'legacy','Other / legacy admin:\nhome page; restricted access',505,625, 'process',220);
off(p,'f','F',594,713);
n(p,'reset','Request reset email; open link',510,785,'input',220);
n(p,'newpass','Submit valid token and\nnew confirmed password',510,880,'input',220);
off(p,'a','A',594,980);
chain(p,'r','email','send','code','verify'); e(p,'verify','rtry','No','left','right'); e(p,'rentry','email','','right','left');
e(p,'verify','details','Yes'); chain(p,'details','form'); e(p,'form','dtry','No','left','right'); e(p,'dentry','details','','right','left');
e(p,'form','create','Yes'); chain(p,'create','customer'); chain(p,'l','session','role');
e(p,'role','b','Customer','left','top',[[471,367]]); e(p,'role','cc','Cashier');
e(p,'role','dd','Super admin','right','top',[[729,367]]);
e(p,'role','legacy','Other','right','right',[[782,367],[782,655]]);
chain(p,'f','reset','newpass','a');

p=page('Customer ordering','Website shop checkout and customer activities','B = customer home  |  E → booking on p.4  |  K → cashier review on p.5  |  V → reservation review on p.8');
off(p,'b','B',254,110);
n(p,'shop','Open customer shop',190,199);
d(p,'order','Place a food\norder?',190,296);
d(p,'book','Book without\nshop checkout?',530,296);
off(p,'e','E',594,463); n(p,'history','View own history,\nstatus and receipts',540,598);
n(p,'cart','Select menu items\nand quantities',190,438,'input');
n(p,'details','Enter table size, schedule\nand contact details',190,540,'input');
n(p,'payment','Choose cash or GCash;\nprovide required payment details',180,644,'input',200,70);
d(p,'valid','Valid input, stock\nand schedule?',190,754);
n(p,'error','Show error; revise checkout',530,776); c(p,'retry','B1',740,787); c(p,'entry','B1',110,450);
n(p,'save','Save pending order + reservation;\nreserve stock and show confirmation',140,901,'process',280,65);
off(p,'k','K',178,992); off(p,'v','V',329,992);
d(p,'logout','Log out?',540,900); n(p,'end','End',569,1032,'terminator',120,40); c(p,'again','B',738,931);
chain(p,'b','shop','order'); e(p,'order','book','No','right','left'); e(p,'book','e','Yes');
e(p,'book','history','No','right','right',[[775,348],[775,628]]);
e(p,'order','cart','Yes'); e(p,'entry','cart','','right','left'); chain(p,'cart','details','payment','valid');
e(p,'valid','error','No','right','left'); e(p,'error','retry','','right','left'); e(p,'valid','save','Yes');
e(p,'save','k','','bottom','top',[[280,979],[204,979]]); e(p,'save','v','','bottom','top',[[280,979],[355,979]]);
e(p,'history','logout','','right','top',[[795,628],[795,863],[630,863]]); e(p,'logout','again','No','right','left'); e(p,'logout','end','Yes');
note(p,'handoff','Staff continue at K and V.\nCustomer can return to B.',465,688,270,50);

p=page('Table or exclusive reservation','Standalone booking from the website or customer mobile app','E ← p.3 / p.9  |  E1 = retry booking details  |  V → super-admin review on p.8  |  B → customer home on p.3');
off(p,'e','E',269,110);
d(p,'type','Table booking?',205,215);
n(p,'size','Select table size',205,362,'input'); n(p,'guests','Enter exclusive-venue\nguest count',545,237,'input');
c(p,'retryentry','E1',120,481); n(p,'details','Enter future schedule\nand contact details',205,467,'input');
n(p,'food','Optional menu items\nand special requests',205,571,'input');
d(p,'method','Pay using\nGCash?',205,679);
n(p,'proof','Enter 13-digit reference\nand upload payment proof',545,700,'input');
d(p,'valid','Details and\nschedule valid?',205,835);
n(p,'err','Show errors; revise booking',545,857); c(p,'retry','E1',617,957);
n(p,'save','Save pending booking, fees\nand items; show reference',190,987,'process',210,60);
off(p,'v','V',452,991); off(p,'b','B',715,991);
chain(p,'e','type'); e(p,'type','size','Yes'); e(p,'type','guests','No','right','left');
e(p,'guests','details','','bottom','right',[[635,426],[465,426],[465,497]]);
chain(p,'size','details','food','method'); e(p,'retryentry','details','','right','left');
e(p,'method','proof','Yes','right','left'); e(p,'method','valid','No');
e(p,'proof','valid','','bottom','top',[[635,813],[295,813]]);
e(p,'valid','err','No','right','left'); chain(p,'err','retry'); e(p,'valid','save','Yes');
e(p,'save','v','','right','left');
note(p,'staff','Staff review',422,1048,115,20); note(p,'own','Customer home',674,961,110,20);
e(p,'save','b','','bottom','bottom',[[295,1068],[741,1068]]);

p=page('Cashier customer-order review','Pending online orders • payment confirmation • rejection','C = cashier home  |  K ← customer checkout  |  P → walk-in POS on p.6');
off(p,'c','C',239,110); off(p,'k','K',379,110); off(p,'pos','P',659,110);
note(p,'posnote','Walk-in sale → page 6',557,177,230,24);
n(p,'review','Open pending customer order',215,219,'process',220);
d(p,'edit','Edit order\nitems?',235,316); n(p,'adjust','Validate items; adjust\nstock and total',535,338); c(p,'back1','K1',739,349);
c(p,'entry','K1',138,230);
d(p,'reject','Reject order?',235,459);
n(p,'rejected','If allowed: reject order,\nrestore stock and update booking',525,479,'process',220,70); c(p,'back2','C',616,587);
d(p,'active','Linked booking\nstill active, if any?',235,603); n(p,'blocked','Show error; review\nbooking status',535,626); c(p,'back3','K1',739,637);
d(p,'payment','Payment valid?',235,750); n(p,'fix','Correct payment details\nand retry',535,772); c(p,'back4','K1',739,783);
note(p,'paynote','Cash must cover total due.\nGCash requires a valid reference.',55,850,200,65);
n(p,'paid','Mark order and linked booking\npayment as paid; clear hold',220,895,'process',210,70);
n(p,'receipt','Display official receipt',535,900,'input'); c(p,'again','C',607,1012);
e(p,'c','review','','bottom','top',[[265,194],[325,194]]); e(p,'k','review','','bottom','top',[[405,194],[325,194]]);
e(p,'entry','review','','right','left'); chain(p,'review','edit'); e(p,'edit','adjust','Yes','right','left'); e(p,'adjust','back1','','right','left');
e(p,'edit','reject','No'); e(p,'reject','rejected','Yes','right','left'); chain(p,'rejected','back2');
e(p,'reject','active','No'); e(p,'active','blocked','No','right','left'); e(p,'blocked','back3','','right','left');
e(p,'active','payment','Yes'); e(p,'payment','fix','No','right','left'); e(p,'fix','back4','','right','left');
e(p,'payment','paid','Yes'); e(p,'paid','receipt','','right','left'); chain(p,'receipt','again');
note(p,'action','Confirm payment',56,554,165,25);

p=page('Walk-in sale / point of sale','Cashier and super-admin access','P ← p.5 / p.7  |  P1 = retry sale  |  C → cashier home on p.5  |  D → super-admin dashboard on p.7');
off(p,'p','P',274,110); c(p,'entry','P1',160,236);
n(p,'cart','Select products\nand quantities',210,220,'input');
n(p,'payment','Select cash or GCash;\nenter payment details',210,330,'input');
d(p,'valid','Valid cart, stock\nand payment?',210,445);
n(p,'err','Show validation error;\nroll back any changes',550,467); c(p,'retry','P1',621,578);
n(p,'save','Create paid order\nand order items',210,598);
n(p,'stock','Deduct stock and\nrecord stock movements',210,703);
n(p,'receipt','Display printable receipt',210,808,'input');
d(p,'role','Cashier account?',210,913); off(p,'c','C',274,1025); off(p,'d','D',614,939);
chain(p,'p','cart','payment','valid'); e(p,'entry','cart','','right','left'); e(p,'valid','err','No','right','left'); chain(p,'err','retry');
e(p,'valid','save','Yes'); chain(p,'save','stock','receipt','role'); e(p,'role','c','Yes'); e(p,'role','d','No','right','left');
note(p,'report','Paid sales are included\nin dashboard sales and reports.',515,740,240,70);

p=page('Super-admin dashboard','Management modules and authorized staff activities','D = dashboard  |  V → reservation review on p.8  |  P → point of sale on p.6');
off(p,'d','D',224,110); n(p,'dashboard','Open management dashboard',160,207);
d(p,'reservation','Review\nreservations?',160,304); off(p,'v','V',584,330);
d(p,'pos','Process a\nwalk-in sale?',160,453); off(p,'p','P',584,479);
d(p,'manage','Manage system\nrecords?',160,602);
n(p,'modules','Products and inventory\nCustomer and cashier accounts\nAdmin password management\nPayment and security settings',505,592,'process',240,125);
c(p,'return1','D',606,762);
n(p,'reports','View sales reports, customer\nhistory and activity logs',140,757,'input',220,70);
d(p,'logout','Log out?',160,894); c(p,'again','D',526,927);
n(p,'end','End',186,1032,'terminator',128,40);
chain(p,'d','dashboard','reservation'); e(p,'reservation','v','Yes','right','left'); e(p,'reservation','pos','No');
e(p,'pos','p','Yes','right','left'); e(p,'pos','manage','No'); e(p,'manage','modules','Yes','right','left'); chain(p,'modules','return1');
e(p,'manage','reports','No'); chain(p,'reports','logout'); e(p,'logout','again','No','right','left'); e(p,'logout','end','Yes');
note(p,'validation','Changes require validation and\nserver-side authorization before saving.',470,837,285,50);

p=page('Reservation review and status','Super-admin booking decisions; payment is tracked separately','V ← p.3 / p.4 / p.7  |  V1 = review another booking  |  D → dashboard on p.7');
off(p,'v','V',214,110); c(p,'entry','V1',80,228);
n(p,'review','Open reservation details\nand payment proof, if supplied',140,208,'process',200,70);
d(p,'pending','Pending and\nunexpired?',150,326);
d(p,'confirm','Already\nconfirmed?',535,326);
d(p,'approve','Approve booking?',150,485);
n(p,'reject','Reject / cancel pending\nbooking and record history',535,507,'process',215,66);
d(p,'schedule','Future schedule\nand capacity valid?',150,646);
n(p,'error','Show conflict or\npast-schedule error',540,667); c(p,'retry','V1',611,767);
n(p,'confirmed','Mark confirmed; clear hold\nand record status history',140,807,'process',200,70);
n(p,'next','Later: mark completed\nor cancelled; record history',530,837,'process',220,70);
off(p,'dashboard','D',614,991);
n(p,'closed','Expired or terminal booking:\nno further status transition',520,212,'process',240,66);
chain(p,'v','review','pending'); e(p,'entry','review','','right','left'); e(p,'pending','confirm','No','right','left');
e(p,'confirm','closed','No','top','bottom'); e(p,'confirm','next','Yes','right','right',[[785,378],[785,872]]);
off(p,'closedreturn','D',729,118); e(p,'closed','closedreturn','','right','bottom',[[785,245],[785,186],[755,186]]);
e(p,'pending','approve','Yes'); e(p,'approve','reject','No','right','left');
e(p,'reject','dashboard','','right','right',[[775,540],[775,1017]]);
e(p,'approve','schedule','Yes'); e(p,'schedule','error','No','right','left'); chain(p,'error','retry');
e(p,'schedule','confirmed','Yes'); e(p,'confirmed','next','','right','left',[[430,842],[430,872]]); chain(p,'next','dashboard');
note(p,'separate','Approval does not mark payment as paid.\nCashier confirms payment for linked orders.\nPending holds can expire before approval.',95,944,375,90);

p=page('Mobile customer access','Android customer app • verified accounts • token authentication','M = mobile home  |  E → booking on p.4  |  K → cashier order review on p.5');
n(p,'start','Start',215,110,'terminator',160,60);
n(p,'login','Open app; enter\nusername / email and password',185,215,'input',220,66);
d(p,'valid','Valid login and\nnot locked out?',205,324);
n(p,'err','Show error or retry delay',540,346); c(p,'retry','M1',611,448); c(p,'entry','M1',116,229);
d(p,'customer','Customer with\nverified email?',205,480);
n(p,'denied','Reject login; verify email\nor use website for staff access',525,501,'process',235,70);
n(p,'token','Issue mobile access token',205,631);
d(p,'activity','Choose activity',205,744);
off(p,'booking','E',91,915); note(p,'booknote','Book a table\nor exclusive venue',50,977,145,50);
n(p,'order','Browse products; submit\nvalidated pending order',245,909,'process',220,65);
off(p,'cashier','K',329,1006);
n(p,'history','View own orders\nand reservations',535,765); c(p,'return','M',739,776);
n(p,'logout','Log out; revoke token',535,901); n(p,'end','End',567,1011,'terminator',120,48);
chain(p,'start','login','valid'); e(p,'entry','login','','right','left'); e(p,'valid','err','No','right','left'); chain(p,'err','retry');
e(p,'valid','customer','Yes'); e(p,'customer','denied','No','right','left'); e(p,'customer','token','Yes'); chain(p,'token','activity');
e(p,'activity','booking','Book','left','top',[[117,796]]); e(p,'activity','order','Order','bottom','top',[[295,881],[355,881]]);
e(p,'activity','history','History','right','left'); e(p,'history','return','','right','left');
e(p,'activity','logout','Logout','bottom','left',[[295,870],[493,870],[493,931]]); chain(p,'logout','end'); chain(p,'order','cashier');
note(p,'mobile','Cash orders may omit booking.\nGCash orders require a table booking.\nReturn to M after placing an order.',490,604,300,83);
c(p,'m','M',120,690); e(p,'m','activity','','right','top',[[175,709],[175,722],[295,722]]);

const esc=s=>String(s).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll('\n','&#xa;');
const styles={
 process:'rounded=0;', input:'shape=parallelogram;perimeter=parallelogramPerimeter;fixedSize=1;',
 decision:'rhombus;perimeter=rhombusPerimeter;', terminator:'ellipse;', connector:'ellipse;aspect=fixed;',
 offpage:'shape=offPageConnector;direction=south;', text:'text;strokeColor=none;fillColor=none;'
};
const anchors={top:[.5,0],bottom:[.5,1],left:[0,.5],right:[1,.5]};
function point(node,side){const a=anchors[side]; return [node.x+a[0]*node.w,node.y+a[1]*node.h];}
function cellNode(node) {
 const sz=node.kind==='terminator'?23:node.kind==='connector'||node.kind==='offpage'?16:node.kind==='text'?12:14;
 return `<mxCell id="${node.id}" value="${esc(node.text)}" style="${styles[node.kind]}whiteSpace=wrap;html=0;fillColor=#ffffff;strokeColor=#000000;strokeWidth=1.5;fontColor=#000000;fontFamily=Arial;fontSize=${sz};align=center;verticalAlign=middle;spacing=5;${node.kind==='text'?'strokeColor=none;fillColor=none;':''}" vertex="1" parent="1"><mxGeometry x="${node.x}" y="${node.y}" width="${node.w}" height="${node.h}" as="geometry"/></mxCell>`;
}
let xml='<mxfile host="app.diagrams.net" agent="Kermit Flowchart Generator" version="24.7.17" type="device">';
pages.forEach((p,idx)=>{
 const ids=new Set(p.nodes.map(n=>n.id)); if(ids.size!==p.nodes.length) throw new Error('Duplicate node');
 for(const node of p.nodes) if(node.x<30||node.x+node.w>820||node.y<100||node.y+node.h>1080) throw new Error(`Out of bounds: ${p.title}/${node.id}`);
 // Shapes should not overlap; text annotations are allowed to share surrounding whitespace.
 const shapes=p.nodes.filter(n=>n.kind!=='text');
 for(let i=0;i<shapes.length;i++)for(let j=i+1;j<shapes.length;j++){
  const a=shapes[i],b=shapes[j];
  if(a.x<b.x+b.w&&a.x+a.w>b.x&&a.y<b.y+b.h&&a.y+a.h>b.y) throw new Error(`Overlap: ${p.title}/${a.id}/${b.id}`);
 }
 xml+=`<diagram id="page-${idx+1}" name="${idx+1}. ${esc(p.title)}"><mxGraphModel dx="850" dy="1100" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="850" pageHeight="1100" math="0" shadow="0"><root><mxCell id="0"/><mxCell id="1" parent="0"/>`;
 xml+=`<mxCell id="border" value="" style="rounded=0;fillColor=none;strokeColor=#000000;strokeWidth=1;connectable=0;" vertex="1" parent="1"><mxGeometry x="30" y="92" width="790" height="990" as="geometry"/></mxCell>`;
 xml+=cellNode({id:'heading',text:`Flowchart ${idx+1} — ${p.title}`,x:35,y:20,w:780,h:28,kind:'text'}).replace('fontSize=12','fontSize=20;fontStyle=1');
 xml+=cellNode({id:'subtitle',text:p.subtitle,x:35,y:53,w:780,h:22,kind:'text'});
 // Legend is attached as page metadata, and as a small editable footer above the border.
 xml+=cellNode({id:'legend',text:p.foot,x:35,y:76,w:780,h:14,kind:'text'}).replace('fontSize=12','fontSize=9');
 p.edges.forEach((edge,i)=>{
  if(!ids.has(edge.a)||!ids.has(edge.b))throw new Error('Missing edge endpoint');
  const a=anchors[edge.from], b=anchors[edge.to];
  edge.points=[point(p.nodes.find(n=>n.id===edge.a),edge.from),...edge.via,point(p.nodes.find(n=>n.id===edge.b),edge.to)];
  const [s,t]=edge.points;
  edge.labelAt = Math.abs(s[0]-t[0])<1 ? [s[0]+24,(s[1]+t[1])/2] : [(s[0]+t[0])/2,(s[1]+t[1])/2-13];
  if(idx===1 && edge.a==='role') edge.labelAt = ({b:[467,444],cc:[627,447],dd:[728,444],legacy:[739,607]})[edge.b];
  if(idx===8 && edge.a==='activity' && edge.b==='order') edge.labelAt=[357,889];
  if(idx===8 && edge.a==='activity' && edge.b==='logout') edge.labelAt=[449,854];
  const lengths=edge.points.slice(1).map((pt,j)=>Math.hypot(pt[0]-edge.points[j][0],pt[1]-edge.points[j][1]));
  let remaining=lengths.reduce((v,z)=>v+z,0)/2, mid=edge.points[0];
  for(let j=0;j<lengths.length;j++) { if(remaining<=lengths[j]) {const r=lengths[j]?remaining/lengths[j]:0; mid=[edge.points[j][0]+(edge.points[j+1][0]-edge.points[j][0])*r,edge.points[j][1]+(edge.points[j+1][1]-edge.points[j][1])*r];break;}remaining-=lengths[j]; }
  xml+=`<mxCell id="edge-${i}" value="${esc(edge.label)}" style="edgeStyle=none;noEdgeStyle=1;rounded=0;html=0;endArrow=block;endFill=1;endSize=8;strokeColor=#000000;strokeWidth=1.3;fontFamily=Arial;fontSize=12;fontColor=#000000;labelBackgroundColor=#ffffff;exitX=${a[0]};exitY=${a[1]};exitDx=0;exitDy=0;entryX=${b[0]};entryY=${b[1]};entryDx=0;entryDy=0;" edge="1" parent="1" source="${edge.a}" target="${edge.b}"><mxGeometry relative="1" as="geometry">${edge.via.length?`<Array as="points">${edge.via.map(pt=>`<mxPoint x="${pt[0]}" y="${pt[1]}"/>`).join('')}</Array>`:''}<mxPoint x="${edge.labelAt[0]-mid[0]}" y="${edge.labelAt[1]-mid[1]}" as="offset"/></mxGeometry></mxCell>`;
 });
 xml+=p.nodes.map(cellNode).join('')+'</root></mxGraphModel></diagram>';
});
xml+='</mxfile>';
fs.writeFileSync(path.join(__dirname,'SYSTEM_FLOWCHART.drawio'),xml,'utf8');
fs.writeFileSync(path.join(__dirname,'flowchart-layout.json'),JSON.stringify({width:W,height:H,pages}),'utf8');
console.log(`Created ${pages.length} editable pages, ${pages.reduce((s,p)=>s+p.nodes.filter(n=>n.kind!=='text').length,0)} shapes and ${pages.reduce((s,p)=>s+p.edges.length,0)} arrows.`);
