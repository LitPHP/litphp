<?php

// @codeCoverageIgnoreStart

use Lit\Air\Configurator;
use Lit\Bolt\BoltAction;
use Lit\Bolt\BoltApp;
use Lit\Bolt\Zend\BoltRunner;
use Psr\Http\Message\ResponseInterface;

is_readable(__DIR__ . '/../vendor/autoload.php')
    ? require(__DIR__ . '/../vendor/autoload.php')
    : require(__DIR__ . '/../../../../vendor/autoload.php');

class HelloAction extends BoltAction
{
    protected function main(): ResponseInterface
    {
        return $this->json()->render([
            'hello' => 'world',
            'method' => $this->request->getMethod(),
            'uri' => $this->request->getUri()->__toString(),
        ]);
    }
}

BoltRunner::run([
    BoltApp::MAIN_HANDLER => Configurator::produce(HelloAction::class)
]);

// @codeCoverageIgnoreEnd
