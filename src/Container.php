<?php

namespace reporter;

use Closure;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionException;
use ReflectionFunction;
use Exception;

class Container
{
    /**
     * @var Container 容器对象实例
     */
    protected static $instance;

    /**
     * @var array 容器中的对象实例
     */
    protected $instances = [];

    /**
     * @var array 容器绑定标识
     */
    protected $bind = [];

    /**
     * @var array 容器标识别名
     */
    protected $name = [];

    private function __construct()
    {
    }

    /**
     * 获取当前容器的实例（单例）
     * @access public
     * @return static
     */
    public static function getInstance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * @param string $abstract 类名或标识
     * @param array $vars 传入的参数 [default=[]]
     * @param bool $newInstance 是否每次创建新的实例
     * @return object
     */
    public static function get($abstract, $vars = [], $newInstance = false)
    {
        return static::getInstance()->make($abstract, $vars, $newInstance);
    }

    /**
     * @param string $abstract 类名或标识
     * @param array $vars 传入的参数 [default=[]]
     * @param bool $newInstance 是否每次创建新的实例
     * @return object
     */
    protected function make($abstract, $vars = [], $newInstance = false)
    {
        $abstract = isset($this->name[$abstract]) ?: $abstract;

        if (isset($this->instances[$abstract]) === true && $newInstance === false) {
            return $this->instances[$abstract];
        }

        if (isset($this->bind[$abstract]) === true) {

            $concrete = $this->bind[$abstract];

            if ($concrete instanceof Closure) {
                $object = $this->invokeFunction($concrete, $vars);
            } else {
                // 记录标识
                $this->name[$abstract] = $concrete;
                return $this->make($concrete, $vars, $newInstance);
            }

        } else {
            $object = $this->invokeClass($abstract, $vars);
        }

        if ($newInstance === false) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * 调用反射执行类的实例化 支持依赖注入
     *
     * @param string $class 类名
     * @param array $vars 参数数据 [default=[]]
     */
    protected function invokeClass($class, $vars = [])
    {
        try {

            $reflect = new ReflectionClass($class);

            // 验证是否支持静态的形式实例化
            if ($reflect->hasMethod('__make') === true) {
                $method = $reflect->getMethod('__make');
                if ($method->isPublic() === true && $method->isStatic() === true) {
                    $args = $this->bindParams($method, $vars);
                    return $method->invokeArgs(null, $args);
                }
            }

            // 获取构造函数
            $method = $reflect->getConstructor();
            $args = $method ? $this->bindParams($method, $vars) : [];

            return $reflect->newInstanceArgs($args);

        } catch (ContainerException $e) {
            throw new ContainerException('class not exists: ' . $class, $class);
        }
    }

    /**
     * 调用反射执行函数或闭包 支持依赖注入
     *
     * @param mixed $function 函数或闭包
     * @param array $vars 参数
     * @return mixed
     */
    protected function invokeFunction($function, $vars)
    {
        try {
            $reflect = new ReflectionFunction($function);

            $args = $this->bindParams($reflect, $vars);

            return call_user_func_array($function, $args);

        } catch (ReflectionException $e) {
            throw new Exception('function not exists:' . $function . '()');
        }
    }

    /**
     * 绑定参数
     *
     * @param ReflectionMethod|ReflectionFunction $method
     * @param array $vars 函数参数数据
     */
    public function bindParams($method, $vars)
    {
        if ($method->getNumberOfParameters() === 0) {
            return [];
        }

        reset($vars);
        $type = key($vars) === 0 ? 1 : 0;
        $params = $method->getParameters();

        return $this->analysisParams($params, $vars, $type);
    }

    /**
     * 解析参数
     *
     * @param array $params 参数
     * @param array $vars 参数数据
     * @param int $type 参数类型
     * @return array
     */
    public function analysisParams($params, array $vars, $type)
    {
        $args = [];
        foreach ($params as $param) {
            /**
             * @var ReflectionParameter $param
             */
            $name = $param->getName();
            $class = $param->getClass();

            if ($class) {
                $args[] = $this->getObjectParam($class->getName(), $vars);
            } else if (1 === $type && !empty($vars)) {
                $args[] = array_shift($vars);
            } else if (0 === $type && isset($vars[$name]) === true) {
                $args[] = $vars[$name];
            } else if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new ContainerException('method param miss:' . $name);
            }
        }

        return $args;
    }

    /**
     * 获取对象类型的参数值
     *
     * @param string $className 类名
     * @param array $vars 参数数据
     * @return mixed
     */
    protected function getObjectParam($className, &$vars)
    {
        $array = $vars;
        $value = array_shift($array);

        if ($value instanceof $className) {
            $result = $value;
            array_shift($vars);
        } else {
            $result = $this->make($className);
        }

        return $result;
    }
}