<?php
/**
 * @author Jacco.Amersfoort <jacco.amersfoort@monta.nl>
 * @created 1/7/2026 11:46
 */
namespace Monta\Hyva\Helper;

use Magento\Checkout\Model\Session;
use Magento\Quote\Api\CartRepositoryInterface;

readonly class Quote
{
    /**
     * @param Session $checkoutSession
     * @param CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        protected Session $checkoutSession,
        protected CartRepositoryInterface $quoteRepository,
    )
    {
    }

    /** Set a selected delivery option to Quote
     * Basically what Monta\Checkout\Plugin\Quote\Management::getShippingData did except not via ExtensionAttributes,
     * which was set by
     *
     * To be used by Hyva companion module
     *
     * @param string $optionJson
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function setOption(string $optionJson): void
    {
        $quote = $this->checkoutSession->getQuote();

        if ($quote && $quote->getId()) {
            $address = $quote->getShippingAddress();
            $address->setMontaShippingData($optionJson);

            $this->quoteRepository->save($quote);
        }
    }
}