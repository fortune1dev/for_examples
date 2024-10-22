/**
 * @file Классы для авторизации ботов и их работы
 */

//@ts-check

require('dotenv').config();
const { VK, CallbackService, resolveResource, APIError, PhotoAttachment, StoryAttachment } = require('vk-io');
const { HttpsProxyAgent } = require('https-proxy-agent');
const { PrismaClient } = require('@prisma/client');
const { chromium } = require('playwright');
const logger = require('./logger');

const prisma = new PrismaClient();
const { CAPTCHA_KEY } = process.env;

/**
 * Класс для создания объекта для авторизации Бота в VK
 */
class BotIdentity {
    /**
     * @type {Object}
     */
    _botAccount = null;
    _taskID = 0;
    _id = 0;
    _gender = 'all';

    /**
     * @param {number} id если данный параметр === 0 то берется рендомная запись из БД,
     * а иначе запись где id === параметру id
     * @param {number} taskID ID задачи из которой происходит вызов
     */
    constructor(gender = 'all', taskID = 0, id = 0) {
        this._id = id;
        this._taskID = taskID;
        this._gender = gender;
    }

    /**
     * Т.к. конструктор класса не может быть ассинхронным, то для получения данных аккаунта
     * необходимо использовать этот метод.
     * Метод читает данные из БД и инициализирует _botAccount.
     * Плюс к этому, выводит "бота" в онлайн.
     */
    async getBot() {
        if (this.id > 0) {
            await this.getBotAccountByID(this.id)
                .then(async () => {
                    await this.setOnline();
                })
                .catch((error) => {
                    logger.error(Object.assign(error, { job_id: this.taskID, botID: this.id }));
                });
        } else {
            await this.getRandomBotAccount(this.gender)
                .then(async () => {
                    await this.setOnline();
                })
                .catch((error) => {
                    logger.error(Object.assign(error, { job_id: this.taskID, botID: this.id }));
                });
        }
    }

    get id() {
        return this._id;
    }

    get gender() {
        return this._gender;
    }

    get taskID() {
        return this._taskID;
    }

    get botAccount() {
        return this._botAccount;
    }

    async setOnline() {
        const agent = new HttpsProxyAgent(this.botAccount.proxy);

        const vk = new VK({
            token: this.botAccount.token,
            agent,
        });

        // @ts-ignore
        await vk.api.account.setOnline();
    }

    /**
     * Достает из БД данные аккаунта по ID записи в таблице
     * @param {number} botID ID бота в таблице БД
     */
    async getBotAccountByID(botID) {
        const result = await prisma.bot.findFirst({
            where: { id: botID },
        });

        // @ts-ignore
        if (result.length === 0) throw new Error('getBotAccountByID: не смогли получить ID аккаунта для бота');

        // @ts-ignore
        result.proxy = 'http://' + result.proxy;
        this._botAccount = result;
    }

    /**
     * Ассинхронный метод. Получает ID записи случайного аккаунта из БД.
     * @param {string} gender пол аккаунта, одно из трех занчений [all|M|F]
     * @returns {Promise<number>} ID записи в БД, но если ботов нет, то вернет false
     */
    async getRandomBotAccountID(gender = 'all') {
        const result = await prisma.bot.findMany({
            where: {
                status: 'Offline',
                active: 1,
                gender: ['M', 'F'].includes(gender) ? gender : undefined,
            },
        });

        if (result.length > 0) {
            const id = Math.round(Math.random() * (result.length - 1));
            return result[id].id;
        } else {
            return 0;
        }
    }

    /**
     * Получает случайный аккаунт для бота
     * @param {string} gender пол аккаунта, одно из трех занчений [all|M|F]
     */
    async getRandomBotAccount(gender = 'all') {
        if (!['M', 'F', 'all'].includes(gender)) gender = 'all';

        const botID = await this.getRandomBotAccountID(gender);

        if (botID === 0) throw new Error('getRandomBotAccount: не смогли получить ID аккаунта для бота');
        await this.getBotAccountByID(botID);
    }
}

/**
 * Класс для создания объекта бота.
 * При создании получает данные авторизации для объекта бота.
 */
class Bot {
    /**
     *
     * @param {BotIdentity} Identity объект с аутоинтификационными данными для бота в VK
     */
    constructor(Identity) {
        this.taskID = Identity.taskID;
        this.Account = Identity.botAccount;

        const agent = new HttpsProxyAgent(this.Account.proxy);
        this.VK = new VK({
            token: this.Account.token,
            agent,
        });
    }

    /**
     *
     * @param {string} url Обрабатывает капчу через автоматизированный вервис
     * @returns
     */
    async processingCaptcha(url) {
        try {
            const imageUrlData = await fetch(url);
            const buffer = await imageUrlData.arrayBuffer();
            const stringifiedBuffer = Buffer.from(buffer).toString('base64');
            const contentType = imageUrlData.headers.get('content-type');
            const imageBase64 = `data:${contentType};base64,${stringifiedBuffer}`;

            const response = await fetch('https://ocr.captchaai.com/solve.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8',
                    'Accept-Charset': 'utf-8',
                },
                body: `method=base64&json=1&key=${CAPTCHA_KEY}&module=vk-fast&body=${encodeURIComponent(imageBase64)}`,
            });

            const result = await response.json();
            // return Buffer.from(result.request, 'utf-8').toString();
            return result.request;
        } catch (error) {
            logger.error('Error processingCaptcha:', error, { job_id: this.taskID, botID: this.Account.id });
            return false;
        }
    }

    async validateAccount(redirectUri) {
        let browser;
        try {
            const url = this.Account.proxy.split('@')[1];
            const tmp = this.Account.proxy.replace('http://', '').split('@')[0];
            const user = tmp.split(':')[0];
            const pass = tmp.split(':')[1];

            browser = await chromium.launch({
                proxy: {
                    server: 'http://' + url,
                    username: user,
                    password: pass,
                },
            });

            const page = await browser.newPage();
            await page.goto(redirectUri.replace('validate', 'captcha'));

            const imgUrl = await page.locator('img.captcha_img').getAttribute('src');

            const captchaKey = await this.processingCaptcha(imgUrl ?? '');

            await page.locator('input[name=captcha_key]').fill(captchaKey);
            await page.locator('input[type=submit]').click();
            const pageResponse = page.url();
            await browser.close();

            logger.info('accountValidation result: %s. Result URL: %s', captchaKey, pageResponse, {
                job_id: this.taskID,
                botID: this.Account.id,
            });
            return pageResponse;
        } catch (error) {
            logger.error('accountValidation error:', error, { job_id: this.taskID, botID: this.Account.id });
            // @ts-ignore
            await browser.close();
            return '#';
        }
    }

    /**
     * Используем отдельный обработчик, что бы ловить Validation (17) и User blocked (5)
     * @param {Object} error объект ошибки
     * @returns {Promise<number>} код ошибки VK API или 1
     */
    async errorHandler(error) {
        logger.error(Object.assign(error, { job_id: this.taskID, botID: this.Account.id }));

        switch (parseInt(error.code)) {
            case 17: {
                let result;
                do {
                    result = await this.validateAccount(error.redirectUri);
                    if (result === '#') return 0;
                    // @ts-ignore
                } while (result !== 'https://oauth.vk.com/blank.html#success=1');
                return parseInt(error.code);
            }
            case 5: {
                await prisma.bot.update({
                    where: {
                        id: this.Account.id,
                    },
                    data: {
                        status: 'Blocked',
                        active: 0,
                    },
                });
                return parseInt(error.code);
            }
            default:
                if (error instanceof APIError) {
                    // @ts-ignore
                    return error.code;
                }
                return 1;
        }
    }

    async setOnline() {
        let error_code = 0;
        do {
            try {
                return await this.VK.api.account.setOnline({});
            } catch (error) {
                error_code = await this.errorHandler(error);
            }
        } while (error_code === 17);
        return false;
    }

    /**
     *
     * @returns {Promise<boolean>}
     */
    async isOnline() {
        try {
            const result = await this.VK.api.users.get({
                fields: ['online'],
            });
            // @ts-ignore
            return result[0].online == 1;
        } catch (error) {
            await this.errorHandler(error);
            return false;
        }
    }

    /**
     * Ставит лайк
     * @param {string} url ссылка на реусрс VK
     * @param {string} type тип ресурса для лайка
     * @param {number|undefined} item_id id ресурса, как правило используется для лайка комментария
     *
     * Возможные типы:
     * * post — запись на стене пользователя или группы;
     * * comment — комментарий к записи на стене;
     * * photo — фотография;
     * * audio — аудиозапись;
     * * video — видеозапись;
     * * note — заметка;
     * * market — товар;
     * * photo_comment — комментарий к фотографии;
     * * video_comment — комментарий к видеозаписи;
     * * topic_comment — комментарий в обсуждении;
     * * market_comment — комментарий к товару.
     * @returns
     */
    async doLike(url, type, item_id = undefined) {
        let error_code = 0;
        do {
            try {
                const resource = await resolveResource({
                    api: this.VK.api,
                    resource: url,
                });

                const result = await this.VK.api.likes.add({
                    // @ts-ignore
                    type: type,
                    // @ts-ignore
                    owner_id: resource.ownerId,
                    item_id: item_id ? item_id : resource.id,
                });
                logger.info('Success like added by botID: %d', this.Account.id, {
                    job_id: this.taskID,
                    result,
                });

                return result;
            } catch (error) {
                error_code = await this.errorHandler(error);
            }
        } while (error_code === 17);
        return false;
    }

    /**
     * Добавляет трек на стену бота
     * @param {string} url ссылка на трек в VK
     * @returns 1 если все успешно.
     */
    async addAudio(url) {
        let error_code = 0;
        do {
            try {
                const resource = await resolveResource({
                    api: this.VK.api,
                    resource: url,
                });

                if (resource.type !== 'audio') {
                    throw new Error('resolveResource: тип ресурса не распознан.  taskID:' + this.taskID);
                }

                // @ts-ignore
                const result = await this.VK.api.audio.add({
                    audio_id: resource.id,
                    // @ts-ignore
                    owner_id: resource.ownerId,
                });
                logger.info('Success audio added for botID: %d', this.Account.id, {
                    job_id: this.taskID,
                    result,
                });

                return result;
            } catch (error) {
                error_code = await this.errorHandler(error);
            }
        } while (error_code === 17);
        return false;
    }

    /**
     * Остальные методы для работы с VK
     * Остальные методы для работы с VK
     * Остальные методы для работы с VK
     * Остальные методы для работы с VK
     * Остальные методы для работы с VK
     * Остальные методы для работы с VK
     * Остальные методы для работы с VK
     * Остальные методы для работы с VK
     * Остальные методы для работы с VK
     * Остальные методы для работы с VK
     * Остальные методы для работы с VK
     * Остальные методы для работы с VK
     * Остальные методы для работы с VK
     * Остальные методы для работы с VK
     * Остальные методы для работы с VK
     * Остальные методы для работы с VK
     * Остальные методы для работы с VK
     *
     */
}

/**
 * Простая фабрика ботов
 */
class BotFactory {
    /**
     * Возвращает объект класса Bot
     *
     * @param {string} gender пол аккаунта, одно из трех занчений [all|M|F]
     * @param {number} botID ID записи бота в БД
     * @param {number} taskID id
     * @returns {Promise<Bot>} объект (экземпляр) класса Bot
     */
    static async makeBot(taskID = 0, gender = 'all', botID = 0) {
        const Identity = new BotIdentity(gender, taskID, botID);
        await Identity.getBot();
        return new Bot(Identity);
    }
}

exports.Bot = Bot;
exports.BotFactory = BotFactory;
exports.BotIdentity = BotIdentity;
