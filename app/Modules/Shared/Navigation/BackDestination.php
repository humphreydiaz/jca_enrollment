<?php

namespace App\Modules\Shared\Navigation;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Throwable;

/**
 * Works out where the in-page "Back" control should go when the browser has no
 * usable in-app trail (a page opened directly, a bookmark, a new tab).
 *
 * The answer is the nearest *parent* page in the URL hierarchy that is a real
 * GET route, e.g. /registrar/students/{ref}/edit -> /registrar/students/{ref}
 * -> /registrar/students. Only same-application paths built from the current
 * request are ever returned, so this cannot be used as an open redirect.
 *
 * The browser-side trail in resources/js/app.js takes priority over this value;
 * this is only the safe fallback.
 */
class BackDestination
{
    /**
     * Route-name prefixes whose logical parent is not their URL parent.
     *
     * @var array<string, array{0: string, 1: string|null}>
     */
    private const OVERRIDES = [
        'identity.users.reissue.' => ['identity.admin.users.index', 'admin'],
    ];

    public function __construct(private readonly Request $request)
    {
    }

    /**
     * Whether the current page is a "home" page that should not show Back.
     */
    public function isRoot(): bool
    {
        $name = (string) $this->request->route()?->getName();

        return $name === 'identity.dashboard' || str_ends_with($name, '.dashboard');
    }

    public function url(): string
    {
        $dashboard = RouteFacade::has('identity.dashboard') ? route('identity.dashboard') : url('/');
        $name = (string) $this->request->route()?->getName();
        $user = $this->request->user();

        foreach (self::OVERRIDES as $prefix => [$target, $requiredRole]) {
            if (str_starts_with($name, $prefix) && RouteFacade::has($target)) {
                if ($requiredRole === null || ($user !== null && $user->hasRole($requiredRole))) {
                    return route($target);
                }

                return $dashboard;
            }
        }

        $segments = array_values(array_filter(
            explode('/', trim($this->request->path(), '/')),
            fn (string $segment) => $segment !== '',
        ));

        for ($length = count($segments) - 1; $length >= 1; $length--) {
            $candidate = '/'.implode('/', array_slice($segments, 0, $length));
            $route = $this->matchGetRoute($candidate);

            if ($route !== null && ! $this->isUnsuitable($route)) {
                return url($candidate);
            }
        }

        return $dashboard;
    }

    private function matchGetRoute(string $path): ?Route
    {
        try {
            $route = RouteFacade::getRoutes()->match(Request::create($path, 'GET'));
        } catch (Throwable) {
            return null;
        }

        return in_array('GET', $route->methods(), true) ? $route : null;
    }

    /**
     * Parents that are not meant to be landed on (downloads, prints, previews,
     * JSON endpoints, the public landing page).
     */
    private function isUnsuitable(Route $route): bool
    {
        $name = (string) $route->getName();

        if ($name === '' || $route->uri() === '/') {
            return true;
        }

        foreach (['.print', '.download', '.preview', '.export'] as $marker) {
            if (str_contains($name, $marker)) {
                return true;
            }
        }

        return false;
    }
}
