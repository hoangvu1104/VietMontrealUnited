// Optional browser QA: npm install --prefix var/browser-test playwright
const { chromium } = require('../var/browser-test/node_modules/playwright');
const fs = require('node:fs');
const path = require('node:path');
(async () => {
  const browser = await chromium.launch({channel:'msedge',headless:true});
  const page = await browser.newPage({viewport:{width:1440,height:1100}});
  const errors=[];
  page.on('pageerror', e=>errors.push(e.message));
  const base=process.env.TEST_URL || 'http://127.0.0.1:8765';
  const folder=path.resolve(__dirname,'../var/screenshots'); fs.mkdirSync(folder,{recursive:true});
  await page.goto(base);
  await page.getByRole('heading',{name:'Club overview.'}).waitFor();
  await page.screenshot({path:path.join(folder,'desktop.png'),fullPage:true});
  await page.getByRole('link',{name:'Member login ↗'}).click();
  await page.getByLabel('Username',{exact:true}).fill('player1');
  await page.getByLabel('Password',{exact:true}).fill('Player123!');
  await page.getByRole('button',{name:'Sign in to club house ↗'}).click();
  await page.getByRole('button',{name:'✓ Yes, I will play'}).click();
  await page.getByRole('status').filter({hasText:'Đã cập nhật xác nhận tham gia.'}).waitFor();
  await page.getByRole('link',{name:'My profile',exact:false}).click();
  await page.getByRole('heading',{name:'Recent matches'}).waitFor();
  await page.setViewportSize({width:390,height:844});
  await page.goto(base);
  await page.screenshot({path:path.join(folder,'mobile.png'),fullPage:true});
  let overflow=await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth);
  if(overflow) throw Error('Mobile home overflows');
  await page.getByRole('button',{name:'Toggle menu'}).click();
  if(await page.getByRole('button',{name:'Toggle menu'}).getAttribute('aria-expanded')!=='true') throw Error('Menu did not open');
  await page.getByRole('link',{name:'Our squad',exact:false}).click();
  // A full page navigation closes the menu.
  await page.getByPlaceholder('Search name or jersey').fill('Minh');
  await page.getByRole('button',{name:'Search',exact:true}).click();
  await page.getByRole('heading',{name:'Minh Nguyen'}).waitFor();
  overflow=await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth);
  if(overflow) throw Error('Mobile players overflows');
  for (const route of ['statistics','matches','match&id=8','profile&id=1']) {
    await page.goto(base+'/index.php?page='+route);
    if(await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth)) throw Error('Mobile overflow: '+route);
  }
  await page.setViewportSize({width:1440,height:1000});
  await page.getByRole('button',{name:'Log out ↗'}).click();
  await page.getByLabel('Username',{exact:true}).fill('admin');
  await page.getByLabel('Password',{exact:true}).fill('Admin123!');
  await page.getByRole('button',{name:'Sign in to club house ↗'}).click();
  await page.getByRole('heading',{name:'Club dashboard.'}).waitFor();
  await page.screenshot({path:path.join(folder,'admin.png'),fullPage:true});
  for(const route of ['admin-players','admin-matches','admin-attendance','admin-stats','admin-users','admin-player-edit','admin-match-edit']) {
    await page.setViewportSize({width:390,height:844});
    await page.goto(base+'/index.php?page='+route);
    if(await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth)) throw Error('Mobile overflow: '+route);
  }
  await page.goto(base+'/index.php?page=admin-player-edit&id=1');
  const fields=await page.locator('form').filter({has:page.locator('input[value="save-player"]')}).locator('input:not([type="hidden"]),select,textarea').evaluateAll(nodes=>nodes.map(n=>n.name));
  if(JSON.stringify(fields)!==JSON.stringify(['full_name','jersey_number','birth_year','is_active'])) throw Error('Player form must have exactly four fields');
  if(await page.getByLabel('Số áo',{exact:true}).getAttribute('type')!=='text') throw Error('Jersey input must be text');
  await page.screenshot({path:path.join(folder,'player-form-mobile.png'),fullPage:true});
  await page.setViewportSize({width:1440,height:1000});
  await page.screenshot({path:path.join(folder,'player-form-desktop.png'),fullPage:true});
  await browser.close();
  if(errors.length) throw Error(errors.join('\n'));
  console.log('PASS: desktop/mobile screenshots, player login and RSVP, profile, mobile menu, search, admin login, 11 mobile route overflow checks; no JavaScript errors.');
})().catch(e=>{console.error(e);process.exit(1)});
