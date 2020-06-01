<?php

namespace Iazel\RegenProductUrl\Model;

use Iazel\RegenProductUrl\Service\RegenerateProductUrl;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Store\Model\StoreManagerInterface;

class RegenerateAPI
{
    /**
     * @var \Magento\Framework\App\State
     */
    private $state;
    /**
     * @var StoreManagerInterface\Proxy
     */
    private $storeManager;
    /**
     * @var RegenerateProductUrl
     */
    private $regenerateProductUrl;
    /**
     * @var Request
     */
    private $request;

    /**
     * RegenerateProductUrlCommand constructor.
     * @param State $state
     * @param StoreManagerInterface\Proxy $storeManager
     * @param RegenerateProductUrl $regenerateProductUrl
     */
    public function __construct(
        State $state,
        StoreManagerInterface\Proxy $storeManager,
        RegenerateProductUrl $regenerateProductUrl,
        Request $request
    ) {
        $this->state                = $state;
        $this->storeManager         = $storeManager;
        $this->regenerateProductUrl = $regenerateProductUrl;
        $this->request              = $request;
    }

    /**
     * {@inheritDoc}
     */
    public function regenerateProductUrl($store = null, $pids = [])
    {
        try {
            $this->state->getAreaCode();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->state->setAreaCode('adminhtml');
        }

        $storeId = $this->request->getParam('store', null);
        $stores  = $this->storeManager->getStores(false);

        if (!is_numeric($storeId)) {
            $storeId = $this->getStoreIdByCode($storeId, $stores);
        }

        $pids = $this->request->getParam('pids', []);

        if (!empty($pids)) {
            $pids = explode(',', $pids);
        }

        $this->regenerateProductUrl->execute($pids, (int)$storeId);

        return 'success';
    }

    public function regenerateCategoryUrl()
    {
        // TODO: Implement regenerateCategoryUrl() method.
    }

    public function regenerateCategoryPath()
    {
        // TODO: Implement regenerateCategoryPath() method.
    }

    private function getStoreIdByCode($store_id, $stores)
    {
        foreach ($stores as $store) {
            if ($store->getCode() == $store_id) {
                return $store->getId();
            }
        }

        return false;
    }
}
