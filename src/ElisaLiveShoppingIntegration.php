<?php declare(strict_types=1);

namespace Elisa\LiveShoppingIntegration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * Class LiveShoppingIntegration
 * @package Elisa\LiveShoppingIntegration
 */
class ElisaLiveShoppingIntegration extends Plugin
{
    const ELISA_CART_REFERENCE_ID = "ref_id";
    const ELISA_CART_LINEITEM = "elisa_lineitem";
    const ELISA_SET_PRICE = "elisa_set_price";

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        $customFieldSetRepository->upsert([
            [
                'name' => 'elisa_order_fields',
                'customFields' => [
                    [
                        'name' => ElisaLiveShoppingIntegration::ELISA_CART_REFERENCE_ID,
                        'type' => CustomFieldTypes::TEXT,
                        'config' => [
                            'label' => [
                                'da-DK' => 'Elisa Kurv ID',
                                'de-DE' => 'Elisa Warenkorb ID',
                                'en-GB' => 'Elisa Cart ID'
                            ],
                            'customFieldType' => 'text',
                            'customFieldPosition' => 1,
                        ]
                    ]
                ],
                'config' => [
                    'label' => [
                        'da-DK' => 'Elisa Live Shopping',
                        'de-DE' => 'Elisa Live Shopping',
                        'en-GB' => 'Elisa Live Shopping'
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
            new EqualsFilter('custom_field_set.name', ElisaLiveShoppingIntegration::ELISA_CART_REFERENCE_ID)
        );

        $customFields = $customFieldSetRepository->search($criteria, $uninstallContext->getContext());
        foreach ($customFields->getEntities()->getElements() as $key => $customField) {
            $customFieldSetRepository->delete([["id" => $key]], $uninstallContext->getContext());
        }
    }
}
