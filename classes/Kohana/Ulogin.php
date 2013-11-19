<?php defined('SYSPATH') or die('No direct script access.');

class Kohana_Ulogin {
    
    protected $config = array(

        // Возможные значения: small, panel, window
        'type'              => 'panel',
        
        // на какой адрес придёт POST-запрос от uLogin
        'redirect_uri'      => NULL,
        
        // Сервисы, выводимые сразу
        'providers'         => array(
            'vkontakte',
            'facebook',
            'twitter',
            'google',
        ),
        
        // Выводимые при наведении
        'hidden'            => array(
            'odnoklassniki',
            'mailru',
            'livejournal',
            'openid'
        ),
        
        // Эти поля используются для значения поля username в таблице users
        'username'          => array (
            'first_name',
        ),
        
        // Обязательные поля
        'fields'            => array(
            'email',
        ),
        
        // Необязательные поля
        'optional'          => array(),

        // Требовать подтверждения адреса электронной почты?
        'verify_email'      =>  FALSE
    );
    
    protected static $_used_ids = array();
    
    public static function factory(array $config = array())
    {
        return new Ulogin($config);
    }
    
    public function __construct(array $config = array())
    {
        $this->config = array_merge($this->config, Kohana::$config->load('ulogin')->as_array(), $config);
        
        if ( $this->get_redirect_uri() === NULL )
        {
            $this->set_redirect_uri(Request::initial()->url(true));
        }
    }

    /**
     * Creates string representation of the widget
     * @return string
     */
    public function render()
    {    
        $params =     
            'display='.$this->config['type'].
            ( $this->config['verify_email'] ? '&verify=1' : '' ).
            '&fields='.implode(',', array_merge($this->config['username'], $this->config['fields'])).
            '&providers='.implode(',', $this->config['providers']).
            '&hidden='.implode(',', $this->config['hidden']).
            '&redirect_uri='.$this->get_redirect_uri().
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
        catch ( Exception $e )
        {
            Kohana_Exception::handler($e);
            return '';
        }
    }

    /**
     * Auth method
     * redirect_uri must route to action, calling this method
     * @throws Ulogin_Exception
     */
    public function login()
    {
        if ( ! $this->mode() )
            throw new Ulogin_Exception('Empty token.');

        $token = $_POST['token'];
        $user_info = $this->request_user_info($token);

        $model = $this->find_ulogin($user_info['identity']);

        $user_orm = $this->get_user();

        if ( $model->loaded() )
        {
            if ( ! $user_orm )
            {
                // Log in with user, linked to identity
                $this->force_login($model->get_user());
            }
        }
        else
        {
            // If user is authorized
            if ( $user_orm )
            {
                // Add another identity to current user
                $this->create_ulogin($model, $user_orm, $user_info);
            }
            else
            {
                // Create new user
                $user_orm = $this->create_new_user($user_info);

                // Create ulogin identity for new user
                $this->create_ulogin($model, $user_orm, $user_info);

                // Log in
                $this->force_login($user_orm);
            }
        }
    }

    /**
     * @param $identity
     * @return Model_Ulogin
     */
    protected function find_ulogin($identity)
    {
        return ORM::factory('Ulogin', array('identity' => $identity));
    }

    public function mode()
    {
        return !empty($_POST['token']);
    }

    protected function prepare_new_user_data($user_info)
    {
        $data = $this->prepare_custom_fields($user_info);

        $data['username'] = $this->prepare_username($user_info);
        $data['password'] = $this->generate_password();

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

    protected function prepare_custom_fields($user_info)
    {
        $data = array();

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

    protected function create_ulogin(Model_Ulogin $ulogin, $user, $data)
    {
        return $ulogin
            ->set_user($user)
            ->values($data, array('identity', 'network'))
            ->create();
    }

    protected function create_new_user($user_info)
    {
        $data = $this->prepare_new_user_data($user_info);

        $email = $user_info['email'];

        if ( ! $email )
            throw new Ulogin_Exception('Can not create user with empty email');

        $user_orm = ORM::factory('User', array('email' => $email));

        // If user with current email does not exists
        if ( ! $user_orm->loaded() )
        {
            $user_orm
                ->values($data)
                ->create()
                ->add('roles', ORM::factory('Role', array('name' => 'login')));
        }
        // User exists - possible duplicate/hijack - does email verified?
        else if ( ! $this->config['verify_email'] AND $user_info['verified_email'] != 1 )
        {
            // TODO deal with this situation
            throw new Ulogin_Exception('There is another user with verified email :email', array(':email' => $email));
        }

        return $user_orm;
    }

    protected function get_user()
    {
        return Auth::instance()->get_user();
    }

    protected function force_login($user)
    {
        return Auth::instance()->force_login($user);
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

    public function get_redirect_uri()
    {
        return $this->config['redirect_uri'];
    }

    public function set_redirect_uri($value)
    {
        $this->config['redirect_uri'] = $value;
        return $this;
    }


}