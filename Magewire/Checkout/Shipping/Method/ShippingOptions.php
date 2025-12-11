<?php

declare(strict_types=1);

namespace Monta\Hyva\Magewire\Checkout\Shipping\Method;

use Magewirephp\Magewire\Component;

class ShippingOptions extends Component
{
    /**
     * Public state bound to the input field.
     */
    public string $shippingOptions = '';

    /**
     * @var string[]
     */
    protected $listeners = [];
}