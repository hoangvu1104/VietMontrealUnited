const { chromium } = require('../var/browser-test/node_modules/playwright');
const fs = require('node:fs');
const path = require('node:path');
(async () => {
 const accounts=fs.readFileSync(path.resolve(__dirname,'../var/local-preview-accounts.txt'),'utf8');
 const adminPassword=accounts.match(/ADMIN\s+Username: admin\s+Password: (\S+)/)[1];
 const playerPassword=accounts.match(/Username: an\.tran\s+Password: (\S+)/)[1];
 const browser=await chromium.launch({channel:'msedge',headless:true});
 let checks=0;
 const check=(ok,label)=>{if(!ok)throw Error(label);console.log('PASS: '+label);checks++};
 try {
  const page=await browser.newPage({viewport:{width:1440,height:1000}});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  const base='http://127.0.0.1:8780/index.php?page=';
  for(const route of ['home','players','matches','statistics','login']) {
   const response=await page.goto(base+route);check(response.status()===200,'Public '+route);
  }
  await page.goto(base+'players');check(await page.locator('.squad-card').count()===21,'21 real squad cards');
  await page.screenshot({path:path.resolve(__dirname,'../var/screenshots/local-preview-squad-desktop.png'),fullPage:true,animations:'disabled'});
  await page.setViewportSize({width:390,height:844});
  await page.screenshot({path:path.resolve(__dirname,'../var/screenshots/local-preview-squad-mobile.png'),fullPage:true,animations:'disabled'});
  check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Squad mobile without horizontal overflow');
  await page.setViewportSize({width:1440,height:1000});
  await page.goto(base+'login');
  await page.locator('[name=username]').fill('admin');await page.locator('[name=password]').fill(adminPassword);
  await page.getByRole('button',{name:'Sign in to club house ↗'}).click();await page.waitForURL('**page=admin');check(true,'Preview Admin login');
  for(const route of ['admin','admin-players','admin-player-edit','admin-matches','admin-match-edit','admin-attendance','admin-stats','admin-users','admin-settings','account']) {
   const response=await page.goto(base+route);check(response.status()===200,'Admin '+route);
  }
  await page.goto(base+'admin-players');
  check(await page.locator('body').innerText().then(t=>t.includes('Tuan Nguyen')&&t.includes('Steven Ha')),'Admin roster includes corrected players');
  await page.getByRole('button',{name:'Log out ↗'}).click();
  await page.locator('[name=username]').fill('an.tran');await page.locator('[name=password]').fill(playerPassword);
  await page.getByRole('button',{name:'Sign in to club house ↗'}).click();await page.waitForURL('**page=home');check(true,'Preview player login');
  await page.goto(base+'account');check(await page.locator('#player-photo').count()===1,'Own photo form available');
  await page.screenshot({path:path.resolve(__dirname,'../var/screenshots/local-preview-account-desktop.png'),fullPage:true,animations:'disabled'});
  await page.setViewportSize({width:390,height:844});
  await page.screenshot({path:path.resolve(__dirname,'../var/screenshots/local-preview-account-mobile.png'),fullPage:true,animations:'disabled'});
  check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Account mobile without horizontal overflow');
  await page.goto(base+'profile');check(await page.getByRole('link',{name:'Cập nhật ảnh đại diện'}).count()===1,'Own profile links to photo update');
  const denied=await page.goto(base+'admin-players');check(denied.status()===403,'Player cannot access Admin');
  check(errors.length===0,'No browser JavaScript errors');
  console.log(`${checks} local-preview browser checks passed.`);
 } finally {await browser.close()}
})().catch(e=>{console.error(e);process.exit(1)});
