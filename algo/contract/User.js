const MATRIX_LEVELS = 12; // количество уровней в М6
const ADDRESS_0 = '0x0000000000000000000000000000000000000000';
const emitter = app.emitter;

const { M6 } = require('./Matrix');
const { ProfitType } = require('@prisma/client');
const { logError } = require('../helpers');

class User {
    /**
     * @param {*} id
     * @param {*} address
     * @param {*} referrer
     * @param {*} workingMode
     * @param {*} transactionHash
     */
    constructor(id, address, referrer, workingMode = false, transactionHash = '0x') {
        this.id = id;
        this.address = address;
        this.referrer = referrer; // под кем пользователь зарегистрирован
        this.workingMode = workingMode;

        this.transactionHash = Array(MATRIX_LEVELS).fill('0x'); // transactionHash с которым пользователь был зарегистрирован
        this.transactionHash[0] = transactionHash;

        this.currentReferrer = Array(MATRIX_LEVELS).fill(null); // под кем находится в текущий момент и в конкретной матрице
        this.currentPlace = Array(MATRIX_LEVELS).fill(null); // на каком месте находится в текущий момент и в конкретной матрице

        this.referrals = new Map(); //  рефералов.

        this.activeLevel = Array(MATRIX_LEVELS).fill(false);
        this.activeLevel[0] = true; // первый уровень активен сразу при регистрации

        this.frozenLevel = Array(MATRIX_LEVELS).fill(true);
        this.frozenLevel[0] = false; // первый уровень разморожен сразу при регистрации

        this.levelPrice = Array(MATRIX_LEVELS).fill(0);
        this.levelPrice.reduce((accumulator, currentValue, currentIndex) => {
            this.levelPrice[currentIndex] = accumulator;
            return (currentValue = accumulator * 2);
        }, 10);

        this.M6 = new M6();

        // console.log(`Created user ${this.address} id: ${this.id} referrer: ${this.referrer.id}`);
    }

    /**
     * Выбирает свободное место в матрице пользователя
     *
     * @param {*} user
     * @param {*} level
     * @param {*} rootPlace
     * @returns
     */
    findRelatedFreePlace(user, level, rootPlace = false) {
        if (this.M6.findFreePlace(level) === false) {
            logError(
                'findRelatedFreePlace: нет свободных мест у пользователя %s (place: %s, level: %s)',
                this.id,
                rootPlace,
                level
            );
            return false;
        }

        const matrix = this.M6.get(level);
        const referrerAddress = user.getCurrentReferrer(level).address;

        for (let i = 0; i < matrix.length; i++) {
            if (
                matrix[i] == ADDRESS_0 &&
                (user.referrer.address == this.address ||
                    (rootPlace !== false && matrix[i]?.address == referrerAddress))
            ) {
                return i;
            }
        }

        return false;
    }

    /**
     * Добавляет пользователя в "матрицу"
     * Необходимо помнить, что в терминах контракта, мы добавляем пользователя аплайнеру,
     * т.е. this - это аплайнер user
     *
     * @param {*} user
     * @param {*} level
     * @param {*} transactionHash
     * @param {*} rootPlace
     * @returns
     */
    setUserInMatrix(user, level = 0, transactionHash = '0x', rootPlace = false) {
        if (transactionHash != '0x') {
            user.transactionHash[level] = transactionHash;
        }

        const userPlace = this.findRelatedFreePlace(user, level, rootPlace);
        if (userPlace === false) return false;

        this.placeUserInMatrix(user, level, userPlace);
        this.handleMatrixUpdates(user, level, userPlace, rootPlace);

        return true;
    }

    placeUserInMatrix(user, level, userPlace) {
        const matrix = this.M6.get(level);
        matrix[userPlace] = user;
        user.setCurrentPlace(userPlace, level);

        if (this.isWorkingMode()) {
            const event = [];
            event.push({
                user: this.address,
                EventID: 'MatrixM6-NewUserPlace',
                params: ['M6', 1, level, userPlace],
            });

            emitter.emit('MatrixM6-NewUserPlace', event);
        }
    }

    handleMatrixUpdates(user, level, userPlace, rootPlace) {
        let isReinvested = false;
        let firstLine = false;

        if (userPlace === 0 || userPlace === 1) {
            firstLine = true;
            user.setCurrentReferrer(this, level);
        } else if (userPlace > 1 && userPlace < 6) {
            if (this.id != 1) {
                const upRef = this.findActiveReferrer(this, level);
                user.setCurrentReferrer(upRef, level);
            }

            this.firstLineMatrixUpdate(user, level, userPlace);
            user.saveFakeProfit(
                user.getCurrentReferrer(level),
                this,
                level,
                ProfitType.M6UPLINER,
                user.transactionHash[level]
            );
        } else {
            logError(
                `Найдено неправильное место ${userPlace} на уровне ${level} в матрицы пользователя ${this.id} для пользователя ${user.id}`
            );
            return false;
        }

        if (this.checkReinvest(level)) {
            isReinvested = true;
            this.makeReinvest(user, level, userPlace);
        }

        const thisReceiver = this.findReceiver(user, level, isReinvested);

        if (!isReinvested && !firstLine) {
            if (rootPlace === false && !this.isFrozen()) {
                user.saveTrueProfit(thisReceiver, level, ProfitType.DIRECT, user.transactionHash[level]);
            } else if (rootPlace == false && this.isFrozen()) {
                user.saveTrueProfit(thisReceiver, level, ProfitType.ADDITIONAL, user.transactionHash[level]);
            }
        }

        if (this.id == 1 && this.address == user.getCurrentReferrer(level).address && rootPlace === false) {
            if (firstLine) {
                return user.saveFakeProfit(this, this, level, ProfitType.M6UPLINER, user.transactionHash[level]);
            }
            return user.saveTrueProfit(this, level, ProfitType.DIRECT, user.transactionHash[level]);
        }

        if (this.id != 1 && this.address == user.getCurrentReferrer(level).address) {
            const upRef = this.findActiveReferrer(this, level);
            this.setCurrentReferrer(upRef, level);

            if (firstLine) {
                const { isReinvested, userPlace } = upRef.secondLineMatrixUpdate(
                    user,
                    level,
                    this.getCurrentPlace(level)
                );

                const upRefReceiver = upRef.findReceiver(user, level, isReinvested);

                if (!isReinvested && userPlace !== false && !upRef.isFrozen()) {
                    user.saveTrueProfit(upRefReceiver, level, ProfitType.DIRECT, user.transactionHash[level]);
                }

                if (thisReceiver.address != upRefReceiver.address) {
                    user.saveFakeProfit(
                        thisReceiver,
                        upRefReceiver,
                        level,
                        ProfitType.M6UPLINER,
                        user.transactionHash[level]
                    );
                }
            }
        }
    }

    /**
     * Метод осуществляет попытку позиционирования пользователя вниз по матрице, в ПЕРВУЮ линию
     *
     * @param {*} user
     * @param {*} level
     * @param {*} place
     */
    firstLineMatrixUpdate(user, level, place) {
        const matrix = this.M6.get(level);

        if (place == 2 || place == 4) {
            if (matrix[0].M6.get(level)[0] == ADDRESS_0) {
                matrix[0].M6.get(level)[0] = user;
                user.setCurrentReferrer(matrix[0], level);
                user.setCurrentPlace(0, level);
                return 0;
            } else if (matrix[0].M6.get(level)[1] == ADDRESS_0) {
                matrix[0].M6.get(level)[1] = user;
                user.setCurrentReferrer(matrix[0], level);
                user.setCurrentPlace(1, level);
                return 1;
            }
        } else if (place == 3 || place == 5) {
            if (matrix[1].M6.get(level)[0] == ADDRESS_0) {
                matrix[1].M6.get(level)[0] = user;
                user.setCurrentReferrer(matrix[1], level);
                user.setCurrentPlace(0, level);
                return 0;
            } else if (matrix[1].M6.get(level)[1] == ADDRESS_0) {
                matrix[1].M6.get(level)[1] = user;
                user.setCurrentReferrer(matrix[1], level);
                user.setCurrentPlace(1, level);
                return 1;
            }
        }

        return false;
    }

    /**
     * Метод осуществляет попытку позиционирования пользователя вниз по матрице, во ВТОРУЮ линию
     *
     * @param {*} user
     * @param {*} level
     * @param {*} place
     * @returns
     */
    secondLineMatrixUpdate(user, level, place) {
        let userPlace = false;
        let isReinvested = false;
        const matrix = this.M6.get(level);

        if (place == 0) {
            if (matrix[2] == ADDRESS_0) {
                matrix[2] = user;
                userPlace = 2;
            } else if (matrix[4] == ADDRESS_0) {
                matrix[4] = user;
                userPlace = 4;
            }
        } else if (place == 1) {
            if (matrix[3] == ADDRESS_0) {
                matrix[3] = user;
                userPlace = 3;
            } else if (matrix[5] == ADDRESS_0) {
                matrix[5] = user;
                userPlace = 5;
            }
        }

        if (this.checkReinvest(level)) {
            isReinvested = true;
            this.makeReinvest(user, level, userPlace);
        }
        return { isReinvested, userPlace };
    }

    /**
     *
     * @param {*} level
     * @returns
     */
    checkReinvest(level = 0) {
        return this.M6.checkReinvest(level);
    }

    /**
     * Реинвест матрицы пользователя this
     *
     * @param {*} user - пользователь КОТОРЫЙ привел к реинвесту this
     * @param {*} level
     * @param {*} place
     */
    makeReinvest(user, level = 0, place) {
        console.log(`M6 makeReinvest userID: ${user.id} --> ${this.id}`);

        const receiver = this.id == 1 ? this : this.findActiveReferrer(this, level);
        user.saveFakeProfit(this, receiver, level, ProfitType.REINVEST, user.transactionHash[level]);

        if (this.id != 1) {
            user.saveTrueProfit(receiver, level, ProfitType.DIRECT, user.transactionHash[level]);
        }

        if (level < MATRIX_LEVELS && this.isBlocked(level + 1)) {
            if (this.id != 1) {
                this.freeze(level);
            }
        }

        this.setReinvestor(user, level);
        this.resetMatrix(level);
        this.increaseReinvestCount(level);

        if (this.id != 1) {
            const activeReferrer = this.findActiveReferrer(this, level);
            if (activeReferrer) {
                activeReferrer.setUserInMatrix(this, level, user.transactionHash[level], place);
            }
        }

        if (this.isWorkingMode()) {
            const event = [];
            event.push({
                user: receiver.address,
                EventID: 'MatrixM6-Reinvest',
                params: ['M6', 1, level, place],
            });

            emitter.emit('MatrixM6-Reinvest', event);
        }
    }

    /**
     * Сохраняет фэйковую транзакцию, которая не отправляет токены,
     * а только создает запись в базе данных. Эта функция используется для учета
     * фиктивных операций, которые не влияют на балансы пользователей, но необходимы
     * для отслеживания и анализа внутренних процессов системы.
     *
     * @param {User} receiver - Объект получателя, который должен получить "прибыль".
     * @param {User} looser - Объект пользователя, который упустил или потерял
     * прибыль на данном уровне.
     * @param {number} level - Уровень матрицы.
     * @param {string} type - Тип транзакции.
     * @param {string} transactionHash - Хеш транзакции.
     * @returns {void} - Функция не возвращает значения, а только создает запись в базе данных.
     */
    saveFakeProfit(receiver, looser, level, type, transactionHash) {
        if (!this.isWorkingMode()) return;

        /**
         * ..............
         * ..............
         */
    }

    /**
     * Сохраняет реальную транзакцию, которая отправляет токены
     * и создает запись в базе данных. Эта функция используется для учета реальных
     * операций, которые влияют на балансы пользователей.
     *
     * @param {User} receiver - Объект получателя, который должен получить реальную прибыль.
     * @param {number} level - Уровень матрицы.
     * @param {string} type - Тип транзакции.
     * @param {string} transactionHash - Хеш транзакции
     * @returns {void} - Функция не возвращает значения, а только создает запись в базе данных.
     */
    saveTrueProfit(receiver, level, type, transactionHash) {
        if (!this.isWorkingMode()) return;

        /**
         * ..............
         * ..............
         */

        const event = [];
        event.push({
            user: receiver.address,
            EventID: 'MatrixM6-SentDividends',
            params: [this.address, receiver.address],
        });

        emitter.emit('MatrixM6-SentDividends', event);
    }

    /**
     * Ищет реферала с активной матрицей
     *
     * @param {*} user
     * @param {*} level
     * @returns
     */
    findActiveReferrer(user, level = 0) {
        let currentUser = user.getCurrentReferrer(level);

        if (!currentUser) {
            return null;
        }

        while (currentUser && currentUser.id != 1) {
            if (!currentUser.isBlocked(level)) {
                return currentUser;
            }

            currentUser = currentUser.getCurrentReferrer(level);
        }

        return currentUser;
    }

    /**
     * Ищет реферала с РАЗМОРОЖЕННОЙ матрицей
     *
     * @param {*} from
     * @param {*} level
     * @returns
     */
    findReceiver(from, level, isReinvested) {
        const amount = this.levelPrice[level];
        let receiver = this;

        while (receiver && receiver.id != 1) {
            if (!receiver.isFrozen(level)) {
                return receiver;
            }

            if (this.isWorkingMode()) {
                const position = this.M6.get(level).findIndex((element) => element.id === from.id);
                /**
                 * ..............
                 * ..............
                 */

                if (this.isWorkingMode()) {
                    const event = [];
                    event.push({
                        user: receiver.address,
                        EventID: 'MatrixM6-MissedReceive',
                        params: [from.address, receiver.address],
                    });

                    emitter.emit('MatrixM6-MissedReceive', event);
                }
            }

            receiver = receiver.getCurrentReferrer(level);
        }

        return receiver;
    }

    setReinvestor(user, level) {
        this.M6.matrixReinvestUser[level].push(user.id);
    }

    // Обновляет currentReferrer
    setCurrentReferrer(user, level = 0) {
        this.currentReferrer[level] = user;
    }

    // getter currentReferrer
    getCurrentReferrer(level = 0) {
        return this.currentReferrer[level];
    }

    // Обновляет currentPlace
    setCurrentPlace(place, level = 0) {
        this.currentPlace[level] = place;
    }

    // getter currentPlace
    getCurrentPlace(level = 0) {
        return this.currentPlace[level];
    }

    // Блокирует матрицу
    block(level = 0) {
        this.activeLevel[level] = false;
    }

    // Разблокирует матрицу
    unblock(level = 0) {
        this.activeLevel[level] = true;
    }

    // Возвращает статус блокировки матрицы
    isBlocked(level = 0) {
        return !this.activeLevel[level];
    }

    // Замораживает матрицу
    freeze(level = 0) {
        this.frozenLevel[level] = true;
    }

    // Размораживает матрицу
    unfreeze(level = 0) {
        this.frozenLevel[level] = false;
    }

    // Возвращает статус заморозки матрицы
    isFrozen(level = 0) {
        return this.frozenLevel[level];
    }

    // Обнуляет матрицу
    resetMatrix(level = 0) {
        this.M6.resetMatrix(level);
    }

    // Увеличивает reinvestCount
    increaseReinvestCount(level = 0) {
        this.M6.increaseReinvestCount(level);
    }

    // Возвращает количество повторных инвестиций пользователя
    getReinvestCount(level = 0) {
        return this.M6.getReinvestCount(level);
    }

    // Добавляет ОБЪЕКТ пользователя в список рефералов
    addReferral(user) {
        this.referrals.set(user.address, user);
    }

    /**
     * Установка эмиттера событий для записи транзакций в БД
     * Инициализируется в загрузчике матрицы при старте приложения.
     *
     * @param {*} workingMode
     */
    setEmitter(workingMode = false) {
        this.workingMode = workingMode;
    }

    isWorkingMode() {
        return this.workingMode;
    }

    getLineNumber() {
        const error = new Error();
        const stack = error.stack.split('\n');
        // Первая строка стека обычно содержит информацию о самом вызове new Error()
        // Вторая строка содержит информацию о вызове функции, которая вызвала new Error()
        const line = stack[2].trim();
        return line;
    }
}

module.exports = { User };
