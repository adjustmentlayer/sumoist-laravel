<?php

namespace App\Poster\Models;

use App\Poster\meta\PosterDishModification_meta;
use App\Poster\Utils;

/**
 * @class PosterProductModification
 *
 * @property PosterDishModification_meta $attributes
 * @property PosterDishModification_meta $originalAttributes
 */

class PosterDishModification extends PosterModel {
    /**
     * @var PosterDishGroupModification $product
     */
    protected $group;

    public function __construct($attributes, PosterDishGroupModification $group) {
        parent::__construct($attributes);

        $this->group = $group;
    }

    public function getDishModificationId() {
        return $this->attributes->dish_modification_id;
    }

    public function getName() {
        return $this->attributes->name;
    }

    public function getIngredientId() {
        return $this->attributes->ingredient_id;
    }

    public function getType() {
        return $this->attributes->type;
    }

    public function getBrutto() {
        return $this->attributes->brutto;
    }

    public function getPrice() {
        return $this->attributes->price;
    }

    public function getPhotoOrig() {
        // I don't know why, but photo_orig is always empty
        return $this->attributes->photo_orig;
    }

    public function getPhotoLarge() {
        return $this->attributes->photo_large;
    }

    public function getPhotoSmall() {
        // photo_small can have very bad quality, sure it depends on original photo quality
        return $this->attributes->photo_small;
    }

    public function getLastModifiedTime() {
        return $this->attributes->last_modified_time;
    }

    public function asSalesboxOffer() {
        $product = $this->group->getProduct();
        $salesboxStore = $product
            ->getStore()
            ->getRootStore()
            ->getSalesboxStore();
        $offer = new SalesboxOfferV4([], $salesboxStore);
        $offer->setStockType('endless');
        $offer->setUnits('pc');
        $offer->setDescriptions([]);
        $offer->setPhotos([]);
        $offer->setModifierId($this->getDishModificationId());
        $offer->setCategories([]);
        $offer->setExternalId($product->getProductId());
        $offer->setAvailable($product->getFirstSpot()->isVisible());
        $offer->setPrice($this->getPrice());
        $offer->setNames([
            [
                'name' => $product->getProductName() . ' ' . $this->getName(),
                'lang' => 'uk' // todo: move this value to config, or fetch it from salesbox api
            ]
        ]);

        // set photo of product by default
        if ($product->hasPhoto()) {
            $offer->setPreviewURL(Utils::poster_upload_url($product->getPhoto()));
        }

        if ($product->hasPhotoOrigin()) {
            $offer->setOriginalURL(Utils::poster_upload_url($product->getPhotoOrigin()));
        }

        // but photo of modification is more important
        if ($this->getPhotoLarge()) {
            $offer->setPreviewURL(Utils::poster_upload_url($this->getPhotoLarge()));
            $offer->setOriginalURL(Utils::poster_upload_url($this->getPhotoLarge()));
        }

        if ($product->getPhoto() && $product->getPhotoOrigin()) {
            $offer->setPhotos([
                [
                    'url' => Utils::poster_upload_url($product->getPhotoOrigin()),
                    'previewURL' => Utils::poster_upload_url($product->getPhoto()),
                    'order' => 0,
                    'type' => 'image',
                    'resourceType' => 'image'
                ]
            ]);
        }

        if ($this->getPhotoLarge()) {
            $offer->setPhotos([
                [
                    'url' => Utils::poster_upload_url($this->getPhotoLarge()),
                    'previewURL' => Utils::poster_upload_url($this->getPhotoLarge()),
                    'order' => 0,
                    'type' => 'image',
                    'resourceType' => 'image'
                ]
            ]);
        }

        $category = $salesboxStore->findCategoryByExternalId($product->getMenuCategoryId());

        if ($category) {
            $offer->setCategories([$category->getId()]);
        }

        return $offer;
    }

    public function getGroup() {
        return $this->group;
    }
}
