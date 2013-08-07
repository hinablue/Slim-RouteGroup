<?php 

class RouterGroup implements \ArrayAccess, \IteratorAggregate {
    /**
     * Run the route group and return the function for Slim.
     */
    public function run() {
        $arguments = func_get_args();

        if (count($arguments) === 0) {
            throw new Exception("Initial configuration is empty.", 1);
        }
        if (count($arguments) === 1) {
            throw new Exception("Last view configuration is missing.", 1);
        }

        $view = array_pop($arguments);
        $requestUri = '';
        foreach($arguments as $group) {
            if (!isset($group['path']) || empty($group['path'])) {
                throw new Exception($group['name']." path is empty.");
            }
            $requestUri .= $group['path'];
        }
        $view_path = explode('/', $view['path']);
        $preg_path = array();
        foreach($view_path as $path) {
            if (preg_match("/:(?P<path>[a-z0-9_]+)/i", $path, $m)) {
                array_push($preg_path, '(?P<'.$m['path'].'>[0-9a-z_\-]+)');
            }
        }
        unset($view_path);
        if (count($preg_path) > 0) {
            $requestUri .= '/'.implode('/', $preg_path);
        } else {
            $requestUri .= $view['path'];
        }
        $requestUri = str_replace('/', '\/', $requestUri);

        $app = \Slim\Slim::getInstance();
        $process = false;
        if (preg_match("/".$requestUri."/i", $app->request->getResourceUri())) {
            $process = true;
        }

        $groups = array('group' => $arguments, 'view' => $view, 'flag' => true, 'process' => $process);
 
        return $this->runGroup( $groups );

        unset($view);
        unset($requestUri);
        unset($process);
        unset($app);
    }

    private function runGroup( $groups ) {
        if (count($groups['group']) === 0) {
            return $this->runView( $groups['view'], $groups );
        }

        $group = array_shift($groups['group']);

        if (!method_exists($this, $group['name'].'Group')) {
            throw new Exception("Group does not exists.", 1);
        }

        $app = \Slim\Slim::getInstance();

        if ($groups['flag']) {
            if ($groups['process']) call_user_func_array(array($this, $group['name'].'Group'), array($app, $group));

            $groups['flag'] = false;
            $group = array_shift($groups['group']);
        }

        $_params = array($group['path']);
        $_params = $this->mergeMiddleware( $_params, $group );
        array_push($_params, $this->runGroup($groups));

        unset($requestUri);

        return function() use ( $app, $group, $_params, $groups ) {
            if ($groups['process']) call_user_func_array(array($this, $group['name'].'Group'), array($app, $group));

            call_user_func_array(array( $app, 'group' ), $_params);
        };
    }

    private function runView( $group, $groups ) {
        $self = $this;

        if (!method_exists($this, $group['name'].'View')) {
            throw new Exception("Finial View is missing.", 1);
        }

        $app = \Slim\Slim::getInstance();

        $_params = array($group['path']);
        $_params = $this->mergeMiddleware( $_params, $group );

        array_push($_params, 
            function () use ( $app, $self, $group, $groups ) {
                $arguments = func_get_args();
                array_push($arguments, $app);
                array_push($arguments, $group);

                if ($groups['process']) call_user_func_array(array($self, $group['name'].'View'), $arguments);

                $app->render($group['view'], $group['data']);
            }
        );

        return function() use ( $app, $group, $self, $_params ) {
            $return = call_user_func_array(array($app, $group['method']), $_params);
            if ($return) {
                if (is_array($group['conditions']) && count($group['conditions']) > 0) {
                    $return->conditions($group['conditions']);
                } else {
                    throw new Exception("Conditions must be an array.", 1);
                }
                if (is_array($group['via']) && count($group['via'] > 0)) {
                    call_user_func_array(array($return, 'via'), array_map('strtoupper', $group['via']));
                } else {
                    throw new Exception("Via must be an array.", 1);
                }
                if (!empty($group['route_name'])) {
                    $return->name($group['route_name']);
                }
            }
        };
    }

    private function mergeMiddleware( array $params, array $group ) {
        if (is_array($group['middlewares']) && count($group['middlewares']) > 0) {
            foreach($group['middlewares'] as $middleware) {
                array_push($params, $middleware);
            }
        }

        return $params;
    }

    public function offsetExists($offset) {
        return isset($this->groups[$offset]);
    }

    public function offsetGet($offset) {
        return $this->groups[$offset];
    }

    public function offsetSet($offset, $value) {
        $this->groups[$offset] = $vaule;
    }

    public function offsetUnset($offset) {
        unset($this->groups[$offset]);
    }

    public function getIterator() {
        return new \ArrayIterator($this->groups);
    }
}
