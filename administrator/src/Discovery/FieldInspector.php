<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Discovery;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Describes sample rows of a ServiceDesk Plus endpoint by field path and kind of value (date,
 * number, text, list, ...), never by the values themselves.
 *
 * Only texts of status-like lookups (status, priority, urgency, approval status, ...) are listed,
 * and only when they do not belong to a person (requester, technician, approver, ...). Names,
 * e-mails, subjects, descriptions and other personal values are never shown.
 */
final class FieldInspector
{
    /** Field paths whose texts may be shown: the lookup itself or its .name. */
    private const STATUS_PATH = '/(^|\.)(status|approval_status|priority|urgency|impact|request_type|mode|level|state|stage|asset_state|product_type|type|approval_level|risk|change_type|release_type)(\.name)?$/';

    /** Path segments that belong to a person: texts under them are never shown. */
    private const PERSONAL = '/^(requester|user|users|technician|approver|owner|on_behalf_of|contact|vendor|created_by|updated_by|last_updated_by|deleted_by|approved_by|rejected_by|resolved_by|closed_by|assigned_by|sent_by|from|to|cc|reporter|initiator|.*_by)$/';

    /**
     * @param list<array<mixed>> $rows
     *
     * @return array<string, array{type: string, values: list<string>}> field path => description
     */
    public static function inspect(array $rows): array
    {
        $seen = [];

        foreach (\array_slice($rows, 0, 50) as $row) {
            if (\is_array($row)) {
                self::walk($row, '', 0, $seen);
            }
        }

        $out = [];

        foreach ($seen as $path => $info) {
            arsort($info['types']);
            $types = array_keys($info['types']);
            $type  = $types[0] === 'empty' && \count($types) > 1 ? $types[1] : $types[0];

            $values = [];

            if (self::mayShowValues($path) && \count($info['values']) <= 20) {
                $values = array_values(array_unique($info['values']));
                sort($values);
            }

            $out[$path] = ['type' => $type, 'values' => $values];
        }

        ksort($out);

        return $out;
    }

    /** True for status-like paths that are not part of a person's record. */
    public static function mayShowValues(string $path): bool
    {
        if (!preg_match(self::STATUS_PATH, $path)) {
            return false;
        }

        // Drop the lookup itself ("status", or "status" and "name"); no parent may be a person.
        $segments = explode('.', $path);
        array_splice($segments, str_ends_with($path, '.name') ? -2 : -1);

        foreach ($segments as $segment) {
            if (preg_match(self::PERSONAL, $segment)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, array{types: array<string, int>, values: list<string>}> $seen
     */
    private static function walk(array $row, string $prefix, int $depth, array &$seen): void
    {
        foreach ($row as $key => $value) {
            if (!\is_string($key) || !preg_match('/^[A-Za-z0-9_]{1,60}$/', $key)) {
                continue;
            }

            $path = $prefix === '' ? $key : $prefix . '.' . $key;

            if (\is_array($value) && self::isDateObject($value)) {
                self::note($seen, $path, 'datetime');

                continue;
            }

            if (\is_array($value)) {
                if (array_is_list($value)) {
                    self::note($seen, $path, $value === [] ? 'empty' : 'list');
                } elseif ($depth < 3) {
                    self::walk($value, $path, $depth + 1, $seen);
                }

                continue;
            }

            $type = self::type($value);
            self::note($seen, $path, $type);

            if ($type === 'text' || $type === 'number') {
                $text = trim((string) $value);

                if ($text !== '' && mb_strlen($text) <= 40 && \count($seen[$path]['values']) <= 20 && !\in_array($text, $seen[$path]['values'], true)) {
                    $seen[$path]['values'][] = $text;
                }
            }
        }
    }

    /**
     * @param array<string, array{types: array<string, int>, values: list<string>}> $seen
     */
    private static function note(array &$seen, string $path, string $type): void
    {
        $seen[$path]['types'][$type] = ($seen[$path]['types'][$type] ?? 0) + 1;
        $seen[$path]['values'] ??= [];
    }

    /** ServiceDesk Plus dates: {"value": "<epoch ms>", "display_value": "..."}. */
    private static function isDateObject(array $value): bool
    {
        return isset($value['value']) && \is_scalar($value['value']) && preg_match('/^[0-9]{10,16}$/', (string) $value['value'])
            && array_diff(array_keys($value), ['value', 'display_value']) === [];
    }

    private static function type(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'empty';
        }

        if (\is_bool($value)) {
            return 'bool';
        }

        if (\is_int($value) || \is_float($value)) {
            return 'number';
        }

        $v = trim((string) $value);

        return match (true) {
            \in_array(strtolower($v), ['true', 'false'], true)                 => 'bool',
            (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)                       => 'date',
            (bool) preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{1,2}:\d{2}/', $v)       => 'datetime',
            (bool) preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $v)                  => 'time',
            is_numeric($v)                                                       => 'number',
            default                                                              => 'text',
        };
    }
}
