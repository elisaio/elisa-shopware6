<?php declare(strict_types=1);

namespace Sprii\LiveShoppingIntegration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * Class LiveShoppingIntegration
 * @package Sprii\LiveShoppingIntegration
 */
class SpriiLiveShoppingIntegration extends Plugin
{
    const SPRII_CART_REFERENCE_ID = "ref_id";
    const SPRII_CART_LINEITEM = "sprii_lineitem";
    const SPRII_SET_PRICE = "sprii_set_price";

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        $customFieldSetRepository->upsert([
            [
                'name' => 'sprii_order_fields',
                'customFields' => [
                    [
                        'name' => SpriiLiveShoppingIntegration::SPRII_CART_REFERENCE_ID,
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
        ], $installContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('custom_field_set.name', SpriiLiveShoppingIntegration::SPRII_CART_REFERENCE_ID)
        );

        $customFields = $customFieldSetRepository->search($criteria, $uninstallContext->getContext());
        foreach ($customFields->getEntities()->getElements() as $key => $customField) {
            $customFieldSetRepository->delete([["id" => $key]], $uninstallContext->getContext());
        }
    }
}
