<?php declare(strict_types=1);

namespace Sprii\LiveShoppingIntegration;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class SpriiLiveShoppingIntegration extends Plugin
{
    const SPRII_ORDER_FIELDS = "sprii_order_fields";
    const SPRII_CART_REFERENCE_ID = "ref_id";
    const SPRII_CART_EXPORTED = "sprii_exported";
    const SPRII_CART_LINEITEM = "sprii_lineitem";
    const SPRII_SET_PRICE = "sprii_set_price";

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);
        
        // Only create/update custom field sets without removing existing data
        $this->createOrUpdateCustomFieldSet($installContext);
    }

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);
        
        // During updates, preserve existing data and only update field definitions
        $this->createOrUpdateCustomFieldSet($updateContext);
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        // Check if user wants to keep data (Shopware's built-in data retention setting)
        if ($uninstallContext->keepUserData()) {
            // Keep everything - custom fields and data are preserved
            return;
        }
        
        // If user chose to remove data, remove custom field set (this will delete the data too)
        $this->removeCustomFieldSetAndData($uninstallContext->getContext());
    }

    public function createOrUpdateCustomFieldSet(InstallContext|UpdateContext $context): void
    {
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        // Use upsert to safely create or update without data loss
        // This preserves existing custom field data while updating definitions
        $customFieldSetRepository->upsert([
            [
                'name' => self::SPRII_ORDER_FIELDS,
                'customFields' => [
                    [
                        'name' => self::SPRII_CART_REFERENCE_ID,
                        'type' => CustomFieldTypes::TEXT,
                        'config' => [
                            'label' => [
                                'da-DK' => 'Sprii Kurv ID',
                                'de-DE' => 'Sprii Warenkorb ID',
                                'en-GB' => 'Sprii Cart ID'
                            ],
                            'customFieldType' => 'text',
                            'customFieldPosition' => 1,
                        ]
                    ],
                    [
                        'name' => self::SPRII_CART_EXPORTED,
                        'type' => CustomFieldTypes::BOOL,
                        'config' => [
                            'label' => [
                                'da-DK' => 'Sprii Ordre eksporteret',
                                'de-DE' => 'Sprii Bestellung exportiert',
                                'en-GB' => 'Sprii Order Exported'
                            ],
                            'customFieldType' => 'bool',
                            'customFieldPosition' => 2,
                        ]
                    ]
                ],
                'config' => [
                    'label' => [
                        'da-DK' => 'Sprii Live Shopping',
                        'de-DE' => 'Sprii Live Shopping',
                        'en-GB' => 'Sprii Live Shopping'
                    ]
                ],
                'relations' => [
                    [
                        'entityName' => 'order',
                    ]
                ],
            ]
        ], $context->getContext());
    }

    /**
     * Remove custom field set and all associated data
     * WARNING: This will delete all custom field data from orders
     */
    public function removeCustomFieldSetAndData(Context $context): void
    {
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');
        $customFieldRepository = $this->container->get('custom_field.repository');

        // First, remove the custom field set
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('custom_field_set.name', self::SPRII_ORDER_FIELDS)
        );

        $customFieldSet = $customFieldSetRepository->search($criteria, $context);

        foreach ($customFieldSet->getEntities()->getElements() as $key => $customField) {
            $customFieldSetRepository->delete([["id" => $key]], $context);
        }

        // Then, explicitly remove any orphaned custom fields
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsAnyFilter(
                'custom_field.name',
                [self::SPRII_CART_REFERENCE_ID, self::SPRII_CART_EXPORTED]
            )
        );

        $customFields = $customFieldRepository->search($criteria, $context);

        foreach ($customFields->getEntities()->getElements() as $key => $customField) {
            $customFieldRepository->delete([["id" => $key]], $context);
        }
    }


}
