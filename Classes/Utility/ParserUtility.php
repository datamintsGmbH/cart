<?php

namespace Extcode\Cart\Utility;

/*
 * This file is part of the package extcode/cart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

class ParserUtility
{
    /**
     * Object Manager
     *
     * @var \TYPO3\CMS\Extbase\Object\ObjectManager
     */
    protected $objectManager;

    /**
     * @param \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager
     */
    public function injectObjectManager(
        \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager
    ) {
        $this->objectManager = $objectManager;
    }

    /**
     * Parse Tax Classes
     *
     * @param array $pluginSettings
     * @param string $countryCode
     *
     * @return array
     */
    public function parseTaxClasses(array $pluginSettings, $countryCode)
    {
        $taxClasses = [];

        if (isset($pluginSettings['taxClassRepository']) && is_array($pluginSettings['taxClassRepository'])) {
            $taxClasses = $this->loadTaxClassesFromForeignDataStorage($pluginSettings['taxClassRepository'], $countryCode);
        } elseif (isset($pluginSettings['taxClasses']) && is_array($pluginSettings['taxClasses'])) {
            $taxClasses = $this->parseTaxClassesFromTypoScript($pluginSettings['taxClasses'], $countryCode);
        }

        return $taxClasses;
    }

    /**
     * Parse Tax Classes From TypoScript
     *
     * @param array $taxClassSettings
     * @param string $countryCode
     *
     * @return array $taxes
     */
    protected function parseTaxClassesFromTypoScript(array $taxClassSettings, $countryCode)
    {
        $taxClasses = [];

        if ($countryCode && is_array($taxClassSettings[$countryCode])) {
            $taxClassSettings = $taxClassSettings[$countryCode];
        } elseif ($taxClassSettings['fallback'] && is_array($taxClassSettings['fallback'])) {
            $taxClassSettings = $taxClassSettings['fallback'];
        }

        foreach ($taxClassSettings as $taxClassKey => $taxClassValue) {
            $taxClasses[$taxClassKey] = $this->objectManager->get(
                \Extcode\Cart\Domain\Model\Cart\TaxClass::class,
                $taxClassKey,
                $taxClassValue['value'],
                $taxClassValue['calc'],
                $taxClassValue['name']
            );
        }

        return $taxClasses;
    }

    /**
     * Parse Tax Classes From Repository
     *
     * @param array $taxClassRepositorySettings
     * @param string $countryCode
     *
     * @return array
     */
    protected function loadTaxClassesFromForeignDataStorage(array $taxClassRepositorySettings, $countryCode)
    {
        $taxes = [];

        $data = [
            'taxClassRepositorySettings' => $taxClassRepositorySettings,
            'parsedTaxes' => $taxes
        ];

        $signalSlotDispatcher = $this->objectManager->get(
            \TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class
        );
        $slotReturn = $signalSlotDispatcher->dispatch(
            __CLASS__,
            __FUNCTION__,
            [$data]
        );

        if (is_array($slotReturn[0]['cartProduct'])) {
            $parsedTaxes = $slotReturn[0]['cartProduct'];

            foreach ($parsedTaxes as $parsedTaxKey => $parsedTaxValue) {
                if ($parsedTaxValue instanceof \Extcode\Cart\Domain\Model\Cart\TaxClass) {
                    $taxes[$parsedTaxKey] = $parsedTaxValue;
                }
            }
        }

        return $taxes;
    }

    /**
     * Parse Services
     *
     * @param string $className
     * @param array $pluginSettings Plugin Settings
     * @param \Extcode\Cart\Domain\Model\Cart\Cart $cart
     *
     * @return array
     */
    public function parseServices($className, array $pluginSettings, \Extcode\Cart\Domain\Model\Cart\Cart $cart)
    {
        $services = [];
        $type = strtolower($className) . 's';

        $pluginSettingsType = $this->getTypePluginSettings($pluginSettings, $cart, $type);

        if ($pluginSettingsType['options']) {
            foreach ($pluginSettingsType['options'] as $key => $value) {
                $class = 'Extcode\\Cart\\Domain\\Model\\Cart\\' . $className;
                /**
                 * Service
                 * @var \Extcode\Cart\Domain\Model\Cart\AbstractService $service
                 */
                $service = $this->objectManager->get(
                    $class,
                    $key,
                    $value['title'],
                    $cart->getTaxClass($value['taxClassId']),
                    $value['status'],
                    $value['note'],
                    $cart->getIsNetCart()
                );

                if ($className == 'Payment') {
                    if ($value['provider']) {
                        $service->setProvider($value['provider']);
                    }
                }

                if (is_array($value['extra'])) {
                    $service->setExtraType($value['extra']['_typoScriptNodeValue']);
                    unset($value['extra']['_typoScriptNodeValue']);
                    foreach ($value['extra'] as $extraKey => $extraValue) {
                        $extra = $this->objectManager->get(
                            \Extcode\Cart\Domain\Model\Cart\Extra::class,
                            $extraKey,
                            $extraValue['value'],
                            $extraValue['extra'],
                            $cart->getTaxClass($value['taxClassId']),
                            $cart->getIsNetCart()
                        );
                        $service->addExtra($extra);
                    }
                } elseif (!floatval($value['extra'])) {
                    $service->setExtraType($value['extra']);
                    $extra = $this->objectManager->get(
                        \Extcode\Cart\Domain\Model\Cart\Extra::class,
                        0,
                        0,
                        0,
                        $cart->getTaxClass($value['taxClassId']),
                        $cart->getIsNetCart()
                    );
                    $service->addExtra($extra);
                } else {
                    $service->setExtraType('simple');
                    $extra = $this->objectManager->get(
                        \Extcode\Cart\Domain\Model\Cart\Extra::class,
                        0,
                        0,
                        $value['extra'],
                        $cart->getTaxClass($value['taxClassId']),
                        $cart->getIsNetCart()
                    );
                    $service->addExtra($extra);
                }

                if ($value['free']) {
                    $service->setFreeFrom($value['free']['from']);
                    $service->setFreeUntil($value['free']['until']);
                }
                if ($value['available']) {
                    $service->setAvailableFrom($value['available']['from']);
                    $service->setAvailableUntil($value['available']['until']);
                    if ($value['available']['fallBackId']) {
                        $service->setFallBackId($value['available']['fallBackId']);
                    }
                }

                if ($pluginSettingsType['preset'] == $key) {
                    $service->setIsPreset(true);
                }

                $additional = [];
                if ($value['additional.']) {
                    foreach ($value['additional'] as $additionalKey => $additionalValue) {
                        if ($additionalValue['value']) {
                            $additional[$additionalKey] = $additionalValue['value'];
                        }
                    }
                }

                $service->setAdditionalArray($additional);
                $service->setCart($cart);

                $services[$key] = $service;
            }
        }

        return $services;
    }

    /**
     * @param array $pluginSettings
     * @param \Extcode\Cart\Domain\Model\Cart\Cart $cart
     * @param string $type
     *
     * @return array
     */
    public function getTypePluginSettings(array $pluginSettings, \Extcode\Cart\Domain\Model\Cart\Cart $cart, $type)
    {
        $pluginSettingsType = $pluginSettings[$type];
        $selectedCountry = $pluginSettings['settings']['defaultCountry'];

        if ($cart->getCountry()) {
            if ($type == 'payments') {
                $selectedCountry = $cart->getBillingCountry();
            } else {
                $selectedCountry = $cart->getCountry();
            }
        }

        if ($selectedCountry) {
            if (is_array($pluginSettingsType['countries'][$selectedCountry])) {
                $countrySetting = $pluginSettingsType['countries'][$selectedCountry];
                if (is_array($countrySetting) && !empty($countrySetting)) {
                    return $countrySetting;
                }
            }

            if (is_array($pluginSettingsType['zones'])) {
                $zoneSetting = $this->getTypeZonesPluginSettings($pluginSettingsType['zones'], $cart);
                if (is_array($zoneSetting) && !empty($zoneSetting)) {
                    return $zoneSetting;
                }
            }

            if (is_array($pluginSettingsType[$selectedCountry])) {
                $countrySetting = $pluginSettingsType[$selectedCountry];
                if (is_array($countrySetting) && !empty($countrySetting)) {
                    return $countrySetting;
                }
            }

            return $pluginSettingsType;
        }
        return $pluginSettingsType;
    }

    /**
     * @param array $zoneSettings
     * @param \Extcode\Cart\Domain\Model\Cart\Cart $cart
     *
     * @return array
     */
    public function getTypeZonesPluginSettings(array $zoneSettings, \Extcode\Cart\Domain\Model\Cart\Cart $cart)
    {
        foreach ($zoneSettings as $zoneSetting) {
            $zoneSetting['countries'] = preg_replace('/\s+/', '', $zoneSetting['countries']);
            $countriesInZones = explode(',', $zoneSetting['countries']);

            if (in_array($cart->getCountry(), $countriesInZones)) {
                return $zoneSetting;
            }
        }

        return [];
    }
}
