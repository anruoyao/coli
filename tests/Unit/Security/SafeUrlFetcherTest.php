<?php

namespace Tests\Unit\Security;

use App\Services\Text\SafeUrlFetcher;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class SafeUrlFetcherTest extends TestCase
{
    #[DataProvider('unsafeUrls')]
    public function test_it_rejects_non_public_or_unsupported_urls(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(SafeUrlFetcher::class)->fetch($url);
    }

    public function test_it_normalizes_trailing_dot_hosts_and_preserves_bracketed_ipv6_hosts(): void
    {
        $fetcher = new SafeUrlFetcher();
        $method = new \ReflectionMethod($fetcher, 'validateTarget');

        $trailingDot = $method->invoke($fetcher, 'http://example.com./');
        $ipv6 = $method->invoke($fetcher, 'http://[2606:4700:4700::1111]/');

        $this->assertSame('example.com', $trailingDot['host']);
        $this->assertSame('[2606:4700:4700::1111]', $ipv6['host']);
        $this->assertSame('2606:4700:4700::1111', $ipv6['lookup_host']);
        $this->assertSame(
            'http://[2606:4700:4700::1111]/',
            (string) (new \GuzzleHttp\Psr7\Uri('http://[2606:4700:4700::1111]/'))->withHost($ipv6['host'])
        );
    }

    public function test_it_revalidates_redirect_destinations_before_connecting(): void
    {
        $fetcher = new class extends SafeUrlFetcher {
            public array $requests = [];

            protected function sendPinnedRequest(RequestInterface $request, array $target): ResponseInterface
            {
                $this->requests[] = (string) $request->getUri();

                return new Response(302, ['Location' => 'http://127.0.0.1/internal']);
            }
        };

        try {
            $fetcher->fetch('http://example.com/');
            $this->fail('Expected the private redirect destination to be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertCount(1, $fetcher->requests);
            $this->assertSame('http://example.com/', $fetcher->requests[0]);
        }
    }

    public static function unsafeUrls(): array
    {
        return [
            'loopback IPv4' => ['http://127.0.0.1/'],
            'private IPv4' => ['http://10.0.0.1/'],
            'integer IPv4 form' => ['http://2130706433/'],
            'short IPv4 form' => ['http://127.1/'],
            'octal IPv4 form' => ['http://0177.0.0.1/'],
            'hexadecimal IPv4 form' => ['http://0x7f000001/'],
            'link-local IPv4' => ['http://169.254.169.254/'],
            'multicast IPv4' => ['http://224.0.0.1/'],
            'loopback IPv6' => ['http://[::1]/'],
            'unique-local IPv6' => ['http://[fc00::1]/'],
            'link-local IPv6' => ['http://[fe80::1]/'],
            'IPv6 documentation range' => ['http://[2001:db8::1]/'],
            'IPv6 protocol assignment range' => ['http://[2001::1]/'],
            'non-HTTP scheme' => ['file:///etc/passwd'],
            'non-standard port' => ['http://example.com:8080/'],
            'embedded credentials' => ['http://user:pass@example.com/'],
            'invalid host syntax' => ['http://bad_host.example/'],
        ];
    }
}
