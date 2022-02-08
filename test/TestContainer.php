<?php

class TestContainer extends \reporter\Container
{
    /**
     * @var array 容器绑定标识
     */
    protected $bind = [
        'a' => A::class,
        'b' => B::class,
        'c' => C::class,
    ];
}