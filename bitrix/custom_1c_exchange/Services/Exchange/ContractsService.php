<?php

namespace Exchange\Services\Exchange;

use Exchange\Services\ExchangeService;
use Illuminate\Support\Str;
use Psr\Http\Message\ServerRequestInterface;

class ContractsService extends ExchangeService
{

    protected string $iblockCode = 'CONTRACT';

    public function export(): array
    {
        return [];
    }

    public function import(ServerRequestInterface $request): array
    {
        $aItems = $request->getParsedBody();

        $aBusinesses = $this->getServiceItems('BUSINESS');
        $aBuyers = $this->getServiceItems('BUYERS');
        $aCenters = $this->getServiceItems('CENTERS');
        \CModule::IncludeModule('catalog');
        $aPriceTypes = [];
        $oPriceTypes = \CCatalogGroup::GetList();
        while ($aPriceType = $oPriceTypes->Fetch()){
            if(!empty($aPriceType['XML_ID']))
                $aPriceTypes[$aPriceType['XML_ID']] = $aPriceType;
        }

        foreach ($aItems as $aItem) {

            $aBusiness = $aBusinesses[$aItem['BUSINESS_XML_ID']] ?? [];
            if(empty($aBusiness)){
                return [
                    'success' => false,
                    'message' => 'XML_ID ' . $aItem['XML_ID'] . '. Не найден бизнес с XML_ID ' . $aItem['BUSINESS_XML_ID']
                ];
            }
            $aBuyer = $aBuyers[$aItem['BUYER_XML_ID']] ?? [];
            if(empty($aBuyer)){
                return [
                    'success' => false,
                    'message' => 'XML_ID ' . $aItem['XML_ID'] . '. Не найден покупатель с XML_ID ' . $aItem['BUYER_XML_ID']
                ];
            }
            $aCenter = $aCenters[$aItem['CENTER_XML_ID']] ?? [];
            if(empty($aCenter)){
                return [
                    'success' => false,
                    'message' => 'XML_ID ' . $aItem['XML_ID'] . '. Не найден центр с XML_ID ' . $aItem['CENTER_XML_ID']
                ];
            }
            $_priceTypes = [];
            foreach ($aItem['CATALOG_GROUP_XML_ID'] as $priceXmlID)
            {
                $priceType = $aPriceTypes[$priceXmlID] ?? [];
                if(empty($priceType)){
                    return [
                        'success' => false,
                        'message' => 'XML_ID ' . $aItem['XML_ID'] . '. Не найдена цена с XML_ID ' . $priceXmlID
                    ];
                }
                $_priceTypes[] = $priceType['ID'];
            }

            $resp = $this->writeItem([
                'XML_ID' => $aItem['XML_ID'],
                'NAME' => $aItem['NAME'],
                'CODE' => Str::slug($aItem['XML_ID']),
                'ACTIVE' => $aItem['ACTIVE']
            ], [
                'BUSINESS_ID' => $aBusiness['ID'],
                'CENTER_ID' => $aCenter['ID'],
                'BUYER_ID' => $aBuyer['ID'],
                'CATALOG_GROUP_IDS' => $_priceTypes,
            ]);
            if(is_array($resp)){
                return $resp;
            }
        }

        return [
            'success' => true
        ];
    }
}