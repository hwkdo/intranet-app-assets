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

        $users = User::query()
            ->where(function ($query) use ($suchbegriff): void {
                $query
                    ->where('vorname', 'like', '%'.$suchbegriff.'%')
                    ->orWhere('nachname', 'like', '%'.$suchbegriff.'%')
                    ->orWhere('username', 'like', '%'.$suchbegriff.'%')
                    ->orWhere('email', 'like', '%'.$suchbegriff.'%');
            })
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->limit(20)
            ->get(['id', 'vorname', 'nachname', 'username', 'email']);
        Log::info('benutzer_suchen resolved', ['total' => $users->count()]);

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

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'suchbegriff' => $schema->string()
                ->description('Suchbegriff für Vorname, Nachname, Username oder E-Mail. Beispiel: "max.mustermann" oder "mustermann@firma.de".')
                ->required(),
        ];
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
