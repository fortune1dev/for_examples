<?php
\Bitrix\Main\Loader::includeModule('iblock');
\Bitrix\Main\Loader::includeModule('catalog');

/**
 * Функция обновляет цену у товара
 * @param int $productId ID элемента каталога
 * @param int $storeId ID склада
 * @param int $quantity количество товара
 * 
 */
function setQuantity(int $productId, int $storeId, int $quantity) {
    $rsStoreProduct = \Bitrix\Catalog\StoreProductTable::getList(array(
        'filter' => array('=PRODUCT_ID' => $productId, 'STORE.ACTIVE' => 'Y'),
    ));
    if ($arStoreProduct = $rsStoreProduct->fetch()) {
        $updateStore = \Bitrix\Catalog\StoreProductTable::update($arStoreProduct['ID'], array('PRODUCT_ID' => $productId, 'STORE_ID' => $storeId, 'AMOUNT' => $quantity));

    } else {
        $addStore = \Bitrix\Catalog\StoreProductTable::add(array('PRODUCT_ID' => $productId, 'STORE_ID' => $storeId, 'AMOUNT' => $quantity));
    }

    $updateQuantity = \Bitrix\Catalog\ProductTable::update($productId, array('STORE_ID' => $storeId, 'QUANTITY' => $quantity, 'AVAILABLE' => 'Y'));
    return $updateQuantity;
}

/**
 * Ищет товар в каталоге на сайте по значению поля MPN_MANUFACTURED.
 * @param string $value значение для поиск
 * @return int возвращает ID найденного товара или 0, если ничего не найдено. Если товаров с MPN_MANUFACTURED несколько, 
 * то вернет первый попавшийся.
 */
function findByManufacturedValue(string $value): int {
    $elements = \Bitrix\Iblock\Elements\ElementCatalogapiTable::getList([
        'select' => ['ID', 'NAME', 'BRAND', 'MPN_MANUFACTURED'],
        'filter' => ['=ACTIVE' => 'Y', 'BRAND.VALUE' => 162890, 'MPN_MANUFACTURED.VALUE' => $value],
    ])->fetchCollection();

    if (count($elements->getAll()) <= 0)
        return false;

    foreach ($elements as $element) {
        return $element->getId(); //возвращаем первый найденый элемент
    }
}