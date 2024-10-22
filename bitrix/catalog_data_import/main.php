<?php
set_time_limit(0);

//подтягиваем ContentLoader класс
spl_autoload_register(function ($class_name) {
    include $class_name . '.php';
});

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'helpers.php');

// загружаем конфиг, включая данные авторизации
$params = require __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
$loader = new ContentLoader(
    [
        'email'    => $params['login'],
        'password' => $params['password']
    ],
    $params['base_url']
);
$result = $loader->auth(); // авторизовываемся

$offset = 0; //начало выгрузки
$size   = 200; //смещение

do {
    try {
        $result = $loader->GetStocks(['pageSize' => $size, 'offset' => $offset, 'category' => -1]);
        $xml    = new SimpleXMLElement($result); // конвертнули XML-текст в XML-объект
        if (count($xml->offer) <= 0)
            return; // если больше ничего не получили - выходим

        foreach ($xml->offer as $key => $product) {
            $searchResult = findByManufacturedValue($product->number);
            if ($searchResult > 0) { // если нашли такой товар в каталоге на сайте, то обновляем наличиче
                $sum = 0;
                foreach ($product->stocks->stock as $key => $stock) { //считаем остатки на всех их складах
                    $sum += $stock[0];
                }
                setQuantity($searchResult, $params['warehouse'], intval($sum));
            }
        }
    } catch (\Throwable $th) {
        return $th->getMessage();
    }
    $offset += $size;
} while (true);