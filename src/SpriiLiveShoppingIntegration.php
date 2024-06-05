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

        $this->removeExistingCustomFields($installContext->getContext());
        $this->createCustomFieldSet($installContext);
    }

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);

        $this->removeExistingCustomFields($updateContext->getContext());
        $this->createCustomFieldSet($updateContext);
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        $this->removeExistingCustomFields($uninstallContext->getContext());
    }

    public function createCustomFieldSet(InstallContext|UpdateContext $context): void
    {
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

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

    public function removeExistingCustomFields(Context $context): void
    {
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');
        $customFieldRepository = $this->container->get('custom_field.repository');

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('custom_field_set.name', self::SPRII_ORDER_FIELDS)
        );

        $customFieldSet = $customFieldSetRepository->search($criteria, $context);

        foreach ($customFieldSet->getEntities()->getElements() as $key => $customField) {
            $customFieldSetRepository->delete([["id" => $key]], $context);
        }

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
