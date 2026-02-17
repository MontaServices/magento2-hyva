<?php

declare(strict_types=1);

namespace Monta\Hyva\Magewire\Checkout\Shipping;

use GuzzleHttp\Exception\GuzzleException;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magewirephp\Magewire\Component;
use Monta\Checkout\Helper\Shipping;
use Monta\Checkout\Model\Carrier\Monta;
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
    ];

    /**
     * @param Session $checkoutSession
     * @param Shipping $shippingHelper
     * @throws GuzzleException
     * @throws LocalizedException
     * @throws NoSuchEntityException
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
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function mount(): void
    {
        if ($this->isMonta($this->getQuote()->getShippingAddress()->getShippingMethod())) {
            // Retrieve shipping options from same helper
            // TODO clean up duplicate code with MethodConverter, also set selectedTimeframe date from Quote
            $options = $this->shippingHelper->getOptions($this->getQuote());
            if ($options) {
                $this->deliveryOptions = $options[ShippingOption::SHIPPING_OPTIONS_KEY] ?? [];
                $this->pickupOptions = $options[PickupPoint::PICKUP_OPTIONS_KEY] ?? [];
                $this->storeLocation = $options[PickupPoint::PICKUP_STORE_KEY] ?? null;
                $this->standardShipper = $options[ShippingOption::SHIPPING_STANDARD_KEY] ?? null;
            }
        }
    }

    /**
     * @return bool
     */
    public function isFallbackShippper(): bool
    {
        return (sizeof($this->deliveryOptions) == 1 && array_first($this->getDeliveryOptions())->isFallbackShipper());
    }

    /** Hydrate runs after child Magewire components have been created
     *
     * @return void
     */
    public function hydrate(): void
    {
        // Set PickupOptions on child block
        $this->emitChildComponent('pickup', 'updateOptions', $this->pickupOptions);
    }

    /**
     * @return Quote
     * @throws \Magento\Framework\Exception\LocalizedException
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
        return $this->isMonta($method[\Magento\Quote\Model\Shipping::METHOD] ?? null);
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

    /** Switch tab mode
     *
     * @param string $mode
     * @return void
     */

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

        // Update and return property, tabs are shown/hidden accordingly.
        return $this->mode = $mode;
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
     * @param string $date - New value passed by request
     * @return string - Updated value
     */
    public function updatingSelectedTimeFrameDate(string $date): string
    {
        // Find Timeframe for this date (can be only one)
        $timeframe = array_find(
            array: $this->getDeliveryOptions(),
            callback: fn($option) => $option->getDate() == $date,
        );

        // Update child component options
        $this->emitChildComponent('delivery', 'updateOptions', $timeframe->options);

        // Magic method MUST return value to continue updating property
        return $date;
    }

    /** Update options on child component
     *
     * @param string $childName
     * @param string $event
     * @param mixed $data
     * @return void
     */
    protected function emitChildComponent(string $childName, string $event, mixed $data = []): void
    {
        $this->emitTo(
            name: 'monta.hyva.shipping.method.component.' . $childName,
            event: $event,
            params: $data,
        );
    }
}