uLogin - виджет авторизации через социальные сети
-------------------------------------------------

Donate link: http://ulogin.ru/

Tags: ulogin, login, social, authorization

Stable tag: 1.7

License: GPL3

Форма авторизации uLogin через социальные сети. Улучшенный аналог loginza.


uLogin — это инструмент, который позволяет пользователям получить единый доступ к различным Интернет-сервисам без необходимости повторной регистрации,
а владельцам сайтов — получить дополнительный приток клиентов из социальных сетей и популярных порталов (Google, Яндекс, Mail.ru, ВКонтакте, Facebook и др.)

Установка
=========

1. Создать таблицу ulogins:
```sql
CREATE TABLE `ulogins` (
		`id` INT(10) NOT NULL AUTO_INCREMENT,
		`user_id` INT(11) NOT NULL,
		`network` VARCHAR(255) NOT NULL,
		`identity` VARCHAR(255) NOT NULL,
		PRIMARY KEY (`id`),
		UNIQUE INDEX `identity` (`identity`)
)
```

2. Добавить `'ulogins' => array()`, в `protected $_has_many` у класса `Model_User`

3. Скопировать файл *config/ulogin.php* в */application/config* и настроить модуль по своему вкусу

4. Разместить код показа виджета и код авторизации в соответствующих местах, например, так:

```php

class Controller_Ulogin extends Controller {

	public function action_show()
	{
		$ulogin = $this->ulogin_factory();
		
		// При преобразовании объекта в строку на выходе получим рендер виджета
		$this->response->body($ulogin);
	}
	
	public function action_login()
	{
		$ulogin = $this->ulogin_factory();

		try
		{
			$ulogin->login();
		}
		catch ( Ulogin_Exception $e )
		{
			$this->response->body($e->getMessage());
		}
	}

	protected function ulogin_factory()
	{
		$ulogin = Ulogin::factory();
		
		// Сообщаем, где находится экшн, осуществляющий авторизацию для сервиса ulogin
		$redirect_url = $this->request->route()->uri(array('action' => 'login'));
		$ulogin->set_redirect_url($redirect_url);
	}
}

```

ЧаВо
====

* Нужно ли где-то регистрироваться, чтобы плагин заработал?
    Нет, плагин заработает сразу после установки!

Более подробную информацию ищите в [справке на официальном сайте](http://ulogin.ru/help.php#faq)
