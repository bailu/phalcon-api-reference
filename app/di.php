<?php

use Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter;

$di = new Phalcon\DI\FactoryDefault;

$di->setShared('config', function() {
	return new Phalcon\Config(require APP_ROOT . '/config/config.php');
});

$di->setShared('router', function() {
	return require APP_ROOT . '/config/routes.php';
});

$di->setShared('url', function() use ($di) {
	$url = new \ApiDocs\Components\Url;
	$url->setBaseUri('/api/');
	return $url;
});

$di->setShared('tag', function() {
	return new \ApiDocs\Components\Tag;
});

$di->setShared('view', function() use ($di) {
	$view = new Phalcon\Mvc\View;
	$view->setViewsDir(APP_ROOT . '/views/');
	$view->registerEngines(array(
		'.phtml' => 'Phalcon\Mvc\View\Engine\Php',
		'.volt'  => function($view , $di) {
			$config = $di->get('config');
			$volt = new Phalcon\Mvc\View\Engine\Volt($view , $di);
			$volt->setOptions((array) $config->voltOptions);
			$volt->getCompiler()->addFunction(
				'tr',
				function ($key) {
					return "translate({$key})";
				}
			);
			return $volt;
		},
	));
	return $view;
});

$di->setShared('db', function() use ($di) {
	$connection = new DbAdapter((array) $di->get('config')->db);
	return $connection;
});

$di->setShared('dispatcher', function() use($di) {
	$eventsManager = $di->get('eventsManager');
	$eventsManager->attach('dispatch', function($event, $dispatcher, $exception) use($di) {
		if ($event->getType() == 'beforeException') {
			switch ($exception->getCode()) {
				case Phalcon\Dispatcher::EXCEPTION_HANDLER_NOT_FOUND:
				case Phalcon\Dispatcher::EXCEPTION_ACTION_NOT_FOUND:
					$dispatcher->forward($di->get('router')->getRouteByName('404')->getPaths());
					return false;
			}
		}
	});
	$dispatcher = new Phalcon\Mvc\Dispatcher;
	$dispatcher->setDefaultNamespace('\ApiDocs\Controllers');
	$dispatcher->setEventsManager($eventsManager);
	return $dispatcher;
});


$di->setShared('viewCache', function() use($di)
{
	$config = $di->get('config')->cache;
	$frontCache = new Phalcon\Cache\Frontend\Output(array(
		'lifetime' => $config->lifetime
	));
	$cache = new Phalcon\Cache\Backend\File($frontCache, array(
		'cacheDir' => $config->dir,
	));
	return $cache;
});