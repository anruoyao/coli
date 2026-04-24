<?php
/*
|--------------------------------------------------------------------------
| ColibriPlus - The Social Network Web Application.
|--------------------------------------------------------------------------
| Author: Mansur Terla. Full-Stack Web Developer, UI/UX Designer.
| Website: www.terla.me
| E-mail: mansurtl.contact@gmail.com
| Instagram: @mansur_terla
| Telegram: @mansurtl_contact
|--------------------------------------------------------------------------
| Copyright (c)  ColibriPlus. All rights reserved.
|--------------------------------------------------------------------------
*/

namespace App\Http\Controllers\Api\User\Market;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Enums\Product\ProductType;
use App\Http\Controllers\Controller;
use App\Enums\Product\ProductCondition;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Http\Resources\User\Market\ProductResource;
use App\Services\Currency\Fiat\FiatCurrencyService;
use App\Http\Resources\User\Market\ProductCollection;
use App\Http\Resources\User\Market\CategoryCollection;
use App\Traits\Http\Controllers\Api\User\Market\ValidatesProductFilters;

class MarketController extends Controller
{
    use SupportsApiResponses, ValidatesProductFilters;

    public function getProducts(Request $request)
    {
        $filterOption = $this->getValidatedFilters($request);

        $products = Product::listable()->filter($filterOption)->withRelations()->latest('id')->take(12)->get();
        
        return $this->responseSuccess([
            'data' => ProductCollection::make($products)
        ]);
    }

    public function getProductData(Request $request, $productId)
    {
        $productData = Product::active()->withRelations()->findByHashId($productId);

        if($productData) {
            // TODO: This is a temporary solution to allow the user to view the product if it is rejected.
            // We need to improve this in the future.
            
            if(! $productData->approval->isApproved()) {
                if(! me()->isAdmin() && ! me()->id == $productData->user_id) {
                    return $this->responseResourceNotFoundError('Product', $productId);
                }
            }

            return $this->responseSuccess([
                'data' => ProductResource::make($productData)
            ]);
        }

        return $this->responseResourceNotFoundError('Product', $productId);
    }

    public function getCategories(Request $request)
    {
        $categories = Category::active()->marketplace()->take(16)->get();

        return $this->responseSuccess([
            'data' => CategoryCollection::make($categories)
        ]);
    }

    public function bookmark(Request $request)
    {
        $productId = $request->integer('id');

        $productData = Product::listable()->find($productId);

        if ($productData) {
            $bookmarkedStatus = $productData->isBookmarkedBy(me()->id);

            if($bookmarkedStatus) {
                $productData->removeBookmark(me()->id);
            }
            else {
                $productData->addBookmark(me()->id);
            }

            return $this->responseSuccess([
                'data' => [
                    'bookmarked' => (! $bookmarkedStatus)
                ]
            ]);
        }

        else{
            return $this->responseResourceNotFoundError('Product', $productId);
        }
    }

    public function getMetadata(Request $request)
    {
        $fiatCurrencyService = app(FiatCurrencyService::class);
        
        return $this->responseSuccess([
            'data' => [
                'filter' => [
                    'currencies' => $fiatCurrencyService->getPairedCurrencies(),
                    'conditions' => ProductCondition::physicalProductConditions(),
                    'types' =>  ProductType::types(),
                ]
            ]
        ]);
    }
}
