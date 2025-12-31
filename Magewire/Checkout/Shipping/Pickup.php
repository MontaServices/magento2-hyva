<?php
/**
 * @author Jacco.Amersfoort <jacco.amersfoort@monta.nl>
 * @created 12/15/2025 17:09
 */

namespace Monta\Hyva\Magewire\Checkout\Shipping;

use Monta\CheckoutApiWrapper\Objects\PickupPoint;

class Pickup extends Delivery
{
    /** @var string[] */
    protected $listeners = ['updateOptions'];

    /** @var PickupPoint[] $pickupOptions */
    public array $pickupOptions = [];

    /**
     * @param array[] $options - Passed by event as JSON
     * @return void
     */
    public function updateOptions(array $options)
    {
        // Convert argument back into real objects
        $this->pickupOptions = array_map(
            callback: [PickupPoint::class, 'construct'],
            array: $options,
        );
    }
}