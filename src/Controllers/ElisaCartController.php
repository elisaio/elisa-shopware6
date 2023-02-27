<?php declare(strict_types=1);

namespace Elisa\LiveShoppingIntegration\Controllers;

use Elisa\LiveShoppingIntegration\ElisaLiveShoppingIntegration;
use Elisa\LiveShoppingIntegration\Services\ElisaCartService;
use Exception;
use Monolog\Logger;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Api\Response\JsonApiResponse;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route(defaults={"_routeScope"={"storefront"}})
 */
class ElisaCartController extends StorefrontController
{
    protected CartService $cartService;
    protected ElisaCartService $elisaCartService;
    protected AbstractCartPersister $cartPersister;
    protected TranslatorInterface $translator;
    protected Logger $logger;
    protected SalesChannelContextPersister $salesChannelContextPersister;

    public function __construct(
        CartService $cartService,
        ElisaCartService $elisaCartService,
        AbstractCartPersister $cartPersister,
        TranslatorInterface $translator,
        Logger $logger,
        SalesChannelContextPersister $salesChannelContextPersister
    ) {
        $this->cartService = $cartService;
        $this->elisaCartService = $elisaCartService;
        $this->cartPersister = $cartPersister;
        $this->translator = $translator;
        $this->logger = $logger;
        $this->salesChannelContextPersister = $salesChannelContextPersister;
    }

    /**
     * API endpoint to create a Shopware cart from Elisa
     *
     * @Route("/elisa/cart_create",
     *     defaults={"csrf_protected"=false, "XmlHttpRequest"=true},
     *     methods={"POST"}
     * )
     * @param Request $request
     * @param SalesChannelContext $context
     * @return JsonApiResponse
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function createCart(Request $request, SalesChannelContext $context): JsonApiResponse
    {
        $params = $request->get('params');
        $elisaCartId = $request->get(ElisaLiveShoppingIntegration::ELISA_CART_REFERENCE_ID);

        try {
            // Create an Elisa cart in Shopware
            $cart = $this->elisaCartService->createElisaCart(
                $params['products'],
                $elisaCartId,
                $context
            );
        } catch (Exception $e) {
            $this->logger->error(
                "Failed creating Elisa cart in Shopware(" . $elisaCartId . ")",
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'errorType' => get_class($e)
                ]
            );

            return new JsonApiResponse([
                "error" => "Failed creating Elisa cart in Shopware",
                ElisaLiveShoppingIntegration::ELISA_CART_REFERENCE_ID => $elisaCartId
            ], 500);
        }

        // Get domain url, so we can generate link and sent it back to Elisa
        $domain = $context->getDomainId();
        $url = $context->getSalesChannel()->getDomains()->get($domain)->getUrl();

        // Build response data
        $result = [
            ElisaLiveShoppingIntegration::ELISA_CART_REFERENCE_ID =>
                $cart->getExtensions()[ElisaLiveShoppingIntegration::ELISA_CART_REFERENCE_ID],
            'url' => $url . '/elisa/load/' . $cart->getToken()
        ];

        return new JsonApiResponse($result, 200);
    }

    /**
     * API endpoint to load an Elisa cart which has already been created
     *
     * @Route("/elisa/load/{cart_token}",
     *     defaults={"csrf_protected"=false, "XmlHttpRequest"=true},
     *     methods={"GET"}
     * )
     * @param Request $request
     * @param SalesChannelContext $context
     * @return RedirectResponse
     */
    public function loadCart(Request $request, SalesChannelContext $context): RedirectResponse
    {
        $cartToken = $request->get('cart_token');

        // Ensure Shopware allows custom prices from Elisa
        $this->elisaCartService->setAllowElisaPriceOnCart($context);

        try {
            // Get the saved Elisa cart from Shopware
            $apiCart = $this->cartService->getCart($cartToken, $context);
            $newCart = $this->cartService->createNew($context->getToken());

            // Set extensions from api cart, containing reference id from Elisa
            $newCart->setExtensions($apiCart->getExtensions());

            // Add the cart and lines before redirecting, to ensure user is given the correct cart
            $this->cartService->add($newCart, $apiCart->getLineItems()->getElements(), $context);
        } catch (Exception $e) {
            $this->logger->error("Failed loading Elisa cart in Shopware (" . $cartToken . ")", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'errorType' => get_class($e)
            ]);

            $this->addFlash('danger', $this->translator->trans('elisa-integration.load-cart.error'));

            return new RedirectResponse($this->generateUrl('frontend.home.page'));
        }

        // Redirect to cart page
        return new RedirectResponse($this->generateUrl('frontend.checkout.cart.page'));
    }
}
