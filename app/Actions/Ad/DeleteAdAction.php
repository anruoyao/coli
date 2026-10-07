<?php

namespace App\Actions\Ad;

use App\Models\Ad;
use App\Actions\Media\DeleteMediaAction;
use App\Actions\Post\DeletePostAction;

class DeleteAdAction
{
	private $adData;

	public function __construct(Ad $adData)
	{
		$this->adData = $adData;
	}

	public function execute()
	{
		// 原生广告：先物理清理影子帖（连带评论/反应等），
		// 再删广告行（posts.ad_id 的 FK cascade 仅作兜底，避免绕过应用层留下孤儿数据）。
		$shadowPost = $this->adData->post()->first();

		if(! empty($shadowPost)) {
			(new DeletePostAction($shadowPost))->execute();
		}

		$this->adData->media->each(function ($mediaItem) {
			(new DeleteMediaAction($mediaItem))->execute();
		});

		$this->adData->delete();
	}
}
