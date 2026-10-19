# Arm the dark rules without the dark file: the body class is written whenever the site's logo is in force, so a
# site with no dark file hides nothing yet the class claims a variant exists.
s/if \(self::url\(\$variant, \$theme\) !== null && self::site_logo_in_force\(\$variant, \$flavour\)\) \{/if (self::site_logo_in_force(\$variant, \$flavour)) {/;
