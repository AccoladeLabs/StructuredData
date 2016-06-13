<?php
/**
 * @category    Accolade
 * @package     Accolade_Jsonsnippets
 * @author	    Akash Verma <akash@mage.fi>
 * @copyright   Copyright (c) 2014 Mage. (http://www.mage.fi)
 * @license     http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
 
class Accolade_Jsonsnippets_Block_Jsonld extends Mage_Core_Block_Template{
    public function getProduct()
    {
        $product = Mage::registry('current_product');
        return ($product && $product->getEntityId()) ? $product : false;
    }

    public function getAttributeValue($attr)
    {
        $value = null;
        $product = $this->getProduct();
        if($product){
            $type = $product->getResource()->getAttribute($attr)->getFrontendInput();

            if($type == 'text' || $type == 'textarea'){
                $value = $product->getData($attr);
            }elseif($type == 'select'){
                $value = $product->getAttributeText($attr) ? $product->getAttributeText($attr) : '';
            }
        }
        return $value;
    }

    public function getStructuredData()
    {
// get product
        $product = $this->getProduct();

// check if $product exists
        if($product){
            $categoryName = Mage::registry('current_category') ? Mage::registry('current_category')->getName() : '';
            $productId = $product->getEntityId();
            $storeId = Mage::app()->getStore()->getId();
            $currencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();

            $json = array(
                'availability' => $product->isAvailable() ? 'http://schema.org/InStock' : 'http://schema.org/OutOfStock',
                'category' => $categoryName
            );

// check if reviews are enabled in extension's backend configuration
            $review = Mage::getStoreConfig('richsnippets/general/review');
            if($review){
                $reviewSummary = Mage::getModel('review/review/summary');
                $ratingData = Mage::getModel('review/review_summary')->setStoreId($storeId)->load($productId);

// get reviews collection
                $reviews = Mage::getModel('review/review')
                    ->getCollection()
                    ->addStoreFilter($storeId)
                    ->addStatusFilter(1)
                    ->addFieldToFilter('entity_id', 1)
                    ->addFieldToFilter('entity_pk_value', $productId)
                    ->setDateOrder()
                    ->addRateVotes()
                    ->getItems();

                $reviewData = array();
                if (count($reviews) > 0) {
                    foreach ($reviews as $r) {
                        foreach ($r->getRatingVotes() as $vote) {
                            $ratings[] = $vote->getPercent();
                        }

                        $avg = array_sum($ratings) / count($ratings);
                        $avg = number_format(floor(($avg / 20) * 2) / 2, 1); // average rating (1-5 range)

                        $datePublished = explode(' ', $r->getCreatedAt());

// another "mini-array" with schema data
                        $reviewData[] = array(
                            '@type' => 'Review',
                            'author' => $this->htmlEscape($r->getNickname()),
                            'datePublished' => str_replace('/', '-', $datePublished[0]),
                            'name' => $this->htmlEscape($r->getTitle()),
                            'reviewBody' => nl2br($this->escapeHtml($r->getDetail())),
                            'reviewRating' => $avg
                        );
                    }
                }

// let's put review data into $json array
                $json['reviewCount'] = $reviewSummary->getTotalReviews($product->getId());
                $json['ratingValue'] = number_format(floor(($ratingData['rating_summary'] / 20) * 2) / 2, 1); // average rating (1-5 range)
                $json['review'] = $reviewData;
            }

			$manufacturer = Mage::getStoreConfig('jsonsnippets/attributes/manufacturer');
			if ($manufacturer != null){
				$manufacturer = $product->getResource()->getAttribute($manufacturer)->getFrontend()->getValue($product);
			};
			$color = Mage::getStoreConfig('jsonsnippets/attributes/color');
			if ($color != null){
				$color = $product->getResource()->getAttribute($color)->getFrontend()->getValue($product);
			};
			$model = Mage::getStoreConfig('jsonsnippets/attributes/model');
			if ($model != null){
				$model = $product->getResource()->getAttribute($model)->getFrontend()->getValue($product);
			};
// Final array with all basic product data
            $data = array(
                '@context' => 'http://schema.org',
                '@type' => 'Product',
                'name' => $product->getName(),
				'brand' => $manufacturer,
                'sku' => $product->getSku(),
				'color' => $color,
				'model' => $model,
                'image' => $product->getImageUrl(),
                'url' => $product->getProductUrl(),
                'description' => trim(preg_replace('/\s+/', ' ', $this->stripTags($product->getShortDescription()))),
                'offers' => array(
                    '@type' => 'Offer',
                    'availability' => $json['availability'],
                    'price' => number_format((float)$product->getFinalPrice(), 2, '.', ''),
                    'priceCurrency' => $currencyCode,
                    'category' => $json['category']
                )
            );

// if reviews enabled - join it to $data array
            if($review){
                $data['aggregateRating'] = array(
                    '@type' => 'AggregateRating',
                    'bestRating' => '5',
                    'worstRating' => '0',
                    'ratingValue' => $json['ratingValue'],
                    'reviewCount' => $json['reviewCount']
                );
                $data['review'] = $reviewData;
            }
// getting all attributes from "Attributes" section of or extension's config area...
            $attributes = Mage::getStoreConfig('richsnippets/attributes');
// ... and putting them into $data array if they're not empty
			if(is_array($attributes) && !empty($attributes)){
				foreach($attributes AS $key => $value){
					if($value){
						$data[$key] = $this->getAttributeValue($value);
					}
				}
            }
// return $data table in JSON format
            return '[' . json_encode($data) . ']';
        }
		// check if social profiles are enabled
		$socialprofiles = Mage::getStoreConfig('jsonsnippets/socialprofiles/enablesocial');
		if($socialprofiles){
            $socialdata = array(
                '@context' => Mage::getStoreConfig('jsonsnippets/socialprofiles/context'),
				'@type' => Mage::getStoreConfig('jsonsnippets/socialprofiles/type'),
				'name' => Mage::getStoreConfig('jsonsnippets/socialprofiles/name'),
				'url' => Mage::getStoreConfig('jsonsnippets/socialprofiles/url'),
		        'sameAs' => array(
					Mage::getStoreConfig('jsonsnippets/socialprofiles/profile'),
					Mage::getStoreConfig('jsonsnippets/socialprofiles/profile_second'),
					Mage::getStoreConfig('jsonsnippets/socialprofiles/profile_third'),
					Mage::getStoreConfig('jsonsnippets/socialprofiles/profile_fourth')
				),
				'logo' => Mage::getStoreConfig('jsonsnippets/socialprofiles/logo'),
				'contactPoint' => array(
					'telephone' => Mage::getStoreConfig('jsonsnippets/socialprofiles/telephone'),
					'contactType' => Mage::getStoreConfig('jsonsnippets/socialprofiles/contacttype')
				),
		        'address' => array(
					'@type' => 'PostalAddress',
					'streetAddress' => Mage::getStoreConfig('jsonsnippets/socialprofiles/address'),
					'addressLocality' => Mage::getStoreConfig('jsonsnippets/socialprofiles/city'),
					'addressRegion' => Mage::getStoreConfig('jsonsnippets/socialprofiles/region'),
					'postalCode' => Mage::getStoreConfig('jsonsnippets/socialprofiles/postalcode'),
					'addressCountry' => Mage::getStoreConfig('jsonsnippets/socialprofiles/country')
				)
            );
			// remove keys with no value
			foreach($socialdata AS $key => $value){
				if(!$value){
					unset($socialdata[$key]);
				}
			}
			
			return '[' . json_encode($socialdata) . ']';
		}
        return null;

    }
}