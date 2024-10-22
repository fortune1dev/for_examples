// @ts-check
const { By, Builder, Capabilities } = require('selenium-webdriver');
const { suite } = require('selenium-webdriver/testing');
const chrome = require('selenium-webdriver/chrome');
const capabilities = Capabilities.chrome();
const assert = require("assert");
const { screenshotCompare } = require('./utils')

suite(function (env) {
    describe('TikTok screenshots', function () {
        let driver;
        before(async () => {
            const options = new chrome.Options();

            options.addArguments("--headless");
            options.addArguments("--no-sandbox");
            options.addArguments("--disable-gpu");
            options.addArguments("--window-size=1920,1080");

            driver = await new Builder().forBrowser('chrome')
                .usingServer("http://localhost:4444")
                .withCapabilities(capabilities)
                .setChromeOptions(options)
                .build();

            await driver.manage().setTimeouts({ implicit: 1000 });
            await driver.manage().window().maximize();
            await driver.get('https://dev-antiyo.uzavr.ru/blogger/tiktok/fake_avani_fake');
            await driver.sleep(3000);
        });

        after(async () => await driver.quit());

        it('Back to list', async () => {
            let container = await driver.findElement(By.css('a.back'));
            let encodedString = await container.takeScreenshot(true);

            assert.equal(screenshotCompare('TikTok Back to list', encodedString), 0);
        });

        it('Blogger analytics', async () => {
            let container = await driver.findElement(By.css('.main-title'));
            let encodedString = await container.takeScreenshot(true);

            assert.equal(screenshotCompare('TikTok Blogger analytics', encodedString), 0);
        });

        it('HINT: Average views per clip', async () => {
            let container = await driver.findElement(By.id('average-views-menu-link'));
            const actions = driver.actions();
            await actions.move({ origin: container }).click(container).perform();
            container = await driver.findElement(By.id('average-views-menu-text'));
            let encodedString = await container.takeScreenshot(true);

            assert.equal(screenshotCompare('TikTok HINT: Average views per clip', encodedString), 0);
        });

        it('HINT: Average comments per clip', async () => {
            let container = await driver.findElement(By.id('average-comments-menu-link'));
            const actions = driver.actions();
            await actions.move({ origin: container }).click(container).perform();
            container = await driver.findElement(By.id('average-comments-menu-text'));
            let encodedString = await container.takeScreenshot(true);

            assert.equal(screenshotCompare('TikTok HINT: Average comments per clip', encodedString), 0);
        });

        it('HINT: Average clip duration', async () => {
            let container = await driver.findElement(By.id('average-clip-menu-link'));
            const actions = driver.actions();
            await actions.move({ origin: container }).click(container).perform();
            container = await driver.findElement(By.id('average-clip-menu-text'));
            let encodedString = await container.takeScreenshot(true);

            assert.equal(screenshotCompare('TikTok HINT: Average clip duration', encodedString), 0);
        });

        it('HINT: Here are the main indicators of the channel', async () => {
            let container1 = await driver.findElement(By.id('blogger-main-menu-link'));
            let container = await driver.findElement(By.id('blogger-main-menu-text'));
            const actions = driver.actions();
            await actions.move({ origin: container1 }).click(container).perform();
            let encodedString = await container.takeScreenshot(true);

            assert.equal(screenshotCompare('TikTok HINT: Here are the main indicators of the channel', encodedString), 0);
        });
    });
});
