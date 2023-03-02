<?php
/**
 * Created by PhpStorm.
 * User: laijingming
 * Date: 2023/2/22
 * Time: 14:03
 */

namespace ajing\route;

/**
 * @method RouteRegister get(string $route, Callable $callback)
 * @method RouteRegister post(string $route, Callable $callback)
 * @method RouteRegister put(string $route, Callable $callback)
 * @method RouteRegister delete(string $route, Callable $callback)
 * @method RouteRegister options(string $route, Callable $callback)
 * @method RouteRegister head(string $route, Callable $callback)
 * @method RouteRegister group(array|\Closure|string $attributes, \Closure|string $routes)
 * @method RouteRegister getPathList()
 * @method RouteRegister getNameList()
 * @method RouteRegister name(string $value)
 * @see RouteRegister
 */
class Route
{
    public static $routes = array();//存储注册的路由
    public static $patterns = array(
        ':any' => '[^/]+',
        ':num' => '[0-9]+',
        ':all' => '.*'
    );
    public static $matchRoutes = array();

    /**
     * 他将属性传递给路由器
     * @var array
     */
    protected $attributes = [];
    /**
     * 可以通过这个类设置的属性
     * @var array
     */
    protected $allowedAttributes = [
        'as', 'domain', 'middleware', 'namespace', 'prefix', 'where', 'middlewareGroups', 'routeMiddleware'
    ];

    /**
     * 动态传递给路由器的方法
     * @var array
     */
    protected $passthru = [
        'get', 'post', 'put', 'patch', 'delete', 'options', 'any','group'
    ];


//    /**
//     * @param $method
//     * @param $arguments
//     */
//    public static function __callStatic($method, $arguments)
//    {
//        return self::getRouteRegister()->$method(...$arguments);
//    }

    /**
     * @param $method
     * @param $parameters
     */
    public function __call($method, $parameters)
    {
        if (in_array($method, $this->passthru)) {
            return self::getRouteRegister()->$method(...$parameters);
        }

        if (in_array($method, $this->allowedAttributes)) {
            if ($method == 'middleware') {
                return $this->attribute($method, is_array($parameters[0]) ? $parameters[0] : $parameters);
            }

            return $this->attribute($method, $parameters[0]);
        }

        throw new \Exception(sprintf(
            'Method %s::%s does not exist.', static::class, $method
        ));
    }

    /**
     * 创建具有共享属性的路由组。
     * @param $callback
     * @return $this
     */
    public function groupFirst($callback)
    {
        $middleware = $this->getAttribute('middleware', []);
        $middlewareGroups = $this->getAttribute('middlewareGroups', []);
        $pipeline = new Pipeline();
        if (!empty($middleware) && isset($middlewareGroups[$middleware[0]])) {
            $pipeline->through($middlewareGroups[$middleware[0]]);
        }
        $pipeline->then(function () use ($callback) {
            self::getRouteRegister()->group($this->attributes, $callback);
        });
        return $this;
    }


    /**
     * 设置给定属性的值
     * @param  string $key
     * @param  mixed $value
     * @return $this
     *
     * @throws \Exception
     */
    public function attribute($key, $value)
    {
        if (!in_array($key, $this->allowedAttributes)) {
            throw new \Exception("Attribute [{$key}] does not exist.");
        }

        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * 获取属性
     * @param $key
     * @param $default
     * @return mixed
     */
    public function getAttribute($key, $default = '')
    {
        if (!isset($this->attributes[$key])) {
            return $default;
        }
        return $this->attributes[$key];
    }

    /**
     * 启动路由
     */
    public function run(Request $request)
    {
        $uri = $request->path();
        $method = $request->getMethod();
        $routes = self::getRouteRegister()->getPathList();
        $matchRoutes = array();
        if ((!$this->matchWithoutRegex($uri, $method, $routes, $matchRoutes) &&
                !$this->matchRegex($uri, $method, $routes, $matchRoutes)) ||
            empty($matchRoutes)) {

            header($request->server->get('SERVER_PROTOCOL') . " 404 Not Found");
            echo '404';
            return;
        }
        $pipeline = new Pipeline();
        $routeMiddleware = $this->getAttribute('routeMiddleware', []);
        if (!empty($routeMiddleware) && isset($matchRoutes['middleware'])) {
            array_pop($matchRoutes['middleware']);
            $pipeline->through($this->getRouteMiddleware($matchRoutes['middleware'], $routeMiddleware));
        }
        $pipeline->then(function () use ($matchRoutes) {
            if (!$this->callObject($matchRoutes['action'], $matchRoutes['query'])) {
                $this->callControllerMethod($matchRoutes['action'], $matchRoutes['query']);
            }
        });
    }

    protected function getRouteMiddleware(array $middleware, array $routeMiddleware)
    {
        $middlewareArr = [];
        foreach ($middleware as $name) {
            if (isset($routeMiddleware[$name])) {
                array_unshift($middlewareArr, $routeMiddleware[$name]);
            }
        }
        return $middlewareArr;
    }

    /**
     * 调用控制器方法
     * @param string $callback
     * @param array $params
     */
    protected function callControllerMethod($callback, $params = null)
    {
        // 抓取控制器名称和方法调用
        $segments = explode('@', $callback);
        $controllerName = $segments[0];
        $methodName = isset($segments[1]) ? $segments[1] : 'index';
        $controller = new $controllerName();
        // 修复多参数
        if (!method_exists($controller, $methodName)) {
            echo "controller and action not found";
        } else {
            call_user_func_array(array($controller, $methodName), $params);
        }
    }

    /**
     * 是函数则调用
     * @param $callback
     * @param null $params
     * @return bool
     */
    protected function callObject($callback, $params = [])
    {
        if (!is_object($callback)) {
            return false;
        }
        call_user_func_array($callback, $params);
        return true;
    }

    /**
     * 非正则路由匹配
     * @param $uri
     * @param $method
     * @param $routes
     * @param $matchRoutes
     * @return bool
     */
    public function matchWithoutRegex($uri, $method, $routes, &$matchRoutes)
    {
        $route = $routes[$uri] ?? [];
        //未找到注册uri或者未匹配到对应方法路由配置
        if ($route && $callback = $this->methodToCallBack($route, $method)) {
            $callback['query'] = [];
            $matchRoutes = $callback;
            return true;
        }
        return false;
    }

    /**
     * 正则路由匹配
     * @param $uri
     * @param $method
     * @param $routes
     * @param $matchRoutes
     * @return bool
     */
    public function matchRegex($uri, $method, $routes, &$matchRoutes)
    {
        $searches = array_keys(static::$patterns);
        $replaces = array_values(static::$patterns);
        foreach ($routes as $registerUri => $methodCallBack) {
            if (strpos($registerUri, ':') !== false) {
                $registerUri = str_replace($searches, $replaces, $registerUri);
            }
            if (preg_match('#^' . $registerUri . '$#', $uri, $matched)) {
                if (!$callback = $this->methodToCallBack($methodCallBack, $method)) {
                    continue;
                }
                print_r($callback);
                //移出开头数组
                array_shift($matched);
                $callback['query'] = $matched;
                $matchRoutes = $callback;
                return true;
            }
        }
        return false;
    }

    /**
     * 通过请求的方法=>匿名函数或者要执行的控制器方法
     * @param $route
     * @param $method
     * @return null
     */
    public function methodToCallBack($methodCallBack, $method)
    {
        //是否存请求方法对应注册路由
        $callback = isset($methodCallBack[$method]) ? $methodCallBack[$method] : null;
        //不存在路由则判断是否注册过ANY路由
        return !$callback && isset($methodCallBack['ANY']) ? $methodCallBack['ANY'] : $callback;
    }

    /**
     * 返回RouteRegister实例
     * @return RouteRegister
     */
    public static function getRouteRegister()
    {
        return RouteRegister::getInstance();
    }
}
