<?php

declare(strict_types=1);

namespace Yawasla\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Yawasla\Core\AppShell;

/**
 * Toute URL hors API (GET) : page du front, avec l'état de l'app (GET /api/) déjà injecté, ce qui
 * évite au front un aller-retour au démarrage.
 */
final class ShellAction
{
    public function __construct(private App $app, private AppShell $shell, private Urls $urls)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->shell->exists()) {
            $response->getBody()->write('Front non compilé : en dev, utilisez le serveur Vite (npm run dev dans front/).');

            return $response->withStatus(404)->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        $boot = ['basePath' => $this->urls->basePath, 'apiBase' => $this->urls->apiBase];

        // Sous-requête interne : même logique et même réponse que si le front appelait GET /api/.
        // Requête neuve : la requête courante porte déjà son résultat de routage (cette route), que
        // Slim réutiliserait → boucle infinie
        $statusRequest = (new ServerRequestFactory())->createServerRequest(
            'GET',
            $request->getUri()->withPath($this->urls->apiBase . '/')->withQuery(''),
            $request->getServerParams(),
        );
        $statusResponse = $this->app->handle($statusRequest);

        if ($statusResponse->getStatusCode() === 200) {
            $boot['status'] = json_decode((string) $statusResponse->getBody(), true);
        }

        $response->getBody()->write($this->shell->render($this->urls->basePath, $boot));

        return $response
            // Base indisponible : la page est servie sans état, le front interroge l'API et affiche l'erreur
            ->withStatus($statusResponse->getStatusCode() === 503 ? 503 : 200)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            // L'état injecté change avec la base : la page ne doit pas être mise en cache
            ->withHeader('Cache-Control', 'no-store');
    }
}
