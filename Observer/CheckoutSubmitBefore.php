<?php

namespace SBS\Checkout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CheckoutSubmitBefore implements ObserverInterface
{
    public function execute(Observer $observer): void
    {
        $quote = $observer->getQuote();
        if ($quote->getShippingAddress()->getSameAsBilling()) {
            $quote->getShippingAddress()->importCustomerAddressData($quote->getBillingAddress()->exportCustomerAddress());
        }
    }
}
