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
use Monta\CheckoutApiWrapper\Objects\Address;
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

    /** @var Address|null $address */
    public ?Address $address = null;

    /** @var bool $showingMap */
    public bool $showingMap = false;

    /** @var bool - Whether the block should be visible */
    public bool $show = false;

    /** @var string|null - State property for remembering selected date */
    public ?string $selectedTimeFrameDate = null;

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

    /**
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function mount(): void
    {
        if ($this->isMonta($this->getQuote()->getShippingAddress()->getShippingMethod())) {
            // Retrieve shipping options from same helper
            // TODO clean up duplicate code with MethodConverter, also set selectedTimeframe date
            $options = $this->shippingHelper->getOptions($this->getQuote());
            if ($options) {
                $this->deliveryOptions = $options[ShippingOption::SHIPPING_OPTIONS_KEY] ?? [];
                $this->setPickups($options[PickupPoint::PICKUP_OPTIONS_KEY] ?? []);
                $this->storeLocation = $options[PickupPoint::PICKUP_STORE_KEY] ?? null;
                $this->standardShipper = $options[ShippingOption::SHIPPING_STANDARD_KEY] ?? null;
            }
        }
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
    public function onShippingMethodSelected(array $method = null): bool
    {
        return $this->isMonta($method[\Magento\Quote\Model\Shipping::METHOD] ?? null);
    }

    /**
     * @param string|null $method
     * @return bool
     */
    protected function isMonta(string $method = null): bool
    {
        // Must return something, but Magewire is listening to the property
        return $this->show = ($method == Monta::METHOD_CODE || $method == Monta::METHOD_CODE . "_" . Monta::METHOD_CODE);
    }

    /**
     * @param array $options
     * @return void
     */
    protected function setPickups(array $options): void
    {
        $this->pickupOptions = $options;
        // Set PickupOptions on child block
        $this->emitTo(
            name: 'monta.hyva.shipping.method.component.pickup',
            event: 'updateOptions',
            params: $this->pickupOptions,
        );
    }

    /**
     * @return array[]
     */
    public function getTabButtons(): array
    {
        return [
            [
                // id is also the name of the property this corresponds to
                "id" => self::DELIVERY_OPTIONS_IDENTIFIER,
                "label" => __("Delivery"),
                'active' => false,
            ],

            [
                "id" => self::PICKUP_OPTIONS_IDENTIFIER,
                "label" => __("Pickup"),
                'active' => false,
            ],
        ];
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
     * @param string $date - New value is passed
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
        $this->emitTo(
            name: 'monta.hyva.shipping.method.component.delivery',
            event: 'updateOptions',
            params: $timeframe->options,
        );
        // TODO focus selection visually
    }
}