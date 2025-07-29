<?php
/**
 * @license MIT
 *
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace StoreEngine\Stripe;

/**
 * Client used to send requests to Stripe's API.
 *
 * @property \StoreEngine\Stripe\Service\OAuthService $oauth
 * // The beginning of the section generated from our OpenAPI spec
 * @property \StoreEngine\Stripe\Service\AccountLinkService $accountLinks
 * @property \StoreEngine\Stripe\Service\AccountService $accounts
 * @property \StoreEngine\Stripe\Service\AccountSessionService $accountSessions
 * @property \StoreEngine\Stripe\Service\ApplePayDomainService $applePayDomains
 * @property \StoreEngine\Stripe\Service\ApplicationFeeService $applicationFees
 * @property \StoreEngine\Stripe\Service\Apps\AppsServiceFactory $apps
 * @property \StoreEngine\Stripe\Service\BalanceService $balance
 * @property \StoreEngine\Stripe\Service\BalanceTransactionService $balanceTransactions
 * @property \StoreEngine\Stripe\Service\Billing\BillingServiceFactory $billing
 * @property \StoreEngine\Stripe\Service\BillingPortal\BillingPortalServiceFactory $billingPortal
 * @property \StoreEngine\Stripe\Service\ChargeService $charges
 * @property \StoreEngine\Stripe\Service\Checkout\CheckoutServiceFactory $checkout
 * @property \StoreEngine\Stripe\Service\Climate\ClimateServiceFactory $climate
 * @property \StoreEngine\Stripe\Service\ConfirmationTokenService $confirmationTokens
 * @property \StoreEngine\Stripe\Service\CountrySpecService $countrySpecs
 * @property \StoreEngine\Stripe\Service\CouponService $coupons
 * @property \StoreEngine\Stripe\Service\CreditNoteService $creditNotes
 * @property \StoreEngine\Stripe\Service\CustomerService $customers
 * @property \StoreEngine\Stripe\Service\CustomerSessionService $customerSessions
 * @property \StoreEngine\Stripe\Service\DisputeService $disputes
 * @property \StoreEngine\Stripe\Service\Entitlements\EntitlementsServiceFactory $entitlements
 * @property \StoreEngine\Stripe\Service\EphemeralKeyService $ephemeralKeys
 * @property \StoreEngine\Stripe\Service\EventService $events
 * @property \StoreEngine\Stripe\Service\ExchangeRateService $exchangeRates
 * @property \StoreEngine\Stripe\Service\FileLinkService $fileLinks
 * @property \StoreEngine\Stripe\Service\FileService $files
 * @property \StoreEngine\Stripe\Service\FinancialConnections\FinancialConnectionsServiceFactory $financialConnections
 * @property \StoreEngine\Stripe\Service\Forwarding\ForwardingServiceFactory $forwarding
 * @property \StoreEngine\Stripe\Service\Identity\IdentityServiceFactory $identity
 * @property \StoreEngine\Stripe\Service\InvoiceItemService $invoiceItems
 * @property \StoreEngine\Stripe\Service\InvoiceRenderingTemplateService $invoiceRenderingTemplates
 * @property \StoreEngine\Stripe\Service\InvoiceService $invoices
 * @property \StoreEngine\Stripe\Service\Issuing\IssuingServiceFactory $issuing
 * @property \StoreEngine\Stripe\Service\MandateService $mandates
 * @property \StoreEngine\Stripe\Service\PaymentIntentService $paymentIntents
 * @property \StoreEngine\Stripe\Service\PaymentLinkService $paymentLinks
 * @property \StoreEngine\Stripe\Service\PaymentMethodConfigurationService $paymentMethodConfigurations
 * @property \StoreEngine\Stripe\Service\PaymentMethodDomainService $paymentMethodDomains
 * @property \StoreEngine\Stripe\Service\PaymentMethodService $paymentMethods
 * @property \StoreEngine\Stripe\Service\PayoutService $payouts
 * @property \StoreEngine\Stripe\Service\PlanService $plans
 * @property \StoreEngine\Stripe\Service\PriceService $prices
 * @property \StoreEngine\Stripe\Service\ProductService $products
 * @property \StoreEngine\Stripe\Service\PromotionCodeService $promotionCodes
 * @property \StoreEngine\Stripe\Service\QuoteService $quotes
 * @property \StoreEngine\Stripe\Service\Radar\RadarServiceFactory $radar
 * @property \StoreEngine\Stripe\Service\RefundService $refunds
 * @property \StoreEngine\Stripe\Service\Reporting\ReportingServiceFactory $reporting
 * @property \StoreEngine\Stripe\Service\ReviewService $reviews
 * @property \StoreEngine\Stripe\Service\SetupAttemptService $setupAttempts
 * @property \StoreEngine\Stripe\Service\SetupIntentService $setupIntents
 * @property \StoreEngine\Stripe\Service\ShippingRateService $shippingRates
 * @property \StoreEngine\Stripe\Service\Sigma\SigmaServiceFactory $sigma
 * @property \StoreEngine\Stripe\Service\SourceService $sources
 * @property \StoreEngine\Stripe\Service\SubscriptionItemService $subscriptionItems
 * @property \StoreEngine\Stripe\Service\SubscriptionService $subscriptions
 * @property \StoreEngine\Stripe\Service\SubscriptionScheduleService $subscriptionSchedules
 * @property \StoreEngine\Stripe\Service\Tax\TaxServiceFactory $tax
 * @property \StoreEngine\Stripe\Service\TaxCodeService $taxCodes
 * @property \StoreEngine\Stripe\Service\TaxIdService $taxIds
 * @property \StoreEngine\Stripe\Service\TaxRateService $taxRates
 * @property \StoreEngine\Stripe\Service\Terminal\TerminalServiceFactory $terminal
 * @property \StoreEngine\Stripe\Service\TestHelpers\TestHelpersServiceFactory $testHelpers
 * @property \StoreEngine\Stripe\Service\TokenService $tokens
 * @property \StoreEngine\Stripe\Service\TopupService $topups
 * @property \StoreEngine\Stripe\Service\TransferService $transfers
 * @property \StoreEngine\Stripe\Service\Treasury\TreasuryServiceFactory $treasury
 * @property \StoreEngine\Stripe\Service\V2\V2ServiceFactory $v2
 * @property \StoreEngine\Stripe\Service\WebhookEndpointService $webhookEndpoints
 * // The end of the section generated from our OpenAPI spec
 */
class StripeClient extends BaseStripeClient
{
    /**
     * @var \StoreEngine\Stripe\Service\CoreServiceFactory
     */
    private $coreServiceFactory;

    public function __get($name)
    {
        return $this->getService($name);
    }

    public function getService($name)
    {
        if (null === $this->coreServiceFactory) {
            $this->coreServiceFactory = new \StoreEngine\Stripe\Service\CoreServiceFactory($this);
        }

        return $this->coreServiceFactory->getService($name);
    }
}
