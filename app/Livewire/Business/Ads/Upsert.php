<?php

namespace App\Livewire\Business\Ads;

use Throwable;
use App\Models\Ad;
use App\Rules\X\XRule;
use Livewire\Component;
use App\Enums\Ad\AdStatus;
use App\Enums\Ad\AdApproval;
use App\Constants\Filesystem;
use Livewire\WithFileUploads;
use App\Enums\Media\MediaType;
use App\Enums\Media\MediaStatus;
use App\Enums\Wallet\TransactionType;
use App\Services\Wallet\WalletService;
use App\Enums\Wallet\TransactionStatus;
use App\Actions\Media\DeleteMediaAction;
use App\Enums\Wallet\TransactionDirection;
use Illuminate\Validation\ValidationException;
use App\Services\Filesystem\Upload\ImageUploadService;
use App\Services\Filesystem\RoundRobin\RoundRobinService;

class Upsert extends Component
{
    use WithFileUploads;
    
    public Ad $adData;
    public string $upsertType;
    public $formData = [];
    public $creative = null;
    public $adMedia = null;

    public function mount()
    {
        $this->adMedia = $this->adData->media;

        $this->formData = [
            'title' => $this->adData->title,
            'content' => $this->adData->content,
            'cta_text' => $this->adData->cta_text,
            'total_budget' => $this->adData->total_budget,
            'target_url' => $this->adData->target_url,
        ];
    }

    public function updatedCreative()
    {
        if($this->creative) {
            $this->uploadCreative();
        }
    }
    
    public function render()
    {
        return view('livewire.business.ads.upsert');
    }

    public function getRules()
    {
        return [
            'formData.title' => [
                'required',
                'string',
                XRule::join('min', config('ads.ad.validation.title.min')),
                XRule::join('max', config('ads.ad.validation.title.max'))
            ],
            'formData.content' => [
                'required',
                'string',
                XRule::join('min', config('ads.ad.validation.content.min')),
                XRule::join('max', config('ads.ad.validation.content.max'))
            ],
            'formData.cta_text' => [
                'required',
                'string',
                XRule::join('min', config('ads.ad.validation.cta_text.min')),
                XRule::join('max', config('ads.ad.validation.cta_text.max'))
            ],
            'formData.total_budget' => [
                'required',
                'numeric',
                XRule::join('min', config('ads.ad.validation.total_budget.min')),
                XRule::join('max', config('ads.ad.validation.total_budget.max'))
            ],
            'formData.target_url' => [
                'required',
                'url',
                XRule::join('max', config('ads.ad.validation.target_url.max')),
            ]
        ];
    }

    public function submitForm()
    {
        $this->validate(rules: $this->getRules(), attributes: [
            'formData.title' => __('business/ads.form.title'),
            'formData.content' => __('business/ads.form.content'),
            'formData.cta_text' => __('business/ads.form.cta'),
            'formData.total_budget' => __('business/ads.form.budget'),
            'formData.target_url' => __('business/ads.form.target_url'),
        ]);

        if($this->adData->media->isEmpty()) {
            $this->addError('creative', __('business/ads.form.creative_required'));

            return false;
        }

        $updateData = [
            'title' => e($this->formData['title']),
            'content' => e($this->formData['content']),
            'cta_text' => e($this->formData['cta_text']),
            'target_url' => $this->formData['target_url']
        ];

        if($this->upsertType == 'create') {
            $updateData['price_per_view'] = config('ads.price_per_view');
            $updateData['approval'] = config('ads.default_approval') ? AdApproval::APPROVED : AdApproval::PENDING;
            $updateData['status'] = AdStatus::PUBLISHED;
            $updateData['total_budget'] = $this->formData['total_budget'];
        }

        if($this->upsertType == 'edit') {
            // If the ad is rejected, set it to pending.
            // This is to allow the user to update the ad and resubmit it for approval.
            if($this->adData->approval->isRejected()) {
                $updateData['approval'] = AdApproval::PENDING;
            }
        }

        if(! $this->allocateBudget()) {
            return false;
        }

        $this->adData->update($updateData);
        
        return redirect()->route('business.ads.index');
    }

    private function uploadCreative()
    {
        if($this->adMedia->count()) {
            $this->addError('creative', __('business/ads.form.creative_max'));

            return false;
        }
        
        try {
            $this->validate(rules: [
                'creative' => [
                    'required',
                    'image',
                    XRule::join('mimes', config('ads.ad.validation.creative.mimes')),
                    XRule::join('mimetypes', config('ads.ad.validation.creative.mimetypes')),
                    XRule::join('max', config('ads.ad.validation.creative.max'))
                ]
            ], attributes: [
                'creative' => __('business/ads.form.creative')
            ]);

            $imageUploadService = app(ImageUploadService::class);
            $roundRobinService = app(RoundRobinService::class);
            
            $imageData = $imageUploadService
                ->load($this->creative->getRealPath())
                ->setStorageDisk($roundRobinService->getNextDisk())
                ->setNamespace(Filesystem::getAdCreativeNamespace())
                ->crop(config('ads.ad.image_width'), config('ads.ad.image_height'))
                ->compress()
                ->upload();

            if($imageData) {
                $this->adData->media()->create([
                    'source_path' => $imageData['image_path'],
                    'disk' => $imageData['disk'],
                    'type' => MediaType::IMAGE,
                    'status' => MediaStatus::PROCESSED,
                    'extension' => $this->creative->getClientOriginalExtension(),
                    'mime' => $this->creative->getClientMimeType(),
                    'size' => $imageData['image_size'],
                    'metadata' => []
                ]);

                $this->adMedia = $this->adData->media;
            }
        }
        
        catch (ValidationException $e) {
            $this->addError('creative', $e->getMessage());
        }
    }

    private function allocateBudget()
    {
        if($this->upsertType == 'create') {
            if(! me()->wallet->balance->canAfford($this->formData['total_budget'])) {
                $this->addError('formData.total_budget', __('business/ads.form.budget_insufficient'));

                return false;
            }

            $walletService = app(WalletService::class);

            $walletService->setUserData(me())->subtractWalletBalance($this->formData['total_budget'])->addWalletTransaction([
                'amount' => $this->formData['total_budget'],
                'transaction_type' => TransactionType::ADVERTISING,
                'status' => TransactionStatus::COMPLETED,
                'direction' => TransactionDirection::OUTGOING,
                'currency' => config('app.default_currency'),
                'metadata' => [
                    'ad_id' => $this->adData->id,
                    'source' => ['name' => config('ads.name')]
                ]
            ]);
        }
        else {
            if($this->formData['total_budget'] != $this->adData->total_budget) {
                $this->addError('formData.total_budget', __('business/ads.form.budget_edit'));

                return false;
            }
        }

        return true;
    }

    public function deleteAdCreative(int $mediaId)
    {
        $adMediaItem = $this->adMedia->find($mediaId);

        if($adMediaItem) {
            try {
                (new DeleteMediaAction($adMediaItem))->execute();

                $this->adMedia = $this->adData->media;
            }
            
            catch (Throwable $th) {
                $this->addError('creative', $th->getMessage());
            }
        }
    }
}
