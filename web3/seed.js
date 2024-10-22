const { PrismaClient } = require('@prisma/client');
const fs = require('fs');
const EventEmitter = require('events');
const { ethers } = require('ethers');
const prisma = new PrismaClient();

let app = {
    config: JSON.parse(fs.readFileSync('config.json', 'utf8')), // считываются настройки из конфигурационного файла
    emitter: new EventEmitter(), // иницилизируется менеджер событий
    db: prisma, // подключаем работу с основной БД приложения
};

global.app = app;
const { logError } = require('../modules/helpers');

// app.handlers = require('../modules/handlers'); // Обработчики событий контрактов для телеграм бота
app.provider = require('../modules/provider'); // подключение к блокчейну для прослушивания смарт-контрактов приложения

const { UserLine5Graph } = require('../modules/Line5_matrix/UserGraph');
app.Line5graph = new UserLine5Graph(app.config.contracts.ID1.address, app.emitter) // создаем граф пользователей в памяти сервера

const { UserM3Graph } = require('../modules/M3_matrix/UserGraph');
app.M3graph = new UserM3Graph(app.config.contracts.ID1.address, app.emitter) // создаем граф пользователей в памяти сервера

const { UserM6Graph } = require('../modules/M6_matrix/UserGraph');
app.M6graph = new UserM6Graph(app.config.contracts.ID1.address, app.emitter) // создаем граф пользователей в памяти сервера

function sleep(ms) {
    return new Promise(res => setTimeout(res, ms));
} 

async function main() {
    const id1User = await prisma.user.upsert({
        where: { username: app.config.contracts.ID1.address },
        update: {},
        create: {
            username: app.config.contracts.ID1.address,
            userId: 1,
            referrerId: 0,
            Profile: {
                create: {},
            },
        },
    });
    const id1CUser = await prisma.ContractUserMM.upsert({
        where: { user: app.config.contracts.ID1.address },
        update: {},
        create: {
            user: app.config.contracts.ID1.address,
            userId: 1,
            referrerId: 0,
            referrer: '0x0000000000000000000000000000000000000000',
        },
    });

    const id1CM3User = await prisma.ContractUserM3.upsert({
        where: { user: app.config.contracts.ID1.address },
        update: {},
        create: {
            user: app.config.contracts.ID1.address,
            userId: 1,
            referrerId: 0,
            referrer: '0x0000000000000000000000000000000000000000',
        },
    });

    const id1CM6User = await prisma.ContractUserM6.upsert({
        where: { user: app.config.contracts.ID1.address },
        update: {},
        create: {
            user: app.config.contracts.ID1.address,
            userId: 1,
            referrerId: 0,
            referrer: '0x0000000000000000000000000000000000000000',
        },
    });
    const id1CL5User = await prisma.ContractUserL5.upsert({
        where: { user: app.config.contracts.ID1.address },
        update: {},
        create: {
            user: app.config.contracts.ID1.address,
            userId: 1,
            referrerId: 0,
            referrer: '0x0000000000000000000000000000000000000000',
        },
    });

    await prisma.transaction.create({
        data: {
            blockNumber: 0,
            blockHash: '0x',
            hash: "0x",
            from: "0x",
            to: "0x",
            type: 0,
            eventName: "Seed",
            contractName: "Seed"
        }
    })

    const { MatrixManager } = require('../modules/contract_handlers');

    // const blockNumber = 6618000; // ETH
    const blockNumber = 44010000;
    const blockRange = 10000;
    const latestBlock = (await app.provider.getBlock("latest")).number;
    const cAddresses = [
        app.config.contracts.mm.address,
    ];

    const mmABI = Object.values(require('../modules/abi/' + app.config.contracts.mm.abi))[0].filter(
        (el) => el.type === 'event'
    );
    const cMM = new ethers.Contract(app.config.contracts.mm.address, mmABI, app.provider);

    let topics = await cMM.filters.Registration().getTopicFilter();
    topics = topics.concat(await cMM.filters.Upgrade().getTopicFilter());
    topics = topics.concat(await cMM.filters.SendTokens().getTopicFilter());

    let currentLastBlock = blockNumber + blockRange < latestBlock ? blockNumber + blockRange : latestBlock;
    const combinedFilter = {
        address: cAddresses,
        topics: [topics],
        fromBlock: blockNumber,
        toBlock: currentLastBlock,
    };

    while (latestBlock >= currentLastBlock) {
        const result = await app.provider.getLogs(combinedFilter);

        for (let event of result) {
            const block = await app.provider.getBlock(event.blockHash);

            if (event.address == app.config.contracts.mm.address) {
                const parsedLogs = cMM.interface.parseLog(event);

                switch (parsedLogs.name) {
                    case 'Registration':
                        await MatrixManager.RegistrationHandler(...parsedLogs.args, event, block.timestamp);
                        break;
                    case 'Upgrade':
                        await MatrixManager.UpgradeHandler(...parsedLogs.args, event, block.timestamp);
                        break;
                    case 'SendTokens':
                        await MatrixManager.SendTokensHandler(...parsedLogs.args, event, block.timestamp);
                        break;

                    default:
                        break;
                }
            } 
            await sleep(50);

        }

        if (currentLastBlock == latestBlock) {
            break;
        }

        combinedFilter.fromBlock = currentLastBlock;
        currentLastBlock = currentLastBlock + blockRange < latestBlock ? currentLastBlock + blockRange : latestBlock;
        combinedFilter.toBlock = currentLastBlock;
    }

}
main()
    .then(async () => {
        await prisma.$disconnect();
        process.exit(0);
    })
    .catch(async (e) => {
        logError(e);
        await prisma.$disconnect();
        process.exit(1);
    });
