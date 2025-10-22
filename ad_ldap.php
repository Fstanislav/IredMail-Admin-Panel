<?php

declare(strict_types=1);

// Active Directory configuration
const AD_SERVER_IP = '192.168.0.3';
const AD_ADMIN_USER_PRIMARY = 'Администратор';
const AD_ADMIN_USER_EN = 'Administrator';
const AD_ADMIN_PASSWORD = 'Garena182025';

/**
 * Connects to LDAP/AD and binds as the administrator.
 * Falls back across multiple bind formats and auto-detects base DN via RootDSE.
 *
 * @param bool $useLdaps Whether to use LDAPS (required for password changes)
 * @return array{0: LDAP\Connection, 1: string} [$ldap, $baseDn]
 * @throws RuntimeException on connection or bind failure
 */
function ad_connect_and_bind(bool $useLdaps = false): array
{
    $host = $useLdaps ? 'ldaps://' . AD_SERVER_IP . ':636' : 'ldap://' . AD_SERVER_IP;

    $ldap = ldap_connect($host);
    if ($ldap === false) {
        throw new RuntimeException('Не удалось подключиться к LDAP-серверу.');
    }

    // Standard options
    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

    if ($useLdaps) {
        // In many environments AD uses self-signed certs; disable cert validation if needed
        if (defined('LDAP_OPT_X_TLS_REQUIRE_CERT')) {
            ldap_set_option($ldap, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
        }
    }

    // Read RootDSE to discover base DN
    $baseDn = '';
    $sr = @ldap_read($ldap, '', '(objectClass=*)', ['defaultNamingContext', 'rootDomainNamingContext']);
    if ($sr !== false) {
        $entries = ldap_get_entries($ldap, $sr);
        if ($entries !== false && $entries['count'] > 0) {
            $dnCandidate = $entries[0]['defaultnamingcontext'][0] ?? ($entries[0]['rootdomainnamingcontext'][0] ?? '');
            if (is_string($dnCandidate) && $dnCandidate !== '') {
                $baseDn = $dnCandidate;
            }
        }
    }

    if ($baseDn === '') {
        // Fallback (will limit search capabilities later, but bind can still succeed)
        $baseDn = '';
    }

    // Derive domain forms from base DN
    $domainFqdn = '';
    $netbios = '';
    if ($baseDn !== '') {
        $dcParts = [];
        foreach (explode(',', $baseDn) as $part) {
            $part = trim($part);
            if (stripos($part, 'DC=') === 0) {
                $dcParts[] = substr($part, 3);
            }
        }
        if ($dcParts) {
            $domainFqdn = implode('.', $dcParts);
            $netbios = strtoupper($dcParts[0]);
        }
    }

    $adminPassword = AD_ADMIN_PASSWORD;

    $candidates = [];
    if ($domainFqdn !== '') {
        $candidates[] = AD_ADMIN_USER_PRIMARY . '@' . $domainFqdn;
        $candidates[] = AD_ADMIN_USER_EN . '@' . $domainFqdn;
    }
    if ($netbios !== '') {
        $candidates[] = $netbios . '\\' . AD_ADMIN_USER_PRIMARY;
        $candidates[] = $netbios . '\\' . AD_ADMIN_USER_EN;
    }
    $candidates[] = AD_ADMIN_USER_PRIMARY;
    $candidates[] = AD_ADMIN_USER_EN;
    if ($baseDn !== '') {
        $candidates[] = 'CN=' . AD_ADMIN_USER_EN . ',CN=Users,' . $baseDn;
        $candidates[] = 'CN=' . AD_ADMIN_USER_PRIMARY . ',CN=Users,' . $baseDn;
    }

    $bindOk = false;
    $bindError = '';
    foreach ($candidates as $bindUser) {
        if (@ldap_bind($ldap, $bindUser, $adminPassword)) {
            $bindOk = true;
            break;
        }
        $bindError = ldap_error($ldap);
    }

    if (!$bindOk) {
        @ldap_unbind($ldap);
        throw new RuntimeException('Ошибка привязки к AD: ' . ($bindError ?: 'cred/format'));
    }

    return [$ldap, $baseDn];
}

/**
 * Searches AD for users.
 *
 * @param string $searchTerm Optional search term for cn/displayName/sAMAccountName/UPN/mail
 * @return array<int, array<string, mixed>> LDAP entries
 */
function ad_get_users(string $searchTerm = ''): array
{
    [$ldap, $baseDn] = ad_connect_and_bind(false);

    if ($baseDn === '') {
        // If base DN unknown, try configuration-less discovery via RootDSE again
        $sr = @ldap_read($ldap, '', '(objectClass=*)', ['defaultNamingContext']);
        if ($sr !== false) {
            $entries = ldap_get_entries($ldap, $sr);
            if ($entries !== false && $entries['count'] > 0) {
                $baseDnCandidate = $entries[0]['defaultnamingcontext'][0] ?? '';
                if (is_string($baseDnCandidate) && $baseDnCandidate !== '') {
                    $baseDn = $baseDnCandidate;
                }
            }
        }
        if ($baseDn === '') {
            throw new RuntimeException('Не удалось определить base DN из RootDSE.');
        }
    }

    $baseFilter = '(&(objectCategory=person)(objectClass=user))';

    $filter = $baseFilter;
    $searchTerm = trim($searchTerm);
    if ($searchTerm !== '') {
        $escaped = ldap_escape($searchTerm, '', LDAP_ESCAPE_FILTER);
        $filter = '(&' . $baseFilter . '(|(cn=*' . $escaped . '*)(displayName=*' . $escaped . '*)(sAMAccountName=*' . $escaped . '*)(userPrincipalName=*' . $escaped . '*)(mail=*' . $escaped . '*)))';
    }

    $attrs = ['cn', 'displayName', 'sAMAccountName', 'userPrincipalName', 'mail', 'distinguishedName'];
    $sr = @ldap_search($ldap, $baseDn, $filter, $attrs, 0, 2000);
    if ($sr === false) {
        $err = ldap_error($ldap);
        @ldap_unbind($ldap);
        throw new RuntimeException('Ошибка поиска в AD: ' . $err);
    }

    $entries = ldap_get_entries($ldap, $sr);
    @ldap_unbind($ldap);

    if (!is_array($entries) || !isset($entries['count'])) {
        return [];
    }

    $result = [];
    for ($i = 0; $i < $entries['count']; $i++) {
        $e = $entries[$i];
        $result[] = [
            'cn' => $e['cn'][0] ?? '',
            'displayName' => $e['displayname'][0] ?? ($e['cn'][0] ?? ''),
            'sAMAccountName' => $e['samaccountname'][0] ?? '',
            'userPrincipalName' => $e['userprincipalname'][0] ?? '',
            'mail' => $e['mail'][0] ?? '',
            'distinguishedName' => $e['distinguishedname'][0] ?? '',
        ];
    }

    usort($result, static function (array $a, array $b): int {
        return strcasecmp($a['displayName'], $b['displayName']);
    });

    return $result;
}

/**
 * Changes a user's password using LDAPS unicodePwd replace.
 *
 * @param string $distinguishedName User DN
 * @param string $newPassword New password
 */
function ad_change_user_password(string $distinguishedName, string $newPassword): void
{
    if ($distinguishedName === '' || $newPassword === '') {
        throw new InvalidArgumentException('DN и новый пароль обязательны.');
    }

    [$ldap, $baseDn] = ad_connect_and_bind(true);

    // AD requires quoted UTF-16LE password
    $quoted = '"' . $newPassword . '"';
    $encoded = mb_convert_encoding($quoted, 'UTF-16LE');

    $entry = ['unicodePwd' => $encoded];
    $ok = @ldap_mod_replace($ldap, $distinguishedName, $entry);
    if (!$ok) {
        $err = ldap_error($ldap);
        @ldap_unbind($ldap);
        throw new RuntimeException('Не удалось сменить пароль: ' . $err);
    }

    @ldap_unbind($ldap);
}
