<?php

declare(strict_types=1);

namespace Orders\Helpers;

use Bitrix\Main\Data\Cache;
use Bitrix\Sale\Internals\OrderTable;
use Orders\OrderExporter;

class OrderGenerator
{
    /**
     * Генератор для получения списка заказов с учетом фильтра.
     * 
     * @param array $filter Фильтр для выборки заказов.
     * @param int $limit Ограничение количества заказов.
     * @param int $step Шаг выборки заказов.
     * @param bool $includeItems Включать ли состав заказа.
     * @return \Generator Генератор, возвращающий данные заказов.
     */
    public static function getOrdersGenerator(array $filter, int $limit = 0, int $step = 10, bool $includeItems = false): \Generator
    {
        $offset = 0;
        $totalOrders = 0;
        $cacheTime = OrderExporter::CACHE_TIME;
        $cacheId = md5(serialize($filter) . $limit . $step . $includeItems);
        $cache = Cache::createInstance();

        if ($cache->initCache($cacheTime, $cacheId, 'orders_export')) {
            $orders = $cache->getVars();
        } elseif ($cache->startDataCache()) {
            $orders = [];

            $runtime = $filter['runtime'];
            unset($filter['runtime']);
            $filter = $filter['filter'];
            unset($filter['filter']);

            do {
                $query = OrderTable::getList([
                    'filter' => $filter,
                    'select' => [
                        'ID',
                        'DATE_INSERT',
                        'STATUS_ID',
                        'PRICE',
                        'CURRENCY',
                        'USER_ID',
                        'ACCOUNT_NUMBER',
                    ],
                    'limit' => $step,
                    'offset' => $offset,
                    'runtime' => $runtime ?? null,
                    'group' => ['ID'],
                ]);

                while ($order = $query->fetch()) {
                    if ($includeItems) {
                        $order['ITEMS'] = OrderDataHelper::getOrderItems((int)$order['ID']);
                    }
                    $order['PROPERTIES'] = OrderDataHelper::getOrderProperties((int)$order['ID']);
                    $order['PROPERTIES'] = OrderDataHelper::getOrderPropsValues($order['PROPERTIES']);
                    $order['ITEM_COUNT'] = OrderDataHelper::getOrderItemCount((int)$order['ID']);

                    yield $order;

                    $totalOrders++;
                    if ($limit > 0 && $totalOrders >= $limit) {
                        break 2;
                    }
                }

                $offset += $step;
            } while ($query->getSelectedRowsCount() > 0);

            $cache->endDataCache($orders);
        }
    }
}
