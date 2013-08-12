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
        $params = 0;

        if (preg_match('#^'.$patternAsRegex.'$#', $app->request->getResourceUri())) {
            if (preg_match_all('#:[\w]+\+?#', $requestUri.$view['path'], $m)) {
                $params = count($m[0]);
                unset($m);
            }
            $process = true;
        }

        $groups = array('group' => $arguments, 'view' => $view, 'flag' => true, 'process' => $process, 'params_count' => $params);
 
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
            throw new Exception("Group does not exists.", 1);
        }

        if ($groups['flag']) {
            if ($groups['process']) call_user_func_array(array($this, $group['name'].'Group'), array($app, $group));

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
                throw new Exception("Group method `".$group['name']."Group` does not exists.", 1);
            }
            if ($groups['process']) call_user_func_array(array($this, $group['name'].'Group'), array($app, $group));

            call_user_func_array(array( $app, 'group' ), $_params);
        };
    }

    private function runView( $group, $groups, $app ) {
        $self = $this;

        if (!method_exists($this, $group['name'].'View')) {
            throw new Exception("Finial View is missing.", 1);
        }

        $_params = array($group['path']);
        $_params = $this->mergeMiddleware( $_params, $group );

        array_push($_params, 
            function () use ( $app, $self, $group, $groups ) {
                $arguments = func_get_args();
                if (count($arguments) < $groups['params_count']) {
                    for($i = count($arguments); $i< $groups['params_count']; $i++) {
                        array_push($arguments, null);
                    }
                }
                array_push($arguments, $app);
                array_push($arguments, $group);

                if (!method_exists($self, $group['name'].'View')) {
                    throw new Exception("View method `".$group['name']."View` does not exists.", 1);
                }
                if ($groups['process']) call_user_func_array(array($self, $group['name'].'View'), $arguments);
            }
        );

        return function() use ( $app, $group, $self, $_params ) {
            $return = call_user_func_array(array($app, $group['method']), $_params);
            if ($return) {
                if (isset($group['conditions'])) {
                    if (is_array($group['conditions']) && count($group['conditions']) > 0) {
                        $return->conditions($group['conditions']);
                    } else {
                        throw new Exception("Conditions must be an array.", 1);
                    }
                }
                if (isset($group['via'])) {
                   if (is_array($group['via']) && count($group['via'] > 0)) {
                        call_user_func_array(array($return, 'via'), array_map('strtoupper', $group['via']));
                    } else {
                        throw new Exception("Via must be an array.", 1);
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
