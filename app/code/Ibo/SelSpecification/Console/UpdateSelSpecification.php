<?php

namespace Ibo\SelSpecification\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ProductFactory;
use Ibo\SelSpecification\Model\ProductSelSpecification;
use Magento\Framework\App\State as AppState;

class UpdateSelSpecification extends Command
{
    const CATEGORY_ID = 'ibo_category_id';

    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var CategoryFactory
     */
    protected $categoryFactory;

    /**
     * @var ProductSelSpecification
     */
    protected $productSelSpecification;

    /**
     * @var AppState
     */
    protected $appState;

    /**
     * @param ProductFactory $productFactory
     * @param CategoryFactory $categoryFactory
     * @param ProductSelSpecification $productSelSpecification
     * @param AppState $appState
     */
    public function __construct(
        ProductFactory $productFactory,
        CategoryFactory $categoryFactory,
        ProductSelSpecification $productSelSpecification,
        AppState $appState
    ) {
        $this->productFactory = $productFactory;
        $this->categoryFactory = $categoryFactory;
        $this->productSelSpecification = $productSelSpecification;
        $this->appState = $appState;
        parent::__construct('mycommand');
    }

    protected function configure()
    {
        $options = [
            new InputOption(
                self::CATEGORY_ID,
                null,
                InputOption::VALUE_REQUIRED,
                'IBO Category ID'
            )
        ];

        $this->setName('update:product:selspecification')
            ->setDescription('Update Product SEL Specifications')
            ->setDefinition($options);

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $categoryIdParam = $input->getOption(self::CATEGORY_ID);
        $categoryIds = trim($categoryIdParam);
        if ($categoryIds) {
            $this->appState->setAreaCode('adminhtml');
            $output->writeln("Process start:");
            foreach (explode(",", $categoryIds) as $categoryId) {
                $categoryIdNew = trim($categoryId);
                $output->writeln("Category ID:" . $categoryIdNew);
                $response = $this->productSelSpecification->setSelSpecification($categoryId);
                $output->writeln($response);
            }
        } else {
            $output->writeln("Please probide IBO Category ID.");
        }
        return $this;
    }
}
