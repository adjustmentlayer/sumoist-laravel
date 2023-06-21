<?php

namespace App\Poster\Stores;

use App\Poster\Models\PosterCategory;
use App\Poster\Models\PosterProduct;
use App\Poster\Models\SalesboxCategory;
use App\Poster\Models\SalesboxOffer;
use App\Salesbox\Facades\SalesboxApi;
use App\Salesbox\Facades\SalesboxApiV4;
use Illuminate\Support\Arr;

/**
 * @see  \App\Poster\Facades\SalesboxStore
 */
class SalesboxStore
{

    /** @var SalesboxCategory[] $categories */
    private $categories = [];

    /** @var SalesboxOffer[] $offers */
    private $offers = [];

    /** @var string|null $accessToken */
    private $accessToken;

    /** @var RootStore $rootStore */
    private $rootStore;

    public function __construct(RootStore $rootStore)
    {
        $this->rootStore = $rootStore;
    }

    /**
     * @return RootStore
     */
    public function getRootStore(): RootStore
    {
        return $this->rootStore;
    }

    /**
     * @return void
     */
    function authenticate()
    {
        $this->accessToken = SalesboxApi::getAccessToken()['data']['token'];
        SalesboxApi::setAccessToken($this->accessToken);
        SalesboxApiV4::setAccessToken($this->accessToken);
    }

    /**
     * @return SalesboxOffer[]
     */
    public function loadOffers()
    {
        $this->offers = array_map(function ($item) {
            return new SalesboxOffer($item, $this);
        }, SalesboxApiV4::getOffers()['data']);
        return $this->offers;
    }

    /**
     * @return SalesboxOffer[]
     */
    public function getOffers()
    {
        return $this->offers;
    }

    /**
     * @param $external_id
     * @return SalesboxOffer|SalesboxOffer[]|null
     */
    public function findOffer($external_id)
    {
        $ids = Arr::wrap($external_id);
        $found = array_filter($this->offers, function (SalesboxOffer $offer) use ($ids) {
            return in_array($offer->getExternalId(), $ids);
        });
        if (is_array($external_id)) {
            return $found;
        }
        return array_values($found)[0] ?? null;
    }

    public function offerExists($externalId): bool
    {
        return !!$this->findOffer($externalId);
    }

    /**
     * @return SalesboxCategory[]
     */
    public function loadCategories()
    {
        $this->categories = array_map(function ($item) {
            return new SalesboxCategory($item, $this);
        }, SalesboxApi::getCategories()['data']);
        return $this->categories;
    }

    /**
     * @return SalesboxCategory[]
     */
    public function getCategories()
    {
        return $this->categories;
    }

    public function categoryExists($externalId): bool
    {
        return !!$this->findCategory($externalId);
    }

    /**
     * @param $external_id
     * @return SalesboxCategory|SalesboxCategory[]|null
     */
    public function findCategory($external_id)
    {
        $ids = Arr::wrap($external_id);
        $found = array_filter($this->categories, function (SalesboxCategory $category) use ($ids) {
            return in_array($category->getExternalId(), $ids);
        });
        if (is_array($external_id)) {
            return $found;
        }
        return array_values($found)[0] ?? null;
    }

    /**
     * @param SalesboxCategory $salesboxCategory
     * @return array
     */
    public function deleteCategory(SalesboxCategory $salesboxCategory)
    {
        // recursively=true is important,
        // without this param salesbox will throw an error if the category being deleted has child categories
        return SalesboxApi::deleteCategory([
            'id' => $salesboxCategory->getId(),
            'recursively' => true
        ], []);
    }

    /**
     * @param SalesboxCategory[] $categories
     * @return array
     */
    public function updateManyCategories($categories)
    {
        $categories = collect($categories)
            ->map(function (SalesboxCategory $category) {
                return [
                    'names' => $category->getNames(),
                    'available' => $category->getAvailable(),
                    'internalId' => $category->getInternalId(),
                    'originalURL' => $category->getOriginalURL(),
                    'previewURL' => $category->getPreviewURL(),
                    'externalId' => $category->getExternalId(),
                    'id'=> $category->getId(),
                    'parentId' => $category->getParentId(),
                    'photos' => $category->getPhotos(),
                ];
            })
            ->values()
            ->toArray();

        return SalesboxApi::updateManyCategories([
            'categories' => $categories // reindex array
        ]);
    }

    /**
     * @param SalesboxCategory[] $categories
     * @return array
     */
    public function createManyCategories($categories)
    {
        $categories = array_map(function (SalesboxCategory $category) {
            return [
                'names' => $category->getNames(),
                'available' => $category->getAvailable(),
                'internalId' => $category->getInternalId(),
                'originalURL' => $category->getOriginalURL(),
                'previewURL' => $category->getPreviewURL(),
                'externalId' => $category->getExternalId(),
                'parentId' => $category->getParentId(),
                'photos' => $category->getPhotos(),
            ];
        }, $categories);

        return SalesboxApi::createManyCategories([
            'categories' => array_values($categories) //reindex array
        ]);
    }

    /**
     * @param SalesboxOffer[] $offers
     * @return array
     */
    public function createManyOffers($offers)
    {
        $offersAsArray = array_map(function (SalesboxOffer $offer) {
            return [
                'externalId' => $offer->getExternalId(),
                'modifierId' => $offer->getModifierId(),
                'units' => $offer->getUnits(),
                'stockType' => $offer->getStockType(),
                'descriptions' => $offer->getDescriptions(),
                'photos' => $offer->getPhotos(),
                'categories' => $offer->getCategories(),
                'names' => $offer->getNames(),
                'available' => $offer->getAvailable(),
                'price' => $offer->getPrice(),
            ];
        }, $offers);

        return SalesboxApi::createManyOffers([
            'offers' => array_values($offersAsArray)// reindex array, it's important, otherwise salesbox api will fail
        ]);
    }

    /**
     * @param SalesboxOffer[] $offers
     * @return array
     */
    public function updateManyOffers($offers)
    {
        $offersAsJson = array_map(function (SalesboxOffer $offer) {
            return [
                'id' => $offer->getId(),
                'externalId' => $offer->getExternalId(),
                'units' => $offer->getUnits(),
                'stockType' => $offer->getStockType(),
                'descriptions' => $offer->getDescriptions(),
                'photos' => $offer->getPhotos(),
                'categories' => $offer->getCategories(),
                'names' => $offer->getNames(),
                'available' => $offer->getAvailable(),
                'price' => $offer->getPrice(),
            ];
        }, $offers);

        return SalesboxApi::updateManyOffers([
            'offers' => array_values($offersAsJson)// reindex array, it's important, otherwise salesbox api will fail
        ]);
    }

    /**
     * @param SalesboxOffer[] $offers
     * @return array
     */
    public function deleteManyOffers($offers)
    {
        $ids = array_map(function (SalesboxOffer $offer) {
            return $offer->getId();
        }, $offers);

        return SalesboxApi::deleteManyOffers([
            'ids' => array_values($ids)
        ]);
    }

    /**
     * @param PosterProduct[] $poster_categories
     * @return SalesboxOffer[]|array
     */
    public function updateFromPosterProducts($poster_products)
    {
        $found_poster_products = array_filter($poster_products, function (PosterProduct $poster_product) {
            return $this->offerExists($poster_product->getProductId());
        });

        return array_map(function (PosterProduct $poster_product) {
            $offer = $this->findOffer($poster_product->getProductId());
            return $offer->updateFromPosterProduct($poster_product);
        }, $found_poster_products);
    }

    /**
     * @param PosterCategory[] $poster_categories
     * @return SalesboxCategory[]|array
     */
    public function updateFromPosterCategories($poster_categories)
    {
        $found_poster_categories = array_filter($poster_categories, function (PosterCategory $poster_category) {
            return $this->categoryExists($poster_category->getCategoryId());
        });
        return array_map(function (PosterCategory $poster_category) {
            $category = $this->findCategory($poster_category->getCategoryId());
            return $category->updateFromPosterCategory($poster_category);
        }, $found_poster_categories);

    }
}
