<?php

namespace Fbl\Api;

use Fbl\Tokens\TokenB24;
use Bitrix\Main\Diag\Debug;

class Bitrix24Api
{
    private const API_URL = 'https://xxxxxx/rest/crm.deal.list.json';

    /**
     * Получает информацию о сделке через REST API Битрикс24.
     * 
     * @param int $dealId ID сделки.
     * @return array|null Информация о сделке или null в случае ошибки.
     */
    public static function getDealInfo(int $dealId): ?array
    {
        Debug::writeToFile("dealInfo ID: {$dealId}", '', "/local/logs/my_events.log");

        $url = self::buildUrlWithToken();
        $data = [
            'filter' => ['ID' => $dealId],
            'select' => ['*', 'UF_*']
        ];

        $headers = [
            'Content-Type: application/json',
            'authtoken: ' . TokenB24::getAuthToken(),
        ];

        $response = self::makeRequest($url, $data, $headers);

        if ($response === null) {
            return null;
        }

        return $response['result'][0] ?? null;
    }

    /**
     * Создает URL с текущим токеном доступа.
     * 
     * @return string URL с токеном.
     */
    private static function buildUrlWithToken(): string
    {
        return self::API_URL . '?auth=' . TokenB24::getAccessToken();
    }

    /**
     * Выполняет HTTP-запрос к API Битрикс24.
     * 
     * @param string $url URL для запроса.
     * @param array $data Данные для отправки.
     * @param array $headers Заголовки запроса.
     * @return array|null Ответ от API или null в случае ошибки.
     */
    private static function makeRequest(string $url, array $data, array $headers): ?array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($httpCode == 401) {
            return self::handleUnauthorized($url, $data, $headers);
        }

        return json_decode($response, true);
    }

    /**
     * Обрабатывает ошибку 401 (Unauthorized) и повторяет запрос с обновленным токеном.
     * 
     * @param string $url URL для запроса.
     * @param array $data Данные для отправки.
     * @param array $headers Заголовки запроса.
     * @return array|null Ответ от API или null в случае ошибки.
     */
    private static function handleUnauthorized(string $url, array $data, array $headers): ?array
    {
        if (!TokenB24::refreshAccessToken()) {
            Debug::writeToFile("API Error: ошибка refreshAccessToken()", "", "/local/logs/my_events.log");
            return null;
        }

        $url = self::buildUrlWithToken();
        return self::makeRequest($url, $data, $headers);
    }
}
