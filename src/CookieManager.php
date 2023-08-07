<?php

declare(strict_types=1);

namespace SwooleTW\Hyperf\Cookie;

use Hyperf\Context\Context;
use Hyperf\HttpMessage\Cookie\Cookie;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Support\Traits\InteractsWithTime;
use Psr\Http\Message\ServerRequestInterface;

class CookieManager
{
    use InteractsWithTime;

    public function __construct(
        protected RequestInterface $request
    ) {}

    public function has(string $key): bool
    {
        return ! is_null($this->get($key));
    }

    public function get(string $key, ?string $default = null): ?string
    {
        if (! Context::has(ServerRequestInterface::class)) {
            return null;
        }

        return $this->request->cookie($key, $default);
    }

    public function make(string $name, string $value, int $minutes = 0, string $path = '', string $domain = '', bool $secure = false, bool $httpOnly = true, bool $raw = false, ?string $sameSite = null): Cookie
    {
        $time = ($minutes == 0) ? 0 : $this->availableAt($minutes * 60);

        return new Cookie($name, $value, $time, $path, $domain, $secure, $httpOnly, $raw, $sameSite);
    }

    public function queue(...$parameters): void
    {
        if (isset($parameters[0]) && $parameters[0] instanceof Cookie) {
            $cookie = $parameters[0];
        } else {
            $cookie = $this->make(...array_values($parameters));
        }

        $this->appendToQueue($cookie);
    }

    public function expire(string $name, string $path = '', string $domain = ''): void
    {
        $this->queue($this->forget($name, $path, $domain));
    }

    public function unqueue(string $name, string $path = ''): void
    {
        $cookies = $this->getQueuedCookies();
        if ($path === '') {
            unset($cookies[$name]);

            $this->setQueueCookies($cookies);
            return;
        }

        unset($cookies[$name][$path]);

        if (empty($cookies[$name])) {
            unset($cookies[$name]);
        }

        $this->setQueueCookies($cookies);
    }

    protected function appendToQueue(Cookie $cookie): void
    {
        $cookies = $this->getQueuedCookies();
        $cookies[$cookie->getName()][$cookie->getPath()] = $cookie;

        $this->setQueueCookies($cookies);
    }

    public function getQueuedCookies(): array
    {
        return Context::get('http.cookies.queue', []);
    }

    protected function setQueueCookies(array $cookies): array
    {
        return Context::set('http.cookies.queue', $cookies);
    }

    public function forever(string $name, string $value, string $path = '', string $domain = '', bool $secure = false, bool $httpOnly = true, bool $raw = false, ?string $sameSite = null): Cookie
    {
        return $this->make($name, $value, 2628000, $path, $domain, $secure, $httpOnly, $raw, $sameSite);
    }

    public function forget(string $name, string $path = '', string $domain = ''): Cookie
    {
        return $this->make($name, '', -2628000, $path, $domain);
    }
}
