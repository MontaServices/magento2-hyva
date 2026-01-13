<?php
/**
 * @author Jacco.Amersfoort <jacco.amersfoort@monta.nl>
 * @created 12/15/2025 17:09
 */

namespace Monta\Hyva\Magewire\Checkout\Shipping;

use Magewirephp\Magewire\Component;
use Monta\Checkout\Helper\Quote;
use Monta\CheckoutApiWrapper\Objects\ShippingOption;

class Delivery extends Component
{
    protected $listeners = ['updateOptions'];

    /**
     * @var array[] $selectedDateOptions - Array of arrays of Shipping Options for the selected Timeframe.
     * Copy of parent property but bound to this component.
     */
    public array $selectedDateOptions = [];

    /** @var string|null $selectedOptionCode - Code of the selected Shipping Option in state */
    public ?string $selectedOptionCode = null;

    /**
     * @param Quote $quoteHelper
     */
    public function __construct(
        protected readonly Quote $quoteHelper,
    )
    {
    }

    /** Save selected Option JSON string to quote
     *
     * @param string $deliveryOptionCode - New value passed by browser
     * @return string - Magic method must return new value
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function updatingSelectedOptionCode(string $deliveryOptionCode)
    {
        // First find the selected option in array by code
        $option = array_find(
        // call flat array directly instead of converting to object and then back to JSON
            array: $this->selectedDateOptions,
            callback: fn($option) => $option['code'] == $deliveryOptionCode);

        // Save to quote
        $this->quoteHelper->setOption(json_encode($option));

        return $deliveryOptionCode;
    }

    /**
     * @param array[] $options - ShippingOptions from selected Timeframe (as array)
     * @return void
     */
    public function updateOptions(array $options): void
    {
        // updated array is received as array
        $this->selectedDateOptions = $options;
    }

    /** Convert array to real objects
     *
     * @return ShippingOption[]
     */
    public function getSelectedDateOptions(): array
    {
        // Magewire serializes objects into arrays, convert back
        return array_map(
            callback: [ShippingOption::class, 'construct'],
            array: $this->selectedDateOptions,
        );
    }
}