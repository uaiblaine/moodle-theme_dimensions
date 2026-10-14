# Point the panel's fallback back at the registration class whose logo auth_loginsteps retired in
# 2026100609: without a compact logo the panel then always shows the wordmark.
s/public const LOGO_PROVIDER = '\\auth_loginsteps\\local\\shell_assets';/public const LOGO_PROVIDER = '\\auth_loginsteps\\local\\registration';/;
