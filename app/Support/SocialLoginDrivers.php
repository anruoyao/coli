<?php

namespace App\Support;

use Illuminate\Support\Str;

class SocialLoginDrivers
{
    protected array $socialLoginOptions;

    public function __construct()
    {
        $this->socialLoginOptions = [
            'google' => $this->getDriverInfo('google'),
            'apple' => $this->getDriverInfo('apple'),
            'telegram' => $this->getDriverInfo('telegram'),
            'tiktok' => $this->getDriverInfo('tiktok'),
            'facebook' => $this->getDriverInfo('facebook'),
            'twitter' => $this->getDriverInfo('twitter'),
            'discord' => $this->getDriverInfo('discord'),
            'vk' => $this->getDriverInfo('vk'),
            'linkedin' => $this->getDriverInfo('linkedin'),
            'microsoft' => $this->getDriverInfo('microsoft')
        ];
    }

    public function getActiveDrivers():array
    {
        return collect($this->socialLoginOptions)->filter(function($item) {
            return (bool) $item['status'] === true;
        })->toArray();
    }
    
    public function getDriver(string $name):array
    {
        return $this->socialLoginOptions[$name] ?? [];
    }

    private function getDriverInfo(string $driverName):array
    {
        $socialLoginProviders = config('social-login.providers');

        return [
            'status' => $socialLoginProviders[$driverName]['enabled'],
            'credentials' => [
                'client_id' => $socialLoginProviders[$driverName]['client_id'],
                'client_secret' => $socialLoginProviders[$driverName]['client_secret'],
                'redirect' => url($socialLoginProviders[$driverName]['redirect'])
            ],
            'meta' => [
                'name' => Str::title($driverName),
                'url' => route("social-login.{$driverName}.redirect"),
                'logo' => asset("assets/social-logos/{$driverName}.png")
            ] 
        ];
    }
}
