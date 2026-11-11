<?php

namespace App\Helpers;

class PaymentMethodHelper
{
    /**
     * Map database payment method values to display labels.
     * 
     * @param string|null $method The raw method value from database
     * @return string The formatted display label
     */
    public static function getLabel(?string $method): string
    {
        if (empty($method)) {
            return 'Others';
        }

        $normalized = strtoupper(trim($method));

        return match ($normalized) {
            'CASH' => 'Cash',
            'GCASH' => 'GCash',
            'BANK' => 'Bank Transfer',
            'MANUAL' => 'Others',
            default => 'Others',
        };
    }

    /**
     * Get all available payment methods.
     * 
     * @return array Array of methods in database format
     */
    public static function getAvailableMethods(): array
    {
        return ['cash', 'gcash', 'bank', 'manual'];
    }

    /**
     * Get mapping of database values to display labels.
     * 
     * @return array Key: database value, Value: display label
     */
    public static function getMappingArray(): array
    {
        return [
            'cash' => 'Cash',
            'gcash' => 'GCash',
            'bank' => 'Bank Transfer',
            'manual' => 'Others',
        ];
    }
}
