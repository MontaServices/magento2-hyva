<?php

declare(strict_types=1);

namespace Monta\Hyva\Magewire\Checkout\Shipping;

use GuzzleHttp\Exception\GuzzleException;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magewirephp\Magewire\Component;
use Monta\Checkout\Helper\Shipping;
use Monta\CheckoutApiWrapper\Objects\PickupPoint;
use Monta\CheckoutApiWrapper\Objects\ShippingOption;
use Monta\CheckoutApiWrapper\Objects\TimeFrame;

class Options extends Component
{
    public const string DELIVERY_OPTIONS_IDENTIFIER = "DeliveryOptions";

    public const string PICKUP_OPTIONS_IDENTIFIER = "PickupOptions";

    /** @var TimeFrame[] $deliveryOptions - These are actually the Timeframes */
    public array $deliveryOptions = [];

    /** @var PickupPoint[] */
    public array $pickupOptions = [];

    /** @var ?PickupPoint */
    public ?PickupPoint $storeLocation = null;

    /** @var ShippingOption|array|null $standardShipper */
    public ShippingOption|array|null $standardShipper = null;

    /** @var bool - Whether the block should be visible */
    public bool $show = false;

    /** @var string|null - State property for remembering selected date */
    public ?string $selectedTimeFrameDate = null;

    /** Which tab is active?
     *
     * @var string - Start on Delivery
     */
    public string $mode = self::DELIVERY_OPTIONS_IDENTIFIER;

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
     */
    public function __construct(
        protected readonly Session $checkoutSession,
        protected readonly Shipping $shippingHelper,
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
                    $this->storeLocation = $options[PickupPoint::PICKUP_STORE_KEY] ?? null;
                    $this->standardShipper = $options[ShippingOption::SHIPPING_STANDARD_KEY] ?? null;
                }
            }

            // "click" the selected Timeframe because the magic method only runs on updates
            $this->updatingSelectedTimeFrameDate($this->selectedTimeFrameDate);
        } catch (GuzzleException $exception) {
            // Catch and ignore
        } catch (LocalizedException $exception) {
            // Catch and ignore
        }
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
            $this->mode = self::PICKUP_OPTIONS_IDENTIFIER;
            $this->updatedMode($this->mode); // Magic listeners don't run when called internally
            // PickupOptions are passed to child block already during render
        }
    }

    /**
     * @return Quote
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
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
        if (sizeof($this->deliveryOptions) > 0) {
            // tabId is also the name of the property this corresponds to
            $buttons[self::DELIVERY_OPTIONS_IDENTIFIER] = __("Delivery");
        }
        if (sizeof($this->pickupOptions) > 0) {
            $buttons[self::PICKUP_OPTIONS_IDENTIFIER] = __("Pickup");
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
        // Convert tab identifiers to child component name
        // TODO identify child components by exact identifiers directly
        switch ($mode) {
            case self::PICKUP_OPTIONS_IDENTIFIER:
                $childName = 'pickup';
                break;
            default:
            case self::DELIVERY_OPTIONS_IDENTIFIER:
                $childName = 'delivery';
                // And hide Pickup tab (for some reason not working via JS)
                $this->emitChildComponent('pickup', 'activateTab', false);
                break;
        }
        $this->emitChildComponent($childName, 'activateTab', true);

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
            $this->emitChildComponent('delivery', 'updateOptions', $timeframe->options);
        }

        // Magic method returns the new value to continue updating property
        return $date;
    }

    /** Pass data to child component
     * Caution: If child component does not yet exist, it fails quietly
     *
     * @param string $childName - Child name as declared in XML
     * @param string $event - function name on child component
     * @param mixed $data
     * @return void
     */
    protected function emitChildComponent(string $childName, string $event, mixed $data = []): void
    {
        $this->emitTo(
            name: $this->childComponent($childName),
            event: $event,
            params: $data,
        );
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