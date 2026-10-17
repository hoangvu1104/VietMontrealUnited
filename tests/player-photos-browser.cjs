const { chromium } = require('../var/browser-test/node_modules/playwright');
const path = require('node:path');
(async () => {
 const browser = await chromium.launch({channel:'msedge',headless:true});
 try {
  const page = await browser.newPage({viewport:{width:1440,height:1000}});
  const errors=[]; page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://127.0.0.1:8770/index.php?page=login');
  await page.locator('[name=username]').fill('player1');
  await page.locator('[name=password]').fill('Player123!');
  await Promise.all([page.waitForURL(url=>url.searchParams.get('page')==='home'),page.getByRole('button',{name:'Sign in to club house ↗'}).click()]);
  await page.goto('http://127.0.0.1:8770/index.php?page=account');
  await page.locator('#player-photo').setInputFiles(path.resolve(__dirname,'../public/assets/images/club-logo.jpg'));
  await page.getByRole('button',{name:'Lưu ảnh đại diện',exact:true}).click();
  await page.getByRole('status').filter({hasText:'Đã cập nhật ảnh đại diện.'}).waitFor();
  await page.locator('.account-photo img').waitFor();
  if (!await page.locator('.account-photo img').evaluate(img=>img.complete && img.naturalWidth>0)) throw Error('Image not loaded');
  await page.screenshot({path:path.resolve(__dirname,'../var/screenshots/player-photo-desktop.png'),fullPage:true,animations:'disabled'});
  await page.setViewportSize({width:390,height:844});
  await page.screenshot({path:path.resolve(__dirname,'../var/screenshots/player-photo-mobile.png'),fullPage:true,animations:'disabled'});
  if (await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth)) throw Error('Mobile horizontal overflow');
  if (errors.length) throw Error(errors.join('\n'));
  console.log('PASS: browser login, JPG upload, rendered image, desktop/mobile layout, no JS errors.');
 } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exit(1)});


