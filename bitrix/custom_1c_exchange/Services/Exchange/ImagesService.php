<?php

namespace Exchange\Services\Exchange;

use Exchange\Services\ExchangeService;
use Exchange\Services\Exchange\ExtendXmlIdField;
use Illuminate\Support\Str;
use Psr\Http\Message\ServerRequestInterface;

class ImagesService extends ExchangeService
{
    use ExtendXmlIdField;

    protected string $iblockCode = 'PRODUCTS';

    public function export(): array
    {
        return [];
    }

    public function import(ServerRequestInterface $request): array
    {

        if(empty($_FILES)){
            return [
                'success' => false,
                'message' => 'Отсутствуют данные для загрузки'
            ];
        }

        foreach ($_FILES as $key => $file) {
            $uuid = trim(pathinfo($file['name'], PATHINFO_FILENAME), " \n\r\t\v\0{}");
            $element = \CIBlockElement::GetList([], ['XML_ID' => $uuid, 'IBLOCK_CODE' => $this->iblockCode])->Fetch();
            if(empty($element)){
                return [
                    'success' => false,
                    'message' => sprintf("Товар с XML_ID '%s' не найден!", $uuid)
                ];
            }
            $file["MODULE_ID"] = "iblock"; // добавляем нужный модуль
            $file["description"] = "";
            $ib = (new \CIBlockElement());

            $ib->Update($element['ID'], [
                'DETAIL_PICTURE' => $file
            ]);

            if(!empty($ib->LAST_ERROR)){
                return [
                    'success' => false,
                    'message' => $ib->LAST_ERROR
                ];
            }
        }

        return [
            'success' => true
        ];
    }
}
