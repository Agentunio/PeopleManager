<?php

namespace App\Services;

use App\Models\User;

class UsernameGeneratorService
{
    private const POLISH_MAP = [
        'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
        'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
        'Ą' => 'a', 'Ć' => 'c', 'Ę' => 'e', 'Ł' => 'l', 'Ń' => 'n',
        'Ó' => 'o', 'Ś' => 's', 'Ź' => 'z', 'Ż' => 'z',
    ];

    public function generate(string $firstName, string $lastName): string
    {
        $firstName = $this->normalize($firstName);
        $lastName = $this->normalize($lastName);

        for ($i = 1; $i <= mb_strlen($firstName); $i++) {
            $candidate = mb_substr($firstName, 0, $i) . '.' . $lastName;
            if (!User::where('username', $candidate)->exists()) {
                return $candidate;
            }
        }

        $base = $firstName . '.' . $lastName;
        $counter = 1;
        while (User::where('username', $base . $counter)->exists()) {
            $counter++;
        }

        return $base . $counter;
    }

    private function normalize(string $value): string
    {
        $value = strtr($value, self::POLISH_MAP);
        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z]/', '', $value);

        return $value;
    }
}
