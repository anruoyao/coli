<?php

namespace App\Actions\Ad;

use App\Models\Ad;
use App\Support\Money;
use App\Enums\Ad\AdStatus;

class AdShowAction
{
	private $adData;

	public function __construct(Ad $adData)
	{
		$this->adData = $adData;
	}
	
	public function execute()
	{
		$this->adData->update([
			'last_show_at' => now()
		]);

		if(empty($this->adData->last_charge_at)) {
			$this->chargeOrFinishAd();
		}
		else {	
			// Only charge if last charge was MORE than X minutes ago
			if(now()->subMinutes(config('ads.charge_interval'))->gt($this->adData->last_charge_at->getTimestamp())) {
				$this->chargeOrFinishAd(); 
			}
		}
	}

	private function chargeOrFinishAd()
	{
		if($this->adData->spent_budget >= $this->adData->total_budget) {
			$this->adData->update([
				'status' => AdStatus::COMPLETED
			]);
		}
		else {
			$this->adData->update([
				'last_charge_at' => now(),
				'views_count' => ($this->adData->views_count + 1),
				'spent_budget' => Money::add($this->adData->spent_budget, $this->adData->price_per_view)
			]);
		}
	}
}
