<?php
/**
 * @author Jacco.Amersfoort <jacco.amersfoort@monta.nl>
 * @created 12/15/2025 17:09
 */

namespace Monta\Hyva\Magewire\Checkout\Shipping;

use Monta\CheckoutApiWrapper\Objects\PickupPoint;

class Pickup extends Delivery
{
    public const string INIT_MAP_EVENT = 'monta:mode:' . PickupPoint::PICKUP_OPTIONS_KEY;

    public const string MAP_IDENTIFIER = 'montaPickupsMap';

    /** @var bool $showingMap - Start in Map mode */
    public bool $showingMap = true;

    /** Override on activateTab
     *
     * @param bool $active
     * @return void
     */
    public function activateTab(bool $active)
    {
        parent::activateTab($active);

        $this->triggerMap();
    }

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
        if ($this->showingMap) {
            $this->triggerMap();
        }
    }

    /** Magic method after property has changed
     *
     * @param string $deliveryOptionCode
     * @return string
     */
    public function updatedSelectedOptionCode(string $deliveryOptionCode)
    {
        if ($this->showingMap) {
            $this->triggerMap();
        }
        // Magic methods must return the value
        return $deliveryOptionCode;
    }

    /** Dispatch event to trigger mounting the Google Maps widget
     *
     * @return void
     */
    protected function triggerMap(): void
    {
        $this->dispatchBrowserEvent(self::INIT_MAP_EVENT);
    }
}