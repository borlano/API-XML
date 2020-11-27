<?php

namespace borlano\api_xml;

use http\Exception;
use SimpleXMLElement;

/**
 * Вспомогательный класс для работы с XML API программы RBS360
 * Документация API: https://developers.rbs360.ru/integraciya-api-xml/.
 */
class Xml_api
{
    /**
     *    property string $url - хранение адреса сервера с XRM
     *    property string $login - хранение логина авторизации сервера с XRM
     *    property string $pass - хранение пароля авторизации сервера с XRM
     *    property string $sess_id - хранение хэша сессии после авторизации сервера с XRM.
     */
    private $url;

    private $login;

    private $pass;

    private $sess_id;

    /**
     * Создание подключения и получение sess_id.
     *
     * @param $url
     * @param $login
     * @param $pass
     */
    public function __construct($url, $login, $pass)
    {
        $this->url = $url;
        $this->login = $login;
        $this->pass = $pass;
        $this->_auth();
    }

    /**
     * @return string
     */
    public function head()
    {
        return "<?xml version='1.0' encoding='utf8' ?><request>";
    }

    public function footer()
    {
        return '</request>';
    }

    /**
     * Создание запроса на авторизацию и отправка его на сервер, в случае успеха установка свойства sess_id.
     */
    private function _auth()
    {
        $request = $this->head()
            ."<action type='auth' uid='80085'>
			<login>".$this->login.'</login>
			<password>'.$this->pass.'</password>
			</action>'
            .$this->footer();

        $response = $this->send($this->url, 'POST', ['xml' => $request]);
        if ($response_parse = self::_parse($response)) {
            $this->sess_id = $response_parse->action->sess_id;
        }
    }

    /**
     *    отправка xml запроса на сервер
     *
     * @param $url
     * @param string $method
     * @param array  $args
     * @param array  $cookie
     *
     * @return bool|string
     */
    public function send($url, $method = 'GET', $args = [], $cookie = [])
    {
        $ch = curl_init();
        $query_string = '';
        foreach ($args as $key => $val) {
            $query_string .= $key.'='.$val.'&';
        }

        if ($method == 'GET') {
            $url .= '?'.$query_string;
        }

        curl_setopt($ch, CURLOPT_URL, $url);

        $cookies = '';

        foreach ($cookie as $key => $val) {
            $cookies .= "$key=".$val.';';
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/28.0.1500.95 Safari/537.36');
        curl_setopt($ch, CURLOPT_REFERER, $url);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $query_string);
        }

        $resp = curl_exec($ch);

        return $resp;
    }

    /**
     *    Парсинг xml c помощью библиотеки SimpleXMLElement.
     *
     * @param $xml
     *
     * @return SimpleXMLElement
     */
    private static function _parse($xml)
    {
        try {
            return new SimpleXMLElement($xml);
        } catch (Exception $e) {
        }
    }

    /**
     * Получение ID сессии.
     *
     * @return mixed
     */
    public function getSessionId()
    {
        return $this->sess_id;
    }

    /**
     *    создание запроса на получение данных.
     *
     * @param $structure
     * @param array $fields
     * @param array $filters
     * @param array $limits
     * @param array $orders
     *
     * @return SimpleXMLElement
     */
    public function select($structure, $fields = [], $filters = [], $limits = [], $orders = [])
    {
        $request = "<?xml version='1.0' encoding='utf8' ?>
			<request>
			<action type='list' uid='80085'>
			<structure name='".$structure."'>".'
			'.$this->_prepareFields($fields).'
			'.$this->_prepareFilters($filters).'
			'.$this->_prepareOrders($orders).'
			'.$this->_prepareLimits($limits).'     								
			</structure>
			</action>
			</request>';

        return $this->_parse($this->send($this->url, 'POST', ['xml' => $request], ['sess_id' => $this->sess_id]));
    }

    /**
     *    преобразование массива полей в xml.
     *
     * @param $fields
     *
     * @return string
     */
    private function _prepareFields($fields)
    {
        $fields_xml = '<fields>';
        foreach ($fields as $field => $value) {
            if (is_numeric($field)) {
                $field = $value;
                $value = '';
            }
            $fields_xml .= '
				<'.$field.'>'.$value.'</'.$field.'>';
        }
        $fields_xml .= '</fields>';

        return $fields_xml;
    }

    /**
     *    преобразование массива фильтров в xml.
     *
     * @param $filters
     *
     * @return string
     */
    private function _prepareFilters($filters)
    {
        $filters_xml = '<filters>';

        foreach ($filters as $filter) {
            $filters_xml .= '<filter>
				<field>'.$filter['field'].'</field>
				<operation>'.$filter['operation'].'</operation>
				<value>'.$filter['value'].'</value>
				</filter>';
        }

        $filters_xml .= '</filters>';

        return $filters_xml;
    }

    /**
     * @param $orders
     *
     * @return string
     */
    private function _prepareOrders($orders)
    {
        $orders_xml = '<orders>';

        foreach ($orders as $order) {
            $orders_xml .= '<order>
				<field>'.$order['field'].'</field>
				<type>'.$order['type'].'</type>
				</order>';
        }
        $orders_xml .= '</orders>';

        return $orders_xml;
    }

    /**
     * @param $limits
     *
     * @return string
     */
    private function _prepareLimits($limits)
    {
        $filters_xml = '';

        if (count($limits)) {
            $filters_xml .= '<limit>
				<first>'.$limits[0].'</first>
				<number>'.$limits[1].'</number>
				</limit>';
        }

        return $filters_xml;
    }

    /**
     *    создание запроса на добавление данных.
     *
     * @param $structure
     * @param array $fields
     *
     * @return SimpleXMLElement
     */
    public function add($structure, $fields = [])
    {
        $request = "<?xml version='1.0' encoding='utf8' ?>
			<request>
			<action type='add' uid='80085'>
			<structure name='".$structure."'>".'
			'.$this->_prepareFields($fields).'     								
			</structure>
			</action>
			</request>';

        return $this->_parse($this->send($this->url, 'POST', ['xml' => $request], ['sess_id' => $this->sess_id]));
    }

    /**
     * @param $structure
     * @param $id
     * @param array $fields
     *
     * @return SimpleXMLElement
     */
    public function fileAdd($structure, $id, $fields = [])
    {
        $request = "<?xml version='1.0' encoding='utf8' ?>
			<request>
			<action type='fileAdd' uid='80085'>
			<structure name='".$structure."' id='".$id."'>".$this->_prepareFields($fields).'</structure>
			</action>
			</request>';

        return $this->_parse($this->send($this->url, 'POST', ['xml' => $request], ['sess_id' => $this->sess_id]));
    }

    /**
     * @param $id
     *
     * @return SimpleXMLElement
     */
    public function paymentCalc($id)
    {
        $request = "<?xml version='1.0' encoding='utf8' ?>
			<request>
			<action type='paymentCalc' uid='80085'>
			<structure id='".$id."'></structure>
			</action>
			</request>";

        return $this->_parse($this->send($this->url, 'POST', ['xml' => $request], ['sess_id' => $this->sess_id]));
    }

    /**
     *    создание запроса на редактирование данных.
     *
     * @param $structure
     * @param array $fields
     * @param array $filters
     *
     * @return SimpleXMLElement
     */
    public function update($structure, $fields = [], $filters = [])
    {
        $request = "<?xml version='1.0' encoding='utf8' ?>
			<request>
			<action type='edit' uid='80085'>
			<structure name='".$structure."'>".'
			'.$this->_prepareFields($fields).'
			'.$this->_prepareFilters($filters).'     								
			</structure>
			</action>
			</request>';

        return $this->_parse($this->send($this->url, 'POST', ['xml' => $request], ['sess_id' => $this->sess_id]));
    }

    /**
     * Удаление элемента из CRM.
     *
     * @param $structure
     * @param $id
     *
     * @return SimpleXMLElement
     */
    public function delete($structure, $id)
    {
        $request = "<?xml version='1.0' encoding='utf8' ?>
			<request>
			<action type='wipe' uid='80085'>
			<structure name='".$structure."' id='".$id."'>     								
			</structure>
			</action>
			</request>";

        return $this->_parse($this->send($this->url, 'POST', ['xml' => $request], ['sess_id' => $this->sess_id]));
    }
}
