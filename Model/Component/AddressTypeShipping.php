<?php

namespace SBS\Checkout\Model\Component;

use Hyva\Checkout\Model\Component\AbstractAddressType;
use Hyva\Checkout\Model\Component\AddressTypeInterface;
use Hyva\Checkout\Model\Form\EntityFormInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\BlockInterface;
use Magento\Quote\Model\Quote\Address;

class AddressTypeShipping extends AbstractAddressType
{
    public const TYPE = 'shipping';

    /**
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getQuoteAddress(): Address
    {
        return $this->sessionCheckout->getQuote()->getShippingAddress();
    }

    public function getForm(): EntityFormInterface
    {
        return $this->entityFormProvider->getShippingAddressForm();
    }

    public function getNamespace(): string
    {
        return self::TYPE;
    }

    public function getComponentViewBlock(): bool|BlockInterface
    {
        try {
            $quote = $this->sessionCheckout->getQuote();
            $addressShipping = $quote->getShippingAddress();

            if ($quote->isVirtual()) {
                if ($quote->getCustomerIsGuest() || count($quote->getCustomer()->getAddresses()) === 0) {
                    return $this->layout->getBlock(sprintf(AddressTypeInterface::VIEW_ADDRESS_FORM, $this));
                }

                return $this->layout->getBlock(sprintf(AddressTypeInterface::VIEW_ADDRESS_LIST, $this));
            }
        } catch (NoSuchEntityException | LocalizedException $exception) {
            return false;
        }

        if ($addressShipping->getSameAsBilling()) {
            return $this->layout->getBlock(sprintf(AddressTypeInterface::VIEW_ADDRESS, $this));
        }

        return false;
    }
}
