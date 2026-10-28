<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services\InvoiceMail;

use App\Models\User;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailMatchStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class InvoiceMailRecipientMatcher
{
    public const UniqueMinScore = 0.6;

    /**
     * Ordnet die vom Agent gefundenen Personen aktiven Intranet-Usern zu.
     *
     * @param  list<array{name?: ?string, email?: ?string, department?: ?string, evidence?: ?string, source?: string, weight?: float|int}>  $recipients
     * @return array{
     *   status: InvoiceMailMatchStatus,
     *   user_id: ?int,
     *   confidence: ?float,
     *   candidates: list<array{user_id: int, name: string, email: ?string, score: float, reasons: list<string>}>
     * }
     */
    public function match(array $recipients, float $agentConfidence): array
    {
        /** @var array<int, array{user: User, score: float, reasons: list<string>}> $scores */
        $scores = [];

        foreach ($recipients as $recipient) {
            $weight = $this->clamp((float) ($recipient['weight'] ?? 0.5));

            foreach ($this->usersFor($recipient) as [$user, $baseScore, $reason]) {
                $score = round($baseScore * $weight, 4);
                $entry = $scores[$user->id] ?? ['user' => $user, 'score' => 0.0, 'reasons' => []];
                $entry['score'] = max($entry['score'], $score);
                $entry['reasons'][] = $reason;
                $scores[$user->id] = $entry;
            }
        }

        $candidates = collect($scores)
            ->sortByDesc('score')
            ->values()
            ->map(fn (array $entry): array => [
                'user_id' => $entry['user']->id,
                'name' => self::displayName($entry['user']),
                'email' => $entry['user']->email,
                'score' => $entry['score'],
                'reasons' => array_values(array_unique($entry['reasons'])),
            ])
            ->take(10)
            ->all();

        if ($candidates === []) {
            return ['status' => InvoiceMailMatchStatus::None, 'user_id' => null, 'confidence' => null, 'candidates' => []];
        }

        $best = $candidates[0];
        $runnerUp = $candidates[1]['score'] ?? 0.0;
        $isClear = $best['score'] >= self::UniqueMinScore && ($best['score'] - $runnerUp) >= 0.2;

        return [
            'status' => $isClear ? InvoiceMailMatchStatus::Unique : InvoiceMailMatchStatus::Ambiguous,
            'user_id' => $isClear ? $best['user_id'] : null,
            'confidence' => $isClear ? round($best['score'] * (0.5 + 0.5 * $this->clamp($agentConfidence)), 4) : null,
            'candidates' => $candidates,
        ];
    }

    /**
     * @param  array{name?: ?string, email?: ?string}  $recipient
     * @return list<array{0: User, 1: float, 2: string}>
     */
    private function usersFor(array $recipient): array
    {
        $matches = [];
        $email = Str::lower(trim((string) ($recipient['email'] ?? '')));

        if ($email !== '' && str_contains($email, '@')) {
            $user = $this->activeUsers()->whereRaw('LOWER(email) = ?', [$email])->first();
            if ($user instanceof User) {
                $matches[] = [$user, 1.0, 'E-Mail '.$email];
            }
        }

        [$first, $last] = $this->splitName((string) ($recipient['name'] ?? ''));
        if ($last === null) {
            return $matches;
        }

        if ($first !== null) {
            $users = $this->activeUsers()
                ->where('nachname', $last)
                ->where('vorname', $first)
                ->limit(5)
                ->get();

            foreach ($users as $user) {
                $matches[] = [$user, $users->count() === 1 ? 0.95 : 0.6, 'Name '.$first.' '.$last];
            }

            if ($users->isNotEmpty()) {
                return $matches;
            }

            $users = $this->activeUsers()
                ->where('nachname', $last)
                ->where('vorname', 'like', mb_substr($first, 0, 1).'%')
                ->limit(5)
                ->get();

            foreach ($users as $user) {
                $matches[] = [$user, $users->count() === 1 ? 0.8 : 0.5, 'Nachname + Initiale '.$last];
            }

            if ($users->isNotEmpty()) {
                return $matches;
            }
        }

        $users = $this->activeUsers()->where('nachname', $last)->limit(6)->get();
        foreach ($users as $user) {
            $matches[] = [$user, $users->count() === 1 ? 0.7 : 0.35, 'Nachname '.$last];
        }

        return $matches;
    }

    /**
     * „Müller, Anna“, „Anna Müller“, „Frau Müller“, „A. Müller“.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function splitName(string $name): array
    {
        $name = trim((string) preg_replace('/\b(herrn?|frau|dr\.?|prof\.?|dipl\.?-?\w*\.?|z\.\s*hd\.?)\s*/iu', '', $name));
        $name = trim((string) preg_replace('/\s+/', ' ', $name), " \t\n\r\0\x0B,.");

        if ($name === '') {
            return [null, null];
        }

        if (str_contains($name, ',')) {
            [$last, $first] = array_map('trim', explode(',', $name, 2));

            return [$this->usableFirstName($first), $last !== '' ? $last : null];
        }

        $parts = explode(' ', $name);
        $last = array_pop($parts);
        $first = $parts === [] ? null : implode(' ', $parts);

        return [$this->usableFirstName($first), $last];
    }

    private function usableFirstName(?string $first): ?string
    {
        $first = trim((string) $first, ' .');

        return $first === '' ? null : $first;
    }

    /**
     * @return Builder<User>
     */
    private function activeUsers(): Builder
    {
        return User::query()->where('active', true);
    }

    private function clamp(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }

    public static function displayName(User $user): string
    {
        return (string) ($user->ldap_displayname ?: trim($user->vorname.' '.$user->nachname));
    }
}
