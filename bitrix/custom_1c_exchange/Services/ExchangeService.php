<?php

namespace Exchange\Services;

use Exchange\Services\IExchange;
use Bitrix\Iblock\ElementTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Psr\Http\Message\ServerRequestInterface;

class ExchangeService implements IExchange
{
    // Код инфоблока, с которым работает сервис
    protected string $iblockCode = '';

    // Информация об инфоблоке
    protected array|false $iblock;

    // Статический массив для хранения сервисов
    protected static array $aServices = [];

    /**
     * Конструктор класса.
     * Подключает модуль инфоблоков и получает информацию об инфоблоке по его коду.
     */
    public function __construct()
    {
        \CModule::IncludeModule('iblock');
        $this->iblock = \CIBlock::GetList([], ['CODE' => $this->iblockCode])->Fetch();
    }

    /**
     * Метод для экспорта данных.
     * @return array
     */
    public function export(): array
    {
        return [];
    }

    /**
     * Метод для импорта данных.
     * @param ServerRequestInterface $request
     * @return array
     */
    public function import(ServerRequestInterface $request): array
    {
        return [];
    }

    /**
     * Метод для записи элемента в инфоблок.
     * @param array $fields Поля элемента.
     * @param array $props Свойства элемента.
     * @return int|array ID созданного или обновленного элемента.
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws ArgumentException
     */
    protected function writeItem(array $fields, array $props = []): int|array
    {
        $elem = new ElementTable();
        $aElem = $elem->getList(['filter' => [
            'IBLOCK_ID' => $this->iblock['ID'],
            'XML_ID' => $fields['XML_ID'],
        ]])->fetch();

        $fields['IBLOCK_ID'] = $this->iblock['ID'];
        $fields['ACTIVE'] = (isset($fields['ACTIVE']) && $fields['ACTIVE'] === false) ? 'N' : 'Y';
        $fields = array_map('recursiveTrim', $fields);
        $props = array_map('recursiveTrim', $props);

        $el = new \CIBlockElement();
        if (empty($aElem)) {
            $id = $el->Add($fields);
        } else {
            $el->Update(
                $aElem['ID'],
                $fields
            );
            $id = $aElem['ID'];
        }

        if (!empty($props) && !empty($id)) {
            \CIBlockElement::SetPropertyValuesEx(
                $id,
                $this->iblock['ID'],
                $props
            );
        }
        return $id;
    }

    /**
     * Метод для получения элементов сервиса.
     * @param string $serviceCode Код сервиса.
     * @return array Массив элементов сервиса.
     */
    protected static function getServiceItems(string $serviceCode): array
    {
        $oServices = \CIBlockElement::GetList([], [
            'IBLOCK_CODE' => $serviceCode
        ], false, false, ['ID', 'XML_ID', 'NAME', 'IBLOCK_ID']);
        $aServices = [];
        while ($aService = $oServices->Fetch()) {
            $aServices[$aService['XML_ID']] = $aService;

            // Дополнительный добор значений для Категорий и Брендов
            try {
                $propertyIterator = \CIBlockElement::GetProperty(
                    $aService['IBLOCK_ID'],
                    $aService['ID'],
                    [],
                    ['CODE' => 'ADDITIONAL_XML_ID']
                );
    
                while ($property = $propertyIterator->GetNext()) {
                    if (!empty($property['VALUE'])) {
                        $aServices[$property['VALUE']] = $aService;
                    }
                }
            } catch (\Throwable $th) {
                //throw $th;
            }

        }

        return $aServices;
    }

    /**
     * Метод для получения конкретного элемента сервиса.
     * @param string $serviceCode Код сервиса.
     * @param string $itemCode Код элемента.
     * @param string $xmlid XML ID элемента.
     * @param bool $exeption Флаг, указывающий, выбрасывать ли исключение, если элемент не найден.
     * @return array|null Массив с информацией об элементе или null, если элемент не найден.
     * @throws \Exception
     */
    protected static function getServiceItem(string $serviceCode, string $itemCode, string $xmlid, $exeption = true): array|null
    {
        if (empty(self::$aServices[$serviceCode]) || empty(self::$aServices[$serviceCode]['ITEMS'][$itemCode])) {
            $iblock = \CIBlock::GetList([], ['CODE' => $serviceCode])->Fetch();
            self::$aServices[$serviceCode]['ITEMS'] = self::getServiceItems($serviceCode);
            self::$aServices[$serviceCode]['IBLOCK'] = $iblock;
        }
        if($itemCode === '00000000-0000-0000-0000-000000000000'){
            return [
                'ID' => 0,
                'XML_ID' => "-",
                'NAME' => "-",
                'IBLOCK_ID' => 0
            ];
        }

        if (empty(self::$aServices[$serviceCode]['ITEMS'][$itemCode])) {
            if($exeption) {
                throw new \Exception('XML_ID ' . $xmlid . '. Не найден ' . self::$aServices[$serviceCode]['IBLOCK']['NAME'] . ' с XML_ID ' . $itemCode, 500);
            }else {
                return null;
            }
        } else {
            return self::$aServices[$serviceCode]['ITEMS'][$itemCode];
        }
    }

    /**
     * Метод для переинициализации информации об инфоблоке.
     */
    protected function reinitIblock(): void
    {
        $this->iblock = \CIBlock::GetList([], ['CODE' => $this->iblockCode])->Fetch();
    }

    /**
     * Метод для логирования информации.
     * @param string $service Сервис.
     * @param string $method Метод.
     * @param string $request Запрос.
     * @param string $response Ответ.
     * @param string $status Статус.
     */
    protected function logInfo($service, $method, $request, $response, $status)
    {
        try {
            $config = [
                'host' => 'localhost',
                'port' => '8123',
                'username' => 'default',
                'password' => '',
                'https' => false
            ];
            $db = new \ClickHouseDB\Client($config);
            $db->database('default');
            $db->insert(
                'log',
                [
                    [
                        time(),
                        $service,
                        $method,
                        $request,
                        $response,
                        $status
                    ]
                ],
                [
                    'created_at',
                    'service',
                    'method',
                    'request',
                    'response',
                    'status',
                ]
            );
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
}