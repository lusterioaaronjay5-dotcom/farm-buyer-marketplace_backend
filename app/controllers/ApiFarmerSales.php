<?php

class ApiFarmerSales extends Controller
{
    private const WINDOW_DAYS = 30;
    private const RECENT_DAYS = 7;
    private const PREDICT_LIMIT = 3;

    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->database();
        $this->call->helper('api');
        $this->call->helper('farmer');
    }

    /**
     * GET /api/farmer/sales-summary
     * Benta (paid lang), mabilis/matagal maubos, at estimate ng susunod na mabenta.
     */
    public function summary()
    {
        $this->api->require_method('GET');
        $farmerId = farmer_require_farmer($this->api);

        $now       = time();
        $today     = date('Y-m-d', $now);
        $monthKey  = date('Y-m', $now);
        $yearKey   = date('Y', $now);
        $windowStart = date('Y-m-d', strtotime('-' . (self::WINDOW_DAYS - 1) . ' days', $now));
        $recentStart = date('Y-m-d', strtotime('-' . (self::RECENT_DAYS - 1) . ' days', $now));

        // 1. Produkto ng farmer
        $products = [];
        foreach ($this->db->table('products')->where('farmer_id', $farmerId)->get_all() ?: [] as $row) {
            $p = (array) $row;
            $products[(int) $p['product_id']] = $p;
        }

        // 2. Lahat ng item ng mga produktong ito (1 query)
        $items = [];
        foreach (farmer_fetch_in($this->db, 'order_items', 'product_id', array_keys($products)) as $row) {
            $items[] = (array) $row;
        }

        // 3. Payments ng mga order na iyon (1 query). Paid lang ang binibilang.
        $orderIds = array_map(fn ($i) => (int) $i['order_id'], $items);
        $paidAt = [];
        foreach (farmer_fetch_in($this->db, 'payments', 'order_id', $orderIds) as $row) {
            $pay = (array) $row;
            if (($pay['payment_status'] ?? '') === 'paid' && !empty($pay['paid_at'])) {
                $paidAt[(int) $pay['order_id']] = $pay['paid_at'];
            }
        }

        // Blangkong series para kumpleto ang chart kahit walang benta
        $daily = [];
        for ($d = self::WINDOW_DAYS - 1; $d >= 0; $d--) {
            $key = date('Y-m-d', strtotime("-$d days", $now));
            $daily[$key] = ['date' => $key, 'amount' => 0.0, 'units' => 0];
        }

        $monthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $key = sprintf('%s-%02d', $yearKey, $m);
            $monthly[$key] = ['month' => $key, 'amount' => 0.0];
        }

        $totals = ['today' => 0.0, 'month' => 0.0, 'year' => 0.0];

        $stats = [];
        foreach ($products as $pid => $p) {
            $stats[$pid] = ['units_recent' => 0, 'units_window' => 0, 'units_total' => 0];
        }

        foreach ($items as $i) {
            $orderId = (int) $i['order_id'];
            if (!isset($paidAt[$orderId])) {
                continue;
            }

            $pid    = (int) $i['product_id'];
            $qty    = (int) $i['quantity'];
            $amount = (float) ($i['subtotal'] ?? ((float) $i['unit_price'] * $qty));

            $ts    = strtotime($paidAt[$orderId]);
            $day   = date('Y-m-d', $ts);
            $month = date('Y-m', $ts);
            $year  = date('Y', $ts);

            $stats[$pid]['units_total'] += $qty;

            if ($day === $today) {
                $totals['today'] += $amount;
            }
            if ($month === $monthKey) {
                $totals['month'] += $amount;
            }
            if ($year === $yearKey) {
                $totals['year'] += $amount;
            }

            if (isset($monthly[$month])) {
                $monthly[$month]['amount'] += $amount;
            }

            if (isset($daily[$day])) {
                $daily[$day]['amount'] += $amount;
                $daily[$day]['units']  += $qty;
            }

            if ($day >= $windowStart) {
                $stats[$pid]['units_window'] += $qty;
            }
            if ($day >= $recentStart) {
                $stats[$pid]['units_recent'] += $qty;
            }
        }

        // 4. Listahan ng produkto na may bilis ng benta
        $list = [];
        foreach ($products as $pid => $p) {
            $s        = $stats[$pid];
            $velocity = $s['units_window'] / self::WINDOW_DAYS;
            $qtyLeft  = (int) $p['quantity_available'];

            $list[] = [
                'product_id'         => $pid,
                'product_name'       => $p['product_name'],
                'unit'               => $p['unit'] ?? '',
                'quantity_available' => $qtyLeft,
                'units_recent'       => $s['units_recent'],
                'units_window'       => $s['units_window'],
                'units_total'        => $s['units_total'],
                'velocity'           => round($velocity, 2),
                'days_to_sold_out'   => $velocity > 0 ? round($qtyLeft / $velocity, 1) : null,
                'speed'              => 'walang_benta',
            ];
        }

        usort($list, fn ($a, $b) => $b['velocity'] <=> $a['velocity']);

        // Kalahati ng may benta ay "mabilis", ang natitira ay "matagal"
        $sellerCount = count(array_filter($list, fn ($x) => $x['units_window'] > 0));
        $half        = (int) ceil($sellerCount / 2);
        $rank        = 0;
        foreach ($list as $idx => $x) {
            if ($x['units_window'] > 0) {
                $list[$idx]['speed'] = $rank < $half ? 'mabilis' : 'matagal';
                $rank++;
            }
        }

        // 5. Estimate ng susunod na mabenta (recent week may doble ang timbang)
        $candidates = array_values(array_filter($list, fn ($x) => $x['units_window'] > 0));
        usort($candidates, fn ($a, $b) =>
            ($b['units_recent'] * 2 + $b['units_window']) <=> ($a['units_recent'] * 2 + $a['units_window'])
        );

        $predicted = [];
        foreach (array_slice($candidates, 0, self::PREDICT_LIMIT) as $x) {
            $predicted[] = [
                'product_id'   => $x['product_id'],
                'product_name' => $x['product_name'],
                'reason'       => $x['units_recent'] > 0
                    ? 'May benta sa nakaraang 7 araw (' . $x['units_recent'] . ' units)'
                    : 'May benta sa nakaraang 30 araw (' . $x['units_window'] . ' units)',
            ];
        }

        $this->api->respond([
            'window_days' => self::WINDOW_DAYS,
            'recent_days' => self::RECENT_DAYS,
            'totals'      => [
                'today' => round($totals['today'], 2),
                'month' => round($totals['month'], 2),
                'year'  => round($totals['year'], 2),
            ],
            'daily'     => array_values(array_map(fn ($d) => [
                'date'   => $d['date'],
                'amount' => round($d['amount'], 2),
                'units'  => $d['units'],
            ], $daily)),
            'monthly'   => array_values(array_map(fn ($m) => [
                'month'  => $m['month'],
                'amount' => round($m['amount'], 2),
            ], $monthly)),
            'products'  => $list,
            'predicted' => $predicted,
        ]);
    }
}