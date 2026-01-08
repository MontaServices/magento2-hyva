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

    /** @var array[] $selectedDateOptions - JSON array Copy of the parent component's property, bound to this component */
    public array $selectedDateOptions = [];

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
     * @param string $deliveryOption
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function clickDeliveryOption(string $deliveryOption)
    {
        $this->quoteHelper->setOption($deliveryOption);
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
     * TODO tried using magewire magic with `getSelectedDateOptionsProperty` but to no avail
     * @return ShippingOption[]
     */
    public function getSelectedDateOptions(): array
    {
        return array_map(
            callback: [ShippingOption::class, 'construct'],
            array: $this->selectedDateOptions,
        );
    }
}