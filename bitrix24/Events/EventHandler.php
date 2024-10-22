<?php

namespace Fbl\Events;

use Fbl\Tokens\TokenB24;
use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;

class EventHandler
{
    private static $moduleId = "fbl.donationevents";

    /**
     * Обработчик события получения платежей.
     * 
     * Этот метод обновляет данные о событии в инфоблоке при получении уведомления о платеже.
     * 
     * @param \Bitrix\Main\Event $event Событие, содержащее данные о платеже.
     * @return \Bitrix\Main\EventResult Результат выполнения операции.
     */
    public static function receivePayments(\Bitrix\Main\Event $event): \Bitrix\Main\EventResult
    {
        $postData = $event->getParameter('postData');
        $elementId = $postData['InvoiceId'];
        $element = self::findElementByInvoiceId($elementId);

        if ($element) {
            $paymentAmount = (float)$postData['Amount'];
            $newTotal = (float)$element['PROPERTY_TOTAL_VALUE'] + $paymentAmount;
            $newDonationCounter = (int)$element['PROPERTY_DONATION_COUNTER_VALUE'] + 1;

            $updateSuccess = self::updateElement($element['ID'], $newTotal, $newDonationCounter);

            if (!$updateSuccess) {
                Debug::writeToFile("receivePayments update error", "receivePayments error", "/local/logs/my_events.log");
                return self::createEventResult(false, "receivePayments update error");
            }
        } else {
            Debug::writeToFile("Element with ID {$elementId} not found", "receivePayments error", "/local/logs/my_events.log");
            return self::createEventResult(false, "Element with ID {$elementId} not found");
        }

        return self::createEventResult(true);
    }

    /**
     * Обработчик события отправки данных.
     * 
     * Этот метод отправляет данные события в Битрикс24 для создания новой сделки.
     * 
     * @param \Bitrix\Main\Event $event Событие, содержащее данные для отправки.
     * @return \Bitrix\Main\EventResult Результат выполнения операции.
     */
    public static function sendData(\Bitrix\Main\Event $event): \Bitrix\Main\EventResult
    {
        $eventData = new Event($event->getParameters()['data']->id);
        $dealData = self::prepareDealData($eventData);
        $method = $dealData['fields']['STAGE_ID'] === EventStatus::NEW ? 'crm.deal.add' : 'crm.deal.update';
        $dealData['id'] = $method === 'crm.deal.update' ? $eventData->dealId : null;

        $result = self::sendRequest($method, $dealData);

        if ($result['result']['error_description']) {
            Debug::writeToFile($result['result']['error_description'], "EventHandler::sendData error", "/local/logs/my_events.log");
            return self::createEventResult(false, $result['result']['error_description']);
        }

        if (!$eventData->dealId && isset($result['result']['result']) && is_int($result['result']['result'])) {
            $dealId = $result['result']['result'];
            self::updateElementDealId($eventData->id, $dealId);
        }

        return self::createEventResult(true, null, $result['result']['result'] ?? null);
    }

    /**
     * Находит элемент инфоблока по ID.
     * 
     * @param int $elementId ID элемента для поиска.
     * @return array|null Найденный элемент или null, если элемент не найден.
     */
    private static function findElementByInvoiceId(int $elementId): ?array
    {
        Loader::includeModule('iblock');

        return \CIBlockElement::GetList(
            [],
            [
                'ID' => $elementId,
            ],
            false,
            false,
            ['ID', 'PROPERTY_TOTAL', 'PROPERTY_DONATION_COUNTER']
        )->Fetch();
    }

    /**
     * Обновляет элемент инфоблока.
     * 
     * @param int $elementId ID элемента.
     * @param float $newTotal Новое значение TOTAL.
     * @param int $newDonationCounter Новое значение DONATION_COUNTER.
     * @return bool Успешность обновления.
     */
    private static function updateElement(int $elementId, float $newTotal, int $newDonationCounter): bool
    {
        Loader::includeModule('iblock');

        $element = new \CIBlockElement;
        $fields = [
            'PROPERTY_VALUES' => [
                'TOTAL' => $newTotal,
                'DONATION_COUNTER' => $newDonationCounter,
            ],
        ];

        return $element->Update($elementId, $fields);
    }

    /**
     * Обновляет свойство DEAL_ID в инфоблоке.
     * 
     * @param int $elementId ID элемента.
     * @param int $dealId ID сделки.
     */
    private static function updateElementDealId(int $elementId, int $dealId): void
    {
        Loader::includeModule('iblock');
        \CIBlockElement::SetPropertyValuesEx($elementId, false, ['DEAL_ID' => $dealId]);
    }

    private static function base64EncodeImage(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: $filePath");
        }
        $imageData = file_get_contents($filePath);
        return base64_encode($imageData);
    }

    /**
     * @TODO работу с массивами пенерести в self::base64EncodeImage()
     * 
     * Подготавливает данные для создания сделки.
     * 
     * @param Event $event Объект Event, который нужно преобразовать.
     * @return array Массив данных для создания сделки.
     */
    private static function prepareDealData(Event $event): array
    {
        $type = EventType::getTypeById($event->getType());
        $status = EventStatus::getStatusById($event->getStatus());

        $aCover = [
            "fileData" => [
                basename($_SERVER['DOCUMENT_ROOT'] . $event->getCoverPath()),
                self::base64EncodeImage($_SERVER['DOCUMENT_ROOT'] . $event->getCoverPath())
            ]
        ];

        $aPhotos =
            array_map(function ($photo) {
                return [
                    "fileData" => [
                        basename($_SERVER['DOCUMENT_ROOT'] . $photo),
                        self::base64EncodeImage($_SERVER['DOCUMENT_ROOT'] . $photo)
                    ]
                ];
            }, $event->getPhotosPaths()) ?? [];

        $baseArray = [
            'TITLE'                => $event->title,
            'CATEGORY_ID'          => 19,
            'STAGE_ID'             => $status['XML_ID'],
            'CLOSEDATE'            => $event->active_to->toString(),
            'UF_CRM_1723181938437' => $event->isVerified() ? 4937 : 4938,
            'UF_CRM_1723182033704' => (int)$type['XML_ID'],
            'UF_CRM_1704369339034' => $event->goal,
            'UF_CRM_1723182160707' => $event->hasMaximum ? 1 : 0,
            'UF_CRM_1723182254017' => $event->description,
            'UF_CRM_1723182289522' => $event->why,
            'UF_CRM_1723182327032' => $event->gratitude,
            'UF_CRM_1723182363040' => $aCover,
            'UF_CRM_1723182411791' => $aPhotos,
            'UF_CRM_1723182522982' => $event->videoLink,
            'UF_CRM_1723182725187' => 0, //Не передавать событие на сайт
        ];

        if (!$event->isNew()) {

            $newCover = [
                "fileData" => [
                    basename($_SERVER['DOCUMENT_ROOT'] . $event->getCoverPath()),
                    self::base64EncodeImage($_SERVER['DOCUMENT_ROOT'] . $event->getCoverPath(true))
                ]
            ];

            $newPhotos =
                array_map(function ($photo) {
                    return [
                        "fileData" => [
                            basename($_SERVER['DOCUMENT_ROOT'] . $photo),
                            self::base64EncodeImage($_SERVER['DOCUMENT_ROOT'] . $photo)
                        ]
                    ];
                }, $event->newPhotos) ?? [];

            $baseArray = array_merge($baseArray, [
                'UF_CRM_1723182272587' => $event->draft->description, // Описание события - изменено
                'UF_CRM_1723182311071' => $event->draft->why, // Почему я создаю сбор в пользу ФБЛ - изменено
                'UF_CRM_1723182343794' => $event->draft->gratitude, // Благодарность участникам от автора - изменено
                'UF_CRM_1723182393499' => $newCover, // Фото из обложки - изменено
                'UF_CRM_1723182497407' => $newPhotos, // Дополнительные фото - изменено
                'UF_CRM_1723182539549' => $event->draft->videoLink, // Видео - изменено
            ]);
        }

        return [
            'fields' => $baseArray,
            'params' => ['REGISTER_SONET_EVENT' => 'Y']
        ];
    }

    /**
     * Отправляет запрос на указанный URL с использованием cURL.
     * 
     * @param string $method Метод API (например, 'crm.deal.add' или 'crm.deal.update').
     * @param array $data Данные, которые будут отправлены в теле запроса.
     * @return array Результат выполнения запроса.
     */
    private static function sendRequest(string $method, array $data): array | null
    {
        $headers = [
            'Content-Type: application/json',
            'authtoken: ' . TokenB24::getAuthToken(),
        ];

        $encodedData = json_encode($data);

        if ($encodedData === false) {
            return ['error' =>  "Ошибка кодирования в JSON: " . json_last_error_msg()];
        }

        $b24Url = Option::get(self::$moduleId, 'b24_url', '');
        $url = $b24Url . '/rest/' . $method . '.json?auth=' . TokenB24::getAccessToken();

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($response === false) {
            $errorCode = curl_errno($ch);
            $errorMessage = curl_error($ch);
            return ['error' =>  "Ошибка запроса к Б24: " . $errorCode . " - " . $errorMessage];
        }

        if ($httpCode == 401) {
            if (!TokenB24::refreshAccessToken()) {
                Debug::writeToFile("sendRequest Error: ошибка refreshAccessToken()", "", "/local/logs/my_events.log");
                return null;
            }
            return self::sendRequest($method, $data);
        }

        if ($result = json_decode($response, true)) {
            return ['result' => $result];
        } else {
            Debug::writeToFile($response, "EventHandler::sendRequest", "/local/logs/my_events.log");
            return ['error' =>  "Ошибка декодирования из JSON: " . json_last_error_msg()];
        }
    }

    /**
     * Создает результат события (Bitrix).
     * 
     * @param bool $success Успешность операции.
     * @param string|null $error Ошибка, если есть.
     * @param mixed|null $data Данные, если есть.
     * @return \Bitrix\Main\EventResult Результат события.
     */
    private static function createEventResult(bool $success, ?string $error = null, $data = null): \Bitrix\Main\EventResult
    {
        return new \Bitrix\Main\EventResult(
            \Bitrix\Main\EventResult::SUCCESS,
            [
                "result" => $success,
                'data' => $data,
                'error' => $error
            ]
        );
    }
}
