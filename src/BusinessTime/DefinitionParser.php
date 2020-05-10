<?php

namespace BusinessTime;

use ArrayAccess;
use Spatie\OpeningHours\OpeningHours;

class DefinitionParser
{
    /**
     * @var MixinBase|string
     */
    protected $mixin;

    /**
     * @var OpeningHours|array
     */
    protected $openingHours;

    public function __construct($mixin, $openingHours)
    {
        $this->mixin = $mixin;
        $this->openingHours = $openingHours;
    }

    /**
     * Return region and formatted holidays from options.
     *
     * @param array|null $holidays options or null
     *
     * @return array [region, holidays]
     */
    public function parseHolidaysArray($holidays = null)
    {
        $region = null;

        if ($this->isArrayAccessible($holidays) && isset($holidays[$this->mixin::REGION_OPTION_KEY])) {
            $region = $holidays[$this->mixin::REGION_OPTION_KEY];
            unset($holidays[$this->mixin::REGION_OPTION_KEY]);

            if (isset($holidays[$this->mixin::ADDITIONAL_HOLIDAYS_OPTION_KEY])) {
                $holidays = $holidays[$this->mixin::ADDITIONAL_HOLIDAYS_OPTION_KEY];
            }
        }

        return [$region, $holidays];
    }

    /**
     * Extract "region" and "holidays" keys from options to return them as an arguments array with openingHours.
     *
     * @param array|null $openingHours
     *
     * @return array [region, holidays, openingHours]
     */
    public function extractHolidaysFromOptions($openingHours = null)
    {
        $region = null;
        $holidays = null;

        if (is_string($openingHours[$this->mixin::HOLIDAYS_OPTION_KEY])) {
            $region = $openingHours[$this->mixin::HOLIDAYS_OPTION_KEY];
        } elseif (is_iterable($openingHours[$this->mixin::HOLIDAYS_OPTION_KEY])) {
            [$region, $holidays] = self::parseHolidaysArray($openingHours[$this->mixin::HOLIDAYS_OPTION_KEY]);
        }

        unset($openingHours[$this->mixin::HOLIDAYS_OPTION_KEY]);

        return [$region, $holidays, $openingHours];
    }

    /**
     * @deprecated use getDefinition() instead which also support split argument list.
     *
     * Convert options input into usable definition to be distributed to BusinessTime and BusinessDay.
     *
     * @return array
     */
    public function getSetterParameters()
    {
        @trigger_error(
            'The DefinitionParser::getSetterParameters method is deprecated,'.
            ' use DefinitionParser::getDefinition() instead which also support split argument list.',
            E_USER_DEPRECATED
        );

        return $this->getDefinition([]);
    }

    /**
     * Return the chosen region or "custom-holidays" as default value.
     *
     * @param string $region
     * @param array  $holidays
     *
     * @return string
     */
    public function getRegionOrFallback($region, $holidays)
    {
        return $holidays && !$region ? 'custom-holidays' : $region;
    }

    /**
     * Create and return an OpeningHours instance with holidays options put in embedded metadata.
     *
     * @param string $carbonClass class enabled by the mixin.
     * @param string $arguments   extra paremeters to provide options as a list (empty array by default).
     *
     * @return \Spatie\OpeningHours\OpeningHours
     */
    public function getEmbeddedOpeningHours($carbonClass, array $arguments = [])
    {
        [$region, $holidays, $openingHours] = $this->getDefinition($arguments);
        /* @var \Spatie\OpeningHours\OpeningHours $openingHours */
        $openingHours = $carbonClass::convertOpeningHours($openingHours);

        if ($region) {
            $openingHours->setData([
                $this->mixin::HOLIDAYS_OPTION_KEY => [
                    $this->mixin::REGION_OPTION_KEY              => $region,
                    $this->mixin::ADDITIONAL_HOLIDAYS_OPTION_KEY => $holidays,
                ],
            ]);
        }

        return $openingHours;
    }

    /**
     * Convert options input into usable definition to be distributed to BusinessTime and BusinessDay.
     *
     * @param array $arguments
     *
     * @return array
     */
    public function getDefinition(array $arguments = [])
    {
        $hours = $this->openingHours;

        if (is_string($hours)) {
            return array_pad($arguments, 3, null);
        }

        $region = null;
        $holidays = null;

        if ($this->isArrayAccessible($hours)) {
            if (isset($hours[$this->mixin::HOLIDAYS_ARE_CLOSED_OPTION_KEY])) {
                $hours = $this->handleSpecialOptions($hours);
            }

            if (isset($hours[$this->mixin::HOLIDAYS_OPTION_KEY])) {
                [$region, $holidays, $hours] = $this->extractHolidaysFromOptions($hours);
            }
        }

        return [$this->getRegionOrFallback($region, $holidays), $holidays, $hours];
    }

    /**
     * Check if a value is accessible as an array ($value[...] can be get, set and unset).
     *
     * @param mixed $value
     *
     * @return bool
     */
    private function isArrayAccessible($value)
    {
        return is_array($value) || $value instanceof ArrayAccess;
    }

    /**
     * Extract "holidaysAreClosed" option and turn it into a closure that can be handled by
     * exceptions option of OpeningHours.
     *
     * @param array $options
     *
     * @return mixed options without holidaysAreClosed key and with exceptions updated if the option was true.
     */
    private function handleSpecialOptions($options)
    {
        $exceptions = $options['exceptions'] ?? [];

        if ($options[$this->mixin::HOLIDAYS_ARE_CLOSED_OPTION_KEY]) {
            $exceptions[] = function ($date) {
                return $date->isHoliday() ? [] : null;
            };
        }

        unset($options[$this->mixin::HOLIDAYS_ARE_CLOSED_OPTION_KEY]);

        $options['exceptions'] = $exceptions;

        return $options;
    }
}
