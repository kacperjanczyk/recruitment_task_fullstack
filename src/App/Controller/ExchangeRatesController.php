<?php

declare(strict_types=1);

namespace App\Controller;

use Cassandra\Date;
use DateTime;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ExchangeRatesController extends AbstractController
{
    private $httpClient;
    private const ACTIVE_CURRENCIES = ['EUR', 'USD', 'CZK', 'IDR', 'BRL'];
    private const ACTIVE_BUY_CURRENCIES = ['EUR', 'USD'];
    private const NBP_API_BASE_URL = 'https://api.nbp.pl/api/exchangerates/tables/A/';
    private const BUY_RATE_DIFFERENCE = -0.05;
    private const SELL_RATE_DIFFERENCE_FOR_ACTIVE_BUY = 0.07;
    private const SELL_RATE_DIFFERENCE = 0.15;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * @Route("/api/exchange-rates", methods={"GET"})
     */
    public function index(Request $request): Response
    {
        $dateString = $request->query->get('date');

        if ($dateString && !\DateTime::createFromFormat('Y-m-d', $dateString)) {
            return $this->json([
                'message' => 'Invalid date format. Expected format: Y-m-d.'
            ], 400);
        }

        try {
            $date = (new DateTime($dateString ?? 'now'));
            $date = $this->adjustDateIfWeekend($date);
            $dateToday = $this->adjustTodayDate();
            $dateToday = $this->adjustDateIfWeekend($dateToday);

            $selectedDateRates = $this->fetchExchangeRates($date);
            $todayRates = $this->fetchExchangeRates($dateToday);

            if (empty($selectedDateRates[0]['rates']) || empty($todayRates[0]['rates'])) {
                return $this->json([
                    'message' => 'No exchange rates available for the provided date.'
                ], 404);
            }

            return $this->json($this->getActiveCurrenciesAndSetRates($selectedDateRates[0]['rates'], $todayRates[0]['rates']));
        } catch (ExceptionInterface|Exception $e) {
            return $this->json(['message' => 'No exchange rates available for the provided date.'], 500);
        }
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    private function fetchExchangeRates(string $date): array
    {
        return $this->httpClient->request(
            'GET',
            self::NBP_API_BASE_URL . $date,
            [
                'query' => [
                    'format' => 'json'
                ]
            ]
        )->toArray();
    }

    private function getActiveCurrenciesAndSetRates(array $selectedDateCurrencies, array $todayCurrencies): array
    {
        $activeCurrencies = [];
        foreach ($selectedDateCurrencies as $currencyKey => $currency) {
            if (in_array($currency['code'], self::ACTIVE_CURRENCIES, true)) {
                if (in_array($currency['code'], self::ACTIVE_BUY_CURRENCIES, true)) {
                    $currency['buyRate'] = (double)$currency['mid'] + self::BUY_RATE_DIFFERENCE;
                    $currency['sellRate'] = (double)$currency['mid'] + self::SELL_RATE_DIFFERENCE_FOR_ACTIVE_BUY;
                    $currency['todayBuyRate'] = $todayCurrencies[$currencyKey]['mid'] + self::BUY_RATE_DIFFERENCE;
                    $currency['todaySellRate'] = $todayCurrencies[$currencyKey]['mid'] + self::SELL_RATE_DIFFERENCE_FOR_ACTIVE_BUY;
                } else {
                    $currency['buyRate'] = null;
                    $currency['sellRate'] = (double)$currency['mid'] + self::SELL_RATE_DIFFERENCE;
                    $currency['todayBuyRate'] = null;
                    $currency['todaySellRate'] = $todayCurrencies[$currencyKey]['mid'] + self::SELL_RATE_DIFFERENCE;
                }
                $currency['todayMid'] = $todayCurrencies[$currencyKey]['mid'];
                $activeCurrencies[] = $currency;
            }
        }

        return $activeCurrencies;
    }

    private function adjustDateIfWeekend(DateTime $date): string
    {
        $dayOfWeek = $date->format('w');

        if ($dayOfWeek === '0') {
            $date->modify('-2 days');
        } elseif ($dayOfWeek === '6') {
            $date->modify('-1 day');
        }

        return $date->format('Y-m-d');
    }

    private function adjustTodayDate(): DateTime
    {
        $now = new DateTime('now');

        if ($now->format('H') < 12) {
            $now->modify('-1 day');
        }

        return $now;
    }
}
