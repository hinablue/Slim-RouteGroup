<?php 

require 'app.php';
require 'route.php';

$app = new \Slim\Slim(array(
	'view' => new \Slim\Views\Twig()
));

$app->configureMode('development', function () use ( $app ) {

	$view = $app->view();
	$view->twigTemplateDirs = dirname(__FILE__).'/views';
	$view->parserOptions = array(
		'debug' => true
	);
})

require 'app.php';
require 'route.php';

$app->get('/', function() use ( $app ) {
	$app->render('index.phtml');
});

$app->run();