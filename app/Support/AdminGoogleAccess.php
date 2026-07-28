<?php

namespace App\Support;

class AdminGoogleAccess
{
    public function role(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $this->validConfiguration()) {
            return null;
        }

        if (in_array($email, $this->emails('super_admin_emails'), true)) {
            return 'super_admin';
        }

        return in_array($email, $this->emails('admin_emails'), true) ? 'admin' : null;
    }

    public function validConfiguration(): bool
    {
        $all = [...$this->emails('admin_emails'), ...$this->emails('super_admin_emails')];

        return $all !== [] && collect($all)->every(
            fn (string $email): bool => $email === strtolower(trim($email))
                && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        );
    }

    /** @return array<int, string> */
    private function emails(string $key): array
    {
        $value = config("admin_access.{$key}", []);

        return is_array($value) && collect($value)->every(fn (mixed $email): bool => is_string($email))
            ? array_values(array_unique($value))
            : [];
    }
}
