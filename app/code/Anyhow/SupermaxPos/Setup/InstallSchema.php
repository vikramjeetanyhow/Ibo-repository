<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Setup;

class InstallSchema implements \Magento\Framework\Setup\InstallSchemaInterface
{
	public function install(\Magento\Framework\Setup\SchemaSetupInterface $setup, \Magento\Framework\Setup\ModuleContextInterface $context)
	{
		$installer = $setup;
        $installer->startSetup();
		
		// Alter 'catalog_product_entity' Table to add columns 'barcode' & 'barcode_type'
		if ($installer->tableExists('catalog_product_entity')) {
			$tableName = $installer->getTable('catalog_product_entity');
			$columns = [
						'barcode' => [
									'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
									'nullable' => true,
									'comment' => 'Barcode',
								],
						'barcode_type' => [
									'type' => \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
									'nullable' => true,
									'comment' => 'Barcode Type',
								],
					];
			foreach ($columns as $name => $definition) {
				$installer->getConnection()->addColumn($tableName, $name, $definition);
			}
		}

		// Alter 'sales_order_item' Table to add columns 'cost'.
		if ($installer->tableExists('sales_order_item')) {
			$orderItemTableName = $installer->getTable('sales_order_item');
			$columns = [
				'cost' => [
					'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
					'nullable' => true,
					'comment' => 'Product Cost',
				],
			];
			foreach ($columns as $name => $definition) {
				$installer->getConnection()->addColumn($orderItemTableName, $name, $definition);
			}
		}
		 
		// Create 'ah_supermax_pos_user' Table
		if (!$installer->tableExists('ah_supermax_pos_user')) {
			$table1 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_user')
			)
			->addColumn(
				'pos_user_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'User ID'
			)
			->addColumn(
				'username',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'User Name'
			)
			->addColumn(
				'password',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Password'
			)
			->addColumn(
				'firstname',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => false],
				'First Name'
			)
			->addColumn(
				'lastname',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => false],
				'Last Name'
			)
			->addColumn(
				'email',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => false],
				'Email'
			)
			->addColumn(
				'phone',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => false],
				'Phone'
			)
			->addColumn(
				'status',
				\Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
				1,
				['nullable' => false],
				'Status'
			)
			->addColumn(
				'pos_outlet_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Outlet Id'
			)
			->addColumn(
				'store_view_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Store View Id'
			)
			->addColumn(
				'pos_user_role_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'User Role Id'
			)
			->addColumn(
				'password_reset_date',
				\Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
				null,
				['nullable' => true],
				'Last Password Reset'
			)
			->setComment('Supermax Pos User Table');
			$installer->getConnection()->createTable($table1);
		}
		 
		// Create 'ah_supermax_pos_api' Table
		if (!$installer->tableExists('ah_supermax_pos_api')) {
			$table2 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_api')
			)
			->addColumn(
				'pos_api_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'Pos Api Id'
			)
			->addColumn(
				'pos_user_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'User Id'
			)
			->addColumn(
				'token',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => false],
				'Token'
			)
			->addColumn(
				'expire',
				\Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
				null,
				['nullable' => false],
				'Token Expire'
			)
			->setComment('Supermax Pos Api Table');
			$installer->getConnection()->createTable($table2);
		}
		
		// Create 'ah_supermax_pos_connection' Table
		if (!$installer->tableExists('ah_supermax_pos_connection')) {
			$table3 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_connection')
			)
			->addColumn(
				'pos_connection_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'Pos Connection Id'
			)
			->addColumn(
				'connection_code',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => false],
				'Connection Code'
			)
			->addColumn(
				'connection_date',
				\Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
				null,
				['nullable' => false],
				'Connection Date'
			)
			->setComment('Supermax Pos Connection Table');
			$installer->getConnection()->createTable($table3);
		}

		// Create 'ah_supermax_pos_outlet' Table
		if (!$installer->tableExists('ah_supermax_pos_outlet')) {
			$table4 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_outlet')
			)
			->addColumn(
				'pos_outlet_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'Pos Outlet Id'
			)
			->addColumn(
				'outlet_name',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => false],
				'Outlet Name'
			)
			->addColumn(
				'email',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => false],
				'Email'
			)
			->addColumn(
				'status',
				\Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
				1,
				['nullable' => false],
				'Status'
			)
			->addColumn(
				'outlet_address_type',
				\Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
				1,
				['nullable' => false],
				'Outlet Address Type'
			)
			->addColumn(
				'source_code',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => false],
				'Source Code'
			)
			->addColumn(
				'pos_receipt_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Receipt Id'
			)
			->addColumn(
				'product_assignment_basis',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => false],
				'Product Assignment Basis'
			)
			->addColumn(
				'inventory_node',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Inventory Node'
			)
			->addColumn(
				'store_wh_node',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Store WH Node'
			)
			->addColumn(
				'store_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Store Id'
			)
			->addColumn(
				'allowed_ips',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				500,
				['nullable' => false],
				'Allowed Ips'
			)
			->addColumn(
				'receipt_thermal_status',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Receipt Thermal'
			)
			->addColumn(
				'online_payment_popup_status',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Online Payment Popup Status'
			)
			->addColumn(
				'display_payments',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Display Payment'
			)
			->addColumn(
				'multi_lot_status',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Multi-Lot / Multi-MRP Handling Status'
			)
			// ->addColumn(
			// 	'multi_lot_info_api_url',
			// 	\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
			// 	255,
			// 	['nullable' => false],
			// 	'Get Product Lot Info API Url'
			// )			
			->setComment('Supermax Pos Outlet Table');
			$installer->getConnection()->createTable($table4);
		}

		// Create 'ah_supermax_pos_outlet_address' Table
		if (!$installer->tableExists('ah_supermax_pos_outlet_address')) {
			$table5 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_outlet_address')
			)
			->addColumn(
				'pos_outlet_address_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'Outlet Address Id'
			)
			->addColumn(
				'parent_outlet_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Outlet Id'
			)
			->addColumn(
				'firstname',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => false],
				'First Name'
			)
			->addColumn(
				'lastname',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => false],
				'Last Name'
			)
			->addColumn(
				'company',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => true],
				'Company'
			)
			->addColumn(
				'street',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Street'
			)
			->addColumn(
				'city',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => false],
				'City'
			)
			->addColumn(
				'country_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => false],
				'Country Id'
			)
			->addColumn(
				'region',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => true],
				'region'
			)
			->addColumn(
				'region_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => true],
				'region_id'
			)
			->addColumn(
				'postcode',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => false],
				'Postcode'
			)
			->addColumn(
				'telephone',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => true],
				'Telephone'
			)
			->addColumn(
				'pan_no',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				20,
				['nullable' => true],
				'Outlet PAN No'
			)
			->addColumn(
				'gstin',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				200,
				['nullable' => true],
				'Outlet GSTIN'
			)
			->setComment('Supermax Pos Outlet Address Table');
			$installer->getConnection()->createTable($table5);
		}

		// Create 'ah_supermax_pos_receipt' Table
		if (!$installer->tableExists('ah_supermax_pos_receipt')) {
			$table6 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_receipt')
			)
			->addColumn(
				'pos_receipt_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'Pos Receipt Id'
			)
			->addColumn(
				'header_logo',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => true],
				'Header Logo Image'
			)
			->addColumn(
				'header_logo_path',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => true],
				'Header Logo Image Path'
			)
			->addColumn(
				'width',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => true],
				'Receipt Width'
			)
			->addColumn(
				'barcode_width',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => true],
				'Receipt Barcode Width'
			)
			->addColumn(
				'font_size',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => true],
				'Receipt Font Size'
			)
			->setComment('Supermax Pos Receipt Table');
			$installer->getConnection()->createTable($table6);
		}

		// Create 'ah_supermax_pos_receipt_store' Table
		if (!$installer->tableExists('ah_supermax_pos_receipt_store')) {
			$table14 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_receipt_store')
			)
			->addColumn(
				'id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'Id'
			)
			->addColumn(
				'receipt_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => true],
				'Pos Receipt Id'
			)
			->addColumn(
				'store_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => true],
				'Store Id'
			)
			->addColumn(
				'title',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => false],
				'Receipt Title'
			)
			->addColumn(
				'header_details',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				500,
				['nullable' => true],
				'Receipt Header Details'
			)
			->addColumn(
				'footer_details',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				500,
				['nullable' => true],
				'Receipt Footer Details'
			)
			->addColumn(
				'seller_bank_info',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => true],
				'Seller Bank Details'
			)
			->addColumn(
				'disclaimer',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				500,
				['nullable' => true],
				'Disclaimer'
			)
			->setComment('Supermax Pos Receipt Store Data Table');
			$installer->getConnection()->createTable($table14);
		}

		// Create 'ah_supermax_pos_orders' Table
		if (!$installer->tableExists('ah_supermax_pos_orders')) {
			$table7 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_orders')
			)
			->addColumn(
				'pos_order_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'Pos Order Id'
			)
			->addColumn(
				'order_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Order Id'
			)
			->addColumn(
				'pos_user_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos User Id'
			)
			->addColumn(
				'pos_outlet_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos Outlet Id'
			)
			->addColumn(
				'pos_terminal_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos Terminal Id'
			)
			->addColumn(
				'barcode',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Barcode'
			)
			->addColumn(
				'payment_code',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				40,
				['nullable' => false],
				'Payment Code'
			)
			->addColumn(
				'payment_method',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Payment Method Title'
			)
			->addColumn(
				'payment_intent_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Payment Intent Id'
			)
			->addColumn(
				'device_type',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => false],
				'Device Type'
			)
			->addColumn(
				'payment_device_mode',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => false],
				'Payment Device Mode'
			)
			->addColumn(
				'payment_data',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				900,
				['nullable' => false],
				'Payment Data'
			)
			->addColumn(
				'sales_associate_1',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Sales Associate 1'
			)
			->addColumn(
				'sales_associate_2',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Sales Associate 2'
			)
			->addColumn(
				'additional_data',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				900,
				['nullable' => false],
				'Additional Data'
			)
			->setComment('Supermax Pos Order Table');
			$installer->getConnection()->createTable($table7);
		}

		// Create 'ah_supermax_pos_connection_update' Table
		if (!$installer->tableExists('ah_supermax_pos_connection_update')) {
			$table8 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_connection_update')
			)
			->addColumn(
				'pos_connection_update_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'Pos Connection Update Id'
			)
			->addColumn(
				'pos_connection_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos Connection Id'
			)
			->addColumn(
				'code',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => false],
				'Code'
			)
			->addColumn(
				'update',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => true],
				'Update'
			)
			->addColumn(
				'date_added',
				\Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
				null,
				['nullable' => false],
				'Date Added'
			)
			->setComment('Supermax Pos Connection Update Table');
			$installer->getConnection()->createTable($table8);
		}

		// Create 'ah_supermax_pos_register' Table
		if (!$installer->tableExists('ah_supermax_pos_register')) {
			$table9 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_register')
			)
			->addColumn(
				'pos_register_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'Pos Register Id'
			)
			->addColumn(
				'name',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => false],
				'Pos Register Name'
			)
			->addColumn(
				'pos_user_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos User Id'
			)
			->addColumn(
				'status',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Register Status'
			)
			->addColumn(
				'reconciliation_status',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Reconciliation Status'
			)
			->addColumn(
				'close_note',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Pos Register Close Note'
			)
			->addColumn(
				'head_cashier_cash_total',
				\Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
				'15,4',
				['nullable' => false],
				'Head Cashier Cash Total'
			)
			->addColumn(
				'head_cashier_card_total',
				\Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
				'15,4',
				['nullable' => false],
				'Head Cashier Card Total'
			)
			->addColumn(
				'head_cashier_custom_total',
				\Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
				'15,4',
				['nullable' => false],
				'Head Cashier Custom Total'
			)
			->addColumn(
				'head_cashier_emi_total',
				\Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
				'15,4',
				['nullable' => false],
				'Head Cashier EMI Total'
			)->addColumn(
				'head_cashier_bank_deposit_total',
				\Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
				'15,4',
				['nullable' => false],
				'Head Bank Deposit Total'
			)
			->addColumn(
				'head_cashier_pod_total',
				\Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
				'15,4',
				['nullable' => false],
				'Head Cashier Pod Total'
			)
			->addColumn(
				'head_cashier_wallet_total',
				\Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
				'15,4',
				['nullable' => false],
				'Head Cashier Pod Total'
			)
			->addColumn(
				'head_cashier_pay_later_total',
				\Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
				'15,4',
				['nullable' => false],
				'Head Bank Deposit Total'
			)
			->addColumn(
				'head_cashier_close_note',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Head Cashier Close Note'
			)
			->addColumn(
				'date_open',
				\Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
				null,
				['nullable' => true],
				'Date Open'
			)
			->addColumn(
				'date_close',
				\Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
				null,
				['nullable' => true],
				'Date Close'
			)			
			->setComment('Supermax Pos Register Table');
			$installer->getConnection()->createTable($table9);
		}

		// Create 'ah_supermax_pos_register_transaction' Table
		if (!$installer->tableExists('ah_supermax_pos_register_transaction')) {
			$table10 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_register_transaction')
			)
			->addColumn(
				'pos_register_transaction_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'Pos Register Transaction Id'
			)
			->addColumn(
				'pos_register_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos Register Id'
			)
			->addColumn(
				'code',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => false],
				'Code'
			)
			->addColumn(
				'expected',
				\Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
				'15,4',
				['nullable' => false],
				'Expected Amount'
			)
			->addColumn(
				'total',
				\Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
				'15,4',
				['nullable' => false],
				'Total Amount'
			)
			->addColumn(
				'difference',
				\Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
				'15,4',
				['nullable' => false],
				'Difference Amount'
			)
			->setComment('Supermax Pos Register Transaction Table');
			$installer->getConnection()->createTable($table10);
		}

		// Create 'ah_supermax_pos_register_transaction_detail' Table
		if (!$installer->tableExists('ah_supermax_pos_register_transaction_detail')) {
			$table11 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_register_transaction_detail')
			)
			->addColumn(
				'pos_register_transaction_detail_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'Pos Register Transaction Detail Id'
			)
			->addColumn(
				'pos_register_transaction_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos Register Transaction Id'
			)
			->addColumn(
				'pos_register_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos Register Id'
			)
			->addColumn(
				'code',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => false],
				'Code'
			)
			->addColumn(
				'title',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Title'
			)
			->addColumn(
				'description',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Description'
			)
			->addColumn(
				'amount',
				\Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
				'15,4',
				['nullable' => false],
				'Amount'
			)
			->addColumn(
				'date_added',
				\Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
				null,
				['nullable' => false],
				'Date Added'
			)
			->setComment('Supermax Pos Register Transaction Details Table');
			$installer->getConnection()->createTable($table11);
		}

		// Create 'ah_supermax_pos_customer' Table
		if (!$installer->tableExists('ah_supermax_pos_customer')) {
			$table12 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_customer')
			)
			->addColumn(
				'pos_customer_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'Pos Customer Id'
			)
			->addColumn(
				'customer_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Customer Id'
			)
			->addColumn(
				'pos_user_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos User Id'
			)
			->addColumn(
				'pos_outlet_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos Outlet Id'
			)
			->addColumn(
				'customer_referral_title',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => true],
				'Customer referral Title'
			)
			->addColumn(
				'customer_referral_Phone',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => true],
				'Customer referral Phone'
			)
			->setComment('Supermax Pos Customer Table');
			$installer->getConnection()->createTable($table12);
		}

		// Create 'ah_supermax_pos_report' Table
		if (!$installer->tableExists('ah_supermax_pos_report')) {
			$table13 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_report')
			)
			->addColumn(
				'pos_report_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'Pos Report Id'
			)
			->addColumn(
				'period',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				11,
				['nullable' => true],
				'Period'
			)
			->addColumn(
				'from',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => true],
				'From Date'
			)
			->addColumn(
				'to',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => true],
				'To Date'
			)
			->addColumn(
				'pos_outlet_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				250,
				['nullable' => true],
				'Pos Outlet Id'
			)
			->addColumn(
				'pos_user_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => true],
				'Pos User Id'
			)
			->addColumn(
				'pos_register_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => true],
				'Pos Register Id'
			)
			->addColumn(
				'pos_approver_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => true],
				'Pos Approver Id'
			)
			->addColumn(
				'type',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => true],
				'Type'
			)
			->addColumn(
				'status',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => true],
				'Status'
			)
			->addColumn(
				'payment_method',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => true],
				'Payment Method'
			)
			->addColumn(
				'override_permission',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => true],
				'Override Permission'
			)
			->addColumn(
				'filter',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => true],
				'Filter'
			)
			->addColumn(
				'new_old',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => true],
				'New Old Report'
			)
			->setComment('Supermax Pos Report Table');
			$installer->getConnection()->createTable($table13);
		}

		// Create 'ah_supermax_pos_product_to_outlet' Table
		if (!$installer->tableExists('ah_supermax_pos_product_to_outlet')) {
			$table15 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_product_to_outlet')
			)
			->addColumn(
				'id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'Pos Outlet Product Id'
			)
			->addColumn(
				'parent_outlet_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos Outlet Id'
			)
			->addColumn(
				'product_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos Product Id'
			)
			->setComment('Supermax Pos Outlet Product Table');
			$installer->getConnection()->createTable($table15);
		}

		// Create 'ah_supermax_pos_category_to_outlet' Table
		if (!$installer->tableExists('ah_supermax_pos_category_to_outlet')) {
			$table16 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_category_to_outlet')
			)
			->addColumn(
				'id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'Pos Outlet Product Id'
			)
			->addColumn(
				'parent_outlet_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos Outlet Id'
			)
			->addColumn(
				'category_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos Category Id'
			)
			->setComment('Supermax Pos Outlet Caregory Table');
			$installer->getConnection()->createTable($table16);
		}

		// Create 'ah_supermax_pos_user_role' Table
		if (!$installer->tableExists('ah_supermax_pos_user_role')) {
			$table17 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_user_role')
			)
			->addColumn(
				'pos_user_role_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'User Role ID'
			)
			->addColumn(
				'title',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'User Role Title'
			)
			->addColumn(
				'access_permission',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Access Permission'
			)
			->addColumn(
				'status',
				\Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
				1,
				['nullable' => false],
				'Status'
			)
			->setComment('Supermax Pos User Role Table');
			$installer->getConnection()->createTable($table17);
		}

		// Create 'ah_supermax_pos_price_reductions' Table
		if (!$installer->tableExists('ah_supermax_pos_price_reductions')) {
			$table18 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_price_reductions')
			)
			->addColumn(
				'pos_price_reduction_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'Price Reduction ID'
			)
			->addColumn(
				'title',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Price Reduction Title'
			)
			->addColumn(
				'max_capacity',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Maximum Capacity'
			)
			->addColumn(
				'override_type',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Override Type'
			)
			->addColumn(
				'status',
				\Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
				1,
				['nullable' => false],
				'Status'
			)
			->setComment('Supermax Pos Price Reduction Table');
			$installer->getConnection()->createTable($table18);
		}

		// Create 'ah_supermax_pos_terminals' Table
		if (!$installer->tableExists('ah_supermax_pos_terminals')) {
			$table19 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_terminals')
			)
			->addColumn(
				'pos_terminal_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'Pos Terminal ID'
			)
			->addColumn(
				'pos_outlet_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos Outlet ID'
			)
			->addColumn(
				'code',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Terminal Code'
			)
			->addColumn(
				'title',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Terminal Title'
			)
			->addColumn(
				'edc_type',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => false],
				'EDC Device Type'
			)
			->addColumn(
				'edc_serial_no',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'EDC Serial Number'
			)
			->addColumn(
				'pos_serial_no',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Pos Serial Number'
			)
			->addColumn(
				'ezetap_app_key',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable'=>false],
				'Ezetap App Key'
			)
			->addColumn(
				'ezetap_username',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'ezetap_username'
			)
			->addColumn(
				'ezetap_device_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'ezetap_device_id'
			)
			->addColumn(
				'pinelabs_device_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'pinelabs_device_id'
			)
			->addColumn(
				'pinelabs_allowed_mops',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'pinelabs_allowed_mops'
			)
			->addColumn(
				'pinelabs_merchant_pos_code',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable'=>false],
				'Pinelabs Merchant Pos Code'
			)
			->addColumn(
				'status',
				\Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
				1,
				['nullable' => false],
				'Status'
			)
			->addColumn(
				'receipt_thermal_status',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Receipt Thermal'
			)			
			->setComment('Supermax Pos Terminal Table');
			$installer->getConnection()->createTable($table19);
		}

		// Create 'ah_supermax_pos_payment_ezetap' Table
		if (!$installer->tableExists('ah_supermax_pos_payment_ezetap')) {
			$table20 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_payment_ezetap')
			)
			->addColumn(
				'pos_ezetap_payment_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'Pos Ezetap Payment ID'
			)
			->addColumn(
				'request_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Request Id'
			)
			->addColumn(
				'pos_outlet_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos Outlet ID'
			)
			->addColumn(
				'pos_user_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos User ID'
			)
			->addColumn(
				'order_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Order ID'
			)
			->addColumn(
				'status_check_info',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				900,
				['nullable' => false],
				'Check Transaction Status Response'
			)
			->addColumn(
				'cancel_info',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				900,
				['nullable' => false],
				'Transaction Cancel Response'
			)
			->addColumn(
				'date_added',
				\Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
				null,
				['nullable' => false],
				'Date Added'
			)

			->addColumn(
				'date_modified',
				\Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
				null,
				['nullable' => false],
				'Date Modified'
			)
			->setComment('Supermax Pos Ezetap Payment Table');
			$installer->getConnection()->createTable($table20);
		}

		// Create 'ah_supermax_pos_user_overrides' Table
		if (!$installer->tableExists('ah_supermax_pos_user_overrides')) {
			$table21 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_user_overrides')
			)
			->addColumn(
				'pos_user_override_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'Pos User Override ID'
			)
			->addColumn(
				'pos_user_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos User Id'
			)
			->addColumn(
				'pos_outlet_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos Outlet ID'
			)
			->addColumn(
				'customer_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos Customer ID'
			)
			->addColumn(
				'customer_group_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Customer Group ID'
			)
			->addColumn(
				'order_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Order ID'
			)
			->addColumn(
				'date_added',
				\Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
				null,
				['nullable' => false],
				'Date Added'
			)
			->setComment('Supermax Pos User Overrides Table');
			$installer->getConnection()->createTable($table21);
		}

		// Create 'ah_supermax_pos_user_override_details' Table
		if (!$installer->tableExists('ah_supermax_pos_user_override_details')) {
			$table22 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_user_override_details')
			)
			->addColumn(
				'pos_user_override_detail_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'Pos User Override Detail ID'
			)
			->addColumn(
				'parent_pos_user_override_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos User Override ID'
			)
			->addColumn(
				'approver_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos Approver ID'
			)
			->addColumn(
				'product_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Product ID'
			)
			->addColumn(
				'sku',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Product SKU'
			)
			->addColumn(
				'name',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Product Name'
			)
			->addColumn(
				'pos_price_reduction_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Price Override ID'
			)
			->addColumn(
				'price_reduction_title',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Price Override Title'
			)
			->addColumn(
				'original_price',
				\Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
				'15,2',
				['nullable' => false],
				'Product Original Price'
			)
			->addColumn(
				'overrided_price',
				\Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
				'15,2',
				['nullable' => false],
				'Product Overrided Price'
			)
			->addColumn(
				'discount',
				\Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
				'15,2',
				['nullable' => false],
				'Discount'
			)
			->addColumn(
				'discount_type',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Discount Type'
			)
			->addColumn(
				'permission_type',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Permission Type'
			)
			->addColumn(
				'overrided_delivery_price',
				\Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
				'15,2',
				['nullable' => false],
				'Delivery Overrided Price'
			)
			->addColumn(
				'original_delivery_price',
				\Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
				'15,2',
				['nullable' => false],
				'Delivery Original Price'
			)
			->setComment('Supermax Pos User Override Details Table');
			$installer->getConnection()->createTable($table22);
		}

		// Create 'ah_supermax_pos_user_login_history' Table
		if (!$installer->tableExists('ah_supermax_pos_user_login_history')) {
			$table23 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_user_login_history')
			)
			->addColumn(
				'pos_user_login_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'Pos User Login ID'
			)
			->addColumn(
				'pos_user_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos User ID'
			)
			->addColumn(
				'pos_terminal_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos Terminal ID'
			)
			->addColumn(
				'status',
				\Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
				1,
				['nullable' => false],
				'Status'
			)
			->addColumn(
				'login_time',
				\Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
				null,
				['nullable' => false],
				'Login time'
			)
			->addColumn(
				'logout_time',
				\Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
				null,
				['nullable' => false],
				'Logout Time'
			)
			->setComment('Supermax Pos User Login History Table');
			$installer->getConnection()->createTable($table23);
		}

		// Create 'ah_supermax_pos_payment_pinelabs' Table
		if (!$installer->tableExists('ah_supermax_pos_payment_pinelabs')) {
			$table24 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_payment_pinelabs')
			)
			->addColumn(
				'pos_pinelabs_payment_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'Pos Pinelabs Payment ID'
			)
			->addColumn(
				'request_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Request Id'
			)
			->addColumn(
				'pos_outlet_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos Outlet ID'
			)
			->addColumn(
				'pos_user_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos User ID'
			)
			->addColumn(
				'order_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Order ID'
			)
			->addColumn(
				'status_check_info',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				900,
				['nullable' => false],
				'Check Transaction Status Response'
			)
			->addColumn(
				'cancel_info',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				900,
				['nullable' => false],
				'Transaction Cancel Response'
			)
			->addColumn(
				'date_added',
				\Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
				null,
				['nullable' => false],
				'Date Added'
			)
			->addColumn(
				'date_modified',
				\Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
				null,
				['nullable' => false],
				'Date Modified'
			)
			->setComment('Supermax Pos Pinelabs Payment Table');
			$installer->getConnection()->createTable($table24);
		}

		// Create 'ah_supermax_pos_payment_detail' Table
		if (!$installer->tableExists('ah_supermax_pos_payment_detail')) {
			$table24 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_payment_detail')
			)
			->addColumn(
				'pos_payment_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'Pos Payment Id'
			)
			->addColumn(
				'pos_order_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'POs Order Id'
			)
			->addColumn(
				'payment_method',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Payment Method'
			)
			->addColumn(
				'payment_code',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Payment Code'
			)			
			->addColumn(
				'amount',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Amount'
			)
			->addColumn(
				'payment_intent_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => true],
				'Payment Intent Id'
			)
			->addColumn(
				'amount_formatted',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				11,
				['nullable' => true],
				'Amount Formatted'
			)
			->addColumn(
				'cash_paid',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => true],
				'Cash Paid'
			)
			->addColumn(
				'cash_change',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => true],
				'Cash Change'
			)			
			->setComment('Supermax Pos Payment Details Table');
			$installer->getConnection()->createTable($table25);
		}

		// Create 'ah_supermax_pos_quote' Table
		if (!$installer->tableExists('ah_supermax_pos_quote')) {
			$table26 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_quote')
			)
			->addColumn(
				'pos_quote_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'Pos Quote Id'
			)
			->addColumn(
				'quote_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Quote Id'
			)
			->addColumn(
				'pos_outlet_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos Outlet Id'
			)			
			->addColumn(
				'pos_user_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Pos User Id'
			)
			->addColumn(
				'type',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				100,
				['nullable' => true],
				'Type'
			)
			->addColumn(
				'comment',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => true],
				'Comment'
			)
			->addColumn(
				'status',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				['nullable' => false],
				'Status'
			)
			->addColumn(
				'date_added',
				\Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
				null,
				['nullable' => false],
				'Date Added'
			)
			->addColumn(
				'date_modified',
				\Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
				null,
				['nullable' => false],
				'Date Modified'
			)		
			->setComment('Supermax Pos Quote Table');
			$installer->getConnection()->createTable($table26);
		}

		// Create 'ah_supermax_pos_customer_referral' Table
		if (!$installer->tableExists('ah_supermax_pos_customer_referral')) {
			$table27 = $installer->getConnection()->newTable(
				$installer->getTable('ah_supermax_pos_customer_referral')
			)
			->addColumn(
				'pos_referral_id',
				\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
				11,
				[
					'identity' => true,
					'nullable' => false,
					'primary'  => true,
				],
				'Pos Customer Referral ID'
			)			
			->addColumn(
				'referral_title',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => true],
				'Customer Referral Title'
			)
			// ->addColumn(
			// 	'referral_code',
			// 	\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
			// 	100,
			// 	['nullable' => true],
			// 	'Customer Referral Code'
			// )
			->addColumn(
				'status',
				\Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
				1,
				['nullable' => false],
				'Status'
			)
			->setComment('Supermax Pos Customer Referral Table');
			$installer->getConnection()->createTable($table27);
		}



		$installer->endSetup();
	}
}