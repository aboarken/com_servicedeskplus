<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Announcements;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


use Psr\Log\LoggerInterface;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\ListInfo;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpClient;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Cache\UserCacheInterface;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Identity\Identity;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Support\SdpFields;

/**
 * ServiceDesk Plus announcements the user may see (Announcements folder of the On-Premises
 * Postman collection: GET /api/v3/announcements and /api/v3/announcements/{id}).
 *
 * The service key sees every announcement, so the portal applies ServiceDesk Plus' own rules for
 * requesters, and hides an announcement whenever it cannot be sure:
 *  - "is_public" must be true (otherwise the announcement is for technicians only);
 *  - the display period (from_date / to_date) must include now;
 *  - an announcement limited to user groups is shown only when the user's ServiceDesk Plus record
 *    lists one of those groups.
 */
final class AnnouncementService
{
    /** Newest announcements read from ServiceDesk Plus. */
    public const MAX_ITEMS = 100;

    /** @var array<string, list<string>> SDP user id => user group ids, per page load */
    private array $userGroups = [];

    /**
     * @param  \Closure(): SdpClient  $clientFactory
     * @param  \Closure(): int|null  $clock
     */
    public function __construct(
        private readonly \Closure $clientFactory,
        private readonly UserCacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly ?\Closure $clock = null
    ) {
    }

    /**
     * Newest first.
     *
     * @return list<Announcement>
     *
     * @throws SdpException
     */
    public function listForUser(Identity $identity): array
    {
        $cached = $this->cache->get($identity, 'announcements:list');

        if ($cached !== null) {
            return array_map(static fn (array $a) => Announcement::fromArray($a), $cached);
        }

        $client = ($this->clientFactory)();
        $page   = $client->listAnnouncements(new ListInfo(rowCount: self::MAX_ITEMS, sortField: 'id', sortOrder: 'desc'));
        $now    = $this->now();
        $result = [];

        foreach ($page->items as $row) {
            // The list may leave out the group list of a restricted announcement; read the full one.
            if (self::isRestricted($row) && !\is_array($row['user_groups'] ?? null) && self::showable($row, $now)) {
                try {
                    $row = $client->getAnnouncement(SdpFields::string($row, 'id'));
                } catch (SdpException | \InvalidArgumentException $e) {
                    if ($e instanceof SdpException && \in_array($e->getKind(), [SdpException::NETWORK, SdpException::AUTH], true)) {
                        throw $e;
                    }

                    continue;
                }
            }

            if ($this->isVisible($row, $identity, $client, $now)) {
                $result[] = Announcement::fromSdp($row);
            }
        }

        usort($result, static fn (Announcement $a, Announcement $b): int => [$b->date() ?? 0, (int) $b->id] <=> [$a->date() ?? 0, (int) $a->id]);

        $this->cache->set($identity, 'announcements:list', array_map(static fn (Announcement $a) => $a->toArray(), $result));

        return $result;
    }

    /**
     * @throws AnnouncementNotFound  the announcement does not exist or is not shown to the user
     * @throws SdpException
     */
    public function getForUser(Identity $identity, string $id): Announcement
    {
        try {
            $id = SdpClient::id($id);
        } catch (\InvalidArgumentException) {
            throw new AnnouncementNotFound();
        }

        $client = ($this->clientFactory)();

        try {
            $row = $client->getAnnouncement($id);
        } catch (SdpException $e) {
            if ($e->getKind() === SdpException::NOT_FOUND) {
                throw new AnnouncementNotFound();
            }

            throw $e;
        }

        if (SdpFields::string($row, 'id') !== $id || !$this->isVisible($row, $identity, $client, $this->now())) {
            throw new AnnouncementNotFound();
        }

        return Announcement::fromSdp($row);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isVisible(array $row, Identity $identity, SdpClient $client, int $now): bool
    {
        if (!self::showable($row, $now)) {
            return false;
        }

        if (!self::isRestricted($row)) {
            return true;
        }

        $groups = self::ids($row['user_groups'] ?? null);

        return $groups !== [] && array_intersect($groups, $this->groupsOf($identity, $client)) !== [];
    }

    /**
     * Public and inside its display period (group limits are checked separately).
     *
     * @param  array<string, mixed>  $row
     */
    private static function showable(array $row, int $now): bool
    {
        if (!filter_var($row['is_public'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $from = SdpFields::timestamp($row, 'from_date');
        $to   = SdpFields::timestamp($row, 'to_date');

        return ($from === null || $from <= $now) && ($to === null || $to >= $now);
    }

    /** @param array<string, mixed> $row */
    private static function isRestricted(array $row): bool
    {
        return filter_var($row['has_user_group'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || self::ids($row['user_groups'] ?? null) !== [];
    }

    /**
     * User group ids on the user's ServiceDesk Plus record; none when they cannot be read.
     *
     * @return list<string>
     */
    private function groupsOf(Identity $identity, SdpClient $client): array
    {
        $userId = $identity->sdpUserId();

        if (!isset($this->userGroups[$userId])) {
            try {
                $user = $client->getUser($userId);
                $this->userGroups[$userId] = self::ids($user['user_groups'] ?? null);
            } catch (SdpException $e) {
                if (\in_array($e->getKind(), [SdpException::NETWORK, SdpException::AUTH], true)) {
                    throw $e;
                }

                $this->logger->warning(\sprintf('User groups of ServiceDesk Plus user %s could not be read (%s)', $userId, $e->getKind()));
                $this->userGroups[$userId] = [];
            }
        }

        return $this->userGroups[$userId];
    }

    /**
     * @return list<string>
     */
    private static function ids(mixed $list): array
    {
        if (!\is_array($list)) {
            return [];
        }

        $ids = [];

        foreach ($list as $item) {
            $id = \is_array($item) ? ($item['id'] ?? null) : $item;

            if (\is_scalar($id) && preg_match('/^[0-9]{1,20}$/', (string) $id)) {
                $ids[] = (string) $id;
            }
        }

        return $ids;
    }

    private function now(): int
    {
        return $this->clock !== null ? ($this->clock)() : time();
    }
}
