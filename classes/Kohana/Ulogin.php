<?php defined('SYSPATH') OR die('No direct script access.');

class Kohana_Ulogin {
    
    protected $config = array(

        // Возможные значения: small, panel, window
        'type'              => 'panel',
        
        // На какой адрес придёт POST-запрос от uLogin (адрес до контроллера/экшна, вызывающего метод Ulogin::login())
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
        'verify_email'      =>  FALSE,

        // Имя коллбэк-функции для авторизации по токену (по умолчанию используется встроенная)
        'javascript_callback'   =>  NULL,
    );
    
    protected static $_used_ids = array();

    protected $_widget_id;
    
    public static function factory(array $config = array())
    {
        return new Ulogin($config);
    }
    
    public function __construct(array $config = array())
    {
        $this->config = array_merge($this->config, Kohana::$config->load('ulogin')->as_array(), $config);

        $current_url = Request::initial()->url(true);

        // Устанавливаем дефолтный адрес для авторизации
        if ( $this->get_redirect_uri() === NULL )
        {
            $this->set_redirect_uri($current_url);
        }
    }

    /**
     * Возвращает строку с HTML/CSS/JS кодом виджета
     * @return string
     */
    public function render()
    {
        $auth_callback = $this->get_javascript_callback();

        // Есди указана функция-коллбэк
        if ( $auth_callback )
        {
            // Убираем адрес для перенаправления (согласно инструкции http://ulogin.ru/help.php#faq)
            $this->set_redirect_uri(NULL);
        }

        $params =
            'display='.$this->config['type'].
            ( $this->config['verify_email'] ? '&verify=1' : '' ).
            '&fields='.implode(',', array_merge($this->config['username'], $this->config['fields'])).
            '&providers='.implode(',', $this->config['providers']).
            '&hidden='.implode(',', $this->config['hidden']).
            '&redirect_uri='.$this->get_redirect_uri().
            ( $auth_callback ? '&callback='.$auth_callback : '' ).
            '&optional='.implode(',', $this->config['optional']);
        
        $view = $this->view_factory('ulogin/ulogin');

        $view_data = array(
            'cfg'               =>  $this->config,
            'uniq_id'           =>  $this->get_widget_id(),
            'params'            =>  $params,
            'redirect_uri'      =>  $this->get_redirect_uri(),
        );

        $view->set($view_data);

        return $view->render();
    }

    protected function view_factory($file)
    {
        return View::factory($file);
    }

    public function get_widget_id()
    {
        if ( ! $this->_widget_id )
        {
            $this->_widget_id = $this->generate_unique_id();
        }

        return $this->_widget_id;
    }

    /**
     * Генерирует уникальный идентификатор виджета в пределах текущего HTTP-запроса
     * @return string
     */
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
     * Метод для авторизации по токену, выданному сервисом Ulogin
     * Этот метод должен быть вызван из экшна, на который указывает параметр *redirect_uri*
     * @throws Ulogin_Exception
     */
    public function login()
    {
        if ( ! $this->mode() )
            throw new Ulogin_Exception('Empty token.');

        $token = $_POST['token'];

        // Получаем данные пользователя по токену
        $user_info = $this->request_user_info($token);

        // Получаем текущего пользователя, если таковой авторизован
        $user_orm = $this->get_user();

        // Ищем ulogin по identity
        $model = $this->find_ulogin($user_info);

        // Пользователь входил раньше через эту соцсеть?
        if ( $model->loaded() )
        {
            // Сейчас ни один пользователь не авторизован?
            if ( ! $user_orm )
            {
                // Тогда входим под пользовтелем, привязанным к ulogin
                $this->force_login($model->get_user());
            }
        }
        // Новая соцсеть
        else
        {
            // Если пользователь авторизован
            if ( $user_orm )
            {
                // Добавляем ему ulogin с текущей соцсетью
                $this->create_ulogin($model, $user_orm, $user_info);
            }
            else
            {
                // Иначе создаём нового пользователя на основе полученных данных
                $user_orm = $this->new_user_factory($user_info);

                // Добавляем ему новый ulogin
                $this->create_ulogin($model, $user_orm, $user_info);

                // И авторизуем
                $this->force_login($user_orm);
            }
        }
    }

    /**
     * Ищет ulogin в системе
     * @param Ulogin_Request $user_info
     * @return Model_Ulogin
     */
    protected function find_ulogin(Ulogin_Request $user_info)
    {
        return ORM::factory('Ulogin', array('identity' => $user_info->identity()));
    }

    public function mode()
    {
        return !empty($_POST['token']);
    }

    /**
     * Подготавливает массив данных для создания нового пользователя
     * @param Ulogin_Request $user_info
     * @return array
     */
    protected function prepare_new_user_data(Ulogin_Request $user_info)
    {
        $data = $this->prepare_custom_fields($user_info);

        $data['username'] = $this->prepare_username($user_info);
        $data['password'] = $this->generate_password();

        return $data;
    }

    /**
     * Подготавливает имя пользователя
     * @param Ulogin_Request $user_info
     * @return string
     * @throws Kohana_Exception
     */
    protected function prepare_username(Ulogin_Request $user_info)
    {
        $username = '';

        $user_info_array = $user_info->as_array();

        foreach ( $this->config['username'] as $part_of_name )
        {
            $username .= (empty($user_info_array[$part_of_name]) ? '' : (' '.$user_info_array[$part_of_name]));
        }

        $username = trim($username);

        if ( ! $username )
            throw new Kohana_Exception('Username fields are not set in config/ulogin.php');

        return $username;
    }

    /**
     * Генерирует пароль для нового пользователя
     * @return string
     */
    protected function generate_password()
    {
        return md5('ulogin_autogenerated_password'.microtime(TRUE));
    }

    /**
     * Подготавливает все остальные поля для создания нового пользователя
     * @param Ulogin_Request $user_info
     * @return array
     */
    protected function prepare_custom_fields(Ulogin_Request $user_info)
    {
        $data = array();

        $cfg_fields = array_merge($this->config['fields'], $this->config['optional']);

        $user_info_array = $user_info->as_array();

        foreach ( $cfg_fields as $field )
        {
            if ( ! empty($user_info_array[$field]) )
            {
                $data[$field] = $user_info_array[$field];
            }
        }

        return $data;
    }

    /**
     * Создаёт новый ulogin
     * @param Model_Ulogin $ulogin
     * @param $user_orm
     * @param Ulogin_Request $user_info
     * @return ORM
     */
    protected function create_ulogin(Model_Ulogin $ulogin, $user_orm, Ulogin_Request $user_info)
    {
        return $ulogin
            ->set_user($user_orm)
            ->values($user_info->as_array(), array('identity', 'network'))
            ->create();
    }

    /**
     * Создаёт нового пользователя, опираясь на данные, полученные от сервиса Ulogin
     * Метод стоит переопределить, если требуется другая логика создания нового пользователя
     * @param Ulogin_Request $user_info
     * @return ORM
     * @throws Ulogin_Exception
     */
    protected function new_user_factory(Ulogin_Request $user_info)
    {
        $email = $user_info->email();

        if ( ! $email )
            throw new Ulogin_Exception('Can not create user with empty email');

        // Проверим нет ли такого пользователя уже в системе
        $user_orm = ORM::factory('User', array('email' => $email));

        // Пользователя с таким email в системе нет
        if ( ! $user_orm->loaded() )
        {
            // Можно создавать нового
            $data = $this->prepare_new_user_data($user_info);
            $new_user = $this->create_new_user($data);
            $this->process_new_user($new_user, $user_info);
            return $new_user;
        }
        // Пользователь есть - возможный дубликат - а у нового подтверждённый email?
        else if ( $this->config['verify_email'] OR $user_info->verified_email() )
        {
            // Тогда используем найденного
            return $user_orm;
        }

        // Есть пользователь с таким же email, но у пользователя текущей соцсети не подтверждён email
        // Соответственно, это может быть попытка взлома
        // TODO подумать, как разрулить эту ситуацию
        throw new Ulogin_Exception('There is another user with verified email :email', array(':email' => $email));
    }

    protected function process_new_user(Model_User $user, Ulogin_Request $user_info)
    {
        // Empty by default
    }

    /**
     * Создаёт нового пользователя на основе подготовленного массива данных
     * @param array $user_data
     * @return Model_User
     */
    protected function create_new_user(array $user_data)
    {
        return ORM::factory('User')
            ->values($user_data)
            ->create()
            ->add('roles', ORM::factory('Role', array('name' => 'login')));
    }

    /**
     * Получает текущего авторизованного пользователя системы
     * @return mixed
     */
    protected function get_user()
    {
        return Auth::instance()->get_user();
    }

    /**
     * Авторизует пользователя
     * @param $user
     * @return bool
     */
    protected function force_login($user)
    {
        return Auth::instance()->force_login($user);
    }

    /**
     * Запрашивает у сервиса Ulogin информацию о пользователе по токену
     * @param $token
     * @return Ulogin_Request
     */
    public function request_user_info($token)
    {
        return Ulogin_Request::factory($token);
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

    public function get_javascript_callback()
    {
        return $this->config['javascript_callback'];
    }

    public function set_javascript_callback($value)
    {
        $this->config['javascript_callback'] = $value;
        return $this;
    }

}
