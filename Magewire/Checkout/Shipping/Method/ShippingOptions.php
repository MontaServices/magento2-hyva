<?php

declare(strict_types=1);

namespace Monta\Hyva\Magewire\Checkout\Shipping\Method;

use Magento\Checkout\Model\Session;
use Magento\Quote\Model\Quote;
use Magewirephp\Magewire\Component;
use Monta\Checkout\Model\Carrier\Monta;

class ShippingOptions extends Component
{
    public function __construct(
        protected readonly Session $checkoutSession
    )
    {
    }

    /**
     * Public state bound to the input field.
     */
    public string $shippingOptions = '';

    /**
     * Whether the block should be visible
     */
    public bool $show = false;

    /**
     * @var string[]
     */
    protected $listeners = [
        'shipping_method_selected' => 'onShippingMethodSelected',
    ];

    public function mount(): void
    {
        $this->isMonta($this->getQuote()->getShippingAddress()->getShippingMethod());
    }

    protected function getQuote(): Quote
    {
        return $this->checkoutSession->getQuote();
    }

    /** Listener for selecting Shipping method
     *
     * @param array|null $method
     */
    public function onShippingMethodSelected(array $method = null): void
    {
        $this->show = $this->isMonta($method['method']);
    }

    /**
     * Carrier code and/or method code
     */
    protected function isMonta(string $method = null): bool
    {
        // Must return something, but Magewire is listening to the property
        return $this->show = ($method == Monta::METHOD_CODE || $method == Monta::METHOD_CODE . "_" . Monta::METHOD_CODE);
    }
}