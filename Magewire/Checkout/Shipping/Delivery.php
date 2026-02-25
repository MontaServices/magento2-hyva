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

    /**
     * @var string|null $selectedOptionCode - Code of the selected Shipping Option in state
     */
    public ?string $selectedOptionCode = null;

    /**
     * @var array $selectedShipperOptions - Truth map, keyed on code, e.g. ['NoNeighbor' => false, 'SignatureRequired' => true]
     */
    public array $selectedShipperOptions = [];

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
    public function activateTab(bool $active): void
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
    public function updatedSelectedOptionCode(string $deliveryOptionCode): string
    {
        // First find the selected option in array by code
        $option = $this->getSelectedShippingOption();

        // Save to quote
        $this->quoteHelper->setOption(json_encode($option));

        // Shipper options are overwritten in Quote, match visually
        $this->selectedShipperOptions = [];

        return $deliveryOptionCode;
    }

    /** TODO when is $options ever NULL? Strange errors
     * @param array[] $options - ShippingOptions from selected Timeframe (as array)
     * @return void
     */
    public function updateOptions(?array $options = null): void
    {
        // updated array is received as array
        if ($options) {
            $this->options = $options;
        }
    }

    /** Magic method runs after property is updated
     * Basically 'clickShipperOption'
     *
     * @param bool[] $shipperOptions - Boolean truth map, keyed on ShipperOptionCode
     * @param string $property - Magewire passes name of property
     * @return array - the updated array
     */
    public function updatedSelectedShipperOptions(array $shipperOptions): array
    {
        $this->quoteHelper->setShipperOptions(array_map(
            callback: function (string $code) {
                return $this->getShipperOptionByCode($code);
            },
            // Return the keys of the truth map
            array: array_keys(
            // Keep only options that are selected (true)
                array_filter($this->selectedShipperOptions),
            ),
        ));
        // Magic methods must always return the updated value
        return $shipperOptions;
    }

    /** Reconstruct ShipperOption from originally loaded ShippingOption that is currently selected
     * TODO merge duplicate code with ShippingOption::getShipperOptionByCode() except for arrays
     *
     * @param string $code
     * @return array
     */
    protected function getShipperOptionByCode(string $code): array
    {
        $shipperOption = [];
        if ($shippingOption = $this->getSelectedShippingOption()) {
            $shipperOption = array_first(array_filter(
                array: $shippingOption['deliveryOptions'],
                callback: fn($option) => $option['code'] == $code,
            )) ?? []; // TODO when could that ever be NULL?
        }
        return $shipperOption;
    }

    /** Get the ShippingOption currently selected, originally loaded as array
     *
     * @return array|null
     */
    protected function getSelectedShippingOption(): ?array
    {
        return array_first(array_filter(
        // call flat array directly instead of converting back and forth
            array: $this->options,
            callback: fn($option) => $this->isSelected($option['code']),
        ));
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

    /** Check if this is the selected Option
     *
     * @param string $code
     * @return bool
     */
    public function isSelected(string $code): bool
    {
        return $this->selectedOptionCode == $code;
    }
}