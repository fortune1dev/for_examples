// @ts-check
import { test, expect } from '@playwright/test';
const { hexToRgb } = require('./utils');

/** @type {import('@playwright/test').Page} */
let page;

test.beforeAll(async ({ browser }) => {
  page = await browser.newPage();
  await page.goto('https://dev-antiyo.uzavr.ru/blogger/tiktok/fake_avani_fake');
});

test.afterAll(async () => {
  await page.close();
});

test.describe('Наличие фильтров в шапке', () => {
  test('Есть шапка сайта', async () => {
    await expect.soft(page.locator('header')).toBeVisible();
  });

  test('Блок фильтра раскрыт/закрыт', async () => {
    await page.locator('.search > a').click();
    await expect.soft(page.locator('div .filters')).toBeVisible();
    await page.locator('.search > a').click();
    await expect.soft(page.locator('div .filters')).not.toBeVisible();
  });
});

test.describe('Проверка наличия пробелов в сокращениях', () => {
  test('Number of subscribers', async () => {
    const rightblock = page.locator('.color-animate.bloger-about__number').nth(0);
    await expect.soft(rightblock).toContainText('42.5 M');
  });

  test('Number of likes', async () => {
    const rightblock = page.locator('.color-animate.bloger-about__number').nth(3);
    await expect.soft(rightblock).toContainText('2.9 B');
  });

  test('Number of subscriptions', async () => {
    const rightblock = page.locator('.color-animate.bloger-about__number').nth(1);
    await expect.soft(rightblock).toContainText('5.1 K');
  });

  test('Левый блок, справа от логина', async () => {
    const leftblock = page.locator('.color-animate.about-mini-card__subscribers');
    await expect.soft(leftblock).toContainText('42.5 M');
  });

  test('Подсписчиков в Instagram', async () => {
    const leftblock = page.locator('.about-mini-card__subscribers:has-text("18.7 M")');
    await expect.soft(leftblock).toContainText('18.7 M');
  });

  test('Average views per clip', async () => {
    const container = page.locator('.color-animate.info-stat__number').nth(0);
    await expect.soft(container).toContainText('2.6 M');
  });

  test('Average comments per clip', async () => {
    const container = page.locator('.color-animate.info-stat__number').nth(1);
    await expect.soft(container).toContainText('3.0 K');
  });

  test('Number of clips', async () => {
    const container = page.locator('#blogger-about-posts-count');
    expect.soft((await container.innerText()).trim()).toMatch(/21$/);
  });

  test('Average clip duration', async () => {
    const container = page.locator('#blogger-clip-duration');
    expect.soft((await container.innerText()).trim()).toMatch(/29$/);
  });
});

test.describe('Проверяем всплывающие подсказки', () => {
  test('Number of subscribers', async () => {
    const container = page.locator('.color-animate.bloger-about__number').nth(0);
    await container.hover();
    await expect.soft(container.locator('div.view-count-all')).toBeVisible();
  });

  test('Number of subscriptions', async () => {
    const container = page.locator('.color-animate.bloger-about__number').nth(1);
    await container.hover();
    await expect.soft(container.locator('div.view-count-all')).toBeVisible();
  });

  test('Number of clips', async () => {
    const container = page.locator('.color-animate.bloger-about__number').nth(2);
    await container.hover();
    await expect.soft(container.locator('div.view-count-all')).toHaveCount(0);
  });

  test('Number of likes', async () => {
    const container = page.locator('.color-animate.bloger-about__number').nth(3);
    await container.hover();
    await expect.soft(container.locator('div.view-count-all')).toBeVisible();
  });

  test('Number of mutual likes', async () => {
    const container = page.locator('.color-animate.bloger-about__number').nth(4);
    await container.hover();
    await expect.soft(container.locator('div.view-count-all')).toHaveCount(0);
  });

  test('NUMBERS Average views per clip', async () => {
    const container = page.locator('.color-animate.info-stat__number').nth(0);
    await container.hover();
    await expect.soft(container.locator('div.view-count-all')).toBeVisible();
  });

  test('NUMBERS Average comments per clip', async () => {
    const container = page.locator('.color-animate.info-stat__number').nth(1);
    await container.hover();
    await expect.soft(container.locator('div.view-count-all')).toBeVisible();
  });

  test('NUMBERS Average clip duration', async () => {
    const container = page.locator('.color-animate.info-stat__number').nth(2);
    await container.hover();
    await expect.soft(container.locator('div.view-count-all')).toHaveCount(0);
  });

  test('HINTS Average views per clip', async () => {
    const container = page.locator('#average-views-menu-link');
    await container.hover();
    await expect.soft(container.locator('#average-views-menu-text')).toBeVisible();
  });

  test('HINTS Average comments per clip', async () => {
    const container = page.locator('#average-comments-menu-link');
    await container.hover();
    await expect.soft(container.locator('#average-comments-menu-text')).toBeVisible();
  });

  test('HINTS Average clip duration', async () => {
    const container = page.locator('#average-clip-menu-link');
    await container.hover();
    await expect.soft(container.locator('#average-clip-menu-text')).toBeVisible();
  });
});

test.describe('Совпадение значений', () => {
  test('Совпадение Number of subscribers', async () => {
    const leftblock = page.locator('.color-animate.about-mini-card__subscribers');
    const rightblock = page.locator('.color-animate.bloger-about__number').nth(0);
    await expect.soft(leftblock).toHaveText(await rightblock.innerText());
  });

  test('Совпадение имен', async () => {
    const top_name = await page.locator('.color-animate.about-mini-card__fio').innerText();
    const bottom_name = await page.locator('.color-animate.bloger-about__fio').innerText();
    expect.soft(top_name).toEqual(bottom_name);
  });

  test('Совпадение логинов', async () => {
    const top_name = await page.locator('.login.about-mini-card__login a.login__name').innerText();
    const bottom_name = await page.locator('.login.bloger-about__login a.login__name').innerText();
    expect.soft(top_name).toEqual(bottom_name);
  });
});

test.describe('Подстветки при наведении мышки', () => {
  test('Подсветка Number of subscribers', async () => {
    const container = page.locator('.color-animate.bloger-about__number').nth(0);
    await container.hover();
    // @ts-ignore
    await expect.soft(container).toHaveCSS('color', hexToRgb('#ff836b'));
  });

  test('Подсветка Number of subscriptions', async () => {
    const container = page.locator('.color-animate.bloger-about__number').nth(1);
    await container.hover();
    // @ts-ignore
    await expect.soft(container).toHaveCSS('color', hexToRgb('#ff836b'));
  });

  test('Нет подсветски Number of clips', async () => {
    const container = page.locator('.color-animate.bloger-about__number').nth(2);
    await container.hover();
    // @ts-ignore
    await expect.soft(container).not.toHaveCSS('color', hexToRgb('#ff836b'));
  });

  test('Подсветка Number of likes', async () => {
    const container = page.locator('.color-animate.bloger-about__number').nth(3);
    await container.hover();
    // @ts-ignore
    await expect.soft(container).toHaveCSS('color', hexToRgb('#ff836b'));
  });

  test('Нет подсветски Number of mutual likes', async () => {
    const container = page.locator('.color-animate.bloger-about__number').nth(4);
    await container.hover();
    // @ts-ignore
    await expect.soft(container).not.toHaveCSS('color', hexToRgb('#ff836b'));
  });

  test('Подсветка Average views per clip', async () => {
    const container = page.locator('.color-animate.info-stat__number').nth(0);
    await container.hover();
    // @ts-ignore
    await expect.soft(container).toHaveCSS('color', hexToRgb('#ff836b'));
  });

  test('Подсветка Average comments per clip', async () => {
    const container = page.locator('.color-animate.info-stat__number').nth(1);
    await container.hover();
    // @ts-ignore
    await expect.soft(container).toHaveCSS('color', hexToRgb('#ff836b'));
  });

  test('Нет подсветски Average clip duration', async () => {
    const container = page.locator('.color-animate.info-stat__number').nth(2);
    await container.hover();
    // @ts-ignore
    await expect.soft(container).not.toHaveCSS('color', hexToRgb('#ff836b'));
  });

  test('Подсветка Instagram', async () => {
    const container = page.locator('a.bloger-another-soc__link').nth(0);
    await container.hover();
    // @ts-ignore
    await expect.soft(container).toHaveCSS('color', hexToRgb('#ff836b'));
  });

  test('Подсветка TikTok', async () => {
    const container = page.locator('a.bloger-another-soc__link').nth(1);
    await container.hover();
    // @ts-ignore
    await expect.soft(container).toHaveCSS('color', hexToRgb('#ff836b'));
  });

  test('Подсветка логина в табе', async () => {
    const container = page.locator('.about-mini-card__info >> a.login__name');
    await container.hover();
    // @ts-ignore
    await expect.soft(container).toHaveCSS('background-color', hexToRgb('#ff836b'));
  });

  test('Подсветка логина в основном блоке', async () => {
    const container = page.locator('.bloger-about__info >> a.login__name');
    await container.hover();
    // @ts-ignore
    await expect.soft(container).toHaveCSS('background-color', hexToRgb('#ff836b'));
  });
});

test.describe('Наличие данных', () => {
  test('Аватарка маленькая', async () => {
    const container = page.locator('#blogger-ava-small');
    await expect.soft(container).toHaveAttribute('src', 'https://358305.selcdn.ru/Asup_media/avani_avatar.jpg');
  });

  test('Аватарка большая', async () => {
    const container = page.locator('#blogger-ava-big');
    await expect.soft(container).toHaveAttribute('src', 'https://358305.selcdn.ru/Asup_media/avani_avatar.jpg');
  });

  test('Связь с Instagram', async () => {
    const container = page.locator('a.bloger-about-tab');
    await expect.soft(container).toHaveCount(1);
  });

  test('Статус Archived', async () => {
    const container = page.locator('#blogger-status');
    await expect.soft(container).toHaveCount(1);
    await expect.soft(container).toContainText("archived");
  });

  test('Есть пол и возраст', async () => {
    const container = page.locator('#blogger-gender-age');
    await expect.soft(container).toHaveCount(1);
    await expect.soft(container).toContainText("female, 19");

  });

  test('Верификация блогера отсутсвует', async () => {
    const container = page.locator('.verification-animate.verification-icon.login__verification');
    await expect.soft(container).toHaveCount(0);
  });
});
