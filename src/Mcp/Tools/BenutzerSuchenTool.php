<?php

namespace Hwkdo\IntranetAppAssets\Mcp\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld]
class BenutzerSuchenTool extends Tool
{
    protected string $name = 'benutzer_suchen';

    protected string $description = 'Sucht Benutzer anhand von Vorname, Nachname, Username oder E-Mail und liefert die passenden Benutzer-IDs für die weitere Asset-Anlage.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $suchbegriff = trim((string) $request->get('suchbegriff', ''));
        Log::info('benutzer_suchen called', ['suchbegriff' => $suchbegriff]);

        if ($suchbegriff === '') {
            Log::warning('benutzer_suchen missing suchbegriff');
            return Response::error('Das Feld "suchbegriff" ist erforderlich. Suche z. B. nach Vorname, Nachname, Username oder E-Mail.');
        }

        $suchbegriffe = $this->buildSuchbegriffe($suchbegriff);

        $users = $this->searchStrict($suchbegriff, $suchbegriffe);
        $strategy = 'strict';

        if ($users->isEmpty()) {
            $users = $this->searchFallback($suchbegriff, $suchbegriffe);
            $strategy = 'fallback';
        }

        Log::info('benutzer_suchen resolved', [
            'total' => $users->count(),
            'strategy' => $strategy,
        ]);

        return Response::structured([
            'query' => $suchbegriff,
            'total' => $users->count(),
            'users' => $users->map(fn (User $user): array => [
                'id' => $user->id,
                'vorname' => (string) ($user->vorname ?? ''),
                'nachname' => (string) ($user->nachname ?? ''),
                'username' => (string) ($user->username ?? ''),
                'email' => (string) ($user->email ?? ''),
            ])->values()->all(),
        ]);
    }

    private function searchStrict(string $suchbegriff, array $suchbegriffe)
    {
        return User::query()
            ->where(function ($query) use ($suchbegriff): void {
                $query
                    ->whereRaw("CONCAT(COALESCE(vorname, ''), ' ', COALESCE(nachname, '')) LIKE ?", ['%'.$suchbegriff.'%'])
                    ->orWhereRaw("CONCAT(COALESCE(nachname, ''), ' ', COALESCE(vorname, '')) LIKE ?", ['%'.$suchbegriff.'%'])
                    ->orWhere('username', 'like', '%'.$suchbegriff.'%')
                    ->orWhere('email', 'like', '%'.$suchbegriff.'%');
            })
            ->where(function ($query) use ($suchbegriffe): void {
                foreach ($suchbegriffe as $teil) {
                    $query->where(function ($tokenQuery) use ($teil): void {
                        $tokenQuery
                            ->where('vorname', 'like', '%'.$teil.'%')
                            ->orWhere('nachname', 'like', '%'.$teil.'%')
                            ->orWhere('username', 'like', '%'.$teil.'%')
                            ->orWhere('email', 'like', '%'.$teil.'%');
                    });
                }
            })
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->limit(20)
            ->get(['id', 'vorname', 'nachname', 'username', 'email']);
    }

    private function searchFallback(string $suchbegriff, array $suchbegriffe)
    {
        return User::query()
            ->where(function ($query) use ($suchbegriff, $suchbegriffe): void {
                $query
                    ->where('vorname', 'like', '%'.$suchbegriff.'%')
                    ->orWhere('nachname', 'like', '%'.$suchbegriff.'%')
                    ->orWhere('username', 'like', '%'.$suchbegriff.'%')
                    ->orWhere('email', 'like', '%'.$suchbegriff.'%')
                    ->orWhereRaw("CONCAT(COALESCE(vorname, ''), ' ', COALESCE(nachname, '')) LIKE ?", ['%'.$suchbegriff.'%'])
                    ->orWhereRaw("CONCAT(COALESCE(nachname, ''), ' ', COALESCE(vorname, '')) LIKE ?", ['%'.$suchbegriff.'%']);

                foreach ($suchbegriffe as $teil) {
                    $query
                        ->orWhere('vorname', 'like', '%'.$teil.'%')
                        ->orWhere('nachname', 'like', '%'.$teil.'%')
                        ->orWhere('username', 'like', '%'.$teil.'%')
                        ->orWhere('email', 'like', '%'.$teil.'%');
                }
            })
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->limit(20)
            ->get(['id', 'vorname', 'nachname', 'username', 'email']);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'suchbegriff' => $schema->string()
                ->description('Suchbegriff für Vorname, Nachname, Vollname, Username oder E-Mail. Beispiele: "Max Mustermann", "max.mustermann", "mustermann@firma.de".')
                ->required(),
        ];
    }

    /**
     * @return list<string>
     */
    private function buildSuchbegriffe(string $suchbegriff): array
    {
        $teile = preg_split('/\s+/u', trim($suchbegriff)) ?: [];

        return collect($teile)
            ->map(static fn (string $teil): string => trim($teil))
            ->filter(static fn (string $teil): bool => $teil !== '' && mb_strlen($teil) >= 2)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Der übergebene Suchbegriff.')
                ->required(),
            'total' => $schema->integer()
                ->description('Anzahl gefundener Benutzer.')
                ->required(),
            'users' => $schema->array()
                ->items($schema->object([
                    'id' => $schema->integer()->description('Eindeutige Benutzer-ID.')->required(),
                    'vorname' => $schema->string()->description('Vorname des Benutzers.')->required(),
                    'nachname' => $schema->string()->description('Nachname des Benutzers.')->required(),
                    'username' => $schema->string()->description('Technischer Benutzername.')->required(),
                    'email' => $schema->string()->description('E-Mail-Adresse.')->required(),
                ]))
                ->description('Gefundene Benutzerdatensätze zur Auswahl der korrekten user_id.')
                ->required(),
        ];
    }
}
