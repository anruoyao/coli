<?php

namespace App\Support;

use Illuminate\Support\Number;
use App\Services\Currency\Fiat\FiatCurrencyService;

class Num
{
	public static function abbreviate($number = 0)
	{
		return Number::abbreviate($number, 0, 1);
	}

	public static function currency($number = 0, $in = null)
	{
		$in = $in ?? config('app.default_currency');

		return Number::currency($number, $in, 'ru');
	}

	public static function currencyName($in = null)
	{
		$fiatCurrencyService = app(FiatCurrencyService::class);
		$currencyName = $fiatCurrencyService->getCurrencyName($in);

		if(empty($currencyName)) {
			return $in;
		}
		
		return $currencyName;
	}

	public static function leadingZero($number = 0, $length = 11)
	{
		return str_pad($number, $length, '0', STR_PAD_LEFT);
	}
}
