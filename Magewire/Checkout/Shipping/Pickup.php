<?php
/**
 * @author Jacco.Amersfoort <jacco.amersfoort@monta.nl>
 * @created 12/15/2025 17:09
 */

namespace Monta\Hyva\Magewire\Checkout\Shipping;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Monta\CheckoutApiWrapper\Objects\PickupPoint;

class Pickup extends Delivery
{
    public const string INIT_MAP_EVENT = 'monta:mode:' . PickupPoint::PICKUP_OPTIONS_KEY;

    public const string MAP_IDENTIFIER = 'montaPickupsMap';

    /** @var bool $showingMap - Start in Map mode */
    public bool $showingMap = true;

    /** @var int $selectedPickupKey - Index of the selected pickup point in the available options */
    public int $selectedPickupKey = 0;

    /** @var string $packstationPostnumber */
    public string $packstationPostnumber = "";

    /** Override on activateTab
     *
     * @param bool $active
     * @return void
     */
    public function activateTab(bool $active): void
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
        // array_first is available in PHP 8.5 but already in polyfill
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
     * @param string $property - Always passed by Magewire
     * @param array $shipperOptions - Ignored by this child
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function updatedSelectedOptionCode(
        string $deliveryOptionCode,
        string $property = "",
        array $shipperOptions = [],
    ): string
    {
        if ($this->showingMap) {
            $this->triggerMap();
        }
        // Call parent to save the option to quote
        return parent::updatedSelectedOptionCode($deliveryOptionCode);
    }

    /** Magic method listening to updated Postnumber
     *
     * @param string $newPostnumber - the updated value, now equal to property
     * @return string - Magic methods must return the value
     */
    public function updatedPackstationPostnumber(string $newPostnumber): string
    {
        $this->quoteHelper->updatePackstation($this->packstationPostnumber);
        // Magic methods must return the value
        return $newPostnumber;
    }

    /** Dispatch event to trigger mounting the Google Maps widget
     *
     * @return void
     */
    protected function triggerMap(): void
    {
        // Update counter
        $this->selectedPickupKey = array_find_key(
            array: $this->getPickupOptions(),
            callback: fn($option) => $this->isSelected($option->getCode()))
            // if not found or options empty, fallback to first item
            ?? array_key_first($this->options);

        // Browser event triggers Google map
        $this->dispatchBrowserEvent(self::INIT_MAP_EVENT);
    }

    /** Step one pickup ahead
     *
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function nextPickup(): void
    {
        $this->getPickup(1);
    }

    /** Step one pickup down
     *
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function prevPickup(): void
    {
        $this->getPickup(-1);
    }

    /** Select another Pickup from the available options
     *
     * @param int $incrementer - change Pickup by index in array (plus or minus)
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function getPickup(int $incrementer): void
    {
        // If there is an option on that index, change it
        if (!empty($this->options[$this->selectedPickupKey + $incrementer])) {
            $this->selectedPickupKey += $incrementer;
        } // TODO else if no next option available, disable arrow button

        // Update selected option
        $this->selectedOptionCode =
            // find by updated index, pluck the code
            $this->options[$this->selectedPickupKey]['code'];

        // Manually trigger magic method, updating property from code does not trigger hooks
        // TODO replace manual magic call with proper lifecycle control
        $this->updatedSelectedOptionCode($this->selectedOptionCode);
    }

    /** Get the HTML classes for one Pickup Point tile
     *
     * @param string $code - Pickup Point code
     * @return array
     */
    public function getClasses(string $code): array
    {
        $classes = [];

        // Focus class on selected option
        $isSelected = $this->isSelected($code);
        if ($isSelected) {
            $classes[] = 'monta-blue-focus';
        }
        // In Map mode, hide the options that are not selected
        if ($this->showingMap && $this->options) {
            // Pluck 'code' from array, works also on PickupPoint[] objects
            $pickupCodes = array_column($this->options, 'code');

            // if the selected option is a Pickup
            $visibleCode = in_array(needle: $this->selectedOptionCode, haystack: $pickupCodes)
                ? $this->selectedOptionCode
                // when no Pickup point is selected, only show the first one
                : ($pickupCodes[array_key_first($pickupCodes)] ?? null);

            if (!$isSelected && $code !== $visibleCode) {
                $classes[] = 'hidden';
            }
        }
        return $classes;
    }
}