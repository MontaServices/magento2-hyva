<?php
/**
 * @author Jacco.Amersfoort <jacco.amersfoort@monta.nl>
 * @created 12/15/2025 17:09
 */

namespace Monta\Hyva\Magewire\Checkout\Shipping;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
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
     * @param string $property - Magewire always passes property name
     * @param array $shipperOptions - Custom property when updating with options
     * @return string - Magic method must return new value
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function updatedSelectedOptionCode(
        string $deliveryOptionCode,
        string $property = "",
        array $shipperOptions = [],
    ): string
    {
        // First the currently selected option by the updated code
        $option = $this->getSelectedShippingOption();
        $option['selectedShipperOptions'] = $shipperOptions;

        // Save to quote
        $this->quoteHelper->setOption(json_encode($option));

        // Shipper options are overwritten in Quote, match visually
        $this->selectedShipperOptions = $shipperOptions;

        return $deliveryOptionCode;
    }

    /** TODO when is $options ever NULL? Strange errors
     *
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
     * @param array $shipperOptions - Boolean truth map, keyed on ShipperOptionCode grouped on DeliveryOption code
     * e.g. ['PostNL_20260101' => ["NoNeighbor"=>true]]
     * @param string $property - Magewire passes name of property as well
     * @return array - the updated array
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function updatedSelectedShipperOptions(array $shipperOptions): array
    {
        // Shipperoptions is keyed on DeliveryOption, clicking a ShipperOption should select that DeliveryOption
        $this->selectedOptionCode = array_key_first($shipperOptions);

        // Pluck this option's ShipperOptions from the updated array (keyed on DeliveryOptionCode)
        $selectedShipperOptions = $shipperOptions[$this->selectedOptionCode];

        // Convert truth map into flat array of strings
        $flatSelectedShipperOptions = array_map(
            callback: function (string $code) {
                return $this->getShipperOptionByCode($code);
            },
            array: array_keys(
            // Keep only options that are selected (true)
                array_filter($selectedShipperOptions),
            ),
        );

        // Save the DeliveryOption (with ShipperOptions) to quote, since only updating property does not run listeners
        $this->updatedSelectedOptionCode(
            deliveryOptionCode: $this->selectedOptionCode,
            shipperOptions: $flatSelectedShipperOptions,
        );

        // Return new array to update value
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