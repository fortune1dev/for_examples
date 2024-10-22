<?php
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use \Bitrix\Main\Web\HttpClient;

/**
 * Класс для загрузки контента с сайта поставщика
 * @param array $params копия параметров для конструктора
 * @param string $authData JSON данные авторизации для POST запроса
 * @param string $baseUrl основа URL для передачи запроса
 * @param string $token токен авторизации полученный от поставщика
 * 
 *  */
final class ContentLoader {
    private $params = null;
    private $authData = null;
    private $baseUrl = null;
    private $token = null;

    /**
     * @param array $params массив параметров для авторизации
     * @param string $url базовый URL для запросов
     */
    public function __construct(array $params = null, string $url) {
        $this->params   = $params;
        $this->authData = json_encode($this->params);
        if (is_null($this->authData)) {
            throw new \Exception('Error decoding requests params');
        }
        $this->baseUrl = $url;
    }

    /**
     * Метод получает токен авторизации и сохраняет его в приватое свойство
     * @return string строка ответа от сервера постащика с данными для авторизации
     */
    public function auth() {
        $httpClient = new HttpClient();
        $httpClient->setHeader('Content-Type', 'application/json', true);
        $httpClient->setHeader('Content-Length', strlen($this->authData), true);

        try {
            $result = $httpClient->post($this->baseUrl . 'Auth', $this->authData);
        } catch (\ErrorException $ex) {
            throw new \Exception($ex->getMessage(), $ex->getCode(), $ex->getPrevious());
        }

        if ($result === false) {
            throw new \Exception();
        }

        if (!empty($result))
            $this->token = json_decode($result)->auth_token;

        return $result;
    }

    /**
     * "Волшебный" метод перехватывае обращения к несуществующим методам класса
     * предполагая, что они носят названия ресурсов RPC API поставщика
     * @return string возращает ответы сервера поставщика
     */
    function __call($method, $arguments) {
        if (method_exists($this, $method))
            $this->$method($arguments);
        else
            return $this->get($method, $arguments);
    }

    /**
     * Формирует запросы к ресурсам RPC API
     * @param string $method название ресурса, оно же название вызываемого метода Класса
     * @param array $arguments массив параметров для  http_build_query()
     * @return string возвращает ответ сервера поставщика
     */
    private function get($method, $arguments) {
        $httpClient = new HttpClient();
        $httpClient->setHeader('Authorization', 'Bearer ' . $this->token, true);

        try {
            $url    = $this->baseUrl . $method . '?' . http_build_query($arguments[0]);
            $result = $httpClient->get($url);
        } catch (\ErrorException $ex) {
            throw new \Exception($ex->getMessage(), $ex->getCode(), $ex->getPrevious());
        }

        if ($result === false) {
            throw new \Exception();
        }

        return $result;
    }
}