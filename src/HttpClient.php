<?php

declare(strict_types=1);

namespace Fan\EasyWeChat;

use EasyWeChat\Kernel\HttpClient\RequestUtil;
use EasyWeChat\Kernel\HttpClient\ScopingHttpClient;
use EasyWeChat\Kernel\Support\Arr;
use Hyperf\Context\Context;
use Symfony\Component\HttpClient\HttpClient as SymfonyClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

class HttpClient implements HttpClientInterface
{
    public function __construct(protected array $option = [])
    {
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        // 在请求时才实例化客户端
        return $this->client()->request($method, $url, $options);
    }

    public function stream(iterable|ResponseInterface $responses, ?float $timeout = null): ResponseStreamInterface
    {
        // 在请求时才实例化客户端
        return $this->client()->stream($responses, $timeout);
    }

    public function withOptions(array $options): static
    {
        // 合并 如果键名相同，则后面的数组的值将会覆盖前面数组的值
        $this->option = array_replace($this->option, $options);

        $client = $this->client()->withOptions($options);
        // 唯一值
        Context::set($this->contextKey(), $client);
        return $this;
    }

    protected function client(): HttpClientInterface
    {
        $optionsByRegexp = Arr::get($this->option, 'options_by_regexp', []);
        unset($this->option['options_by_regexp']);

        $client = Context::getOrSet($this->contextKey(), function () {
            return SymfonyClient::create(RequestUtil::formatDefaultOptions($this->option));
        });

        if (! empty($optionsByRegexp)) {
            $client = new ScopingHttpClient($client, $optionsByRegexp);
        }
        return $client;
    }

    protected function contextKey(): string
    {
        return sprintf('%s:%s', static::class, md5(json_encode($this->option, JSON_THROW_ON_ERROR)));
    }
}
