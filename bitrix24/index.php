<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
use Bitrix\Main\Diag\Debug;
use Fbl\Api\Bitrix24Api;
use Fbl\Iblock\IblockManager;

Loader::includeModule('iblock');

$dealId = $_GET['event_update'];

if (!empty($dealId)) {
    $dealInfo = Bitrix24Api::getDealInfo($dealId);

    if ($dealInfo) {
        IblockManager::updateIblockElement($dealInfo);
    } else {
        Debug::writeToFile("Failed to get deal info for ID: {$dealId}", "", "/local/logs/my_events.log");
    }
} else {
    Debug::writeToFile("Invalid request method or missing deal ID", "", "/local/logs/my_events.log");
}
