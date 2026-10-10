<?php
// 2단계 인증(TOTP, RFC 6238) — 구글 OTP·Microsoft Authenticator·Authy 등과 호환. 외부 라이브러리 없음.
function b32_encode(string $bin): string {
    $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; $out = '';
    foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    foreach (str_split($bits, 5) as $ch) $out .= $a[bindec(str_pad($ch, 5, '0'))];
    return $out;
}
function b32_decode(string $s): string {
    $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $s = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s)); $bits = '';
    foreach (str_split($s) as $c) $bits .= str_pad(decbin(strpos($a, $c)), 5, '0', STR_PAD_LEFT);
    $out = ''; foreach (str_split($bits, 8) as $b) if (strlen($b) === 8) $out .= chr(bindec($b));
    return $out;
}
function totp_new_secret(): string { return b32_encode(random_bytes(20)); }
function totp_code(string $secret, ?int $counter = null): string {
    $counter ??= intdiv(time(), 30);
    $h = hash_hmac('sha1', pack('N2', 0, $counter), b32_decode($secret), true);
    $o = ord($h[19]) & 15;
    $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
}
/** 맞으면 사용된 카운터(재사용 방지에 저장), 틀리면 null. 앞뒤 30초 허용 */
function totp_verify(string $secret, string $code, int $lastUsed = 0): ?int {
    $code = preg_replace('/\D/', '', $code); if (strlen($code) !== 6) return null;
    $now = intdiv(time(), 30);
    for ($i = -1; $i <= 1; $i++) { $c = $now + $i; if ($c > $lastUsed && hash_equals(totp_code($secret, $c), $code)) return $c; }
    return null;
}
function totp_uri(string $secret, string $account, string $issuer): string {
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
}
/** 복구 코드 8개: [평문 목록, 저장용 해시 목록] */
function totp_new_recovery(): array {
    $plain = $hash = [];
    for ($i = 0; $i < 8; $i++) { $c = strtolower(bin2hex(random_bytes(5))); $c = substr($c, 0, 5) . '-' . substr($c, 5); $plain[] = $c; $hash[] = hash('sha256', $c); }
    return [$plain, $hash];
}
/** 복구 코드 사용: 맞으면 해당 해시를 목록에서 제거해 돌려줌 */
function totp_use_recovery(array $hashes, string $input): ?array {
    $h = hash('sha256', strtolower(trim($input)));
    foreach ($hashes as $i => $x) if (hash_equals((string)$x, $h)) { unset($hashes[$i]); return array_values($hashes); }
    return null;
}
