<?php

namespace Exchange\Services\Exchange;

trait ExtendXmlIdField
{
    private function findByName(string $name): array
    {
        $oServices = \CIBlockElement::GetList([], [
            'IBLOCK_CODE' => $this->iblockCode,
            '=NAME' => $name
        ], false, false, ['ID', 'XML_ID', 'NAME', 'IBLOCK_ID']);

        if ($aService = $oServices->Fetch()) {
            return $aService;
        }

        return [];
    }

    /**
     * Сохраняет дополнительный XML_ID в свойство ADDITIONAL_XML_ID
     * @param array $brand
     * @param string $xmlId
     * @return array
     */
    private function saveAdditionalItem(array $brand, $xmlId): array
    {
        $propertyIterator = \CIBlockElement::GetProperty(
            $brand['IBLOCK_ID'],
            $brand['ID'],
            [],
            ['CODE' => 'ADDITIONAL_XML_ID']
        );

        $propertyValues = [$xmlId];
        while ($property = $propertyIterator->GetNext()) {
            if (!empty($property['VALUE'])) {
                $propertyValues[] = $property['VALUE'];
            }
        }

        $propertyValues = array_unique($propertyValues);

        \CIBlockElement::SetPropertyValuesEx(
            $brand['ID'],
            $brand['IBLOCK_ID'],
            ['ADDITIONAL_XML_ID' => $propertyValues]
        );

        return $propertyValues;
    }
}
