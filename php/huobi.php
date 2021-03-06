<?php

namespace ccxt;

class huobi extends Exchange {

    public function describe () {
        return array_replace_recursive (parent::describe (), array (
            'id' => 'huobi',
            'name' => 'Huobi',
            'countries' => 'CN',
            'rateLimit' => 2000,
            'version' => 'v3',
            'has' => array (
                'CORS' => false,
                'fetchOHLCV' => true,
            ),
            'timeframes' => array (
                '1m' => '001',
                '5m' => '005',
                '15m' => '015',
                '30m' => '030',
                '1h' => '060',
                '1d' => '100',
                '1w' => '200',
                '1M' => '300',
                '1y' => '400',
            ),
            'urls' => array (
                'logo' => 'https://user-images.githubusercontent.com/1294454/27766569-15aa7b9a-5edd-11e7-9e7f-44791f4ee49c.jpg',
                'api' => 'http://api.huobi.com',
                'www' => 'https://www.huobi.com',
                'doc' => 'https://github.com/huobiapi/API_Docs_en/wiki',
            ),
            'api' => array (
                'staticmarket' => array (
                    'get' => array (
                        '{id}_kline_{period}',
                        'ticker_{id}',
                        'depth_{id}',
                        'depth_{id}_{length}',
                        'detail_{id}',
                    ),
                ),
                'usdmarket' => array (
                    'get' => array (
                        '{id}_kline_{period}',
                        'ticker_{id}',
                        'depth_{id}',
                        'depth_{id}_{length}',
                        'detail_{id}',
                    ),
                ),
                'trade' => array (
                    'post' => array (
                        'get_account_info',
                        'get_orders',
                        'order_info',
                        'buy',
                        'sell',
                        'buy_market',
                        'sell_market',
                        'cancel_order',
                        'get_new_deal_orders',
                        'get_order_id_by_trade_id',
                        'withdraw_coin',
                        'cancel_withdraw_coin',
                        'get_withdraw_coin_result',
                        'transfer',
                        'loan',
                        'repayment',
                        'get_loan_available',
                        'get_loans',
                    ),
                ),
            ),
            'markets' => array (
                'BTC/CNY' => array ( 'id' => 'btc', 'symbol' => 'BTC/CNY', 'base' => 'BTC', 'quote' => 'CNY', 'type' => 'staticmarket', 'coinType' => 1 ),
                'LTC/CNY' => array ( 'id' => 'ltc', 'symbol' => 'LTC/CNY', 'base' => 'LTC', 'quote' => 'CNY', 'type' => 'staticmarket', 'coinType' => 2 ),
                // 'BTC/USD' => array ( 'id' => 'btc', 'symbol' => 'BTC/USD', 'base' => 'BTC', 'quote' => 'USD', 'type' => 'usdmarket',    'coinType' => 1 ),
            ),
        ));
    }

    public function fetch_balance ($params = array ()) {
        $balances = $this->tradePostGetAccountInfo ();
        $result = array ( 'info' => $balances );
        $currencies = is_array ($this->currencies) ? array_keys ($this->currencies) : array ();
        for ($i = 0; $i < count ($currencies); $i++) {
            $currency = $currencies[$i];
            $lowercase = strtolower ($currency);
            $account = $this->account ();
            $available = 'available_' . $lowercase . '_display';
            $frozen = 'frozen_' . $lowercase . '_display';
            $loan = 'loan_' . $lowercase . '_display';
            if (is_array ($balances) && array_key_exists ($available, $balances))
                $account['free'] = floatval ($balances[$available]);
            if (is_array ($balances) && array_key_exists ($frozen, $balances))
                $account['used'] = floatval ($balances[$frozen]);
            if (is_array ($balances) && array_key_exists ($loan, $balances))
                $account['used'] = $this->sum ($account['used'], floatval ($balances[$loan]));
            $account['total'] = $this->sum ($account['free'], $account['used']);
            $result[$currency] = $account;
        }
        return $this->parse_balance($result);
    }

    public function fetch_order_book ($symbol, $params = array ()) {
        $market = $this->market ($symbol);
        $method = $market['type'] . 'GetDepthId';
        $orderbook = $this->$method (array_merge (array ( 'id' => $market['id'] ), $params));
        return $this->parse_order_book($orderbook);
    }

    public function fetch_ticker ($symbol, $params = array ()) {
        $market = $this->market ($symbol);
        $method = $market['type'] . 'GetTickerId';
        $response = $this->$method (array_merge (array (
            'id' => $market['id'],
        ), $params));
        $ticker = $response['ticker'];
        $timestamp = intval ($response['time']) * 1000;
        return array (
            'symbol' => $symbol,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'high' => $this->safe_float($ticker, 'high'),
            'low' => $this->safe_float($ticker, 'low'),
            'bid' => $this->safe_float($ticker, 'buy'),
            'ask' => $this->safe_float($ticker, 'sell'),
            'vwap' => null,
            'open' => $this->safe_float($ticker, 'open'),
            'close' => null,
            'first' => null,
            'last' => $this->safe_float($ticker, 'last'),
            'change' => null,
            'percentage' => null,
            'average' => null,
            'baseVolume' => null,
            'quoteVolume' => $this->safe_float($ticker, 'vol'),
            'info' => $ticker,
        );
    }

    public function parse_trade ($trade, $market) {
        $timestamp = $trade['ts'];
        return array (
            'info' => $trade,
            'id' => (string) $trade['id'],
            'order' => null,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'symbol' => $market['symbol'],
            'type' => null,
            'side' => $trade['direction'],
            'price' => $trade['price'],
            'amount' => $trade['amount'],
        );
    }

    public function fetch_trades ($symbol, $since = null, $limit = null, $params = array ()) {
        $market = $this->market ($symbol);
        $method = $market['type'] . 'GetDetailId';
        $response = $this->$method (array_merge (array (
            'id' => $market['id'],
        ), $params));
        return $this->parse_trades($response['trades'], $market, $since, $limit);
    }

    public function parse_ohlcv ($ohlcv, $market = null, $timeframe = '1m', $since = null, $limit = null) {
        // not implemented yet
        return [
            $ohlcv[0],
            $ohlcv[1],
            $ohlcv[2],
            $ohlcv[3],
            $ohlcv[4],
            $ohlcv[6],
        ];
    }

    public function fetch_ohlcv ($symbol, $timeframe = '1m', $since = null, $limit = null, $params = array ()) {
        $market = $this->market ($symbol);
        $method = $market['type'] . 'GetIdKlinePeriod';
        $ohlcvs = $this->$method (array_merge (array (
            'id' => $market['id'],
            'period' => $this->timeframes[$timeframe],
        ), $params));
        return $ohlcvs;
        // return $this->parse_ohlcvs($ohlcvs, $market, $timeframe, $since, $limit);
    }

    public function create_order ($symbol, $type, $side, $amount, $price = null, $params = array ()) {
        $market = $this->market ($symbol);
        $method = 'tradePost' . $this->capitalize ($side);
        $order = array (
            'coin_type' => $market['coinType'],
            'amount' => $amount,
            'market' => strtolower ($market['quote']),
        );
        if ($type == 'limit')
            $order['price'] = $price;
        else
            $method .= $this->capitalize ($type);
        $response = $this->$method (array_merge ($order, $params));
        return array (
            'info' => $response,
            'id' => $response['id'],
        );
    }

    public function cancel_order ($id, $symbol = null, $params = array ()) {
        return $this->tradePostCancelOrder (array ( 'id' => $id ));
    }

    public function sign ($path, $api = 'public', $method = 'GET', $params = array (), $headers = null, $body = null) {
        $url = $this->urls['api'];
        if ($api == 'trade') {
            $this->check_required_credentials();
            $url .= '/api' . $this->version;
            $query = $this->keysort (array_merge (array (
                'method' => $path,
                'access_key' => $this->apiKey,
                'created' => $this->nonce (),
            ), $params));
            $queryString = $this->urlencode ($this->omit ($query, 'market'));
            // secret key must be appended to the $query before signing
            $queryString .= '&secret_key=' . $this->secret;
            $query['sign'] = $this->hash ($this->encode ($queryString));
            $body = $this->urlencode ($query);
            $headers = array (
                'Content-Type' => 'application/x-www-form-urlencoded',
            );
        } else {
            $url .= '/' . $api . '/' . $this->implode_params($path, $params) . '_json.js';
            $query = $this->omit ($params, $this->extract_params($path));
            if ($query)
                $url .= '?' . $this->urlencode ($query);
        }
        return array ( 'url' => $url, 'method' => $method, 'body' => $body, 'headers' => $headers );
    }

    public function request ($path, $api = 'trade', $method = 'GET', $params = array (), $headers = null, $body = null) {
        $response = $this->fetch2 ($path, $api, $method, $params, $headers, $body);
        if (is_array ($response) && array_key_exists ('status', $response))
            if ($response['status'] == 'error')
                throw new ExchangeError ($this->id . ' ' . $this->json ($response));
        if (is_array ($response) && array_key_exists ('code', $response))
            throw new ExchangeError ($this->id . ' ' . $this->json ($response));
        return $response;
    }
}
