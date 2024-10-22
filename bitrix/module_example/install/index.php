<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Config\Option;

Loc::loadMessages(__FILE__);

class fbl_donationevents extends CModule
{
    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';
        $this->MODULE_ID = 'fbl.donationevents';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = 'FBL Инсталлятор';
        $this->MODULE_DESCRIPTION = "Инсталлятор инфоблоков для раздела Cобытий";
        $this->PARTNER_NAME = Loc::getMessage('DonationEvents_PARTNER_NAME');
        $this->PARTNER_URI = Loc::getMessage('DonationEvents_PARTNER_URI');
    }

    public function DoInstall()
    {
        ModuleManager::registerModule($this->MODULE_ID);
        $this->InstallDB();
        $this->InstallFiles();
    }

    public function DoUninstall()
    {
        $this->UnInstallDB();
        $this->UnInstallFiles();
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    public function InstallDB()
    {
        // Создание нового типа инфоблоков
        $this->createIblockType();

        // Создание нового инфоблока в этом типе
        $iblockId = $this->createIblock();

        // Создание свойств для инфоблока
        $this->createIblockProperties($iblockId);

        // Создание хайлоад инфоблоков
        $this->createHighloadIblocks();
    }

    public function UnInstallDB()
    {
        // Удаление инфоблоков и свойств
        $this->deleteIblocs();
        $this->deleteHighloadIblocks();
    }

    public function InstallEvents()
    {
        // Здесь вы должны добавить код для установки событий
    }

    public function UnInstallEvents()
    {
        // Здесь вы должны добавить код для удаления событий
    }

    public function InstallFiles()
    {
        // Копирование файлов компонента модуля
    }

    public function UnInstallFiles()
    {
        // Удаление файлов компонента модуля
    }

    private function createIblockType()
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $iblockType = [
            'ID' => 'donations',
            'SECTIONS' => 'Y',
            'LANG' => [
                'ru' => [
                    'NAME' => 'Пожертвования',
                    'SECTION_NAME' => 'Разделы',
                    'ELEMENT_NAME' => 'Элементы',
                ],
                'en' => [
                    'NAME' => 'Donations',
                    'SECTION_NAME' => 'Sections',
                    'ELEMENT_NAME' => 'Elements',
                ],
            ],
        ];

        $obBlocktype = new CIBlockType;
        $res = $obBlocktype->Add($iblockType);
        if (!$res) {
            throw new Error("Ошибка создания типа инфоблока Пожертвования {$obBlocktype->LAST_ERROR}");
        }
    }

    private function createIblock()
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $iblock = [
            'NAME' => 'События',
            'CODE' => 'events',
            "API_CODE" => 'events', // обязательно, иначе не будет работать ORM!
            'IBLOCK_TYPE_ID' => 'donations',
            'SITE_ID' => ['s1'],
            'GROUP_ID' => ['2' => 'R'],
            'DETAIL_PAGE_URL' => '#SITE_DIR#/events/#ELEMENT_ID#/',
            'LIST_PAGE_URL' => '#SITE_DIR#/events/',
            'SECTION_PAGE_URL' => '#SITE_DIR#/events/',
        ];
        $ib = new CIBlock;
        $result['events'] = $ib->Add($iblock);
        if (!$result['events']) {
            throw new Error("Ошибка создания инфоблока {$ib->LAST_ERROR}");
        }
        Option::set($this->MODULE_ID, 'events_id', $result['events']);

        $iblock = [
            'NAME' => 'Истории',
            'CODE' => 'stories',
            "API_CODE" => 'stories', // обязательно, иначе не будет работать ORM!
            'IBLOCK_TYPE_ID' => 'donations',
            'SITE_ID' => ['s1'],
            'GROUP_ID' => ['2' => 'R'],
            'DETAIL_PAGE_URL' => '#SITE_DIR#/events_stories/#ELEMENT_ID#/',
            'LIST_PAGE_URL' => '#SITE_DIR#/events_stories/',
            'SECTION_PAGE_URL' => '#SITE_DIR#/events_stories/',
        ];
        $ib = new CIBlock;
        $result['stories'] = $ib->Add($iblock);
        if (!$result['stories']) {
            throw new Error("Ошибка создания инфоблока {$ib->LAST_ERROR}");
        }
        Option::set($this->MODULE_ID, 'stories_id', $result['stories']);

        $iblock = [
            'NAME' => 'ЧАВо',
            'CODE' => 'faq',
            "API_CODE" => 'faq', // обязательно, иначе не будет работать ORM!
            'IBLOCK_TYPE_ID' => 'donations',
            'SITE_ID' => ['s1'],
            'GROUP_ID' => ['2' => 'R'],
            'DETAIL_PAGE_URL' => '#SITE_DIR#/events/faq/#ELEMENT_ID#/',
            'LIST_PAGE_URL' => '#SITE_DIR#/events/faq/',
            'SECTION_PAGE_URL' => '#SITE_DIR#/events/faq/',
        ];
        $ib = new CIBlock;
        $result['faq'] = $ib->Add($iblock);
        if (!$result['faq']) {
            throw new Error("Ошибка создания инфоблока {$ib->LAST_ERROR}");
        }
        Option::set($this->MODULE_ID, 'faq_id', $result['faq']);

        return $result;
    }

    private function createIblockProperties($iblockId)
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $properties = [
            [
                'NAME' => 'ID сделки в Б24',
                'CODE' => 'DEAL_ID',
                'PROPERTY_TYPE' => 'N',
                'IBLOCK_ID' => $iblockId['events'],
            ],
            [
                'NAME' => 'Статус',
                'CODE' => 'STATUS',
                'PROPERTY_TYPE' => 'L',
                'VALUES' => [
                    ['VALUE' => 'На проверке', "SORT" => "100", 'XML_ID' => 'C19:NEW'],
                    ['VALUE' => 'На модерации', "SORT" => "200", 'XML_ID' => 'C19:PREPARATION'],
                    ['VALUE' => 'Есть ошибки', "SORT" => "300", 'XML_ID' => 'C19:PREPAYMENT_INVOIC'],
                    ['VALUE' => 'Проверено', "SORT" => "400", 'XML_ID' => 'C19:EXECUTING'],
                    ['VALUE' => 'Завершено', "SORT" => "500", 'XML_ID' => 'C19:WON'],
                    ['VALUE' => 'Провалено', "SORT" => "600", 'XML_ID' => 'C19:LOSE'],
                ],
                'IBLOCK_ID' => $iblockId['events'],
            ],
            [
                'NAME' => 'Тип сбора',
                'CODE' => 'TYPE',
                'PROPERTY_TYPE' => 'L',
                'VALUES' => [
                    ['VALUE' => 'День рождения', "SORT" => "100", 'XML_ID' => '4939'],
                    ['VALUE' => 'Мастер-класс', "SORT" => "200", 'XML_ID' => '4940'],
                    ['VALUE' => 'Сбор в память о пациенте', "SORT" => "300", 'XML_ID' => '4941'],
                    ['VALUE' => 'Забег/пробежка', "SORT" => "300", 'XML_ID' => '4942'],
                    ['VALUE' => 'Концерт/выступление', "SORT" => "300", 'XML_ID' => '4943'],
                    ['VALUE' => 'Новый год', "SORT" => "300", 'XML_ID' => '4944'],
                    ['VALUE' => 'Свадьба', "SORT" => "300", 'XML_ID' => '4945'],
                    ['VALUE' => 'Просто так', "SORT" => "300", 'XML_ID' => '4946'],
                    ['VALUE' => 'Другое', "SORT" => "300", 'XML_ID' => '4947'],
                ],
                'IBLOCK_ID' => $iblockId['events'],
            ],
            [
                'NAME' => 'Финансовая цель',
                'CODE' => 'GOAL',
                'PROPERTY_TYPE' => 'N',
                'IBLOCK_ID' => $iblockId['events'],
            ],
            [
                'NAME' => 'Уже собрано',
                'CODE' => 'TOTAL',
                'PROPERTY_TYPE' => 'N',
                'IBLOCK_ID' => $iblockId['events'],
            ],
            [
                'NAME' => 'Количество пожертвований',
                'CODE' => 'DONATION_COUNTER',
                'PROPERTY_TYPE' => 'N',
                'IBLOCK_ID' => $iblockId['events'],
            ],
            [
                'NAME' => 'Нет максимальной суммы сбора',
                'CODE' => 'HASMAXSUM',
                'PROPERTY_TYPE' => 'L',
                'PROPERTY_DISPLAY_TYPE' => 'K',
                'VALUES' => [
                    ['VALUE' => 'Да', 'XML_ID' => 'true'],
                ],
                'IBLOCK_ID' => $iblockId['events'],
            ],
            [
                'NAME' => 'Благодарность',
                'CODE' => 'GRATITUDE',
                'PROPERTY_TYPE' => 'S',
                'IBLOCK_ID' => $iblockId['events'],
            ],
            [
                'NAME' => 'Ссылка на видео',
                'CODE' => 'VIDEO',
                'PROPERTY_TYPE' => 'S',
                'IBLOCK_ID' => $iblockId['events'],
            ],
            [
                'NAME' => 'Фотографии',
                'CODE' => 'PHOTOS',
                'PROPERTY_TYPE' => 'F',
                'FILE_TYPE' => 'jpg, png, jpeg, webp',
                'MULTIPLE' => 'Y',
                'IBLOCK_ID' => $iblockId['events'],
            ],

            [
                'NAME' => 'Комментарий модератора',
                'CODE' => 'COMMENT',
                'PROPERTY_TYPE' => 'S',
                'IBLOCK_ID' => $iblockId['events'],
            ],
            [
                'NAME' => 'Черновик',
                'CODE' => 'DRAFT',
                'PROPERTY_TYPE' => 'S',
                'IBLOCK_ID' => $iblockId['events'],
                'SORT' => '1000',
            ],
            [
                'NAME' => 'Новая обложка',
                'CODE' => 'NEW_COVER',
                'PROPERTY_TYPE' => 'F',
                'IBLOCK_ID' => $iblockId['events'],
                'SORT' => '1020',
            ],
            [
                'NAME' => 'Новые фотографии',
                'CODE' => 'NEW_PHOTOS',
                'PROPERTY_TYPE' => 'F',
                'FILE_TYPE' => 'jpg, png, jpeg, webp',
                'MULTIPLE' => 'Y',
                'IBLOCK_ID' => $iblockId['events'],
                'SORT' => '1040',
            ],
        ];

        $ibp = new \CIBlockProperty();
        foreach ($properties as $property) {
            $propId = $ibp->Add($property);
            if ($propId <= 0) {
                throw new Error("Ошибка создания свойства " . $property['NAME'] . " для инфоблока " . $iblockId['events']);
            }

            Option::set($this->MODULE_ID, 'PROPERTY_' . $property['CODE'] . '_ID', $propId);
        }
    }

    private function createHighloadIblocks()
    {
        \Bitrix\Main\Loader::includeModule('highloadblock');

        $highloadIblocks = [
            [
                'NAME' => 'Covers',
                'TABLE_NAME' => 'covers',
            ],
            [
                'NAME' => 'Descriptions',
                'TABLE_NAME' => 'descriptions',
            ],
            [
                'NAME' => 'Why',
                'TABLE_NAME' => 'why',
            ],
            [
                'NAME' => 'Gratitude',
                'TABLE_NAME' => 'gratitude',
            ],
        ];

        foreach ($highloadIblocks as $highloadIblock) {
            HighloadBlockTable::add($highloadIblock);
        }

        // добавляем справочник обложек
        $hlblockId = $this->getHighloadIblockId('covers');
        $UFObject = "HLBLOCK_$hlblockId";

        $arFields = [
            'UF_NAME' => ['type' => 'string', 'label' => 'Название'],
            'UF_PICTURE' => ['type' => 'file', 'label' => 'Изображение']
        ];

        $arObFields = [];
        try {
            foreach ($arFields as $key => $field) {
                $arObFields[$key] =
                    [
                        'ENTITY_ID' => $UFObject,
                        'FIELD_NAME' => $key,
                        'USER_TYPE_ID' => $field['type'],
                        'SORT' => 100,
                        'MULTIPLE' => 'N',
                        'MANDATORY' => 'Y',
                        'IS_SEARCHABLE' => 'N',
                        'SETTINGS' => [
                            'DEFAULT_VALUE' => '',
                        ],
                        'EDIT_FORM_LABEL' => [
                            'ru' => $field['label'],
                        ],
                        'LIST_COLUMN_LABEL' => [
                            'ru' => $field['label'],
                        ],
                        'LIST_FILTER_LABEL' => [
                            'ru' => $field['label'],
                        ],
                    ];

                foreach ($arObFields as $arCartField) {
                    $obUserField  = new CUserTypeEntity;
                    $id = $obUserField->Add($arCartField);
                }
            }
        } catch (Exception  $e) {
            throw new Error($e->getMessage());
        }

        // добавлляем справочник описаний
        $hlblockId = $this->getHighloadIblockId('descriptions');
        $UFObject = "HLBLOCK_$hlblockId";

        $arFields = [
            'UF_SORT' => ['type' => 'integer', 'label' => 'Сортировка'],
            'UF_TEXT' => ['type' => 'string', 'label' => 'Текст'],
        ];

        $arObFields = [];
        try {
            foreach ($arFields as $key => $field) {
                $arObFields[$key] =
                    [
                        'ENTITY_ID' => $UFObject,
                        'FIELD_NAME' => $key,
                        'USER_TYPE_ID' => $field['type'],
                        'SORT' => 100,
                        'MULTIPLE' => 'N',
                        'MANDATORY' => 'Y',
                        'IS_SEARCHABLE' => 'N',
                        'SETTINGS' => [
                            'DEFAULT_VALUE' => '',
                        ],
                        'EDIT_FORM_LABEL' => [
                            'ru' => $field['label'],
                        ],
                        'LIST_COLUMN_LABEL' => [
                            'ru' => $field['label'],
                        ],
                        'LIST_FILTER_LABEL' => [
                            'ru' => $field['label'],
                        ],
                    ];

                foreach ($arObFields as $arCartField) {
                    $obUserField  = new CUserTypeEntity;
                    $id = $obUserField->Add($arCartField);
                }
            }
        } catch (Exception  $e) {
            throw new Error($e->getMessage());
        }

        // добавлляем справочник благодарностей
        $hlblockId = $this->getHighloadIblockId('gratitude');
        $UFObject = "HLBLOCK_$hlblockId";

        $arFields = [
            'UF_SORT' => ['type' => 'integer', 'label' => 'Сортировка'],
            'UF_TEXT' => ['type' => 'string', 'label' => 'Текст'],
        ];

        $arObFields = [];
        try {
            foreach ($arFields as $key => $field) {
                $arObFields[$key] =
                    [
                        'ENTITY_ID' => $UFObject,
                        'FIELD_NAME' => $key,
                        'USER_TYPE_ID' => $field['type'],
                        'SORT' => 100,
                        'MULTIPLE' => 'N',
                        'MANDATORY' => 'Y',
                        'IS_SEARCHABLE' => 'N',
                        'SETTINGS' => [
                            'DEFAULT_VALUE' => '',
                        ],
                        'EDIT_FORM_LABEL' => [
                            'ru' => $field['label'],
                        ],
                        'LIST_COLUMN_LABEL' => [
                            'ru' => $field['label'],
                        ],
                        'LIST_FILTER_LABEL' => [
                            'ru' => $field['label'],
                        ],
                    ];

                foreach ($arObFields as $arCartField) {
                    $obUserField  = new CUserTypeEntity;
                    $id = $obUserField->Add($arCartField);
                }
            }
        } catch (Exception  $e) {
            throw new Error($e->getMessage());
        }

        // добавлляем справочник "почему?"
        $hlblockId = $this->getHighloadIblockId('why');
        $UFObject = "HLBLOCK_$hlblockId";

        $arFields = [
            'UF_SORT' => ['type' => 'integer', 'label' => 'Сортировка'],
            'UF_TEXT' => ['type' => 'string', 'label' => 'Текст'],
        ];

        $arObFields = [];
        try {
            foreach ($arFields as $key => $field) {
                $arObFields[$key] =
                    [
                        'ENTITY_ID' => $UFObject,
                        'FIELD_NAME' => $key,
                        'USER_TYPE_ID' => $field['type'],
                        'SORT' => 100,
                        'MULTIPLE' => 'N',
                        'MANDATORY' => 'Y',
                        'IS_SEARCHABLE' => 'N',
                        'SETTINGS' => [
                            'DEFAULT_VALUE' => '',
                        ],
                        'EDIT_FORM_LABEL' => [
                            'ru' => $field['label'],
                        ],
                        'LIST_COLUMN_LABEL' => [
                            'ru' => $field['label'],
                        ],
                        'LIST_FILTER_LABEL' => [
                            'ru' => $field['label'],
                        ],
                    ];

                foreach ($arObFields as $arCartField) {
                    $obUserField  = new CUserTypeEntity;
                    $id = $obUserField->Add($arCartField);
                }
            }
        } catch (Exception  $e) {
            throw new Error($e->getMessage());
        }
    }

    private function getHighloadIblockId($name)
    {
        \Bitrix\Main\Loader::includeModule('highloadblock');

        $result = HighloadBlockTable::getList([
            'filter' => ['=NAME' => $name],
        ]);

        if ($row = $result->fetch()) {
            return $row['ID'];
        }

        return null;
    }

    private function deleteIblocs()
    {
        global $USER, $DB;

        \Bitrix\Main\Loader::includeModule('iblock');
        if ($USER->IsAdmin()) {
            $DB->StartTransaction();

            $res = CIBlock::GetList([], ['TYPE ' => 'donations']);
            while ($ar_res = $res->Fetch()) {
                if ($ar_res['IBLOCK_TYPE_ID'] !== 'donations')
                    continue;

                // \Bitrix\Main\Diag\Debug::writeToFile($ar_res, "", "/local/logs/my_events.log");

                if (!CIBlock::Delete($ar_res['ID'])) {
                    $DB->Rollback();
                    throw new Error("Ошибка удаления ифноблока {$ar_res['NAME']}");
                }
            }

            CIBlockType::Delete('donations');
            $DB->Commit();
        }
    }

    private function deleteHighloadIblocks()
    {
        \Bitrix\Main\Loader::includeModule('highloadblock');

        $highloadIblocks = [
            [
                'NAME' => 'Covers',
                'TABLE_NAME' => 'covers',
            ],
            [
                'NAME' => 'Descriptions',
                'TABLE_NAME' => 'descriptions',
            ],
            [
                'NAME' => 'Why',
                'TABLE_NAME' => 'why',
            ],
            [
                'NAME' => 'gratitude',
                'TABLE_NAME' => 'descriptions',
            ],
        ];

        foreach ($highloadIblocks as $name) {
            $id = $this->getHighloadIblockId($name);
            if ($id) {
                HighloadBlockTable::delete($id);
            }
        }
    }
}
