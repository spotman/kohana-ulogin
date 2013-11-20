uLogin - виджет авторизации через социальные сети
=================================================

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
2. Добавить `'ulogins' => array()`, в `protected $_has_many` у *Model_User*

ЧаВо
====

* Нужно ли где-то регистрироваться, чтобы плагин заработал?
    Нет, плагин заработает сразу после установки!

