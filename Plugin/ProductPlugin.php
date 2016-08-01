<?php
/**
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade Smile Elastic Suite to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile\ElasticsuiteCatalog
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2016 Smile
 * @license   Open Software License ("OSL") v. 3.0
 */
namespace Smile\RetailerOffer\Plugin;

use Magento\Catalog\Model\Product;
use Smile\Offer\Api\Data\OfferInterface;
use Smile\Offer\Api\OfferManagementInterface;
use Smile\Retailer\CustomerData\RetailerData;

/**
 * Replace is in stock native filter on layer.
 *
 * @category  Smile
 * @package   Smile\ElasticsuiteCatalog
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 */
class ProductPlugin
{
    /**
     * @var OfferManagementInterface
     */
    private $offerManagement;

    /**
     * @var RetailerData
     */
    private $retailerData;

    /**
     * @var OfferInterface[]
     */
    private $offersCache = [];

    public function __construct(OfferManagementInterface $offerManagement, RetailerData $retailerData)
    {
        $this->offerManagement = $offerManagement;
        $this->retailerData    = $retailerData;
    }

    public function aroundIsAvailable(Product $product, \Closure $proceed)
    {
        $isAvailable = false;
        $offer       = $this->getCurrentOffer($product);

        if ($offer !== null && $offer->isAvailable()) {
            $isAvailable = (bool) $offer->isAvailable();
        }

        return $isAvailable;
    }

    public function aroundGetPrice(Product $product, \Closure $proceed)
    {
        $offer = $this->getCurrentOffer($product);
        $price = $proceed();

        if ($offer && $offer->getPrice()) {
            $price = $offer->getPrice();
        } elseif ($offer && $offer->getSpecialPrice()) {
            $price = $offer->getSpecialPrice();
        }

        return $price;
    }

    public function aroundGetSpecialPrice(Product $product, \Closure $proceed)
    {
        $offer = $this->getCurrentOffer($product);
        $price = $proceed();

        if ($offer && $offer->getSpecialPrice()) {
            $price = $offer->getSpecialPrice();
        }

        return $price;
    }

    public function aroundGetFinalPrice(Product $product, \Closure $proceed, $qty = null)
    {
        $price = $proceed();
        $offer = $this->getCurrentOffer($product);

        if ($offer) {
            if ($offer->getPrice() && $offer->getSpecialPrice()) {
                $price = min($offer->getPrice(), $offer->getSpecialPrice());
            } elseif ($offer->getPrice()) {
                $price = $offer->getPrice();
            } elseif ($offer->getSpecialPrice()) {
                $price = $offer->getSpecialPrice();
            }
        }

        return $price;
    }

    public function aroundGetMinimalPrice(Product $product, \Closure $proceed)
    {
        return $this->aroundGetFinalPrice($product, $proceed);
    }

    /**
     * Return the current pickup date.
     *
     * @return string
     */
    private function getPickupDate()
    {
        return $this->retailerData->getPickupDate();
    }

    /**
     * Return the current retailer id.
     *
     * @return int
     */
    private function getRetailerId()
    {
        return $this->retailerData->getRetailerId();
    }

    /**
     *
     * @param Product $product
     *
     * @return OfferInterface
     */
    private function getCurrentOffer($product)
    {
        $offer      = null;
        $retailerId = $this->getRetailerId();
        $pickupDate = $this->getPickupDate();

        if ($retailerId && $pickupDate) {
            $cacheKey = implode('_', [$product->getId(), $retailerId, $pickupDate]);

            if (false === isset($this->offersCache[$cacheKey])) {
                $offer = $this->offerManagement->getOffer($product->getId(), $retailerId, $pickupDate);
                $this->offersCache[$cacheKey] = $offer;
            }

            $offer = $this->offersCache[$cacheKey];
        }

        return $offer;
    }
}
