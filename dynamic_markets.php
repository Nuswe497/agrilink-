<?php
/**
 * Dynamic Markets Generator
 * Generates algorithmic recurring markets and merges them with database markets.
 */

function getUpcomingMarkets($conn, $limit = 5) {
    $markets = [];

    // 1. Fetch Manual Database Markets
    if ($conn) {
        $stmt = $conn->prepare("SELECT location, market_date, description FROM markets WHERE market_date >= CURDATE()");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $row['type'] = 'manual';
                $markets[] = $row;
            }
            $stmt->close();
        }
    }

    // 2. Generate Algorithmic Malawian Markets
    // We calculate the next occurrence of these recurring markets from today.
    
    $algorithmicRules = [
        [
            'location' => 'Mzuzu Regional Honey Fair',
            'rule' => 'next Saturday',
            'description' => 'Weekly regional gathering for honey and beeswax trading in Mzuzu.'
        ],
        [
            'location' => 'Lilongwe Farmers Market',
            'rule' => 'first Saturday of next month',
            'description' => 'Major agricultural market in the capital for premium hive products.'
        ],
        [
            'location' => 'Blantyre Agri-Expo',
            'rule' => 'next Friday',
            'description' => 'Southern region cooperative market day.'
        ],
        [
            'location' => 'Kasungu Trade Fair',
            'rule' => 'second Wednesday of this month',
            'description' => 'Central region agricultural trade and supply market.'
        ],
        [
            'location' => 'Zomba Beekeepers Meet',
            'rule' => 'third Friday of this month',
            'description' => 'Local cooperative gathering and market day in Zomba.'
        ]
    ];

    $today = new DateTime();
    $today->setTime(0,0,0);
    
    foreach ($algorithmicRules as $rule) {
        $date = new DateTime();
        $date->modify($rule['rule']);
        $date->setTime(0,0,0);
        
        // If the generated date is in the past (e.g. second Wednesday already passed this month),
        // we calculate for the NEXT month.
        if ($date < $today) {
             $ruleStr = str_replace('this month', 'next month', $rule['rule']);
             $date = new DateTime();
             $date->modify($ruleStr);
        }

        $markets[] = [
            'location' => $rule['location'],
            'market_date' => $date->format('Y-m-d'),
            'description' => $rule['description'],
            'type' => 'dynamic'
        ];
    }

    // 3. Sort chronologically
    usort($markets, function($a, $b) {
        return strtotime($a['market_date']) - strtotime($b['market_date']);
    });

    // 4. Return top $limit
    return array_slice($markets, 0, $limit);
}
?>
