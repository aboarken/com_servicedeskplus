<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Users;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The logged-in user's own ServiceDesk Plus record, as shown at the top of the Dashboard:
 * name, employee ID, department and job title (empty when ServiceDesk Plus has none).
 */
final class UserProfile
{
    public function __construct(
        public readonly string $name,
        public readonly string $employeeId,
        public readonly string $department,
        public readonly string $jobTitle
    ) {
    }

    /**
     * From GET /api/v3/users/{id} ("Get an user": name, employee_id, department.name, jobtitle).
     *
     * @param  array<string, mixed>  $user
     */
    public static function fromSdp(array $user): self
    {
        $text = static function (mixed $value): string {
            if (\is_array($value)) {
                $value = $value['name'] ?? $value['display_value'] ?? null;
            }

            return \is_scalar($value) ? mb_substr(trim((string) $value), 0, 200) : '';
        };

        return new self(
            $text($user['name'] ?? null),
            $text($user['employee_id'] ?? null),
            $text($user['department'] ?? null),
            $text($user['jobtitle'] ?? $user['job_title'] ?? null)
        );
    }

    /** @return array{name: string, employee_id: string, department: string, job_title: string} */
    public function toArray(): array
    {
        return ['name' => $this->name, 'employee_id' => $this->employeeId, 'department' => $this->department, 'job_title' => $this->jobTitle];
    }

    /** @param array<string, mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self((string) ($a['name'] ?? ''), (string) ($a['employee_id'] ?? ''), (string) ($a['department'] ?? ''), (string) ($a['job_title'] ?? ''));
    }
}
