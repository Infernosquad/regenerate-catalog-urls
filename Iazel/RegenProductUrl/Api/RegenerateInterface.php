<?php

namespace Iazel\RegenProductUrl\Api;

interface RegenerateInterface
{
    /**
     * @param string|null $store
     * @param string[]|null $pids
     *
     * @return int Regenerated product count
     */
    public function regenerateProductUrl($store = null, $pids = []);

    /**
     * @param string|null $store
     * @param string[]|null $cids
     *
     * @return int Regenerated category count
     */
    public function regenerateCategoryUrl($store = null, $cids = []);

    /**
     * @param string|null $store
     * @param string[]|null $cids
     *
     * @return int Regenerated category count
     */
    public function regenerateCategoryPath($store = null, $cids = []);

    /**
     * @param string|null $store
     * @param string[]|null $pids
     *
     * @return int Regenerated pages count
     */
    public function regenerateCmsUrl($store = null, $pids = []);
}
