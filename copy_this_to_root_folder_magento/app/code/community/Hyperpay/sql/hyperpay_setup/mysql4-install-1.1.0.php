<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @package     Hyperpay
 * @copyright   Copyright (c) 2014 HYPERPAY
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/* @var $installer Mage_Core_Model_Resource_Setup */

$installer = $this;
$installer->startSetup();
$installer->run("
   INSERT INTO  `{$this->getTable('sales/order_status')}` (
        `status` ,
        `label`
    ) SELECT 'payment_accepted','Payment Accepted' FROM dual WHERE NOT EXISTS (SELECT * FROM `{$this->getTable('sales/order_status')}` WHERE `status` = 'payment_accepted' AND `label` = 'Payment Accepted');	
   INSERT INTO  `{$this->getTable('sales/order_status')}` (
        `status` ,
        `label`
    ) SELECT 'payment_pa','Pre-Authorization of Payment' FROM dual WHERE NOT EXISTS (SELECT * FROM `{$this->getTable('sales/order_status')}` WHERE `status` = 'payment_pa' AND `label` = 'Pre-Authorization of Payment');	
   INSERT INTO  `{$this->getTable('sales/order_status_state')}` (
        `status` ,
        `state` ,
        `is_default`
    ) SELECT 'payment_accepted','processing','1' FROM dual WHERE NOT EXISTS (SELECT * FROM `{$this->getTable('sales/order_status_state')}` WHERE `status` = 'payment_accepted' AND `state` = 'processing');	
   INSERT INTO  `{$this->getTable('sales/order_status_state')}` (
        `status` ,
        `state` ,
        `is_default`
    ) SELECT 'payment_pa','new','1' FROM dual WHERE NOT EXISTS (SELECT * FROM `{$this->getTable('sales/order_status_state')}` WHERE `status` = 'payment_pa' AND `state` = 'new');	
");
$installer->endSetup();