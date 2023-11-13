<?php

namespace SBS\Checkout\Model\Component;

use Hyva\Checkout\Model\Component\AddressTypeBilling as Subject;

class AddressTypeBillingPlugin
{
    /**
     * @param Subject $subject
     * @param callable $proceed
     *
     * @return bool
     */
    public function aroundGetComponentViewBlock(Subject $subject, callable $proceed): bool
    {
        return false;
    }
}
