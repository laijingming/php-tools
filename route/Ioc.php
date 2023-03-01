<?php
/**
 * Created by PhpStorm.
 * User: aj407
 * Date: 2023/2/25
 * Time: 13:58
 */

namespace ajing\route;


Class Ioc
{

    protected static $Instances = [];

    /**
     * 获取实例
     * @param $abstract
     * @return object
     * @throws \ReflectionException
     */
    public function getInstances($abstract)
    {
        //获取类的反射信息，也就是类的所有信息
        $reflector = new \ReflectionClass($abstract);
//        echo $reflector->getDocComment();//获取类的注释信息

        //获取反射类的构造函数信息
        $constructor = $reflector->getConstructor();
        if (is_null($constructor)) {
            return new $abstract;
        }
        //获取反射类构造函数的参数
        $dependencies = $constructor->getParameters();
        $p = [];
        foreach ($dependencies as $dependency) {
            if (!is_null($dependency->getClass())) {
                $p[] = $this->make($dependency->getClass()->name);
                //这里$p[0]获取是C的实例化，$p[1]获取D的实例化
            }
        }
        //创建一个类新的实例，给出的参数将传递到类的构造函数
        return $reflector->newInstanceArgs($p);
    }

    /**
     * @param $abstract
     * @return mixed|object
     * @throws \ReflectionException
     */
    public function make($abstract)
    {
        if (isset(self::$Instances[$abstract])) {
            return self::$Instances[$abstract];
        }
        return self::$Instances[$abstract] = $this->getInstances($abstract);
    }


    public static function getInstance()
    {
        static $instance;
        if ($instance) {
            return $instance;
        }
        return $instance = new static;
    }
}
