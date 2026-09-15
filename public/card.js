// One Canvas renderer powers both the live preview and the 1200px PNG export.
const loaded = new Map();
async function imageAt(url) {
  if (!url) return null;
  if (!loaded.has(url)) loaded.set(url, new Promise((resolve, reject) => {
    const image = new Image(); image.crossOrigin = 'anonymous';
    image.onload = () => resolve(image); image.onerror = () => { loaded.delete(url); reject(new Error('A card image could not load. Check the photo or storage permissions.')); };
    image.src = url;
  }));
  return loaded.get(url);
}
function fitText(ctx, text, x, y, width, size, weight = 600) {
  text = String(text ?? '');
  ctx.font = `${weight} ${size}px Arial`;
  while (ctx.measureText(text).width > width && size > 10) ctx.font = `${weight} ${--size}px Arial`;
  ctx.fillText(text, x, y, width);
}
function dateLabel(date) { return new Date(`${date}T12:00:00`).toLocaleDateString('en-US', {month:'long', day:'numeric', year:'numeric'}); }
function crop(ctx, image, x, y, width, height) {
  const scale = Math.max(width/image.width, height/image.height);
  const sw = width/scale, sh = height/scale;
  ctx.drawImage(image, (image.width-sw)/2, (image.height-sh)/2, sw, sh, x,y,width,height);
}
export async function renderCard(canvas, data) {
  const revision = (canvas.revision || 0) + 1; canvas.revision = revision;
  canvas.ready = false;
  const [photo, qr, logo] = await Promise.all([imageAt(data.photo), imageAt(data.qr), imageAt(data.logo)]);
  if (canvas.revision !== revision) return;
  const c = canvas.getContext('2d'); c.clearRect(0,0,1200,756);
  c.fillStyle='#f1f1e6'; c.fillRect(0,0,1200,756);
  c.strokeStyle='#dce0d0'; c.lineWidth=1;
  for(let x=-800;x<1400;x+=24){ c.beginPath(); c.moveTo(x,756);c.lineTo(x+850,0);c.stroke(); }
  c.fillStyle='#223c2d';c.fillRect(0,0,1200,118);
  c.fillStyle='#d4ddb7';fitText(c,data.header,42,52,950,34,700);
  c.fillStyle='#fff';fitText(c,data.subtitle,43,88,960,18,500);
  c.fillStyle='#b9c994';fitText(c,'SG / ID',1050,69,110,20,700);
  c.fillStyle='#dae0d2';c.fillRect(42,153,228,254);
  if(photo)crop(c,photo,42,153,228,254);
  else { c.fillStyle='#a3b09b';c.beginPath();c.arc(156,235,48,0,Math.PI*2);c.fill();c.beginPath();c.ellipse(156,379,87,87,0,Math.PI,0);c.fill(); }
  c.fillStyle='#23392a';fitText(c,data.name || 'MEMBER NAME',42,447,680,38,700);
  c.fillStyle='#617159';fitText(c,`MEMBER ID  ${data.memberId}`,43,481,660,20,700);
  c.fillStyle='#778669';fitText(c,'ORGANIZATION / ASSIGNMENT',316,178,570,15,700);
  c.fillStyle='#283d2b';data.organization.forEach((line,i)=>fitText(c,line,316,229+i*45,570,28,i===0?700:500));
  if(logo) { const scale=Math.min(160/logo.width,160/logo.height);c.drawImage(logo,950+(160-logo.width*scale)/2,175,logo.width*scale,logo.height*scale); }
  else { c.strokeStyle='#7c8c5b';c.lineWidth=4;c.beginPath();c.moveTo(1040,165);c.lineTo(1100,251);c.lineTo(1040,329);c.lineTo(980,251);c.closePath();c.stroke();c.fillStyle='#536947';fitText(c,'S',1020,263,60,48,700); }
  c.fillStyle=data.status==='revoked'?'#9f3832':data.status==='expired'?'#8a671f':'#405d36';
  fitText(c,String(data.status).toUpperCase(),949,378,220,18,700);
  c.strokeStyle='#b5bfab';c.lineWidth=1;c.beginPath();c.moveTo(316,383);c.lineTo(894,383);c.stroke();
  if(qr){c.imageSmoothingEnabled=false;c.drawImage(qr,42,514,178,178);c.imageSmoothingEnabled=true;}
  c.fillStyle='#768067';fitText(c,'SCAN TO VERIFY',244,551,250,14,700);
  fitText(c,'Community personnel record',244,579,300,17,400);
  c.fillStyle='#cdb975';c.beginPath();c.roundRect(565,552,106,83,14);c.fill();c.strokeStyle='#8c793e';c.lineWidth=2;c.stroke();
  for(let i=1;i<3;i++){c.beginPath();c.moveTo(565,552+i*27);c.lineTo(671,552+i*27);c.stroke();}c.strokeRect(598,552,40,83);
  c.fillStyle='#768067';fitText(c,'DECORATIVE',563,655,125,10,500);
  c.fillStyle='#748265';fitText(c,'ISSUED',772,538,385,14,700);fitText(c,'EXPIRES',772,618,385,14,700);
  c.fillStyle='#23392a';fitText(c,dateLabel(data.issue),772,574,385,24,600);fitText(c,dateLabel(data.expiration),772,654,385,24,600);
  c.fillStyle='#233c2b';c.fillRect(0,714,1200,42);c.fillStyle='#fff';fitText(c,'MILSIM / TRAINING · NOT A GOVERNMENT ID',42,741,800,15,700);
  fitText(c,data.disclaimer,850,741,310,11,500);
  canvas.ready=true;
}
function showError(error){const target=document.querySelector('[data-card-message]');if(target)target.textContent=error.message;else console.error(error);}
const views=[...document.querySelectorAll('[data-card-view]')];
for(const view of views){view.card=JSON.parse(view.dataset.card);view.render=()=>renderCard(view.querySelector('canvas'),view.card);view.render().catch(showError);}
const editor=document.querySelector('[data-card-editor]');
if(editor){
  const view=views[0],form=editor.querySelector('form'),field=name=>form.elements[`card[${name}]`];
  const initial=structuredClone(view.card);
  let portraitUrl=null,searchVersion=0;
  const update=()=>{
    const d=view.card;d.name=field('displayName').value;d.memberId=field('memberId').value;d.organization=[1,2,3].map(i=>field(`organizationLine${i}`).value);
    d.issue=field('issueDate').value;
    if(d.issue && !field('expirationOverride').checked){const [y,m,day]=d.issue.split('-').map(Number);const year=y+Number(editor.dataset.years);const last=new Date(year,m,0).getDate();field('expirationDate').value=`${year}-${String(m).padStart(2,'0')}-${String(Math.min(day,last)).padStart(2,'0')}`;}
    d.expiration=field('expirationDate').value;field('expirationDate').readOnly=!field('expirationOverride').checked;
    if(d.status!=='revoked')d.status=new Date(`${d.expiration}T23:59:59`)<new Date()?'expired':'active';
    const src=field('source').value,linked=src==='milhq'||src==='commandnet';
    editor.querySelector('[data-soldier-search]').hidden=!linked;
    if(linked){const label=editor.querySelector('[data-soldier-search-label]');if(label)label.textContent=src==='milhq'?'Search MILHQ personnel':'Search Command Net personnel';}
    field('milhqSoldierId').parentElement.hidden=src!=='milhq';
    if(field('commandNetSoldierId'))field('commandNetSoldierId').parentElement.hidden=src!=='commandnet';
    if(d.issue&&d.expiration)view.render().catch(showError);
  };
  form.addEventListener('input',update);form.addEventListener('change',update);
  form.addEventListener('reset',()=>setTimeout(()=>{view.card=structuredClone(initial);if(portraitUrl)URL.revokeObjectURL(portraitUrl);portraitUrl=null;update();},0));
  field('upload').addEventListener('change',async()=>{const file=field('upload').files[0];if(!file)return;if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>5*1024*1024){showError(new Error('Use a JPEG, PNG or WebP image smaller than 5 MB.'));field('upload').value='';return;}if(portraitUrl)URL.revokeObjectURL(portraitUrl);portraitUrl=URL.createObjectURL(file);view.card.photo=portraitUrl;field('photoPreference').value='custom';await view.render().catch(showError);});
  field('photoPreference').addEventListener('change',()=>{if(field('photoPreference').value==='default'){view.card.photo=null;field('upload').value='';view.render().catch(showError);}});
  editor.querySelector('[data-regenerate]').addEventListener('click',async()=>{try{const response=await fetch(editor.dataset.idUrl,{method:'POST',body:new URLSearchParams({_token:editor.dataset.token})});if(!response.ok)throw new Error('Could not regenerate Member ID.');field('memberId').value=(await response.json()).memberId;update();}catch(e){showError(e);}});
  const sourceLabel=src=>src==='milhq'?'MILHQ':'Command Net';
  const idFieldFor=src=>src==='milhq'?field('milhqSoldierId'):field('commandNetSoldierId');
  const resolveUrlFor=src=>src==='milhq'?editor.dataset.milhqResolveUrl:editor.dataset.commandnetResolveUrl;
  const searchUrlFor=src=>src==='milhq'?editor.dataset.milhqSearchUrl:editor.dataset.commandnetSearchUrl;
  const importSoldier=async(id)=>{const src=field('source').value;const idField=idFieldFor(src);if(!idField)return;try{const response=await fetch(resolveUrlFor(src).replace(/\/1$/,`/${id}`));if(!response.ok)throw new Error(`${sourceLabel(src)} record unavailable.`);const data=await response.json();idField.value=id;field('displayName').value=data.name;data.organization.forEach((line,i)=>field(`organizationLine${i+1}`).value=line);if(field('photoPreference').value!=='custom'){view.card.photo=data.photo;}editor.querySelector('[data-import-message]').textContent=data.warning||'Personnel imported. You can override the fields below.';update();}catch(e){showError(e);}};
  editor.querySelector('[data-import]').addEventListener('click',()=>{const idField=idFieldFor(field('source').value);if(idField&&idField.value)importSoldier(idField.value);});
  let timer;editor.querySelector('#soldier-query').addEventListener('input',event=>{clearTimeout(timer);const q=event.target.value;const version=++searchVersion;const src=field('source').value;const searchUrl=searchUrlFor(src);if(!searchUrl)return;timer=setTimeout(async()=>{try{const response=await fetch(`${searchUrl}?q=${encodeURIComponent(q)}`);if(!response.ok)throw new Error('Personnel search failed.');const data=await response.json();if(version!==searchVersion)return;const list=editor.querySelector('[data-soldier-results]');list.replaceChildren();if(!data.soldiers.length)list.textContent=data.available?'No soldiers found.':`${sourceLabel(src)} unavailable.`;data.soldiers.forEach(s=>{const b=document.createElement('button');b.type='button';b.textContent=s.name;b.onclick=()=>{importSoldier(s.id);list.replaceChildren();};list.append(b);});}catch(e){showError(e);}},250);});
  update();
}
document.querySelector('[data-download]')?.addEventListener('click',async event=>{
  const button=event.currentTarget;button.disabled=true;
  try{const view=views[0];await view.render();const canvas=view.querySelector('canvas');if(!canvas.ready)throw new Error('Card images are still loading.');const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/png'));if(!blob)throw new Error('PNG export failed.');const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download=`milsim-id-${view.card.memberId}.png`;a.hidden=true;document.body.append(a);a.click();setTimeout(()=>{a.remove();URL.revokeObjectURL(url);},60000);}
  catch(e){showError(new Error(`${e.message} If an image is on another host, its storage must permit cross-origin image access.`));}finally{button.disabled=false;}
});
