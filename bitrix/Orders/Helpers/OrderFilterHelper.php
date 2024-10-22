<?php

declare(strict_types=1);

namespace Orders\Helpers;

use Bitrix\Main\Type\Date;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Sale\Internals\OrderPropsValueTable;
use Bitrix\Sale\Internals\BasketTable;
use Orders\OrderExporter;

class OrderFilterHelper
{
    /**
     * Добавляет условия фильтрации по свойствам заказа в массив $filter.
     * 
     * @param array $filter Основной фильтр для заказов.
     * @return array Дополненный фильтр.
     */
    public static function addPropertyOrderToFilter(array $filter): array
    {
        if (!empty($_POST["EXCEL_FULL"])) {
            return $filter;
        }

        if (!empty($_GET['SET_FILTER']) && $_GET['SET_FILTER'] === 'Y' && !empty($_GET['FILTER'])) {
            foreach ($_GET['FILTER'] as $code => $value) {
                // Пропускаем пустые значения
                if ($value === '') {
                    continue;
                }

                // Обработка интервалов дат
                if (str_ends_with($code, '--MIN_VALUE')) {
                    $code = str_replace('--MIN_VALUE', '', $code);
                    $filter['filter']['>=' . $code] = self::formatDate($value, true);
                } elseif (str_ends_with($code, '--MAX_VALUE')) {
                    $code = str_replace('--MAX_VALUE', '', $code);
                    $filter['filter']['<=' . $code] = self::formatDate($value, false);
                } elseif (str_ends_with($code, '--VALUE')) {
                    $code = str_replace('--VALUE', '', $code);
                    $filter['filter'][$code] = $value;
                } elseif (is_array($value)) {
                    // Обработка массивов значений (например, статусы)
                    $filter['filter'][$code] = $value;
                } else {
                    // Обработка свойств заказа
                    if (array_key_exists($code, OrderExporter::ORDER_PROPERTY_CODES)) {
                        // Добавляем runtime поле для связи с таблицей свойств заказов
                        $filter['runtime'][] = new Reference(
                            'ORDER_PROP_' . $code,
                            OrderPropsValueTable::class,
                            Join::on('ref.ORDER_ID', 'this.ID')
                        );

                        // Добавляем условие фильтрации по свойству заказа
                        $filter['filter']['=ORDER_PROP_' . $code . '.CODE'] = $code;
                        $filter['filter']['=ORDER_PROP_' . $code . '.VALUE'] = $value;
                    } else {
                        // Обработка специальных полей, таких как PRODUCT_ID
                        if ($code === 'PRODUCT_ID') {
                            // Добавляем runtime поле для связи с таблицей товаров в заказе
                            $filter['runtime'][] = new Reference(
                                'ORDER_PRODUCT',
                                BasketTable::class,
                                Join::on('ref.ORDER_ID', 'this.ID')
                            );

                            // Добавляем условие фильтрации по товару в заказе
                            $filter['filter']['=ORDER_PRODUCT.PRODUCT_ID'] = $value;
                        } else {
                            // Если код не найден в ORDER_PROPERTY_CODES, добавляем его напрямую в фильтр
                            $filter['filter'][$code] = $value;
                        }
                    }
                }
            }
        }

        return $filter;
    }

    /**
     * Метод formatDate преобразует дату в нужный формат с использованием методов Битрикса.
     * 
     * @param string $date Дата в строковом формате.
     * @param bool $isMinDate Флаг, указывающий, является ли дата минимальной.
     * @return Date Дата в формате \Bitrix\Main\Type\Date.
     */
    private static function formatDate(string $date, bool $isMinDate = true): Date
    {
        try {
            if ($isMinDate) {
                $date .= ' 00:00:00';
            } else {
                $date .= ' 23:59:59';
            }
            return new Date($date, 'Y-m-d H:i:s');
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Invalid date format: $date");
        }
    }
}
