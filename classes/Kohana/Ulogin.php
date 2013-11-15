<?php defined('SYSPATH') or die('No direct script access.');

class Kohana_Ulogin {
    
    protected $config = array(
        // Возможные значения: small, panel, window
        'type'             => 'panel',
        
        // на какой адрес придёт POST-запрос от uLogin
        'redirect_uri'     => NULL,
        
        // Сервисы, выводимые сразу
        'providers'        => array(
            'vkontakte',
            'facebook',
            'twitter',
            'google',
        ),
        
        // Выводимые при наведении
        'hidden'         => array(
            'odnoklassniki',
            'mailru',
            'livejournal',
            'openid'
        ),
        
        // Эти поля используются для значения поля username в таблице users
        'username'         => array (
            'first_name',
        ),
        
        // Обязательные поля
        'fields'         => array(
            'email',
        ),
        
        // Необязательные поля
        'optional'        => array(),
    );
    
    protected static $_used_ids = array();
    
    public static function factory(array $config = array())
    {
        return new Ulogin($config);
    }
    
    public function __construct(array $config = array())
    {
        $this->config = array_merge($this->config, Kohana::$config->load('ulogin')->as_array(), $config);
        
        if ( $this->config['redirect_uri'] === NULL )
        {
            $this->config['redirect_uri'] = Request::initial()->url(true);
        }
    }
    
    public function render()
    {    
        $params =     
            'display='.$this->config['type'].
            '&fields='.implode(',', array_merge($this->config['username'], $this->config['fields'])).
            '&providers='.implode(',', $this->config['providers']).
            '&hidden='.implode(',', $this->config['hidden']).
            '&redirect_uri='.$this->config['redirect_uri'].
            '&optional='.implode(',', $this->config['optional']);
        
        $view = View::factory('ulogin/ulogin')
                    ->set('cfg', $this->config)
                    ->set('params', $params);

        $view->set('uniq_id', $this->generate_unique_id());
        
        return $view->render();
    }

    protected function generate_unique_id()
    {
        do
        {
            $unique_id = "uLogin_".rand();
        }
        while ( in_array($unique_id, static::$_used_ids) );

        static::$_used_ids[] = $unique_id;

        return $unique_id;
    }
    
    public function __toString()
    {
        try
        {
            return $this->render();
        }
        catch(Exception $e)
        {
            Kohana_Exception::handler($e);
            return '';
        }
    }
    
    public function login()
    {
        if ( empty($_POST['token']) )
            throw new Kohana_Exception('Empty token.');

        $token = $_POST['token'];
        $user_info = $this->request_user_info($token);

        $ulogin = ORM::factory('Ulogin', array('identity' => $user_info['identity']));
        
        if ( ! $ulogin->loaded() )
        {
            if ( ($user_orm = Auth::instance()->get_user()) )
            {
                $this->create_ulogin($ulogin, $user_info);
            }
            else
            {
                $data = $this->prepare_new_user_data($user_info);

                $user_orm = $this->create_new_user($data);
                
                $user_info['user_id'] = $user_orm->pk();
                
                $this->create_ulogin($ulogin, $user_info);

                $this->force_login($user_orm);
            }
        }
        else
        {
            $this->force_login($ulogin->user);
        }
    }
    
    public function mode()
    {
        return !empty($_POST['token']);
    }

    protected function prepare_new_user_data($user_info)
    {
        $data = array(
            'username' => $this->prepare_username($user_info),
            'password' => $this->generate_password()
        );

        $cfg_fields = array_merge($this->config['fields'], $this->config['optional']);

        foreach ( $cfg_fields as $field )
        {
            if ( ! empty($user_info[$field]) )
            {
                $data[$field] = $user_info[$field];
            }
        }

        return $data;
    }

    protected function prepare_username($user_info)
    {
        $username = '';

        foreach ( $this->config['username'] as $part_of_name )
        {
            $username .= (empty($user_info[$part_of_name]) ? '' : (' '.$user_info[$part_of_name]));
        }

        $username = trim($username);

        if ( ! $username )
            throw new Kohana_Exception('Username fields are not set in config/ulogin.php');

        return $username;
    }

    protected function generate_password()
    {
        return md5('ulogin_autogenerated_password'.microtime(TRUE));
    }

    protected function create_ulogin(Model_Ulogin $ulogin, $post)
    {
        return $ulogin->values($post, array(
            'user_id',
            'identity',
            'network',
        ))->create();
    }

    protected function create_new_user(array $data)
    {
        $orm_user = ORM::factory('User')->values($data)->create();
        $orm_user->add('roles', ORM::factory('Role', array('name' => 'login')));
        return $orm_user;
    }

    protected function force_login($user)
    {
        Auth::instance()->force_login($user);
    }

    protected function request_user_info($token)
    {
        $s = Request::factory('http://ulogin.ru/token.php?token='.$token.'&host='.$this->get_domain())->execute()->body();
        return json_decode($s, true);
    }

    protected function get_domain()
    {
        if ( !($domain = parse_url(URL::base(), PHP_URL_HOST)) )
        {
            $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
        }

        return $domain;
    }

}