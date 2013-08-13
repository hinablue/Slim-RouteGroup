<?php 

class RouterGroup implements \ArrayAccess, \IteratorAggregate {
    /**
     * Run the route group and return the function for Slim.
     */
    public function run() {
        $arguments = func_get_args();

        if (count($arguments) === 0) {
            throw new Exception("Initial configuration is empty.", 0);
        }
        if (count($arguments) === 1) {
            throw new Exception("Last view configuration is missing.", 0);
        }

        $view = array_pop($arguments);
        $requestUri = '';

        foreach($arguments as $group) {
            if (!isset($group['path']) || empty($group['path'])) {
                throw new Exception($group['name']." path is empty.");
            }
            $requestUri .= $group['path'];
        }
        $patternAsRegex = preg_replace_callback('#:(?P<path>[\w]+)\+?#', function($m) {
            if (isset($view['conditions'][$m['path']])) {
                return '(?P<'.$m['path'].'>'.$view['conditions'][$m['path']].')';
            }
            if (substr($m[0], -1) === '+') return '(?P<'.$m['path'].'>.+)';
            return '(?P<'.$m['path'].'>[^/]+)';
        }, str_replace(')', ')?', $requestUri.$view['path']));

        if (substr($patternAsRegex, -1) === '/') $patternAsRegex .= '?';

        $app = \Slim\Slim::getInstance();
        $process = false;
        $params = array();

        if (preg_match('#^'.$patternAsRegex.'$#', $app->request->getResourceUri())) {
            if (preg_match_all('#:(?P<parameters>[\w]+)\+?#', $requestUri.$view['path'], $m)) {
                $params = $m['parameters'];
                unset($m);
            }
            $process = true;
        }

        $groups = array('group' => $arguments, 'view' => $view, 'flag' => true, 'process' => $process, 'params' => $params);
 
        return $this->runGroup( $groups, $app );

        unset($view);
        unset($requestUri);
        unset($process);
        unset($params);
        unset($app);
    }

    private function runGroup( $groups, $app ) {
        if (count($groups['group']) === 0) {
            return $this->runView( $groups['view'], $groups, $app );
        }

        $group = array_shift($groups['group']);
        if (!method_exists($this, $group['name'].'Group')) {
            throw new Exception("Group does not exists.", 0);
        }

        if ($groups['flag']) {
            if ($groups['process']) {
                $callable_params = array();
                $refl = new ReflectionMethod(get_class($this), $group['name'].'Group');
                foreach( $refl->getParameters() as $param ) {
                    $param = $param->getName();
                    if (isset($$param)) {
                        array_push($callable_params, $$param);
                    } else {
                        unset($refl);
                        unset($callable_params);
                        throw new Exception('$'.$param.' parameter does not exists.', 0);
                    }
                }
                unset($refl);

                call_user_func_array(array($this, $group['name'].'Group'), $callable_params);

                unset($callable_params);
            }

            $groups['flag'] = false;
            $group = array_shift($groups['group']);

            if ($group === NULL) return $this->runView( $groups['view'], $groups, $app );
        }

        $_params = array($group['path']);
        $_params = $this->mergeMiddleware( $_params, $group );
        array_push($_params, $this->runGroup($groups, $app));

        unset($requestUri);

        return function() use ( $app, $group, $_params, $groups ) {
            if (!method_exists($this, $group['name'].'Group')) {
                throw new Exception("Group method `".$group['name']."Group` does not exists.", 0);
            }

            $callable_params = array();
 
            if ($groups['process']) {
                $refl = new ReflectionMethod(get_class($this), $group['name'].'Group');
                foreach( $refl->getParameters() as $param ) {
                    $param = $param->getName();
                    if (isset($$param)) {
                        array_push($callable_params, $$param);
                    } else {
                        unset($refl);
                        unset($callable_params);
                        throw new Exception('$'.$param.' parameter does not exists.', 0);
                    }
                }
                unset($refl);

                call_user_func_array(array($this, $group['name'].'Group'), $callable_params);
            }

            call_user_func_array(array( $app, 'group' ), $_params);
        };
    }

    private function runView( $group, $groups, $app ) {
        $self = $this;

        if (!method_exists($this, $group['name'].'View')) {
            throw new Exception("Finial View is missing.", 0);
        }

        $_params = array($group['path']);
        $_params = $this->mergeMiddleware( $_params, $group );

        array_push($_params, 
            function () use ( $app, $self, $group, $groups ) {
                if (!method_exists($self, $group['name'].'View')) {
                    throw new Exception("View method `".$group['name']."View` does not exists.", 0);
                }

                if ($groups['process']) {
                    $callable_params = array();
                    $arguments = func_get_args();
                    $refl = new ReflectionMethod(get_class($self), $group['name'].'View');
                    foreach( $refl->getParameters() as $param ) {
                        $param = $param->getName();
                        if (isset($$param)) {
                            array_push($callable_params, $$param);
                        } else {
                            if (($key = array_search($param, $groups['params'])) >= 0) {
                                if (isset($arguments[$key]))  {
                                    array_push($callable_params, $arguments[$key]);
                                } else {
                                    array_push($callable_params, null);
                                }
                            } else {
                                throw new Exception('$'.$param.' parameter does not exists.', 0);
                            }
                        }
                    }
                    unset($refl);

                    call_user_func_array(array($self, $group['name'].'View'), $callable_params);

                    unset($callable_params);
                }
            }
        );

        return function() use ( $app, $group, $self, $_params ) {
            $return = call_user_func_array(array($app, $group['method']), $_params);
            if ($return) {
                if (isset($group['conditions'])) {
                    if (is_array($group['conditions']) && count($group['conditions']) > 0) {
                        $return->conditions($group['conditions']);
                    } else {
                        throw new Exception("Conditions must be an array.", 0);
                    }
                }
                if (isset($group['via'])) {
                   if (is_array($group['via']) && count($group['via'] > 0)) {
                        call_user_func_array(array($return, 'via'), array_map('strtoupper', $group['via']));
                    } else {
                        throw new Exception("Via must be an array.", 0);
                    }
                }
                if (isset($group['route_name']) && !empty($group['route_name'])) {
                    $return->name($group['route_name']);
                }
            }
        };
    }

    private function mergeMiddleware( array $params, array $group ) {
        if (isset($group['middlewares']) && is_array($group['middlewares']) && count($group['middlewares']) > 0) {
            foreach($group['middlewares'] as $middleware) {
                array_push($params, $middleware);
            }
        }

        return $params;
    }

    public function getCurrectUrl( $path = '/' ) {
        if (isset($_SERVER['HTTPS']) && 
            ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1) ||
             isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
            $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $protocol = 'https://';
        } else {
            $protocol = 'http://';
        }
        $port = isset($parts['port']) &&
            (($protocol === 'http://' && $parts['port'] !== 80) ||
            ($protocol === 'https://' && $parts['port'] !== 443))
            ? ':' . $parts['port'] : '';
        return $protocol . $_SERVER['HTTP_HOST'] . $port . $path;
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
