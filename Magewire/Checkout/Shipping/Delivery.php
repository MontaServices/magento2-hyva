<?php
/**
 * @author Jacco.Amersfoort <jacco.amersfoort@monta.nl>
 * @created 12/15/2025 17:09
 */

namespace Monta\Hyva\Magewire\Checkout\Shipping;

use Magewirephp\Magewire\Component;
use Monta\CheckoutApiWrapper\Objects\ShippingOption;

class Delivery extends Component
{
    /** @var string
     * @deprecated
     * */
    public const string OPTIONS_COLLECTION = 'option-collection';

    protected $listeners = ['updateOptions'];

    /** @var ShippingOption[] $selectedDateOptions - Copy of the parent component's property, bound to this component */
    public array $selectedDateOptions = [];

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