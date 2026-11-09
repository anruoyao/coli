<?php

namespace App\Services\Text;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class SafeUrlFetcher implements ClientInterface
{
    private const MAX_REDIRECTS = 3;
    private const MAX_RESPONSE_BYTES = 5_242_880;

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $current = $request;

        for ($redirects = 0; ; $redirects++) {
            $target = $this->validateTarget((string) $current->getUri());
            $current = $current->withoutHeader('Host')->withUri(
                $current->getUri()->withScheme($target['scheme'])->withHost($target['host'])
            );
            $response = $this->sendPinnedRequest($current, $target);

            if (! in_array($response->getStatusCode(), [301, 302, 303, 307, 308], true)) {
                return $response;
            }

            $location = $response->getHeaderLine('Location');
            if ($location === '') {
                return $response;
            }
            if ($redirects >= self::MAX_REDIRECTS) {
                throw new RuntimeException('Too many redirects while fetching external URL.');
            }

            $current = $current->withUri(UriResolver::resolve($current->getUri(), new Uri($location)));
        }
    }

    public function fetch(string $url): ResponseInterface
    {
        return $this->sendRequest(new Request('GET', $url, ['User-Agent' => 'ColibriPlus-LinkPreview/1.0']));
    }

    private function validateTarget(string $url): array
    {
        $parts = parse_url($url);
        if (! is_array($parts)
            || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])) {
            throw new InvalidArgumentException('Only public HTTP and HTTPS URLs are allowed.');
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $lookupHost = rtrim($host, '.');
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if ($port !== ($scheme === 'https' ? 443 : 80)) {
            throw new InvalidArgumentException('Only standard HTTP and HTTPS ports are allowed.');
        }

        $literal = trim($host, '[]');
        if (filter_var($literal, FILTER_VALIDATE_IP)) {
            $addresses = [$literal];
        } else {
            if (preg_match('/^(?:(?:0[xX][0-9a-fA-F]+|[0-9]+)(?:\.(?:0[xX][0-9a-fA-F]+|[0-9]+))*)$/', $lookupHost)
                || ! preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\.(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?))*$/i', $lookupHost)) {
                throw new InvalidArgumentException('The URL host is invalid.');
            }

            $records = @dns_get_record($lookupHost, DNS_A | DNS_AAAA);
            $addresses = [];
            foreach ($records ?: [] as $record) {
                $ip = $record['ip'] ?? $record['ipv6'] ?? null;
                if (is_string($ip)) {
                    $addresses[] = $ip;
                }
            }
        }

        if ($addresses === []) {
            throw new InvalidArgumentException('The URL host has no public address.');
        }
        foreach ($addresses as $address) {
            if (! $this->isPublicAddress($address)) {
                throw new InvalidArgumentException('The URL host resolves to a non-public address.');
            }
        }

        $hostIsIp = filter_var($literal, FILTER_VALIDATE_IP) !== false;

        return [
            'scheme' => $scheme,
            'host' => $hostIsIp ? $host : $lookupHost,
            'lookup_host' => $hostIsIp ? $literal : $lookupHost,
            'port' => $port,
            'ip' => $addresses[0],
        ];
    }

    private function isPublicAddress(string $ip): bool
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        if (strlen($packed) === 4) {
            foreach ([
                ['0.0.0.0', 8], ['10.0.0.0', 8], ['100.64.0.0', 10],
                ['127.0.0.0', 8], ['169.254.0.0', 16], ['172.16.0.0', 12],
                ['192.0.0.0', 24], ['192.0.2.0', 24], ['192.88.99.0', 24],
                ['192.168.0.0', 16], ['198.18.0.0', 15], ['198.51.100.0', 24],
                ['203.0.113.0', 24], ['224.0.0.0', 4], ['240.0.0.0', 4],
            ] as [$network, $bits]) {
                if ($this->inCidr($packed, inet_pton($network), $bits)) {
                    return false;
                }
            }

            return true;
        }

        if (strlen($packed) !== 16
            || ! $this->inCidr($packed, inet_pton('2000::'), 3)
            || $this->inCidr($packed, inet_pton('2001::'), 23)
            || $this->inCidr($packed, inet_pton('2001:db8::'), 32)
            || $this->inCidr($packed, inet_pton('2002::'), 16)) {
            return false;
        }

        return true;
    }

    private function inCidr(string $address, string $network, int $bits): bool
    {
        $wholeBytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;
        if (substr($address, 0, $wholeBytes) !== substr($network, 0, $wholeBytes)) {
            return false;
        }
        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xff << (8 - $remainingBits)) & 0xff;

        return (ord($address[$wholeBytes]) & $mask) === (ord($network[$wholeBytes]) & $mask);
    }

    protected function sendPinnedRequest(RequestInterface $request, array $target): ResponseInterface
    {
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $headers[] = $name . ': ' . $value;
            }
        }
        $hostHeader = $request->getUri()->getHost();
        $hostHeader = str_contains($hostHeader, ':') ? '[' . trim($hostHeader, '[]') . ']' : $hostHeader;
        if (! $request->hasHeader('Host')) {
            $headers[] = 'Host: ' . $hostHeader;
        }

        $responseHeaders = [];
        $headerBlock = [];
        $body = '';
        $tooLarge = false;
        $resolveIp = str_contains($target['ip'], ':') ? '[' . $target['ip'] . ']' : $target['ip'];
        $resolveHost = str_contains($target['lookup_host'], ':') ? '[' . $target['lookup_host'] . ']' : $target['lookup_host'];
        $curl = curl_init((string) $request->getUri());
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_PROXY => '',
            CURLOPT_RESOLVE => [$resolveHost . ':' . $target['port'] . ':' . $resolveIp],
            CURLOPT_ENCODING => '',
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders, &$headerBlock): int {
                $length = strlen($line);
                if (preg_match('/^HTTP\\//', $line)) {
                    $headerBlock = [];
                } elseif (trim($line) === '') {
                    $responseHeaders = $headerBlock;
                } elseif (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $headerBlock[trim($name)][] = trim($value);
                }

                return $length;
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$tooLarge): int {
                if (strlen($body) + strlen($chunk) > self::MAX_RESPONSE_BYTES) {
                    $tooLarge = true;

                    return 0;
                }
                $body .= $chunk;

                return strlen($chunk);
            },
        ]);

        $success = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($tooLarge) {
            throw new RuntimeException('External response exceeds the allowed size.');
        }
        if ($success === false) {
            throw new RuntimeException('Failed to fetch external URL: ' . $error);
        }

        return new Response($status, $responseHeaders, Utils::streamFor($body));
    }
}
