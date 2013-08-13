<?php

$indexRoute = new homepage();

$authenticate = function() {
    var_dump('There is the middleware.');
};

$app->group('/first', $indexRoute->run(
    array(
        'name' => 'first',
        'path' => '/first',
        'middlewares' => array($authenticate)
    ),
    array(
        'name' => 'second',
        'path' => '/second',
        'middlewares' => array()
    ),
    array(
        'name' => 'last',
        'path' => '/:name',
        'middlewares' => array($authenticate),
        'conditions' => array("name" => "(test|finial|gagawolala)"),
        'method' => 'get',
        'view' => 'index.phtml',
        'data' => array()
    )
));

$app->group('/user', $indexRoute->run(
    array(
        'name' => 'user',
        'path' => '/user',
        'middlewares' => array($authenticate)
    ),
    array(
        'name' => 'oauth',
        'path' => '/oauth',
        'middlewares' => array()
    ),
    array(
        'name' => 'authenticate',
        'path' => '/:provider',
        'middlewares' => array($authenticate),
        'conditions' => array("provider" => "(google|twitter|facebook)"),
        'method' => 'map',
        'via' => array('get','post'),
        'view' => 'index.phtml',
        'data' => array()
    )
));
