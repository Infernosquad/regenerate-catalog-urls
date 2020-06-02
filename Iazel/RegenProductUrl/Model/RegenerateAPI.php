<?php

namespace Iazel\RegenProductUrl\Model;

use Iazel\RegenProductUrl\Service\RegenerateProductUrl;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator;
use Magento\Cms\Model\Page;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\CmsUrlRewrite\Model\CmsPageUrlRewriteGenerator;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\EntityManager\EventManager;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\Exception\UrlAlreadyExistsException;
use Magento\UrlRewrite\Model\UrlPersistInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

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
     * @var Emulation
     */
    private $emulation;
    /**
     * @var PageCollectionFactory
     */
    private $pageCollectionFactory;
    /**
     * @var UrlPersistInterface
     */
    private $urlPersist;
    /**
     * @var CmsPageUrlRewriteGenerator
     */
    private $cmsPageUrlRewriteGenerator;
    /**
     * @var CategoryCollectionFactory\Proxy
     */
    private $categoryCollectionFactory;
    /**
     * @var CategoryUrlRewriteGenerator\Proxy
     */
    private $categoryUrlRewriteGenerator;
    /**
     * @var EventManager\Proxy
     */
    private $eventManager;

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
        Emulation $emulation,
        PageCollectionFactory $pageCollectionFactory,
        UrlPersistInterface $urlPersist,
        CategoryCollectionFactory\Proxy $categoryCollectionFactory,
        CmsPageUrlRewriteGenerator $cmsPageUrlRewriteGenerator,
        CategoryUrlRewriteGenerator\Proxy $categoryUrlRewriteGenerator,
        EventManager\Proxy $eventManager
    ) {
        $this->state                = $state;
        $this->storeManager         = $storeManager;
        $this->regenerateProductUrl = $regenerateProductUrl;
        $this->emulation = $emulation;
        $this->pageCollectionFactory = $pageCollectionFactory;
        $this->urlPersist = $urlPersist;
        $this->cmsPageUrlRewriteGenerator = $cmsPageUrlRewriteGenerator;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->categoryUrlRewriteGenerator = $categoryUrlRewriteGenerator;
        $this->eventManager = $eventManager;
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

        $stores  = $this->storeManager->getStores(false);

        if (!is_numeric($store)) {
            $store = $this->getStoreIdByCode($store, $stores);
        }

        $this->regenerateProductUrl->execute($pids, (int)$store);

        return $this->regenerateProductUrl->getRegeneratedCount();
    }

    public function regenerateCategoryUrl($store = null, $cids = [])
    {
        try {
            $this->state->getAreaCode();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->state->setAreaCode('adminhtml');
        }

        $this->emulation->startEnvironmentEmulation($store, Area::AREA_FRONTEND, true);

        $categories = $this
            ->categoryCollectionFactory
            ->create()
            ->setStore($store)
            ->addAttributeToSelect(['name', 'url_path', 'url_key'])
            ->addAttributeToFilter('level', ['gt' => 1]);

        if (!empty($cids)) {
            $categories->addAttributeToFilter('entity_id', ['in' => $cids]);
        }

        $regenerated = 0;
        foreach ($categories as $category) {
            $this->urlPersist->deleteByData([
                UrlRewrite::ENTITY_ID     => $category->getId(),
                UrlRewrite::ENTITY_TYPE   => CategoryUrlRewriteGenerator::ENTITY_TYPE,
                UrlRewrite::REDIRECT_TYPE => 0,
                UrlRewrite::STORE_ID      => $store,
            ]);

            $newUrls = $this->categoryUrlRewriteGenerator->generate($category);
            $newUrls = $this->filterEmptyRequestPaths($newUrls);
            $this->urlPersist->replace($newUrls);
            $regenerated += count($newUrls);
        }
        $this->emulation->stopEnvironmentEmulation();

        return $regenerated;
    }

    public function regenerateCategoryPath($store = null, $cids = [])
    {
        try {
            $this->state->getAreaCode();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->state->setAreaCode('adminhtml');
        }

        $categories = $this->categoryCollectionFactory->create()
                                                      ->setStore($store)
                                                      ->addAttributeToSelect(['name', 'url_path', 'url_key'])
                                                      ->addAttributeToFilter('level', ['gt' => 1]);

        if (!empty($cids)) {
            $categories->addAttributeToFilter('entity_id', ['in' => $cids]);
        }

        $regenerated = 0;
        foreach ($categories as $category) {
            $category->setOrigData('url_key', mt_rand(1, 1000)); // set url_key in orig data to random value to force regeneration of path
            $category->setOrigData('url_path', mt_rand(1, 1000)); // set url_path in orig data to random value to force regeneration of path for children

            // Make use of Magento's event for this
            $this->emulation->startEnvironmentEmulation($store, Area::AREA_FRONTEND, true);
            $this->eventManager->dispatch('regenerate_category_url_path', ['category' => $category]);
            $this->emulation->stopEnvironmentEmulation();

            $regenerated++;
        }

        return $regenerated;
    }

    public function regenerateCmsUrl($store = null, $pids = [])
    {
        try {
            $this->state->getAreaCode();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->state->setAreaCode(Area::AREA_ADMINHTML);
        }

        $this->emulation->startEnvironmentEmulation($store, Area::AREA_FRONTEND, true);

        $pages = $this->pageCollectionFactory->create();

        if (!empty($store)) {
            $pages->addStoreFilter($store);
        }

        if (count($pids) > 0) {
            $pages->addFieldToFilter('page_id', ['in' => $pids]);
        }

        $regenerated = 0;
        /** @var Page $page */
        foreach ($pages as $page) {
            $newUrls = $this->cmsPageUrlRewriteGenerator->generate($page);
            try {
                $this->urlPersist->replace($newUrls);
                $regenerated += count($newUrls);
            } catch (UrlAlreadyExistsException $e) {
            }
        }

        $this->emulation->stopEnvironmentEmulation();

        return $regenerated;
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

    /**
     * Remove entries with request_path='' to prevent error 404 for "http://site.com/" address.
     *
     * @param \Magento\UrlRewrite\Service\V1\Data\UrlRewrite[] $newUrls
     * @return \Magento\UrlRewrite\Service\V1\Data\UrlRewrite[]
     */
    private function filterEmptyRequestPaths($newUrls)
    {
        $result = [];
        foreach ($newUrls as $key => $url) {
            $requestPath = $url->getRequestPath();
            if (!empty($requestPath)) {
                $result[$key] = $url;
            }
        }
        return $result;
    }
}
