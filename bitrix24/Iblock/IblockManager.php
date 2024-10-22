<?php

namespace Fbl\Iblock;

use Bitrix\Iblock\PropertyTable;
use Bitrix\Iblock\ElementPropertyTable;
use Bitrix\Main\Diag\Debug;
use Fbl\Events\Event;
use Fbl\Events\EventStatus;

class IblockManager
{
    /**
     * Обновляет элемент инфоблока, связанный с указанным ID сделки.
     * 
     * @param array $dealInfo Информация о сделке.
     * @return void
     */
    public static function updateIblockElement(array $dealInfo): void
    {
        $dealId = $dealInfo['ID'];

        $propertyId = self::getPropertyId('DEAL_ID');

        if (!$propertyId) {
            return;
        }

        $elementId = self::findElementByDealId($propertyId, $dealId);

        if (!$elementId) {
            Debug::writeToFile("Event with DEAL_ID {$dealId} not found", "updateIblockElement error", "/local/logs/my_events.log");
            return;
        }

        $event = new Event();
        $event->load($elementId);

        if (!empty($event->getErrors())) {
            Debug::writeToFile("Ошибка загрузки элемента $elementId: " . $event->getErrors(), "", "/local/logs/my_events.log");
            return;
        }

        self::updateEventProperties($event, $dealInfo);
    }

    /**
     * Получает ID свойства инфоблока по его коду.
     * 
     * @param string $propertyCode Код свойства.
     * @return int|null ID свойства или null, если свойство не найдено.
     */
    private static function getPropertyId(string $propertyCode): ?int
    {
        $property = PropertyTable::getList([
            'filter' => ['CODE' => $propertyCode],
            'select' => ['ID'],
        ])->fetch();

        return $property['ID'] ?? null;
    }

    /**
     * Ищет элемент инфоблока по значению свойства DEAL_ID.
     * 
     * @param int $propertyId ID свойства DEAL_ID.
     * @param int $dealId ID сделки.
     * @return int|null ID элемента инфоблока или null, если элемент не найден.
     */
    private static function findElementByDealId(int $propertyId, int $dealId): ?int
    {
        $element = ElementPropertyTable::getList([
            'filter' => [
                '=IBLOCK_PROPERTY_ID' => $propertyId,
                '=VALUE' => $dealId,
            ],
            'select' => ['IBLOCK_ELEMENT_ID'],
        ])->fetch();

        return $element['IBLOCK_ELEMENT_ID'] ?? null;
    }

    /**
     * Обновляет свойства элемента инфоблока на основе информации о сделке.
     * 
     * @param Event $event Объект события.
     * @param array $dealInfo Информация о сделке.
     * @return void
     */
    private static function updateEventProperties(Event $event, array $dealInfo): void
    {
        \CIBlockElement::SetPropertyValuesEx(
            $event->id,
            false,
            ['COMMENT' => $dealInfo['UF_CRM_1723182616978']]
        );

        $oldEventStatus = EventStatus::getStatusById($event->getStatus());

        if ($dealInfo['STAGE_ID'] === EventStatus::VERIFIED && $oldEventStatus['XML_ID'] === EventStatus::ON_MODERATION) {
            $event->description = $event->draft->description ?? $event->description;
            $event->why = $event->draft->why ?? $event->why;
            $event->gratitude = $event->draft->gratitude ?? $event->gratitude;
            $event->videoLink = $event->draft->videoLink ?? $event->videoLink;
            $event->cover = $event->newCover ?? $event->cover;
            $event->photos = $event->newPhotos ?? $event->photos;

            $event->save(EventStatus::getStatusByXmlId($dealInfo['STAGE_ID'])['ID']);
        } else {
            \CIBlockElement::SetPropertyValuesEx(
                $event->id,
                false,
                ['STATUS' => EventStatus::getStatusByXmlId($dealInfo['STAGE_ID'])['ID']]
            );
        }
    }
}
