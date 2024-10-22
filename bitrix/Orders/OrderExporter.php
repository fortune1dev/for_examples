<?php

declare(strict_types=1);

namespace Orders;

use Bitrix\Main\Diag\Debug;
use Bitrix\Sale\Internals\StatusLangTable;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Orders\Helpers\OrderFilterHelper;
use Orders\Helpers\OrderGenerator;

class OrderExporter
{
    const CACHE_TIME = 3600 * 24;
    const ORDER_PROPERTY_CODES = [
        'ORDER_NUMBER' => 'Номер заказа в системе клиента',
        'ORDER_TYPE_NO' => 'Тип заказа',
        'ORDER_TYPE' => 'Вид заказа',
        'BUSINESS_ID' => 'Бизнес-подразделение',
        'CENTER_ID' => 'Центр затрат',
        'BUYER_ID' => 'Покупатель',
        'CONTRACT_ID' => 'Соглашение',
        'CONSIGNEE_ID' => 'Грузополучатель',
        'STORE_ID' => 'Склад',
        'ORDER_REQUIRED_DELIVERY_DATE' => 'Требуемая дата отгрузки',
        'SHIPMENT_DATE' => 'Дата отгрузки',
        'DELIVERY_DATE' => 'Дата доставки',
        'TRANSPORT_TYPE' => 'Тип транспорта',
        'CONSIGNEE_ID_ADDRESS' => 'Адрес грузополучателя',
        'BUYER_ID_ADDRESS' => 'Адрес покупателя',
        'PALLETS' => 'Количество паллет',
        'TRANSPORT_BOXES' => 'Количесво коробов',
        'WEIGHT' => 'Общий вес заказа',
        'COMMENT' => 'Комментарий к заказу',
    ];

    const IBLOCK_PROPERTY_CODES = [
        'BUSINESS_ID',
        'CENTER_ID',
        'BUYER_ID',
        'CONTRACT_ID',
        'CONSIGNEE_ID',
        'STORE_ID',
    ];

    /**
     * Экспортирует список заказов в XLSX файл.
     * 
     * @param array $filter Фильтр для выборки заказов.
     * @param string $filePath Путь для сохранения XLSX файла.
     * @param int $limit Ограничение количества заказов.
     * @param int $step Шаг выборки заказов.
     * @param bool $includeItems Включать ли состав заказа.
     * @return string Путь к сохраненному XLSX файлу.
     */
    public static function exportOrders(array $filter, string $filePath, int $limit = 0, int $step = 10, bool $includeItems = false): string
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            self::setHeaders($sheet, $includeItems);

            $row = 2;
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);

            $tempFilePath = tempnam(sys_get_temp_dir(), 'orders_export_');
            $writer->save($tempFilePath);

            $statusDescriptions = self::getStatusDescriptions();

            $filter = OrderFilterHelper::addPropertyOrderToFilter($filter);

            foreach (OrderGenerator::getOrdersGenerator($filter, $limit, $step, $includeItems) as $order) {
                if (!$order) {
                    continue;
                }

                self::fillOrderData($sheet, $order, $statusDescriptions, $row, $includeItems);

                $row++;
                $writer->save($tempFilePath);
            }

            $writer->save($tempFilePath);
            rename($tempFilePath, $filePath);

            return $filePath;
        } catch (\Exception $e) {
            Debug::dumpToFile($e->getMessage() . "\n" . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Устанавливает заголовки столбцов в таблице.
     * 
     * @param Worksheet $sheet Лист таблицы.
     * @param bool $includeItems Включать ли состав заказа.
     */
    private static function setHeaders(Worksheet $sheet, bool $includeItems): void
    {
        $sheet->setCellValue('A1', 'Ссылка на заказ');
        $sheet->setCellValue('B1', 'Номер заказа');
        $sheet->setCellValue('C1', 'Дата заказа');
        $sheet->setCellValue('D1', 'Статус');
        $sheet->setCellValue('E1', 'Сумма');
        $sheet->setCellValue('F1', 'Валюта');
        $sheet->setCellValue('G1', 'Количество позиций');
        $sheet->setCellValue('H1', 'ID покупателя');

        $column = 'I';
        foreach (self::ORDER_PROPERTY_CODES as $name) {
            $sheet->setCellValue($column . '1', $name);
            $column++;
        }

        if ($includeItems) {
            $sheet->setCellValue($column . '1', 'Товары');
        }
    }

    /**
     * Получает описания статусов заказов.
     * 
     * @return array Массив с описаниями статусов.
     */
    private static function getStatusDescriptions(): array
    {
        $statusDescriptions = [];
        $statusResult = StatusLangTable::getList([
            'order' => ['STATUS.SORT' => 'ASC'],
            'filter' => ['LID' => LANGUAGE_ID],
            'select' => ['STATUS_ID', 'NAME', 'DESCRIPTION'],
        ]);

        while ($status = $statusResult->fetch()) {
            $statusDescriptions[$status['STATUS_ID']] = $status['NAME'];
        }

        return $statusDescriptions;
    }

    /**
     * Заполняет данные заказа в таблицу.
     * 
     * @param Worksheet $sheet Лист таблицы.
     * @param array $order Данные заказа.
     * @param array $statusDescriptions Описания статусов.
     * @param int $row Номер строки для заполнения.
     * @param bool $includeItems Включать ли состав заказа.
     */
    private static function fillOrderData(Worksheet $sheet, array $order, array $statusDescriptions, int $row, bool $includeItems): void
    {
        $orderLink = 'https://xxxxxxx/orders/detail.php?ORDER_ID=' . $order['ID'];
        $sheet->setCellValue('A' . $row, $orderLink);
        $sheet->getCell('A' . $row)->getHyperlink()->setUrl($orderLink);

        $sheet->setCellValue('B' . $row, $order['ACCOUNT_NUMBER']);
        $sheet->setCellValue('C' . $row, $order['DATE_INSERT']->format('Y-m-d H:i:s'));

        $statusDescription = $statusDescriptions[$order['STATUS_ID']] ?? $order['STATUS_ID'];
        $sheet->setCellValue('D' . $row, $statusDescription);

        $sheet->setCellValue('E' . $row, $order['PRICE']);
        $sheet->setCellValue('F' . $row, $order['CURRENCY']);
        $sheet->setCellValue('G' . $row, $order['ITEM_COUNT']);
        $sheet->setCellValue('H' . $row, $order['USER_ID']);

        $column = 'I';
        foreach (self::ORDER_PROPERTY_CODES as $code => $name) {
            $sheet->setCellValue($column . $row, $order['PROPERTIES'][$code] ?? '');
            $sheet->getColumnDimension($column)->setAutoSize(true);
            $column++;
        }

        if ($includeItems && isset($order['ITEMS'])) {
            $items = [];
            foreach ($order['ITEMS'] as $item) {
                $items[] = $item['NAME'] . ' (' . $item['QUANTITY'] . ' шт., ' . $item['TOTAL'] . ' ' . $item['CURRENCY'] . ')';
            }
            $sheet->setCellValue($column . $row, implode("\n", $items));
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }
}
