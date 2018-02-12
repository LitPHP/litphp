<?php namespace Lit\Bolt\Traits;

use Lit\Bolt\BoltAppEvent;
use Lit\Core\App;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use const Lit\Bolt\EVENT_AFTER_LOGIC;
use const Lit\Bolt\EVENT_BEFORE_LOGIC;

trait EventHookedAppTrait
{
    /**
     * @var ServerRequestInterface
     */
    protected $request;

    protected function main(): ResponseInterface
    {
        /**
         * @var BoltAppEvent $afterEvent
         * @var BoltAppEvent $beforeEvent
         * @var App $this
         */
        $beforeEvent = $this->container->events->dispatch(EVENT_BEFORE_LOGIC, BoltAppEvent::of($this, [
            'request' => $this->request,
        ]));

        $interceptedResponse = $beforeEvent->getResponse();
        if ($interceptedResponse) {
            return $interceptedResponse;
        }

        $response = parent::main();
        $afterEvent = $this->container->events->dispatch(EVENT_AFTER_LOGIC, BoltAppEvent::of($this, [
            'request' => $this->request,
            'response' => $response,
        ]));

        return $afterEvent->getResponse();
    }

}
