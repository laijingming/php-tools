<?php
/**
 * Created by PhpStorm.
 * User: laijingming
 * Date: 2023/2/27
 * Time: 15:45
 */

namespace ajing\route;

/**
 * @method RouteRegister  get(string $route, Callable $callback)
 * @method RouteRegister  post(string $route, Callable $callback)
 * @method RouteRegister  put(string $route, Callable $callback)
 * @method RouteRegister  delete(string $route, Callable $callback)
 * @method RouteRegister  options(string $route, Callable $callback)
 * @method RouteRegister  head(string $route, Callable $callback)
 */
class RouteRegister
{

    /**
     * 按照访问路径查找路由表
     * @var array
     */
    protected $pathList = [];

    /**
     * 按照别名查找路由表
     * @var array
     */
    protected $nameList = [];
    /**
     * 动态传递给路由器的方法。
     * @var array
     */
    protected $passthru = [
        'get', 'post', 'put', 'delete', 'options', 'any',
    ];

    /**
     * 路由组属性堆栈。
     * @var array
     */
    protected $groupStack = [];
    /**
     * 每次路由注册覆盖一次，取别名时用
     * @var array
     */
    protected $lastRouteKeyMethod = [];

    /**
     * 动态处理对路由注册器的调用。
     * @param $method
     * @param $parameters
     * @return RouteRegister
     * @throws \Exception
     */
    public function __call($method, $parameters)
    {
        if (in_array($method, $this->passthru)) {
            return $this->registerRoute($method, ...$parameters);
        }
        throw new \Exception(sprintf(
            'Method %s::%s does not exist.', static::class, $method
        ));
    }

    /**
     * 向路由器注册一条新路由。
     * @param $method
     * @param $uri
     * @param null $action
     * @return $this
     */
    protected function registerRoute($method, $uri, $action = null)
    {
        $endGroup = end($this->groupStack);
        $uri = static::formatPrefix(['prefix' => $uri], $endGroup);
        if (is_string($action) && isset($endGroup['namespace'])) {
            $action = static::formatNamespace(['namespace' => $action], $endGroup);
        }
        $method = strtoupper($method);
        $endGroup['action'] = $action;
        $this->pathList[$uri][$method] = $endGroup;//允许请求的方法=>匿名函数或者要执行的控制器方法
        $this->lastRouteKeyMethod = [
            'method' => $method,
            'uri' => $uri,
        ];
        return $this;
    }

    /**
     * 注册多类型
     * @param $methods
     * @param $uri
     * @param null $action
     * @return mixed
     */
    public function match($methods, $uri, $action = null)
    {
        foreach ($methods as $method) {
            $this->registerRoute($method, $uri, $action);
        }
        return $this;
    }

    /**
     * 创建具有共享属性的路由组。
     * @param  array $attributes
     * @param  \Closure|string $routes
     * @return void
     */
    public function group(array $attributes, $routes)
    {
        if (!empty($this->groupStack)) {
            $old = end($this->groupStack);
            $attributes = array_merge($attributes, [
                'namespace' => static::formatNamespace($attributes, $old),
                'prefix' => static::formatPrefix($attributes, $old),
            ]);
            if (isset($old['middleware'])) {
                $attributes['middleware'] = array_merge_recursive($attributes['middleware'] ?? [], $old['middleware']);
            }
        }
        $this->groupStack[] = $attributes;
        if ($routes instanceof \Closure) {
            $routes($this);
        } else {
            require $routes;
        }
        array_pop($this->groupStack);
    }

    /**
     * 格式化新组属性的命名空间。
     * @param $new
     * @param $old
     * @return string|null
     */
    protected static function formatNamespace($new, $old)
    {
        if (isset($new['namespace'])) {
            return isset($old['namespace'])
                ? trim($old['namespace'], '\\') . '\\' . trim($new['namespace'], '\\')
                : trim($new['namespace'], '\\');
        }
        return $old['namespace'] ?? null;
    }

    /**
     * 格式化新组属性的前缀。
     * @param  array $new
     * @param  array $old
     * @return string|null
     */
    protected static function formatPrefix($new, $old)
    {
        $old = $old['prefix'] ?? null;
        return isset($new['prefix']) ? trim($old, '/') . '/' . trim($new['prefix'], '/') : $old;
    }

    /**
     * 给路由设置别名
     * @param string $name
     */
    public function name(string $name)
    {
        $this->nameList[$name] = $this->lastRouteKeyMethod;
    }

    /**
     * 输出所有注册路由
     * @return array
     */
    public function getPathList()
    {
        return $this->pathList;
    }

    /**
     * 根据uri匹配路由
     * @param $uri
     * @param $method
     * @return array
     */
    public function getUriMethodToRoute($uri, $method)
    {
        return $this->pathList[$uri][$method] ?? [];
    }

    /**
     * 输出所有注册路由
     * @param string $name
     * @return array|mixed
     */
    public function getNameList(string $name = '')
    {
        if ($name) {
            return $this->nameList[$name] ?? array();
        }
        return $this->nameList;
    }

    /**
     * @return RouteRegister
     */
    public static function getInstance()
    {
        static $instance;
        if (!$instance) {
            $instance = new static;
        }
        return $instance;
    }
}