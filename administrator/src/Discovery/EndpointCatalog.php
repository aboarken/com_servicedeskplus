<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Discovery;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * List endpoints of the ServiceDesk Plus REST API v3 that Find Endpoints knows.
 *
 * ServiceDesk Plus publishes no machine-readable API list (unlike /api/docs/ on other products),
 * so the list is built in: the endpoints this portal reads, then other list endpoints of the
 * On-Premises v3 reference. Not every build has every one; "Check which endpoints answer" shows
 * which exist on this server and whether the API technician may read them.
 */
final class EndpointCatalog
{
    /** Allowed API paths: /api/v3 and up to six lower-case segments (ids are digits). */
    public const PATH = '#^/api/v3(/[a-z0-9_]{1,60}){1,6}$#';

    /**
     * path => [description, portal page that reads it ('' = not read by the portal)].
     */
    private const ENDPOINTS = [
        '/api/v3/requests'        => ['Requests (incidents and service requests)', 'My Requests, Dashboard, My Approvals'],
        '/api/v3/approvals'       => ['Approvals of all modules', 'My Approvals, Dashboard'],
        '/api/v3/changes'         => ['Changes', 'My Approvals'],
        '/api/v3/releases'        => ['Releases', 'My Approvals'],
        '/api/v3/purchase_orders' => ['Purchase orders', 'My Approvals'],
        '/api/v3/assets'          => ['Assets (loans)', 'My Loans, Dashboard'],
        '/api/v3/announcements'   => ['Announcements', 'Announcements, Dashboard'],
        '/api/v3/users'           => ['Users (requesters and technicians)', 'User Mappings, sign-in matching'],
        '/api/v3/requesters'      => ['Requesters', ''],
        '/api/v3/technicians'     => ['Technicians', ''],
        '/api/v3/problems'        => ['Problems', ''],
        '/api/v3/projects'        => ['Projects', ''],
        '/api/v3/tasks'           => ['Tasks', ''],
        '/api/v3/solutions'       => ['Solutions (knowledge base)', ''],
        '/api/v3/request_templates' => ['Request templates', ''],
        '/api/v3/service_categories' => ['Service categories', ''],
        '/api/v3/categories'      => ['Categories', ''],
        '/api/v3/statuses'        => ['Request statuses', ''],
        '/api/v3/priorities'      => ['Priorities', ''],
        '/api/v3/urgencies'       => ['Urgencies', ''],
        '/api/v3/impacts'         => ['Impacts', ''],
        '/api/v3/levels'          => ['Levels', ''],
        '/api/v3/modes'           => ['Modes', ''],
        '/api/v3/request_types'   => ['Request types', ''],
        '/api/v3/support_groups'  => ['Support groups', ''],
        '/api/v3/sites'           => ['Sites', ''],
        '/api/v3/departments'     => ['Departments', ''],
        '/api/v3/workstations'    => ['Workstations', ''],
        '/api/v3/products'        => ['Products', ''],
        '/api/v3/product_types'   => ['Product types', ''],
        '/api/v3/vendors'         => ['Vendors', ''],
        '/api/v3/contracts'       => ['Contracts', ''],
    ];

    /**
     * @return list<array{path: string, summary: string, portal: string}>
     */
    public static function all(): array
    {
        $out = [];

        foreach (self::ENDPOINTS as $path => [$summary, $portal]) {
            $out[] = ['path' => $path, 'summary' => $summary, 'portal' => $portal];
        }

        return $out;
    }

    /**
     * Paths the portal itself reads, for Diagnostics.
     *
     * @return list<string>
     */
    public static function portalPaths(): array
    {
        return array_keys(array_filter(self::ENDPOINTS, static fn (array $e): bool => $e[1] !== ''));
    }

    /**
     * A typed path in canonical form: "requests", "/requests" and "api/v3/requests/" all become
     * /api/v3/requests. Null when the result is not an allowed path.
     */
    public static function normalize(string $path): ?string
    {
        $path = strtolower(trim($path));
        $path = (string) preg_replace('#[?\#].*$#', '', $path);
        $path = '/' . trim($path, '/');

        if (str_starts_with($path . '/', '/api/') && !str_starts_with($path . '/', '/api/v3/')) {
            return null;
        }

        if (!str_starts_with($path . '/', '/api/v3/')) {
            $path = '/api/v3' . $path;
        }

        return preg_match(self::PATH, $path) ? $path : null;
    }
}
