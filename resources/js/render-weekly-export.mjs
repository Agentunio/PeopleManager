import puppeteer from 'puppeteer';

const chunks = [];

for await (const chunk of process.stdin) {
    chunks.push(chunk);
}

const input = JSON.parse(Buffer.concat(chunks).toString('utf8'));

const browser = await puppeteer.launch({
    executablePath: input.chromePath,
    headless: true,
    args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-crashpad',
        '--disable-gpu',
    ],
});

try {
    const page = await browser.newPage();

    await page.setContent(input.pdfHtml, { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => document.fonts.ready);
    await page.pdf({
        path: input.pdfPath,
        format: 'A4',
        landscape: true,
        margin: { top: 0, right: 0, bottom: 0, left: 0 },
        printBackground: true,
    });

    await page.setViewport({ width: 1600, height: 900 });
    await page.setContent(input.pngHtml, { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => document.fonts.ready);
    await page.screenshot({
        path: input.pngPath,
        type: 'png',
        fullPage: true,
    });
} finally {
    await browser.close();
}
