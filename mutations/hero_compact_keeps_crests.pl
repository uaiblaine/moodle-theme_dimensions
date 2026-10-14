# Drop the crests from a compact or folded hero again.
s/\$badges = branding::badges_for_output\(\$categoryid, \$branding\['badges'\]\);/\$badges = \$compact ? [] : branding::badges_for_output(\$categoryid, \$branding['badges']);/;
