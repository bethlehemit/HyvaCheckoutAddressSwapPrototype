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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magewirephp\Magewire\Component;
use Psr\Log\LoggerInterface;

class ShippingDetails extends Component
{
    public bool $billingAsShipping = false;
    protected $loader = true;

    protected $listeners = [
        'shipping_address_submitted' => 'refresh',
        'billing_address_submitted' => 'refresh',
        'billing_address_activated' => 'refresh',
        "shipping_method_selected" => "refresh",
    ];

    public function __construct(
        protected CartRepositoryInterface $quoteRepository,
        protected SessionCheckout $sessionCheckout,
        protected SessionCustomer $sessionCustomer,
        protected LoggerInterface $logger,
        protected Quote\AddressFactory $quoteAddressFactory,
    ) {}

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function boot(): void
    {
        $addressShipping = $this->sessionCheckout->getQuote()->getShippingAddress();
        $this->billingAsShipping = (bool) $addressShipping->getSameAsBilling();
    }

    public function updatedBillingAsShipping(bool $value): bool
    {
        try {
            $quote = $this->sessionCheckout->getQuote();

            $addressShipping = $quote->getShippingAddress();
            $addressShipping->setSameAsBilling($value);

            if ($value === false) {
                $quote = $this->toggleBillingAsShipping($quote, $value);
            }

            $this->quoteRepository->save($quote);
        } catch (LocalizedException $exception) {
            $this->dispatchErrorMessage('Something went wrong while saving your billing preferences.');
            $value = ! $value;
        }

        return $value;
    }

    public function toggleBillingAsShipping(Quote $quote, bool $value): Quote
    {
        $address = $quote->getBillingAddress();

        if ($value === false) {
            $addressBillingPrimary = $this->sessionCustomer->getCustomer()->getPrimaryBillingAddress();

            if ($addressBillingPrimary) {
                $quote->getShippingAddress()->setCustomerAddressId($addressBillingPrimary->getId());
                return $quote;
            }

            // Handover the shipping address object for later usage.
            $addressShipping = $address;

            $address = $this->quoteAddressFactory->create();
            $address->importCustomerAddressData($addressShipping->exportCustomerAddress());
            $address->setCustomerAddressId($addressShipping->getCustomerAddressId());
        }

        $quote->getBillingAddress()
            ->importCustomerAddressData($address->exportCustomerAddress())
            ->setCustomerAddressId($address->getCustomerAddressId());

        return $quote;
    }

    public function pickupPointEnabled(): bool
    {
        return !empty($this->sessionCheckout->getQuote()->getSendcloudServicePointId());
    }
}
