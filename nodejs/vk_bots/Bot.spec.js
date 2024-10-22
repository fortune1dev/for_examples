const assert = require('assert');
const { BotFactory } = require('../libs/bot');
const { it } = require('mocha');
const path = require('node:path');

describe('SSOL classes ', () => {
    describe('Bot', () => {
        it('#isOnline()', async () => {
            const bot = await BotFactory.makeBot();
            assert.ok(await bot.isOnline());
        });

        it('#doLike() post', async () => {
            const bot = await BotFactory.makeBot();
            const result = await bot.doLike('https://vk.com/mypublic_2?w=wall-218001921_823', 'post');
            assert.ok(result);
        });

        it('#doLike() comment', async () => {
            const bot = await BotFactory.makeBot();
            const result = await bot.doLike('https://vk.com/mypublic_2?w=wall-218001921_823', 'comment', 911);
            assert.ok(result);
        });

        it('#addAudio()', async () => {
            const bot = await BotFactory.makeBot();
            const result = await bot.addAudio('https://vk.com/audio-2001213656_58213656');
            assert.ok(result);
        });

        it('#addVideo()', async () => {
            const bot = await BotFactory.makeBot();
            const result = await bot.addVideo('https://vk.com/video-24226428_456239563');
            assert.ok(result);
        });

        describe('#doRepost()', () => {
            context('toFavorite === true', () => {
                it('toFavorite === true', async () => {
                    const bot = await BotFactory.makeBot();
                    const result = await bot.doRepost('https://vk.com/video-24226428_456239563');
                    assert.ok(result);
                });
            });
            context('toFavorite === false', () => {
                it('toFavorite === false', async () => {
                    const bot = await BotFactory.makeBot();
                    const result = await bot.doRepost('https://vk.com/video-24226428_456239563', false);
                    assert.ok(result);
                });
            });
        });

        it('#doMessage()', async () => {
            const bot = await BotFactory.makeBot();
            const result = await bot.doMessage(90272461, 'Привет бро!');
            assert.ok(result);
        });

        describe('#doComment()', () => {
            context('make primary comment', () => {
                it('make primary comment', async () => {
                    const bot = await BotFactory.makeBot();
                    const result = await bot.doComment('https://vk.com/mypublic_2?w=wall-218001921_823', 'Привет бро!');
                    assert.ok(result);
                });
            });
            context('make SUBcomment', () => {
                it('make SUBcomment', async () => {
                    const bot = await BotFactory.makeBot();
                    const result = await bot.doComment(
                        'https://vk.com/mypublic_2?w=wall-218001921_823',
                        'Привет бро!',
                        911
                    );
                    assert.ok(result);
                });
            });
        });

        describe('#doJoinToGroup()', () => {
            context('not member yet', () => {
                it('not member yet', async () => {
                    const bot = await BotFactory.makeBot();
                    const result = await bot.doJoinToGroup('218001921');
                    assert.ok(result);
                });
            });
            context('already member', () => {
                it('already member', async () => {
                    const bot = await BotFactory.makeBot('all', 0, 93);
                    const result = await bot.doJoinToGroup('218001921');
                    assert.ok(result);
                });
            });
        });

        it('#doPost()', async () => {
            const bot = await BotFactory.makeBot();
            const result = await bot.doPost('Привет МИР!', path.resolve(__dirname + path.sep + 'test_img.jpg'));
            assert.ok(result);
        });

        it('#doStories()', async () => {
            const bot = await BotFactory.makeBot();
            const result = await bot.doStories(
                'https://vk.com/ivan_kabakov',
                path.resolve(__dirname + path.sep + 'test_img.jpg'),
                'Привет МИР!'
            );
            assert.ok(result);
        });

        describe('#doFriendAdd()', () => {
            context('isOnline = any', () => {
                it('#doFriendAdd()', async () => {
                    const bot = await BotFactory.makeBot();
                    const result = await bot.doFriendAdd(90272461);
                    assert.ok(result);
                });
            });
            context('isOnline = true', () => {
                it('#doFriendAdd()', async () => {
                    const bot = await BotFactory.makeBot();
                    const result = await bot.doFriendAdd(90272461, true);
                    assert.ok(result);
                });
            });
        });

        it('#doSetAvatar()', async () => {
            const bot = await BotFactory.makeBot();
            const result = await bot.doSetAvatar(path.resolve(__dirname + path.sep + 'test_img.jpg'));
            assert.ok(result);
        });
    });
});
