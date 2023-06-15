<?php

namespace App\Poster\SalesboxIntegration;

use App\Poster\Entities\Product;
use App\Poster\Utils;
use App\Salesbox\Facades\SalesboxApi;
use poster\src\PosterApi;

class SalesboxOffer
{
    static public function create($posterId): array {
        $posterProduct = PosterApi::menu()->getProduct([
            'product_id' => $posterId
        ]);

        Utils::assertResponse($posterProduct, 'getProduct');

        $posterProductEntity = new Product($posterProduct->response);

        if(count($posterProductEntity->getModifications()) > 0) {
            // create offer with modifiers
            $offers = [];
            $common = [
                'externalId' => $posterProductEntity->getProductId(),
                'units' => 'pc',
                'stockType' => 'endless',
                'descriptions' => [],
                'photos' => [],
                'categories' => [],
                'names' => []
            ];

            if($posterProductEntity->getMenuCategoryId() !== '0') {
                $categories = collect(SalesboxApi::getCategories()['data']);
                $category = $categories->firstWhere('externalId', $posterProductEntity->getMenuCategoryId());
                if(!$category) {
                    $category = SalesboxCategory::create($posterProductEntity->getMenuCategoryId(), $categories);
                }
                $common['categories'] = [$category['id']];
            }

            if ($posterProductEntity->getPhoto()) {
                $common['photos'][] = [
                    'url' => config('poster.url') . $posterProductEntity->getPhotoOrigin(),
                    'previewURL' => config('poster.url') . $posterProductEntity->getPhoto(),
                    'order' => 0,
                    'type' => 'image',
                    'resourceType' => 'image'
                ];
            }

            foreach($posterProductEntity->getModifications() as $modification) {
                $spot = $modification->spots[0];
                $offer = [
                    'price' =>  intval($spot->price) / 100,
                    'available' => $spot->visible === "1",
                    'modifierId' => $modification->modificator_id,
                ];
                $offer['names'] = [
                    [
                        'name' => $posterProductEntity->getProductName() . ' ' .$modification->modificator_name,
                        'lang' => 'uk'
                    ]
                ];

                $offers[] = array_merge($offer, $common);
            }
            $res = SalesboxApi::createManyOffers([
                'offers' => $offers
            ]);
            return $res['data']['ids'];
        }

        // create offer without modifiers
        $spot = $posterProductEntity->getSpots()[0];

        $offer = [
            'units' => 'pc',
            'stockType' => 'endless',
            'available' => !$posterProductEntity->isHidden($spot->spot_id),
            'price' =>  intval($posterProductEntity->getPrice($spot->spot_id)) / 100,
            'externalId' => $posterProductEntity->getProductId(),
        ];

        $offer['names'] = [
            [
                'name' => $posterProductEntity->getProductName(),
                'lang' => 'uk' // todo: move this value to config, or fetch it from salesbox api
            ]
        ];

        $offer['descriptions'] = [];
        $offer['categories'] = [];

        if(!!$posterProductEntity->getMenuCategoryId()) {
            $categories = collect(SalesboxApi::getCategories()['data']);
            $category = $categories->firstWhere('externalId', $posterProductEntity->getMenuCategoryId());
            if(!$category) {
                $category = SalesboxCategory::create($posterProductEntity->getMenuCategoryId(), $categories);
            }

            $offer['categories'][] = $category['id'];
        }

        $offer['photos'] = [];
        if ($posterProductEntity->getPhoto()) {
            $offer['photos'][] = [
                'url' => config('poster.url') . $posterProductEntity->getPhotoOrigin(),
                'previewURL' => config('poster.url') . $posterProductEntity->getPhoto(),
                'order' => 0,
                'type' => 'image',
                'resourceType' => 'image'
            ];
        }

        $createResp = SalesboxApi::createManyOffers([
            'offers' => [$offer]
        ]);

        return $createResp['data']['ids'];
    }

    static public function update($posterId, $offer) {
        $posterProduct = PosterApi::menu()->getProduct([
            'product_id' => $posterId
        ]);

        Utils::assertResponse($posterProduct, 'getProduct');

        $posterProductEntity = new Product($posterProduct->response);
        $spot = $posterProductEntity->getSpots()[0];

        $updatedOffer = [
            'id' => $offer['id'],
            'price' => intval($posterProductEntity->getPrice($spot->spot_id)) / 100,
            'available' => !$posterProductEntity->isHidden($spot->spot_id)
        ];

        if($posterProductEntity->getMenuCategoryId() != '0') {
            $categories = collect(SalesboxApi::getCategories()['data']);
            $category = $categories->firstWhere('externalId', $posterId);

            if(!$category) {
                $category = SalesboxCategory::create($posterProductEntity->getMenuCategoryId(), $categories);
            }

            $updatedOffer['categories'][] = $category['id'];
        }

        // update photo only it isn't already present
        if(!$offer['originalURL'] && $posterProductEntity->getPhoto()) {
            $updatedOffer['photos'] = [
                [
                    'url' => config('poster.url') . $posterProductEntity->getPhotoOrigin(),
                    'previewURL' => config('poster.url') . $posterProductEntity->getPhoto(),
                    'order' => 0,
                    'type' => 'image',
                    'resourceType' => 'image'
                ]
            ];
        }

        $updateRes = SalesboxApi::updateManyOffers([
            'offers' => [
                $updatedOffer
            ]
        ]);

        return $updateRes['data'];
    }


}