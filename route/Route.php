<?php
/**
 * Created by PhpStorm.
 * User: laijingming
 * Date: 2023/2/22
 * Time: 14:03
 */

namespace ajing;

/**
 * @method static Route get(string $route, Callable $callback)
 * @method static Route post(string $route, Callable $callback)
 * @method static Route put(string $route, Callable $callback)
 * @method static Route delete(string $route, Callable $callback)
 * @method static Route options(string $route, Callable $callback)
 * @method static Route head(string $route, Callable $callback)
 */
class Route
{
    public static $namespace = '';
    public static $routes = array();//存储注册的路由
    public static $patterns = array(
        ':any' => '[^/]+',
        ':num' => '[0-9]+',
        ':all' => '.*'
    );
    public static $matchRoutes = array();

    /**
     * 注册路由
     * @param $method string 路由类型
     * @param $arguments
     */
    public static function __callStatic($method, $arguments)
    {
        self::$routes['/' . ltrim($arguments[0], '/')] = [
            //允许请求的方法=>匿名函数或者要执行的控制器方法
            strtoupper($method) => $arguments[1],
        ];
    }

    /**
     * 启动路由
     */
    public function run()
    {
//        print_r(self::$routes);
        self::$matchRoutes = array();
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];
        $this->matchWithoutRegex($uri, $method);
        if (!isset(self::$matchRoutes[0])) {
            $this->matchRegex($uri, $method);
        }
        if (!isset(self::$matchRoutes[0])) {
            header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found");
            echo '404';
            return;
        }
        if (!$this->callObject(self::$matchRoutes[1], self::$matchRoutes[2])) {
            $this->callControllerMethod(self::$matchRoutes[1], self::$matchRoutes[2]);
        }
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
        $controllerName = self::$namespace . str_replace('/', '\\', $controllerName);
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
     */
    public function matchWithoutRegex($uri, $method)
    {
        $route = self::$routes[$uri];
        //未找到注册uri或者未匹配到对应方法路由配置
        if ($route && $callback = $this->methodToCallBack($route, $method)) {
            self::$matchRoutes = [
                $route,
                $callback,
                []
            ];
        }
    }

    /**
     * 正则路由匹配
     * @param $uri
     * @param $method
     */
    public function matchRegex($uri, $method)
    {
        $searches = array_keys(static::$patterns);
        $replaces = array_values(static::$patterns);
        foreach (self::$routes as $route => $methodCallBack) {
            if (strpos($route, ':') !== false) {
                $route = str_replace($searches, $replaces, $route);
            }
            if (preg_match('#^' . $route . '$#', $uri, $matched)) {
                if (!$callback = $this->methodToCallBack($methodCallBack, $method)) {
                    continue;
                }
                //移出开头数组
                array_shift($matched);
                self::$matchRoutes = [
                    $route,
                    $callback,
                    $matched
                ];
                break;
            }
        }
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

    public static function load($file)
    {
        $router = new static;
        require $file;
        // 注意这里，静态方法中没有 $this 变量，不能 return $this;
        return $router;
    }

    /**
     * @param $namespace
     * @return $this
     */
    public function namespace($namespace)
    {
        self::$namespace = $namespace;
        return $this;
    }
}
