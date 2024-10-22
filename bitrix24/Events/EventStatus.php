<?php

namespace Fbl\Events;

use Bitrix\Main\Config\Option;

/**
 * Статусы Событий пользователя, которые идентичны статусам в Б24
 */
final class EventStatus
{
    const NEW = 'C19:NEW'; // все сборы, которые еще не находятся на модерации
    const ON_MODERATION = 'C19:PREPARATION'; // сборы, которые находятся на модерации
    const HAS_ERROR = 'C19:PREPAYMENT_INVOIC'; // сборы, которые требуют исправления
    const VERIFIED = 'C19:EXECUTING'; // сбор проверен, модерация не требуется
    const SUCCESSFUL = 'C19:WON'; // сбор закрыт как успешный
    const DECLINED = 'C19:LOSE'; // сбор закрыт как неуспешный

    private static $statuses = [];
    private static $statusesXmlId = [];
    private $statusId;

    /**
     * Конструктор класса EventStatus.
     *
     * @param string|null $statusId Идентификатор статуса события (опционально).
     */
    public function __construct($statusId = null)
    {
        self::loadStatuses();
        $this->statusId = $statusId;
    }

    /**
     * Статический метод для загрузки статусов событий из инфоблока.
     */
    private static function loadStatuses()
    {
        if (empty(self::$statuses)) {
            $statuses = \Bitrix\Iblock\PropertyEnumerationTable::getList(
                [
                    'filter' => ['PROPERTY_ID' => Option::get('fbl.donationevents', 'PROPERTY_STATUS_ID', 0)],
                    'select' => ['ID', 'VALUE', 'XML_ID']
                ]
            )->fetchAll();
            self::$statuses = array_column($statuses, null, 'ID');
            self::$statusesXmlId = array_column($statuses, null, 'XML_ID');
        }
    }

    /**
     * Геттер для получения статуса события.
     *
     * @return string|null Идентификатор статуса события.
     */
    public function getStatusId()
    {
        return $this->statusId;
    }

    /**
     * Сеттер для установки статуса события.
     *
     * @param string $statusId Идентификатор статуса события.
     */
    public function setStatusId($statusId)
    {
        $this->statusId = $statusId;
    }

    /**
     * Статический метод для получения всех статусов событий.
     *
     * @return array Массив статусов событий.
     */
    public static function getAllStatuses()
    {
        self::loadStatuses();
        return self::$statuses;
    }

    /**
     * Статический метод для получения статуса события по его ID.
     *
     * @param int $id Идентификатор статуса события.
     * @return array|null Массив с данными статуса события или null, если статус не найден.
     */
    public static function getStatusById($id)
    {
        self::loadStatuses();
        return isset(self::$statuses[$id]) ? self::$statuses[$id] : null;
    }

    /**
     * Статический метод для получения статуса события по его XML_ID.
     *
     * @param int $id Идентификатор статуса события.
     * @return array|null Массив с данными статуса события или null, если статус не найден.
     */
    public static function getStatusByXmlId($id)
    {
        self::loadStatuses();
        return isset(self::$statusesXmlId[$id]) ? self::$statusesXmlId[$id] : null;
    }
}
