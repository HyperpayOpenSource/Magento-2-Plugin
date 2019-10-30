<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Hyperpay\Extension\Model\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Authorize.net Payment Action Dropdown source
 */
class PaymentAction implements ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'DB',
                'label' => __('Debit')
            ),
            array(
                'value' => 'PA',
                'label' => __('Pre-Authorization')
            )
        );
    }
}