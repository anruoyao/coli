<?php

namespace App\Services\Feedback;

use Exception;

class ReportService
{
	private string $locale;

	private string $reportType;

	public function __construct(string $reportType, ?string $locale = null)
	{
		$this->locale = $locale ?? app()->getLocale();

		$this->reportType = $reportType;
	}

	public function getReasons(): array
	{
        $reasons = var_path("world/reports/{$this->reportType}/{$this->locale}.php");

        if(! file_exists($reasons)) {
            // 指定语言缺文案时回退英文（队列/后台构建场景）
            $reasons = var_path("world/reports/{$this->reportType}/en.php");

            if(! file_exists($reasons)) {
                throw new Exception('An error occurred while fetching report reasons.');
            }
        }

        return require $reasons;
	}
}
