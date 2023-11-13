<?php
/**
 * Hyvä Themes - https://hyva.io
 * Copyright © Hyvä Themes 2022-present. All rights reserved.
 * This product is licensed per Magento install
 * See https://hyva.io/license
 */

declare(strict_types=1);

namespace SBS\Checkout\Model\Form\EntityFormSaveService;

use Hyva\Checkout\Model\Form\EntityFormInterface;
use Hyva\Checkout\Model\Form\EntityFormSaveServiceInterface;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface as CustomerAddressInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory as CustomerAddressInterfaceFactory;
use Magento\Customer\Api\Data\RegionInterface;
use Magento\Customer\Api\Data\RegionInterfaceFactory;
use Magento\Customer\Model\Data\Customer;
use Magento\Customer\Model\Session as SessionCustomer;
use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Directory\Helper\Data as DirectoryDataHelper;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface as QuoteRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\AddressInterface as QuoteAddressInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory as QuoteAddressInterfaceFactory;
use Magento\Quote\Model\ShippingAddressManagementInterface;

/**
 * TODO we're not satisfied with this Shipping Save Service. Think of stuff like code-duplication in
 *      comparison to the Billing Save Service and a lot of code smells. We will release a backwards-
 *      compatible version after our first production release.
 */
class EavAttributeShippingAddress extends \Hyva\Checkout\Model\Form\EntityFormSaveService\EavAttributeShippingAddress
{
    /**
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function save(EntityFormInterface $form): EntityFormInterface
    {
        $data = $form->toArray();
        $quote = $this->sessionCheckout->getQuote();

        $params = $this->processPostValueDataForQuoteAddress($data);

        if (! $quote->getCustomerIsGuest() && (isset($data['id']) || ($data['save'] ?? false))) {
            $customerAddress = isset($data['id'])
                ? $this->addressRepository->getById($data['id'])
                : $this->customerAddressFactory->create();

            $customerAddressData = $this->processParamsForCustomerAddress($params);
            $this->dataObjectHelper->populateWithArray($customerAddress, $customerAddressData, CustomerAddressInterface::class);
            $customerAddress = $this->saveAddressForCustomer($quote->getCustomer(), $customerAddress);

            $quoteAddress = $quote->getShippingAddress()->importCustomerAddressData($customerAddress);
        } else {
            $quoteAddress = $quote->getShippingAddress();

            if (isset($data[AddressInterface::KEY_EMAIL]) && ! $quote->getCustomerEmail()) {
                $quote->setCustomerEmail($data[AddressInterface::KEY_EMAIL]);
            }

            $quoteAddress->setCustomerAddressId(null);
            $this->dataObjectHelper->populateWithArray($quoteAddress, $params, QuoteAddressInterface::class);
        }

        if ($quoteAddress->getSameAsBilling()) {
            $quote->getShippingAddress()->importCustomerAddressData($quote->getBillingAddress()->exportCustomerAddress());
        }

        $street = [];
        if (is_array($quoteAddress->getStreet())) {
            foreach ($quoteAddress->getStreet() as $s) {
                if (is_array($s)) {
                    $street[] = current($s);
                } else {
                    $street[] = $s;
                }
            }
        } else {
            $street[] = $quoteAddress->getStreet();
        }

        $quoteAddress->setStreet($street);
        $quote->setShippingAddress($quoteAddress);

        $this->quoteRepository->save($quote);
        return $form;
    }

    /**
     * @throws NoSuchEntityException
     */
    public function processPostValueDataForQuoteAddress(array $data): array
    {
        $country = $data[QuoteAddressInterface::KEY_COUNTRY_ID] ?? null;
        $region = $data[QuoteAddressInterface::KEY_REGION] ?? null;

        try {
            $country = $this->countryInformationAcquirer->getCountryInfo($country);
        } catch (NoSuchEntityException $exception) {
            throw new NoSuchEntityException(
                __('It looks like you selected a country which is not available (%1). Please select a different country from the available options.', $country)
            );
        }

        $availableRegions = $country->getAvailableRegions();

        if ($availableRegions) {
            if (is_numeric($region)) {
                $availableRegions = array_filter($availableRegions, static function ($value) use ($region) {
                    return (int)$value->getId() === (int)$region;
                });

                if ($region = reset($availableRegions)) {
                    $data['region_id'] = $region->getId();
                    $data['region'] = $region->getName();
                }
            } else {
                $data['region_id'] = null;
                $data['region'] = null;
            }
        } else {
            $data['region_id'] = null;
        }

        return $data;
    }

    /**
     * @throws LocalizedException
     */
    public function saveAddressForCustomer(
        Customer $customer,
        CustomerAddressInterface $address
    ): CustomerAddressInterface {
        if ($address->getId() === null) {
            $address->setCustomerId($customer->getId());
        } elseif ((int) $address->getCustomerId() !== (int) $customer->getId()) {
            throw new LocalizedException(__('The customer address is not valid.'));
        }

        return $this->addressRepository->save($address);
    }

    public function processParamsForCustomerAddress(array $data): array
    {
        $street = $data[AddressInterface::KEY_STREET] ?? null;

        if ($street && ! is_array($street)) {
            $data[AddressInterface::KEY_STREET] = explode(',', $street);
        }

        $regionData = [
            RegionInterface::REGION_ID => !empty($data['region_id']) ? $data['region_id'] : null,
            RegionInterface::REGION => !empty($data['region']) ? $data['region'] : null,
            RegionInterface::REGION_CODE => !empty($data['region_code'])
                ? $data['region_code']
                : null,
        ];

        $region = $this->regionDataFactory->create();

        $this->dataObjectHelper->populateWithArray(
            $region,
            $regionData,
            RegionInterface::class
        );

        $data['region'] = $region;

        return $data;
    }
}
