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
    protected $listeners = ['updateOptions', 'activateTab'];

    /** @var bool - Whether this component tab is active */
    public bool $tabActive = false;

    /**
     * @var array[] $options - Array of arrays of Shipping Options for the selected Timeframe.
     * Copy of parent property but bound to this component.
     */
    public array $options = [];

    /** @var string|null $selectedOptionCode - Code of the selected Shipping Option in state */
    public ?string $selectedOptionCode = null;

    public array $extras = [];

    /**
     * @param Quote $quoteHelper
     */
    public function __construct(
        protected readonly Quote $quoteHelper,
    )
    {
        // When option was already chosen in Quote, set in component
        if ($option = $this->quoteHelper->getOptionFromQuote()) {
            $this->selectedOptionCode = $option->getCode();
        }
    }

    /** Show this mode tab
     *
     * @param bool $active
     * @return void
     */
    public function activateTab(bool $active)
    {
        $this->tabActive = $active;
    }

    /** Save selected Option JSON string to quote
     * Works on Pickup as well, since properties are the same
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
        // call flat array directly instead of converting back and forth
            array: $this->options,
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
        $this->options = $options;
    }

    /** Magic method runs after property is updated
     * Basically 'clickShipperOption'
     *
     * @param array $extras - The updated property after being updated
     * @param string $property - Magewire passes name of property
     * @return array - the updated array
     */
    public function updatedExtras(array $extras): array
    {
        // Magic methods must always return the updated value
        $this->quoteHelper->setShipperOptions($this->extras);
        return $extras;
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
            array: $this->options,
        );
    }
}