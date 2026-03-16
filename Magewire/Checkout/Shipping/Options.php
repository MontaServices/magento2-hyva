<?php

declare(strict_types=1);

namespace Monta\Hyva\Magewire\Checkout\Shipping;

use GuzzleHttp\Exception\GuzzleException;
use Magento\Checkout\Model\Session\Proxy as Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magewirephp\Magewire\Component;
use Monta\Checkout\Helper\Configuration;
use Monta\Checkout\Helper\Shipping;
use Monta\CheckoutApiWrapper\Objects\PickupPoint;
use Monta\CheckoutApiWrapper\Objects\ShippingOption;
use Monta\CheckoutApiWrapper\Objects\TimeFrame;

class Options extends Component
{

    /**
     * @var TimeFrame[] $deliveryOptions - These are actually the Timeframes
     * not accessed externally but making this protected causes the entire Delivery tab to hide
     */
    public array $deliveryOptions = [];

    /** @var PickupPoint[] */
    public array $pickupOptions = [];

    /** @var PickupPoint[] $storeLocations - StoreCollect (array like other properties, but just one) */
    public array $storeLocations = [];

    /** @var ShippingOption|array|null $standardShipper */
    public ShippingOption|array|null $standardShipper = null;

    /**
     * @var bool - Whether the block should be visible
     * @deprecated - Can be handled entirely by Hyva's "as" logic: https://docs.hyva.io/hyva-checkout/devdocs/shipping/shipping-integration-api.html#registering-a-shipping-method
     */
    public bool $show = false;

    /** @var string|null - State property for remembering selected date */
    public ?string $selectedTimeFrameDate = null;

    /** Which tab is active?
     *
     * @var string - Start on Delivery
     */
    public string $mode = Delivery::IDENTIFIER;

    /**
     * @var string[]
     */
    protected $listeners = [
        'shipping_method_selected' => 'onShippingMethodSelected',
        'shipping_address_saved' => 'loadShippingOptions',
    ];

    /**
     * @param Session $checkoutSession
     * @param Shipping $shippingHelper
     * @param Configuration $configHelper
     */
    public function __construct(
        protected readonly Session $checkoutSession,
        protected readonly Shipping $shippingHelper,
        protected readonly Configuration $configHelper,
    )
    {
    }

    /** Mount runs before any child components exist
     *
     * @return void
     */
    public function mount(): void
    {
        $this->loadShippingOptions();
        // TODO set selectedTimeframe date from Quote
    }

    /** Attempt to load Shipping options for current quote
     *
     * @param string|null $method
     * @return void
     */
    public function loadShippingOptions(?string $method = null): void
    {
        try {
            $quote = $this->getQuote();
            if ($this->isMonta($method ?? $quote->getShippingAddress()->getShippingMethod())) {
                // Retrieve shipping options from same helper
                if ($options = $this->shippingHelper->getOptions($quote)) {
                    $this->deliveryOptions = $options[ShippingOption::SHIPPING_OPTIONS_KEY] ?? [];
                    $this->pickupOptions = $options[PickupPoint::PICKUP_OPTIONS_KEY] ?? [];
                    // If storelocation was set, convert to array
                    if (!empty($options[PickupPoint::PICKUP_STORE_KEY])) {
                        $this->storeLocations = [$options[PickupPoint::PICKUP_STORE_KEY]];
                    }
                    $this->standardShipper = $options[ShippingOption::SHIPPING_STANDARD_KEY] ?? null;
                }
            }

            // "click" the selected Timeframe because the magic method only runs on updates
            $this->updatingSelectedTimeFrameDate($this->selectedTimeFrameDate);
        } catch (GuzzleException|LocalizedException $exception) {
            // Catch and ignore
        }
    }

    /**
     * @return string
     */
    public function getCarrierTitle(): string
    {
        return $this->configHelper->getCarrierTitle();
    }

    /**
     * @return bool
     */
    public function isFallbackShippper(): bool
    {
        return (sizeof($this->deliveryOptions) == 1 && array_first($this->getDeliveryOptions())->isFallbackShipper());
    }

    /** Runs after everything is created and properties exist
     *
     * @return void
     */
    public function booted(): void
    {
        // When only Pickups, switch mode
        if (!$this->deliveryOptions && $this->pickupOptions) {
            $this->mode = Pickup::IDENTIFIER;
            $this->updatedMode($this->mode); // Magic listeners don't run when called internally
            // PickupOptions are passed to child block already during render
        }
    }

    /**
     * @return Quote
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function getQuote(): Quote
    {
        return $this->checkoutSession->getQuote();
    }

    /** Listener for selecting Shipping method
     *
     * @param array|null $method
     * @return bool
     */
    public function onShippingMethodSelected(?array $method = null): bool
    {
        $this->loadShippingOptions($method[\Magento\Quote\Model\Shipping::METHOD] ?? null);
        return $this->show;
    }

    /**
     * @param string|null $method
     * @return bool
     */
    protected function isMonta(?string $method = null): bool
    {
        // Must return something, but Magewire is listening to the property
        return $this->show = $this->shippingHelper->isMonta($method);
    }

    /**
     * @return string[] - Button labels keyed on tab identifier
     */
    public function getTabButtons(): array
    {
        $buttons = [];
        if ($this->hasDeliveryOptions()) {
            // tabId is also the name of the property this corresponds to
            $buttons[Delivery::IDENTIFIER] = __("Delivery");
        }
        if ($this->hasPickupOptions()) {
            $buttons[Pickup::IDENTIFIER] = __("Pickup");
        }
        if ($this->hasStoreLocations()) {
            $buttons[Collect::IDENTIFIER] = __("Collect");
        }
        return $buttons;
    }

    /** Magic listener
     *
     * @param string $mode - new value
     * @return string - Magic methods must always return the updated value
     */
    public function updatedMode(string $mode): string
    {
        $this->emitChildComponent(event: 'activateTab', data: $mode);

        // Return updated property, tabs are displayed accordingly
        return $mode;
    }

    /** Get Timeframes (name for consistency)
     *
     * @return TimeFrame[]
     */
    public function getDeliveryOptions(): array
    {
        // Magewire converts property into array of arrays on rerender
        if (is_array(array_first($this->deliveryOptions))) {
            // Run Timeframe constructor on each array then
            return array_map(
                callback: [TimeFrame::class, 'construct'],
                array: $this->deliveryOptions,
            );
        } else {
            // Otherwise just return the correct array
            return $this->deliveryOptions;
        }
    }

    /** Magic method runs when model changes value in frontend
     * basically 'clickDayTile'
     *
     * @param string|null $date - New value passed by request
     * @return string|null - Updated value
     */
    public function updatingSelectedTimeFrameDate(?string $date = null): ?string
    {
        // Find Timeframe for this date (can be only one)
        $timeframe = array_find(
            array: $this->getDeliveryOptions(),
            callback: fn($option) => $option->getDate() == $date,
        )
            // when it was not found, select the first Timeframe
            ?? array_first($this->getDeliveryOptions());

        if ($timeframe && $timeframe->options) {
            // Update child component options
            $this->emitChildComponent(event: 'updateOptions', childName: 'delivery', data: $timeframe->options);
        }

        // Magic method returns the new value to continue updating property
        return $date;
    }

    /**
     * @return bool
     */
    public function hasDeliveryOptions(): bool
    {
        return (bool)sizeof($this->deliveryOptions);
    }

    /**
     * @return bool
     */
    public function hasPickupOptions(): bool
    {
        return (bool)sizeof($this->pickupOptions);
    }

    /**
     * @return bool
     */
    public function hasStoreLocations(): bool
    {
        return (bool)sizeof($this->storeLocations);
    }

    /** Pass data to child component
     * Caution: If child component does not yet exist, it fails quietly
     *
     * @param string $event - function name on child component
     * @param ?string $childName - Child name as declared in XML (optional: otherwise just emit globally)
     * @param mixed $data
     * @return void
     */
    protected function emitChildComponent(string $event, ?string $childName = null, mixed $data = []): void
    {
        if ($childName) {
            $this->emitTo(
                name: $this->childComponent($childName),
                event: $event,
                params: $data,
            );
        } else {
            // No target, just emit. Let child components listen
            $this->emit(event: $event, params: $data);
        }
    }

    /**
     * @param string $childName
     * @return string
     */
    public function childComponent(string $childName): string
    {
        return 'monta.hyva.shipping.method.component.' . $childName;
    }
}