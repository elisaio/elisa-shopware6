<?php declare(strict_types=1);

namespace Elisa\LiveShoppingIntegration\Services;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Elisa\LiveShoppingIntegration\ElisaLiveShoppingIntegration;
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

class ElisaCartService
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
     * Creates a new cart from Elisa and saves it in Shopware
     *
     * @param array $elisaProducts
     * @param string $elisaCartId
     * @param SalesChannelContext $context
     * @return Cart
     * @throws Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function createElisaCart(
        array $elisaProducts,
        string $elisaCartId,
        SalesChannelContext $context
    ): Cart {
        $lineItems = new LineItemCollection();

        $cart = $this->cartService->createNew($context->getToken());

        // Set the Elisa Cart ID on the Shopware cart for future reference
        $cartExtensions = $cart->getExtensions();
        $cartExtensions[ElisaLiveShoppingIntegration::ELISA_CART_REFERENCE_ID] = $elisaCartId;
        $cart->setExtensions($cartExtensions);

        $this->setAllowElisaPriceOnCart($context);
        $taxIds = $this->getShopwareTaxIds($elisaProducts);

        // Loop through products from Elisa to create valid Shopware line items
        foreach ($elisaProducts as $elisaProduct) {
            $productId = $elisaProduct['child_sku'] ?? $elisaProduct['sku'];
            $lineItem = $this->lineItemFactory->create($productId);

            // Set necessary fields on Shopware line item
            $lineItem->setStackable(true);
            $lineItem->setQuantity($elisaProduct['qty']);
            $lineItem->setPayloadValue(ElisaLiveShoppingIntegration::ELISA_CART_LINEITEM, true);
            $lineItem->setPayloadValue(ElisaLiveShoppingIntegration::ELISA_SET_PRICE, false);

            if (isset($elisaProduct['price'])) {
                // Indicate that the price on this line item is set by Elisa
                $lineItem->setPayloadValue(ElisaLiveShoppingIntegration::ELISA_SET_PRICE, true);

                // Calculate taxes for the new line
                $taxCalculator = new TaxCalculator();

                // Convert product id from API to match binary type id in Shopware database
                // and build tax rules from that
                $taxRules = $context->buildTaxRules($taxIds[hex2bin($productId)]);
                $calculatedTaxCollection = $taxCalculator->calculateGrossTaxes(
                    $elisaProduct['price'],
                    $taxRules
                );

                // Set the price supplied from Elisa
                $lineItem->setPrice(
                    new CalculatedPrice(
                        $elisaProduct['price'],
                        $elisaProduct['price'] * $elisaProduct['qty'],
                        $calculatedTaxCollection,
                        $taxRules,
                        $elisaProduct['qty']
                    )
                );
                $lineItem->setPriceDefinition(new QuantityPriceDefinition(
                    $elisaProduct['price'],
                    $taxRules,
                    $elisaProduct['qty']
                ));

                $lineItem->setExtensions(["customPrice" => true]);
            }
            $lineItem->setStackable(false);
            $lineItems->add($lineItem);
        }

        $this->cartService->add($cart, $lineItems->getElements(), $context);
        $this->cartPersister->save($cart, $context);

        // $this->writeElisaShopwareCartRelation($cart, $elisaCartId);

        // Add Shopware line items to the cart and save it
        return $cart;
    }

    /**
     * Gets tax ids on given products from Elisa
     *
     * @param array $elisaProducts
     * @return array
     * @throws Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    protected function getShopwareTaxIds(array $elisaProducts): array
    {
        $productIds = [];

        foreach ($elisaProducts as $elisaProduct) {
            $productIds[] = Uuid::fromHexToBytes($elisaProduct['sku']);
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
     * Method to allow custom prices from Elisa to be set and saved on Shopware cart
     * This ensures recalculation with default prices does not happen.
     *
     * @param SalesChannelContext $context
     * @return void
     */
    public function setAllowElisaPriceOnCart(SalesChannelContext $context): void
    {
        // We need to allow price overwrites to ensure Shopware doesn't recalculate Elisa prices
        if (empty(
            $context->getPermissions()[ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES]
        )) {
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
