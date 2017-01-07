<?php defined('SYSPATH') OR die('No direct script access.');

class Kohana_Ulogin_Request {

    const SEX_UNKNOWN = 0;
    const SEX_FEMALE = 1;
    const SEX_MALE = 2;

    /**
     * @var array
     */
    protected $_response;

    /**
     * @param $token
     * @return Ulogin_Request
     */
    public static function factory($token)
    {
        return new static($token);
    }

    function __construct($token)
    {
        $this->_response = $this->fetch($token);
    }

    /**
     * Получает информацию о пользователе по токену
     * @param $token
     * @return array
     * @throws Ulogin_Exception
     */
    protected function fetch($token)
    {
        $s = Request::factory('https://ulogin.ru/token.php?token='.$token.'&host='.$this->get_domain())->execute()->body();
        $data = json_decode($s, true);

        if ( isset( $data['error'] ) )
            throw new Ulogin_Exception($data['error']);

        return $data;
    }

    protected function get_domain()
    {
        if ( !($domain = parse_url(URL::base(), PHP_URL_HOST)) )
        {
            $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
        }

        return $domain;
    }

    /**
     * @param $key
     * @return mixed|null
     */
    protected function get($key)
    {
        return ( isset($this->_response[$key]) ? $this->_response[$key] : NULL );
    }

    public function as_array()
    {
        return $this->_response;
    }

    /**
     * @return string
     */
    public function network()
    {
        return $this->get(__FUNCTION__);
    }

    public function profile()
    {
        return $this->get(__FUNCTION__);
    }

    public function uid()
    {
        return $this->get(__FUNCTION__);
    }

    public function identity()
    {
        return $this->get(__FUNCTION__);
    }

    public function manual()
    {
        return $this->get(__FUNCTION__);
    }

    public function verified_email()
    {
        return ( $this->get(__FUNCTION__) == 1 );
    }

    public function first_name()
    {
        return $this->get(__FUNCTION__);
    }

    public function last_name()
    {
        return $this->get(__FUNCTION__);
    }

    public function email()
    {
        return $this->get(__FUNCTION__);
    }

    public function nickname()
    {
        return $this->get(__FUNCTION__);
    }

    public function bdate()
    {
        return $this->get(__FUNCTION__);
    }

    public function sex()
    {
        return $this->get(__FUNCTION__);
    }

    public function phone()
    {
        return $this->get(__FUNCTION__);
    }

    public function photo()
    {
        return $this->get(__FUNCTION__);
    }

    public function photo_big()
    {
        return $this->get(__FUNCTION__);
    }

    public function city()
    {
        return $this->get(__FUNCTION__);
    }

    public function country()
    {
        return $this->get(__FUNCTION__);
    }

}
