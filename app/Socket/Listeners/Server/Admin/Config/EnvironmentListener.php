<?php

namespace App\Socket\Listeners\Server\Admin\Config;

use App\Core\Socket\Request;
use App\Socket\Listeners\Listener;

class EnvironmentListener extends Listener
{
    protected array $listeners;

    protected string $responseResultCommand = 'server.admin.config.env.result';

    public function __construct()
    {
        $this->listeners = [
            'get' => 'get',
            'get-all' => 'getAll',
            'set' => 'set',
            'update' => 'update',
            'delete' => 'delete',
            'list-commands' => 'listCommands',
        ];
    }

    public function __invoke(Request $request)
    {
        return call_user_func([$this, $this->listeners[$request->payload()->action]], $request);
    }

    public function listCommands(Request $request)
    {
        resp($request->client())->send(
            $this->responseResultCommand,
            array_keys($this->listeners)
        );
    }

    public function get(Request $request)
    {
        $client = $request->client();
        $message = $request->payload();

        resp($client)->send(
            $this->responseResultCommand,
            $_ENV[$message->name] ?? null
        );
    }

    public function getAll(Request $request)
    {
        resp($request->client())->send(
            $this->responseResultCommand,
            $_ENV
        );
    }

    public function set(Request $request)
    {
        $client = $request->client();
        $message = $request->payload();

        if ($_ENV[$message->name]) {
            resp($client)->send(
                $this->responseResultCommand,
                "Environment variable \"{$message->name} already exist, you can either update or delete only.\"."
            );
            return;
        }

        $_ENV[$message->name] = $message->value;

        resp($client)->send(
            $this->responseResultCommand,
            'Variable has been set.'
        );
    }

    public function update(Request $request)
    {
        $client = $request->client();
        $message = $request->payload();

        if (!$_ENV[$message->name]) {
            resp($client)->send(
                $this->responseResultCommand,
                "Environment variable \"{$message->name}\" does not exits, create it first."
            );
            return;
        }

        $_ENV[$message->name] = $message->value;

        resp($client)->send(
            'server.admin.config.env.update.result',
            'Variable has been updated.'
        );
    }

    public function delete(Request $request)
    {
        $client = $request->client();
        $message = $request->payload();

        if (!$_ENV[$message->name]) {
            resp($client)->send(
                $this->responseResultCommand,
                "Environment variable \"{$message->name}\" does not exits, create it first."
            );
            return;
        }

        unset($_ENV[$message->name]);

        resp($client)->send(
            $this->responseResultCommand,
            'Variable has been set.'
        );
    }
}