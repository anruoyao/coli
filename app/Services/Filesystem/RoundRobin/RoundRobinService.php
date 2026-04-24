<?php

namespace App\Services\Filesystem\RoundRobin;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class RoundRobinService
{
    // Smart logic will be added later.

    private $disks = ['public'];

    private $diskCacheKey = 'current_file_storage_disk_index';

    public function __construct()
    {
        $this->disks = array_keys($this->getRoundRobinDisks());
    }

    public function getNextDisk() 
    {
        $currentIndex = Cache::get($this->diskCacheKey, 0);

        if ($currentIndex >= count($this->disks)) {
            $currentIndex = 0;
        }

        $selectedDisk = $this->disks[$currentIndex];

        Cache::put($this->diskCacheKey, ($currentIndex + 1) % count($this->disks));

        return $selectedDisk;
    }

    public function getRoundRobinDisks()
    {
        return Arr::except(config('filesystems.disks'), 'local');
    }
}
