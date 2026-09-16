const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch();
  const page = await browser.newPage();
  
  page.on('console', msg => {
    console.log('PAGE LOG:', msg.text());
  });

  page.on('pageerror', error => {
    console.log('PAGE ERROR:', error.message);
  });

  await page.goto('http://127.0.0.1:8000/staff', { waitUntil: 'networkidle0' });

  // Get the content of the staff table body
  const tableBody = await page.$eval('#staffTableBody', el => el.innerHTML);
  console.log('Table Body Length:', tableBody.length);
  
  await browser.close();
})();
