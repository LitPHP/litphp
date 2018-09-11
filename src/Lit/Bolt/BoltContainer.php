<?php

declare(strict_types=1);

namespace Lit\Bolt;

use Lit\Air\Injection\SetterInjector;
use Lit\Air\Psr\Container;
use Psr\Http\Message\ResponseFactoryInterface;

class BoltContainer extends Container
{
    public function __construct(?array $config = null)
    {
        parent::__construct(($config ?: []) + [
                Container::KEY_INJECTORS => function () {
                    return [
                        new SetterInjector(),
                    ];
                },
                static::class => $this,

                ResponseFactoryInterface::class => ['$' => 'autowire', BoltResponseFactory::class],
            ]);
    }

    function __get($name)
    {
        return $this->get($name);
    }

    function __isset($name)
    {
        return $this->has($name);
    }
}
