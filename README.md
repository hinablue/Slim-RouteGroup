Slim-RouteGroup
===============

###Slim 2.3.0

[Slim Framework News](http://www.slimframework.com/news/version-230)

In the 2.3.0, Slim can group your own routes, but the group callback function more like the global middleware function. For example,

``` php
$app->group('/first', function() use ( $app ) {

    echo "first group here.";
    $app->group('/second', function() use ( $app ) {
        
        echo "second group here.";
        $app->get('/last', function() use ( $app ) {

            echo "last view here.";
            $app->render('index.phtml');
        });
    });
});
```

When you open the link `/first/second/last` in the browser which echo all the message that you `echo`. BUT, in the other routes, like `/other/route`, will also show the message that you `echo` in the group callback function.

So, I said the group callback function more like the global middleware function here.

###RouteGroup

I do some work that for group route, you can use this class to make the group callback function ONLY in the matched route path. For example,

``` php
class myRoute extends RouteGroup {

    protected function firstGroup( $app, $group ) {
        echo "first group here.";
    }

    protected function secondGroup( $app, $group ) {
        echo "second group here.";
    }

    protected function lastView( $app, $group ) {
        echo "last view here.";

        $app->render($group['view'], $group['data']);
    }
}

$myroute = new myRoute();

$app->group('/first', $myroute->run(
    array(
        'name' => 'first',
        'path' => '/first',
        'middlewares' => array()
    ),
    array(
        'name' => 'second',
        'path' => '/second',
        'middlewares' => array()
    ),
    array(
        'name' => 'last',
        'path' => '/last',
        'middlewares' => array(),
        'conditions' => array(),
        'method' => 'get',
        'data' => array()
    )
));
```

So, if you open the `/other/route` you will see no message output.

See the example for more.

License
=======

Licensed under the MIT License.

Authors
=======

Copyright(c) 2013 Hina Chen <hinablue@gmail.com>
