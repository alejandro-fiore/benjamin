<?php
namespace Ebanx\Benjamin\Services\Gateways;

use Ebanx\Benjamin\Models\Configs\Config;
use Ebanx\Benjamin\Models\Payment;
use Ebanx\Benjamin\Models\Currency;
use Ebanx\Benjamin\Services\Http\Client;
use Ebanx\Benjamin\Services\Exchange;

abstract class BaseGateway
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var Exchange
     */
    protected $exchange;

    abstract protected function getPaymentData(Payment $payment);

    abstract protected static function getEnabledCountries();
    abstract protected static function getEnabledCurrencies();

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->client = $this->client ?: new Client();

        if (!$this->config->isSandbox) {
            $this->client->inLiveMode();
        }

        $this->exchange = new Exchange($this->config, $this->client);
    }

    public function create(Payment $payment)
    {
        $body = $this->client->payment($this->getPaymentData($payment));

        return $body;
    }

    public function request(Payment $payment)
    {
        $body = $this->client->request($this->getPaymentData($payment));

        return $body;
    }

    public function exchange()
    {
        return $this->exchange;
    }

    public function isAvailableForCountry($country)
    {
        $siteCurrency = $this->config->baseCurrency;
        $globalCurrencies = Currency::globalCurrencies();
        $localCurrency = Currency::localForCountry($country);

        $countryIsAccepted = $this->acceptsCountry($country);
        $siteCurrencyIsGlobal = in_array($siteCurrency, $globalCurrencies);
        $siteCurrencyMatchesCountry = $siteCurrency === $localCurrency;

        return $countryIsAccepted
            && ($siteCurrencyIsGlobal || $siteCurrencyMatchesCountry);
    }

    public static function acceptsCurrency($currency)
    {
        return in_array($currency, static::getEnabledCurrencies());
    }

    public static function acceptsCountry($country)
    {
        return in_array($country, static::getEnabledCountries());
    }

    protected function availableForCountryOrThrow($country)
    {
        if ($this->isAvailableForCountry($country)) {
            return;
        }

        throw new \InvalidArgumentException(sprintf('Gateway not available for %s%s',
            $country,
            Currency::isGlobal($this->config->baseCurrency)
                ? ''
                : ' using '.$this->config->baseCurrency
        ));
    }
}
