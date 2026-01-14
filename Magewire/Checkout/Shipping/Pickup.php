<?php
/**
 * @author Jacco.Amersfoort <jacco.amersfoort@monta.nl>
 * @created 12/15/2025 17:09
 */

namespace Monta\Hyva\Magewire\Checkout\Shipping;

use Monta\CheckoutApiWrapper\Objects\PickupPoint;

class Pickup extends Delivery
{
    /** @var bool $showingMap */
    public bool $showingMap = false;

    /**
     * @return PickupPoint[]
     */
    public function getPickupOptions(): array
    {
        // Magewire serializes objects into array on rerender
        if (is_array(array_first($this->options))) {
            return array_map(
                callback: [PickupPoint::class, 'construct'],
                array: $this->options,
            );
        } else {
            return $this->options;
        }
    }

    /** Toggle Map or List view
     *
     * @return void
     */
    public function toggleMap(): void
    {
        $this->showingMap = !$this->showingMap;
        // TODO when becomes true, bind Google Map to the element
    }
}