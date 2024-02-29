<?php declare(strict_types=1);

namespace Sprii\LiveShoppingIntegration\Services;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Sprii\LiveShoppingIntegration\SpriiLiveShoppingIntegration;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Content\Product\Cart\ProductLineItemFactory;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SpriiCartService
{
    protected CartService $cartService;
    protected AbstractCartPersister $cartPersister;
    protected Connection $connection;
    protected ProductLineItemFactory $lineItemFactory;
    protected SalesChannelContextPersister $salesChannelContextPersister;

    public function __construct(
        CartService $cartService,
        AbstractCartPersister $cartPersister,
        Connection $connection,
        ProductLineItemFactory $lineItemFactory,
        SalesChannelContextPersister $salesChannelContextPersister
    ) {
        $this->cartService = $cartService;
        $this->cartPersister = $cartPersister;
        $this->connection = $connection;
        $this->lineItemFactory = $lineItemFactory;
        $this->salesChannelContextPersister = $salesChannelContextPersister;
    }

    /**
     * Creates a new cart from Sprii and saves it in Shopware
     *
     * @param array $spriiProducts
     * @param string $spriiCartId
     * @param SalesChannelContext $context
     * @return Cart
     * @throws Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function createSpriiCart(
        array $spriiProducts,
        string $spriiCartId,
        SalesChannelContext $context
    ): Cart {
        $lineItems = new LineItemCollection();

        $cart = $this->cartService->createNew($context->getToken());

        // Set the Sprii Cart ID on the Shopware cart for future reference
        $cartExtensions = $cart->getExtensions();
        $cartExtensions[SpriiLiveShoppingIntegration::SPRII_CART_REFERENCE_ID] = $spriiCartId;
        $cart->setExtensions($cartExtensions);

        $this->setAllowSpriiPriceOnCart($context);
        $taxIds = $this->getShopwareTaxIds($spriiProducts);

        // Loop through products from Sprii to create valid Shopware line items
        foreach ($spriiProducts as $spriiProduct) {
            $productId = $spriiProduct['child_sku'] ?? $spriiProduct['sku'];
            $lineItem = $this->lineItemFactory->create($productId);

            // Set necessary fields on Shopware line item
            $lineItem->setStackable(true);
            $lineItem->setQuantity($spriiProduct['qty']);
            $lineItem->setPayloadValue(SpriiLiveShoppingIntegration::SPRII_CART_LINEITEM, true);
            $lineItem->setPayloadValue(SpriiLiveShoppingIntegration::SPRII_SET_PRICE, false);

            if (isset($spriiProduct['price'])) {
                // Indicate that the price on this line item is set by Sprii
                $lineItem->setPayloadValue(SpriiLiveShoppingIntegration::SPRII_SET_PRICE, true);

                // Calculate taxes for the new line
                $taxCalculator = new TaxCalculator();

                // Convert product id from API to match binary type id in Shopware database
                // and build tax rules from that
                $taxRules = $context->buildTaxRules($taxIds[hex2bin($productId)]);
                $calculatedTaxCollection = $taxCalculator->calculateGrossTaxes(
                    $spriiProduct['price'],
                    $taxRules
                );

                // Set the price supplied from Sprii
                $lineItem->setPrice(
                    new CalculatedPrice(
                        $spriiProduct['price'],
                        $spriiProduct['price'] * $spriiProduct['qty'],
                        $calculatedTaxCollection,
                        $taxRules,
                        $spriiProduct['qty']
                    )
                );
                $lineItem->setPriceDefinition(
                    new QuantityPriceDefinition(
                        $spriiProduct['price'],
                        $taxRules,
                        $spriiProduct['qty']
                    )
                );

                $lineItem->setExtensions(["customPrice" => true]);
            }
            $lineItem->setStackable(false);
            $lineItems->add($lineItem);
        }

        $this->cartService->add($cart, $lineItems->getElements(), $context);
        $this->cartPersister->save($cart, $context);

        // $this->writeSpriiShopwareCartRelation($cart, $spriiCartId);

        // Add Shopware line items to the cart and save it
        return $cart;
    }

    /**
     * Gets tax ids on given products from Sprii
     *
     * @param array $spriiProducts
     * @return array
     * @throws Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    protected function getShopwareTaxIds(array $spriiProducts): array
    {
        $productIds = [];

        foreach ($spriiProducts as $spriiProduct) {
            $productIds[] = Uuid::fromHexToBytes($spriiProduct['sku']);
        }

        $taxQuery = "
            SELECT id, LOWER(HEX(tax_id)) as tax_id FROM product WHERE id IN (:productIds)
        ";

        return $this->connection->executeQuery(
            $taxQuery,
            [
                "productIds" => $productIds
            ],
            [
                "productIds" => Connection::PARAM_STR_ARRAY
            ]
        )->fetchAllKeyValue();
    }

    /**
     * Method to allow custom prices from Sprii to be set and saved on Shopware cart
     * This ensures recalculation with default prices does not happen.
     *
     * @param SalesChannelContext $context
     * @return void
     */
    public function setAllowSpriiPriceOnCart(SalesChannelContext $context): void
    {
        // We need to allow price overwrites to ensure Shopware doesn't recalculate Sprii prices
        if (
            empty(
            $context->getPermissions()[ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES]
        )
        ) {
            $context->setPermissions([
                ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES => true
            ]);

            // Persist the price overwrite permission on SalesChannelContext
            $this->salesChannelContextPersister->save(
                $context->getToken(),
                [
                    SalesChannelContextService::PERMISSIONS => [
                        ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES => true
                    ]
                ],
                $context->getSalesChannelId()
            );
        }
    }
}
