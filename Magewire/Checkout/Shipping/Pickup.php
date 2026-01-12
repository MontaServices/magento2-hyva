<?php
/**
 * @author Jacco.Amersfoort <jacco.amersfoort@monta.nl>
 * @created 12/15/2025 17:09
 */

namespace Monta\Hyva\Magewire\Checkout\Shipping;

use Monta\CheckoutApiWrapper\Objects\PickupPoint;

class Pickup extends Delivery
{
    /** @var array[] $pickupOptions - TODO why not use same property name as parent */
    public array $pickupOptions = [];

    /**
     * @param array[] $options - Passed by event as JSON array
     * @return void
     */
    public function updateOptions(array $options): void
    {
        $this->pickupOptions = $options;
    }

    /**
     * @return PickupPoint[]
     */
    public function getPickupOptions(): array
    {
        return array_map(
            callback: [PickupPoint::class, 'construct'],
            array: $this->pickupOptions,
        );
    }
}