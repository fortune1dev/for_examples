<?php

declare(strict_types=1);

namespace Orders\Helpers;

use Bitrix\Main\Data\Cache;
use Bitrix\Sale\Order;
use Bitrix\Iblock\ElementTable;
use Orders\OrderExporter;

class OrderDataHelper
{
    /**
     * Получает значения свойств заказа.
     * 
     * @param int $orderId ID заказа.
     * @return array Массив со значениями свойств заказа.
     */
    public static function getOrderProperties(int $orderId): array
    {
        $cacheTime = OrderExporter::CACHE_TIME;
        $cacheId = 'order_properties_' . $orderId;
        $cache = Cache::createInstance();

        if ($cache->initCache($cacheTime, $cacheId, 'order_properties')) {
            return $cache->getVars();
        } elseif ($cache->startDataCache()) {
            $properties = [];

            $order = Order::load($orderId);
            $propertyCollection = $order->getPropertyCollection();

            foreach (OrderExporter::ORDER_PROPERTY_CODES as $code => $name) {
                $property = $propertyCollection->getItemByOrderPropertyCode($code);
                if ($property) {
                    $properties[$code] = $property->getValue();
                }
            }

            $cache->endDataCache($properties);
        }

        return $properties;
    }

    /**
     * Заменяет ID элементов инфоблока на их NAME в массиве свойств заказа.
     * 
     * @param array $orderProps Массив со свойствами заказа.
     * @return array Массив со свойствами заказа с замененными ID на NAME.
     */
    public static function getOrderPropsValues(array $orderProps): array
    {
        $cacheTime = OrderExporter::CACHE_TIME;
        $cacheId = 'order_props_values_' . md5(serialize($orderProps));
        $cache = Cache::createInstance();

        if ($cache->initCache($cacheTime, $cacheId, 'order_props_values')) {
            return $cache->getVars();
        } elseif ($cache->startDataCache()) {
            $iblockElementMap = [];

            $iblockElementIds = array_filter(array_map(function ($propCode) use ($orderProps) {
                return isset($orderProps[$propCode]) ? $orderProps[$propCode] : null;
            }, OrderExporter::IBLOCK_PROPERTY_CODES));

            if (!empty($iblockElementIds)) {
                $iblockElementIds = array_unique($iblockElementIds);

                $iblockElementMap = ElementTable::getList([
                    'filter' => ['ID' => $iblockElementIds],
                    'select' => ['ID', 'NAME'],
                ])->fetchAll();

                $iblockElementMap = array_column($iblockElementMap, 'NAME', 'ID');
            }

            foreach ($orderProps as $propCode => &$propValue) {
                if (in_array($propCode, OrderExporter::IBLOCK_PROPERTY_CODES) && isset($iblockElementMap[$propValue])) {
                    $propValue = $iblockElementMap[$propValue];
                }
            }

            $cache->endDataCache($orderProps);
        }

        return $orderProps;
    }

    /**
     * Получает количество товаров в заказе.
     * 
     * @param int $orderId ID заказа.
     * @return int Количество товаров в заказе.
     */
    public static function getOrderItemCount(int $orderId): int
    {
        $cacheTime = OrderExporter::CACHE_TIME;
        $cacheId = 'order_item_count_' . $orderId;
        $cache = Cache::createInstance();

        if ($cache->initCache($cacheTime, $cacheId, 'order_item_count')) {
            return $cache->getVars();
        } elseif ($cache->startDataCache()) {
            $order = Order::load($orderId);
            $basket = $order->getBasket();
            $itemCount = $basket->count();

            $cache->endDataCache($itemCount);
        }

        return $itemCount;
    }

    /**
     * Получает список товаров в заказе.
     * 
     * @param int $orderId ID заказа.
     * @return array Массив с данными товаров в заказе.
     */
    public static function getOrderItems(int $orderId): array
    {
        $cacheTime = OrderExporter::CACHE_TIME;
        $cacheId = 'order_items_' . $orderId;
        $cache = Cache::createInstance();

        if ($cache->initCache($cacheTime, $cacheId, 'order_items')) {
            return $cache->getVars();
        } elseif ($cache->startDataCache()) {
            $items = [];

            $order = Order::load($orderId);
            $basket = $order->getBasket();

            foreach ($basket as $item) {
                $items[] = [
                    'NAME' => $item->getField('NAME'),
                    'PRICE' => $item->getPrice(),
                    'QUANTITY' => $item->getQuantity(),
                    'TOTAL' => $item->getPrice() * $item->getQuantity(),
                    'CURRENCY' => $item->getCurrency(),
                ];
            }

            $cache->endDataCache($items);
        }

        return $items;
    }
}
