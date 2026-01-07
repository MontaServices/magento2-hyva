<?php
/**
 * @author Jacco.Amersfoort <jacco.amersfoort@monta.nl>
 * @created 12/15/2025 17:09
 */

namespace Monta\Hyva\Magewire\Checkout\Shipping;

use Magewirephp\Magewire\Component;
use Monta\CheckoutApiWrapper\Objects\ShippingOption;
use Monta\Hyva\Helper\Quote;

class Delivery extends Component
{
    protected $listeners = ['updateOptions'];

    /** @var ShippingOption[] $selectedDateOptions - Copy of the parent component's property, bound to this component */
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
    public function updateOptions(array $options)
    {
        // updated array is received as array, convert back to real objects
        $this->selectedDateOptions = array_map(
            callback: [ShippingOption::class, 'construct'],
            array: $options,
        );
    }
}