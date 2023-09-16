<?php
namespace Embitel\CatalogGraphQl\Model\Resolver\DataProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Promises
{
    /**
     *
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param $product
     * @return array
     */
    public function getPromisesData($product)
    {
        $path = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        $data = [];
        $returnableDays = ($product->getReturnWindowInDays()!='')?$product->getReturnWindowInDays():7;

        /* Easy Returns */

        if ($product->getIsReturnable()) {
           $data[] = [
             'title' => $returnableDays.' '.$this->scopeConfig->getValue("promises/easy_returns/title"),
             'content' => $this->scopeConfig->getValue("promises/easy_returns/content"),
             'image_url' => $path.'promises_images/'.$this->scopeConfig->getValue("promises/easy_returns/image_url")
          ];
        } else {
           $data[] = [
             'title' => $this->scopeConfig->getValue("promises/non_returns/title"),
             'content' => $this->scopeConfig->getValue("promises/non_returns/content"),
             'image_url' => $path.'promises_images/'.$this->scopeConfig->getValue("promises/non_returns/image_url")
          ];
        }

        /* 100% Original */
        // $data[] = [
        //      'title' => '100% Original',
        //      'content' => 'Our products are 100% original Lorem Ipsum Lorem Ipsum Lorem Ipsum Lorem Ipsum ',
        //      'image_url' => $path.'promises_images/Original.png'
        //   ];

        /* Ebo Fullfilled */
        if (strpos(strtolower($product->getFulfillmentMethod()), 'ebo')!== false) {
            $data[] = [
             'title' => $this->scopeConfig->getValue("promises/fulfillment/title"),
             'content' => $this->scopeConfig->getValue("promises/fulfillment/content"),
             'image_url' => $path.'promises_images/'.$this->scopeConfig->getValue("promises/fulfillment/image_url")
          ];
        }

        /* Pod Eligible */
        if ($product->getPodEligible()) {
            $data[] = [
             'title' => $this->scopeConfig->getValue("promises/pod_eligible/title"),
             'content' => $this->scopeConfig->getValue("promises/pod_eligible/content"),
             'image_url' => $path.'promises_images/'.$this->scopeConfig->getValue("promises/pod_eligible/image_url")
          ];
        } else {
            $data[] = [
             'title' => '',
             'content' => $this->scopeConfig->getValue("promises/pod_non_eligible/content"),
             'image_url' => $path.'promises_images/'.$this->scopeConfig->getValue("promises/pod_non_eligible/image_url")
          ];
        }

        return $data;
    }
}
