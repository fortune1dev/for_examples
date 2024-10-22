<?php

namespace Exchange\Controllers;

use Exchange\Services\Exchange\BrandsService;
use Exchange\Services\Exchange\BusinessService;
use Exchange\Services\Exchange\BuyersService;
use Exchange\Services\Exchange\CategoryService;
use Exchange\Services\Exchange\CentersService;
use Exchange\Services\Exchange\ConsigneeService;
use Exchange\Services\Exchange\ContractsService;
use Exchange\Services\Exchange\CreditInfoOperationsService;
use Exchange\Services\Exchange\CreditInfoService;
use Exchange\Services\Exchange\CurrencyRateService;
use Exchange\Services\Exchange\ImagesService;
use Exchange\Services\Exchange\ObjectDiscountsService;
use Exchange\Services\Exchange\OffersService;
use Exchange\Services\Exchange\OrderDocumentsService;
use Exchange\Services\Exchange\OrdersService;
use Exchange\Services\Exchange\PricesService;
use Exchange\Services\Exchange\PriceTypeService;
use Exchange\Services\Exchange\ProductService;
use Exchange\Services\Exchange\RemainsService;
use Exchange\Services\IExchange;
use Bitrix\Main\ArgumentNullException;
use ClickHouseDB\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ExchangeController
{

    protected $services = [
        'buyer' => BuyersService::class,
        'buyers' => BuyersService::class,
        'business' => BusinessService::class,
        'center' => CentersService::class,
        'contract' => ContractsService::class,
        'consignee' => ConsigneeService::class,
        'brand' => BrandsService::class,
        'price-type' => PriceTypeService::class,
        'category' => CategoryService::class,
        'product' => ProductService::class,
        'images' => ImagesService::class,
        'trade-offer' => OffersService::class,
        'prices' => PricesService::class,
        'remains' => RemainsService::class,
        'object-discount' => ObjectDiscountsService::class,
        'credit-info' => CreditInfoService::class,
        'credit-info-operations' => CreditInfoOperationsService::class,
        'order-documents' => OrderDocumentsService::class,
        'order' => OrdersService::class,
        'orders' => OrdersService::class,
        'currency-rates' => CurrencyRateService::class,
    ];

    public function import(string $service, ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        /**@var $service IExchange */
        try {
            $_service = $service;
            $service = new $this->services[$service]();
            /**@var $service IExchange */
            $body = $service->import($request);
        } catch (\Throwable|ArgumentNullException $throwable) {
            $body = [
                'success' => false,
                'message' => $throwable->getMessage(),
            ];
        }

        $response->getBody()->write(json_encode($body, JSON_UNESCAPED_UNICODE));

        if ($body['success'] === false) {
            $response = $response->withStatus(500, $body['message']);
        }

        $this->logInfo(
            $_service,
            'import',
            $request->getBody()->getContents(),
            json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $response->getStatusCode()
        );

        return $response->withStatus(
            $body['success'] ? 200 : 500,
        );
    }

    public function export(string $service, ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $_service = $service;
        $service = new $this->services[$service]();

        try {
            /**@var $service IExchange */
            $body = $service->export();
        } catch (\Throwable $throwable) {
            $body = [
                'success' => false,
                'message' => $throwable->getMessage(),
            ];
        }

        $response->getBody()->write(json_encode($body));

        $this->logInfo(
            $_service,
            'export',
            $request->getBody()->getContents(),
            json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $response->getStatusCode()
        );

        return $response->withStatus(
            $body['success'] ? 200 : 500,
        );

    }

    protected function logInfo($service, $method, $request, $response, $status)
    {
        try {
            $config = [
                'host' => CLICKHOUSE_HOST ?? 'localhost',
                'port' => '8123',
                'username' => 'default',
                'password' => '',
                'https' => false
            ];
            $db = new Client($config);
            $db->database('default');
            $db->insert(
                'log',
                [
                    [
                        time(),
                        $service,
                        $method,
                        $request,
                        $response,
                        $status
                    ]
                ],
                [
                    'created_at',
                    'service',
                    'method',
                    'request',
                    'response',
                    'status',
                ]
            );
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
}
