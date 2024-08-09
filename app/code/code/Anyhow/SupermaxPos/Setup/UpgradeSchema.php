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

class UpgradeSchema implements \Magento\Framework\Setup\UpgradeSchemaInterface
{
	public function upgrade(\Magento\Framework\Setup\SchemaSetupInterface $setup, \Magento\Framework\Setup\ModuleContextInterface $context)
	{
		$installer = $setup;
        $installer->startSetup();
		
		// Create 'ah_supermax_pos_register' Table
		if (!$installer->tableExists('ah_supermax_pos_register')) {
			$table1 = $installer->getConnection()->newTable(
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
				'close_note',
				\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
				255,
				['nullable' => false],
				'Pos Register Close Note'
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
			$installer->getConnection()->createTable($table1);
		}

		// Create 'ah_supermax_pos_register_transaction' Table
		if (!$installer->tableExists('ah_supermax_pos_register_transaction')) {
			$table2 = $installer->getConnection()->newTable(
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
			$installer->getConnection()->createTable($table2);
		}

		// Create 'ah_supermax_pos_register_transaction_detail' Table
		if (!$installer->tableExists('ah_supermax_pos_register_transaction_detail')) {
			$table3 = $installer->getConnection()->newTable(
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
			$installer->getConnection()->createTable($table3);
		}
		$installer->endSetup();
	}
}