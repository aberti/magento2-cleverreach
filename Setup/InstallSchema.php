<?php
/**
 * @package     Logeecom_CleverReach
 * @author      CleverReach
 * @copyright   2017 CleverReach
 */

namespace Logeecom\CleverReach\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * @var \Magento\Framework\Setup\SchemaSetupInterface
     */
    private $installer;

    /**
     * @var \Magento\Framework\Setup\ModuleContextInterface
     */
    private $context;

    /**
     * @param SchemaSetupInterface   $setup
     * @param ModuleContextInterface $context
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $this->installer = $setup->startSetup();
        $this->context = $context;

        $tableName = $this->installer->getTable('cleverreach_config');

        if ($this->installer->getConnection()->isTableExists($tableName) != true) {
            $table = $this->installer->getConnection()
                ->newTable($this->installer->getTable('cleverreach_config'))
                ->addColumn(
                    'id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                    null,
                    ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                    'Id'
                )
                ->addColumn(
                    'path',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    255,
                    ['default' => null],
                    'Path'
                )
                ->addColumn(
                    'value',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    null,
                    ['default' => null],
                    'Value'
                )
                ->addIndex(
                    $this->installer->getIdxName('cleverreach_path', ['path']),
                    ['path']
                );

            $this->installer->getConnection()->createTable($table);
            $this->installer->endSetup();
        }
    }
}
