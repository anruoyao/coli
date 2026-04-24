<?php

namespace App\Services\World;

use Exception;
use Illuminate\Support\Facades\Log;

class WorldService 
{
    private string $locale = 'en';

    public function __construct()
    {
        $this->locale = app()->getLocale();
    }

    public function setLocale(string $locale): WorldService
    {
        $this->locale = $locale;

        return $this;
    }

    public function countries(): array
    {
        $localPath = var_path("world/countries/{$this->locale}.php");
        
        if(file_exists($localPath)) {
            $countries = require($localPath);
        
            return $countries;
        }
        else{
            Log::error("The [{$this->locale}] countries file is missing.");

            throw new Exception("Required countries [{$this->locale}] configuration file is missing.");
        }
    }

    public function getUserCategories(): array
    {
        $localPath = var_path("world/user_categories/{$this->locale}.php");

        if(file_exists($localPath)) {
            $userCategories = require($localPath);

            return $userCategories;
        }
        else{
            Log::error("The [{$this->locale}] user categories file is missing.");

            throw new Exception("Required user categories [{$this->locale}] configuration file is missing.");
        }
    }

    public function getCountryKeys(): array
    {
        return array_keys($this->countries());
    }

    public function getCountryName(string $isoCode): string|null
    {
        $countries = $this->countries();
        
        return collect($countries)->first(function($v, $key) use ($isoCode) {
            return $key == $isoCode;
        }, '--Country-Not-Found--');
    }
}