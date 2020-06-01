<?php

namespace Iazel\RegenProductUrl\Api;

use Iazel\RegenProductUrl\Model\RegenerateProductUrlInputInterface;

interface RegenerateInterface
{
    /**
     * @param string|null $store
     * @param string[]|null $pids
     *
     * @return string
     */
    public function regenerateProductUrl($store = null, $pids = []);

    /**
     * @return string
     */
    public function regenerateCategoryUrl();

    /**
     * @return string
     */
    public function regenerateCategoryPath();
}
