<?php
/**
 * @author MageKwik Team <support@magekwik.com>
 * @copyright Copyright (c) 2025 Mage Kwik (https://www.magekwik.com)
 */

namespace Magekwik\AttributeSetLimit\Plugin;

use Magento\Eav\Model\Entity\Attribute\Set;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;

class AttributeSetPlugin
{
    /**
     * @var CollectionFactory
     */
    protected $attributeCollectionFactory;

    const MAX_ATTRIBUTES_PER_SET = 500;

    /**
     * @param CollectionFactory $attributeCollectionFactory
     */
    public function __construct(
        CollectionFactory $attributeCollectionFactory)
    {
        $this->attributeCollectionFactory = $attributeCollectionFactory;
    }

    /**
     * @param Set $subject
     * @param array $data
     * @return array
     * @throws LocalizedException
     */
    public function beforeOrganizeData(
        Set   $subject,
        array $data
    ): array
    {
        $attributeSetId = $subject->getId();
        if ($attributeSetId) {
            $collection = $this->attributeCollectionFactory->create();
            $collection->setAttributeSetFilter($attributeSetId);
            $attributeCount = $collection->getSize();
        }

        if ($attributeCount >= self::MAX_ATTRIBUTES_PER_SET) {
            throw new LocalizedException(
                __('The attribute set has reached the maximum limit of %1 attributes.', self::MAX_ATTRIBUTES_PER_SET)
            );
        }
        return [$subject];
    }
}
