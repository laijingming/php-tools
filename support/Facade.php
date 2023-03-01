<?php
/**
 * Created by PhpStorm.
 * User: laijingming
 * Date: 2023/2/28
 * Time: 10:59
 */

namespace ajing\support;


class Facade
{
    /**
     * 已经解析对象实例
     *
     * @var array
     */
    protected static $resolvedInstance;

    /**
     * 从容器中解析外观根实例
     * @return mixed|void
     * @throws \Exception
     */
    protected static function resolveFacadeInstance()
    {
        $name = static::getFacadeAccessor();
        if (is_object($name)) {
            return $name;
        }

        if (isset(static::$resolvedInstance[$name])) {
            return static::$resolvedInstance[$name];
        }

        return static::$resolvedInstance[$name] = static::createInstance();
    }

    /**
     * 获取注册组键实例
     * @throws \Exception
     */
    protected static function createInstance()
    {
        throw new \Exception('Facade does not implement createInstance method.');
    }

    /**
     * 获取组件的注册名称。
     * @throws \Exception
     */
    protected static function getFacadeAccessor()
    {
        throw new \Exception('Facade does not implement getFacadeAccessor method.');
    }

    /**
     * 处理对对象的动态、静态调用。
     * @param $method
     * @param $args
     * @return mixed
     * @throws \Exception
     */
    public static function __callStatic($method, $args)
    {
        $instance = static::resolveFacadeInstance();
        if (!$instance) {
            throw new \Exception('A facade root has not been set.');
        }

        return $instance->$method(...$args);
    }

}