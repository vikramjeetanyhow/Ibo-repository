<?php

namespace Embitel\ProductImport\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Embitel\ProductImport\Model\Import\ProductFieldProcessor;
use Embitel\ProductImport\Model\ProductName;
use Magento\Framework\App\State as AppState;

class UpdateProducTitles extends Command
{
    const ATTRIBUTE_SET_CODE = 'attriute_set_code';

    /**
     * @var ProductFieldProcessor
     */
    protected $productFieldProcessor;

    /**
     * @var ProductName
     */
    protected $productName;

    /**
     * @var AppState
     */
    protected $appState;

    /**
     * @param ProductFieldProcessor $productFieldProcessor
     * @param ProductName $productName
     * @param AppState $appState
     */
    public function __construct(
        ProductFieldProcessor $productFieldProcessor,
        ProductName $productName,
        AppState $appState
    ) {
        $this->productFieldProcessor = $productFieldProcessor;
        $this->productName = $productName;
        $this->appState = $appState;
        parent::__construct('mycommand');
    }

    protected function configure()
    {
        $options = [
            new InputOption(
                self::ATTRIBUTE_SET_CODE,
                null,
                InputOption::VALUE_REQUIRED,
                'Attribute Set Code'
            )
        ];

        $this->setName('update:product:titles')
            ->setDescription('Update Product Titles For Attribute Set')
            ->setDefinition($options);

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($attributeSetCode = $input->getOption(self::ATTRIBUTE_SET_CODE)) {
            $output->writeln("Title update for:" . $attributeSetCode);
            $this->appState->setAreaCode('adminhtml');
            if ($this->updateProductTiles($attributeSetCode)) {
                $output->writeln("Title update process complete.");
            } else {
                $output->writeln("Please probide valid attribute set.");
            }
        } else {
            $output->writeln("Please probide attribute set.");
        }
        return $this;
    }

    /**
     * Update product titles.
     *
     * @param type $attributeSetCode
     */
    private function updateProductTiles($attributeSetCode)
    {
        $attributeSetId = $this->productFieldProcessor->getAttributeSetId(trim($attributeSetCode));
        if (!$attributeSetId) {
            return false;
        }

        $products = $this->getAttributeSetProducts($attributeSetId);
        if ($products->getSize() == 0) {
            return false;
        }

        $this->productName->updateProductName($products);
        return true;
    }

    /**
     * Get products filter by attribute set
     *
     * @param type $attributeSetId
     */
    private function getAttributeSetProducts($attributeSetId)
    {
        $products = $this->productFieldProcessor->getProductCollection()
            ->addFieldToSelect('*')
            ->addAttributeToFilter('type_id', 'simple')
            ->addAttributeToFilter('attribute_set_id', $attributeSetId);
        return $products;
    }
}
