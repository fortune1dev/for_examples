<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;

Loc::loadMessages(__FILE__);

$module_id = 'fbl.donationevents';
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST'; // если запрос POST

if ($isPost && filter_input(INPUT_POST, 'save_action') === 'Y' && check_bitrix_sessid()) {
    // Обработка сохранения настроек
    Option::set($module_id, 'events_id', $_POST['events_id']);
    Option::set($module_id, 'stories_id', $_POST['stories_id']);
    Option::set($module_id, 'faq_id', $_POST['faq_id']);
    Option::set($module_id, 'PROPERTY_TYPE_ID', $_POST['PROPERTY_TYPE_ID']);
    Option::set($module_id, 'PROPERTY_STATUS_ID', $_POST['PROPERTY_STATUS_ID']);
    Option::set($module_id, 'PROPERTY_HASMAXSUM_ID', $_POST['PROPERTY_HASMAXSUM_ID']);

    // Добавляем сохранение переменных для Б24
    Option::set($module_id, 'client_id', $_POST['client_id']);
    Option::set($module_id, 'client_secret', $_POST['client_secret']);
    Option::set($module_id, 'access_token', $_POST['access_token']);
    Option::set($module_id, 'refresh_token', $_POST['refresh_token']);
    Option::set($module_id, 'auth_token', $_POST['auth_token']);
    Option::set($module_id, 'b24_url', $_POST['b24_url']);

    CAdminMessage::ShowNote('Настройки сохранены');
}

$aTabs = [
    [
        'DIV' => 'edit1',
        'TAB' => 'Настройки',
        'TITLE' => "Настройки",
        'OPTIONS' => [
            [
                'events_id',
                'ID Инфоблока "Cобытий"',
                Option::get($module_id, 'events_id', 0),
                ['text', 10]
            ],
            [
                'stories_id',
                'ID Инфоблока "Историй"',
                Option::get($module_id, 'stories_id', 0),
                ['text', 10]
            ],
            [
                'faq_id',
                'ID Инфоблока "ЧАВо"',
                Option::get($module_id, 'faq_id', 0),
                ['text', 10]
            ],
            [
                'PROPERTY_TYPE_ID',
                'ID свойства "Тип"',
                Option::get($module_id, 'PROPERTY_TYPE_ID', 0),
                ['text', 10]
            ],
            [
                'PROPERTY_STATUS_ID',
                'ID свойства "Статус"',
                Option::get($module_id, 'PROPERTY_STATUS_ID', 0),
                ['text', 10]
            ],
            [
                'PROPERTY_HASMAXSUM_ID',
                'ID свойства "Нет макс. суммы"',
                Option::get($module_id, 'PROPERTY_HASMAXSUM_ID', 0),
                ['text', 10]
            ],
            // Добавляем новые поля для настроек
            [
                'client_id',
                'Client ID',
                Option::get($module_id, 'client_id', ''),
                ['text', 50]
            ],
            [
                'client_secret',
                'Client Secret',
                Option::get($module_id, 'client_secret', ''),
                ['text', 100]
            ],
            [
                'access_token',
                'Access Token',
                Option::get($module_id, 'access_token', ''),
                ['text', 100]
            ],
            [
                'refresh_token',
                'Refresh Token',
                Option::get($module_id, 'refresh_token', ''),
                ['text', 100]
            ],
            [
                'auth_token',
                'Auth Token',
                Option::get($module_id, 'auth_token', ''),
                ['text', 100]
            ],
            [
                'b24_url',
                'URL Bitrix24',
                Option::get($module_id, 'b24_url', ''),
                ['text', 100]
            ],
        ]
    ]
];

$tabControl = new CAdminTabControl('tabControl', $aTabs);
$tabControl->Begin();
?>
<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= htmlspecialcharsbx($mid) ?>&lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>
    <? foreach ($aTabs as $aTab):
        if ($aTab['OPTIONS']):
            $tabControl->BeginNextTab();
            __AdmSettingsDrawList($module_id, $aTab['OPTIONS']);
        endif;
    endforeach;
    $tabControl->Buttons();
    ?>
    <input type="submit" name="save_action" value="Сохранить">
    <input type="hidden" name="save_action" value="Y">
    <?= $tabControl->End(); ?>
</form>