<?php
/**
  * @author MageKwik Team <support@magekwik.com>
  * @copyright Copyright (c) 2025 Mage Kwik (https://www.magekwik.com)
 */
namespace Magekwik\AttributeSetLimit\Plugin;

use Magento\Eav\Api\AttributeManagementInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class AttributeManagementPlugin
{
    const MAX_ATTRIBUTES_PER_SET = 500;

    /**
     * @var AttributeRepositoryInterface
     */
    private $attributeRepository;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @param AttributeRepositoryInterface $attributeRepository
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        AttributeRepositoryInterface $attributeRepository,
        ResourceConnection $resourceConnection
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @param AttributeManagementInterface $subject
     * @param string $entityTypeCode
     * @param int $attributeSetId
     * @param int $attributeGroupId
     * @param string $attributeCode
     * @param int $sortOrder
     * @return array
     * @throws LocalizedException
     */
    public function beforeAssign(
        AttributeManagementInterface $subject,
        string $entityTypeCode,
        int $attributeSetId,
        int $attributeGroupId,
        string $attributeCode,
        int $sortOrder
    ): array {

        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/test.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info("Working");

        // Limit enforcement only for product attributes
        if ($entityTypeCode !== 'catalog_product') {
            return [$entityTypeCode, $attributeSetId, $attributeGroupId, $attributeCode, $sortOrder];
        }

        // Get attribute ID (handles both code and ID input)
        $attribute = $this->attributeRepository->get($entityTypeCode, $attributeCode);
        $attributeId = (int) $attribute->getAttributeId();

        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('eav_entity_attribute');

        // Check if the attribute is already assigned to this set
        $select = $connection->select()
            ->from($tableName, 'entity_attribute_id')
            ->where('attribute_set_id = ?', $attributeSetId)
            ->where('attribute_id = ?', $attributeId);
        $existingId = $connection->fetchOne($select);
        $logger->info($existingId);

        if ($existingId) {
            // Already assigned (possibly moving groups), allow
            return [$entityTypeCode, $attributeSetId, $attributeGroupId, $attributeCode, $sortOrder];
        }

        // Count current attributes in the set
        $countSelect = $connection->select()
            ->from($tableName, new \Zend_Db_Expr('COUNT(*)'))
            ->where('attribute_set_id = ?', $attributeSetId);
        $currentCount = (int) $connection->fetchOne($countSelect);


        $logger->info('Count: '. $currentCount);



        if ($currentCount >= self::MAX_ATTRIBUTES_PER_SET) {
            throw new LocalizedException(
                __('The attribute set has reached the maximum limit of %1 attributes.', self::MAX_ATTRIBUTES_PER_SET)
            );
        }

        return [$entityTypeCode, $attributeSetId, $attributeGroupId, $attributeCode, $sortOrder];
    }
}
