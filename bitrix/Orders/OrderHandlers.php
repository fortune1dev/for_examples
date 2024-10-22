<?php

declare(strict_types=1);

namespace Handlers;

use Bitrix\Sale\BasketItem;
use Bitrix\Sale\Order;
use Bitrix\Sale\PropertyValue;
use Bitrix\Main\Diag\Debug;
use Dompdf\Dompdf;
use Dompdf\Options;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Bitrix\Main\UserTable;

/**
 * Класс OrderHandlers содержит методы для обработки события создания заказа
 */
final class OrderHandlers
{
    const PDF_DIR = "/upload/orders/";
    const EMAIL_TO = 'xxxxxxxx@mail.ru';
    const EMAIL_FROM = 'yyyyyyy@mail.ru';
    const HIDDEN_ORDER_PROPS = [
        'NEW_STORE_ID',
        'SHIPMENT_DATE',
        'DELIVERY_DATE',
        'UPDATED_1C',
        'EMAIL'
    ];
    const IBLOCK_CODES_PROPS = [
        'BUSINESS_ID',
        'CENTER_ID',
        'BUYER_ID',
        'CONTRACT_ID',
        'CONSIGNEE_ID',
        'STORE_ID',
    ];

    /**
     * Метод calculateTotalWeight рассчитывает общий вес заказа в OnSaleOrderBeforeSaved.
     * 
     * @param \Bitrix\Main\Event $event Событие Bitrix, содержащее информацию о заказе.
     * @return void
     */
    public static function calculateTotalWeight(\Bitrix\Main\Event $event): void
    {
        $order = $event->getParameter("ENTITY");

        try {
            $totalWeight = 0;
            $totalPallets = 0;
            $totalTransportBoxes = 0;

            /** @var BasketItem $item */
            foreach ($order->getBasket() as $item) {
                $productId = $item->getProductId();
                $quantity = $item->getQuantity();

                // Получаем свойства товара из инфоблока торговых предложений
                $productProps = \Bitrix\Iblock\ElementPropertyTable::getList([
                    'filter' => [
                        'IBLOCK_ELEMENT_ID' => $productId,
                    ],
                    'select' => ['IBLOCK_PROPERTY_ID', 'VALUE'],
                ])->fetchAll();

                if (!$productProps) {
                    continue;
                }

                // Получаем коды свойств
                $propertyIds = array_column($productProps, 'IBLOCK_PROPERTY_ID');
                $propertyCodes = \Bitrix\Iblock\PropertyTable::getList([
                    'filter' => ['ID' => $propertyIds],
                    'select' => ['ID', 'CODE'],
                ])->fetchAll();

                $propertyCodes = array_column($propertyCodes, 'CODE', 'ID');

                // Преобразуем массив свойств в ассоциативный массив по кодам свойств
                $productProps = array_reduce($productProps, function ($carry, $item) use ($propertyCodes) {
                    $code = $propertyCodes[$item['IBLOCK_PROPERTY_ID']];
                    $carry[$code] = $item['VALUE'];
                    return $carry;
                }, []);

                // Вес штуки брутто
                $grossWeightPcs = (float)$productProps['GROSS_WEIGHT_PCS_KG'];
                // Вес брутто короба
                $grossWeightBox = (float)$productProps['GROSS_WEIGHT_BOX_KG'];
                // Вложение в короб
                $quantityPcsPerBox = (int)$productProps['QUANTITY_PCS_PER_BOX'];
                // Объем, м3
                $volumePcsM3 = (float)$productProps['VOLUME_PCS_M3'];
                // Количество шт. в паллете
                $quantityPcsPerPallet = (int)$productProps['QUANTITY_PCS_PER_PALLETE'];

                // Если QUANTITY_PCS_PER_PALLETE==0, то вычисляем количество паллет через объем товара
                if ($quantityPcsPerPallet == 0) {
                    $totalVolume = $volumePcsM3 * $quantity;
                    $pallets = ceil($totalVolume / 134.4);
                } else {
                    $pallets = ceil($quantity / $quantityPcsPerPallet);
                }

                // Вес штук брутто в коробе
                $grossWeightPcsInBox = $grossWeightPcs * $quantityPcsPerBox;
                // Вес транспортировочного короба
                $transportBoxWeight = $grossWeightBox - $grossWeightPcsInBox;

                // Вес товаров
                $totalWeight += $grossWeightPcs * $quantity;

                $totalPallets += $pallets;

                // Количество транспортировочных коробов
                $transportBoxes = ceil($quantity / $quantityPcsPerBox);
                $totalTransportBoxes += $transportBoxes;
            }

            // Вес паллет (каждая 25 кг)
            $totalWeight += $totalPallets * 25;

            // Вес транспортировочных коробов
            $totalWeight += $totalTransportBoxes * $transportBoxWeight;

            $propertyCollection = $order->getPropertyCollection();
//            $weightProperty = $propertyCollection->getItemByOrderPropertyCode('WEIGHT');
//            if ($weightProperty) {
//                $weightProperty->setValue($totalWeight);
//            }
//

            $session = \Bitrix\Main\Application::getInstance()->getSession();
            if ($session->has('is_cart_calculated') && $session->has('onec_calculate')) {
                $oneCCalculate = json_decode($session->get('onec_calculate'), true);
                $palletsProperty = $propertyCollection->getItemByOrderPropertyCode('PALLETS');
                if ($palletsProperty) {
                    $palletsProperty->setValue($oneCCalculate['PALLETS']);
                }
                $grossWeightProperty = $propertyCollection->getItemByOrderPropertyCode('WEIGHT_GROSS');
                if ($grossWeightProperty) {
                    $grossWeightProperty->setValue($oneCCalculate['GROSS']);
                }
                $netWeightProperty = $propertyCollection->getItemByOrderPropertyCode('WEIGHT_NET');
                if ($netWeightProperty) {
                    $netWeightProperty->setValue($oneCCalculate['NET']);
                }
                $volumeProperty = $propertyCollection->getItemByOrderPropertyCode('VOLUME');
                if ($volumeProperty) {
                    $volumeProperty->setValue($oneCCalculate['VOLUME']);
                }
            }
            $session->remove('onec_calculate');
            $session->remove('is_cart_calculated');
            $transportBoxesProperty = $propertyCollection->getItemByOrderPropertyCode('TRANSPORT_BOXES');
            if ($transportBoxesProperty) {
                $transportBoxesProperty->setValue($totalTransportBoxes);
            }


            Debug::dumpToFile("Order #{$order->getId()} total weight updated to {$totalWeight}.");
        } catch (\Exception $e) {
            Debug::dumpToFile($e->getMessage() . "\n" . $e->getTraceAsString());
            throw $e; // Передаем исключение дальше
        }
    }


    /**
     * Метод sendOrderAsPDF - обработчик события OnSaleOrderSaved.
     * 
     * @param \Bitrix\Main\Event $event Событие Bitrix, содержащее информацию о заказе.
     * @return void
     */
    public static function sendOrderAsPDF(\Bitrix\Main\Event $event): void
    {
        if (!$event->getParameter("IS_NEW")) {
            return;
        }

        $order = $event->getParameter("ENTITY");

        try {
            $offerList = self::getBasketData($order);
            $orderProps = self::getOrderProps($order);
            $orderProps = self::getOrderPropsValues($orderProps);
            $orderSummary = self::getOrderSummary($order);

            $pdf = self::createPDF($order, $offerList, $orderProps, $orderSummary);
            self::sendPDF($pdf, $order);
            self::savePDFToFile($pdf, $order);

            Debug::dumpToFile("Order #{$order->getId()} processed successfully.");
        } catch (\Exception $e) {
            Debug::dumpToFile($e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }

    /**
     * Метод getBasketData извлекает данные корзины заказа.
     * 
     * @param Order $order Объект заказа.
     * @return array Массив с данными корзины.
     */
    private static function getBasketData(Order $order): array
    {
        $offerList = [];
        /** @var BasketItem $item */
        foreach ($order->getBasket() as $item) {
            $offerList[$item->getProductId()] = [
                'ORDER_ID' => $order->getId(),
                'NAME' => $item->getField('NAME'),
                'PRICE' => $item->getPrice(),
                'QUANTITY' => $item->getQuantity(),
                'TOTAL' => $item->getPrice() * $item->getQuantity(),
                'CURRENCY' => $item->getCurrency(),
            ];
        }
        return $offerList;
    }

    /**
     * Метод getOrderProps извлекает свойства заказа.
     * 
     * @param Order $order Объект заказа.
     * @return array Массив со свойствами заказа.
     */
    private static function getOrderProps(Order $order): array
    {
        $orderProps = [];
        /** @var PropertyValue $propertyValue */
        foreach ($order->getPropertyCollection() as $propertyValue) {
            if (in_array($propertyValue->getField('CODE'), self::HIDDEN_ORDER_PROPS) || empty($propertyValue->getValue()))
                continue;

            $orderProps[$propertyValue->getField('CODE')] = [
                'NAME' => $propertyValue->getField('NAME'),
                'VALUE' => in_array($propertyValue->getField('CODE'), self::IBLOCK_CODES_PROPS) ? $propertyValue->getValue() : $propertyValue->getViewHtml(),
            ];
        }
        return $orderProps;
    }

    /**
     * Метод getOrderSummary возвращает массив с общими данными о заказе.
     * 
     * @param Order $order Объект заказа.
     * @return array Массив с общими данными о заказе.
     */
    private static function getOrderSummary(Order $order): array
    {
        $totalWeight = 0;
        $totalPriceWithoutVAT = 0;
        $totalVAT = 0;
        $totalPriceWithVAT = 0;

        /** @var BasketItem $item */
        foreach ($order->getBasket() as $item) {
            $totalWeight += $item->getWeight() * $item->getQuantity();
            $totalPriceWithoutVAT += $item->getPrice() * $item->getQuantity();
            $totalVAT += $item->getVat() * $item->getQuantity();
        }

        $totalPriceWithVAT = $totalPriceWithoutVAT + $totalVAT;

        return [
            'TOTAL_WEIGHT' => $totalWeight,
            'TOTAL_PRICE_WITHOUT_VAT' => $totalPriceWithoutVAT,
            'TOTAL_VAT' => $totalVAT,
            'TOTAL_PRICE_WITH_VAT' => $totalPriceWithVAT,
            'TOTAL_DISCOUNT_PRICE' => $order->getDiscountPrice(), // Размер скидки
            'DELIVERY_PRICE' => $order->getDeliveryPrice(), // Стоимость доставки
        ];
    }

    /**
     * Метод getOrderPropsValues заменяет ID элементов инфоблока на их NAME в массиве свойств заказа.
     * 
     * @param array $orderProps Массив со свойствами заказа.
     * @return array Массив со свойствами заказа с замененными ID на NAME.
     */
    private static function getOrderPropsValues(array $orderProps): array
    {
        $iblockElementMap = [];

        // Получаем все элементы инфоблока, которые могут быть использованы в свойствах заказа
        $iblockElementIds = array_filter(array_map(function ($propCode) use ($orderProps) {
            return isset($orderProps[$propCode]) ? $orderProps[$propCode]['VALUE'] : null;
        }, self::IBLOCK_CODES_PROPS));

        if (!empty($iblockElementIds)) {
            $iblockElementIds = array_unique($iblockElementIds);

            // Получаем данные элементов инфоблока
            $iblockElementMap = \Bitrix\Iblock\ElementTable::getList([
                'filter' => ['ID' => $iblockElementIds],
                'select' => ['ID', 'NAME'],
            ])->fetchAll();

            $iblockElementMap = array_column($iblockElementMap, 'NAME', 'ID');
        }

        // Заменяем ID на NAME в свойствах заказа
        foreach ($orderProps as $propCode => &$propValue) {
            if (in_array($propCode, self::IBLOCK_CODES_PROPS) && isset($iblockElementMap[$propValue['VALUE']])) {
                $propValue['VALUE'] = $iblockElementMap[$propValue['VALUE']];
            }
        }

        return $orderProps;
    }

    /**
     * Метод createPDF создает PDF-файл на основе данных заказа.
     * 
     * @param Order $order Объект заказа.
     * @param array $offerList Данные корзины.
     * @param array $orderProps Свойства заказа.
     * @return string Содержимое PDF-файла в виде строки.
     */
    private static function createPDF(Order $order, array $offerList, array $orderProps, array $orderSummary): string
    {
        $html = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';
        $html .= '<h1>Заказ №' . $order->getId() . '</h1>';
        $html .= '<p>Дата заказа: ' . $order->getDateInsert()->format('Y-m-d H:i:s') . '</p>';

        $html .= '<style>
                table {
                    font-size: 12px; /* Уменьшаем размер шрифта в таблице */
                }
                th, td {
                    padding: 5px; /* Уменьшаем отступы в ячейках */
                }
              </style>';

        $html .= '<table border="1" cellpadding="5" cellspacing="0">';
        $html .= '<tr><th>Свойство</th><th>Значение</th><th>Свойство</th><th>Значение</th></tr>';

        $propsCount = count($orderProps);
        $halfCount = ceil($propsCount / 2);

        $keys = array_keys($orderProps);
        $values = array_values($orderProps);

        for ($i = 0; $i < $halfCount; $i++) {
            $html .= '<tr>';
            if (isset($keys[$i])) {
                $html .= '<td>' . $values[$i]['NAME'] . '</td>';
                $html .= '<td>' . $values[$i]['VALUE'] . '</td>';
            } else {
                $html .= '<td></td><td></td>';
            }
            if (isset($keys[$i + $halfCount])) {
                $html .= '<td>' . $values[$i + $halfCount]['NAME'] . '</td>';
                $html .= '<td>' . $values[$i + $halfCount]['VALUE'] . '</td>';
            } else {
                $html .= '<td></td><td></td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';

        $html .= '<h2>Элементы корзины</h2>';
        $html .= '<table border="1" cellpadding="6" cellspacing="0">';
        $html .= '<tr><th>Название</th><th>Цена</th><th>Количество</th><th>Сумма</th><th>Валюта</th></tr>';
        foreach ($offerList as $item) {
            $html .= '<tr>';
            $html .= '<td>' . $item['NAME'] . '</td>';
            $html .= '<td>' . $item['PRICE'] . '</td>';
            $html .= '<td>' . $item['QUANTITY'] . '</td>';
            $html .= '<td>' . $item['TOTAL'] . '</td>';
            $html .= '<td>' . $item['CURRENCY'] . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';

        $html .= '<h2>Итоговая информация о заказе</h2>';
        $html .= '<p>Итого: ' . $orderSummary['TOTAL_PRICE_WITHOUT_VAT'] . ' ' . $order->getCurrency() . '</p>';

        $html .= '</body></html>';

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'dejavu sans'); // Ставим шрифт, поддерживающий UTF-8

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait'); // Для удобства печати выставляем формат страницы А4
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Метод sendPDF отправляет PDF-файл по электронной почте.
     * 
     * @param string $pdf Содержимое PDF-файла в виде строки.
     * @param Order $order Объект заказа.
     * @return void
     */
    private static function sendPDF(string $pdf, Order $order): void
    {
        $subject = 'Заказ №' . $order->getId();
        $message = 'Прилагается PDF-файл с данными заказа.';
        $headers = 'From: ' . self::EMAIL_FROM . "\r\n" .
            'Reply-To: ' . self::EMAIL_FROM . "\r\n" .
            'X-Mailer: PHP/' . phpversion();

        // Добавляем заголовки для вложения файла
        $attachment = chunk_split(base64_encode($pdf));
        $boundary = md5(\Bitrix\Main\Security\Random::getString(10));
        $headers .= "\r\nMIME-Version: 1.0\r\n" .
            "Content-Type: multipart/mixed; boundary=\"" . $boundary . "\"\r\n\r\n";

        $body = "--" . $boundary . "\r\n" .
            "Content-Type: text/plain; charset=\"UTF-8\"\r\n" .
            "Content-Transfer-Encoding: 8bit\r\n\r\n" .
            $message . "\r\n\r\n";

        $body .= "--" . $boundary . "\r\n" .
            "Content-Type: application/pdf; name=\"order_" . $order->getId() . ".pdf\"\r\n" .
            "Content-Transfer-Encoding: base64\r\n" .
            "Content-Disposition: attachment\r\n\r\n" .
            $attachment . "\r\n\r\n";

        $body .= "--" . $boundary . "--";

        // Получаем email-адреса специалистов
        $specialistEmails = self::getSpecialistEmails($order);
        $to = self::EMAIL_TO . ', ' . implode(', ', $specialistEmails);

        // Отправляем письмо
        if (!mail($to, $subject, $body, $headers)) {
            Debug::dumpToFile('Failed to send email for order #' . $order->getId());
        }
    }

    /**
     * Метод savePDFToFile сохраняет PDF-файл на сервере.
     * 
     * @param string $pdf Содержимое PDF-файла в виде строки.
     * @param Order $order Объект заказа.
     * @return void
     */
    private static function savePDFToFile(string $pdf, Order $order): void
    {
        $filename = 'order_' . $order->getId() . '.pdf';
        $filePath = $_SERVER['DOCUMENT_ROOT'] . self::PDF_DIR . $filename;

        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        file_put_contents($filePath, $pdf);
    }

    /**
     * Метод getSpecialistEmails получает email-адреса специалистов, связанных с пользователем, создавшим заказ.
     * 
     * @param Order $order Объект заказа.
     * @return array Массив с email-адресами специалистов.
     */
    private static function getSpecialistEmails(Order $order): array
    {
        $specialistEmails = [];

        // Получаем пользователя, создавшего заказ
        $userId = $order->getUserId();
        $user = UserTable::getById($userId)->fetch();

        if ($user && isset($user['UF_SPECIALIST_USER_IDS']) && is_array($user['UF_SPECIALIST_USER_IDS'])) {
            $specialistUserIds = $user['UF_SPECIALIST_USER_IDS'];

            // Получаем email-адреса специалистов
            $specialistUsers = UserTable::getList([
                'filter' => ['ID' => $specialistUserIds],
                'select' => ['EMAIL'],
            ])->fetchAll();

            $specialistEmails = array_column($specialistUsers, 'EMAIL');
        }

        return $specialistEmails;
    }
}
