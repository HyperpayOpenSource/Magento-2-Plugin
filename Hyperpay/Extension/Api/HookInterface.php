<?php

namespace Hyperpay\Extension\Api;

interface HookInterface
{
    /**
     * webhook function
     *
     * @api
     * @return
     */
    public function hook();
}
