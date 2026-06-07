<?php
/**
 * Currency class - Quản lý đổi tiền tệ
 * Hỗ trợ: VND, USD, EUR, JPY, KRW, THB, CNY, GBP
 */
class Currency {
    
    /**
     * Danh sách tiền tệ hỗ trợ
     */
    public static function getSupportedCurrencies() {
        return [
            'VND' => ['name' => 'Việt Nam Đồng', 'symbol' => 'VNĐ', 'locale' => 'vi-VN', 'decimals' => 0],
            'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'locale' => 'en-US', 'decimals' => 2],
            'EUR' => ['name' => 'Euro', 'symbol' => '€', 'locale' => 'de-DE', 'decimals' => 2],
            'JPY' => ['name' => 'Japanese Yen', 'symbol' => '¥', 'locale' => 'ja-JP', 'decimals' => 0],
            'KRW' => ['name' => 'Korean Won', 'symbol' => '₩', 'locale' => 'ko-KR', 'decimals' => 0],
            'THB' => ['name' => 'Thai Baht', 'symbol' => '฿', 'locale' => 'th-TH', 'decimals' => 2],
            'CNY' => ['name' => 'Chinese Yuan', 'symbol' => '¥', 'locale' => 'zh-CN', 'decimals' => 2],
            'GBP' => ['name' => 'British Pound', 'symbol' => '£', 'locale' => 'en-GB', 'decimals' => 2],
        ];
    }
    
    /**
     * Tỷ giá fallback (VND to X) - cập nhật gần đúng
     */
    private static function getFallbackRates() {
        return [
            'VND' => 1,
            'USD' => 0.0000392,    // 1 VND ≈ 0.0000392 USD
            'EUR' => 0.0000361,    // 1 VND ≈ 0.0000361 EUR
            'JPY' => 0.00610,      // 1 VND ≈ 0.00610 JPY
            'KRW' => 0.0543,       // 1 VND ≈ 0.0543 KRW
            'THB' => 0.00136,      // 1 VND ≈ 0.00136 THB
            'CNY' => 0.000285,     // 1 VND ≈ 0.000285 CNY
            'GBP' => 0.0000307,    // 1 VND ≈ 0.0000307 GBP
        ];
    }
    
    /**
     * Lấy tỷ giá từ API (cache trong session 1 giờ)
     */
    public static function getExchangeRates($baseCurrency = 'VND') {
        $cacheKey = 'exchange_rates_' . $baseCurrency;
        $cacheTimeKey = 'exchange_rates_time_' . $baseCurrency;
        
        // Check session cache (1 hour)
        if (isset($_SESSION[$cacheKey]) && isset($_SESSION[$cacheTimeKey])) {
            if (time() - $_SESSION[$cacheTimeKey] < 3600) {
                return $_SESSION[$cacheKey];
            }
        }
        
        // Try API
        $rates = self::fetchRatesFromAPI($baseCurrency);
        
        if ($rates) {
            $_SESSION[$cacheKey] = $rates;
            $_SESSION[$cacheTimeKey] = time();
            return $rates;
        }
        
        // Fallback to hardcoded rates
        return self::getFallbackRates();
    }
    
    /**
     * Gọi API exchangerate-api.com
     */
    private static function fetchRatesFromAPI($baseCurrency) {
        $url = "https://open.er-api.com/v6/latest/{$baseCurrency}";
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'method' => 'GET',
                'header' => 'Accept: application/json'
            ]
        ]);
        
        try {
            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                return null;
            }
            
            $data = json_decode($response, true);
            if (!$data || $data['result'] !== 'success') {
                return null;
            }
            
            // Extract rates for supported currencies only
            $supported = array_keys(self::getSupportedCurrencies());
            $rates = [];
            foreach ($supported as $code) {
                if (isset($data['rates'][$code])) {
                    $rates[$code] = $data['rates'][$code];
                }
            }
            
            return !empty($rates) ? $rates : null;
        } catch (Exception $e) {
            error_log("Currency API Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Chuyển đổi số tiền từ VND sang tiền tệ khác
     */
    public static function convert($amount, $fromCurrency = 'VND', $toCurrency = 'VND') {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }
        
        $rates = self::getExchangeRates($fromCurrency);
        
        if (isset($rates[$toCurrency])) {
            return $amount * $rates[$toCurrency];
        }
        
        // Fallback calculation via VND
        $fallback = self::getFallbackRates();
        if ($fromCurrency === 'VND' && isset($fallback[$toCurrency])) {
            return $amount * $fallback[$toCurrency];
        }
        
        return $amount; // Cannot convert, return original
    }
    
    /**
     * Format số tiền theo tiền tệ
     */
    public static function format($amount, $currencyCode = 'VND') {
        $currencies = self::getSupportedCurrencies();
        $info = $currencies[$currencyCode] ?? $currencies['VND'];
        
        $decimals = $info['decimals'];
        $formatted = number_format($amount, $decimals, ',', '.');
        
        // Position symbol based on currency
        switch ($currencyCode) {
            case 'USD':
                return '$' . number_format($amount, $decimals, '.', ',');
            case 'EUR':
                return '€' . number_format($amount, $decimals, ',', '.');
            case 'GBP':
                return '£' . number_format($amount, $decimals, '.', ',');
            case 'JPY':
                return '¥' . number_format($amount, $decimals, '.', ',');
            case 'KRW':
                return '₩' . number_format($amount, $decimals, ',', '.');
            case 'THB':
                return '฿' . number_format($amount, $decimals, '.', ',');
            case 'CNY':
                return '¥' . number_format($amount, $decimals, '.', ',');
            case 'VND':
            default:
                return $formatted . ' VNĐ';
        }
    }
    
    /**
     * Lấy currency hiện tại của user từ DB
     */
    public static function getUserCurrency($user_id, $db) {
        // Check session first
        if (isset($_SESSION['user_currency'])) {
            return $_SESSION['user_currency'];
        }
        
        $query = "SELECT currency FROM users WHERE id = ?";
        $result = $db->execute($query, [$user_id]);
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $currency = $row['currency'] ?? 'VND';
            $_SESSION['user_currency'] = $currency;
            return $currency;
        }
        
        return 'VND';
    }
    
    /**
     * Cập nhật currency cho user
     */
    public static function setUserCurrency($user_id, $db, $currency) {
        $supported = array_keys(self::getSupportedCurrencies());
        if (!in_array($currency, $supported)) {
            return false;
        }
        
        $query = "UPDATE users SET currency = ? WHERE id = ?";
        $result = $db->execute($query, [$currency, $user_id]);
        
        if ($result !== false) {
            $_SESSION['user_currency'] = $currency;
            // Clear cached rates when currency changes
            unset($_SESSION['exchange_rates_VND']);
            unset($_SESSION['exchange_rates_time_VND']);
            return true;
        }
        
        return false;
    }
}
