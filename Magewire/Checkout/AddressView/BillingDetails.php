<?php
/**
 * Hyvä Themes - https://hyva.io
 * Copyright © Hyvä Themes 2022-present. All rights reserved.
 * This product is licensed per Magento install
 * See https://hyva.io/license
 */

declare(strict_types=1);

namespace SBS\Checkout\Magewire\Checkout\AddressView;

use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Customer\Model\Session as SessionCustomer;
use Magento\Quote\Api\CartRepositoryInterface;
use Magewirephp\Magewire\Component;
use Psr\Log\LoggerInterface;

class BillingDetails extends Component
{
    protected CartRepositoryInterface $quoteRepository;
    protected SessionCheckout $sessionCheckout;
    protected SessionCustomer $sessionCustomer;
    protected LoggerInterface $logger;

    protected $listeners = [
        'billing_address_submitted' => 'refresh',
        'billing_address_saved' => 'refresh'
    ];
}
