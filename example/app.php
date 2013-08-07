<?php

class homepage extends RouterGroup {

    protected function firstGroup( $app, $group ) {
        $view = $app->view;

        $view->appendData(array(
            'data' => "I'm group 1"
        ));
    }

    protected function secondGroup( $app, $group ) {
        $view = $app->view;

        $data = $view->getData('data');

        $view->appendData(array(
            'data' => $data."\nI'm group 2"
        ));
    }

    protected function userGroup( $app, $group ) {
    }

    protected function oauthGroup( $app, $group ) {
    }

    protected function authenticateView( $provider, $app, $group ) {

        $app->render($group['view'], $group['data']);
    }

    protected function testView( $app, $group ) {
        $view = $app->view;

        $data = $view->getData('data');

        $view->appendData(array(
            'data' => $data."\nI'm view"
        ));

        $app->render($group['view'], $group['data']);
    }
}
