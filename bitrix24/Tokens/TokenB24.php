<?php

namespace Fbl\Tokens;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Diag\Debug;

class TokenB24
{
    private static $moduleId = "fbl.donationevents";
    private static $baseUrl = "https://oauth.bitrix.info";

    /**
     * Обновляет access_token с использованием refresh_token.
     * 
     * @return void
     */
    public static function refreshAccessToken(): bool
    {
        $clientId = Option::get(self::$moduleId, 'client_id', '');
        $clientSecret = Option::get(self::$moduleId, 'client_secret', '');
        $refreshToken = Option::get(self::$moduleId, 'refresh_token', '');

        $url = self::$baseUrl . "/oauth/token/?grant_type=refresh_token&client_id={$clientId}&client_secret={$clientSecret}&refresh_token={$refreshToken}";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, false);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        if (isset($result['access_token']) && isset($result['refresh_token'])) {
            Option::set(self::$moduleId, 'access_token', $result['access_token']);
            Option::set(self::$moduleId, 'refresh_token', $result['refresh_token']);
            return true;
        } else {
            // Обработка ошибки, если не удалось получить новые токены
            Debug::writeToFile($result, "refreshAccessToken error", "/local/logs/my_events.log");
            return false;
        }
    }

    /**
     * Получает текущий access_token.
     * 
     * @return string
     */
    public static function getAccessToken(): string
    {
        return Option::get(self::$moduleId, 'access_token', '');
    }

    /**
     * Получает текущий auth_token.
     * 
     * @return string
     */
    public static function getAuthToken(): string
    {
        return Option::get(self::$moduleId, 'auth_token', '');
    }
}
