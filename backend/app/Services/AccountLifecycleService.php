<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AccountLifecycleService
{
    private const ALPHABET_UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const ALPHABET_LOWER = 'abcdefghijkmnopqrstuvwxyz';
    private const ALPHABET_DIGITS = '23456789';
    private const ALPHABET_SYMBOLS = '!@#$%&*?';

    public function generateTemporaryPassword(int $length = 12): string
    {
        $length = max(8, $length);

        $chars = [
            self::ALPHABET_UPPER[random_int(0, strlen(self::ALPHABET_UPPER) - 1)],
            self::ALPHABET_LOWER[random_int(0, strlen(self::ALPHABET_LOWER) - 1)],
            self::ALPHABET_DIGITS[random_int(0, strlen(self::ALPHABET_DIGITS) - 1)],
            self::ALPHABET_SYMBOLS[random_int(0, strlen(self::ALPHABET_SYMBOLS) - 1)],
        ];

        $all = self::ALPHABET_UPPER . self::ALPHABET_LOWER . self::ALPHABET_DIGITS . self::ALPHABET_SYMBOLS;
        $maxIndex = strlen($all) - 1;

        while (count($chars) < $length) {
            $chars[] = $all[random_int(0, $maxIndex)];
        }

        // Fisher-Yates shuffle using random_int
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    public function createEskulAccount(array $data): array
    {
        $plainPassword = ! empty($data['generate_password']) || empty($data['password'])
            ? $this->generateTemporaryPassword()
            : (string) $data['password'];

        $mustChange = array_key_exists('must_change_password', $data) && $data['must_change_password'] !== null
            ? (bool) $data['must_change_password']
            : true;

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($plainPassword),
            'role' => 'owner_eskul',
            'organization_id' => $data['organization_id'],
            'must_change_password' => $mustChange,
            'password_changed_at' => null,
            'is_active' => true,
        ]);

        return [
            'user' => $user->load('organization:id,name'),
            'plain_password' => $plainPassword,
        ];
    }

    public function resetEskulPassword(User $user, array $data): array
    {
        $plainPassword = ! empty($data['generate_password']) || empty($data['password'])
            ? $this->generateTemporaryPassword()
            : (string) $data['password'];

        $mustChange = array_key_exists('must_change_password', $data) && $data['must_change_password'] !== null
            ? (bool) $data['must_change_password']
            : true;

        $user->update([
            'password' => Hash::make($plainPassword),
            'must_change_password' => $mustChange,
            'password_changed_at' => null,
        ]);

        $user->tokens()->delete();

        return [
            'user' => $user->fresh('organization:id,name'),
            'plain_password' => $plainPassword,
        ];
    }

    public function changePassword(User $user, string $newPassword, ?int $keepTokenId = null): User
    {
        $user->update([
            'password' => Hash::make($newPassword),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        $query = $user->tokens();
        if ($keepTokenId !== null) {
            $query->where('id', '!=', $keepTokenId);
        }
        $query->delete();

        return $user->fresh('organization:id,name,is_mpk,chairperson_name,chairperson_title');
    }

    public function toggleActive(User $user): User
    {
        $nextActive = ! ($user->is_active ?? true);

        $user->update(['is_active' => $nextActive]);

        if (! $nextActive) {
            $user->tokens()->delete();
        }

        return $user->fresh('organization:id,name');
    }
}

