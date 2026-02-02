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

    /** @var int $currentSelectedPickup - Index of the selected pickup point in the available options */
    public int $currentSelectedPickup = 0;

    /** Override on activateTab
     *
     * @param bool $active
     * @return void
     */
    public function activateTab(bool $active)
    {
        parent::activateTab($active);
        if ($active) {
            $this->triggerMap();
        }
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
        // Update counter
        $this->currentSelectedPickup = array_find_key(
            array: $this->options,
            callback: fn($option) => $option['code'] == $this->selectedOptionCode)
            // if not found or options empty, fallback to one
            ?? 1;

        // Browser event triggers Google map
        $this->dispatchBrowserEvent(self::INIT_MAP_EVENT);
    }

    /** Step one pickup ahead
     *
     * @return void
     */
    public function nextPickup(): void
    {
        $this->getPickup(1);
    }

    /** Step one pickup down
     *
     * @return void
     */
    public function prevPickup(): void
    {
        $this->getPickup(-1);
    }

    /** Select another Pickup from the available options
     *
     * @param int $incrementer - change Pickup by index in array (plus or minus)
     * @return void
     */
    protected function getPickup(int $incrementer): void
    {
        // If there is an option on that index, change it
        if (!empty($this->options[$this->currentSelectedPickup + $incrementer])) {
            $this->currentSelectedPickup += $incrementer;
        }
        // Update selected option, TODO property listeners are not triggered
        $this->selectedOptionCode =
            // find by updated index, pluck the code
            $this->options[$this->currentSelectedPickup]['code'];
    }
}